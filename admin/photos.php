<?php
// Admin: add destination photos (upload or free photo search).
$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $a = $_POST['act'] ?? '';
    $id = (int) ($_POST['d'] ?? 0);
    try {
        if ($a == 'upload' && $id) {
            $f = $_FILES['photo'] ?? null;
            if (!$f || $f['error'] || $f['size'] > 6000000) {
                throw new Exception(
                    'Upload failed. Choose a JPG, PNG or WEBP under 2 MB (the default XAMPP limit).'
                );
            }
            save_destination_photo($id, file_get_contents($f['tmp_name']), null);
            $_SESSION['ok'] = 'Photo saved.';
        } elseif ($a == 'usephoto' && $id) {
            $url = $_POST['url'] ?? '';
            if (parse_url($url, PHP_URL_HOST) !== 'upload.wikimedia.org') {
                throw new Exception('Invalid photo address.');
            }
            $b = http_get($url);
            if (!$b) {
                throw new Exception('Could not download the photo. Check your internet connection.');
            }
            save_destination_photo($id, $b, $_POST['credit'] ?? null);
            $_SESSION['ok'] = 'Photo saved.';
        } elseif ($a == 'autofill') {
            set_time_limit(180);
            $n = 0;
            $notes = [];
            foreach (q('SELECT * FROM destinations')->fetchAll() as $d) {
                if (!empty($d['photo']) && is_file(__DIR__ . '/../assets/images/destinations/' . $d['photo'])) {
                    continue;
                }
                $terms = [$d['name'] . ' Philippines', trim(explode(',', $d['name'])[0])];
                $found = false;
                foreach ($terms as $term) {
                    foreach (commons_search($term, 6) as $x) {
                        $b = http_get($x['thumb']);
                        if ($b) {
                            try {
                                save_destination_photo($d['destination_id'], $b, $x['credit']);
                                $n++;
                                $found = true;
                                break 2;
                            } catch (Exception $e) {
                            }
                        }
                    }
                    if (!empty($GLOBALS['http_error'])) {
                        break;
                    }
                }
                if (!$found) {
                    $notes[] = $d['name'] . ': ' . ($GLOBALS['http_error'] ?: 'no photo found');
                }
            }
            $_SESSION['ok'] = $n . ' photo(s) added. Review them below and change any you do not like.';
            if ($notes) {
                $_SESSION['flash'] = 'Not added - ' . implode(' | ', $notes) . '. You can upload your own photo instead.';
            }
        } elseif ($a == 'removephoto' && $id) {
            $old = q('SELECT photo FROM destinations WHERE destination_id=?', [$id])->fetchColumn();
            if ($old && is_file(__DIR__ . '/../assets/images/destinations/' . $old)) {
                @unlink(__DIR__ . '/../assets/images/destinations/' . $old);
            }
            q('UPDATE destinations SET photo=NULL,photo_credit=NULL WHERE destination_id=?', [$id]);
            $_SESSION['ok'] = 'Photo removed.';
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'photos';
require __DIR__ . '/../includes/admin-header.php';
$ds = q('SELECT * FROM destinations ORDER BY name')->fetchAll();
$sd = (int) ($_GET['d'] ?? 0);
$sq = trim($_GET['q'] ?? '');
?>
<div class="adm-card" style="margin-bottom:18px">
<p><b>Add real photos.</b> Upload your own, or search free photos from Wikimedia Commons (no account or key needed). The photo and its credit then appear on the landing page.</p>
<form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Finding photos, please wait...'"><input type="hidden" name="act" value="autofill"><button class="btn">Auto-fill all missing photos</button></form>
</div>
<?php foreach($ds as $d): $q0=$sq!==''&&$sd==$d['destination_id']?$sq:$d['name'].' Philippines'; ?>
<div class="card" style="margin-bottom:18px">
<div class="pad">
<div class="phrow">
<div class="phthumb" style="<?= !empty($d['photo'])?"background-image:url('".BASE_URL."assets/images/destinations/".rawurlencode($d['photo'])."')":'' ?>">
</div>
<div class="phinfo">
<h3><?= h($d['name']) ?></h3>
<p class="muted"><?= !empty($d['photo'])?(is_file(__DIR__.'/../assets/images/destinations/'.$d['photo'])?h($d['photo_credit']?:'Your own photo'):'Photo file is missing - search or upload again'):'No photo yet' ?></p>
<form method="post" enctype="multipart/form-data" class="inline"><input type="hidden" name="act" value="upload"><input type="hidden" name="d" value="<?= $d['destination_id'] ?>"><input type="file" name="photo" accept="image/*" required style="width:auto"> <button class="btn sm" style="margin:0">Upload</button></form><br>
<form method="get" class="inline"><input type="hidden" name="d" value="<?= $d['destination_id'] ?>"><input name="q" value="<?= h($q0) ?>" style="width:260px"> <button class="btn alt sm" style="margin:0">Search free photos</button></form>
<?php if(!empty($d['photo'])): ?><form method="post" class="inline" onsubmit="return confirm('Remove this photo?')"><input type="hidden" name="act" value="removephoto"><input type="hidden" name="d" value="<?= $d['destination_id'] ?>"><button class="btn red sm" style="margin:0">Remove</button></form><?php endif; ?>
</div>
</div>
<?php if($sd==$d['destination_id']): $res=commons_search($q0,12); ?>
<div class="pgrid"><?php foreach($res as $x): ?><form method="post"><input type="hidden" name="act" value="usephoto"><input type="hidden" name="d" value="<?= $d['destination_id'] ?>"><input type="hidden" name="url" value="<?= h($x['thumb']) ?>"><input type="hidden" name="credit" value="<?= h($x['credit']) ?>"><img src="<?= h($x['thumb']) ?>" loading="lazy" alt=""><button class="btn sm" style="margin:0;width:100%">Use this photo</button></form><?php endforeach; if(!$res) echo '<p class="muted">No photos found. ' . h($GLOBALS['http_error'] ?? '') . ' Try different words, for example the place name only.</p>'; ?>
</div><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php require __DIR__.'/../includes/admin-footer.php'; ?>
