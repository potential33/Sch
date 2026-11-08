<?php
// /public/app/public/pages/password_force.php
$pageTitle = 'Change your password';

$user = auth_user();
if (!$user) { redirect('/'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($new) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($new !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        q("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?",
          [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        csrf_rotate();
        flash_set('success', 'Password set. Welcome!');
        redirect('/');
    }
}
?>
<div style="max-width:500px; margin:2rem auto;">
    <div class="card">
        <h1 style="margin-top:0;">Change your password</h1>
        <p class="muted">
            You're using an initial password. Please set your own before continuing.
        </p>

        <?php if ($errors): ?>
            <div class="flash flash-error" style="margin-bottom:1rem;">
                <?php foreach ($errors as $e_): ?>
                    <div><?= e($e_) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label>New password (8+ characters)</label>
                <input type="password" name="new" required minlength="8"
                       autocomplete="new-password" autofocus>
            </div>
            <div class="field">
                <label>Confirm new password</label>
                <input type="password" name="confirm" required minlength="8"
                       autocomplete="new-password">
            </div>
            <div class="actions">
                <button class="btn" type="submit" style="width:100%;">Set password</button>
            </div>
        </form>
    </div>
</div>