<?php
// /public/app/public/pages/students_list.php
$pageTitle = 'Students';

$sessions   = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms  = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$classArmId = isset($_GET['class_arm']) && $_GET['class_arm'] !== '' ? (int) $_GET['class_arm'] : null;
$sessionId  = isset($_GET['session'])   && $_GET['session']   !== '' ? (int) $_GET['session']   : null;
$gender     = $_GET['gender'] ?? null;
if ($gender !== 'M' && $gender !== 'F') $gender = null;

$students = student_list($classArmId, $sessionId, $gender);
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Students <span class="muted" style="font-weight:normal; font-size:1rem;">(<?= count($students) ?>)</span></h1>
    <a href="<?= e(route_link('students/new')) ?>" class="btn">+ Add student</a>
</div>

<form method="get" class="card" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:end;">
    <input type="hidden" name="r" value="students">
    <div class="field" style="flex:1; min-width:140px; margin:0;">
        <label>Session</label>
        <select name="session">
            <option value="">Any</option>
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= $sessionId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="flex:1; min-width:140px; margin:0;">
        <label>Class arm</label>
        <select name="class_arm">
            <option value="">Any</option>
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>" <?= $classArmId === (int)$ca['id'] ? 'selected' : '' ?>>
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="flex:0 0 110px; margin:0;">
        <label>Gender</label>
        <select name="gender">
            <option value="">Any</option>
            <option value="M" <?= $gender === 'M' ? 'selected' : '' ?>>Male</option>
            <option value="F" <?= $gender === 'F' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <button class="btn" type="submit">Filter</button>
</form>

<div class="card">
    <?php if (!$students): ?>
        <p class="muted">No students match the filter.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Admission #</th><th>Name</th><th>Gender</th><th>DOB</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?= e($s['admission_no']) ?></td>
                <td><?= e($s['full_name']) ?></td>
                <td><?= e($s['gender'] ?? '') ?></td>
                <td><?= e($s['date_of_birth'] ?? '') ?></td>
                <td>
                    <a class="btn small secondary" href="<?= e(route_link('students/view', ['id' => $s['id']])) ?>">View</a>
                    <a class="btn small secondary" href="<?= e(route_link('students/edit', ['id' => $s['id']])) ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>