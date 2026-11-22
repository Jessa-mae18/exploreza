<?php
// Tour guide: places the guide covers and places assigned in bookings.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
$t = 'places';
require __DIR__ . '/../includes/guide-header.php';
$covered = q(
    'SELECT d.* FROM guide_destinations gd JOIN destinations d USING(destination_id) WHERE gd.guide_id=? ORDER BY d.name',
    [$gid]
)->fetchAll();
$assigned = q(
    "SELECT d.name,COUNT(*) n,MIN(i.travel_date) nxt FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id JOIN destinations d ON d.destination_id=t.destination_id WHERE i.guide_id=? AND b.status IN('pending','approved') AND i.travel_date>=CURDATE() GROUP BY d.destination_id,d.name ORDER BY nxt",
    [$gid]
)->fetchAll();
?>
<h3>Places assigned to me</h3>
<div class="adm-card" style="margin-bottom:22px">
<table>
<tr><th>Place</th><th>Upcoming tours</th><th>Next date</th></tr>
<?php foreach ($assigned as $x) {
    echo '<tr><td>' . h($x['name']) . '</td><td>' . $x['n'] . '</td><td>' . h($x['nxt']) . '</td></tr>';
}
if (!$assigned) {
    echo '<tr><td colspan="3" class="muted">No upcoming tours yet.</td></tr>';
} ?>
</table>
</div>
<h3>Destinations I cover</h3>
<div class="grid">
<?php foreach ($covered as $d) {
    echo '<div class="card"><div class="cover" style="' . cover_bg($d) . '">' . h($d['name']) . '</div><div class="pad"><span class="tag">' . h($d['region']) . '</span><p>' . h($d['description']) . '</p></div></div>';
}
if (!$covered) {
    echo '<p class="muted">The admin has not set your destinations yet.</p>';
} ?>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
