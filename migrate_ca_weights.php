<?php
// /app/migrate_ca_weights.php
declare(strict_types=1);
require __DIR__ . '/db.php';

// Deactivate CA3, re-weight CA1=10, CA2=20, EXAM=70
q("UPDATE grading_components SET active = 0 WHERE name = 'CA3'");
q("UPDATE grading_components SET max_score = 10 WHERE name = 'CA1'");
q("UPDATE grading_components SET max_score = 20 WHERE name = 'CA2'");
q("UPDATE grading_components SET max_score = 70 WHERE name = 'EXAM'");

// Recompute totals for all existing results using ONLY active components
$active = fetch_all("SELECT name, max_score FROM grading_components WHERE active = 1");
echo "Active components:\n";
foreach ($active as $a) echo "  {$a['name']} (max {$a['max_score']})\n";

$results = fetch_all("SELECT id, ca1, ca2, ca3, exam FROM results");
$updated = 0;
foreach ($results as $r) {
    // Sum only active components
    $map = ['CA1' => $r['ca1'], 'CA2' => $r['ca2'], 'CA3' => $r['ca3'], 'EXAM' => $r['exam']];
    $total = 0.0;
    $any   = false;
    foreach ($active as $a) {
        $v = $map[$a['name']] ?? null;
        if ($v !== null) { $total += (float)$v; $any = true; }
    }
    q("UPDATE results SET total = ? WHERE id = ?",
      [$any ? $total : null, $r['id']]);
    $updated++;
}

echo "Recomputed totals for {$updated} result(s).\n";
echo "Done.\n";