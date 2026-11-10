<?php
// /public/app/public/pages/results_entry.php
$pageTitle = 'Grade entry';

// Lookups for the pickers
$sessions  = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$subjects  = fetch_all("SELECT id, name FROM subjects ORDER BY name");
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);

// If teacher, restrict subjects and class arms to their assignments
$myTeacherId = auth_teacher_id();
if ($myTeacherId !== null) {
    $subjects = fetch_all(
        "SELECT DISTINCT s.id, s.name
         FROM teacher_subjects ts
         JOIN subjects s ON s.id = ts.subject_id
         WHERE ts.teacher_id = ?
         ORDER BY s.name",
        [$myTeacherId]
    );
    $classArms = fetch_all(
        "SELECT DISTINCT ca.id, c.name AS class_name, ca.arm
         FROM teacher_subjects ts
         JOIN class_arms ca ON ca.id = ts.class_arm_id
         JOIN classes c ON c.id = ca.class_id
         WHERE ts.teacher_id = ?
         ORDER BY c.name, ca.arm",
        [$myTeacherId]
    );
}

// Resolve current pick
$sessionId  = (int) ($_GET['session_id']  ?? ($sessions[0]['id'] ?? 0));
$subjectId  = (int) ($_GET['subject_id']  ?? 0);
$classArmId = (int) ($_GET['class_arm_id'] ?? 0);

// Terms for the session
$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$limits  = grade_limits();

// Belt-and-braces: teacher can only save to their own assigned subject+class
if ($myTeacherId !== null && $subjectId && $classArmId) {
    $assigned = fetch_val(
        "SELECT 1 FROM teacher_subjects
         WHERE teacher_id = ? AND subject_id = ? AND class_arm_id = ? AND session_id = ?",
        [$myTeacherId, $subjectId, $classArmId, $sessionId]
    );
    if (!$assigned) {
        throw new AppException('You are not assigned to teach this subject in this class.');
    }
}
$weights = $limits; // ['CA1'=>10, 'CA2'=>10, 'CA3'=>10, 'EXAM'=>70]

// Handle row save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_row'])) {
    try {
        $res = grade_save(
            (int) $_POST['enrollment_id'],
            $subjectId,
            $termId,
            array_intersect_key($_POST, array_flip(array_keys($limits))),
            auth_teacher_id() // null for admin, teacher id for teachers
        );
        if ($res['ok']) {
            flash_set('success', 'Saved. ' . (
                $res['status'] === 'submitted' ? 'Complete.' : 'Draft (missing: '
                . implode(', ', $res['missing']) . ').'
            ));
        } else {
            flash_set('error', implode(' ', $res['errors']));
        }
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    $qs = http_build_query([
        'r' => 'results/entry',
        'session_id' => $sessionId, 'term_id' => $termId,
        'subject_id' => $subjectId, 'class_arm_id' => $classArmId,
    ]);
    redirect('/?' . $qs);
}

// Handle bulk submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['submit_term'])) {
    try {
        $res = grade_submit_term_for_class($subjectId, $classArmId, $sessionId, $termId);
        if ($res['ok']) {
            flash_set('success', "Submitted {$res['flipped']} result(s).");
        } else {
            flash_set('error', 'Cannot submit: ' . implode(' · ', $res['errors']));
        }
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    $qs = http_build_query([
        'r' => 'results/entry',
        'session_id' => $sessionId, 'term_id' => $termId,
        'subject_id' => $subjectId, 'class_arm_id' => $classArmId,
    ]);
    redirect('/?' . $qs);
}

$rows = ($subjectId && $classArmId && $termId)
    ? grades_for_class_term($subjectId, $classArmId, $sessionId, $termId)
    : [];
?>
<h1>Grade entry</h1>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="results/entry">
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

<?php if (!$rows): ?>
    <div class="card">
        <p class="muted">Pick a subject and class arm to enter grades. If a class arm has no enrollments for the session, nothing will appear.</p>
    </div>
<?php else: ?>
<div class="card">
    <table>
        <thead>
            <tr>
    <th>Student</th>
    <?php foreach (array_keys($weights) as $comp): ?>
        <th style="width:70px;">
            <?= e($comp) ?><br>
            <small class="muted">/<?= e($weights[$comp]) ?></small>
        </th>
    <?php endforeach; ?>
    <th style="width:70px;">Total</th>
    <th style="width:100px;">Status</th>
    <th style="width:80px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $status = $r['result_status'];
            $rowClass = match ($status) {
                'submitted' => 'row-ok',
                'approved'  => 'row-approved',
                'draft'     => 'row-draft',
                default     => 'row-empty',
            };
        ?>
            <tr class="<?= e($rowClass) ?>" data-row>
                <td>
                    <strong><?= e($r['full_name']) ?></strong><br>
                    <small class="muted"><?= e($r['admission_no']) ?></small>
                </td>
                <form method="post" style="display:contents;">
    <?= csrf_field() ?>
    <input type="hidden" name="save_row" value="1">
                    <input type="hidden" name="enrollment_id" value="<?= e($r['enrollment_id']) ?>">
                    <?php foreach (array_keys($weights) as $comp):
    $field = strtolower($comp);
    $val = $r[$field] ?? '';
?>
<td><input class="score-input" data-field="<?= e($comp) ?>" name="<?= e($comp) ?>"
    value="<?= e($val) ?>"
    max="<?= e($weights[$comp]) ?>" <?= $status === 'approved' ? 'disabled' : '' ?>></td>
<?php endforeach; ?>
                    <td class="total-cell"><?= e(fmt_num($r['total'])) ?></td>
                    <td>
                        <?php if ($status === 'submitted'): ?>
                            <span class="tag ok">submitted</span>
                        <?php elseif ($status === 'approved'): ?>
                            <span class="tag approved">approved </span>
                        <?php elseif ($status === 'draft'): ?>
                            <span class="tag warn">draft</span>
                        <?php else: ?>
                            <span class="tag empty">no entry</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status !== 'approved'): ?>
                            <button class="btn small" type="submit">Save</button>
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <form method="post" onsubmit="return confirm('Submit all draft results for this class? This will lock further edits.')">
    <?= csrf_field() ?>
    <input type="hidden" name="submit_term" value="1">
        <button class="btn" type="submit">Submit all drafts for this class</button>
        <span class="muted" style="margin-left:.75rem;">Requires every row to be complete.</span>
    </form>
</div>

<script>
// Live total: sum present numeric values. Turn red if over max.
document.querySelectorAll('[data-row]').forEach(row => {
    const inputs = row.querySelectorAll('.score-input');
    const totalCell = row.querySelector('.total-cell');
    function recompute() {
        let total = 0, any = false, bad = false;
        inputs.forEach(inp => {
            const max = parseFloat(inp.getAttribute('max'));
            const val = inp.value.trim();
            inp.style.borderColor = '';
            if (val !== '') {
                const n = parseFloat(val);
                if (!isNaN(n) && n >= 0 && n <= max) {
                    total += n; any = true;
                } else {
                    bad = true;
                    inp.style.borderColor = '#dc2626';
                }
            }
        });
        totalCell.textContent = any && !bad
            ? (Math.round(total * 100) / 100).toString()
            : '—';
    }
    inputs.forEach(inp => inp.addEventListener('input', recompute));
    recompute();
});
</script>
<?php endif; ?>