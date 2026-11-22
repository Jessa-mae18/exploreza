<?php
// Admin layout: sidebar and top bar.
$titles = [
    'dash' => 'Dashboard',
    'book' => 'Bookings',
    'pay' => 'Payments',
    'manage' => 'Manage content',
    'photos' => 'Photos',
    'gpay' => 'Guide payments'
];
$one = fn($s) => q($s)->fetchColumn();
$adm = me();
$srch = trim($_GET['s'] ?? '');
$sq = "%$srch%";
$pend = (int) $one("SELECT COUNT(*) FROM bookings WHERE status='pending'");
$files = [
    'dash' => 'dashboard.php',
    'book' => 'bookings.php',
    'pay' => 'payments.php',
    'manage' => 'manage.php',
    'photos' => 'photos.php',
    'gpay' => 'guide-payments.php'
];
$menu = [
    ['dash', 'Dashboard', '&#9638;'],
    ['book', 'Bookings', '&#9776;'],
    ['pay', 'Payments', '&#8369;'],
    ['manage', 'Manage content', '&#10010;'],
    ['photos', 'Photos', '&#9707;'],
    ['gpay', 'Guide payments', '&#8369;']
];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $titles[$t] ?> | Exploreza Admin</title><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css"><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css"></head><body class="adm">
<div class="adm-wrap">
<aside class="adm-side" id="aside"><a class="adm-logo" href="dashboard.php">Exploreza</a>
<div class="adm-lbl">MANAGE
</div>
<?php foreach($menu as [$k,$l,$g])echo '<a class="'.($t==$k?'on':'').'" href="'.$files[$k].'"><i>'.$g.'</i>'.$l.'</a>'; ?>
<div class="adm-lbl">SITE
</div>
<a href="<?= BASE_URL ?>customer/availability.php"><i>&#9719;</i>Availability</a><a href="<?= BASE_URL ?>index.php#packages"><i>&#9992;</i>Book a tour</a><a href="<?= BASE_URL ?>customer/my-trips.php"><i>&#9733;</i>My trips</a><a href="<?= BASE_URL ?>index.php"><i>&#8962;</i>View website</a><a href="<?= BASE_URL ?>auth/logout.php"><i>&#10162;</i>Log out</a>
<div class="adm-user"><span class="av"><?= h(strtoupper(substr($adm['name'],0,1))) ?></span>
<div><b>Admin</b><small>ADMINISTRATOR</small>
</div>
</div>
</aside>
<div class="adm-main">
<div class="adm-top"><button class="burger" onclick="document.getElementById('aside').classList.toggle('open')">&#9776;</button>
<form method="get" action="bookings.php" class="adm-search"><input name="s" placeholder="Search bookings by reference or name" value="<?= h($srch) ?>"></form>
<span class="sp"></span><a class="pill" href="bookings.php"><?= $pend ?> pending</a><span class="av"><?= h(strtoupper(substr($adm['name'],0,1))) ?></span>
</div>
<div class="adm-body">
<h1><?= $titles[$t] ?></h1>
<p class="adm-crumb">Exploreza &nbsp;/&nbsp; <?= $titles[$t] ?></p>
<?php if(!empty($_SESSION['ok'])){echo '<div class="ok">'.h($_SESSION['ok']).'</div>';unset($_SESSION['ok']);}
if(!empty($_SESSION['flash'])){echo '<div class="err">'.h($_SESSION['flash']).'</div>';unset($_SESSION['flash']);} ?>
