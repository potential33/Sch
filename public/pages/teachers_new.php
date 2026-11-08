<?php
// /public/app/public/pages/teachers_new.php
$pageTitle = 'Add teacher';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        teacher_create(
            $_POST['full_name'] ?? '',
            ($_POST['email'] ?? '') === '' ? null : $_POST['email']
        );
        flash_set('success', 'Teacher added.');
        redirect(route_link('teachers'));
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}
?>
<h1>Add teacher</h1>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
    <div class="field">
        <label>Full name *</label>
        <input name="full_name" required value="<?= e(old('full_name')) ?>">
    </div>
    <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e(old('email')) ?>">
    </div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn secondary" href="<?= e(route_link('teachers')) ?>">Cancel</a>
    </div>
</form>