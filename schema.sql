-- /app/schema.sql
PRAGMA foreign_keys = ON;

-- Independent first
CREATE TABLE IF NOT EXISTS sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS terms (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id  INTEGER NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    UNIQUE (session_id, name)
);

CREATE TABLE IF NOT EXISTS classes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    section     TEXT NOT NULL CHECK (section IN ('junior','senior'))
);

CREATE TABLE IF NOT EXISTS subjects (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    code        TEXT UNIQUE,
    section     TEXT NOT NULL CHECK (section IN ('junior','senior','both'))
);

CREATE TABLE IF NOT EXISTS students (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    admission_no    TEXT NOT NULL UNIQUE,
    full_name       TEXT NOT NULL,
    gender          TEXT CHECK (gender IN ('M','F')),
    date_of_birth   TEXT
);

CREATE TABLE IF NOT EXISTS teachers (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name   TEXT NOT NULL,
    email       TEXT UNIQUE,
    user_id     TEXT UNIQUE
);

-- Depends on classes / subjects
CREATE TABLE IF NOT EXISTS class_arms (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id    INTEGER NOT NULL REFERENCES classes(id),
    arm         TEXT NOT NULL,
    UNIQUE (class_id, arm)
);

CREATE TABLE IF NOT EXISTS subject_classes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id  INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    class_id    INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE (subject_id, class_id)
);

-- Depends on students / class_arms / sessions
CREATE TABLE IF NOT EXISTS enrollments (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id      INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    class_arm_id    INTEGER NOT NULL REFERENCES class_arms(id),
    session_id      INTEGER NOT NULL REFERENCES sessions(id),
    UNIQUE (student_id, session_id)
);

-- Depends on teachers / subjects / class_arms / sessions
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id      INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
    subject_id      INTEGER NOT NULL REFERENCES subjects(id),
    class_arm_id    INTEGER NOT NULL REFERENCES class_arms(id),
    session_id      INTEGER NOT NULL REFERENCES sessions(id),
    UNIQUE (teacher_id, subject_id, class_arm_id, session_id)
);

-- Trigger: teacher_subjects subject must be mapped to class_arm's class
CREATE TRIGGER IF NOT EXISTS trg_teacher_subjects_check
BEFORE INSERT ON teacher_subjects
FOR EACH ROW
BEGIN
    SELECT
        CASE
            WHEN NOT EXISTS (
                SELECT 1
                FROM class_arms ca
                JOIN subject_classes sc ON sc.class_id = ca.class_id
                WHERE ca.id = NEW.class_arm_id
                  AND sc.subject_id = NEW.subject_id
            )
            THEN RAISE(ABORT, 'Subject not offered in this class (see subject_classes).')
        END;
END;