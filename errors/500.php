<?php
// 500 Server Error page.
require_once __DIR__ . '/../config/config.php';
http_response_code(500);
head('Server error');
?>
<div class="box">
<h2>500 Server error</h2>
<p>Something went wrong on our side.</p><a class="btn" href="<?= BASE_URL ?>index.php">Back to home</a>
</div>
<?php foot(); ?>
