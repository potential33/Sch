<?php
// /app/init.php
declare(strict_types=1);
require __DIR__ . '/db.php';

$dbFile = __DIR__ . '/school.sqlite';

// Wipe for a clean seed
if (file_exists($dbFile)) {
    unlink($dbFile);
    echo "Removed existing database.\n";
}
// Remove WAL/SHM leftovers
foreach (['-wal', '-shm'] as $suffix) {
    if (file_exists($dbFile . $suffix)) unlink($dbFile . $suffix);
}

$pdo = db();

// 1) Schema
$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);
echo "Schema created.\n";

// 2) Seed
$pdo->beginTransaction();
try {
    // Sessions
    $sessions = ['2024/2025', '2025/2026'];
    foreach ($sessions as $s) {
        q("INSERT INTO sessions (name) VALUES (?)", [$s]);
    }

    // Terms per session
    foreach (fetch_all("SELECT id FROM sessions") as $row) {
        foreach (['First Term', 'Second Term', 'Third Term'] as $t) {
            q("INSERT INTO terms (session_id, name) VALUES (?, ?)", [$row['id'], $t]);
        }
    }

    // Classes
    $classes = [
        ['JSS1', 'junior'], ['JSS2', 'junior'], ['JSS3', 'junior'],
        ['SS1', 'senior'],  ['SS2', 'senior'],  ['SS3', 'senior'],
    ];
    foreach ($classes as [$name, $section]) {
        q("INSERT INTO classes (name, section) VALUES (?, ?)", [$name, $section]);
    }

    // Subjects
    $subjects = [
        ['Mathematics',       'MTH', 'both'],
        ['English Language',  'ENG', 'both'],
        ['Basic Science',     'BSC', 'junior'],
        ['Basic Technology',  'BTE', 'junior'],
        ['Social Studies',    'SOS', 'junior'],
        ['Civic Education',   'CIV', 'both'],
        ['Physics',           'PHY', 'senior'],
        ['Chemistry',         'CHM', 'senior'],
        ['Biology',           'BIO', 'senior'],
        ['Further Mathematics','FMT','senior'],
        ['Economics',         'ECO', 'senior'],
        ['Government',        'GOV', 'senior'],
    ];
    foreach ($subjects as [$name, $code, $section]) {
        q("INSERT INTO subjects (name, code, section) VALUES (?, ?, ?)", [$name, $code, $section]);
    }

    // Class arms — JSS1A, JSS1B, SS1 Science, SS1 Arts, etc.
    $armsByClass = [
        'JSS1' => ['A', 'B'],
        'JSS2' => ['A', 'B'],
        'JSS3' => ['A'],
        'SS1'  => ['Science', 'Arts'],
        'SS2'  => ['Science', 'Arts'],
        'SS3'  => ['Science'],
    ];
    foreach ($armsByClass as $className => $arms) {
        $classId = fetch_val("SELECT id FROM classes WHERE name = ?", [$className]);
        foreach ($arms as $arm) {
            q("INSERT INTO class_arms (class_id, arm) VALUES (?, ?)", [$classId, $arm]);
        }
    }

    // subject_classes — which subjects are offered in which class
    $map = [
        'Mathematics'        => ['JSS1','JSS2','JSS3','SS1','SS2','SS3'],
        'English Language'   => ['JSS1','JSS2','JSS3','SS1','SS2','SS3'],
        'Basic Science'      => ['JSS1','JSS2','JSS3'],
        'Basic Technology'   => ['JSS1','JSS2','JSS3'],
        'Social Studies'     => ['JSS1','JSS2','JSS3'],
        'Civic Education'    => ['JSS1','JSS2','JSS3','SS1','SS2','SS3'],
        'Physics'            => ['SS1','SS2','SS3'],
        'Chemistry'          => ['SS1','SS2','SS3'],
        'Biology'            => ['SS1','SS2','SS3'],
        'Further Mathematics'=> ['SS1','SS2','SS3'],
        'Economics'          => ['SS1','SS2','SS3'],
        'Government'         => ['SS1','SS2','SS3'],
    ];
    foreach ($map as $subjectName => $classNames) {
        $sid = fetch_val("SELECT id FROM subjects WHERE name = ?", [$subjectName]);
        foreach ($classNames as $cn) {
            $cid = fetch_val("SELECT id FROM classes WHERE name = ?", [$cn]);
            q("INSERT INTO subject_classes (subject_id, class_id) VALUES (?, ?)", [$sid, $cid]);
        }
    }

    // Teachers — Nigerian names
    $teachers = [
        ['Mr. Adewale Ogunleye',    'adewale.ogunleye@school.ng'],
        ['Mrs. Ngozi Eze',          'ngozi.eze@school.ng'],
        ['Mr. Ibrahim Bello',       'ibrahim.bello@school.ng'],
        ['Ms. Funmilayo Adeyemi',   'funmi.adeyemi@school.ng'],
        ['Mr. Chinedu Okonkwo',     'chinedu.okonkwo@school.ng'],
    ];
    foreach ($teachers as [$name, $email]) {
        q("INSERT INTO teachers (full_name, email) VALUES (?, ?)", [$name, $email]);
    }

    // Students — Nigerian names
    $students = [
        ['ADM/2025/001', 'Tunde Bakare',         'M', '2013-04-12'],
        ['ADM/2025/002', 'Chiamaka Nwosu',       'F', '2012-09-08'],
        ['ADM/2025/003', 'Musa Abdullahi',       'M', '2011-01-23'],
        ['ADM/2025/004', 'Blessing Eze',         'F', '2014-06-30'],
        ['ADM/2025/005', 'Aisha Suleiman',       'F', '2010-11-17'],
        ['ADM/2025/006', 'Emeka Obi',            'M', '2013-02-05'],
        ['ADM/2025/007', 'Yetunde Alabi',        'F', '2012-12-19'],
        ['ADM/2025/008', 'Samuel Danjuma',       'M', '2011-08-14'],
    ];
    foreach ($students as [$adm, $name, $g, $dob]) {
        q("INSERT INTO students (admission_no, full_name, gender, date_of_birth) VALUES (?, ?, ?, ?)", [$adm, $name, $g, $dob]);
    }

    // Enroll a few students into 2025/2026
    $sessionId = fetch_val("SELECT id FROM sessions WHERE name = '2025/2026'");

    $enroll = [
        ['ADM/2025/001', 'JSS2', 'A'],
        ['ADM/2025/002', 'JSS2', 'A'],
        ['ADM/2025/003', 'JSS2', 'B'],
        ['ADM/2025/004', 'JSS1', 'A'],
        ['ADM/2025/005', 'SS1',  'Science'],
        ['ADM/2025/006', 'SS1',  'Science'],
        ['ADM/2025/007', 'SS1',  'Arts'],
        ['ADM/2025/008', 'JSS3', 'A'],
    ];
    foreach ($enroll as [$adm, $className, $arm]) {
        $studentId = fetch_val("SELECT id FROM students WHERE admission_no = ?", [$adm]);
        $classArmId = fetch_val(
            "SELECT ca.id FROM class_arms ca
             JOIN classes c ON c.id = ca.class_id
             WHERE c.name = ? AND ca.arm = ?",
            [$className, $arm]
        );
        q("INSERT INTO enrollments (student_id, class_arm_id, session_id) VALUES (?, ?, ?)",
          [$studentId, $classArmId, $sessionId]);
    }

    // Assign teachers — valid combinations only
    $assign = [
        ['adewale.ogunleye@school.ng', 'Mathematics',      'JSS2', 'A'],
        ['ngozi.eze@school.ng',        'English Language', 'JSS2', 'A'],
        ['ibrahim.bello@school.ng',    'Basic Science',    'JSS2', 'A'],
        ['funmi.adeyemi@school.ng',    'Mathematics',      'SS1',  'Science'],
        ['chinedu.okonkwo@school.ng',  'Physics',          'SS1',  'Science'],
    ];
    foreach ($assign as [$email, $subjectName, $className, $arm]) {
        $tid = fetch_val("SELECT id FROM teachers WHERE email = ?", [$email]);
        $sid = fetch_val("SELECT id FROM subjects WHERE name = ?", [$subjectName]);
        $caid = fetch_val(
            "SELECT ca.id FROM class_arms ca
             JOIN classes c ON c.id = ca.class_id
             WHERE c.name = ? AND ca.arm = ?",
            [$className, $arm]
        );
        q("INSERT INTO teacher_subjects (teacher_id, subject_id, class_arm_id, session_id) VALUES (?, ?, ?, ?)",
          [$tid, $sid, $caid, $sessionId]);
    }

    $pdo->commit();
    echo "Seed data inserted.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "SEED FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done. Database at: $dbFile\n";