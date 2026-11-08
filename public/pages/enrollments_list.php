<?php
// /public/app/public/pages/enrollments_list.php
$pageTitle = 'Enrollments';
$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");

$sessionId = isset($_GET['session']) && $_GET['session'] !== ''
    ? (int) $_GET['session']
    : ($sessions[0]['id'] ?? null);

$rows = $sessionId ? enrollments_for_session((int) $sessionId) : [];
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Enrollments</h1>
    <a href="<?= e(route_link('enrollments/new')) ?>" class="btn">+ Enroll student</a>
</div>

<form method="get" class="card" style="display:flex; gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="enrollments">
    <div class="field" style="flex:1; margin:0;">
        <label>Session</label>
        <select name="session" onchange="this.form.submit()">
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <?php if (!$rows): ?>
        <p class="muted">No enrollments for this session.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Admission #</th><th>Name</th><th>Class</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['admission_no']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['class_label']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>