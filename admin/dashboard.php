<?php
// Admin dashboard with statistics and charts.
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'dash';
require __DIR__ . '/../includes/admin-header.php';
$tot = (int) $one('SELECT COUNT(*) FROM bookings');
$rev = (float) $one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'");
$cur = (int) $one(
    "SELECT COUNT(*) FROM bookings WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')"
);
$prev = (int) $one(
    "SELECT COUNT(*) FROM bookings WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE()-INTERVAL 1 MONTH,'%Y-%m')"
);
$growth = $prev > 0 ? round((($cur - $prev) / $prev) * 100, 2) : ($cur > 0 ? 100 : 0);
$st = q('SELECT status,COUNT(*) n FROM bookings GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$cols = [
    'pending' => '#f4a51c',
    'approved' => '#2fb35a',
    'completed' => '#2b6fd6',
    'rejected' => '#e5484d',
    'cancelled' => '#9aa7b4'
];
$acc = 0;
$stops = [];
foreach ($cols as $k => $c) {
    $n = (int) ($st[$k] ?? 0);
    if (!$n || !$tot) {
        continue;
    }
    $a = $acc;
    $acc += ($n / $tot) * 100;
    $stops[] = "$c {$a}% {$acc}%";
}
$conic = $stops ? implode(',', $stops) : '#e3e9ee 0% 100%';
$wd = q(
    "SELECT DAYOFWEEK(i.travel_date) d,SUM(i.participants) n FROM booking_items i JOIN bookings b USING(booking_id) WHERE b.status IN('pending','approved','completed') GROUP BY d"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$days = [2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun'];
$wmax = max(1, ...array_map('intval', array_values($wd ?: [0])));
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("first day of -$i month"));
    $months[date('M', strtotime($ym . '-01'))] = (float) q(
        "SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b USING(booking_id) WHERE p.status='verified' AND DATE_FORMAT(b.created_at,'%Y-%m')=?",
        [$ym]
    )->fetchColumn();
}
$mmax = max(1, ...array_values($months));
?>
<div class="adm-grid4">
<div class="adm-card adm-hello">
<div><small>Good day,</small>
<h2>Admin!</h2>
<p class="adm-date"><?= date('M j, Y') ?> &nbsp;&middot;&nbsp; <?= date('H:i:s') ?></p>
</div>
<svg viewBox="0 0 90 70" width="90" height="70" aria-hidden="true"><circle cx="45" cy="38" r="26" fill="#c3d6ef"/><path d="M14 40 L72 16 L56 58 L44 42 Z" fill="#2b6fd6"/><path d="M44 42 L72 16 L50 38 Z" fill="#fff" opacity=".7"/></svg>
</div>
<div class="adm-card adm-stat"><small>BOOKINGS</small><b><?= number_format($tot) ?></b><span class="up">&uarr; <?= $growth ?>% since last month</span><i class="ic">&#9776;</i>
</div>
<div class="adm-card adm-stat"><small>REVENUE</small><b><?= php_($rev) ?></b><span class="muted">Verified payments</span><i class="ic g">&#8369;</i>
</div>
<div class="adm-card adm-stat"><small>GROWTH</small><b><?= ($growth>=0?'+':'').$growth ?>%</b><span class="muted"><?= $cur ?> this month vs <?= $prev ?> last month</span><i class="ic t">&#8599;</i>
</div>
</div>
<div class="adm-grid4 mt">
<div class="adm-card adm-stat"><small>TRAVELERS</small><b><?= $one("SELECT COUNT(*) FROM users WHERE role='user'") ?></b><span class="muted">Registered accounts</span>
</div>
<div class="adm-card adm-stat"><small>AWAITING REVIEW</small><b><?= $pend ?></b><span class="muted">Pending bookings</span>
</div>
<div class="adm-card adm-stat"><small>PAYMENTS TO VERIFY</small><b><?= $one("SELECT COUNT(*) FROM payments WHERE status='pending'") ?></b><span class="muted">Check IDs and receipts</span>
</div>
<div class="adm-card adm-stat"><small>PACKAGES</small><b><?= $one("SELECT COUNT(*) FROM packages") ?></b><span class="muted"><?= $one("SELECT COUNT(*) FROM guides") ?> tour guides</span>
</div>
</div>
<div class="adm-row2 mt">
<div class="adm-card">
<h3>Booking status analytics</h3>
<div class="donut-wrap">
<div class="donut" style="background:conic-gradient(<?= $conic ?>)">
<div class="hole"><small>Total</small><b><?= $tot ?></b>
</div>
</div>
<div class="legend"><?php foreach($cols as $k=>$c)echo '<span><i style="background:'.$c.'"></i>'.ucfirst($k).' ('.(int)($st[$k]??0).')</span>'; ?>
</div>
</div>
</div>
<div class="adm-card">
<h3>Weekly trip insights <small class="muted">guests by travel day</small></h3><?php foreach($days as $d=>$nm){$v=(int)($wd[$d]??0);echo '<div class="hbar"><span>'.$nm.'</span><div><i style="width:'.round($v/$wmax*100).'%"></i></div><span>'.$v.'</span></div>';} ?>
</div>
</div>
<div class="adm-row2 mt">
<div class="adm-card">
<h3>Revenue report <small class="muted">last 6 months, verified</small></h3>
<div class="cols"><?php foreach($months as $m=>$v)echo '<div class="col"><span class="amt">'.($v?number_format($v):'0').'</span><div><i style="height:'.max(3,round($v/$mmax*100)).'%"></i></div><span>'.$m.'</span></div>'; ?>
</div>
</div>
<div class="adm-card">
<h3>Top packages</h3>
<table>
<tr>
<th>Package</th>
<th>Booked</th>
<th>Guests</th>
</tr><?php foreach(q("SELECT t.title,COUNT(*) n,SUM(i.participants) g FROM booking_items i JOIN packages t USING(package_id) GROUP BY t.package_id ORDER BY n DESC LIMIT 5")->fetchAll() as $r)echo "<tr><td>".h($r['title'])."</td><td>{$r['n']}</td><td>{$r['g']}</td></tr>"; ?>
</table>
<h3 style="margin-top:18px">Recent bookings</h3>
<table>
<tr>
<th>Ref</th>
<th>Traveler</th>
<th>Total</th>
<th>Status</th>
</tr><?php foreach(q("SELECT b.reference_no,b.total_amount,b.status,u.first_name FROM bookings b JOIN users u USING(user_id) ORDER BY booking_id DESC LIMIT 5")->fetchAll() as $r)echo '<tr><td>'.h($r['reference_no']).'</td><td>'.h($r['first_name']).'</td><td>'.php_($r['total_amount']).'</td><td><span class="tag '.$r['status'].'">'.$r['status'].'</span></td></tr>'; ?>
</table>
</div>
</div>

<?php require __DIR__.'/../includes/admin-footer.php'; ?>
