<?php
// Requires a logged-in user (and optionally a role).
// Set $required_role ('admin') before including to restrict a page; leave unset for any logged-in user.
require_once __DIR__ . '/../config/config.php';
need($required_role ?? null);
// Tour guides only use the guide portal
if (!isset($required_role) && me()['role'] == 'guide') {
    header('Location: ' . BASE_URL . 'tour-guide/dashboard.php');
    exit();
}
