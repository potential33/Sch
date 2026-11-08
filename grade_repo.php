<?php
// /app/grade_repo.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/grading.php';

/** Active weight set: ['CA1'=>10, 'CA2'=>10, 'CA3'=>10, 'EXAM'=>70] */
function grade_limits(): array
{
    $rows = fetch_all("SELECT name, max_score FROM grading_components
                       WHERE active = 1 ORDER BY sort_order");
    $out = [];
    foreach ($rows as $r) $out[$r['name']] = (float) $r['max_score'];
    return $out;
}

/** Fetch one result row (or null) */
function grade_get(int $enrollmentId, int $subjectId, int $termId): ?array
{
    return fetch_one(
        "SELECT * FROM results
         WHERE enrollment_id = ? AND subject_id = ? AND term_id = ?",
        [$enrollmentId, $subjectId, $termId]
    );
}

/**
 * Save a grade entry for one student/subject/term.
 *
 * @param array $input Raw $_POST values keyed CA1,CA2,CA3,EXAM
 * @return array {
 *   ok: bool,
 *   status?: 'draft'|'submitted',
 *   total?: ?float,
 *   missing?: string[],
 *   errors?: string[]
 * }
 */
function grade_save(
    int $enrollmentId,
    int $subjectId,
    int $termId,
    array $input,
    ?int $teacherId = null
): array {
    if (!fetch_val("SELECT 1 FROM enrollments WHERE id = ?", [$enrollmentId])) {
        throw new AppException('Enrollment not found.');
    }
    if (!fetch_val("SELECT 1 FROM subjects WHERE id = ?", [$subjectId])) {
        throw new AppException('Subject not found.');
    }
    if (!fetch_val("SELECT 1 FROM terms WHERE id = ?", [$termId])) {
        throw new AppException('Term not found.');
    }

    // Lock: refuse edits to approved results
    $existing = grade_get($enrollmentId, $subjectId, $termId);
    if ($existing && $existing['status'] === 'approved') {
        return ['ok' => false, 'errors' => ['This result has been approved and is locked.']];
    }

    $limits = grade_limits();
    $v = grade_validate($input, $limits);

    if ($v['status'] === 'blocked') {
        return ['ok' => false, 'errors' => $v['errors']];
    }

    $now = date('Y-m-d H:i:s');
    $pdo = db();

    if ($v['status'] === 'empty') {
        // Log anomaly; do NOT write a results row
        try {
            q("INSERT INTO result_anomalies
                (enrollment_id, term_id, subject_id, kind, detail, flagged_at, resolved)
               VALUES (?, ?, ?, 'all_empty', ?, ?, 0)
               ON CONFLICT(enrollment_id, term_id, kind, subject_id) DO UPDATE SET
                 flagged_at = excluded.flagged_at,
                 resolved   = 0",
              [$enrollmentId, $termId, $subjectId,
               'All score fields empty on save', $now]);
        } catch (PDOException $e) {
            // If uniqueness on NULL subject_id is odd, swallow — anomaly is advisory
        }
        return ['ok' => false, 'errors' => [
            'All scores are empty. If the student has left, mark them inactive; otherwise enter at least EXAM.'
        ]];
    }

    $dbStatus = $v['status'] === 'complete' ? 'submitted' : 'draft';

    // Upsert
    q("INSERT INTO results
    (enrollment_id, subject_id, term_id, ca1, ca2, ca3, exam, total,
     status, entered_by, entered_at, updated_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
   ON CONFLICT(enrollment_id, subject_id, term_id) DO UPDATE SET
     ca1 = excluded.ca1, ca2 = excluded.ca2, ca3 = excluded.ca3,
     exam = excluded.exam, total = excluded.total, status = excluded.status,
     entered_by = excluded.entered_by,
     updated_at = excluded.updated_at",
  [$enrollmentId, $subjectId, $termId,
   $v['values']['CA1'] ?? null, $v['values']['CA2'] ?? null,
   $v['values']['CA3'] ?? null, $v['values']['EXAM'] ?? null,
   $v['total'], $dbStatus, $teacherId, $now, $now]);
    // If this save resolves an earlier anomaly, clear it
    q("UPDATE result_anomalies SET resolved = 1
       WHERE enrollment_id = ? AND term_id = ? AND subject_id = ? AND resolved = 0",
      [$enrollmentId, $termId, $subjectId]);

    return [
        'ok'      => true,
        'status'  => $dbStatus,
        'total'   => $v['total'],
        'missing' => $v['missing'],
    ];
}

/**
 * Grid for one subject × class arm × session × term.
 * Returns enrollment rows joined with any existing result.
 */
function grades_for_class_term(
    int $subjectId,
    int $classArmId,
    int $sessionId,
    int $termId
): array {
    return fetch_all(
        "SELECT e.id AS enrollment_id,
                s.id AS student_id, s.admission_no, s.full_name,
                r.id AS result_id, r.ca1, r.ca2, r.ca3, r.exam, r.total,
                r.status AS result_status
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         LEFT JOIN results r
           ON r.enrollment_id = e.id
          AND r.subject_id = ?
          AND r.term_id = ?
         WHERE e.class_arm_id = ? AND e.session_id = ?
         ORDER BY s.full_name",
        [$subjectId, $termId, $classArmId, $sessionId]
    );
}

/**
 * Bulk flip: all rows for (subject × class × session × term)
 * that are currently 'draft' become 'submitted'. Refuses if any row is
 * still draft with missing data (partial) — those must be completed first,
 * or marked as anomalies and resolved.
 *
 * Returns ['ok' => bool, 'flipped' => int, 'errors' => string[]].
 */
function grade_submit_term_for_class(
    int $subjectId,
    int $classArmId,
    int $sessionId,
    int $termId
): array {
    $pdo = db();

    // Any blocked (missing exam w/ CA) or partial rows?
    $rows = fetch_all(
        "SELECT e.id AS enrollment_id, s.full_name,
                r.status, r.exam, r.ca1, r.ca2, r.ca3
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         LEFT JOIN results r
           ON r.enrollment_id = e.id
          AND r.subject_id = ?
          AND r.term_id = ?
         WHERE e.class_arm_id = ? AND e.session_id = ?",
        [$subjectId, $termId, $classArmId, $sessionId]
    );

    $problems = [];
    foreach ($rows as $r) {
        $hasAny = $r['ca1'] !== null || $r['ca2'] !== null
               || $r['ca3'] !== null || $r['exam'] !== null;
        if ($r['status'] === null) {
            if ($hasAny) {
                $problems[] = "{$r['full_name']}: incomplete draft";
            } else {
                $problems[] = "{$r['full_name']}: no grades entered";
            }
        } elseif ($r['status'] === 'draft') {
            $problems[] = "{$r['full_name']}: still draft";
        }
    }

    if ($problems) {
        return ['ok' => false, 'flipped' => 0, 'errors' => $problems];
    }

    $pdo->beginTransaction();
    try {
        $affected = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'submitted') {
                q("UPDATE results SET status = 'submitted', updated_at = ?
                   WHERE enrollment_id = ? AND subject_id = ? AND term_id = ?",
                  [date('Y-m-d H:i:s'), $r['enrollment_id'], $subjectId, $termId]);
                $affected++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['ok' => true, 'flipped' => $affected, 'errors' => []];
}

/** List unresolved anomalies for a term (admin review) */
function anomalies_list(int $sessionId, ?int $termId = null): array
{
    $sql = "SELECT ra.id, ra.kind, ra.detail, ra.flagged_at,
                   s.admission_no, s.full_name,
                   c.name || ' ' || ca.arm AS class_label,
                   sub.name AS subject, ses.name AS session, t.name AS term
            FROM result_anomalies ra
            JOIN enrollments e ON e.id = ra.enrollment_id
            JOIN students s ON s.id = e.student_id
            JOIN class_arms ca ON ca.id = e.class_arm_id
            JOIN classes c ON c.id = ca.class_id
            JOIN sessions ses ON ses.id = e.session_id
            JOIN terms t ON t.id = ra.term_id
            LEFT JOIN subjects sub ON sub.id = ra.subject_id
            WHERE ses.id = ? AND ra.resolved = 0";
    $params = [$sessionId];
    if ($termId !== null) { $sql .= " AND ra.term_id = ?"; $params[] = $termId; }
    $sql .= " ORDER BY s.full_name, sub.name";

    return fetch_all($sql, $params);
}

function anomaly_resolve(int $id): void
{
    if (!fetch_val("SELECT 1 FROM result_anomalies WHERE id = ?", [$id])) {
        throw new AppException('Anomaly not found.');
    }
    q("UPDATE result_anomalies SET resolved = 1 WHERE id = ?", [$id]);
}