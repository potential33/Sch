<?php
// /public/app/public/pages/me_report.php
$pageTitle = 'My Report Card';

$studentId = auth_student_id();
if (!$studentId) throw new AppException('Student account not linked.');

$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$sessionId = (int) ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));

$terms = $sessionId
    ? fetch_all("SELECT id, name, next_resumption_date FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = (int) ($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$report = null;
$published = false;
$termRow = null;

if ($sessionId && $termId) {
    $report = report_student_term($studentId, $sessionId, $termId);
    if ($report) {
        $termRow = fetch_one(
            "SELECT class_teacher_remark, principal_remark, status, published_at
             FROM term_reports
             WHERE enrollment_id = ? AND term_id = ?",
            [$report['enrollment']['enrollment_id'], $termId]
        );
        $published = $termRow && $termRow['status'] === 'published';
    }
}
?>
<style>
@media print {
    .topbar, .footer, .flash, .no-print { display: none !important; }
    body { background: #fff; }
    .card { box-shadow: none; border: 1px solid #ccc; }
    main { max-width: none; padding: 0; }
    .report-header { border-bottom: 2px solid #000; padding-bottom: .5rem; }
}
.report-header { text-align: center; margin-bottom: 1rem; }
.report-header h1 { margin: 0; font-size: 1.3rem; }
.report-header .sub { color: #555; font-size: .9rem; }
.report-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem 1.5rem; margin: 1rem 0; }
.report-meta .label { color: #666; font-size: .85rem; }
.report-meta .value { font-weight: 600; }
.remarks-block { display: grid; gap: 1rem; margin-top: 1.25rem; }
.remarks-block .remark { border-top: 1px solid #ddd; padding-top: .5rem; }
.signature { margin-top: 2rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.signature > div { border-top: 1px solid #000; padding-top: .25rem; min-width: 160px; text-align: center; font-size: .85rem; }
.next-resumption { margin-top: 1.25rem; padding: .5rem .75rem; background: #eef2ff; border-radius: 6px; text-align: center; }
</style>

<h1 class="no-print">My Report Card</h1>

<form method="get" class="card no-print" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
    <input type="hidden" name="r" value="me/report">
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

<?php if (!$report): ?>
    <div class="card"><p class="muted">You are not enrolled in that session.</p></div>
<?php elseif (!$published): ?>
    <div class="card">
        <p><strong>Your report card for this term is not yet available.</strong></p>
      <?php
    // Detect provisional: coverage gaps for this enrollment at publish time (approx)
    $enrId = (int)$report['enrollment']['enrollment_id'];
    $missingNow = (int) fetch_val(
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
        [(int)$report['enrollment']['class_arm_id'], $enrId, $termId, $enrId]
    );
?>
<?php if ($missingNow > 0): ?>
    <div class="flash flash-error" style="margin-bottom:1rem;">
        <strong>Provisional report.</strong>
        <?= e((string)$missingNow) ?> subject(s) not yet recorded. Contact the school office if this looks wrong.
    </div>
<?php endif; ?>
        <p class="muted">It will appear here once the school publishes it.</p>
    </div>
<?php else: ?>
    <?php
        $schoolName = setting_get('school_name', 'School');
        $bands      = grade_bands();
        $weights    = grade_limits();
        $componentLabels = array_keys($weights);
        $nextResume = fetch_val("SELECT next_resumption_date FROM terms WHERE id = ?", [$termId]);
    ?>
    <div class="card">
        <div class="report-header">
            <h1><?= e($schoolName) ?></h1>
            <div class="sub">
                <?= e($report['session']['name']) ?> ·
                <?= e($report['term']['name']) ?> Report Card
            </div>
            <div class="sub" style="font-size:.8rem;">
                Printed <?= e(date('Y-m-d')) ?>
            </div>
        </div>

        <div class="report-meta">
            <div><div class="label">Name</div><div class="value"><?= e($report['student']['full_name']) ?></div></div>
            <div><div class="label">Admission #</div><div class="value"><?= e($report['student']['admission_no']) ?></div></div>
            <div><div class="label">Class</div><div class="value"><?= e($report['enrollment']['class_name'] . ' ' . $report['enrollment']['arm']) ?></div></div>
            <div><div class="label">Students in class</div><div class="value"><?= e((string)$report['class_size']) ?></div></div>
        </div>

        <?php if (!$report['subjects']): ?>
            <p class="muted">No published results yet for this term.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <?php foreach ($componentLabels as $comp): ?>
                        <th style="text-align:right;"><?= e($comp) ?></th>
                    <?php endforeach; ?>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:center;">Grade</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['subjects'] as $sub):
                $subTotal = (float)$sub['total'];
                $grade = grade_band_for($subTotal, $bands);
            ?>
                <tr>
                    <td><strong><?= e($sub['subject_name']) ?></strong>
                        <small class="muted"><?= e($sub['code']) ?></small></td>
                    <?php foreach ($componentLabels as $comp):
                        $field = strtolower($comp);
                    ?>
                        <td style="text-align:right;"><?= e($sub[$field] ?? '—') ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:right;"><strong><?= e(rtrim(rtrim((string)$subTotal,'0'),'.')) ?></strong></td>
                    <td style="text-align:center;"><strong><?= e($grade) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?= 1 + count($componentLabels) ?>" style="text-align:right;"><strong>Total / Average</strong></td>
                    <td style="text-align:right;"><strong><?= e(rtrim(rtrim((string)$report['totals']['sum'],'0'),'.')) ?></strong></td>
                    <td style="text-align:center;"><strong><?= e(number_format($report['totals']['avg'], 1)) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top:1rem; display:flex; gap:2rem; flex-wrap:wrap;">
            <div><span class="label muted">Position:</span>
                <strong>
                    <?= $report['position'] !== null
                        ? e($report['position'] . ' of ' . $report['class_size'])
                        : '—' ?>
                </strong>
            </div>
            <div><span class="label muted">Class average:</span>
                <strong>
                    <?= $report['class_avg'] !== null
                        ? e(number_format($report['class_avg'], 1))
                        : '—' ?>
                </strong>
            </div>
        </div>

        <div class="remarks-block">
            <div class="remark">
                <div class="label muted">Class Teacher's Remark</div>
                <div><?= e($termRow['class_teacher_remark'] ?: '—') ?></div>
            </div>
            <div class="remark">
                <div class="label muted">Principal's Remark</div>
                <div><?= e($termRow['principal_remark'] ?: '—') ?></div>
            </div>
        </div>

        <?php if ($nextResume): ?>
            <div class="next-resumption">
                <strong>Next term begins:</strong> <?= e($nextResume) ?>
            </div>
        <?php endif; ?>

        <div class="signature">
            <div>Class Teacher</div>
            <div>Principal</div>
            <div>Parent / Guardian</div>
        </div>

        <p style="margin-top:1.5rem;" class="no-print">
            <button class="btn" onclick="window.print()">Print / Save as PDF</button>
        </p>
        <?php endif; ?>
    </div>
<?php endif; ?>