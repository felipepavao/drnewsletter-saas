<?php
$messages = Flash::pull();
if (!$messages) return;
?>
<div class="flash-stack">
    <?php foreach ($messages as $m): ?>
        <div class="flash flash--<?= e($m['type']) ?>" role="status">
            <?= e($m['message']) ?>
        </div>
    <?php endforeach; ?>
</div>
