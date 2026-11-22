<?php
// Availability calendar for every package.
require __DIR__ . '/../includes/auth-check.php';
head('Availability');
$days = 30;
$pk = (int) ($_GET['pkg'] ?? 0);
$all = q(
    'SELECT p.*,d.name dn FROM packages p JOIN destinations d USING(destination_id) ORDER BY d.name,p.title'
)->fetchAll();
$list = array_values(array_filter($all, fn($p) => !$pk || $p['package_id'] == $pk));
$end = date('Y-m-d', strtotime("+$days day"));
$data = [];
$open = 0;
$full = 0;
foreach ($list as $p) {
    $m = q(
        "SELECT i.travel_date d,SUM(i.participants) n FROM booking_items i JOIN bookings b USING(booking_id) WHERE i.package_id=? AND i.travel_date BETWEEN ? AND ? AND b.status IN('pending','approved','completed') GROUP BY i.travel_date",
        [$p['package_id'], date('Y-m-d', strtotime('+1 day')), $end]
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $any = false;
    $t = [];
    for ($i = 1; $i <= $days; $i++) {
        $dt = date('Y-m-d', strtotime("+$i day"));
        $left = $p['max_participants'] - ($m[$dt] ?? 0);
        $t[] = [$dt, $left];
        if ($left > 0) {
            $any = true;
        }
    }
    $data[] = [$p, $t, $any];
    $any ? $open++ : $full++;
}
?>
<section class="sec">
<h2>Tour availability</h2>
<div class="stats">
<div class="stat"><b><?= count($list) ?></b>Packages shown
</div>
<div class="stat"><b><?= $open ?></b>With open dates
</div>
<div class="stat"><b><?= $full ?></b>Fully booked (next <?= $days ?> days)
</div>
</div>
<form class="bar" method="get"><select name="pkg" onchange="this.form.submit()"><option value="0">All packages</option><?php foreach($all as $p)echo '<option value="'.$p['package_id'].'"'.($p['package_id']==$pk?' selected':'').'>'.h($p['dn'].' – '.$p['title']).'</option>'; ?></select></form>
<p class="muted"><span class="slot open" style="display:inline-block;padding:2px 10px">Open</span> <span class="slot low" style="display:inline-block;padding:2px 10px">3 or fewer left</span> <span class="slot full" style="display:inline-block;padding:2px 10px">Full</span> · Click an open date to book it.</p>
<?php foreach($data as [$p,$t,$any]): ?>
<div class="card" style="margin-bottom:18px">
<div class="pad">
<h3><?= h($p['title']) ?> <span class="tag <?= $any?'approved':'rejected' ?>"><?= $any?'Dates available':'Fully booked' ?></span></h3>
<p class="muted"><?= h($p['dn']) ?> · <?= php_($p['price']) ?> per person · <?= $p['max_participants'] ?> guests per date</p>
<div class="slots"><?php foreach($t as [$dt,$left]){$lb=date('D j M',strtotime($dt)); echo $left<=0?'<span class="slot full">'.$lb.'<br>FULL</span>':'<a class="slot '.($left<=3?'low':'open').'" href="book.php?pkg='.$p['package_id'].'&date='.$dt.'">'.$lb.'<br>'.$left.' left</a>';} ?>
</div>
</div>
</div>
<?php endforeach; ?>
</section><?php foot(); ?>
