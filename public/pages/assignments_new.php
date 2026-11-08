<?php
// /public/app/public/pages/assignments_new.php
$pageTitle = 'Assign teacher';

$teachers  = fetch_all("SELECT id, full_name FROM teachers ORDER BY full_name");
$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms = fetch_all(
    "SELECT ca.id, c.id AS class_id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$sessionId  = (int) ($_POST['session_id']  ?? ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0)));
$classArmId = (int) ($_POST['class_arm_id'] ?? ($_GET['class_arm_id'] ?? 0));

// Subjects valid for the selected class arm
$availableSubjects = [];
if ($classArmId) {
    $classId = fetch_val(
        "SELECT class_id FROM class_arms WHERE id = ?", [$classArmId]);
    if ($classId) $availableSubjects = subjects_available_for_class((int)$classId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = assign_teacher(
            (int) ($_POST['teacher_id']   ?? 0),
            (int) ($_POST['subject_id']   ?? 0),
            (int) ($_POST['class_arm_id'] ?? 0),
            (int) ($_POST['session_id']   ?? 0)
        );
        flash_set('success', 'Assignment created.');
        redirect(route_link('assignments', ['session' => (int) $_POST['session_id']]));
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}
?>
<h1>Assign teacher</h1>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
    <?= csrf_field() ?>

    <div class="field">
        <label>Session</label>
        <select name="session_id" onchange="this.form.submit()" required>
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Class arm</label>
        <select name="class_arm_id" onchange="this.form.submit()" required>
            <option value="">— pick —</option>
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>" <?= (int)$ca['id'] === $classArmId ? 'selected' : '' ?>>
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Subject</label>
        <select name="subject_id" required <?= $classArmId ? '' : 'disabled' ?>>
            <option value="">— pick —</option>
            <?php foreach ($availableSubjects as $s): ?>
                <option value="<?= e($s['id']) ?>">
                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($classArmId && !$availableSubjects): ?>
            <small class="muted">No subjects mapped to this class.</small>
        <?php endif; ?>
    </div>

    <div class="field">
        <label>Teacher</label>
        <select name="teacher_id" required>
            <option value="">— pick —</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= e($t['id']) ?>"><?= e($t['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="actions">
        <button class="btn" type="submit">Assign</button>
        <a class="btn secondary" href="<?= e(route_link('assignments')) ?>">Cancel</a>
    </div>
</form>