<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../dashboard.php');

$username = post_str('username');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if (login_locked($conn, 'admin', $username)) {
    audit($conn, 'admin', $username, 'login_blocked', 'too many failed attempts');
    flash('error', 'Too many failed attempts. Please try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.');
    redirect('../dashboard.php');
}

$stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ok = password_verify(strlen($password) <= 200 ? $password : '', $row['password_hash'] ?? DUMMY_HASH) && $row;

record_login_attempt($conn, 'admin', $username, (bool)$ok);
if ($ok) {
    audit($conn, 'admin', $username, 'login_success');
    login_session('admin', ['id' => (int)$row['id'], 'username' => $row['username']]);
    redirect('../dashboard.php');
}
audit($conn, 'admin', $username, 'login_failed');
flash('error', 'Incorrect username or password.');
redirect('../dashboard.php');
