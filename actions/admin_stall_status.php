<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../dashboard.php');
require_admin('../dashboard.php');

$stallId = filter_var($_POST['stall_id'] ?? '', FILTER_VALIDATE_INT);
$status  = post_str('status');
if ($stallId === false || !in_array($status, ['approved', 'rejected'], true)) {
    flash('error', 'Invalid request.');
    redirect('../dashboard.php');
}

$stmt = $conn->prepare("UPDATE stalls SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $stallId);
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();

if ($changed > 0) {
    $admin = admin_user();
    audit($conn, 'admin', $admin['username'], 'stall_' . $status, "stall id $stallId");
    flash('success', 'Stall ' . $status . '.');
} else {
    flash('error', 'No change made.');
}
redirect('../dashboard.php');
