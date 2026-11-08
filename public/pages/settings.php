<?php
// /public/app/public/pages/settings.php
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        setting_set('school_name',    trim($_POST['school_name'] ?? ''));
        setting_set('school_motto',   trim($_POST['school_motto'] ?? ''));
        setting_set('school_address', trim($_POST['school_address'] ?? ''));
        flash_set('success', 'Settings saved.');
        redirect(route_link('settings'));
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
        redirect(route_link('settings'));
    }
}

$s = settings_all();
?>
<h1>Settings</h1>

<form method="post" class="card">
    <?= csrf_field() ?>
    <div class="field">
        <label>School name</label>
        <input name="school_name" required value="<?= e($s['school_name'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Motto (optional)</label>
        <input name="school_motto" value="<?= e($s['school_motto'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Address (optional)</label>
        <input name="school_address" value="<?= e($s['school_address'] ?? '') ?>">
    </div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
    </div>
</form>