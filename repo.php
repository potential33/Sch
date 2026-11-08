<?php
// /app/repo.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AppException.php';

/* ============================================================
 * LOOKUP HELPERS
 * ============================================================ */

function resolve_session(string $name): ?int
{
    $id = fetch_val("SELECT id FROM sessions WHERE name = ?", [$name]);
    return $id === null ? null : (int) $id;
}

function resolve_class_arm(string $className, string $arm): ?int
{
    $id = fetch_val(
        "SELECT ca.id FROM class_arms ca
         JOIN classes c ON c.id = ca.class_id
         WHERE c.name = ? AND ca.arm = ?",
        [$className, $arm]
    );
    return $id === null ? null : (int) $id;
}

function resolve_subject(string $name): ?int
{
    $id = fetch_val("SELECT id FROM subjects WHERE name = ?", [$name]);
    return $id === null ? null : (int) $id;
}

function class_arm_full_name(int $classArmId): ?string
{
    $row = fetch_one(
        "SELECT c.name AS class_name, ca.arm
         FROM class_arms ca JOIN classes c ON c.id = ca.class_id
         WHERE ca.id = ?",
        [$classArmId]
    );
    return $row ? ($row['class_name'] . ' ' . $row['arm']) : null;
}

function subjects_available_for_class(int $classId): array
{
    return fetch_all(
        "SELECT s.id, s.name, s.code
         FROM subject_classes sc
         JOIN subjects s ON s.id = sc.subject_id
         WHERE sc.class_id = ?
         ORDER BY s.name",
        [$classId]
    );
}

/* ============================================================
 * STUDENTS
 * ============================================================ */

function student_create(
    string $admissionNo,
    string $fullName,
    ?string $gender = null,
    ?string $dob = null
): int {
    $admissionNo = trim($admissionNo);
    $fullName    = trim($fullName);

    if ($admissionNo === '') throw new AppException('Admission number is required.');
    if ($fullName === '')    throw new AppException('Full name is required.');
    if ($gender !== null && !in_array($gender, ['M','F'], true)) {
        throw new AppException("Gender must be 'M' or 'F'.");
    }
    if ($dob !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        throw new AppException('Date of birth must be YYYY-MM-DD.');
    }
    if (fetch_val("SELECT 1 FROM students WHERE admission_no = ?", [$admissionNo])) {
        throw new AppException("Admission number '{$admissionNo}' already exists.");
    }

    return insert(
        "INSERT INTO students (admission_no, full_name, gender, date_of_birth)
         VALUES (?, ?, ?, ?)",
        [$admissionNo, $fullName, $gender, $dob]
    );
}

function student_get(int $id): ?array
{
    return fetch_one("SELECT * FROM students WHERE id = ?", [$id]);
}

function student_get_by_admission(string $adm): ?array
{
    return fetch_one("SELECT * FROM students WHERE admission_no = ?", [$adm]);
}

function student_list(?int $classArmId = null, ?int $sessionId = null, ?string $gender = null): array
{
    $sql = "SELECT s.* FROM students s";
    $params = [];
    $where = [];

    if ($classArmId !== null || $sessionId !== null) {
        $sql .= " JOIN enrollments e ON e.student_id = s.id";
        if ($classArmId !== null) { $where[] = "e.class_arm_id = ?"; $params[] = $classArmId; }
        if ($sessionId   !== null) { $where[] = "e.session_id = ?";   $params[] = $sessionId; }
    }
    if ($gender !== null) { $where[] = "s.gender = ?"; $params[] = $gender; }
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY s.full_name";

    return fetch_all($sql, $params);
}

function student_update(int $id, array $fields): void
{
    $allowed = ['admission_no','full_name','gender','date_of_birth'];
    $set = [];
    $params = [];

    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            throw new AppException("Field '{$k}' cannot be updated.");
        }
        if ($k === 'gender' && $v !== null && !in_array($v, ['M','F'], true)) {
            throw new AppException("Gender must be 'M' or 'F'.");
        }
        if ($k === 'date_of_birth' && $v !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            throw new AppException('Date of birth must be YYYY-MM-DD.');
        }
        $set[] = "{$k} = ?";
        $params[] = $v;
    }
    if (!$set) throw new AppException('Nothing to update.');
    $params[] = $id;

    try {
        q("UPDATE students SET " . implode(', ', $set) . " WHERE id = ?", $params);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            throw new AppException('Admission number already exists.');
        }
        throw $e;
    }
}

function student_delete(int $id): void
{
    $n = (int) fetch_val("SELECT COUNT(*) FROM enrollments WHERE student_id = ?", [$id]);
    // Enrollments cascade; we still confirm the student exists.
    if (!student_get($id)) throw new AppException('Student not found.');
    q("DELETE FROM students WHERE id = ?", [$id]);
    // (No friendly note needed — cascade will clean enrollments.)
}

/* ============================================================
 * TEACHERS
 * ============================================================ */

function teacher_create(string $fullName, ?string $email = null): int
{
    $fullName = trim($fullName);
    $email    = $email === null ? null : strtolower(trim($email));

    if ($fullName === '') throw new AppException('Teacher name is required.');
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppException('Invalid email address.');
    }
    if ($email !== null && fetch_val("SELECT 1 FROM teachers WHERE email = ?", [$email])) {
        throw new AppException("Email '{$email}' is already in use.");
    }

    return insert(
        "INSERT INTO teachers (full_name, email) VALUES (?, ?)",
        [$fullName, $email]
    );
}

function teacher_get(int $id): ?array
{
    return fetch_one("SELECT * FROM teachers WHERE id = ?", [$id]);
}

function teacher_get_by_email(string $email): ?array
{
    return fetch_one("SELECT * FROM teachers WHERE email = ?", [strtolower(trim($email))]);
}

function teacher_list(): array
{
    return fetch_all("SELECT * FROM teachers ORDER BY full_name");
}

function teacher_update(int $id, array $fields): void
{
    $allowed = ['full_name','email','user_id'];
    $set = [];
    $params = [];

    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            throw new AppException("Field '{$k}' cannot be updated.");
        }
        if ($k === 'email' && $v !== null) {
            $v = strtolower(trim($v));
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                throw new AppException('Invalid email address.');
            }
        }
        $set[] = "{$k} = ?";
        $params[] = $v;
    }
    if (!$set) throw new AppException('Nothing to update.');
    $params[] = $id;

    try {
        q("UPDATE teachers SET " . implode(', ', $set) . " WHERE id = ?", $params);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            throw new AppException('Email or user_id already in use.');
        }
        throw $e;
    }
}

function teacher_delete(int $id): void
{
    if (!teacher_get($id)) throw new AppException('Teacher not found.');
    q("DELETE FROM teachers WHERE id = ?", [$id]); // cascades teacher_subjects
}

/* ============================================================
 * ENROLLMENTS
 * ============================================================ */

function enroll(int $studentId, int $classArmId, int $sessionId): int
{
    if (!student_get($studentId)) throw new AppException('Student not found.');
    if (!fetch_val("SELECT 1 FROM class_arms WHERE id = ?", [$classArmId])) {
        throw new AppException('Class arm not found.');
    }
    if (!fetch_val("SELECT 1 FROM sessions WHERE id = ?", [$sessionId])) {
        throw new AppException('Session not found.');
    }

    $existing = fetch_one(
        "SELECT id, class_arm_id FROM enrollments WHERE student_id = ? AND session_id = ?",
        [$studentId, $sessionId]
    );
    if ($existing) {
        $arm = class_arm_full_name((int) $existing['class_arm_id']) ?? '?';
        throw new AppException("Student already enrolled in this session ({$arm}).");
    }

    return insert(
        "INSERT INTO enrollments (student_id, class_arm_id, session_id) VALUES (?, ?, ?)",
        [$studentId, $classArmId, $sessionId]
    );
}

function enrollment_get(int $studentId, int $sessionId): ?array
{
    return fetch_one(
        "SELECT e.*, c.name AS class_name, ca.arm
         FROM enrollments e
         JOIN class_arms ca ON ca.id = e.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE e.student_id = ? AND e.session_id = ?",
        [$studentId, $sessionId]
    );
}

function enrollments_for_session(int $sessionId): array
{
    return fetch_all(
        "SELECT e.id, s.admission_no, s.full_name, s.gender,
                c.name AS class_name, ca.arm, c.name || ' ' || ca.arm AS class_label
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         JOIN class_arms ca ON ca.id = e.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE e.session_id = ?
         ORDER BY c.name, ca.arm, s.full_name",
        [$sessionId]
    );
}

function enrollment_move(int $studentId, int $sessionId, int $newClassArmId): void
{
    if (!fetch_val("SELECT 1 FROM class_arms WHERE id = ?", [$newClassArmId])) {
        throw new AppException('Target class arm not found.');
    }
    $enr = enrollment_get($studentId, $sessionId);
    if (!$enr) throw new AppException('Student is not enrolled in this session.');

    q("UPDATE enrollments SET class_arm_id = ? WHERE id = ?",
      [$newClassArmId, $enr['id']]);
}

function enrollment_withdraw(int $studentId, int $sessionId): void
{
    $enr = enrollment_get($studentId, $sessionId);
    if (!$enr) throw new AppException('Student is not enrolled in this session.');
    q("DELETE FROM enrollments WHERE id = ?", [$enr['id']]);
}

/* ============================================================
 * TEACHER ASSIGNMENTS
 * ============================================================ */

function assign_teacher(int $teacherId, int $subjectId, int $classArmId, int $sessionId): int
{
    if (!teacher_get($teacherId)) throw new AppException('Teacher not found.');
    if (!fetch_val("SELECT 1 FROM subjects WHERE id = ?", [$subjectId])) {
        throw new AppException('Subject not found.');
    }
    if (!fetch_val("SELECT 1 FROM class_arms WHERE id = ?", [$classArmId])) {
        throw new AppException('Class arm not found.');
    }
    if (!fetch_val("SELECT 1 FROM sessions WHERE id = ?", [$sessionId])) {
        throw new AppException('Session not found.');
    }

    try {
        return insert(
            "INSERT INTO teacher_subjects (teacher_id, subject_id, class_arm_id, session_id)
             VALUES (?, ?, ?, ?)",
            [$teacherId, $subjectId, $classArmId, $sessionId]
        );
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Subject not offered in this class')) {
            throw new AppException(
                'This subject is not offered for the selected class (see subject_classes).'
            );
        }
        if (str_contains($msg, 'UNIQUE')) {
            throw new AppException('This exact teacher-subject-class-session assignment already exists.');
        }
        throw $e;
    }
}

function unassign_teacher(int $assignmentId): void
{
    if (!fetch_val("SELECT 1 FROM teacher_subjects WHERE id = ?", [$assignmentId])) {
        throw new AppException('Assignment not found.');
    }
    q("DELETE FROM teacher_subjects WHERE id = ?", [$assignmentId]);
}

function assignments_for_teacher(int $teacherId, ?int $sessionId = null): array
{
    $sql = "SELECT ts.id, sub.name AS subject, c.name || ' ' || ca.arm AS class_label,
                   ses.name AS session
            FROM teacher_subjects ts
            JOIN subjects sub ON sub.id = ts.subject_id
            JOIN class_arms ca ON ca.id = ts.class_arm_id
            JOIN classes c ON c.id = ca.class_id
            JOIN sessions ses ON ses.id = ts.session_id
            WHERE ts.teacher_id = ?";
    $params = [$teacherId];
    if ($sessionId !== null) { $sql .= " AND ts.session_id = ?"; $params[] = $sessionId; }
    $sql .= " ORDER BY ses.name, class_label, subject";
    return fetch_all($sql, $params);
}

function assignments_for_class(int $subjectId, int $classArmId, int $sessionId): array
{
    return fetch_all(
        "SELECT ts.id, t.full_name AS teacher, t.email
         FROM teacher_subjects ts
         JOIN teachers t ON t.id = ts.teacher_id
         WHERE ts.subject_id = ? AND ts.class_arm_id = ? AND ts.session_id = ?
         ORDER BY t.full_name",
        [$subjectId, $classArmId, $sessionId]
    );
}