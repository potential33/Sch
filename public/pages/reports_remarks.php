<?php
// /public/app/public/pages/reports_remarks.php
$pageTitle = 'Report card remarks';

$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$sessionId  = (int) ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0));
$classArmId = (int) ($_GET['class_arm_id'] ?? ($classArms[0]['id'] ?? 0));
$terms = $sessionId
    ? fetch_all("SELECT id, name, next_resumption_date FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_remarks'])) {
    try {
        $enrollmentId = (int) $_POST['enrollment_id'];
        $ctr = trim($_POST['class_teacher_remark'] ?? '');
        $pr  = trim($_POST['principal_remark'] ?? '');

        q("INSERT INTO term_reports
            (enrollment_id, term_id, class_teacher_remark, principal_remark, status)
           VALUES (?, ?, ?, ?, 'draft')
           ON CONFLICT(enrollment_id, term_id) DO UPDATE SET
             class_teacher_remark = excluded.class_teacher_remark,
             principal_remark     = excluded.principal_remark",
          [$enrollmentId, $termId, $ctr !== '' ? $ctr : null, $pr !== '' ? $pr : null]);
        flash_set('success', 'Remarks saved.');
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    $qs = http_build_query([
        'r' => 'reports/remarks',
        'session_id' => $sessionId, 'term_id' => $termId, 'class_arm_id' => $classArmId,
    ]);
    redirect('/?' . $qs);
}

// Also handle updating term next_resumption_date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_next_resume'])) {
    $date = trim($_POST['next_resumption_date'] ?? '');
    q("UPDATE terms SET next_resumption_date = ? WHERE id = ?",
      [$date !== '' ? $date : null, $termId]);
    flash_set('success', 'Next resumption date updated.');
    redirect('/?' . http_build_query([
        'r' => 'reports/remarks',
        'session_id' => $sessionId, 'term_id' => $termId, 'class_arm_id' => $classArmId,
    ]));
}

// Fetch students + existing remarks
$students = fetch_all(
    "SELECT e.id AS enrollment_id, s.admission_no, s.full_name,
            tr.class_teacher_remark, tr.principal_remark, tr.status
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     LEFT JOIN term_reports tr ON tr.enrollment_id = e.id AND tr.term_id = ?
     WHERE e.class_arm_id = ? AND e.session_id = ?
     ORDER BY s.full_name",
    [$termId, $classArmId, $sessionId]
);

$currentTerm = null;
foreach ($terms as $t) if ((int)$t['id'] === $termId) { $currentTerm = $t; break; }
?>
<h1>Report card remarks</h1>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="reports/remarks">
    <div class="field" style="margin:0;">
        <label>Session</label>
        <select name="session_id" onchange="this.form.submit()">
            <?php foreach ($sessions as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="margin:0;">
        <label>Term</label>
        <select name="term_id" onchange="this.form.submit()">
            <?php foreach ($terms as $t): ?>
                <option value="<?= e($t['id']) ?>" <?= (int)$t['id'] === $termId ? 'selected' : '' ?>>
                    <?= e($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="margin:0;">
        <label>Class arm</label>
        <select name="class_arm_id" onchange="this.form.submit()">
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>" <?= (int)$ca['id'] === $classArmId ? 'selected' : '' ?>>
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($currentTerm): ?>
<div class="card">
    <h2>Term settings</h2>
    <form method="post" style="display:flex; gap:.75rem; align-items:end; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="save_next_resume" value="1">
        <div class="field" style="margin:0; flex:1; min-width:200px;">
            <label>Next resumption date (<?= e($currentTerm['name']) ?>)</label>
            <input type="date" name="next_resumption_date"
                   value="<?= e($currentTerm['next_resumption_date'] ?? '') ?>">
        </div>
        <button class="btn" type="submit">Save date</button>
    </form>
</div>

<div class="card">
    <h2>Students</h2>
    <?php if (!$students): ?>
        <p class="muted">No enrolled students in this class + session.</p>
    <?php else: ?>
        <?php foreach ($students as $s): ?>
            <form method="post" style="border:1px solid #eee; border-radius:8px; padding:1rem; margin-bottom:1rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="save_remarks" value="1">
                <input type="hidden" name="enrollment_id" value="<?= e($s['enrollment_id']) ?>">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:.5rem;">
                    <strong><?= e($s['full_name']) ?></strong>
                    <small class="muted">
                        <?= e($s['admission_no']) ?>
                        · <?= e($s['status'] ?? 'draft') ?>
                    </small>
                </div>
                <div class="field">
                    <label>Class teacher's remark</label>
                    <input name="class_teacher_remark" maxlength="500"
                           value="<?= e($s['class_teacher_remark'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Principal's remark</label>
                    <input name="principal_remark" maxlength="500"
                           value="<?= e($s['principal_remark'] ?? '') ?>">
                </div>
                <button class="btn small" type="submit">Save remarks</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>