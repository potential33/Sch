<?php
// /app/migrate_student_auth.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

// 1. Rebuild users with expanded role CHECK + student_id + must_change_password
$cols = fetch_all("PRAGMA table_info(users)");
$hasStudentId = false;
$hasMustChange = false;
foreach ($cols as $c) {
    if ($c['name'] === 'student_id')          $hasStudentId = true;
    if ($c['name'] === 'must_change_password') $hasMustChange = true;
}

if (!$hasStudentId || !$hasMustChange) {
    echo "Rebuilding users table…\n";
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('BEGIN');

    $pdo->exec("CREATE TABLE users_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK (role IN ('admin','teacher','student')),
        teacher_id INTEGER REFERENCES teachers(id) ON DELETE SET NULL,
        student_id INTEGER REFERENCES students(id) ON DELETE SET NULL,
        active INTEGER NOT NULL DEFAULT 1,
        must_change_password INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )");

    // Copy existing rows, adding defaults for new columns
    $existing = fetch_all("SELECT * FROM users");
    foreach ($existing as $u) {
        q("INSERT INTO users_new
            (id, username, password_hash, role, teacher_id, student_id,
             active, must_change_password, created_at)
           VALUES (?,?,?,?,?,NULL,?,0,?)",
          [$u['id'], $u['username'], $u['password_hash'], $u['role'],
           $u['teacher_id'], $u['active'], $u['created_at']]);
    }

    $pdo->exec("DROP TABLE users");
    $pdo->exec("ALTER TABLE users_new RENAME TO users");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_teacher ON users(teacher_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_student ON users(student_id)");
    $pdo->exec('COMMIT');
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "users rebuilt.\n";
} else {
    echo "users already has student_id + must_change_password.\n";
}

// 2. terms.next_resumption_date
$termCols = array_column(fetch_all("PRAGMA table_info(terms)"), 'name');
if (!in_array('next_resumption_date', $termCols, true)) {
    $pdo->exec("ALTER TABLE terms ADD COLUMN next_resumption_date TEXT");
    echo "Added terms.next_resumption_date.\n";
} else {
    echo "terms.next_resumption_date already exists.\n";
}

// 3. term_reports
$pdo->exec("
CREATE TABLE IF NOT EXISTS term_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL REFERENCES enrollments(id) ON DELETE CASCADE,
    term_id INTEGER NOT NULL REFERENCES terms(id),
    class_teacher_remark TEXT,
    principal_remark TEXT,
    status TEXT NOT NULL DEFAULT 'draft'
           CHECK (status IN ('draft','published')),
    published_at TEXT,
    UNIQUE (enrollment_id, term_id)
)");
echo "term_reports ready.\n";

echo "Migration complete.\n";