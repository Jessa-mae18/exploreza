<?php
// Customer booking history and confirmations.
require __DIR__ . '/../includes/auth-check.php';
$u = me();
if (!$u['verified']) {
    header('Location: ' . BASE_URL . 'auth/verify.php');
    exit();
}
if (isset($_GET['cancel'])) {
    q("UPDATE bookings SET status='cancelled' WHERE reference_no=? AND user_id=? AND status='pending'", [
        $_GET['cancel'],
        $u['id']
    ]);
    header('Location: my-trips.php');
    exit();
}
// Rate the tour guide after a completed tour
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['act'] ?? '') == 'rate') {
    $it = q(
        "SELECT i.item_id,i.guide_id,t.title FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id WHERE i.item_id=? AND b.user_id=? AND b.status='completed' AND i.guide_id IS NOT NULL",
        [(int) $_POST['item'], $u['id']]
    )->fetch();
    $stars = (int) ($_POST['stars'] ?? 0);
    if ($it && $stars >= 1 && $stars <= 5 && !q('SELECT 1 FROM guide_ratings WHERE item_id=?', [$it['item_id']])->fetch()) {
        q('INSERT INTO guide_ratings(item_id,guide_id,user_id,stars,comment) VALUES(?,?,?,?,?)', [
            $it['item_id'],
            $it['guide_id'],
            $u['id'],
            $stars,
            substr(trim($_POST['comment'] ?? ''), 0, 250)
        ]);
        $gu = q('SELECT user_id FROM guides WHERE guide_id=?', [$it['guide_id']])->fetchColumn();
        if ($gu) {
            notify($gu, "You received a $stars-star rating for {$it['title']}.");
        }
    }
    header('Location: my-trips.php?rated=1');
    exit();
}
function guide_cell($i, $b) {
    if (!$i['full_name']) {
        return $i['want_guide'] ? 'Awaiting assignment' : 'None';
    }
    $fee = $i['guide_fee'] > 0 ? ' <span class="muted">(+' . php_($i['guide_fee']) . ')</span>' : '';
    $html = '<a href="guide.php?id=' . (int) $i['guide_id'] . '">' . h($i['full_name']) . '</a>' . $fee;
    if ($b['status'] == 'completed') {
        if ($i['myrating']) {
            $html .= '<br>' . stars_html($i['myrating']) . ' <span class="muted">your rating</span>';
        } else {
            $html .=
                '<br><form method="post" class="inline"><input type="hidden" name="act" value="rate"><input type="hidden" name="item" value="' . (int) $i['item_id'] . '">' .
                '<select name="stars" style="width:auto"><option value="5">5 - Excellent</option><option value="4">4 - Very good</option><option value="3">3 - Good</option><option value="2">2 - Fair</option><option value="1">1 - Poor</option></select> ' .
                '<input name="comment" placeholder="Comment (optional)" style="width:170px"> <button class="btn sm" style="margin:0">Rate guide</button></form>';
        }
    }
    return $html;
}
head('My trips');
$ns = q('SELECT * FROM notifications WHERE user_id=? ORDER BY notif_id DESC LIMIT 5', [$u['id']])->fetchAll();
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);
?>
<section class="sec">
<h2>Hello, <?= h($u['name']) ?></h2>
<?php if(isset($_GET['rated'])) echo '<div class="ok">Thank you for rating your tour guide.</div>'; if(isset($_GET['ref'])) echo '<div class="ok">Booking confirmed for review. Reference: <b>'.h($_GET['ref']).'</b>. We will notify you once the admin approves it.</div>'; foreach($ns as $n)echo '<div class="ok">'.h($n['message']).'</div>'; ?>
<h3>Booking history</h3>
<?php foreach(q("SELECT b.*,p.method,p.status ps FROM bookings b LEFT JOIN payments p USING(booking_id) WHERE user_id=? ORDER BY booking_id DESC",[$u['id']])->fetchAll() as $b): ?>
<div class="card" style="margin-bottom:18px">
<div class="pad"><b><?= h($b['reference_no']) ?></b> <span class="tag <?= $b['status'] ?>"><?= $b['status'] ?></span> <span class="tag <?= $b['ps'] ?>">payment <?= $b['ps'] ?> (<?= $b['method'] ?>)</span> <span class="muted"><?= $b['created_at'] ?></span>
<table>
<tr>
<th>Package</th>
<th>Date</th>
<th>Guests</th>
<th>Guide</th>
</tr>
<?php foreach(q("SELECT i.*,t.title,g.full_name,(SELECT stars FROM guide_ratings r WHERE r.item_id=i.item_id) myrating FROM booking_items i JOIN packages t USING(package_id) LEFT JOIN guides g USING(guide_id) WHERE booking_id=?",[$b['booking_id']])->fetchAll() as $i) echo '<tr><td>'.h($i['title']).'</td><td>'.$i['travel_date'].'</td><td>'.$i['participants'].'</td><td>'.guide_cell($i,$b).'</td></tr>'; ?>
</table>
<p><b>Total <?= php_($b['total_amount']) ?></b> <span class="noprint"> <a href="#" onclick="print();return false">Print confirmation</a><?= $b['status']=='pending'?' · <a href="my-trips.php?cancel='.h($b['reference_no']).'">Cancel</a>':'' ?></span></p>
</div>
</div>
<?php endforeach; ?>
</section><?php foot(); ?>
