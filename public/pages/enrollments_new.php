<?php
// /public/app/public/pages/enrollments_new.php
$pageTitle = 'Enroll student';

$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

// Preselect student if ?student_id= is passed
$preselect = (int) ($_GET['student_id'] ?? ($_POST['student_id'] ?? 0));

// Suggest students without enrollment in chosen session
$sessionId = (int) ($_POST['session_id'] ?? ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0)));
$students = $sessionId
    ? fetch_all(
        "SELECT id, admission_no, full_name FROM students
         WHERE id NOT IN (SELECT student_id FROM enrollments WHERE session_id = ?)
         ORDER BY full_name",
        [$sessionId]
    )
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        enroll(
            (int) ($_POST['student_id'] ?? 0),
            (int) ($_POST['class_arm_id'] ?? 0),
            (int) ($_POST['session_id'] ?? 0)
        );
        flash_set('success', 'Student enrolled.');
        redirect(route_link('enrollments', ['session' => (int) $_POST['session_id']]));
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}
?>
<h1>Enroll student</h1>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
    <div class="field">
        <label>Session</label>
        <select name="session_id" onchange="this.form.submit()">
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Student</label>
        <select name="student_id" required>
            <option value="">— pick one —</option>
            <?php foreach ($students as $st): ?>
                <option value="<?= e($st['id']) ?>" <?= (int)$st['id'] === $preselect ? 'selected' : '' ?>>
                    <?= e($st['admission_no'] . ' — ' . $st['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="muted">Students already enrolled in this session are hidden.</small>
    </div>

    <div class="field">
        <label>Class arm</label>
        <select name="class_arm_id" required>
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>">
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="actions">
        <button class="btn" type="submit">Enroll</button>
        <a class="btn secondary" href="<?= e(route_link('enrollments')) ?>">Cancel</a>
    </div>
</form>