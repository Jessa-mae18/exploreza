<?php
// Tour guide: bookings assigned to this guide.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
$t = 'book';
require __DIR__ . '/../includes/guide-header.php';
$rows = q(
    "SELECT i.*,t.title,t.duration_days,d.name dest,b.reference_no,b.status,u.first_name,u.last_name,u.phone FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id JOIN destinations d ON d.destination_id=t.destination_id JOIN users u ON u.user_id=b.user_id WHERE i.guide_id=? AND b.status IN('pending','approved','completed') ORDER BY i.travel_date DESC",
    [$gid]
)->fetchAll();
?>
<div class="adm-card">
<table>
<tr><th>Booking</th><th>Traveler</th><th>Tour and place</th><th>Dates</th><th>Guests</th><th>Special requests</th><th>Status</th></tr>
<?php foreach ($rows as $x) {
    $end = date('Y-m-d', strtotime($x['travel_date'] . ' +' . max(0, $x['duration_days'] - 1) . ' day'));
    echo '<tr><td>' . h($x['reference_no']) . '</td>';
    echo '<td>' . h($x['first_name'] . ' ' . $x['last_name']) . '<br><span class="muted">' . h($x['phone']) . '</span></td>';
    echo '<td>' . h($x['title']) . '<br><span class="muted">' . h($x['dest']) . '</span></td>';
    echo '<td>' . h($x['travel_date']) . ($end != $x['travel_date'] ? ' to ' . h($end) : '') . '</td>';
    echo '<td>' . $x['participants'] . '</td><td>' . h($x['special_requests'] ?: '-') . '</td>';
    echo '<td><span class="tag ' . $x['status'] . '">' . $x['status'] . '</span></td></tr>';
}
if (!$rows) {
    echo '<tr><td colspan="7" class="muted">No bookings assigned to you yet.</td></tr>';
} ?>
</table>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
