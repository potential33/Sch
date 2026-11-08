<?php
// /public/app/public/pages/dashboard.php
$pageTitle = 'Dashboard';

$counts = [
    'Students'    => (int) fetch_val("SELECT COUNT(*) FROM students"),
    'Teachers'    => (int) fetch_val("SELECT COUNT(*) FROM teachers"),
    'Classes'     => (int) fetch_val("SELECT COUNT(*) FROM classes"),
    'Subjects'    => (int) fetch_val("SELECT COUNT(*) FROM subjects"),
    'Enrollments' => (int) fetch_val("SELECT COUNT(*) FROM enrollments"),
    'Assignments' => (int) fetch_val("SELECT COUNT(*) FROM teacher_subjects"),
];

$currentSession = fetch_one("SELECT * FROM sessions ORDER BY name DESC LIMIT 1");
?>
<h1>Dashboard</h1>
<div class="grid" style="margin-bottom:1.5rem;">
    <?php foreach ($counts as $label => $n): ?>
        <div class="stat">
            <div class="num"><?= e((string) $n) ?></div>
            <div class="lbl"><?= e($label) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Quick actions</h2>
    <p>
        <?php if (auth_role_is('admin')): ?>
    <a href="<?= e(route_link('students/new')) ?>" class="btn">+ Add student</a>
    <a href="<?= e(route_link('teachers/new')) ?>" class="btn">+ Add teacher</a>
    <a href="<?= e(route_link('enrollments/new')) ?>" class="btn secondary">Enroll student</a>
<?php elseif (auth_role_is('student')): ?>
    <a href="<?= e(route_link('me/report')) ?>" class="btn">My Report Card</a>
<?php else: ?>
    <a href="<?= e(route_link('results/entry')) ?>" class="btn">Enter grades</a>
<?php endif; ?>
    </p>
    <?php if ($currentSession): ?>
        <p class="muted">
            Most recent session: <span class="tag"><?= e($currentSession['name']) ?></span>
        </p>
    <?php endif; ?>
</div>