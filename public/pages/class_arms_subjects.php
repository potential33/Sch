<?php
// /public/app/public/pages/class_arms_subjects.php
$pageTitle = 'Class arm subjects';

$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);
$subjects = fetch_all("SELECT id, name, code FROM subjects ORDER BY name");

$classArmId = (int) ($_GET['class_arm_id'] ?? ($classArms[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $caid = (int) $_POST['class_arm_id'];
        $selected = $_POST['subject_ids'] ?? [];
        $selected = array_map('intval', (array)$selected);

        // Full sync
        $current = array_map('intval', array_column(
            fetch_all("SELECT subject_id FROM class_arm_subjects WHERE class_arm_id = ?", [$caid]),
            'subject_id'
        ));

        foreach ($selected as $sid) {
            if (!in_array($sid, $current, true)) arm_subject_set($caid, $sid, true);
        }
        foreach ($current as $sid) {
            if (!in_array($sid, $selected, true)) arm_subject_set($caid, $sid, false);
        }

        flash_set('success', 'Subjects updated.');
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect(route_link('class-arms/subjects', ['class_arm_id' => (int)$_POST['class_arm_id']]));
}

$currentSubjects = $classArmId
    ? array_map('intval', array_column(
        fetch_all("SELECT subject_id FROM class_arm_subjects WHERE class_arm_id = ?", [$classArmId]),
        'subject_id'))
    : [];
?>
<h1>Class arm subjects</h1>
<p class="muted">Which subjects are offered in each arm. Used to compute expected grades and coverage.</p>

<form method="get" class="card" style="display:flex; gap:.75rem; align-items:end; flex-wrap:wrap;">
    <input type="hidden" name="r" value="class-arms/subjects">
    <div class="field" style="margin:0; flex:1; min-width:200px;">
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

<form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="class_arm_id" value="<?= e($classArmId) ?>">
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:.5rem;">
        <?php foreach ($subjects as $s): ?>
            <label style="display:flex; align-items:center; gap:.5rem; font-weight:normal;">
                <input type="checkbox" name="subject_ids[]" value="<?= e($s['id']) ?>"
                    <?= in_array((int)$s['id'], $currentSubjects, true) ? 'checked' : '' ?>>
                <?= e($s['name']) ?> <small class="muted">(<?= e($s['code']) ?>)</small>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="actions" style="margin-top:1rem;">
        <button class="btn" type="submit">Save subjects</button>
    </div>
</form>