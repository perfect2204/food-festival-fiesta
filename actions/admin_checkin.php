<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../checkin.php');
require_admin('../dashboard.php');

$passId = strtoupper(post_str('pass_id'));
if (!valid_pass_id($passId)) {
    flash('error', 'That is not a valid pass ID.');
    redirect('../checkin.php');
}

$stmt = $conn->prepare("SELECT id, name, guests, checked_in, checked_in_at FROM visitors WHERE pass_id = ?");
$stmt->bind_param('s', $passId);
$stmt->execute();
$visitor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visitor) {
    flash('error', "No pass found for $passId.");
    redirect('../checkin.php');
}
if ((int)$visitor['checked_in'] === 1) {
    flash('error', $visitor['name'] . ' already checked in at ' . $visitor['checked_in_at'] . '. This pass cannot be used again.');
    redirect('../checkin.php');
}

// "AND checked_in = 0" makes the update atomic: two scanners cannot both admit the same pass
$stmt = $conn->prepare("UPDATE visitors SET checked_in = 1, checked_in_at = NOW() WHERE id = ? AND checked_in = 0");
$stmt->bind_param('i', $visitor['id']);
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();

if ($changed === 1) {
    $admin = admin_user();
    audit($conn, 'admin', $admin['username'], 'visitor_checked_in', $passId);
    flash('success', 'Welcome in, ' . $visitor['name'] . ' — ' . (int)$visitor['guests'] . ' guest(s).');
} else {
    flash('error', 'This pass was just used at another gate.');
}
redirect('../checkin.php');
