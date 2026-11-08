<?php
// /app/report_repo.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AppException.php';

/** All grading bands, sorted high-to-low. Returns ['A1'=>75, ...] */
function grade_bands(): array
{
    $rows = fetch_all("SELECT name, min_score FROM grading_bands ORDER BY sort_order");
    $out = [];
    foreach ($rows as $r) $out[$r['name']] = (float) $r['min_score'];
    return $out;
}

/** Map a numeric total to its band name ('A1' … 'F9') */
function grade_band_for(float $total, ?array $bands = null): string
{
    $bands = $bands ?? grade_bands();
    foreach ($bands as $name => $min) {
        if ($total >= $min) return $name;
    }
    return array_key_last($bands);
}

/**
 * Full term result for one student.
 * Returns:
 * [
 *   'student'    => row,
 *   'enrollment' => row (with class_name + arm),
 *   'term'       => row,
 *   'session'    => row,
 *   'subjects'   => [ ['subject'=>..., 'ca1'=>..., ..., 'total'=>..., 'grade'=>...], ... ],
 *   'totals'     => ['sum'=>float, 'avg'=>float, 'count'=>int],
 *   'position'   => int|null,
 *   'class_size' => int,
 *   'class_avg'  => float|null,
 * ]
 * Returns null if the student isn't enrolled in that session.
 */
function report_student_term(int $studentId, int $sessionId, int $termId): ?array
{
    $enrollment = fetch_one(
        "SELECT e.id AS enrollment_id, e.class_arm_id,
                c.name AS class_name, ca.arm
         FROM enrollments e
         JOIN class_arms ca ON ca.id = e.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE e.student_id = ? AND e.session_id = ?",
        [$studentId, $sessionId]
    );
    if (!$enrollment) return null;

    $student = fetch_one("SELECT * FROM students WHERE id = ?", [$studentId]);
    $term    = fetch_one("SELECT * FROM terms WHERE id = ?", [$termId]);
    $session = fetch_one("SELECT * FROM sessions WHERE id = ?", [$sessionId]);
    if (!$student || !$term || !$session) return null;
    if ((int)$term['session_id'] !== $sessionId) {
        throw new AppException('Term does not belong to that session.');
    }

    // Subject-level results — only submitted or approved
    $rows = fetch_all(
        "SELECT sub.id AS subject_id, sub.name AS subject_name, sub.code,
                r.ca1, r.ca2, r.ca3, r.exam, r.total, r.status
         FROM results r
         JOIN subjects sub ON sub.id = r.subject_id
         WHERE r.enrollment_id = ? AND r.term_id = ?
           AND r.status IN ('submitted','approved')
         ORDER BY sub.name",
        [$enrollment['enrollment_id'], $termId]
    );

    $bands  = grade_bands();
    $subjects = [];
    $sum = 0.0; $count = 0;

    foreach ($rows as $r) {
        $total = $r['total'] !== null ? (float)$r['total'] : 0.0;
        $sum  += $total; $count++;
        $subjects[] = [
            'subject_id'   => (int)$r['subject_id'],
            'subject_name' => $r['subject_name'],
            'code'         => $r['code'],
            'ca1'          => $r['ca1'],
            'ca2'          => $r['ca2'],
            'ca3'          => $r['ca3'],
            'exam'         => $r['exam'],
            'total'        => $total,
            'grade'        => grade_band_for($total, $bands),
            'status'       => $r['status'],
        ];
    }

    $avg = $count > 0 ? $sum / $count : 0.0;

    // Class ranking: sum of totals across (submitted/approved) subjects
    $rankData = _class_totals_for_term(
        $enrollment['class_arm_id'], $sessionId, $termId
    );
    $position = null;
    foreach ($rankData['ranking'] as $idx => $r) {
        if ((int)$r['student_id'] === $studentId) {
            $position = $r['position'];
            break;
        }
    }

    return [
        'student'    => $student,
        'enrollment' => $enrollment,
        'term'       => $term,
        'session'    => $session,
        'subjects'   => $subjects,
        'totals'     => ['sum' => $sum, 'avg' => $avg, 'count' => $count],
        'position'   => $position,
        'class_size' => $rankData['class_size'],
        'class_avg'  => $rankData['class_avg'],
    ];
}

/**
 * Broad class result for one class arm + term.
 * Returns:
 * [
 *   'class_arm'  => row (with class_name + arm),
 *   'term'       => row,
 *   'session'    => row,
 *   'subjects'   => [ ['id'=>..., 'name'=>..., 'code'=>...], ... ],
 *   'rows'       => [
 *       ['student'=>..., 'enrollment_id'=>..., 'scores'=>[
 *           subject_id => ['total'=>..., 'grade'=>...], ...
 *       ], 'sum'=>..., 'avg'=>..., 'position'=>..., 'has_any'=>bool],
 *       ...
 *   ],
 *   'class_avg'  => float,
 * ]
 */
function report_class_term(int $classArmId, int $sessionId, int $termId): ?array
{
    $classArm = fetch_one(
        "SELECT ca.id, c.name AS class_name, ca.arm
         FROM class_arms ca JOIN classes c ON c.id = ca.class_id
         WHERE ca.id = ?", [$classArmId]);
    if (!$classArm) return null;

    $term    = fetch_one("SELECT * FROM terms WHERE id = ?", [$termId]);
    $session = fetch_one("SELECT * FROM sessions WHERE id = ?", [$sessionId]);
    if (!$term || !$session) return null;
    if ((int)$term['session_id'] !== $sessionId) {
        throw new AppException('Term does not belong to that session.');
    }

    // Enrolled students
    $students = fetch_all(
        "SELECT e.id AS enrollment_id, s.id AS student_id,
                s.admission_no, s.full_name
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.class_arm_id = ? AND e.session_id = ?
         ORDER BY s.full_name",
        [$classArmId, $sessionId]
    );

    // All subjects with any submitted/approved result in this class for this term
    $subjects = fetch_all(
        "SELECT DISTINCT sub.id, sub.name, sub.code
         FROM results r
         JOIN subjects sub ON sub.id = r.subject_id
         JOIN enrollments e ON e.id = r.enrollment_id
         WHERE e.class_arm_id = ? AND r.term_id = ?
           AND r.status IN ('submitted','approved')
         ORDER BY sub.name",
        [$classArmId, $termId]
    );

    // All scores: enrollment_id × subject_id → total
    $scoreRows = fetch_all(
        "SELECT r.enrollment_id, r.subject_id, r.total
         FROM results r
         JOIN enrollments e ON e.id = r.enrollment_id
         WHERE e.class_arm_id = ? AND r.term_id = ?
           AND r.status IN ('submitted','approved')",
        [$classArmId, $termId]
    );
    $scoreMap = [];
    foreach ($scoreRows as $r) {
        $scoreMap[(int)$r['enrollment_id']][(int)$r['subject_id']] = (float)$r['total'];
    }

    $bands = grade_bands();
    $rows  = [];
    $classSum = 0.0; $classCount = 0;

    foreach ($students as $s) {
        $scores = [];
        $sum = 0.0; $n = 0;
        foreach ($subjects as $sub) {
            $sid = (int)$sub['id'];
            $t   = $scoreMap[(int)$s['enrollment_id']][$sid] ?? null;
            $scores[$sid] = [
                'total' => $t,
                'grade' => $t !== null ? grade_band_for($t, $bands) : null,
            ];
            if ($t !== null) { $sum += $t; $n++; }
        }
        $rows[] = [
            'student'        => $s,
            'enrollment_id'  => (int)$s['enrollment_id'],
            'scores'         => $scores,
            'sum'            => $sum,
            'avg'            => $n > 0 ? $sum / $n : 0.0,
            'count'          => $n,
            'has_any'        => $n > 0,
            'position'       => null, // filled below
        ];
        $classSum   += $sum;
        $classCount += $n;
    }

    // Competition ranking: students with 0 subjects get no position
    $sorted = array_filter($rows, fn($r) => $r['has_any']);
    usort($sorted, fn($a, $b) => $b['sum'] <=> $a['sum']);
    $pos = 0; $prevSum = null; $prevPos = 0;
    foreach ($sorted as $i => &$r) {
        $pos++;
        if ($prevSum !== null && $r['sum'] === $prevSum) {
            $r['position'] = $prevPos;
        } else {
            $r['position'] = $pos;
            $prevSum = $r['sum'];
            $prevPos = $pos;
        }
    }
    unset($r);

    // Merge positions back
    $byEnrollment = [];
    foreach ($sorted as $r) $byEnrollment[$r['enrollment_id']] = $r['position'];
    foreach ($rows as &$r) {
        if (isset($byEnrollment[$r['enrollment_id']])) {
            $r['position'] = $byEnrollment[$r['enrollment_id']];
        }
    }
    unset($r);

    return [
        'class_arm' => $classArm,
        'term'      => $term,
        'session'   => $session,
        'subjects'  => $subjects,
        'rows'      => $rows,
        'class_avg' => $classCount > 0 ? $classSum / $classCount : 0.0,
    ];
}

/** Internal: totals + rankings for one class arm + term */
function _class_totals_for_term(int $classArmId, int $sessionId, int $termId): array
{
    $rows = fetch_all(
        "SELECT e.id AS enrollment_id, s.id AS student_id, s.full_name,
                SUM(r.total) AS sum_total, COUNT(r.id) AS n
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         LEFT JOIN results r
           ON r.enrollment_id = e.id
          AND r.term_id = ?
          AND r.status IN ('submitted','approved')
         WHERE e.class_arm_id = ? AND e.session_id = ?
         GROUP BY e.id, s.id, s.full_name
         ORDER BY sum_total DESC",
        [$termId, $classArmId, $sessionId]
    );

    // Sum of per-student averages across the class → class average
    $totalSum = 0.0; $withCount = 0;
    $ranking = [];
    $pos = 0; $prevSum = null; $prevPos = 0;
    foreach ($rows as $r) {
        $sum = $r['sum_total'] !== null ? (float)$r['sum_total'] : 0.0;
        $n   = (int)$r['n'];
        if ($n > 0) {
            $totalSum += $sum;
            $withCount++;
        }
        $pos++;
        if ($prevSum !== null && $sum === $prevSum) {
            $p = $prevPos;
        } else {
            $p = $pos; $prevSum = $sum; $prevPos = $pos;
        }
        $ranking[] = [
            'student_id'    => (int)$r['student_id'],
            'enrollment_id' => (int)$r['enrollment_id'],
            'full_name'     => $r['full_name'],
            'sum'           => $sum,
            'count'         => $n,
            'position'      => $n > 0 ? $p : null,
        ];
    }

    return [
        'ranking'     => $ranking,
        'class_size'  => count($rows),
        'class_avg'   => $withCount > 0 ? $totalSum / $withCount : null,
    ];
}