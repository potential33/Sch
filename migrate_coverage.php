<?php
// /app/migrate_coverage.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

// 1. class_arm_subjects
$pdo->exec("
CREATE TABLE IF NOT EXISTS class_arm_subjects (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    class_arm_id INTEGER NOT NULL REFERENCES class_arms(id) ON DELETE CASCADE,
    subject_id   INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE (class_arm_id, subject_id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_cas_arm ON class_arm_subjects(class_arm_id)");
echo "class_arm_subjects ready.\n";

// 2. subject_exemptions
$pdo->exec("
CREATE TABLE IF NOT EXISTS subject_exemptions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL REFERENCES enrollments(id) ON DELETE CASCADE,
    subject_id    INTEGER NOT NULL REFERENCES subjects(id),
    reason        TEXT,
    exempted_by   INTEGER REFERENCES users(id),
    exempted_at   TEXT NOT NULL,
    UNIQUE (enrollment_id, subject_id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_exemptions_enr ON subject_exemptions(enrollment_id)");
echo "subject_exemptions ready.\n";

// 3. enrollments.status
$cols = array_column(fetch_all("PRAGMA table_info(enrollments)"), 'name');
if (!in_array('status', $cols, true)) {
    $pdo->exec("ALTER TABLE enrollments ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
    $pdo->exec("ALTER TABLE enrollments ADD COLUMN status_changed_at TEXT");
    $pdo->exec("ALTER TABLE enrollments ADD COLUMN status_reason TEXT");
    echo "enrollments.status added.\n";
} else {
    echo "enrollments.status already exists.\n";
}

echo "Migration complete.\n";