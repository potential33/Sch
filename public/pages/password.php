<?php
// /public/app/public/pages/password.php
$pageTitle = 'Change password';

$user = auth_user();
$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if ($current === '') $errors[] = 'Current password is required.';
        if (strlen($new) < 8)  $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';
        if ($new === $current && $current !== '') $errors[] = 'New password must differ from the current one.';

        if (!$errors) {
            $row = fetch_one("SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
            if (!$row || !password_verify($current, $row['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (!$errors) {
            q("UPDATE users SET password_hash = ? WHERE id = ?",
              [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            csrf_rotate();
            flash_set('success', 'Password changed.');
            redirect(route_link('dashboard'));
        }
    } catch (AppException $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<h1>Change password</h1>

<?php if ($errors): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $e_): ?>
            <div><?= e($e_) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:500px;">
    <p class="muted">
        Signed in as <strong><?= e($user['username']) ?></strong>
        (<span class="tag"><?= e($user['role']) ?></span>).
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label>Current password</label>
            <input type="password" name="current" required autocomplete="current-password">
        </div>
        <div class="field">
            <label>New password (8+ characters)</label>
            <input type="password" name="new" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
            <label>Confirm new password</label>
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
        </div>
        <div class="actions">
            <button class="btn" type="submit">Update password</button>
            <a class="btn secondary" href="<?= e(route_link('dashboard')) ?>">Cancel</a>
        </div>
    </form>
</div>