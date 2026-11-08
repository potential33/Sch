<?php
// /public/app/public/pages/exemptions.php
$pageTitle = 'Subject exemptions';

$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$sessionId  = (int) ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0));
$classArmId = (int) ($_GET['class_arm_id'] ?? ($classArms[0]['id'] ?? 0));

$students = fetch_all(
    "SELECT e.id AS enrollment_id, s.admission_no, s.full_name
     FROM enrollments e JOIN students s ON s.id = e.student_id
     WHERE e.class_arm_id = ? AND e.session_id = ? AND e.status = 'active'
     ORDER BY s.full_name",
    [$classArmId, $sessionId]
);
$subjects = subjects_for_arm($classArmId);

// Existing exemptions
$exemptions = exemptions_for_class($classArmId, $sessionId);
$byEnrollment = [];
foreach ($exemptions as $ex) {
    $byEnrollment[(int)$ex['enrollment_id']][] = $ex;
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_exempt'])) {
    try {
        exemption_add(
            (int) $_POST['enrollment_id'],
            (int) $_POST['subject_id'],
            trim($_POST['reason'] ?? ''),
            (int) auth_user()['id']
        );
        flash_set('success', 'Exemption added.');
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect(route_link('exemptions', ['session_id'=>$sessionId, 'class_arm_id'=>$classArmId]));
}

// Handle remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['remove_exempt'])) {
    exemption_remove((int) $_POST['enrollment_id'], (int) $_POST['subject_id']);
    flash_set('success', 'Exemption removed.');
    redirect(route_link('exemptions', ['session_id'=>$sessionId, 'class_arm_id'=>$classArmId]));
}
?>
<h1>Subject exemptions</h1>
<p class="muted">Mark a student as exempt from a subject they legitimately do not take.</p>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="exemptions">
    <div class="field" style="margin:0;">
        <label>Session</label>
        <select name="session_id" onchange="this.form.submit()">
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="margin:0;">
        <label>Class arm</label>
        <select name="class_arm_id" onchange="this.form.submit()">
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>" <?= (int)$ca['id'] === $classArmId ? 'selected' : '' ?>>
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <h2>Add exemption</h2>
    <form method="post" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="add_exempt" value="1">
        <div class="field" style="margin:0;">
            <label>Student</label>
            <select name="enrollment_id" required>
                <?php foreach ($students as $st): ?>
                    <option value="<?= e($st['enrollment_id']) ?>">
                        <?= e($st['admission_no'] . ' — ' . $st['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;">
            <label>Subject</label>
            <select name="subject_id" required>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;">
            <label>Reason</label>
            <input name="reason" placeholder="e.g. joined mid-term">
        </div>
        <button class="btn" type="submit">Add</button>
    </form>
</div>

<div class="card">
    <h2>Current exemptions</h2>
    <?php if (!$exemptions): ?>
        <p class="muted">None.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Student</th><th>Subject</th><th>Reason</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($exemptions as $ex): ?>
                <tr>
                    <td><?= e($ex['full_name']) ?><br><small class="muted"><?= e($ex['admission_no']) ?></small></td>
                    <td><?= e($ex['subject_name']) ?></td>
                    <td><?= e($ex['reason'] ?? '—') ?></td>
                    <td><?= e($ex['exempted_at']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="remove_exempt" value="1">
                            <input type="hidden" name="enrollment_id" value="<?= e($ex['enrollment_id']) ?>">
                            <input type="hidden" name="subject_id" value="<?= e($ex['subject_id']) ?>">
                            <button class="btn small secondary" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>