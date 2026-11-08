<?php
// /app/seed_admin.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$existing = fetch_val("SELECT id FROM users WHERE username = 'admin'");
if ($existing) {
    echo "admin already exists (id={$existing}). Skipping.\n";
    exit(0);
}

$hash = password_hash('admin123', PASSWORD_DEFAULT);
q("INSERT INTO users (username, password_hash, role, teacher_id, active, created_at)
   VALUES (?, ?, 'admin', NULL, 1, ?)",
  ['admin', $hash, date('Y-m-d H:i:s')]);

echo "Created admin / admin123. Change it after first login.\n";