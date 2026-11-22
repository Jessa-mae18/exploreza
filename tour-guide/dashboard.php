<?php
// Tour guide: dashboard with upcoming tours, earnings and notifications.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
$t = 'dash';
require __DIR__ . '/../includes/guide-header.php';
$n = fn($sql, $p = []) => q($sql, $p)->fetchColumn();
$upcoming = $n(
    "SELECT COUNT(*) FROM booking_items i JOIN bookings b USING(booking_id) WHERE i.guide_id=? AND b.status='approved' AND i.travel_date>=CURDATE()",
    [$gid]
);
$waiting = $n(
    "SELECT COUNT(*) FROM booking_items i JOIN bookings b USING(booking_id) WHERE i.guide_id=? AND b.status='pending'",
    [$gid]
);
$places = $n('SELECT COUNT(*) FROM guide_destinations WHERE guide_id=?', [$gid]);
$paid = $n("SELECT COALESCE(SUM(amount),0) FROM guide_payments WHERE guide_id=? AND status='paid'", [$gid]);
$owed = $n("SELECT COALESCE(SUM(amount),0) FROM guide_payments WHERE guide_id=? AND status='pending'", [$gid]);
$notes = q('SELECT * FROM notifications WHERE user_id=? ORDER BY notif_id DESC LIMIT 8', [$me['id']])->fetchAll();
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$me['id']]);
$next = q(
    "SELECT i.*,t.title,t.duration_days,d.name dest,b.reference_no,b.status FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id JOIN destinations d ON d.destination_id=t.destination_id WHERE i.guide_id=? AND b.status IN('pending','approved') AND i.travel_date>=CURDATE() ORDER BY i.travel_date LIMIT 5",
    [$gid]
)->fetchAll();
?>
<div class="adm-grid4 mt" style="margin-top:0">
<div class="adm-card adm-stat"><small>UPCOMING TOURS</small><b><?= $upcoming ?></b><span class="muted">Approved bookings</span></div>
<div class="adm-card adm-stat"><small>AWAITING APPROVAL</small><b><?= $waiting ?></b><span class="muted">Assigned, admin still reviewing</span></div>
<div class="adm-card adm-stat"><small>PAYMENT TO RECEIVE</small><b><?= php_($owed) ?></b><span class="muted">Paid so far: <?= php_($paid) ?></span></div>
<div class="adm-card adm-stat"><small>PLACES I COVER</small><b><?= $places ?></b><span class="muted">Destinations</span></div>
</div>
<div class="adm-row2 mt">
<div class="adm-card">
<h3>Notifications</h3>
<?php foreach ($notes as $x) {
    echo '<p style="margin:8px 0;padding:8px 10px;background:' . ($x['is_read'] ? '#f4f7fa' : '#fff4d6') . '">' . h($x['message']) . '<br><small class="muted">' . h($x['created_at']) . '</small></p>';
}
if (!$notes) {
    echo '<p class="muted">No notifications yet. You will be told here when the admin assigns you to a tour.</p>';
} ?>
</div>
<div class="adm-card">
<h3>Next tours</h3>
<table>
<tr><th>Date</th><th>Tour</th><th>Place</th><th>Status</th></tr>
<?php foreach ($next as $x) {
    echo '<tr><td>' . h($x['travel_date']) . '</td><td>' . h($x['title']) . '</td><td>' . h($x['dest']) . '</td><td><span class="tag ' . $x['status'] . '">' . $x['status'] . '</span></td></tr>';
}
if (!$next) {
    echo '<tr><td colspan="4" class="muted">No upcoming tours yet.</td></tr>';
} ?>
</table>
</div>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
