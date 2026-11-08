<?php
// /app/migrate_auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('admin','teacher')),
    teacher_id    INTEGER REFERENCES teachers(id) ON DELETE SET NULL,
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL
)");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_teacher ON users(teacher_id)");

echo "users table ready.\n";