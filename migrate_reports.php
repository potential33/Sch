<?php
// /app/migrate_reports.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS grading_bands (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    min_score   REAL NOT NULL,
    label       TEXT,
    sort_order  INTEGER NOT NULL
)");

$n = (int) fetch_val("SELECT COUNT(*) FROM grading_bands");
if ($n === 0) {
    $bands = [
        ['A1', 75, 'Excellent',  1],
        ['B2', 70, 'Very Good',  2],
        ['B3', 65, 'Good',       3],
        ['C4', 60, 'Credit',     4],
        ['C5', 55, 'Credit',     5],
        ['C6', 50, 'Credit',     6],
        ['D7', 45, 'Pass',       7],
        ['E8', 40, 'Pass',       8],
        ['F9',  0, 'Fail',       9],
    ];
    foreach ($bands as [$name, $min, $label, $order]) {
        q("INSERT INTO grading_bands (name, min_score, label, sort_order)
           VALUES (?, ?, ?, ?)", [$name, $min, $label, $order]);
    }
    echo "Seeded grading_bands.\n";
} else {
    echo "grading_bands already has {$n} row(s). Skipping.\n";
}

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_results_status ON results(status)");

echo "Migration complete.\n";