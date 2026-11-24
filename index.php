<?php
// Landing page: hero, destinations, packages, guides, FAQ and contact.
require __DIR__ . '/config/config.php';
head('Philippine tours');
$u = me();
$qs = trim($_GET['s'] ?? '');
$rg = $_GET['r'] ?? '';
$sql = 'SELECT * FROM destinations WHERE (name LIKE ? OR region LIKE ?)' . ($rg ? ' AND region=?' : '');
$ds = q($sql, array_merge(["%$qs%", "%$qs%"], $rg ? [$rg] : []))->fetchAll();
$regions = q('SELECT DISTINCT region FROM destinations')->fetchAll(PDO::FETCH_COLUMN);
try {
    $hp = q(
        "SELECT photo FROM destinations WHERE photo IS NOT NULL AND photo<>'' ORDER BY destination_id LIMIT 1"
    )->fetchColumn();
} catch (Exception $e) {
    $hp = null;
}
$heroStyle = $hp
    ? "background:linear-gradient(100deg,rgba(11,29,46,.92) 30%,rgba(11,29,46,.25)),url('" .
        BASE_URL .
        'assets/images/destinations/' .
        rawurlencode($hp) .
        "') center/cover"
    : '';
function link_for($id) {
    $u = me();
    if (!$u) {
        return BASE_URL . 'auth/login.php?next=' . urlencode("customer/book.php?pkg=$id");
    }
    return BASE_URL . "customer/book.php?pkg=$id";
}
?>
<section class="hero" style="<?= $heroStyle ?>">
<h1>Discover the<br>Philippines, guided.</h1>
<p>Island lagoons, mountain terraces and heritage towns. Book several tours in one transaction and have a local guide assigned to you.</p>
<p><a class="btn" href="<?= $u?'#packages':BASE_URL.'auth/register.php' ?>"><?= $u?'Browse packages':'Create your account' ?></a></p>
</section>
<section class="sec" id="destinations">
<h2>Destinations</h2>
<form class="bar" method="get" action="#destinations"><input name="s" placeholder="Search destination or region" value="<?= h($qs) ?>"><select name="r"><option value="">All regions</option><?php foreach($regions as $r) echo '<option'.($r==$rg?' selected':'').'>'.h($r).'</option>'; ?></select><button class="btn alt">Search</button></form>
<div class="grid"><?php foreach($ds as $d): [$c1,$c2]=explode(',',$d['cover']); ?>
<div class="card">
<div class="cover tall" style="<?= cover_bg($d) ?>"><?= h($d['name']) ?>
</div>
<div class="pad"><span class="tag"><?= h($d['region']) ?></span>
<p><?= h($d['description']) ?></p>
<p class="muted">Entrance fee: <?= $d['entrance_fee']>0?php_($d['entrance_fee']):'Free' ?></p><?php if(!empty($d['photo_credit'])): ?>
<p class="credit">Photo: <?= h($d['photo_credit']) ?></p><?php endif; ?>
</div>
</div><?php endforeach; if(!$ds) echo '<p>No destinations match your search.</p>'; ?>
</div>
</section>
<section class="sec w" id="packages">
<h2>Popular tour packages</h2>
<div class="grid">
<?php foreach(q("SELECT p.*,d.*,d.name dn FROM packages p JOIN destinations d USING(destination_id) ORDER BY p.price")->fetchAll() as $p): [$c1,$c2]=explode(',',$p['cover']); $l=link_for($p['package_id']); ?>
<div class="card">
<div class="cover" style="height:150px;<?= cover_bg($p) ?>;font-size:1.1rem"><?= h($p['dn']) ?>
</div>
<div class="pad">
<h3><?= h($p['title']) ?></h3>
<p class="muted"><?= $p['duration_days'] ?> day(s) · up to <?= $p['max_participants'] ?> guests</p>
<p><?= h($p['itinerary']) ?></p>
<div class="price"><?= php_($p['price']) ?> <span class="muted">per person</span>
</div><?php if($l!==''): ?>
<p><a class="btn" href="<?= $l ?>">Book this tour</a></p><?php else: ?>
<p class="muted">Admin view</p><?php endif; ?>
</div>
</div><?php endforeach; ?>
</div>
</section>
<section class="sec" id="guides">
<h2>Meet our tour guides</h2>
<div class="grid"><?php try{$gl=q("SELECT g.*,(SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM guide_destinations gd JOIN destinations d USING(destination_id) WHERE gd.guide_id=g.guide_id) places FROM guides g")->fetchAll();}catch(Exception $e){$gl=q("SELECT * FROM guides")->fetchAll();} foreach($gl as $g): ?>
<div class="card">
<div class="pad">
<h3><?= h($g['full_name']) ?></h3>
<p><?= h($g['specialization']) ?></p>
<?php [$avg, $cnt] = guide_rating($g['guide_id']); echo $avg ? stars_html($avg) . ' <span class="muted">' . $avg . ' (' . $cnt . ')</span>' : '<span class="muted">No ratings yet</span>'; ?>
<p class="muted">Speaks <?= h($g['languages']) ?></p><?php if(!empty($g['places'])): ?>
<p class="muted">Covers: <?= h($g['places']) ?></p><?php endif; ?>
<p><a href="<?= me() ? BASE_URL . 'customer/guide.php?id=' . $g['guide_id'] : BASE_URL . 'auth/login.php?next=' . urlencode('customer/guide.php?id=' . $g['guide_id']) ?>">View profile and reviews</a></p>
</div>
</div><?php endforeach; ?>
</div>
</section>
<section class="sec w" id="faq">
<h2>Frequently asked questions</h2>
<details>
<summary>Can I book more than one tour at once?</summary>Yes. Add as many packages as you like to your cart and pay for all of them in one transaction.
</details>
<details>
<summary>Do I need a tour guide?</summary>It is optional. Tick the guide option on a booking and the admin assigns whoever is available on your date.
</details>
<details>
<summary>Which payment methods are accepted?</summary>Cash, GCash, bank transfer or card. Online methods need a valid ID upload for verification; cash does not.
</details>
<details>
<summary>How do I cancel?</summary>Open My trips and cancel any booking that is still pending.
</details>
</section>
<section class="sec" id="contact">
<h2>Contact us</h2>
<p>Email: info@exploreza.ph · Phone: 0917 000 0000 · Open daily 8:00 to 18:00 (PHT).</p>
</section>
<?php foot(); ?>
