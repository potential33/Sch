<?php
// /public/app/public/pages/login.php
$pageTitle = 'Log in';

// Already logged in → go home
if (auth_check()) {
    redirect('/');
}

$error    = null;
$username = $_POST['username'] ?? '';
$next     = $_GET['next'] ?? ($_POST['next'] ?? '/');
// Only allow relative paths, block open redirect
if (!str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = auth_attempt($username, $_POST['password'] ?? '');
    if ($res['ok']) {
        flash_set('success', 'Welcome back.');
        redirect($next);
    }
    $error = $res['error'];
}
?>
<div style="max-width:420px; margin:3rem auto;">
    <div class="card">
        <h1 style="margin-top:0;">🏫 School</h1>
        <p class="muted">Sign in to continue.</p>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom:1rem;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="field">
                <label>Username</label>
                <input name="username" required autofocus
                       value="<?= e($username) ?>" autocomplete="username">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required
                       autocomplete="current-password">
            </div>
            <div class="actions">
                <button class="btn" type="submit" style="width:100%;">Log in</button>
            </div>
        </form>
    </div>
    <p class="muted" style="text-align:center; font-size:.85rem;">
        Prototype · admin / admin123 (change after first login)
    </p>
</div>