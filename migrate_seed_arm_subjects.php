<?php
// /app/migrate_seed_arm_subjects.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$arms = fetch_all(
    "SELECT ca.id, ca.class_id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$seeded = 0;
foreach ($arms as $ca) {
    $armId = (int) $ca['id'];
    $classId = (int) $ca['class_id'];

    // Skip if already configured
    $existing = (int) fetch_val(
        "SELECT COUNT(*) FROM class_arm_subjects WHERE class_arm_id = ?", [$armId]);
    if ($existing > 0) {
        echo "SKIP {$ca['class_name']} {$ca['arm']}: already has {$existing} subject(s).\n";
        continue;
    }

    $subjects = fetch_all(
        "SELECT subject_id FROM subject_classes WHERE class_id = ?", [$classId]);
    if (!$subjects) {
        echo "SKIP {$ca['class_name']} {$ca['arm']}: no subjects mapped in parent class.\n";
        continue;
    }

    foreach ($subjects as $s) {
        q("INSERT OR IGNORE INTO class_arm_subjects (class_arm_id, subject_id) VALUES (?, ?)",
          [$armId, $s['subject_id']]);
        $seeded++;
    }
    echo "Seeded {$ca['class_name']} {$ca['arm']}: " . count($subjects) . " subject(s).\n";
}

echo "\nDone. {$seeded} arm-subject row(s) inserted.\n";
echo "Admin can adjust per arm at /?r=class-arms/subjects\n";