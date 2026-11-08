<?php
// /app/create_student_users.php
declare(strict_types=1);
require __DIR__ . '/db.php';

// Only students who are currently enrolled in at least one session
$students = fetch_all(
    "SELECT DISTINCT s.id, s.admission_no, s.full_name
     FROM students s
     JOIN enrollments e ON e.student_id = s.id
     WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.student_id = s.id)
     ORDER BY s.full_name"
);

if (!$students) {
    echo "No students need a user account.\n";
    exit(0);
}

$hash = password_hash('password123', PASSWORD_DEFAULT);
$now  = date('Y-m-d H:i:s');
$n = 0;

foreach ($students as $s) {
    $username = strtolower($s['admission_no']);
    if (fetch_val("SELECT 1 FROM users WHERE username = ?", [$username])) {
        echo "SKIP {$s['full_name']}: username {$username} already taken.\n";
        continue;
    }
    q("INSERT INTO users
        (username, password_hash, role, teacher_id, student_id, active, must_change_password, created_at)
       VALUES (?, ?, 'student', NULL, ?, 1, 1, ?)",
      [$username, $hash, $s['id'], $now]);
    echo "Created {$username} (student: {$s['full_name']}).\n";
    $n++;
}

echo "\nDone. {$n} student account(s) created.\n";
echo "Default password: password123 — MUST be changed on first login.\n";