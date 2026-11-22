<?php
// Admin: verify payments and view uploaded IDs.
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'pay';
require __DIR__ . '/../includes/admin-header.php';
?>
<table>
<tr>
<th>Ref</th>
<th>Method</th>
<th>Reference no.</th>
<th>ID</th>
<th>Amount</th>
<th>Status</th>
<th></th>
</tr><?php foreach(q("SELECT p.*,b.reference_no rn FROM payments p JOIN bookings b USING(booking_id) ORDER BY payment_id DESC")->fetchAll() as $p): ?>
<tr>
<td><?= h($p['rn']) ?></td>
<td><?= $p['method'] ?></td>
<td><?= h($p['reference_no']) ?></td>
<td><?= $p['id_file']?'<a target="_blank" href="'.BASE_URL.'assets/uploads/payment-ids/'.h($p['id_file']).'">View ID</a>':'Not required (cash)' ?></td>
<td><?= php_($p['amount']) ?></td>
<td><span class="tag <?= $p['status'] ?>"><?= $p['status'] ?></span></td>
<td><form method="post" class="inline"><input type="hidden" name="act" value="pay"><input type="hidden" name="b" value="<?= $p['booking_id'] ?>"><button class="btn sm" style="margin:0" name="v" value="verified">Verify</button> <button class="btn red sm" style="margin:0" name="v" value="rejected">Reject</button></form></td>
</tr><?php endforeach; ?>
</table>

<?php require __DIR__.'/../includes/admin-footer.php'; ?>
