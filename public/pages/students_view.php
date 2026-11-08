<?php
// /public/app/public/pages/students_view.php
$id = (int) ($_GET['id'] ?? 0);
$student = student_get($id);
if (!$student) {
    throw new AppException('Student not found.');
}
$pageTitle = $student['full_name'];

$enrollments = fetch_all(
    "SELECT e.id, ses.name AS session, c.name AS class_name, ca.arm
     FROM enrollments e
     JOIN sessions ses ON ses.id = e.session_id
     JOIN class_arms ca ON ca.id = e.class_arm_id
     JOIN classes c ON c.id = ca.class_id
     WHERE e.student_id = ?
     ORDER BY ses.name DESC",
    [$id]
);
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1><?= e($student['full_name']) ?></h1>
    <div>
        <a class="btn secondary" href="<?= e(route_link('students')) ?>">← Back</a>
        <?php if (auth_role_is('admin')): ?>
<a class="btn" href="<?= e(route_link('students/edit', ['id' => $id])) ?>">Edit</a>
<?php endif; ?>
    </div>
</div>

<div class="card">
    <p><strong>Admission #:</strong> <?= e($student['admission_no']) ?></p>
    <p><strong>Gender:</strong> <?= e($student['gender'] ?? '—') ?></p>
    <p><strong>Date of birth:</strong> <?= e($student['date_of_birth'] ?? '—') ?></p>
</div>

<div class="card">
    <h2>Enrollments</h2>
    <?php if (!$enrollments): ?>
        <p class="muted">Not enrolled in any session yet.</p>
        <a class="btn small" href="<?= e(route_link('enrollments/new', ['student_id' => $id])) ?>">Enroll now</a>
    <?php else: ?>
        <table>
            <thead><tr><th>Session</th><th>Class</th></tr></thead>
            <tbody>
            <?php foreach ($enrollments as $en): ?>
                <tr>
                    <td><?= e($en['session']) ?></td>
                    <td><?= e($en['class_name'] . ' ' . $en['arm']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>