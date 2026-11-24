<?php
// Admin form actions (assign guide, approve, verify, delete, add records).
$required_role = 'admin';
require_once __DIR__ . '/auth-check.php';
function guides_for($i) {
    $end = date(
        'Y-m-d',
        strtotime($i['travel_date'] . ' +' . max(0, (int) $i['duration_days'] - 1) . ' day')
    );
    $out = [];
    try {
        $gl = q(
            'SELECT g.* FROM guides g JOIN guide_destinations gd ON gd.guide_id=g.guide_id WHERE gd.destination_id=? ORDER BY g.full_name',
            [$i['destination_id']]
        )->fetchAll();
    } catch (Exception $e) {
        $gl = q('SELECT * FROM guides ORDER BY full_name')->fetchAll();
    }
    foreach ($gl as $g) {
        $c = q(
            "SELECT b.reference_no,t.title FROM booking_items i2 JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i2.package_id WHERE i2.guide_id=? AND i2.item_id<>? AND b.status IN('pending','approved') AND i2.travel_date<=? AND DATE_ADD(i2.travel_date,INTERVAL (t.duration_days-1) DAY)>=?",
            [$g['guide_id'], $i['item_id'], $end, $i['travel_date']]
        )->fetch();
        $g['busy'] = $c ?: null;
        if (!$g['busy']) {
            try {
                $off = q('SELECT COUNT(*) FROM guide_days_off WHERE guide_id=? AND off_date BETWEEN ? AND ?', [
                    $g['guide_id'],
                    $i['travel_date'],
                    $end
                ])->fetchColumn();
            } catch (Exception $e) {
                $off = 0;
            }
            if ($off) {
                $g['busy'] = ['reference_no' => 'guide', 'title' => 'marked as day off', 'off' => 1];
            }
        }
        if (!$g['busy']) {
            $o = q(
                'SELECT t.title FROM booking_items i2 JOIN packages t ON t.package_id=i2.package_id WHERE i2.booking_id=? AND i2.guide_id=? AND i2.item_id<>? AND t.destination_id<>?',
                [$i['booking_id'], $g['guide_id'], $i['item_id'], $i['destination_id']]
            )->fetch();
            if ($o) {
                $g['busy'] = [
                    'reference_no' => 'this booking',
                    'title' => $o['title'] . ' (different place)',
                    'same' => 1
                ];
            }
        }
        $out[] = $g;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $a = $_POST['act'];
    if ($a == 'assign') {
        $it = q(
            'SELECT i.*,t.duration_days,t.destination_id FROM booking_items i JOIN packages t USING(package_id) WHERE i.item_id=?',
            [$_POST['item']]
        )->fetch();
        $pick = $_POST['guide'] ?: null;
        if (isset($_POST['auto'])) {
            $pick = null;
            foreach (guides_for($it) as $g) {
                if (!$g['busy']) {
                    $pick = $g['guide_id'];
                    break;
                }
            }
            if (!$pick) {
                $_SESSION['flash'] = 'No tour guide is available on that date.';
            }
        } elseif ($pick) {
            foreach (guides_for($it) as $g) {
                if ($g['guide_id'] == $pick && $g['busy']) {
                    $_SESSION['flash'] = $g['full_name'] . ' is already booked on those dates.';
                    $pick = null;
                }
            }
        }
        if ($pick) {
            q('UPDATE booking_items SET guide_id=? WHERE item_id=?', [$pick, $it['item_id']]);
            $info = q(
                'SELECT b.reference_no,b.status,t.title FROM booking_items i JOIN bookings b USING(booking_id) JOIN packages t ON t.package_id=i.package_id WHERE i.item_id=?',
                [$it['item_id']]
            )->fetch();
            if (!empty($it['guide_id']) && $it['guide_id'] != $pick) {
                $old = q('SELECT user_id FROM guides WHERE guide_id=?', [$it['guide_id']])->fetchColumn();
                if ($old) {
                    notify($old, "You were removed from {$info['title']} on {$it['travel_date']} (booking {$info['reference_no']}).");
                }
                q("DELETE FROM guide_payments WHERE item_id=? AND status='pending'", [$it['item_id']]);
            }
            $gu = q('SELECT user_id FROM guides WHERE guide_id=?', [$pick])->fetchColumn();
            if ($gu) {
                notify($gu, "New tour assigned: {$info['title']} on {$it['travel_date']} (booking {$info['reference_no']}). Waiting for admin approval.");
            }
            if ($info['status'] == 'approved') {
                guide_payment_for_item($it['item_id']);
            }
        }
    }
    if ($a == 'approve' || $a == 'reject') {
        $bid = $_POST['b'];
        $b = q('SELECT * FROM bookings WHERE booking_id=?', [$bid])->fetch();
        $miss = q(
            'SELECT COUNT(*) FROM booking_items WHERE booking_id=? AND want_guide=1 AND guide_id IS NULL',
            [$bid]
        )->fetchColumn();
        $pay = q('SELECT status FROM payments WHERE booking_id=?', [$bid])->fetch();
        if ($a == 'approve' && (!$pay || $pay['status'] != 'verified')) {
            $_SESSION['flash'] = 'Verify the payment first, then approve the booking.';
        } elseif ($a == 'approve' && $miss) {
            $_SESSION['flash'] = 'Assign a guide to every requested tour before approving.';
        } elseif (
            $a == 'approve' &&
            q(
                'SELECT COUNT(*) FROM booking_items x JOIN packages px ON px.package_id=x.package_id JOIN booking_items y ON y.booking_id=x.booking_id AND y.guide_id=x.guide_id AND y.item_id<>x.item_id JOIN packages py ON py.package_id=y.package_id WHERE x.booking_id=? AND x.guide_id IS NOT NULL AND px.destination_id<>py.destination_id',
                [$bid]
            )->fetchColumn()
        ) {
            $_SESSION['flash'] =
                'The same guide is assigned to different places. Use a different guide for each destination.';
        } else {
            q('UPDATE bookings SET status=? WHERE booking_id=?', [
                $a == 'approve' ? 'approved' : 'rejected',
                $bid
            ]);
            if ($a == 'approve') {
                foreach (
                    q('SELECT item_id FROM booking_items WHERE booking_id=? AND guide_id IS NOT NULL', [$bid])->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    as $iid
                ) {
                    guide_payment_for_item($iid);
                }
            } else {
                q(
                    "DELETE FROM guide_payments WHERE status='pending' AND item_id IN (SELECT item_id FROM booking_items WHERE booking_id=?)",
                    [$bid]
                );
            }
            notify(
                $b['user_id'],
                "Your booking {$b['reference_no']} was " .
                    ($a == 'approve' ? 'approved. See your guide details in My trips.' : 'rejected.')
            );
        }
    }
    if ($a == 'pay') {
        $v = in_array($_POST['v'] ?? '', ['verified', 'rejected']) ? $_POST['v'] : 'pending';
        q('UPDATE payments SET status=? WHERE booking_id=?', [$v, $_POST['b']]);
        $bk = q('SELECT user_id,reference_no FROM bookings WHERE booking_id=?', [$_POST['b']])->fetch();
        if ($bk && $v != 'pending') {
            notify($bk['user_id'], "Your payment for booking {$bk['reference_no']} was " . ($v == 'verified' ? 'verified.' : 'rejected. Please contact us.'));
        }
        $_SESSION['ok'] = 'Payment ' . $v . '.';
    }
    if ($a == 'done') {
        q("UPDATE bookings SET status='completed' WHERE booking_id=?", [$_POST['b']]);
    }
    if ($a == 'delete' || $a == 'purge') {
        $ids =
            $a == 'delete'
                ? [(int) $_POST['b']]
                : q("SELECT booking_id FROM bookings WHERE status IN('rejected','cancelled')")->fetchAll(
                    PDO::FETCH_COLUMN
                );
        foreach ($ids as $id) {
            $f = q('SELECT id_file FROM payments WHERE booking_id=?', [$id])->fetchColumn();
            if ($f && is_file(UPLOAD_DIR . $f)) {
                unlink(UPLOAD_DIR . $f);
            }
            q('DELETE FROM payments WHERE booking_id=?', [$id]);
            q('DELETE FROM guide_payments WHERE item_id IN (SELECT item_id FROM booking_items WHERE booking_id=?)', [$id]);
            q('DELETE FROM booking_items WHERE booking_id=?', [$id]);
            q('DELETE FROM bookings WHERE booking_id=?', [$id]);
        }
        $_SESSION['ok'] = count($ids) . ' booking(s) deleted.';
    }
    if ($a == 'pkg') {
        q(
            'INSERT INTO packages(destination_id,title,itinerary,duration_days,price,inclusions,exclusions,max_participants) VALUES(?,?,?,?,?,?,?,?)',
            [
                $_POST['d'],
                $_POST['title'],
                $_POST['it'],
                $_POST['days'],
                $_POST['price'],
                $_POST['inc'],
                $_POST['exc'],
                $_POST['max']
            ]
        );
    }
    if ($a == 'dest') {
        $lat = trim($_POST['lat'] ?? '');
        $lng = trim($_POST['lng'] ?? '');
        if ($lat === '' || $lng === '') {
            $geo = geocode_place($_POST['name']);
            [$lat, $lng] = $geo ?: [null, null];
        }
        q('INSERT INTO destinations(name,region,description,entrance_fee,latitude,longitude) VALUES(?,?,?,?,?,?)', [
            $_POST['name'],
            $_POST['region'],
            $_POST['desc'],
            $_POST['fee'],
            $lat,
            $lng
        ]);
        $_SESSION['ok'] = $lat === null ? 'Destination added, but no map location was found. Enter latitude and longitude.' : 'Destination added with its map location.';
    }
    if ($a == 'geocodeall') {
        $found = 0;
        foreach (q('SELECT destination_id,name FROM destinations WHERE latitude IS NULL')->fetchAll() as $d) {
            $geo = geocode_place($d['name']);
            if ($geo) {
                q('UPDATE destinations SET latitude=?, longitude=? WHERE destination_id=?', [$geo[0], $geo[1], $d['destination_id']]);
                $found++;
            }
            sleep(1);
        }
        $_SESSION['ok'] = "$found map location(s) found.";
    }
    if ($a == 'guide') {
        q('INSERT INTO guides(full_name,specialization,languages,phone,day_rate) VALUES(?,?,?,?,?)', [
            $_POST['name'],
            $_POST['sp'],
            $_POST['lang'],
            $_POST['ph'],
            max(0, (float) ($_POST['rate'] ?? 1000))
        ]);
        $gid = $pdo->lastInsertId();
        foreach ($_POST['dest'] ?? [] as $dd) {
            q('INSERT IGNORE INTO guide_destinations(guide_id,destination_id) VALUES(?,?)', [
                $gid,
                (int) $dd
            ]);
        }
    }
    if ($a == 'guide' && trim($_POST['username'] ?? '') !== '') {
        if (!password_ok($_POST['password'] ?? '')) {
            $_SESSION['flash'] = 'Guide added, but no login was created: the password needs 8+ characters with upper, lower, number and symbol.';
        } else {
            $acc = create_guide_account($gid, trim($_POST['username']), $_POST['password']);
            $_SESSION['ok'] = 'Guide added. Login: ' . $acc[0];
        }
    }
    if ($a == 'guidelogin') {
        $g = q('SELECT * FROM guides WHERE guide_id=?', [(int) $_POST['g']])->fetch();
        if (!password_ok($_POST['password'] ?? '')) {
            $_SESSION['flash'] = 'Password needs 8+ characters with upper, lower, number and symbol.';
        } elseif ($g && $g['user_id']) {
            q('UPDATE users SET password_hash=? WHERE user_id=?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $g['user_id']]);
            $_SESSION['ok'] = 'Password updated for ' . $g['full_name'] . '.';
        } elseif ($g) {
            $acc = create_guide_account($g['guide_id'], trim($_POST['username'] ?? ''), $_POST['password']);
            $_SESSION['ok'] = 'Login created for ' . $g['full_name'] . ': ' . $acc[0];
        }
    }
    if ($a == 'guideaccounts') {
        $made = [];
        foreach (q('SELECT guide_id FROM guides WHERE user_id IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $gid2) {
            $acc = create_guide_account($gid2);
            $made[] = $acc[0] . ' / ' . $acc[1];
        }
        $_SESSION['ok'] = $made
            ? count($made) . ' login(s) created (username / temporary password): ' . implode(', ', $made)
            : 'Every guide already has a login.';
    }
    if ($a == 'guiderepair') {
        $names = repair_guide_accounts();
        $_SESSION['ok'] = 'Guide logins repaired. Password for all of them: Guide_123. Usernames: ' . implode(', ', $names);
    }
    if ($a == 'guidepaid') {
        $gp = q(
            'SELECT gp.*,g.user_id,t.title,b.reference_no FROM guide_payments gp JOIN guides g ON g.guide_id=gp.guide_id JOIN booking_items i ON i.item_id=gp.item_id JOIN packages t ON t.package_id=i.package_id JOIN bookings b ON b.booking_id=i.booking_id WHERE gp.payment_id=?',
            [(int) $_POST['pid']]
        )->fetch();
        if ($gp && $gp['status'] == 'pending') {
            q("UPDATE guide_payments SET status='paid', paid_at=NOW() WHERE payment_id=?", [$gp['payment_id']]);
            if ($gp['user_id']) {
                notify($gp['user_id'], 'Payment released: ' . php_($gp['amount']) . " for {$gp['title']} (booking {$gp['reference_no']}).");
            }
            $_SESSION['ok'] = 'Marked as paid.';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
