<?php
// /public/app/public/pages/reports_class.php
$pageTitle = 'Class broadsheet';

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

$report = null;
if ($classArmId && $sessionId && $termId) {
    $report = report_class_term($classArmId, $sessionId, $termId);
}
?>
<style>
@media print {
    .topbar, .footer, .flash, .no-print { display: none !important; }
    body { background: #fff; }
    .card { box-shadow: none; border: 1px solid #ccc; }
    main { max-width: none; padding: 0; }
    table { font-size: .8rem; }
    th, td { padding: .25rem .35rem; }
}
.broadsheet th, .broadsheet td { font-size: .85rem; }
.broadsheet th { position: sticky; top: 0; background: #f9fafb; }
.broadsheet .total-cell { background: #f0fdf4; font-weight: 600; }
.broadsheet .rank-cell { background: #eef2ff; font-weight: 600; text-align: center; }
</style>

<h1 class="no-print">Class broadsheet</h1>

<form method="get" class="card no-print" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="reports/class">
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
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:.5rem;">
        <div>
            <h2 style="margin:0;">
                <?= e($report['class_arm']['class_name'] . ' ' . $report['class_arm']['arm']) ?>
                <small class="muted">·
                    <?= e($report['session']['name']) ?> ·
                    <?= e($report['term']['name']) ?>
                </small>
            </h2>
            <p class="muted" style="margin:.25rem 0 0;">
                Class average: <strong><?= e(number_format($report['class_avg'], 1)) ?></strong>
                · <?= count($report['rows']) ?> student(s)
            </p>
        </div>
        <button class="btn no-print" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
</div>

<?php if (!$report['subjects']): ?>
    <div class="card"><p class="muted">No submitted results for this class/term yet.</p></div>
<?php else: ?>
<div class="card" style="overflow-x:auto;">
    <table class="broadsheet">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <?php foreach ($report['subjects'] as $sub): ?>
                    <th title="<?= e($sub['name']) ?>">
                        <?= e($sub['code']) ?>
                    </th>
                <?php endforeach; ?>
                <th>Total</th>
                <th>Avg</th>
                <th>Pos</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($report['rows'] as $i => $r): ?>
            <tr>
                <td><?= e((string)($i + 1)) ?></td>
                <td>
                    <strong><?= e($r['student']['full_name']) ?></strong><br>
                    <small class="muted"><?= e($r['student']['admission_no']) ?></small>
                </td>
                <?php foreach ($report['subjects'] as $sub):
                    $cell = $r['scores'][(int)$sub['id']] ?? null; ?>
                    <td style="text-align:right;">
                        <?php if ($cell && $cell['total'] !== null): ?>
                            <?= e(rtrim(rtrim((string)$cell['total'],'0'),'.')) ?>
                            <small class="muted">(<?= e($cell['grade']) ?>)</small>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td class="total-cell" style="text-align:right;">
                    <?= $r['has_any'] ? e(rtrim(rtrim((string)$r['sum'],'0'),'.')) : '—' ?>
                </td>
                <td style="text-align:right;">
                    <?= $r['has_any'] ? e(number_format($r['avg'], 1)) : '—' ?>
                </td>
                <td class="rank-cell">
                    <?= $r['position'] !== null ? e((string)$r['position']) : '—' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted" style="margin-top:.75rem; font-size:.85rem;">
        Scores show subject total with grade band in parentheses. Positions use competition ranking (ties share a rank).
    </p>
</div>
<?php endif; ?>
<?php endif; ?>