<?php
// Customer: tour guide profile with credentials and reviews.
require __DIR__ . '/../includes/auth-check.php';
$g = q('SELECT * FROM guides WHERE guide_id=?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$g) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit();
}
[$avg, $cnt] = guide_rating($g['guide_id']);
$places = q(
    'SELECT GROUP_CONCAT(d.name SEPARATOR ", ") FROM guide_destinations gd JOIN destinations d USING(destination_id) WHERE gd.guide_id=?',
    [$g['guide_id']]
)->fetchColumn();
$done = q(
    "SELECT COUNT(*) FROM booking_items i JOIN bookings b USING(booking_id) WHERE i.guide_id=? AND b.status='completed'",
    [$g['guide_id']]
)->fetchColumn();
head($g['full_name']);
?>
<div class="box wide">
<h2><?= h($g['full_name']) ?></h2>
<p><?= $avg ? stars_html($avg) . ' <b>' . $avg . ' / 5</b> <span class="muted">(' . $cnt . ' rating' . ($cnt == 1 ? '' : 's') . ')</span>' : '<span class="muted">No ratings yet</span>' ?></p>
<table>
<tr><th>Credentials</th><th></th></tr>
<tr><td>Specialization</td><td><?= h($g['specialization']) ?></td></tr>
<tr><td>Languages spoken</td><td><?= h($g['languages']) ?></td></tr>
<tr><td>Places covered</td><td><?= h($places ?: 'Not set') ?></td></tr>
<tr><td>Tours completed</td><td><?= (int) $done ?></td></tr>
</table>
<h3 style="margin-top:24px">Reviews from travelers</h3>
<?php
$rv = q('SELECT r.*,u.first_name FROM guide_ratings r JOIN users u ON u.user_id=r.user_id WHERE r.guide_id=? ORDER BY r.rating_id DESC LIMIT 20', [$g['guide_id']])->fetchAll();
foreach ($rv as $x) {
    echo '<p style="margin:10px 0;padding:8px 10px;background:#f4f7fa">' . stars_html($x['stars']) . ' <b>' . h($x['first_name']) . '</b><br>' . h($x['comment'] ?: 'No comment') . '</p>';
}
if (!$rv) {
    echo '<p class="muted">No reviews yet. Travelers can rate their guide after a completed tour.</p>';
}
?>
<p><a href="<?= BASE_URL ?>index.php#guides">&larr; Back to all guides</a></p>
</div>
<?php foot(); ?>
