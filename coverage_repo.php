<?php
// /app/coverage_repo.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AppException.php';

/** Subjects expected for a class arm */
function subjects_for_arm(int $classArmId): array
{
    return fetch_all(
        "SELECT s.id, s.name, s.code
         FROM class_arm_subjects cas
         JOIN subjects s ON s.id = cas.subject_id
         WHERE cas.class_arm_id = ?
         ORDER BY s.name",
        [$classArmId]
    );
}

/**
 * Coverage for one class arm × session × term.
 * Returns per-subject status + totals.
 *
 * Result shape:
 * [
 *   'arm' => row,
 *   'session' => row,
 *   'term' => row,
 *   'students' => [ ['enrollment_id'=>.., 'full_name'=>.., 'admission_no'=>..], ... ],
 *   'subjects' => [
 *       [
 *         'id'=>.., 'name'=>.., 'code'=>..,
 *         'expected' => int,   // enrolled students minus exemptions
 *         'submitted' => int,  // students with submitted/approved result
 *         'exempted' => int,
 *         'missing' => int,
 *         'status' => 'complete'|'partial'|'empty',
 *         'missing_students' => [ ['enrollment_id'=>.., 'full_name'=>..], ... ],
 *       ], ...
 *   ],
 *   'totals' => ['expected'=>.., 'submitted'=>.., 'exempted'=>.., 'missing'=>.., 'coverage_pct'=>..],
 * ]
 */
function coverage_for_class(int $classArmId, int $sessionId, int $termId): array
{
    $arm = fetch_one(
        "SELECT ca.id, c.name AS class_name, ca.arm
         FROM class_arms ca JOIN classes c ON c.id = ca.class_id
         WHERE ca.id = ?", [$classArmId]);
    if (!$arm) throw new AppException('Class arm not found.');

    $session = fetch_one("SELECT * FROM sessions WHERE id = ?", [$sessionId]);
    $term    = fetch_one("SELECT * FROM terms WHERE id = ?", [$termId]);
    if (!$session || !$term) throw new AppException('Session or term not found.');

    // Active students only
    $students = fetch_all(
        "SELECT e.id AS enrollment_id, s.admission_no, s.full_name
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.class_arm_id = ? AND e.session_id = ?
           AND e.status = 'active'
         ORDER BY s.full_name",
        [$classArmId, $sessionId]
    );

    $subjects = subjects_for_arm($classArmId);

    // Preload exemptions and submitted results
    $exemptions = fetch_all(
        "SELECT enrollment_id, subject_id FROM subject_exemptions
         WHERE enrollment_id IN (SELECT id FROM enrollments WHERE class_arm_id = ? AND session_id = ?)",
        [$classArmId, $sessionId]
    );
    $exemptMap = [];
    foreach ($exemptions as $e) {
        $exemptMap[(int)$e['enrollment_id']][(int)$e['subject_id']] = true;
    }

    $submittedRows = fetch_all(
        "SELECT r.enrollment_id, r.subject_id
         FROM results r
         JOIN enrollments e ON e.id = r.enrollment_id
         WHERE e.class_arm_id = ? AND e.session_id = ?
           AND r.term_id = ?
           AND r.status IN ('submitted','approved')",
        [$classArmId, $sessionId, $termId]
    );
    $submittedMap = [];
    foreach ($submittedRows as $r) {
        $submittedMap[(int)$r['enrollment_id']][(int)$r['subject_id']] = true;
    }

    $subjectReport = [];
    $totals = ['expected'=>0, 'submitted'=>0, 'exempted'=>0, 'missing'=>0];

    foreach ($subjects as $sub) {
        $sid = (int)$sub['id'];
        $expected = 0; $submitted = 0; $exempted = 0;
        $missingStudents = [];

        foreach ($students as $st) {
            $eid = (int)$st['enrollment_id'];
            $isExempt    = isset($exemptMap[$eid][$sid]);
            $hasSubmitted = isset($submittedMap[$eid][$sid]);

            if ($isExempt) {
                $exempted++;
                continue;
            }
            $expected++;
            if ($hasSubmitted) {
                $submitted++;
            } else {
                $missingStudents[] = [
                    'enrollment_id' => $eid,
                    'full_name'     => $st['full_name'],
                ];
            }
        }

        $missing = $expected - $submitted;
        $status = $missing === 0 ? 'complete' : ($submitted === 0 ? 'empty' : 'partial');

        $subjectReport[] = [
            'id'               => $sid,
            'name'             => $sub['name'],
            'code'             => $sub['code'],
            'expected'         => $expected,
            'submitted'        => $submitted,
            'exempted'         => $exempted,
            'missing'          => $missing,
            'status'           => $status,
            'missing_students' => $missingStudents,
        ];

        $totals['expected']  += $expected;
        $totals['submitted'] += $submitted;
        $totals['exempted']  += $exempted;
        $totals['missing']   += $missing;
    }

    $totals['coverage_pct'] = $totals['expected'] > 0
        ? round(100 * $totals['submitted'] / $totals['expected'], 1)
        : 100.0;

    return [
        'arm'      => $arm,
        'session'  => $session,
        'term'     => $term,
        'students' => $students,
        'subjects' => $subjectReports,
        'totals'   => $totals,
    ];
}

/** Coverage summary across every subject the teacher teaches in a session. */
function coverage_for_teacher(int $teacherId, int $sessionId, int $termId): array
{
    $assignments = fetch_all(
        "SELECT ts.subject_id, ts.class_arm_id,
                sub.name AS subject_name, sub.code,
                c.name AS class_name, ca.arm
         FROM teacher_subjects ts
         JOIN subjects sub ON sub.id = ts.subject_id
         JOIN class_arms ca ON ca.id = ts.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE ts.teacher_id = ? AND ts.session_id = ?
         ORDER BY c.name, ca.arm, sub.name",
        [$teacherId, $sessionId]
    );

    $out = [];
    foreach ($assignments as $a) {
        $sid = (int)$a['subject_id'];
        $caid = (int)$a['class_arm_id'];

        // Expected (active, non-exempt students in this arm)
        $expected = (int) fetch_val(
            "SELECT COUNT(*) FROM enrollments e
             WHERE e.class_arm_id = ? AND e.session_id = ? AND e.status = 'active'
               AND NOT EXISTS (
                 SELECT 1 FROM subject_exemptions se
                 WHERE se.enrollment_id = e.id AND se.subject_id = ?
               )",
            [$caid, $sessionId, $sid]
        );

        $submitted = (int) fetch_val(
            "SELECT COUNT(*) FROM results r
             JOIN enrollments e ON e.id = r.enrollment_id
             WHERE e.class_arm_id = ? AND e.session_id = ?
               AND r.subject_id = ? AND r.term_id = ?
               AND r.status IN ('submitted','approved')",
            [$caid, $sessionId, $sid, $termId]
        );

        $missing = max(0, $expected - $submitted);
        $out[] = [
            'subject_id'    => $sid,
            'subject_name'  => $a['subject_name'],
            'code'          => $a['code'],
            'class_label'   => $a['class_name'] . ' ' . $a['arm'],
            'class_arm_id'  => $caid,
            'expected'      => $expected,
            'submitted'     => $submitted,
            'missing'       => $missing,
            'status'        => $missing === 0 ? 'complete'
                              : ($submitted === 0 ? 'empty' : 'partial'),
        ];
    }
    return $out;
}

/* =========================================================
 * ARM SUBJECT CONFIG
 * ========================================================= */

function arm_subject_set(int $classArmId, int $subjectId, bool $on): void
{
    if ($on) {
        q("INSERT OR IGNORE INTO class_arm_subjects (class_arm_id, subject_id) VALUES (?, ?)",
          [$classArmId, $subjectId]);
    } else {
        q("DELETE FROM class_arm_subjects WHERE class_arm_id = ? AND subject_id = ?",
          [$classArmId, $subjectId]);
    }
}

/* =========================================================
 * EXEMPTIONS
 * ========================================================= */

function exemption_add(int $enrollmentId, int $subjectId, string $reason, ?int $userId): void
{
    if (!fetch_val("SELECT 1 FROM enrollments WHERE id = ?", [$enrollmentId])) {
        throw new AppException('Enrollment not found.');
    }
    if (!fetch_val("SELECT 1 FROM subjects WHERE id = ?", [$subjectId])) {
        throw new AppException('Subject not found.');
    }
    q("INSERT INTO subject_exemptions
        (enrollment_id, subject_id, reason, exempted_by, exempted_at)
       VALUES (?, ?, ?, ?, ?)
       ON CONFLICT(enrollment_id, subject_id) DO UPDATE SET
         reason = excluded.reason,
         exempted_by = excluded.exempted_by,
         exempted_at = excluded.exempted_at",
      [$enrollmentId, $subjectId, $reason ?: null, $userId, date('Y-m-d H:i:s')]);
}

function exemption_remove(int $enrollmentId, int $subjectId): void
{
    q("DELETE FROM subject_exemptions WHERE enrollment_id = ? AND subject_id = ?",
      [$enrollmentId, $subjectId]);
}

function exemptions_for_class(int $classArmId, int $sessionId): array
{
    return fetch_all(
        "SELECT se.id, e.id AS enrollment_id, e.student_id,
                s.admission_no, s.full_name,
                sub.id AS subject_id, sub.name AS subject_name,
                se.reason, se.exempted_at
         FROM subject_exemptions se
         JOIN enrollments e ON e.id = se.enrollment_id
         JOIN students s ON s.id = e.student_id
         JOIN subjects sub ON sub.id = se.subject_id
         WHERE e.class_arm_id = ? AND e.session_id = ?
         ORDER BY s.full_name, sub.name",
        [$classArmId, $sessionId]
    );
}