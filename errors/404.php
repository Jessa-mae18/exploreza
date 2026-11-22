<?php
// 404 Not Found page.
require_once __DIR__ . '/../config/config.php';
http_response_code(404);
head('Page not found');
?>
<div class="box">
<h2>404 Page not found</h2>
<p>We could not find that page.</p><a class="btn" href="<?= BASE_URL ?>index.php">Back to home</a>
</div>
<?php foot(); ?>
