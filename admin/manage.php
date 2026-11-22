<?php
// Admin: add destinations, packages and tour guides.
require_once __DIR__ . '/../includes/admin-actions.php';
$t = 'manage';
// Every guide gets a login automatically (temporary password Guide_123)
$made = [];
try {
    foreach (q('SELECT guide_id FROM guides WHERE user_id IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $gid2) {
        $acc = create_guide_account($gid2);
        if ($acc) {
            $made[] = $acc[0] . ' / ' . $acc[1];
        }
    }
} catch (Exception $e) {
}
if ($made) {
    $_SESSION['ok'] = count($made) . ' guide login(s) created (username / temporary password): ' . implode(', ', $made);
}
require __DIR__ . '/../includes/admin-header.php';
$ds = q('SELECT * FROM destinations')->fetchAll();
?>
<div class="grid"><form method="post" class="card pad">
<h3>Add destination</h3><input type="hidden" name="act" value="dest"><input name="name" placeholder="Name" required><input name="region" placeholder="Region" required style="margin-top:8px"><textarea name="desc" placeholder="Description"></textarea><input name="fee" type="number" placeholder="Entrance fee" value="0"><input name="lat" placeholder="Latitude (optional - found automatically)"><input name="lng" placeholder="Longitude (optional)">
<p><button class="btn">Save</button></p></form>
<form method="post" class="card pad">
<h3>Add package</h3><input type="hidden" name="act" value="pkg"><select name="d"><?php foreach($ds as $d)echo '<option value="'.$d['destination_id'].'">'.h($d['name']).'</option>'; ?></select><input name="title" placeholder="Title" required><textarea name="it" placeholder="Itinerary"></textarea><input name="days" type="number" placeholder="Days" value="1"><input name="price" type="number" placeholder="Price per person" required><input name="inc" placeholder="Inclusions"><input name="exc" placeholder="Exclusions"><input name="max" type="number" value="10">
<p><button class="btn">Save</button></p></form>
<form method="post" class="card pad">
<h3>Add tour guide</h3><input type="hidden" name="act" value="guide"><input name="name" placeholder="Full name" required><input name="sp" placeholder="Specialization"><input name="lang" placeholder="Languages"><input name="ph" placeholder="Phone"><input name="rate" type="number" min="0" value="1000" placeholder="Pay per tour day (PHP)"><label>Guide login (optional now, can be added later)</label><input name="username" placeholder="Username"><input name="password" type="password" placeholder="Password (8+, upper, lower, number, symbol)"><label>Destinations covered</label><?php foreach($ds as $d)echo '<label style="font-weight:400;margin:4px 0"><input type="checkbox" name="dest[]" value="'.$d['destination_id'].'" style="width:auto"> '.h($d['name']).'</label>'; ?>
<p><button class="btn">Save</button></p></form>
</div>
<form method="post" style="margin:22px 0 0"><input type="hidden" name="act" value="geocodeall"><button class="btn alt">Find map locations for destinations without one</button> <span class="muted">Uses the free OpenStreetMap search</span></form>
<h3 style="margin-top:26px">Tour guide directory</h3><form method="post" style="margin-bottom:12px"><input type="hidden" name="act" value="guideaccounts"><button class="btn alt">Create logins for all guides without one</button> <span class="muted">Temporary password: Guide_123 (guides can change it in their profile)</span></form>
<form method="post" style="margin-bottom:12px" onsubmit="return confirm('Reset every guide password to Guide_123?')"><input type="hidden" name="act" value="guiderepair"><button class="btn red">Repair all guide logins (set password to Guide_123)</button> <span class="muted">Use this if a guide cannot log in</span></form>
<table>
<tr>
<th>Guide</th>
<th>Specialization</th>
<th>Languages</th>
<th>Destinations covered</th><th>Day rate</th><th>Login account</th><th>Rating</th>
</tr><?php try{$gr=q("SELECT g.*,(SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM guide_destinations gd JOIN destinations d USING(destination_id) WHERE gd.guide_id=g.guide_id) places,(SELECT u.username FROM users u WHERE u.user_id=g.user_id) uname,(SELECT u.password_hash FROM users u WHERE u.user_id=g.user_id) phash,(SELECT ROUND(AVG(stars),1) FROM guide_ratings r WHERE r.guide_id=g.guide_id) avg_rating FROM guides g ORDER BY g.full_name")->fetchAll();}catch(Exception $e){$gr=[];} foreach($gr as $g)echo '<tr><td>'.h($g['full_name']).'</td><td>'.h($g['specialization']).'</td><td>'.h($g['languages']).'</td><td>'.h($g['places']?:'None yet').'</td><td>'.php_($g['day_rate']??1000).'</td><td>'.(!empty($g['uname'])?'<b>'.h($g['uname']).'</b><br><span class="muted">'.(password_verify('Guide_123',$g['phash'])?'Password: Guide_123 (temporary)':'Password was changed by the guide').'</span><br>':'No account yet<br>').'<form method="post" class="inline"><input type="hidden" name="act" value="guidelogin"><input type="hidden" name="g" value="'.$g['guide_id'].'">'.(empty($g['uname'])?'<input name="username" placeholder="username" style="width:120px" required> ':'').'<input type="password" name="password" placeholder="new password" style="width:130px" required> <button class="btn sm" style="margin:0">'.(empty($g['uname'])?'Create login':'Reset password').'</button></form></td><td>'.($g['avg_rating']?h($g['avg_rating']).' / 5':'-').'</td></tr>'; ?>
</table>
<?php require __DIR__.'/../includes/admin-footer.php'; ?>
