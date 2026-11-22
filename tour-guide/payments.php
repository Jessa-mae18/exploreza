<?php
// Tour guide: pay for each assigned tour.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
$t = 'pay';
require __DIR__ . '/../includes/guide-header.php';
$rows = q(
    'SELECT gp.*,i.travel_date,t.title,t.duration_days,b.reference_no FROM guide_payments gp JOIN booking_items i ON i.item_id=gp.item_id JOIN packages t ON t.package_id=i.package_id JOIN bookings b ON b.booking_id=i.booking_id WHERE gp.guide_id=? ORDER BY i.travel_date DESC',
    [$gid]
)->fetchAll();
$paid = 0;
$owed = 0;
foreach ($rows as $x) {
    if ($x['status'] == 'paid') {
        $paid += $x['amount'];
    } else {
        $owed += $x['amount'];
    }
}
?>
<div class="adm-grid4 mt" style="margin-top:0">
<div class="adm-card adm-stat"><small>MY RATE</small><b><?= php_($guide['day_rate']) ?></b><span class="muted">Per tour day</span></div>
<div class="adm-card adm-stat"><small>TO RECEIVE</small><b><?= php_($owed) ?></b><span class="muted">Waiting for payment</span></div>
<div class="adm-card adm-stat"><small>PAID</small><b><?= php_($paid) ?></b><span class="muted">Already released</span></div>
<div class="adm-card adm-stat"><small>TOURS</small><b><?= count($rows) ?></b><span class="muted">With a fee</span></div>
</div>
<div class="adm-card mt">
<p class="muted">A fee appears here after the admin approves the booking.</p>
<table>
<tr><th>Booking</th><th>Tour</th><th>Date</th><th>Days</th><th>Fee</th><th>Status</th><th>Paid on</th></tr>
<?php foreach ($rows as $x) {
    echo '<tr><td>' . h($x['reference_no']) . '</td><td>' . h($x['title']) . '</td><td>' . h($x['travel_date']) . '</td><td>' . $x['duration_days'] . '</td><td>' . php_($x['amount']) . '</td><td><span class="tag ' . ($x['status'] == 'paid' ? 'approved' : 'pending') . '">' . $x['status'] . '</span></td><td>' . h($x['paid_at'] ?: '-') . '</td></tr>';
}
if (!$rows) {
    echo '<tr><td colspan="7" class="muted">No fees yet.</td></tr>';
} ?>
</table>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
