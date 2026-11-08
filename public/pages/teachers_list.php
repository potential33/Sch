<?php
// /public/app/public/pages/teachers_list.php
$pageTitle = 'Teachers';
$teachers = teacher_list();
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Teachers</h1>
    <a href="<?= e(route_link('teachers/new')) ?>" class="btn">+ Add teacher</a>
</div>

<div class="card">
    <?php if (!$teachers): ?>
        <p class="muted">No teachers yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Assignments</th></tr></thead>
        <tbody>
        <?php foreach ($teachers as $t):
            $n = (int) fetch_val("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = ?", [$t['id']]);
        ?>
            <tr>
                <td><?= e($t['full_name']) ?></td>
                <td><?= e($t['email'] ?? '—') ?></td>
                <td><?= e((string) $n) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>