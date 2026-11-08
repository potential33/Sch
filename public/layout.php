<?php
// /public/app/public/layout.php
declare(strict_types=1);
$flash = flash_get();
$user  = auth_user();
$isAdmin = $user && $user['role'] === 'admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'School') ?> · School</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
    <a href="/" class="brand">Chibugwu</a>
    <?php if ($user): ?>
    <?php if ($user['role'] === 'student'): ?>
    <nav>
        <a href="<?= e(route_link('me/report')) ?>">My Report Card</a>
    </nav>
<?php else: ?>
    <nav>
        <a href="<?= e(route_link('students')) ?>">Students</a>
        <?php if ($isAdmin): ?>
            <a href="<?= e(route_link('teachers')) ?>">Teachers</a>
            <a href="<?= e(route_link('enrollments')) ?>">Enrollments</a>
           <a href="<?= e(route_link('coverage')) ?>">My Coverage</a>
        <?php endif; ?>
        <a href="<?= e(route_link('assignments')) ?>">Assignments</a>
        <a href="<?= e(route_link('results/entry')) ?>">Grades</a>
        <?php if ($isAdmin): ?>
            <a href="<?= e(route_link('results/approve')) ?>">Approve</a>
            <a href="<?= e(route_link('results/anomalies')) ?>">Anomalies</a>
        <?php endif; ?>
        <a href="<?= e(route_link('reports/student')) ?>">Reports</a>
        <a href="<?= e(route_link('reports/class')) ?>">Broadsheet</a>
        <?php if ($isAdmin): ?>
            <a href="<?= e(route_link('reports/remarks')) ?>">Remarks</a>
            <a href="<?= e(route_link('reports/publish')) ?>">Publish</a>
            <a href="<?= e(route_link('coverage')) ?>">Coverage</a>
<a href="<?= e(route_link('class-arms/subjects')) ?>">Arm Subjects</a>
<a href="<?= e(route_link('exemptions')) ?>">Exemptions</a>
            <a href="<?= e(route_link('settings')) ?>">Settings</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
    <div style="margin-left:auto; display:flex; align-items:center; gap:.75rem;">
        <span style="font-size:.9rem;">
            <?= e($user['teacher_name'] ?? $user['username']) ?>
            <span class="tag" style="background:#374151;color:#e5e7eb;">
                <?= e($user['role']) ?>
            </span>
        </span>
        <a href="<?= e(route_link('password')) ?>" class="btn secondary small">Password</a>
<form method="post" action="<?= e(route_link('logout')) ?>" style="display:inline;">
    <?= csrf_field() ?>
    <button class="btn secondary small" type="submit">Log out</button>
</form>
    </div>
    <?php endif; ?>
</header>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<main>
    <?= $content ?>
</main>

<footer class="footer">
    <small>School Manager · SQLite/PHP prototype</small>
</footer>
</body>
</html>