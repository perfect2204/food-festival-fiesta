<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin('../dashboard.php');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="food-festival-fiesta-report.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

fputcsv($out, ['STALLS']);
fputcsv($out, ['Stall Number', 'Name', 'Cuisine', 'Status', 'Rating', 'Ratings Count']);
$res = $conn->query("SELECT stall_num, name, cuisine, status, rating, reviews_count FROM stalls ORDER BY stall_num");
while ($r = $res->fetch_assoc()) {
    fputcsv($out, [$r['stall_num'], csv_safe($r['name']), csv_safe($r['cuisine']), $r['status'], $r['rating'], $r['reviews_count']]);
}

fputcsv($out, []);
fputcsv($out, ['ATTENDANCE']);
$v = $conn->query("SELECT COUNT(*) t, COALESCE(SUM(checked_in),0) i, COALESCE(SUM(CASE WHEN checked_in=1 THEN guests ELSE 0 END),0) g FROM visitors")->fetch_assoc();
fputcsv($out, ['Passes issued', 'Checked in', 'Guests inside']);
fputcsv($out, [$v['t'], $v['i'], $v['g']]);

fputcsv($out, []);
fputcsv($out, ['REVIEWS']);
fputcsv($out, ['Stall', 'Reviewer', 'Rating', 'Comment', 'Date']);
$res = $conn->query("SELECT s.name AS stall_name, f.visitor_name, f.rating, f.comment, f.created_at
                     FROM feedback f JOIN stalls s ON f.stall_id = s.id ORDER BY f.created_at DESC, f.id DESC");
while ($r = $res->fetch_assoc()) {
    fputcsv($out, [csv_safe($r['stall_name']), csv_safe($r['visitor_name']), $r['rating'], csv_safe((string)$r['comment']), $r['created_at']]);
}
fclose($out);

$admin = admin_user();
audit($conn, 'admin', $admin['username'], 'report_exported');
