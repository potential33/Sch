<?php
// /public/app/public/pages/results_approve.php
$pageTitle = 'Approve results';

$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$subjects  = fetch_all("SELECT id, name FROM subjects ORDER BY name");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$sessionId  = (int) ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0));
$subjectId  = (int) ($_GET['subject_id']  ?? 0);
$classArmId = (int) ($_GET['class_arm_id'] ?? 0);

$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

// Handle approve / unapprove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    try {
        if (!fetch_val("SELECT 1 FROM enrollments e
                        WHERE e.class_arm_id = ? AND e.session_id = ? LIMIT 1",
                       [$classArmId, $sessionId])) {
            throw new AppException('No enrollments for that class + session.');
        }

        $targetStatus = $_POST['action'] === 'approve' ? 'approved' : 'submitted';
        $fromStatus   = $_POST['action'] === 'approve' ? 'submitted' : 'approved';

        // Only flip if all rows are in the expected "from" state
        $pending = (int) fetch_val(
            "SELECT COUNT(*) FROM results r
             JOIN enrollments e ON e.id = r.enrollment_id
             WHERE r.subject_id = ? AND r.term_id = ?
               AND e.class_arm_id = ? AND e.session_id = ?
               AND r.status != ?",
            [$subjectId, $termId, $classArmId, $sessionId, $fromStatus]
        );
        if ($pending > 0) {
            throw new AppException(
                $_POST['action'] === 'approve'
                  ? "Cannot approve: {$pending} result(s) still not submitted."
                  : "Cannot revert: {$pending} result(s) not in approved state."
            );
        }

        $affected = 0;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE results SET status = ?, updated_at = ?
                 WHERE subject_id = ? AND term_id = ?
                   AND enrollment_id IN (
                     SELECT id FROM enrollments
                     WHERE class_arm_id = ? AND session_id = ?
                   )");
            $stmt->execute([
                $targetStatus, date('Y-m-d H:i:s'),
                $subjectId, $termId, $classArmId, $sessionId
            ]);
            $affected = $stmt->rowCount();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        flash_set('success',
            ($_POST['action'] === 'approve' ? 'Approved ' : 'Reverted ')
            . $affected . ' result(s).');
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    $qs = http_build_query([
        'r' => 'results/approve',
        'session_id' => $sessionId, 'term_id' => $termId,
        'subject_id' => $subjectId, 'class_arm_id' => $classArmId,
    ]);
    redirect('/?' . $qs);
}

// Status summary
$summary = null;
if ($subjectId && $classArmId && $termId) {
    $counts = fetch_all(
        "SELECT r.status, COUNT(*) AS n
         FROM results r
         JOIN enrollments e ON e.id = r.enrollment_id
         WHERE r.subject_id = ? AND r.term_id = ?
           AND e.class_arm_id = ? AND e.session_id = ?
         GROUP BY r.status",
        [$subjectId, $termId, $classArmId, $sessionId]
    );
    $summary = ['draft' => 0, 'submitted' => 0, 'approved' => 0];
    foreach ($counts as $c) $summary[$c['status']] = (int) $c['n'];

    $totalEnrolled = (int) fetch_val(
        "SELECT COUNT(*) FROM enrollments WHERE class_arm_id = ? AND session_id = ?",
        [$classArmId, $sessionId]
    );
    $summary['enrolled'] = $totalEnrolled;
    $summary['missing']  = $totalEnrolled - array_sum(
        ['draft' => $summary['draft'], 'submitted' => $summary['submitted'],
         'approved' => $summary['approved']]
    );
}
?>
<h1>Approve results</h1>
<p class="muted">Approve submitted results for a subject + class + term. Approved results become read-only for teachers.</p>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="results/approve">
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
        <label>Subject</label>
        <select name="subject_id" onchange="this.form.submit()">
            <option value="">— pick —</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $subjectId ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="margin:0;">
        <label>Class arm</label>
        <select name="class_arm_id" onchange="this.form.submit()">
            <option value="">— pick —</option>
            <?php foreach ($classArms as $ca): ?>
                <option value="<?= e($ca['id']) ?>" <?= (int)$ca['id'] === $classArmId ? 'selected' : '' ?>>
                    <?= e($ca['class_name'] . ' ' . $ca['arm']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($summary): ?>
<div class="card">
    <h2>Status</h2>
    <div class="grid">
        <div class="stat">
            <div class="num"><?= e((string)$summary['enrolled']) ?></div>
            <div class="lbl">Enrolled</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$summary['missing']) ?></div>
            <div class="lbl">No grade</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$summary['draft']) ?></div>
            <div class="lbl">Draft</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$summary['submitted']) ?></div>
            <div class="lbl">Submitted</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$summary['approved']) ?></div>
            <div class="lbl">Approved 🔒</div>
        </div>
    </div>

    <div style="display:flex; gap:.5rem; margin-top:1rem; flex-wrap:wrap;">
        <form method="post" onsubmit="return confirm('Approve all submitted results for this class+subject+term?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <button class="btn" type="submit" <?= $summary['submitted'] === 0 ? 'disabled' : '' ?>>
                Approve all submitted
            </button>
        </form>
        <form method="post" onsubmit="return confirm('Revert approved results back to submitted?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unapprove">
            <button class="btn secondary" type="submit" <?= $summary['approved'] === 0 ? 'disabled' : '' ?>>
                Revert approvals
            </button>
        </form>
        <a class="btn secondary" href="<?= e(route_link('results/entry', [
            'session_id' => $sessionId, 'term_id' => $termId,
            'subject_id' => $subjectId, 'class_arm_id' => $classArmId,
        ])) ?>">Open grade entry →</a>
    </div>

    <?php if ($summary['missing'] > 0): ?>
        <p class="muted" style="margin-top:.75rem;">
            ⚠ <?= e((string)$summary['missing']) ?> enrolled student(s) still have no grade entry.
            Complete them in <a href="<?= e(route_link('results/entry', [
                'session_id' => $sessionId, 'term_id' => $termId,
                'subject_id' => $subjectId, 'class_arm_id' => $classArmId,
            ])) ?>">grade entry</a> first.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>