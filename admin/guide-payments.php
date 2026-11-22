<?php
// Admin: pay tour guides for their assigned tours.
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'gpay';
require __DIR__ . '/../includes/admin-header.php';
$rows = q(
    'SELECT gp.*,g.full_name,i.travel_date,t.title,b.reference_no FROM guide_payments gp JOIN guides g ON g.guide_id=gp.guide_id JOIN booking_items i ON i.item_id=gp.item_id JOIN packages t ON t.package_id=i.package_id JOIN bookings b ON b.booking_id=i.booking_id ORDER BY gp.status, i.travel_date'
)->fetchAll();
$owed = 0;
$paid = 0;
foreach ($rows as $x) {
    if ($x['status'] == 'paid') {
        $paid += $x['amount'];
    } else {
        $owed += $x['amount'];
    }
}
?>
<div class="adm-grid4 mt" style="margin-top:0">
<div class="adm-card adm-stat"><small>TO PAY GUIDES</small><b><?= php_($owed) ?></b><span class="muted">Pending</span></div>
<div class="adm-card adm-stat"><small>PAID TO GUIDES</small><b><?= php_($paid) ?></b><span class="muted">Released</span></div>
</div>
<div class="adm-card mt">
<p class="muted">A fee is created when you approve a booking that has a guide. Fee = the guide's day rate x the tour's days.</p>
<table>
<tr><th>Guide</th><th>Booking</th><th>Tour</th><th>Date</th><th>Fee</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $x) {
    echo '<tr><td>' . h($x['full_name']) . '</td><td>' . h($x['reference_no']) . '</td><td>' . h($x['title']) . '</td><td>' . h($x['travel_date']) . '</td><td>' . php_($x['amount']) . '</td><td><span class="tag ' . ($x['status'] == 'paid' ? 'approved' : 'pending') . '">' . $x['status'] . '</span></td><td>';
    if ($x['status'] == 'pending') {
        echo '<form method="post" class="inline"><input type="hidden" name="act" value="guidepaid"><input type="hidden" name="pid" value="' . $x['payment_id'] . '"><button class="btn sm" style="margin:0">Mark paid</button></form>';
    } else {
        echo h($x['paid_at']);
    }
    echo '</td></tr>';
}
if (!$rows) {
    echo '<tr><td colspan="7" class="muted">No guide fees yet.</td></tr>';
} ?>
</table>
</div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
