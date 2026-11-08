<?php
// /public/app/public/pages/students_new.php
$pageTitle = 'Add student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = student_create(
            $_POST['admission_no'] ?? '',
            $_POST['full_name'] ?? '',
            ($_POST['gender'] ?? '') === '' ? null : $_POST['gender'],
            ($_POST['date_of_birth'] ?? '') === '' ? null : $_POST['date_of_birth']
        );
        flash_set('success', 'Student added.');
        redirect(route_link('students/view', ['id' => $id]));
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}
?>
<h1>Add student</h1>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
    <div class="field">
        <label>Admission number *</label>
        <input name="admission_no" required value="<?= e(old('admission_no')) ?>">
    </div>
    <div class="field">
        <label>Full name *</label>
        <input name="full_name" required value="<?= e(old('full_name')) ?>">
    </div>
    <div class="field">
        <label>Gender</label>
        <select name="gender">
            <option value="">—</option>
            <option value="M" <?= old('gender') === 'M' ? 'selected' : '' ?>>Male</option>
            <option value="F" <?= old('gender') === 'F' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <div class="field">
        <label>Date of birth</label>
        <input type="date" name="date_of_birth" value="<?= e(old('date_of_birth')) ?>">
    </div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn secondary" href="<?= e(route_link('students')) ?>">Cancel</a>
    </div>
</form>