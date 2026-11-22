<?php
// Tour guide layout: sidebar and top bar.
$titles = [
    'dash' => 'Dashboard',
    'book' => 'Assigned bookings',
    'avail' => 'My availability',
    'places' => 'My places',
    'pay' => 'My payments',
    'profile' => 'Profile'
];
$files = [
    'dash' => 'dashboard.php',
    'book' => 'bookings.php',
    'avail' => 'availability.php',
    'places' => 'places.php',
    'pay' => 'payments.php',
    'profile' => 'profile.php'
];
$menu = [
    ['dash', 'Dashboard', '&#9638;'],
    ['book', 'Assigned bookings', '&#9776;'],
    ['avail', 'Availability', '&#9719;'],
    ['places', 'My places', '&#9873;'],
    ['pay', 'My payments', '&#8369;'],
    ['profile', 'Profile', '&#9786;']
];
$me = me();
$guide = $guide ?? guide_for_user($me['id']);
if (!$guide) {
    http_response_code(403);
    head('Guide account');
    echo '<div class="box"><h2>No guide profile</h2><p>Your login is not linked to a tour guide profile yet. Please ask the admin.</p><a class="btn" href="' . BASE_URL . 'auth/logout.php">Log out</a></div>';
    foot();
    exit();
}
$gid = $guide['guide_id'];
$unread = (int) q('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$me['id']])->fetchColumn();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $titles[$t] ?> | Exploreza Guide</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body class="adm">
<div class="adm-wrap">
<aside class="adm-side" id="aside">
<a class="adm-logo" href="dashboard.php">Exploreza</a>
<div class="adm-lbl">MY ACCOUNT</div>
<?php foreach ($menu as [$k, $l, $g]) {
    echo '<a class="' . ($t == $k ? 'on' : '') . '" href="' . $files[$k] . '"><i>' . $g . '</i>' . $l . '</a>';
} ?>
<div class="adm-lbl">SITE</div>
<a href="<?= BASE_URL ?>index.php"><i>&#8962;</i>View website</a>
<a href="<?= BASE_URL ?>auth/logout.php"><i>&#10162;</i>Log out</a>
<div class="adm-user">
<span class="av"><?= h(strtoupper(substr($guide['full_name'], 0, 1))) ?></span>
<div><b><?= h($guide['full_name']) ?></b><small>TOUR GUIDE</small></div>
</div>
</aside>
<div class="adm-main">
<div class="adm-top">
<button class="burger" onclick="document.getElementById('aside').classList.toggle('open')">&#9776;</button>
<span class="sp"></span>
<a class="pill" href="dashboard.php"><?= $unread ?> new notification<?= $unread == 1 ? '' : 's' ?></a>
<span class="av"><?= h(strtoupper(substr($guide['full_name'], 0, 1))) ?></span>
</div>
<div class="adm-body">
<h1><?= $titles[$t] ?></h1>
<p class="adm-crumb">Exploreza &nbsp;/&nbsp; <?= $titles[$t] ?></p>
<?php
if (!empty($_SESSION['ok'])) {
    echo '<div class="ok">' . h($_SESSION['ok']) . '</div>';
    unset($_SESSION['ok']);
}
if (!empty($_SESSION['flash'])) {
    echo '<div class="err">' . h($_SESSION['flash']) . '</div>';
    unset($_SESSION['flash']);
}
?>
