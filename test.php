<?php
// /app/test.php
declare(strict_types=1);
require __DIR__ . '/db.php';

echo "=== Counts ===\n";
foreach (['sessions','terms','classes','subjects','students','teachers','class_arms','subject_classes','enrollments','teacher_subjects'] as $t) {
    printf("%-18s %d\n", $t, (int) fetch_val("SELECT COUNT(*) FROM $t"));
}

echo "\n=== Students enrolled in 2025/2026 ===\n";
$rows = fetch_all("
    SELECT s.admission_no, s.full_name, c.name || ca.arm AS class, ses.name AS session
    FROM enrollments e
    JOIN students s ON s.id = e.student_id
    JOIN class_arms ca ON ca.id = e.class_arm_id
    JOIN classes c ON c.id = ca.class_id
    JOIN sessions ses ON ses.id = e.session_id
    WHERE ses.name = '2025/2026'
    ORDER BY s.full_name
");
foreach ($rows as $r) {
    echo "  {$r['admission_no']}  {$r['full_name']}  ({$r['class']})\n";
}

echo "\n=== Teacher assignments (2025/2026) ===\n";
$rows = fetch_all("
    SELECT t.full_name AS teacher, sub.name AS subject, c.name || ca.arm AS class
    FROM teacher_subjects ts
    JOIN teachers t ON t.id = ts.teacher_id
    JOIN subjects sub ON sub.id = ts.subject_id
    JOIN class_arms ca ON ca.id = ts.class_arm_id
    JOIN classes c ON c.id = ca.class_id
    JOIN sessions ses ON ses.id = ts.session_id
    WHERE ses.name = '2025/2026'
    ORDER BY t.full_name
");
foreach ($rows as $r) {
    echo "  {$r['teacher']} → {$r['subject']} ({$r['class']})\n";
}

echo "\n=== Trigger test: bad assignment should fail ===\n";
try {
    // Physics is SS-only; try assigning to JSS1A
    $tid = fetch_val("SELECT id FROM teachers LIMIT 1");
    $sid = fetch_val("SELECT id FROM subjects WHERE name = 'Physics'");
    $caid = fetch_val("SELECT ca.id FROM class_arms ca JOIN classes c ON c.id=ca.class_id WHERE c.name='JSS1' AND ca.arm='A'");
    $sess = fetch_val("SELECT id FROM sessions WHERE name='2025/2026'");
    q("INSERT INTO teacher_subjects (teacher_id, subject_id, class_arm_id, session_id) VALUES (?,?,?,?)",
      [$tid, $sid, $caid, $sess]);
    echo "  UNEXPECTED: insert succeeded (trigger did not fire)\n";
} catch (PDOException $e) {
    echo "  OK — rejected: " . $e->getMessage() . "\n";
}