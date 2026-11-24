<?php
// Admin: review a booking, verify its payment, assign guides, then approve (all in one place).
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'book';
require __DIR__ . '/../includes/admin-header.php';
$rows = q(
    'SELECT b.*,u.first_name,u.last_name,u.phone,u.birthdate,p.method,p.status ps,p.reference_no pref,p.id_file,p.amount pamount FROM bookings b JOIN users u USING(user_id) LEFT JOIN payments p USING(booking_id) WHERE (b.reference_no LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?) ORDER BY b.booking_id DESC',
    [$sq, $sq, $sq]
)->fetchAll();
?>
<form method="post" onsubmit="return confirm('Permanently delete ALL rejected and cancelled bookings?')" style="margin-bottom:16px">
<input type="hidden" name="act" value="purge">
<button class="btn red">Delete all rejected and cancelled bookings</button>
</form>
<?php foreach ($rows as $b):
    $age = $b['birthdate'] ? age_from($b['birthdate']) : null;
    $payOk = $b['ps'] == 'verified';
    $items = q(
        'SELECT i.*,t.title,t.duration_days,t.destination_id FROM booking_items i JOIN packages t USING(package_id) WHERE i.booking_id=?',
        [$b['booking_id']]
    )->fetchAll();
?>
<div class="card" style="margin-bottom:22px">
<div class="pad">
<div>
<b><?= h($b['reference_no']) ?></b> &middot; <?= h($b['first_name'] . ' ' . $b['last_name']) ?> (<?= h($b['phone']) ?>) &middot; age <?= $age === null ? '?' : $age ?>
<span class="tag <?= $b['status'] ?>"><?= $b['status'] ?></span>
<b><?= php_($b['total_amount']) ?></b>
<?php if ($age !== null && $age < 18): ?><span class="tag rejected">UNDER 18 - do not approve</span><?php endif; ?>
</div>

<h3 style="margin:16px 0 6px">1. Tours</h3>
<table>
<tr><th>Package</th><th>Date</th><th>Guests</th><th>Guide fee</th><th>Guide</th></tr>
<?php foreach ($items as $i): ?>
<tr>
<td><?= h($i['title']) ?></td>
<td><?= h($i['travel_date']) ?></td>
<td><?= (int) $i['participants'] ?></td>
<td><?= $i['guide_fee'] > 0 ? php_($i['guide_fee']) : '-' ?></td>
<td><?php if ($i['want_guide']):
    $gs = guides_for($i);
    $cur = null;
    foreach ($gs as $g) {
        if ($g['guide_id'] == $i['guide_id']) {
            $cur = $g;
        }
    }
    echo $cur ? '<span class="tag approved">Assigned: ' . h($cur['full_name']) . '</span>' : '<span class="tag pending">No guide assigned yet</span>';
    echo $gs ? '' : '<div class="err">No guide covers this destination yet. Add one in Manage content.</div>';
    echo '<div class="gchips">';
    foreach ($gs as $g) {
        if ($g['busy']) {
            $why = !empty($g['busy']['same']) ? 'Used for another place' : (!empty($g['busy']['off']) ? 'Day off' : 'Busy');
            echo '<span class="gchip busy" title="Already on ' . h($g['busy']['reference_no']) . ' (' . h($g['busy']['title']) . ')">' . h($g['full_name']) . ' &middot; ' . $why . '</span>';
        } else {
            echo '<span class="gchip free">' . h($g['full_name']) . ' &middot; Available</span>';
        }
    }
    echo '</div>';
?>
<form method="post" class="inline">
<input type="hidden" name="act" value="assign">
<input type="hidden" name="item" value="<?= (int) $i['item_id'] ?>">
<select name="guide" style="width:auto">
<option value="">Choose an available guide</option>
<?php foreach ($gs as $g) {
    if (!$g['busy'] || $g['guide_id'] == $i['guide_id']) {
        echo '<option value="' . $g['guide_id'] . '"' . ($g['guide_id'] == $i['guide_id'] ? ' selected' : '') . '>' . h($g['full_name']) . ' (Available)</option>';
    }
} ?>
</select>
<button class="btn sm" style="margin:0">Assign</button>
<button class="btn alt sm" style="margin:0" name="auto" value="1">Auto-assign</button>
</form>
<?php else:
    echo 'Not requested';
endif; ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3 style="margin:18px 0 6px">2. Payment and ID</h3>
<div style="background:#f8fafc;border:1px solid #d9e0e3;padding:12px 14px">
<?php if (!$b['method']): ?>
<span class="muted">No payment record for this booking.</span>
<?php else: ?>
<b><?= h(strtoupper($b['method'])) ?></b> &middot; <?= php_($b['pamount']) ?>
<span class="tag <?= $b['ps'] ?>">payment <?= $b['ps'] ?></span><br>
<span class="muted">Reference no.:</span> <?= h($b['pref'] ?: '-') ?> &nbsp;&middot;&nbsp;
<span class="muted">Valid ID:</span>
<?= $b['id_file'] ? '<a target="_blank" href="' . BASE_URL . 'assets/uploads/payment-ids/' . h($b['id_file']) . '">View uploaded ID</a>' : 'Not required (cash)' ?>
<br><span class="muted">Check that the name and age on the ID match the traveler (age <?= $age === null ? '?' : $age ?>).</span>
<?php if ($b['status'] == 'pending' && !$payOk): ?>
<form method="post" class="inline" style="display:block;margin-top:10px">
<input type="hidden" name="act" value="pay">
<input type="hidden" name="b" value="<?= (int) $b['booking_id'] ?>">
<button class="btn sm" name="v" value="verified" style="margin:0"><?= $b['method'] == 'cash' ? 'Confirm cash payment' : 'Verify payment' ?></button>
<button class="btn red sm" name="v" value="rejected" style="margin:0">Reject payment</button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>

<h3 style="margin:18px 0 6px">3. Decision</h3>
<?php if ($b['status'] == 'pending'): ?>
<form method="post" class="inline">
<input type="hidden" name="b" value="<?= (int) $b['booking_id'] ?>">
<button class="btn" name="act" value="approve" <?= $payOk ? '' : 'disabled title="Verify the payment first"' ?>>Approve booking</button>
<button class="btn red" name="act" value="reject">Reject booking</button>
<?php if (!$payOk): ?><span class="muted">Verify the payment first, then you can approve.</span><?php endif; ?>
</form>
<?php elseif ($b['status'] == 'approved'): ?>
<form method="post" class="inline">
<input type="hidden" name="b" value="<?= (int) $b['booking_id'] ?>">
<button class="btn alt" name="act" value="done">Mark tour completed</button>
</form>
<?php else: ?>
<span class="muted">This booking is <?= h($b['status']) ?>.</span>
<?php endif; ?>
<form method="post" class="inline" onsubmit="return confirm('Permanently delete booking <?= h($b['reference_no']) ?>? This cannot be undone.')" style="float:right">
<input type="hidden" name="act" value="delete">
<input type="hidden" name="b" value="<?= (int) $b['booking_id'] ?>">
<button class="btn red sm" style="margin:0">Delete</button>
</form>
</div>
</div>
<?php endforeach;
if (!$rows) {
    echo '<p class="muted">No bookings found.</p>';
} ?>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
