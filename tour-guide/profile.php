<?php
// Tour guide: profile details and password change.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $row = q('SELECT password_hash FROM users WHERE user_id=?', [me()['id']])->fetch();
    if (!password_verify($_POST['current'] ?? '', $row['password_hash'])) {
        $_SESSION['flash'] = 'Your current password is not correct.';
    } elseif (!password_ok($_POST['new'] ?? '')) {
        $_SESSION['flash'] = 'New password needs 8+ characters with upper, lower, number and symbol.';
    } elseif (($_POST['new'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $_SESSION['flash'] = 'The new passwords do not match.';
    } else {
        q('UPDATE users SET password_hash=? WHERE user_id=?', [password_hash($_POST['new'], PASSWORD_DEFAULT), me()['id']]);
        $_SESSION['ok'] = 'Password changed.';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
$t = 'profile';
require __DIR__ . '/../includes/guide-header.php';
?>
<div class="adm-row2">
<div class="adm-card">
<h3>My details</h3>
<table>
<tr><th>Name</th><td><?= h($guide['full_name']) ?></td></tr>
<tr><th>Username</th><td><?= h($me['username'] ?? '') ?></td></tr>
<tr><th>Specialization</th><td><?= h($guide['specialization']) ?></td></tr>
<tr><th>Languages</th><td><?= h($guide['languages']) ?></td></tr>
<tr><th>Phone</th><td><?= h($guide['phone']) ?></td></tr>
<tr><th>Pay per tour day</th><td><?= php_($guide['day_rate']) ?></td></tr>
</table>
<p class="muted">To change these details, ask the admin.</p>
</div>
<div class="adm-card">
<h3>Change password</h3>
<form method="post">
<label>Current password</label><input type="password" name="current" required>
<label>New password</label><input type="password" name="new" required>
<label>Confirm new password</label><input type="password" name="confirm" required>
<p><button class="btn">Change password</button></p>
</form>
</div>
</div>
<?php [$avg, $cnt] = guide_rating($gid); ?>
<div class="adm-card mt">
<h3>My ratings and reviews</h3>
<p><?= $avg ? stars_html($avg) . ' <b>' . $avg . ' / 5</b> from ' . $cnt . ' rating(s)' : 'No ratings yet.' ?></p>
<?php foreach (q('SELECT r.*,u.first_name FROM guide_ratings r JOIN users u ON u.user_id=r.user_id WHERE r.guide_id=? ORDER BY r.rating_id DESC LIMIT 20', [$gid])->fetchAll() as $x) {
    echo '<p style="margin:10px 0;padding:8px 10px;background:#f4f7fa">' . stars_html($x['stars']) . ' <b>' . h($x['first_name']) . '</b><br>' . h($x['comment'] ?: 'No comment') . '<br><small class="muted">' . h($x['created_at']) . '</small></p>';
} ?>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
