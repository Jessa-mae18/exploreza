<?php
// Site settings, base URL detection and shared includes.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
}
date_default_timezone_set('Asia/Manila');
// BASE_URL is detected from the folder name, so the project can be renamed freely.
$__root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$__doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$__path = $__doc !== '' && stripos($__root, $__doc) === 0 ? substr($__root, strlen($__doc)) : '';
define('BASE_URL', rtrim($__path, '/') . '/');
// Extra charge per tour day when the traveler asks for a tour guide
define('GUIDE_FEE_PER_DAY', 1500);
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/payment-ids/');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
