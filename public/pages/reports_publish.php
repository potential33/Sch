<?php
// /public/app/public/pages/reports_publish.php
$pageTitle = 'Publish report cards';

$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

$sessionId  = (int) ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0));
$classArmId = (int) ($_GET['class_arm_id'] ?? ($classArms[0]['id'] ?? 0));
$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $eid    = isset($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : null;

        if ($action === 'publish_all' || $action === 'unpublish_all') {
    $students = fetch_all(
        "SELECT e.id AS enrollment_id
         FROM enrollments e
         WHERE e.class_arm_id = ? AND e.session_id = ?",
        [$classArmId, $sessionId]
    );
    $ok = 0; $skipped = 0;
    foreach ($students as $st) {
        try {
            _publish_one((int)$st['enrollment_id'], $termId, $action === 'publish_all');
            $ok++;
        } catch (AppException $e) {
            $skipped++;
        }
    }
    flash_set($skipped > 0 ? 'error' : 'success',
        $action === 'publish_all'
            ? "Published {$ok} student(s)." . ($skipped ? " Skipped {$skipped} with missing subjects — publish those individually as provisional." : '')
            : "Unpublished {$ok} student(s).");
        } elseif ($eid && ($action === 'publish' || $action === 'publish_provisional' || $action === 'unpublish')) {
    $force = $action === 'publish_provisional';
    _publish_one($eid, $termId, $action !== 'unpublish', $force);
    flash_set('success',
        $action === 'unpublish' ? 'Unpublished.'
        : ($force ? 'Published provisionally.' : 'Published.'));
        }
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/?' . http_build_query([
        'r' => 'reports/publish',
        'session_id' => $sessionId, 'term_id' => $termId, 'class_arm_id' => $classArmId,
    ]));
}

function _publish_one(int $enrollmentId, int $termId, bool $publish, bool $force = false): void
{
    if ($publish) {
        $has = fetch_val(
            "SELECT 1 FROM results
             WHERE enrollment_id = ? AND term_id = ?
               AND status IN ('submitted','approved') LIMIT 1",
            [$enrollmentId, $termId]
        );
        if (!$has) throw new AppException('Cannot publish: no submitted results for this student.');

        // Coverage gate: every expected subject should have a grade OR an exemption
        if (!$force) {
            $enr = fetch_one(
                "SELECT class_arm_id, session_id FROM enrollments WHERE id = ?", [$enrollmentId]);
            if ($enr) {
                $missing = (int) fetch_val(
                    "SELECT COUNT(*) FROM class_arm_subjects cas
                     WHERE cas.class_arm_id = ?
                       AND NOT EXISTS (
                         SELECT 1 FROM results r
                         WHERE r.enrollment_id = ?
                           AND r.subject_id = cas.subject_id
                           AND r.term_id = ?
                           AND r.status IN ('submitted','approved')
                       )
                       AND NOT EXISTS (
                         SELECT 1 FROM subject_exemptions se
                         WHERE se.enrollment_id = ? AND se.subject_id = cas.subject_id
                       )",
                    [(int)$enr['class_arm_id'], $enrollmentId, $termId, $enrollmentId]
                );
                if ($missing > 0) {
                    throw new AppException(
                        "Cannot publish: {$missing} subject(s) still missing. "
                        . "Use 'publish provisionally' to override."
                    );
                }
            }
        }

        q("INSERT INTO term_reports (enrollment_id, term_id, status, published_at)
           VALUES (?, ?, 'published', ?)
           ON CONFLICT(enrollment_id, term_id) DO UPDATE SET
             status = 'published', published_at = excluded.published_at",
          [$enrollmentId, $termId, date('Y-m-d H:i:s')]);
    } else {
        q("UPDATE term_reports SET status = 'draft', published_at = NULL
           WHERE enrollment_id = ? AND term_id = ?", [$enrollmentId, $termId]);
    }
}

// Fetch rows
$students = fetch_all(
    "SELECT e.id AS enrollment_id, s.admission_no, s.full_name,
            tr.status, tr.published_at,
            (SELECT COUNT(*) FROM results r
             WHERE r.enrollment_id = e.id AND r.term_id = ?
               AND r.status IN ('submitted','approved')) AS result_count
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     LEFT JOIN term_reports tr ON tr.enrollment_id = e.id AND tr.term_id = ?
     WHERE e.class_arm_id = ? AND e.session_id = ?
     ORDER BY s.full_name",
    [$termId, $termId, $classArmId, $sessionId]
);
?>
<h1>Publish report cards</h1>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="reports/publish">
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

<div class="card">
    <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem;">
        <form method="post" onsubmit="return confirm('Publish all reports for this class?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="publish_all">
            <button class="btn" type="submit">Publish all</button>
        </form>
        <form method="post" onsubmit="return confirm('Unpublish all reports for this class?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unpublish_all">
            <button class="btn secondary" type="submit">Unpublish all</button>
        </form>
    </div>

    <?php if (!$students): ?>
        <p class="muted">No enrolled students.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Student</th><th>Subjects w/ results</th><th>Status</th><th>Published</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s):
            $status = $s['status'] ?? 'not created';
            $canPublish = (int)$s['result_count'] > 0;
        ?>
            <tr>
                <td><?= e($s['full_name']) ?><br><small class="muted"><?= e($s['admission_no']) ?></small></td>
                <td><?= e((string)$s['result_count']) ?></td>
                <td>
                    <?php if ($status === 'published'): ?>
                        <span class="tag ok">published</span>
                    <?php else: ?>
                        <span class="tag warn"><?= e($status) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= e($s['published_at'] ?? '—') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="enrollment_id" value="<?= e($s['enrollment_id']) ?>">
                        <?php if ($status === 'published'): ?>
                            <input type="hidden" name="action" value="unpublish">
                            <button class="btn small secondary" type="submit">Unpublish</button>
                        <?php else: ?>
    <input type="hidden" name="action" value="publish">
    <button class="btn small" type="submit" <?= $canPublish ? '' : 'disabled title="No submitted results"' ?>>Publish</button>
    <?php if ($canPublish): ?>
        <button class="btn small secondary" type="submit"
                name="action" value="publish_provisional"
                onclick="return confirm('Publish provisionally despite missing subjects?')">
            Provisional
        </button>
    <?php endif; ?>
<?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>