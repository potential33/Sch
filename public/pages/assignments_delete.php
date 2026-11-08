<?php
// /public/app/public/pages/assignments_delete.php
$pageTitle = 'Unassign teacher';

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

$assign = fetch_one(
    "SELECT ts.id, t.full_name AS teacher, sub.name AS subject,
            c.name || ' ' || ca.arm AS class_label, ses.name AS session
     FROM teacher_subjects ts
     JOIN teachers t ON t.id = ts.teacher_id
     JOIN subjects sub ON sub.id = ts.subject_id
     JOIN class_arms ca ON ca.id = ts.class_arm_id
     JOIN classes c ON c.id = ca.class_id
     JOIN sessions ses ON ses.id = ts.session_id
     WHERE ts.id = ?",
    [$id]
);
if (!$assign) throw new AppException('Assignment not found.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        unassign_teacher($id);
        flash_set('success', 'Assignment removed.');
        redirect(route_link('assignments'));
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
        redirect(route_link('assignments'));
    }
}
?>
<h1>Unassign teacher</h1>
<div class="card">
    <p>
        <strong><?= e($assign['teacher']) ?></strong>
        will no longer be assigned to teach
        <strong><?= e($assign['subject']) ?></strong>
        in <strong><?= e($assign['class_label']) ?></strong>
        (<em><?= e($assign['session']) ?></em>).
    </p>
    <p class="muted">
        Existing grades entered by this teacher are <strong>not</strong> affected —
        they remain in the results table.
    </p>
    <form method="post" style="display:flex; gap:.5rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <button class="btn danger" type="submit">Yes, unassign</button>
        <a class="btn secondary" href="<?= e(route_link('assignments')) ?>">Cancel</a>
    </form>
</div>