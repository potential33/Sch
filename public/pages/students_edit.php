<?php
// /public/app/public/pages/students_edit.php
$id = (int) ($_GET['id'] ?? 0);
$student = student_get($id);
if (!$student) throw new AppException('Student not found.');
$pageTitle = 'Edit ' . $student['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        student_update($id, [
            'admission_no'  => trim($_POST['admission_no'] ?? ''),
            'full_name'     => trim($_POST['full_name'] ?? ''),
            'gender'        => ($_POST['gender'] ?? '') === '' ? null : $_POST['gender'],
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') === '' ? null : $_POST['date_of_birth'],
        ]);
        flash_set('success', 'Student updated.');
        redirect(route_link('students/view', ['id' => $id]));
    } catch (AppException $e) {
        $error = $e->getMessage();
        // merge submitted values so the user doesn't lose them
        $student = array_merge($student, $_POST);
    }
}
?>
<h1>Edit student</h1>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
    <div class="field">
        <label>Admission number *</label>
        <input name="admission_no" required value="<?= e($student['admission_no']) ?>">
    </div>
    <div class="field">
        <label>Full name *</label>
        <input name="full_name" required value="<?= e($student['full_name']) ?>">
    </div>
    <div class="field">
        <label>Gender</label>
        <select name="gender">
            <option value="">—</option>
            <option value="M" <?= ($student['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Male</option>
            <option value="F" <?= ($student['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <div class="field">
        <label>Date of birth</label>
        <input type="date" name="date_of_birth" value="<?= e($student['date_of_birth'] ?? '') ?>">
    </div>
    <div class="actions">
        <button class="btn" type="submit">Update</button>
        <a class="btn secondary" href="<?= e(route_link('students/view', ['id' => $id])) ?>">Cancel</a>
    </div>
</form>

<form method="post" action="<?= e(route_link('students/edit', ['id' => $id])) ?>"
  <?= csrf_field() ?>
      onsubmit="return confirm('Delete this student? This cannot be undone.')" style="margin-top:1rem;">
    <input type="hidden" name="_delete" value="1">
    <button class="btn danger" type="submit">Delete student</button>
</form>

<?php
// Handle delete from the same page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_delete'])) {
    try {
        student_delete($id);
        flash_set('success', 'Student deleted.');
        redirect(route_link('students'));
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
        redirect(route_link('students/edit', ['id' => $id]));
    }
}
?>