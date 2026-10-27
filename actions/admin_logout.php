<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../dashboard.php');
$u = admin_user();
if ($u) audit($conn, 'admin', $u['username'], 'logout');
logout_session('admin');
flash('success', 'You have been signed out.');
redirect('../dashboard.php');
