<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../vendor.php');
logout_session('vendor');
flash('success', 'You have been signed out.');
redirect('../vendor.php?tab=login');
