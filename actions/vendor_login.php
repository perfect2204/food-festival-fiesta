<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../vendor.php?tab=login');

$username = post_str('username');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if (login_locked($conn, 'vendor', $username)) {
    audit($conn, 'vendor', $username, 'login_blocked', 'too many failed attempts');
    flash('error', 'Too many failed attempts. Please try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.');
    redirect('../vendor.php?tab=login');
}

$stmt = $conn->prepare("SELECT id, stall_id, username, password_hash FROM vendors WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Always run password_verify so response time does not reveal whether the username exists
$ok = password_verify(strlen($password) <= 200 ? $password : '', $row['password_hash'] ?? DUMMY_HASH) && $row;

record_login_attempt($conn, 'vendor', $username, (bool)$ok);
if ($ok) {
    audit($conn, 'vendor', $username, 'login_success');
    login_session('vendor', ['id' => (int)$row['id'], 'stall_id' => (int)$row['stall_id'], 'username' => $row['username']]);
    flash('success', 'Welcome back.');
    redirect('../vendor.php');
}
audit($conn, 'vendor', $username, 'login_failed');
flash('error', 'Incorrect username or password.');
redirect('../vendor.php?tab=login');
