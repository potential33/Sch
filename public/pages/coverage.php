<?php
// /public/app/public/pages/coverage.php
$pageTitle = 'Coverage';

$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$sessionId = (int) ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$isTeacher = auth_role_is('teacher');
$isAdmin   = auth_role_is('admin');

if ($isTeacher) {
    $teacherId = (int) auth_teacher_id();
    $rows = coverage_for_teacher($teacherId, $sessionId, $termId);
    ?>
    <h1>My coverage</h1>
    <p class="muted">Grades you still need to enter for <?= e($terms[0]['name'] ?? '') ?>.</p>

    <form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
        <input type="hidden" name="r" value="coverage">
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
    </form>

    <div class="card">
        <?php if (!$rows): ?>
            <p class="muted">No assignments for this session.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Class</th><th>Subject</th><th>Expected</th><th>Submitted</th><th>Missing</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $tagClass = match ($r['status']) {
                    'complete' => 'ok',
                    'partial'  => 'warn',
                    'empty'    => 'empty',
                };
            ?>
                <tr>
                    <td><?= e($r['class_label']) ?></td>
                    <td><?= e($r['subject_name']) ?></td>
                    <td><?= e((string)$r['expected']) ?></td>
                    <td><?= e((string)$r['submitted']) ?></td>
                    <td><?= e((string)$r['missing']) ?></td>
                    <td><span class="tag <?= $tagClass ?>"><?= e($r['status']) ?></span></td>
                    <td>
                        <a class="btn small" href="<?= e(route_link('results/entry', [
                            'session_id' => $sessionId, 'term_id' => $termId,
                            'subject_id' => $r['subject_id'], 'class_arm_id' => $r['class_arm_id'],
                        ])) ?>">Enter →</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
    return;
}

// Admin view
$classArms = fetch_all(
    "SELECT ca.id, c.name AS class_name, ca.arm
     FROM class_arms ca JOIN classes c ON c.id = ca.class_id
     ORDER BY c.name, ca.arm"
);
$classArmId = (int) ($_GET['class_arm_id'] ?? ($classArms[0]['id'] ?? 0));

$report = ($classArmId && $sessionId && $termId)
    ? coverage_for_class($classArmId, $sessionId, $termId)
    : null;
?>
<h1>Coverage</h1>
<p class="muted">Expected vs actual grade entries. Use this before publishing reports.</p>

<form method="get" class="card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="coverage">
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

<?php if ($report): ?>
    <div class="grid" style="margin-bottom:1rem;">
        <div class="stat">
            <div class="num"><?= e((string)$report['totals']['coverage_pct']) ?>%</div>
            <div class="lbl">Coverage</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$report['totals']['expected']) ?></div>
            <div class="lbl">Expected</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$report['totals']['submitted']) ?></div>
            <div class="lbl">Submitted</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$report['totals']['missing']) ?></div>
            <div class="lbl">Missing</div>
        </div>
        <div class="stat">
            <div class="num"><?= e((string)$report['totals']['exempted']) ?></div>
            <div class="lbl">Exempted</div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Subject</th><th>Expected</th><th>Submitted</th><th>Missing</th><th>Exempted</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($report['subjects'] as $sub):
                $tagClass = match ($sub['status']) {
                    'complete' => 'ok',
                    'partial'  => 'warn',
                    'empty'    => 'empty',
                };
            ?>
                <tr>
                    <td><strong><?= e($sub['name']) ?></strong> <small class="muted"><?= e($sub['code']) ?></small></td>
                    <td><?= e((string)$sub['expected']) ?></td>
                    <td><?= e((string)$sub['submitted']) ?></td>
                    <td><?= e((string)$sub['missing']) ?></td>
                    <td><?= e((string)$sub['exempted']) ?></td>
                    <td><span class="tag <?= $tagClass ?>"><?= e($sub['status']) ?></span></td>
                    <td>
                        <?php if ($sub['status'] !== 'complete'): ?>
                            <details>
                                <summary style="cursor:pointer; font-size:.85rem;">missing list</summary>
                                <ul style="margin:.5rem 0 0 1rem; font-size:.85rem;">
                                    <?php foreach ($sub['missing_students'] as $m): ?>
                                        <li><?= e($m['full_name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>