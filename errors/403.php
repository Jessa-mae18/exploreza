<?php
// 403 Forbidden page.
require_once __DIR__ . '/../config/config.php';
http_response_code(403);
head('Forbidden');
?>
<div class="box">
<h2>403 Forbidden</h2>
<p>You do not have access to this page.</p><a class="btn" href="<?= BASE_URL ?>index.php">Back to home</a>
</div>
<?php foot(); ?>
