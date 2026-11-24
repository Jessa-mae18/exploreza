<?php
// Login, sign-up, verification and logout logic.
require_once __DIR__ . '/../config/config.php';
$err = [];
$msg = '';
if (isset($_GET['minor'])) {
    $err[] = 'Access denied: minors (under 18) are not allowed to use this system.';
}
if ($a == 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}
// Already signed in: go straight to their own area
if (($a == 'login' || $a == 'signup') && me()) {
    header('Location: ' . BASE_URL . role_home(me()['role']));
    exit();
}
// One-click login for accounts remembered on this computer
if ($a == 'login' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_uid'])) {
    $uid = (int) $_POST['quick_uid'];
    $tok = $_COOKIE['exp_tok_' . $uid] ?? '';
    $r = null;
    try {
        if (($_POST['do'] ?? '') == 'forget') {
            q('DELETE FROM remember_tokens WHERE user_id=? AND token_hash=?', [$uid, hash('sha256', $tok)]);
            setcookie('exp_tok_' . $uid, '', time() - 3600, '/');
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit();
        }
        $r = q(
            'SELECT u.* FROM remember_tokens t JOIN users u ON u.user_id=t.user_id WHERE t.user_id=? AND t.token_hash=? AND t.expires_at>NOW()',
            [$uid, hash('sha256', $tok)]
        )->fetch();
    } catch (Exception $e) {
        $r = null;
    }
    if ($r && $r['role'] == 'user' && !empty($r['birthdate']) && age_from($r['birthdate']) < 18) {
        $err[] = 'Access denied: minors (under 18) are not allowed to use this system.';
    } elseif (!$r) {
        $err[] = 'This saved account has expired. Please log in with your password.';
    } else {
        $_SESSION['u'] = [
            'id' => $r['user_id'],
            'name' => $r['first_name'],
            'username' => $r['username'],
            'role' => $r['role'],
            'verified' => $r['verified']
        ];
        setcookie(session_name(), session_id(), ['expires' => time() + 2592000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        if ($r['role'] == 'admin' || $r['role'] == 'guide') {
            header('Location: ' . BASE_URL . role_home($r['role']));
        } elseif (!$r['verified']) {
            header('Location: ' . BASE_URL . 'auth/verify.php');
        } else {
            header('Location: ' . BASE_URL . 'index.php');
        }
        exit();
    }
}
if ($a == 'login' && $_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['quick_uid'])) {
    $r = q('SELECT * FROM users WHERE username=? OR email=?', [$_POST['id'], $_POST['id']])->fetch();
    if (!$r || !password_verify($_POST['pw'], $r['password_hash'])) {
        $err[] = 'Wrong username/email or password.';
    } elseif ($r['role'] == 'user' && !empty($r['birthdate']) && age_from($r['birthdate']) < 18) {
        $err[] = 'Access denied: minors (under 18) are not allowed to use this system.';
    } else {
        $_SESSION['u'] = [
            'id' => $r['user_id'],
            'name' => $r['first_name'],
            'username' => $r['username'],
            'role' => $r['role'],
            'verified' => $r['verified']
        ];
        // Every account that logs in is remembered on this computer for 30 days
        // (the login page shows it as a card; remove it with the x)
        $month = time() + 2592000;
        setcookie('exp_user', $r['username'], $month, '/');
        try {
            $tok = bin2hex(random_bytes(32));
            q('DELETE FROM remember_tokens WHERE user_id=? AND expires_at<NOW()', [$r['user_id']]);
            q('INSERT INTO remember_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))', [
                $r['user_id'],
                hash('sha256', $tok)
            ]);
            setcookie('exp_tok_' . $r['user_id'], $tok, ['expires' => $month, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        } catch (Exception $e) {
        }
        setcookie(session_name(), session_id(), ['expires' => $month, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        if ($r['role'] == 'admin' || $r['role'] == 'guide') {
            header('Location: ' . BASE_URL . role_home($r['role']));
            exit();
        }
        if (!$r['verified']) {
            header('Location: ' . BASE_URL . 'auth/verify.php');
            exit();
        }
        $n = $_GET['next'] ?? 'index.php';
        if (!preg_match('#^[a-z-]+/[a-z-]+\.php(\?[\w=&%.-]*)?$#', $n)) {
            $n = 'index.php';
        }
        header('Location: ' . BASE_URL . $n);
        exit();
    }
}
if ($a == 'signup' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $f = array_map('trim', $_POST);
    if (!$f['first'] || !$f['last']) {
        $err[] = 'Enter your first and last name.';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Enter a valid email.';
    }
    if (!preg_match('/^(09|\+639)\d{9}$/', $f['phone'])) {
        $err[] = 'Use a Philippine mobile number (09XXXXXXXXX).';
    }
    $bd = $f['birthdate'] ?? '';
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $bd) || !checkdate((int) substr($bd, 5, 2), (int) substr($bd, 8, 2), (int) substr($bd, 0, 4))) {
        $err[] = 'Enter your birthdate.';
    } elseif (age_from($bd) < 18) {
        $err[] = 'You must be at least 18 years old to create an account and book tours.';
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,}$/', $_POST['pw'])) {
        $err[] = 'Password needs 8+ characters with upper, lower, number and symbol.';
    }
    if ($_POST['pw'] !== $_POST['pw2']) {
        $err[] = 'Passwords do not match.';
    }
    if (
        !$err &&
        q('SELECT 1 FROM users WHERE email=? OR username=?', [$f['email'], $f['username']])->fetch()
    ) {
        $err[] = 'Username or email already in use.';
    }
    if (!$err) {
        $code = sprintf('%06d', random_int(0, 999999));
        q(
            'INSERT INTO users(username,first_name,last_name,email,phone,password_hash,verify_code,birthdate) VALUES(?,?,?,?,?,?,?,?)',
            [
                $f['username'],
                $f['first'],
                $f['last'],
                $f['email'],
                $f['phone'],
                password_hash($_POST['pw'], PASSWORD_DEFAULT),
                $code,
                $bd
            ]
        );
        $id = $pdo->lastInsertId();
        $_SESSION['u'] = ['id' => $id, 'name' => $f['first'], 'role' => 'user', 'verified' => 0];
        header('Location: ' . BASE_URL . 'auth/verify.php');
        exit();
    }
}
if ($a == 'verify') {
    need();
    $r = q('SELECT verify_code,verified FROM users WHERE user_id=?', [me()['id']])->fetch();
    if ($r['verified']) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_POST['code'] === $r['verify_code']) {
            q('UPDATE users SET verified=1 WHERE user_id=?', [me()['id']]);
            $_SESSION['u']['verified'] = 1;
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
        $err[] = 'Incorrect code.';
    }
}
head(ucfirst($a));
?>
<div class="box"><?php foreach($err as $e)echo '<div class="err">'.h($e).'</div>'; ?>
<?php if($a=='login'): ?>
<h2>Welcome back</h2>
<?php
// Accounts remembered on this computer (shown in a dropdown under the username box)
$saved = [];
try {
    foreach ($_COOKIE as $ck => $cv) {
        if (preg_match('/^exp_tok_(\\d+)$/', $ck, $mm)) {
            $s = q(
                'SELECT u.user_id,u.username,u.email,u.first_name,u.last_name,u.role FROM remember_tokens t JOIN users u ON u.user_id=t.user_id WHERE t.user_id=? AND t.token_hash=? AND t.expires_at>NOW()',
                [(int) $mm[1], hash('sha256', $cv)]
            )->fetch();
            if ($s) {
                $saved[] = $s;
            }
        }
    }
} catch (Exception $e) {
}
$roleName = ['admin' => 'Admin', 'guide' => 'Tour guide', 'user' => 'Traveler'];
$colors = ['#1a73e8', '#188038', '#d93025', '#9334e6', '#e8710a', '#007b83', '#c2185b'];
$jsAccounts = [];
foreach ($saved as $s) {
    $jsAccounts[] = [
        'uid' => (int) $s['user_id'],
        'name' => $s['first_name'] . ' ' . $s['last_name'],
        'email' => $s['email'],
        'role' => $roleName[$s['role']] ?? '',
        'initial' => strtoupper(substr($s['first_name'], 0, 1)),
        'color' => $colors[$s['user_id'] % count($colors)]
    ];
}
?>
<form method="post" id="loginForm" autocomplete="off">
<input type="hidden" name="quick_uid" id="quickUid" value="" disabled>
<label>Username or email</label>
<div style="position:relative">
<input name="id" id="loginId" autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" required>
<div id="accList" class="acc-list" hidden></div>
</div>
<label>Password</label>
<div class="pw"><input type="password" name="pw" id="loginPw" autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" required><button type="button" class="eye" aria-label="Show password" onclick="tp(this)"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><line class="sl" x1="3" y1="21" x2="21" y2="3"/></svg></button>
</div>
<p><button class="btn">Log in</button></p></form>
<script>
// Keep both boxes blank when the page opens (stops the browser from auto-filling saved logins)
(function(){
var ids=['loginId','loginPw'];
ids.forEach(function(i){var e=document.getElementById(i);if(e)['focus','input'].forEach(function(ev){e.addEventListener(ev,function(){e.dataset.t='1';});});});
function wipe(){ids.forEach(function(i){var e=document.getElementById(i);if(e&&!e.dataset.t)e.value='';});}
[50,300,1000].forEach(function(ms){setTimeout(wipe,ms);});
window.addEventListener('pageshow',function(){setTimeout(wipe,50);});
})();
</script>
<form method="post" id="forgetForm"><input type="hidden" name="quick_uid" id="forgetUid"><input type="hidden" name="do" value="forget"></form>
<script>
(function(){
var accounts=<?= json_encode($jsAccounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if(!accounts.length)return;
var idEl=document.getElementById('loginId'),pw=document.getElementById('loginPw'),q=document.getElementById('quickUid'),list=document.getElementById('accList');
var MASK='\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022',suppress=false;
function clearQuick(){if(!q.disabled){q.disabled=true;q.value='';pw.readOnly=false;}}
function pick(a){idEl.value=a.email;pw.value=MASK;pw.readOnly=true;q.value=a.uid;q.disabled=false;list.hidden=true;}
function build(filter){
 list.innerHTML='';
 accounts.forEach(function(a){
  if(filter&&(a.name+' '+a.email).toLowerCase().indexOf(filter.toLowerCase())<0)return;
  var row=document.createElement('div');row.className='acc-row';
  var av=document.createElement('span');av.className='acc-av';av.style.background=a.color;av.textContent=a.initial;
  var info=document.createElement('span');info.className='acc-info';
  var b=document.createElement('b');b.textContent=a.name;
  var sm=document.createElement('small');sm.textContent=a.email+' \u00b7 '+a.role;
  info.appendChild(b);info.appendChild(document.createElement('br'));info.appendChild(sm);
  var x=document.createElement('button');x.type='button';x.className='acc-rm';x.title='Remove this account from this computer';x.textContent='\u00d7';
  x.addEventListener('mousedown',function(e){e.preventDefault();e.stopPropagation();document.getElementById('forgetUid').value=a.uid;document.getElementById('forgetForm').submit();});
  row.appendChild(av);row.appendChild(info);row.appendChild(x);
  row.addEventListener('mousedown',function(e){e.preventDefault();pick(a);});
  list.appendChild(row);
 });
 var other=document.createElement('div');other.className='acc-row';
 other.innerHTML='<span class="acc-av acc-av-o">&#9786;</span><span class="acc-info"><b>Use another account</b></span>';
 other.addEventListener('mousedown',function(e){e.preventDefault();clearQuick();idEl.value='';pw.value='';suppress=true;list.hidden=true;idEl.focus();});
 list.appendChild(other);
 list.hidden=false;
}
function open(){if(!suppress)build('');}
idEl.addEventListener('focus',open);
idEl.addEventListener('click',function(){suppress=false;build('');});
idEl.addEventListener('input',function(){suppress=false;clearQuick();build(idEl.value);});
idEl.addEventListener('blur',function(){list.hidden=true;});
pw.addEventListener('focus',function(){if(!q.disabled){clearQuick();pw.value='';}});
})();
</script>
<p class="muted">New here? <a href="<?= BASE_URL ?>auth/register.php">Create an account</a></p>
<?php elseif($a=='signup'): ?>
<h2>Create your account</h2><form method="post"><label>First name</label><input name="first" required><label>Last name</label><input name="last" required><label>Username</label><input name="username" required pattern="\w{4,30}"><label>Email</label><input type="email" name="email" required><label>Contact number</label><input name="phone" placeholder="09171234567" required><label>Birthdate (you must be 18 or older to book)</label><input type="date" name="birthdate" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required><label>Password</label>
<div class="pw"><input type="password" name="pw" required><button type="button" class="eye" aria-label="Show password" onclick="tp(this)"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><line class="sl" x1="3" y1="21" x2="21" y2="3"/></svg></button>
</div>
<p class="muted">8+ characters with uppercase, lowercase, number and symbol.</p><label>Confirm password</label>
<div class="pw"><input type="password" name="pw2" required><button type="button" class="eye" aria-label="Show password" onclick="tp(this)"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><line class="sl" x1="3" y1="21" x2="21" y2="3"/></svg></button>
</div>
<p><button class="btn">Sign up</button></p></form>
<?php else: ?>
<h2>Verify your account</h2>
<p>Enter the 6-digit code to unlock bookings.</p>
<div class="ok">Demo mode: no mail server is set up, so your code is <b><?= h($r['verify_code']) ?></b>.
</div><form method="post"><label>Verification code</label><input name="code" maxlength="6" required>
<p><button class="btn">Verify</button></p></form><?php endif; ?>
</div>
<?php foot(); ?>
