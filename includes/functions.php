<?php
// Shared helpers: database, sessions, availability, photos.
function q($s, $p = []) {
    global $pdo;
    $st = $pdo->prepare($s);
    $st->execute($p);
    return $st;
}
function h($s) {
    return htmlspecialchars((string) $s);
}
function php_($n) {
    return '₱' . number_format($n, 2);
}
function me() {
    return $_SESSION['u'] ?? null;
}
function need($role = null) {
    if (!me()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
    if ($role && me()['role'] !== $role) {
        http_response_code(403);
        require __DIR__ . '/../errors/403.php';
        exit();
    }
}
function booked($pkg, $date) {
    return (int) q(
        "SELECT COALESCE(SUM(i.participants),0) FROM booking_items i JOIN bookings b USING(booking_id) WHERE i.package_id=? AND i.travel_date=? AND b.status IN('pending','approved')",
        [$pkg, $date]
    )->fetchColumn();
}
function incart($pkg, $date) {
    $n = 0;
    foreach ($_SESSION['cart'] ?? [] as $c) {
        if ($c['pkg'] == $pkg && $c['date'] == $date) {
            $n += $c['pax'];
        }
    }
    return $n;
}
function notify($uid, $m) {
    q('INSERT INTO notifications(user_id,message) VALUES(?,?)', [$uid, $m]);
}
if (!q("SELECT 1 FROM users WHERE username='admin'")->fetch()) {
    q(
        "INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) VALUES('admin','Site','Admin','admin@tourbook.ph','09170000000',?, 'admin',1)",
        [password_hash('Admin_123', PASSWORD_DEFAULT)]
    );
}

// ---- Photo helpers (Wikimedia Commons API: free, no API key, needs credit)
function http_get($url) {
    $ua = 'ExplorezaBooking/1.0 (school project; info@exploreza.ph)';
    $GLOBALS['http_error'] = '';
    // SSL verification is relaxed so it also works on a default XAMPP without a CA bundle; only public image URLs are fetched.
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        $r = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        if ($r === false) {
            $GLOBALS['http_error'] = 'Connection problem: ' . curl_error($c);
        } elseif ($code >= 400) {
            $GLOBALS['http_error'] = 'The photo server answered with error ' . $code . '.';
            $r = false;
        }
        return $r;
    }
    $r = @file_get_contents(
        $url,
        false,
        stream_context_create([
            'http' => ['header' => "User-Agent: $ua\r\n", 'timeout' => 25],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ])
    );
    if ($r === false) {
        $GLOBALS['http_error'] = 'Could not reach the internet from PHP (enable the curl extension or allow_url_fopen in php.ini).';
    }
    return $r;
}
function commons_search($term, $limit = 12) {
    $url =
        'https://commons.wikimedia.org/w/api.php?' .
        http_build_query([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'search',
            'gsrnamespace' => 6,
            'gsrsearch' => $term . ' filetype:bitmap',
            'gsrlimit' => $limit,
            'prop' => 'imageinfo',
            'iiprop' => 'url|mime|size|extmetadata',
            'iiurlwidth' => 1200
        ]);
    $r = json_decode((string) http_get($url), true);
    $out = [];
    foreach ($r['query']['pages'] ?? [] as $p) {
        $i = $p['imageinfo'][0] ?? null;
        if (
            !$i ||
            ($i['mime'] ?? '') !== 'image/jpeg' ||
            ($i['width'] ?? 0) < 1000 ||
            empty($i['thumburl'])
        ) {
            continue;
        }
        $m = $i['extmetadata'] ?? [];
        $artist = trim(strip_tags($m['Artist']['value'] ?? 'Unknown'));
        $lic = $m['LicenseShortName']['value'] ?? '';
        $out[] = [
            'thumb' => $i['thumburl'],
            'credit' => mb_substr(
                trim($artist . ($lic ? ' / ' . $lic : '') . ' via Wikimedia Commons'),
                0,
                240
            )
        ];
    }
    return $out;
}
function save_destination_photo($id, $bytes, $credit) {
    $tmp = tempnam(sys_get_temp_dir(), 'ph');
    file_put_contents($tmp, $bytes);
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
        @unlink($tmp);
        throw new Exception('That file is not a valid image.');
    }
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
    $dir = __DIR__ . '/../assets/images/destinations/';
    $name = 'dest-' . $id . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!@rename($tmp, $dir . $name)) {
        copy($tmp, $dir . $name);
        @unlink($tmp);
    }
    $old = q('SELECT photo FROM destinations WHERE destination_id=?', [$id])->fetchColumn();
    if ($old && is_file($dir . $old)) {
        @unlink($dir . $old);
    }
    q('UPDATE destinations SET photo=?,photo_credit=? WHERE destination_id=?', [$name, $credit, $id]);
}
function cover_bg($r) {
    [$c1, $c2] = explode(',', $r['cover']);
    return !empty($r['photo']) && is_file(__DIR__ . '/../assets/images/destinations/' . $r['photo'])
        ? "background:linear-gradient(180deg,rgba(11,29,46,.05),rgba(11,29,46,.72)),url('" .
                BASE_URL .
                'assets/images/destinations/' .
                rawurlencode($r['photo']) .
                "') center/cover"
        : "background:linear-gradient(150deg,$c1,$c2)";
}

// ---- Roles, tour guide accounts and guide payments
function role_home($role) {
    return ['admin' => 'admin/dashboard.php', 'guide' => 'tour-guide/dashboard.php'][$role] ?? 'index.php';
}
function password_ok($p) {
    return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $p);
}
function guide_for_user($uid) {
    return q('SELECT * FROM guides WHERE user_id=?', [$uid])->fetch();
}
function create_guide_account($gid, $username = null, $password = null) {
    $g = q('SELECT * FROM guides WHERE guide_id=?', [$gid])->fetch();
    if (!$g || !empty($g['user_id'])) {
        return null;
    }
    if (!$username) {
        $parts = preg_split('/\s+/', strtolower(trim(preg_replace('/[^a-zA-Z ]/', '', $g['full_name']))));
        $username = $parts[0] . (count($parts) > 1 ? '.' . end($parts) : '');
    }
    $base = $username;
    $n = 1;
    while (q('SELECT 1 FROM users WHERE username=? OR email=?', [$username, $username . '@exploreza.ph'])->fetch()) {
        $n++;
        $username = $base . $n;
    }
    $password = $password ?: 'Guide_123';
    $names = explode(' ', trim($g['full_name']), 2);
    q(
        "INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) VALUES(?,?,?,?,?,?, 'guide',1)",
        [
            $username,
            $names[0],
            $names[1] ?? '',
            $username . '@exploreza.ph',
            $g['phone'],
            password_hash($password, PASSWORD_DEFAULT)
        ]
    );
    $uid = $GLOBALS['pdo']->lastInsertId();
    q('UPDATE guides SET user_id=? WHERE guide_id=?', [$uid, $gid]);
    return [$username, $password];
}
// Creates (or updates) the guide's pay for one tour and tells the guide.
function guide_payment_for_item($iid) {
    $r = q(
        'SELECT i.item_id,i.guide_id,i.travel_date,t.title,t.duration_days,g.day_rate,g.user_id,b.reference_no FROM booking_items i JOIN packages t ON t.package_id=i.package_id JOIN guides g ON g.guide_id=i.guide_id JOIN bookings b ON b.booking_id=i.booking_id WHERE i.item_id=?',
        [$iid]
    )->fetch();
    if (!$r) {
        return;
    }
    $amount = $r['day_rate'] * max(1, (int) $r['duration_days']);
    q(
        "INSERT INTO guide_payments(item_id,guide_id,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE guide_id=IF(status='pending',VALUES(guide_id),guide_id), amount=IF(status='pending',VALUES(amount),amount)",
        [$iid, $r['guide_id'], $amount]
    );
    if ($r['user_id']) {
        notify(
            $r['user_id'],
            "Booking {$r['reference_no']} approved: you are confirmed for {$r['title']} on {$r['travel_date']}. Your fee: " .
                php_($amount) .
                '.'
        );
    }
}

// ---- Maps (Leaflet + OpenStreetMap), geocoding (Nominatim) and ratings
function map_point($name, $region, $lat, $lng, $extra = '') {
    if ($lat === null || $lat === '' || $lng === null || $lng === '') {
        return ['name' => $name, 'lat' => null, 'lng' => null, 'html' => ''];
    }
    $osm = 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=12/' . $lat . '/' . $lng;
    return [
        'name' => $name,
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'html' =>
            '<b>' . h($name) . '</b><br>' . h($region) . '<br><a target="_blank" rel="noopener" href="' . $osm . '">Open in OpenStreetMap</a>' . ($extra ? '<br>' . $extra : '')
    ];
}
function render_map($points, $height = 360, $id = 'map') {
    $pts = array_values(array_filter($points, fn($p) => $p['lat'] !== null && $p['lng'] !== null));
    if (!$pts) {
        echo '<p class="muted">Map locations have not been set yet.</p>';
        return;
    }
    $json = json_encode($pts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">';
    echo '<div id="' . h($id) . '" style="height:' . (int) $height . 'px;border-radius:8px;position:relative;z-index:0"></div>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>';
    echo '<script>(function(){var pts=' . $json . ';var m=L.map("' . h($id) . '");' .
        'L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:18,attribution:"&copy; OpenStreetMap contributors"}).addTo(m);' .
        'var b=[];pts.forEach(function(p){L.marker([p.lat,p.lng]).addTo(m).bindPopup(p.html);b.push([p.lat,p.lng]);});' .
        'if(b.length==1){m.setView(b[0],10);}else{m.fitBounds(b,{padding:[30,30]});}})();</script>';
}
function geocode_place($name) {
    $r = json_decode(
        (string) http_get(
            'https://nominatim.openstreetmap.org/search?' .
                http_build_query(['q' => $name . ', Philippines', 'format' => 'json', 'limit' => 1, 'countrycodes' => 'ph'])
        ),
        true
    );
    return !empty($r[0]) ? [(float) $r[0]['lat'], (float) $r[0]['lon']] : null;
}
function stars_html($n) {
    $n = max(0, min(5, (int) round($n)));
    return '<span style="color:#e0a526;letter-spacing:2px">' . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . '</span>';
}
function guide_rating($gid) {
    try {
        $r = q('SELECT AVG(stars) a, COUNT(*) n FROM guide_ratings WHERE guide_id=?', [$gid])->fetch();
        return [$r['a'] ? round($r['a'], 1) : null, (int) $r['n']];
    } catch (Exception $e) {
        return [null, 0];
    }
}

// ---- Age check and the "where you will stay" map
function age_from($dob) {
    return (int) date_diff(date_create($dob), date_create('today'))->y;
}
function render_stay_map($pt, $id = 'staymap') {
    if ($pt['lat'] === null) {
        echo '<p class="muted">The map location for this place has not been set yet.</p>';
        return;
    }
    $json = json_encode($pt, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $js = <<<'JS'
(function(){
var c=__PT__, id='__ID__';
var m=L.map(id).setView([c.lat,c.lng],12);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(m);
L.marker([c.lat,c.lng]).addTo(m).bindPopup(c.html).openPopup();
var list=document.getElementById(id+'-list');
var types='^(hotel|resort|guest_house|hostel|apartment|motel)$';
var q='[out:json][timeout:20];(node["tourism"~"'+types+'"](around:10000,'+c.lat+','+c.lng+');way["tourism"~"'+types+'"](around:10000,'+c.lat+','+c.lng+'););out center 60;';
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
fetch('https://overpass-api.de/api/interpreter',{method:'POST',body:'data='+encodeURIComponent(q),headers:{'Content-Type':'application/x-www-form-urlencoded'}})
.then(function(r){return r.json();})
.then(function(d){
 var rows=[],seen={};
 d.elements.forEach(function(e){
  var t=e.tags||{},lat=e.lat||(e.center&&e.center.lat),lon=e.lon||(e.center&&e.center.lon);
  if(!t.name||!lat||seen[t.name])return;seen[t.name]=1;
  var kind=(t.tourism||'').replace('_',' ');
  L.circleMarker([lat,lon],{radius:7,color:'#1f6f78',fillColor:'#1f6f78',fillOpacity:.85}).addTo(m).bindPopup('<b>'+esc(t.name)+'</b><br>'+esc(kind));
  rows.push('<li><b>'+esc(t.name)+'</b> <span class="muted">('+esc(kind)+')</span></li>');
 });
 list.innerHTML=rows.length?'<b>Accommodations near this destination ('+rows.length+')</b><ul style="columns:2;margin:8px 0">'+rows.slice(0,40).join('')+'</ul>':'No accommodations are listed on the map for this area yet.';
})
.catch(function(){list.textContent='Could not load nearby accommodations right now. Please try again later.';});
})();
JS;
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">';
    echo '<div id="' . h($id) . '" style="height:380px;border-radius:8px;position:relative;z-index:0"></div>';
    echo '<div id="' . h($id) . '-list" class="muted" style="margin:10px 0">Looking for accommodations nearby...</div>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>';
    echo '<script>' . str_replace(['__PT__', '__ID__'], [$json, $id], $js) . '</script>';
}

// ---- Repair guide logins (creates missing accounts, links them, resets the password to Guide_123)
function guide_username($fullName) {
    $parts = preg_split('/\s+/', strtolower(trim(preg_replace('/[^a-zA-Z ]/', '', $fullName))));
    return $parts[0] . (count($parts) > 1 ? '.' . end($parts) : '');
}
function repair_guide_accounts() {
    try {
        $GLOBALS['pdo']->exec("ALTER TABLE users MODIFY role ENUM('user','admin','guide') NOT NULL DEFAULT 'user'");
    } catch (Exception $e) {
    }
    $names = [];
    foreach (q('SELECT * FROM guides')->fetchAll() as $g) {
        $row = $g['user_id'] ? q('SELECT * FROM users WHERE user_id=?', [$g['user_id']])->fetch() : null;
        if (!$row) {
            q('UPDATE guides SET user_id=NULL WHERE guide_id=?', [$g['guide_id']]);
            $row = q('SELECT * FROM users WHERE username=?', [guide_username($g['full_name'])])->fetch();
            if ($row) {
                q('UPDATE guides SET user_id=? WHERE guide_id=?', [$row['user_id'], $g['guide_id']]);
            } else {
                create_guide_account($g['guide_id']);
                $row = q('SELECT * FROM users WHERE user_id=(SELECT user_id FROM guides WHERE guide_id=?)', [$g['guide_id']])->fetch();
            }
        }
        q("UPDATE users SET role='guide', verified=1, password_hash=? WHERE user_id=?", [
            password_hash('Guide_123', PASSWORD_DEFAULT),
            $row['user_id']
        ]);
        $names[] = $row['username'];
    }
    return $names;
}
