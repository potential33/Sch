<?php
// /public/app/public/pages/assignments_list.php
$pageTitle = 'Teacher assignments';

$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$sessionId = isset($_GET['session']) && $_GET['session'] !== ''
    ? (int) $_GET['session']
    : ($sessions[0]['id'] ?? null);

$rows = $sessionId
    ? fetch_all(
        "SELECT ts.id, t.full_name AS teacher, sub.name AS subject,
                c.name || ' ' || ca.arm AS class_label
         FROM teacher_subjects ts
         JOIN teachers t ON t.id = ts.teacher_id
         JOIN subjects sub ON sub.id = ts.subject_id
         JOIN class_arms ca ON ca.id = ts.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE ts.session_id = ?
         ORDER BY t.full_name, sub.name, class_label",
        [$sessionId]
    )
    : [];
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Teacher assignments</h1>
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Teacher assignments</h1>
    <?php if (auth_role_is('admin')): ?>
        <a href="<?= e(route_link('assignments/new')) ?>" class="btn">+ Assign teacher</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" class="card" style="display:flex; gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="assignments">
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
        <p class="muted">No assignments for this session.</p>
    <?php else: ?>
    <>
        <thead><tr><th>Teacher</th><th>Subject</th><th>Class</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <>
                    <td><?= e($r['class_label']) ?></td>
    <?php if (auth_role_is('admin')): ?>
    <td>
        <a class="btn small secondary"
           href="<?= e(route_link('assignments/delete', ['id' => $r['id']])) ?>">Unassign</a>
    </td>
    <?php else: ?>
    <td></td>
    <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>