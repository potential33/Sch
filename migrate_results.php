<?php
// /app/migrate_results.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS grading_components (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    max_score   REAL NOT NULL,
    sort_order  INTEGER NOT NULL,
    active      INTEGER NOT NULL DEFAULT 1
)");

$pdo->exec("
CREATE TABLE IF NOT EXISTS results (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id    INTEGER NOT NULL REFERENCES enrollments(id) ON DELETE CASCADE,
    subject_id       INTEGER NOT NULL REFERENCES subjects(id),
    term_id          INTEGER NOT NULL REFERENCES terms(id),
    ca1              REAL,
    ca2              REAL,
    ca3              REAL,
    exam             REAL,
    total            REAL,
    status           TEXT NOT NULL DEFAULT 'draft'
                     CHECK (status IN ('draft','submitted','approved')),
    entered_by       INTEGER REFERENCES teachers(id),
    entered_at       TEXT,
    updated_at       TEXT,
    UNIQUE (enrollment_id, subject_id, term_id)
)");

$pdo->exec("
CREATE TABLE IF NOT EXISTS result_anomalies (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id  INTEGER NOT NULL REFERENCES enrollments(id) ON DELETE CASCADE,
    term_id        INTEGER NOT NULL REFERENCES terms(id),
    subject_id     INTEGER REFERENCES subjects(id),
    kind           TEXT NOT NULL
                   CHECK (kind IN ('all_empty','exam_missing_with_ca','over_max')),
    detail         TEXT,
    flagged_at     TEXT NOT NULL,
    resolved       INTEGER NOT NULL DEFAULT 0,
    UNIQUE (enrollment_id, term_id, kind, subject_id)
)");

// Weight seed (only if empty)
$n = (int) fetch_val("SELECT COUNT(*) FROM grading_components");
if ($n === 0) {
    $weights = [
        ['CA1',  10, 1],
        ['CA2',  10, 2],
        ['CA3',  10, 3],
        ['EXAM', 70, 4],
    ];
    foreach ($weights as [$name, $max, $order]) {
        q("INSERT INTO grading_components (name, max_score, sort_order, active)
           VALUES (?, ?, ?, 1)", [$name, $max, $order]);
    }
    echo "Seeded grading_components.\n";
} else {
    echo "grading_components already has {$n} rows — skipped seed.\n";
}

// Indexes for hot queries
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_results_enrollment_term
            ON results(enrollment_id, term_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_results_subject_term
            ON results(subject_id, term_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_anomalies_term
            ON result_anomalies(term_id, resolved)");

echo "Migration complete.\n";