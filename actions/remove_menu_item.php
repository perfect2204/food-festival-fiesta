<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../vendor.php');
require_vendor('../vendor.php?tab=login');

$vendor  = vendor_user();
$stallId = (int)$vendor['stall_id'];
$itemId  = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT);

if ($itemId === false) { flash('error', 'Invalid menu item.'); redirect('../vendor.php'); }

// "AND stall_id = ?" means a vendor can only ever delete items on THEIR OWN stall
$stmt = $conn->prepare("DELETE m FROM menu_items m JOIN stalls s ON s.id = m.stall_id
                        WHERE m.id = ? AND m.stall_id = ? AND s.status <> 'rejected'");
$stmt->bind_param('ii', $itemId, $stallId);
$stmt->execute();
$removed = $stmt->affected_rows;
$stmt->close();

if ($removed > 0) {
    audit($conn, 'vendor', $vendor['username'], 'menu_item_removed', "item #$itemId");
    flash('success', 'Menu item removed.');
} else {
    flash('error', 'That item is not on your menu.');
}
redirect('../vendor.php');
