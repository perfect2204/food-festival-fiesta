<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../feedback.php');

$passId  = strtoupper(post_str('pass_id'));
$stallId = filter_var($_POST['stall_id'] ?? '', FILTER_VALIDATE_INT);
$rating  = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$comment = post_str('comment');

$errors = [];
if (!valid_pass_id($passId))      $errors[] = 'Enter a valid pass ID (like FEST-ABCD2345).';
if ($stallId === false)           $errors[] = 'Choose a stall.';
if ($rating === false)            $errors[] = 'Pick a star rating from 1 to 5.';
if (!valid_len($comment, 0, 500)) $errors[] = 'Comments can be at most 500 characters.';
if ($errors) { flash('error', implode(' ', $errors)); redirect('../feedback.php'); }

// The pass must exist — this is what makes every review "verified"
$stmt = $conn->prepare("SELECT id, name FROM visitors WHERE pass_id = ?");
$stmt->bind_param('s', $passId);
$stmt->execute();
$visitor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$visitor) { flash('error', 'We could not find that pass ID.'); redirect('../feedback.php'); }

// Only public (approved) stalls can be reviewed
$stmt = $conn->prepare("SELECT id FROM stalls WHERE id = ? AND status = 'approved'");
$stmt->bind_param('i', $stallId);
$stmt->execute();
$stall = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$stall) { flash('error', 'That stall is not available for reviews.'); redirect('../feedback.php'); }

$displayName = public_name($visitor['name']);
$visitorId   = (int)$visitor['id'];

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO feedback (stall_id, visitor_id, visitor_name, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iisis', $stallId, $visitorId, $displayName, $rating, $comment);
    $stmt->execute();
    $stmt->close();
    recalc_stall_rating($conn, $stallId);
    $conn->commit();
} catch (mysqli_sql_exception $ex) {
    $conn->rollback();
    if ((int)$ex->getCode() === 1062) {
        flash('error', 'This pass has already reviewed that stall.');
    } else {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('../feedback.php');
}

audit($conn, 'visitor', $displayName, 'feedback_submitted', "stall #$stallId, $rating stars");
flash('success', 'Thanks — your review has been recorded.');
redirect('../feedback.php');
