<?php
// /public/app/public/pages/results_anomalies.php
$pageTitle = 'Grade anomalies';

$sessions = fetch_all("SELECT id, name FROM sessions ORDER BY name DESC");
$sessionId = (int) ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$terms = $sessionId
    ? fetch_all("SELECT id, name FROM terms WHERE session_id = ? ORDER BY id", [$sessionId])
    : [];
$termId = isset($_GET['term_id']) && $_GET['term_id'] !== ''
    ? (int) $_GET['term_id'] : null;

// Handle resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['resolve_id'])) {
    try {
        anomaly_resolve((int) $_POST['resolve_id']);
        flash_set('success', 'Anomaly marked resolved.');
    } catch (AppException $e) {
        flash_set('error', $e->getMessage());
    }
    $qs = http_build_query([
        'r' => 'results/anomalies',
        'session_id' => $sessionId,
    ] + ($termId !== null ? ['term_id' => $termId] : []));
    redirect('/?' . $qs);
}

$rows = $sessionId ? anomalies_list($sessionId, $termId) : [];
?>
<h1>Grade anomalies</h1>
<p class="muted">Students with a fully empty grade entry — likely inactive or withdrawn. Resolve each after investigating.</p>

<form method="get" class="card" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:end;">
    <input type="hidden" name="r" value="results/anomalies">
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
            <option value="">All terms</option>
            <?php foreach ($terms as $t): ?>
                <option value="<?= e($t['id']) ?>" <?= $termId === (int)$t['id'] ? 'selected' : '' ?>>
                    <?= e($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <?php if (!$rows): ?>
        <p class="muted">No anomalies. 🎉</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Student</th><th>Class</th><th>Subject</th><th>Term</th><th>Reason</th><th>Flagged</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
            <tr>
                <td><?= e($a['full_name']) ?><br><small class="muted"><?= e($a['admission_no']) ?></small></td>
                <td><?= e($a['class_label']) ?></td>
                <td><?= e($a['subject'] ?? '—') ?></td>
                <td><?= e($a['term']) ?></td>
                <td><?= e($a['kind']) ?><?= $a['detail'] ? ' <small class="muted">· ' . e($a['detail']) . '</small>' : '' ?></td>
                <td><?= e($a['flagged_at']) ?></td>
                <td>
                    <form method="post" style="display:inline">
    <?= csrf_field() ?>
    <input type="hidden" name="resolve_id" value="<?= e($a['id']) ?>">
                        <button class="btn small" type="submit">Resolve</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>