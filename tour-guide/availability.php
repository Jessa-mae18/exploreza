<?php
// Tour guide: mark days off and see booked, off and free days.
$required_role = 'guide';
require_once __DIR__ . '/../includes/auth-check.php';
$guide = guide_for_user(me()['id']);
function guide_busy_on($gid, $day) {
    return (int) q(
        "SELECT COUNT(*) FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id WHERE i.guide_id=? AND b.status IN('pending','approved') AND i.travel_date<=? AND DATE_ADD(i.travel_date, INTERVAL (t.duration_days-1) DAY)>=?",
        [$gid, $day, $day]
    )->fetchColumn();
}
if ($guide && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $gid = $guide['guide_id'];
    $act = $_POST['act'] ?? '';
    if ($act == 'off') {
        $from = $_POST['from'] ?? '';
        $to = ($_POST['to'] ?? '') ?: $from;
        $fmt = '/^\d{4}-\d{2}-\d{2}$/';
        if (
            !preg_match($fmt, $from) ||
            !preg_match($fmt, $to) ||
            $from < date('Y-m-d') ||
            $to < $from ||
            strtotime($to) - strtotime($from) > 60 * 86400
        ) {
            $_SESSION['flash'] = 'Choose valid dates: today or later, up to 60 days at a time.';
        } else {
            $done = 0;
            $skipped = 0;
            for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
                $day = date('Y-m-d', $d);
                if (guide_busy_on($gid, $day)) {
                    $skipped++;
                    continue;
                }
                q('INSERT IGNORE INTO guide_days_off(guide_id,off_date,note) VALUES(?,?,?)', [
                    $gid,
                    $day,
                    substr($_POST['note'] ?? '', 0, 100)
                ]);
                $done++;
            }
            $_SESSION['ok'] = "$done day(s) marked as day off." . ($skipped ? " $skipped day(s) skipped because you already have a tour." : '');
        }
    }
    if ($act == 'on') {
        q('DELETE FROM guide_days_off WHERE guide_id=? AND off_date=?', [$gid, $_POST['date'] ?? '']);
        $_SESSION['ok'] = 'You are available again on that day.';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
$t = 'avail';
require __DIR__ . '/../includes/guide-header.php';
$off = array_flip(q('SELECT off_date FROM guide_days_off WHERE guide_id=?', [$gid])->fetchAll(PDO::FETCH_COLUMN));
?>
<div class="adm-card" style="margin-bottom:18px">
<h3>Tell the admin when you are not available</h3>
<p class="muted">The admin cannot assign you to a tour on days you mark as day off.</p>
<form method="post" class="bar">
<input type="hidden" name="act" value="off">
<input type="date" name="from" min="<?= date('Y-m-d') ?>" required title="From">
<input type="date" name="to" min="<?= date('Y-m-d') ?>" title="To (optional)">
<input name="note" placeholder="Reason (optional)">
<button class="btn">Mark day off</button>
</form>
</div>
<div class="adm-card" style="margin-bottom:18px">
<h3>Next 6 weeks</h3>
<p class="muted"><span class="slot open" style="display:inline-block;padding:2px 10px">Free</span> <span class="slot off" style="display:inline-block;padding:2px 10px">Day off</span> <span class="slot full" style="display:inline-block;padding:2px 10px">Tour booked</span></p>
<div class="slots">
<?php for ($i = 0; $i < 42; $i++) {
    $day = date('Y-m-d', strtotime("+$i day"));
    $lb = date('D j M', strtotime($day));
    if (guide_busy_on($gid, $day)) {
        echo '<span class="slot full">' . $lb . '<br>Tour</span>';
    } elseif (isset($off[$day])) {
        echo '<span class="slot off">' . $lb . '<br>Day off</span>';
    } else {
        echo '<span class="slot open">' . $lb . '<br>Free</span>';
    }
} ?>
</div>
</div>
<div class="adm-card">
<h3>My days off</h3>
<table>
<tr><th>Date</th><th>Reason</th><th></th></tr>
<?php foreach (q('SELECT * FROM guide_days_off WHERE guide_id=? AND off_date>=CURDATE() ORDER BY off_date', [$gid])->fetchAll() as $x) {
    echo '<tr><td>' . h($x['off_date']) . '</td><td>' . h($x['note'] ?: '-') . '</td><td><form method="post" class="inline"><input type="hidden" name="act" value="on"><input type="hidden" name="date" value="' . h($x['off_date']) . '"><button class="btn alt sm" style="margin:0">Remove</button></form></td></tr>';
} ?>
</table>
</div>
<?php require __DIR__ . '/../includes/guide-footer.php'; ?>
