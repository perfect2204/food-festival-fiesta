<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../vendor.php');
require_vendor('../vendor.php?tab=login');

$vendor   = vendor_user();
$stallId  = (int)$vendor['stall_id'];          // ownership comes from the session, never from the form
$itemName = post_str('item_name');

$stmt = $conn->prepare("SELECT status FROM stalls WHERE id = ?");
$stmt->bind_param('i', $stallId);
$stmt->execute();
$stall = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$stall || $stall['status'] === 'rejected') {
    flash('error', 'This stall can no longer be edited.');
    redirect('../vendor.php');
}
if (!valid_len($itemName, 1, 80)) {
    flash('error', 'Enter a dish name (up to 80 characters).');
    redirect('../vendor.php');
}
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM menu_items WHERE stall_id = ?");
$stmt->bind_param('i', $stallId);
$stmt->execute();
$count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
if ($count >= 40) {
    flash('error', 'A menu can have at most 40 items.');
    redirect('../vendor.php');
}

$stmt = $conn->prepare("INSERT INTO menu_items (stall_id, item_name) VALUES (?, ?)");
$stmt->bind_param('is', $stallId, $itemName);
$stmt->execute();
$stmt->close();

audit($conn, 'vendor', $vendor['username'], 'menu_item_added', $itemName);
flash('success', "\"$itemName\" added to your menu.");
redirect('../vendor.php');
