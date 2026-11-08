<?php
// /public/app/public/pages/_error.php
?>
<div class="card error-card">
    <h2>⚠ Something went wrong</h2>
    <p><?= e($error) ?></p>
    <p><a href="javascript:history.back()" class="btn">← Go back</a></p>
</div>