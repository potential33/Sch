<?php
// /app/migrate_restrict.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();
$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->exec('BEGIN');

// Rebuild class_arms with explicit RESTRICT
$pdo->exec("CREATE TABLE class_arms_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE RESTRICT,
    arm TEXT NOT NULL,
    UNIQUE (class_id, arm)
)");
$pdo->exec("INSERT INTO class_arms_new (id, class_id, arm) SELECT id, class_id, arm FROM class_arms");
$pdo->exec("DROP TABLE class_arms");
$pdo->exec("ALTER TABLE class_arms_new RENAME TO class_arms");

// Rebuild sessions with explicit RESTRICT
$pdo->exec("CREATE TABLE sessions_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)");
$pdo->exec("INSERT INTO sessions_new (id, name) SELECT id, name FROM sessions");
$pdo->exec("DROP TABLE sessions");
$pdo->exec("ALTER TABLE sessions_new RENAME TO sessions");

$pdo->exec('COMMIT');
$pdo->exec('PRAGMA foreign_keys = ON');
echo "Migration done. FKs back ON.\n";
echo "Check: " . fetch_val('PRAGMA foreign_key_check') ?? 'OK';
echo "\n";