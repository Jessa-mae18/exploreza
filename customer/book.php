<?php
// Package details, booking form, cart review and payment.
require __DIR__ . '/../includes/auth-check.php';
$u = me();
if (!$u['verified']) {
    header('Location: ' . BASE_URL . 'auth/verify.php');
    exit();
}
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$err = [];
// Age check: travelers must be 18 or older (admins are exempt)
$ageBlock = null;
if ($u['role'] == 'user') {
    if (($_POST['act'] ?? '') == 'dob') {
        $d = $_POST['birthdate'] ?? '';
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)) && age_from($d) >= 18) {
            q('UPDATE users SET birthdate=? WHERE user_id=? AND birthdate IS NULL', [$d, $u['id']]);
        } else {
            $err[] = 'You must be at least 18 years old to book a tour.';
        }
    }
    $dob = q('SELECT birthdate FROM users WHERE user_id=?', [$u['id']])->fetchColumn();
    if (!$dob) {
        $ageBlock = 'ask';
    } elseif (age_from($dob) < 18) {
        $ageBlock = 'minor';
    }
}
if (isset($_GET['rm'])) {
    unset($_SESSION['cart'][(int) $_GET['rm']]);
    header('Location: ' . BASE_URL . 'customer/checkout.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add']) && !$ageBlock) {
    $p = q('SELECT * FROM packages WHERE package_id=?', [$_POST['pkg']])->fetch();
    $n = (int) $_POST['pax'];
    if (!$p || $n < 1 || $n > $p['max_participants']) {
        $err[] = 'Choose 1 to ' . ($p['max_participants'] ?? 0) . ' participants.';
    }
    if (!$_POST['date'] || $_POST['date'] < date('Y-m-d')) {
        $err[] = 'Pick a future travel date.';
    }
    if (!$err) {
        $inc = 0;
        foreach ($_SESSION['cart'] as $x) {
            if ($x['pkg'] == $p['package_id'] && $x['date'] == $_POST['date']) {
                $inc += $x['pax'];
            }
        }
        $left = $p['max_participants'] - booked($p['package_id'], $_POST['date']) - $inc;
        if ($left <= 0) {
            $err[] = 'This package is fully booked on that date. See the available dates.';
        } elseif ($n > $left) {
            $err[] = "Only $left slot(s) left on that date.";
        }
    }
    if (!$err) {
        $left =
            $p['max_participants'] -
            booked($p['package_id'], $_POST['date']) -
            incart($p['package_id'], $_POST['date']);
        if ($n > $left) {
            $err[] =
                $left <= 0
                    ? 'That date is fully booked. Please choose another date.'
                    : "Only $left slot(s) left on that date.";
        }
    }
    if (!$err) {
        $_SESSION['cart'][] = [
            'pkg' => $p['package_id'],
            'date' => $_POST['date'],
            'pax' => $n,
            'guide' => isset($_POST['guide']) ? 1 : 0,
            'note' => substr($_POST['note'], 0, 250)
        ];
        header('Location: ' . BASE_URL . 'customer/checkout.php');
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay']) && !$ageBlock) {
    $m = $_POST['method'];
    if ($u['role'] == 'user' && empty($_POST['over18'])) {
        $err[] = 'Please confirm that you are 18 years old or older.';
    }
    $cart = $_SESSION['cart'];
    $file = null;
    if (!$cart) {
        $err[] = 'Your cart is empty.';
    }
    if (!in_array($m, ['cash', 'gcash', 'bank', 'card'])) {
        $err[] = 'Choose a payment method.';
    }
    if ($m != 'cash') {
        $f = $_FILES['idf'] ?? null;
        if (
            !$f ||
            $f['error'] ||
            !in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'pdf']) ||
            $f['size'] > 5e6
        ) {
            $err[] = 'Upload a valid ID (JPG, PNG or PDF, max 5 MB) for online payments.';
        }
        if (!trim($_POST['ref'])) {
            $err[] = 'Enter the payment reference number.';
        }
    }
    if (!$err) {
        $use = [];
        foreach ($cart as $c) {
            $k = $c['pkg'] . '|' . $c['date'];
            $use[$k] = ($use[$k] ?? 0) + $c['pax'];
        }
        foreach ($use as $k => $n2) {
            [$pk, $dt] = explode('|', $k);
            $mx = q('SELECT max_participants FROM packages WHERE package_id=?', [$pk])->fetchColumn();
            if (booked($pk, $dt) + $n2 > $mx) {
                $err[] = "A tour on $dt just became full. Remove it or choose another date.";
            }
        }
    }
    foreach ($cart as $c) {
        $p = q('SELECT max_participants,title FROM packages WHERE package_id=?', [$c['pkg']])->fetch();
        $sum = 0;
        foreach ($cart as $d) {
            if ($d['pkg'] == $c['pkg'] && $d['date'] == $c['date']) {
                $sum += $d['pax'];
            }
        }
        if (booked($c['pkg'], $c['date']) + $sum > $p['max_participants']) {
            $err[] =
                $p['title'] . ' on ' . $c['date'] . ' is no longer available. Remove it or change the date.';
            break;
        }
    }
    if (!$err) {
        $total = 0;
        $rows = [];
        foreach ($cart as $c) {
            $p = q('SELECT price,duration_days FROM packages WHERE package_id=?', [$c['pkg']])->fetch();
            $gf = $c['guide'] ? GUIDE_FEE_PER_DAY * max(1, (int) $p['duration_days']) : 0;
            $total += $p['price'] * $c['pax'] + $gf;
            $rows[] = [$c, $p['price'], $gf];
        }
        if ($m != 'cash') {
            $file = bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $file);
        }
        $ref = 'PT' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $pdo->beginTransaction();
        q('INSERT INTO bookings(reference_no,user_id,total_amount) VALUES(?,?,?)', [$ref, $u['id'], $total]);
        $bid = $pdo->lastInsertId();
        foreach ($rows as [$c, $pr, $gf]) {
            q(
                'INSERT INTO booking_items(booking_id,package_id,travel_date,participants,unit_price,want_guide,guide_fee,special_requests) VALUES(?,?,?,?,?,?,?,?)',
                [$bid, $c['pkg'], $c['date'], $c['pax'], $pr, $c['guide'], $gf, $c['note']]
            );
        }
        q('INSERT INTO payments(booking_id,method,reference_no,id_file,amount) VALUES(?,?,?,?,?)', [
            $bid,
            $m,
            $_POST['ref'] ?? null,
            $file,
            $total
        ]);
        $pdo->commit();
        $_SESSION['cart'] = [];
        notify($u['id'], "Booking $ref received. Waiting for admin review.");
        header('Location: ' . BASE_URL . "customer/my-trips.php?ref=$ref");
        exit();
    }
}
head('Booking');
if ($ageBlock) {
    echo '<div class="box"><h2>Age check</h2>';
    foreach ($err as $e) {
        echo '<div class="err">' . h($e) . '</div>';
    }
    if ($ageBlock == 'minor') {
        echo '<p>Sorry, only travelers aged 18 and above can book tours. Minors are not allowed to make a booking.</p>';
    } else {
        echo '<p>Please confirm your birthdate before booking. You must be 18 or older.</p><form method="post"><input type="hidden" name="act" value="dob"><label>Birthdate</label><input type="date" name="birthdate" max="' . date('Y-m-d', strtotime('-18 years')) . '" required><p><button class="btn">Confirm</button></p></form>';
    }
    echo '</div>';
    foot();
    exit();
}
if(isset($_GET['pkg'])): $p=q("SELECT p.*,d.name dn,d.region reg,d.latitude lat,d.longitude lng FROM packages p JOIN destinations d USING(destination_id) WHERE package_id=?",[$_GET['pkg']])->fetch(); if(!$p)exit('Package not found'); ?>
<div class="box"><?php foreach($err as $e)echo '<div class="err">'.h($e).'</div>'; ?>
<h2><?= h($p['title']) ?></h2>
<p class="muted"><?= h($p['dn']) ?> · <?= php_($p['price']) ?> per person · <?= $p['duration_days'] ?> day(s)</p>
<p><b>Included:</b> <?= h($p['inclusions']) ?><br><b>Not included:</b> <?= h($p['exclusions']) ?></p>
<h3>Where you will tour and stay</h3>
<?php render_stay_map(map_point($p['dn'], $p['reg'], $p['lat'], $p['lng']), 'pkgmap'); ?>
<p><b>Upcoming availability</b> (<a href="availability.php?pkg=<?= $p['package_id'] ?>">see 30 days</a>)</p>
<div class="slots"><?php for($i=1;$i<=14;$i++){$dt=date('Y-m-d',strtotime("+$i day"));$left=$p['max_participants']-booked($p['package_id'],$dt);echo $left<=0?'<span class="slot full">'.date('D j M',strtotime($dt)).'<br>FULL</span>':'<a class="slot '.($left<=3?'low':'open').'" href="book.php?pkg='.$p['package_id'].'&date='.$dt.'">'.date('D j M',strtotime($dt)).'<br>'.$left.' left</a>';} ?>
</div>
<form method="post"><input type="hidden" name="pkg" value="<?= $p['package_id'] ?>"><label>Travel date</label><input type="date" name="date" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" value="<?= h($_GET['date']??'') ?>" required>
<p><a href="availability.php?pkg=<?= $p['package_id'] ?>">See available dates for this package</a></p><label>Participants (max <?= $p['max_participants'] ?>)</label><input type="number" name="pax" min="1" max="<?= $p['max_participants'] ?>" value="1" required>
<label><input type="checkbox" name="guide" style="width:auto"> Add a tour guide: +<?= php_(GUIDE_FEE_PER_DAY) ?> per day = <?= php_(GUIDE_FEE_PER_DAY * max(1, (int) $p['duration_days'])) ?> for this tour (optional, assigned by the admin)</label><label>Special requests</label><textarea name="note" rows="2"></textarea>
<p><button class="btn" name="add">Continue to confirmation</button></p></form>
</div>
<?php else: $cart=$_SESSION['cart']; $tot=0; ?>
<div class="box wide"><?php foreach($err as $e)echo '<div class="err">'.h($e).'</div>'; ?>
<h2>Review and pay</h2>
<?php if(!$cart): echo '<p>Your cart is empty. <a href="'.BASE_URL.'index.php#packages">Browse packages</a></p>'; else: ?>
<table>
<tr>
<th>Package</th>
<th>Date</th>
<th>Guests</th>
<th>Guide</th>
<th>Subtotal</th>
<th></th>
</tr>
<?php foreach($cart as $i=>$c){$p=q("SELECT title,price,duration_days FROM packages WHERE package_id=?",[$c['pkg']])->fetch();$gf=$c['guide']?GUIDE_FEE_PER_DAY*max(1,(int)$p['duration_days']):0;$s=$p['price']*$c['pax']+$gf;$tot+=$s; echo '<tr><td>'.h($p['title']).'</td><td>'.h($c['date']).'</td><td>'.$c['pax'].'</td><td>'.($c['guide']?'Requested (+'.php_($gf).')':'No').'</td><td>'.php_($s).'</td><td><a href="book.php?rm='.$i.'">Remove</a></td></tr>';} ?>
<tr>
<th colspan="4">Total</th>
<th colspan="2"><?= php_($tot) ?></th>
</tr>
</table>
<p><a href="<?= BASE_URL ?>index.php#packages">+ Add another package</a></p>
<form method="post" enctype="multipart/form-data"><label>Payment method</label><select name="method" id="m" onchange="var o=this.value!='cash';document.getElementById('on').style.display=o?'block':'none';document.getElementById('idf').required=o;document.getElementsByName('ref')[0].required=o"><option value="cash">Cash (pay on arrival, no ID needed)</option><option value="gcash">GCash</option><option value="bank">Bank transfer</option><option value="card">Credit/debit card</option></select>
<div id="on" style="display:none">
<p><b>A valid ID upload is required for GCash, bank transfer and card.</b> Cash does not need one.</p>
<p class="muted">Pay to GCash 0917 000 0000 or BDO 0012 3456 7890 (Exploreza Travel).</p><label>Payment reference number</label><input name="ref"><label>Upload a valid ID (required for online payment)</label><input type="file" name="idf" id="idf" accept=".jpg,.jpeg,.png,.pdf">
</div>
<?php if($u['role']=='user'): ?><p><label style="font-weight:400"><input type="checkbox" name="over18" value="1" style="width:auto" required> I confirm that I am 18 years old or older, and that the ID I upload (if any) is mine.</label></p><?php endif; ?>
<p><button class="btn" name="pay">Place booking</button></p></form><?php endif; ?>
</div>
<?php endif; foot(); ?>
