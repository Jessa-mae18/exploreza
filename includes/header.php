<?php
// Public page header and navigation.
function head($t) {
    $u = me();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
        h($t) .
        ' | Exploreza</title><link rel="stylesheet" href="' .
        BASE_URL .
        'assets/css/style.css"></head><body><header class="nav"><a class="logo" href="' .
        BASE_URL .
        'index.php">Exploreza</a><nav>';
    echo '<a href="' .
        BASE_URL .
        'index.php#destinations">Destinations</a><a href="' .
        BASE_URL .
        'index.php#packages">Packages</a><a href="' .
        BASE_URL .
        'customer/availability.php">Availability</a><a href="' .
        BASE_URL .
        'index.php#guides">Guides</a><a href="' .
        BASE_URL .
        'index.php#faq">FAQ</a><a href="' .
        BASE_URL .
        'index.php#contact">Contact</a>';
    if ($u) {
        echo '<span class="who">Signed in as <b>' . h($u['username'] ?? $u['name']) . '</b></span>';
        if ($u['role'] == 'guide') {
            echo '<a class="btn sm" href="' . BASE_URL . 'tour-guide/dashboard.php">Guide portal</a>';
        } else {
            echo '<a href="' .
                BASE_URL .
                'customer/checkout.php">Cart (' .
                count($_SESSION['cart'] ?? []) .
                ')</a><a href="' .
                BASE_URL .
                'customer/my-trips.php">My trips</a>';
            if ($u['role'] == 'admin') {
                echo '<a class="btn sm" href="' . BASE_URL . 'admin/dashboard.php">Admin dashboard</a>';
            }
        }
        echo '<a href="' . BASE_URL . 'auth/logout.php">Log out</a>';
    } else {
        echo '<a href="' .
            BASE_URL .
            'auth/login.php">Log in</a><a class="btn sm" href="' .
            BASE_URL .
            'auth/register.php">Sign up</a>';
    }
    echo '</nav></header><main>';
}
