<?php
// Admin: one-page health check for the photo feature.
$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';
head('Photo check');
$dir = __DIR__ . '/../assets/images/destinations/';
$rows = [];
$add = function ($name, $ok, $info) use (&$rows) {
    $rows[] = [$name, $ok, $info];
};

// 1. Database columns
try {
    $has = q("SHOW COLUMNS FROM destinations LIKE 'photo'")->fetch();
    $add('Database has the photo column', (bool) $has, $has ? 'OK' : 'Missing. Import database/update_photos.sql in phpMyAdmin (tourbook).');
} catch (Exception $e) {
    $has = false;
    $add('Database has the photo column', false, $e->getMessage());
}

// 2. Photos saved in the database and their files
$saved = 0;
$missing = [];
if ($has) {
    foreach (q('SELECT name, photo FROM destinations')->fetchAll() as $d) {
        if (!empty($d['photo'])) {
            $saved++;
            if (!is_file($dir . $d['photo'])) {
                $missing[] = $d['name'] . ' (' . $d['photo'] . ')';
            }
        }
    }
}
$add('Destinations with a photo saved', $saved > 0, $saved . ' saved in the database');
$add('Photo files exist in the folder', !$missing, $missing ? 'File missing for: ' . implode(', ', $missing) : 'OK');

// 3. Folder
$add('Photo folder exists', is_dir($dir), realpath($dir) ?: $dir);
$add('Photo folder is writable', is_writable($dir), is_writable($dir) ? 'OK' : 'PHP cannot save into this folder.');

// 4. PHP settings
$add('PHP curl extension', extension_loaded('curl'), extension_loaded('curl') ? 'On' : 'Off. In php.ini remove the ; before extension=curl, then restart Apache.');
$add('PHP openssl extension', extension_loaded('openssl'), extension_loaded('openssl') ? 'On' : 'Off. In php.ini remove the ; before extension=openssl, then restart Apache.');
$add('Upload limit', true, 'upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size'));

// 5. Internet from PHP
$r = http_get('https://commons.wikimedia.org/w/api.php?action=query&format=json&meta=siteinfo');
$add('PHP can reach the photo server', (bool) $r, $r ? 'OK' : ($GLOBALS['http_error'] ?: 'No answer'));

// 6. Search
$found = $r ? commons_search('El Nido Philippines', 3) : [];
$add('Photo search returns results', count($found) > 0, count($found) . ' result(s) for "El Nido Philippines"' . (!$found && !empty($GLOBALS['http_error']) ? ' - ' . $GLOBALS['http_error'] : ''));

// 7. Address the site uses for photos
$add('Photo web address', true, BASE_URL . 'assets/images/destinations/');
?>
<div class="box wide">
<h2>Photo check</h2>
<table>
<tr><th>Check</th><th>Result</th><th>Details</th></tr>
<?php foreach ($rows as [$n, $ok, $i]) {
    echo '<tr><td>' . h($n) . '</td><td><span class="tag ' . ($ok ? 'approved' : 'rejected') . '">' . ($ok ? 'OK' : 'PROBLEM') . '</span></td><td>' . h($i) . '</td></tr>';
} ?>
</table>
<p class="muted">Take a screenshot of this table and send it if something shows PROBLEM.</p>
</div>
<?php foot(); ?>
