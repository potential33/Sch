<?php
// /public/app/public/pages/reports_student.php
$pageTitle = 'Report card';

$students = fetch_all("SELECT id, admission_no, full_name FROM students ORDER BY full_name");
$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");

$studentId = (int) ($_GET['student_id'] ?? 0);
$sessionId = (int) ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));

$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$report = null;
if ($studentId && $sessionId && $termId) {
    $report = report_student_term($studentId, $sessionId, $termId);
}
?>
<style>
@media print {
    .topbar, .footer, .flash, .no-print { display: none !important; }
    body { background: #fff; }
    .card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
    main { max-width: none; padding: 0; }
    .report-header { border-bottom: 2px solid #000; padding-bottom: .5rem; }
}
.report-header { text-align: center; margin-bottom: 1rem; }
.report-header h1 { margin: 0; }
.report-header .sub { color: #555; font-size: .9rem; }
.report-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem 1.5rem; margin: 1rem 0; }
.report-meta .label { color: #666; font-size: .85rem; }
.report-meta .value { font-weight: 600; }
.signature { margin-top: 2rem; display: flex; justify-content: space-between; }
.signature > div { border-top: 1px solid #000; padding-top: .25rem; min-width: 180px; text-align: center; font-size: .9rem; }
</style>

<h1 class="no-print">Report card</h1>

<form method="get" class="card no-print" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="reports/student">
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
        <label>Student</label>
        <select name="student_id" onchange="this.form.submit()">
            <option value="">— pick —</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= (int)$s['id'] === $studentId ? 'selected' : '' ?>>
                    <?= e($s['admission_no'] . ' — ' . $s['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($studentId && !$report): ?>
    <div class="card"><p class="muted">Student is not enrolled in that session.</p></div>
<?php elseif ($report): ?>
    <div class="card">
        <div class="report-header">
            <h1>Term Report Card</h1>
            <div class="sub">
                <?= e($report['session']['name']) ?> ·
                <?= e($report['term']['name']) ?>
            </div>
        </div>

        <div class="report-meta">
            <div>
                <div class="label">Student</div>
                <div class="value"><?= e($report['student']['full_name']) ?></div>
            </div>
            <div>
                <div class="label">Admission #</div>
                <div class="value"><?= e($report['student']['admission_no']) ?></div>
            </div>
            <div>
                <div class="label">Class</div>
                <div class="value"><?= e($report['enrollment']['class_name'] . ' ' . $report['enrollment']['arm']) ?></div>
            </div>
            <div>
                <div class="label">Position</div>
                <div class="value">
                    <?php if ($report['position'] !== null): ?>
                        <?= e((string)$report['position']) ?> of <?= e((string)$report['class_size']) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$report['subjects']): ?>
            <p class="muted">No results recorded for this term yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th style="text-align:right;">CA1</th>
                    <th style="text-align:right;">CA2</th>
                    <th style="text-align:right;">CA3</th>
                    <th style="text-align:right;">Exam</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:center;">Grade</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['subjects'] as $sub): ?>
                <tr>
                    <td><strong><?= e($sub['subject_name']) ?></strong>
                        <small class="muted"><?= e($sub['code']) ?></small></td>
                    <td style="text-align:right;"><?= e($sub['ca1'] ?? '—') ?></td>
                    <td style="text-align:right;"><?= e($sub['ca2'] ?? '—') ?></td>
                    <td style="text-align:right;"><?= e($sub['ca3'] ?? '—') ?></td>
                    <td style="text-align:right;"><?= e($sub['exam'] ?? '—') ?></td>
                    <td style="text-align:right;"><strong><?= e(rtrim(rtrim((string)$sub['total'],'0'),'.')) ?></strong></td>
                    <td style="text-align:center;"><strong><?= e($sub['grade']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right;"><strong>Total / Average</strong></td>
                    <td style="text-align:right;"><strong><?= e(rtrim(rtrim((string)$report['totals']['sum'],'0'),'.')) ?></strong></td>
                    <td style="text-align:center;">
                        <strong><?= e(number_format($report['totals']['avg'], 1)) ?></strong>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top:1rem; display:flex; gap:2rem; flex-wrap:wrap;">
            <div><span class="label muted">Subjects:</span> <strong><?= e((string)$report['totals']['count']) ?></strong></div>
            <div><span class="label muted">Class average:</span>
                <strong>
                    <?= $report['class_avg'] !== null
                        ? e(number_format($report['class_avg'], 1))
                        : '—' ?>
                </strong>
            </div>
        </div>

        <div class="signature">
            <div>Form Teacher</div>
            <div>Principal</div>
            <div>Parent / Guardian</div>
        </div>

        <p style="margin-top:1.5rem; font-size:.85rem;" class="muted no-print">
            <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
        </p>
        <?php endif; ?>
    </div>
<?php endif; ?>