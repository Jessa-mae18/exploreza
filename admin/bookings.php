<?php
// Admin: review, assign guides, approve, reject and delete bookings.
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'book';
require __DIR__ . '/../includes/admin-header.php';
?>
<form method="post" onsubmit="return confirm('Permanently delete ALL rejected and cancelled bookings?')" style="margin-bottom:16px"><input type="hidden" name="act" value="purge"><button class="btn red">Delete all rejected and cancelled bookings</button></form>
<?php foreach(q("SELECT b.*,u.first_name,u.last_name,u.phone,u.birthdate,p.method,p.status ps FROM bookings b JOIN users u USING(user_id) LEFT JOIN payments p USING(booking_id) WHERE (b.reference_no LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?) ORDER BY booking_id DESC",[$sq,$sq,$sq])->fetchAll() as $b): ?>
<div class="card" style="margin-bottom:18px">
<div class="pad"><b><?= h($b['reference_no']) ?></b> · <?= h($b['first_name'].' '.$b['last_name']) ?> (<?= h($b['phone']) ?>) · age <?= $b['birthdate'] ? age_from($b['birthdate']) : '?' ?> <span class="tag <?= $b['status'] ?>"><?= $b['status'] ?></span> <span class="tag <?= $b['ps'] ?>">pay: <?= $b['ps'] ?></span> <b><?= php_($b['total_amount']) ?></b>
<table>
<tr>
<th>Package</th>
<th>Date</th>
<th>Guests</th>
<th>Guide</th>
</tr>
<?php foreach(q("SELECT i.*,t.title,t.duration_days,t.destination_id FROM booking_items i JOIN packages t USING(package_id) WHERE booking_id=?",[$b['booking_id']])->fetchAll() as $i):
 ?>
<tr>
<td><?= h($i['title']) ?></td>
<td><?= $i['travel_date'] ?></td>
<td><?= $i['participants'] ?></td>
<td><?php if($i['want_guide']): $gs=guides_for($i);$cur=null;foreach($gs as $g)if($g['guide_id']==$i['guide_id'])$cur=$g; ?>
<?= $cur?'<span class="tag approved">Assigned: '.h($cur['full_name']).'</span>':'<span class="tag pending">No guide assigned yet</span>' ?>
<?= $gs?'':'<div class="err">No guide covers this destination yet. Add one in Manage content.</div>' ?>
<div class="gchips"><?php foreach($gs as $g)echo $g['busy']?'<span class="gchip busy" title="Booked on '.h($g['busy']['reference_no']).' ('.h($g['busy']['title']).')">'.h($g['full_name']).' &middot; '.(!empty($g['busy']['same'])?'Used for another place':(!empty($g['busy']['off'])?'Day off':'Busy')).'</span>':'<span class="gchip free">'.h($g['full_name']).' &middot; Available</span>'; ?>
</div>
<form method="post" class="inline"><input type="hidden" name="act" value="assign"><input type="hidden" name="item" value="<?= $i['item_id'] ?>"><select name="guide" style="width:auto"><option value="">Choose an available guide</option><?php foreach($gs as $g)if(!$g['busy']||$g['guide_id']==$i['guide_id'])echo '<option value="'.$g['guide_id'].'"'.($g['guide_id']==$i['guide_id']?' selected':'').'>'.h($g['full_name']).' (Available)</option>'; ?></select> <button class="btn sm" style="margin:0">Assign</button> <button class="btn alt sm" style="margin:0" name="auto" value="1">Auto-assign</button></form>
<?php else: echo 'Not requested'; endif; ?></td>
</tr><?php endforeach; ?>
</table>
<?php if($b['status']=='pending'): ?><form method="post" class="inline"><input type="hidden" name="b" value="<?= $b['booking_id'] ?>"><button class="btn" name="act" value="approve">Approve</button> <button class="btn red" name="act" value="reject">Reject</button></form><?php elseif($b['status']=='approved'): ?><form method="post"><input type="hidden" name="b" value="<?= $b['booking_id'] ?>"><button class="btn alt" name="act" value="done">Mark tour completed</button></form><?php endif; ?>
<form method="post" class="inline" onsubmit="return confirm('Permanently delete booking <?= h($b['reference_no']) ?>? This cannot be undone.')" style="float:right;margin-top:-40px"><input type="hidden" name="act" value="delete"><input type="hidden" name="b" value="<?= $b['booking_id'] ?>"><button class="btn red sm" style="margin:0">Delete</button></form>
</div>
</div>
<?php endforeach; ?>
<?php require __DIR__.'/../includes/admin-footer.php'; ?>
