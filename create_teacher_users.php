<?php
// /app/create_teacher_users.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$teachers = fetch_all(
    "SELECT t.id, t.full_name, t.email
     FROM teachers t
     WHERE t.email IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM users u WHERE u.teacher_id = t.id)
     ORDER BY t.full_name"
);

if (!$teachers) {
    echo "No teachers need a user account.\n";
    exit(0);
}

$hash = password_hash('password123', PASSWORD_DEFAULT);
$now  = date('Y-m-d H:i:s');
$n = 0;

foreach ($teachers as $t) {
    $username = strtolower($t['email']);
    // Skip if username collides with an existing user
    if (fetch_val("SELECT 1 FROM users WHERE username = ?", [$username])) {
        echo "SKIP {$t['full_name']}: username {$username} already taken.\n";
        continue;
    }
    q("INSERT INTO users (username, password_hash, role, teacher_id, active, created_at)
       VALUES (?, ?, 'teacher', ?, 1, ?)",
      [$username, $hash, $t['id'], $now]);
    echo "Created {$username} (teacher: {$t['full_name']}).\n";
    $n++;
}
echo "Done. {$n} user(s) created. Default password: password123\n";