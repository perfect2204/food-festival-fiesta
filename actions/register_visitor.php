<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../visitor.php');

$name   = post_str('name');
$phone  = post_str('phone');
$date   = post_str('visit_date');
$guests = filter_var($_POST['guests'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);

$errors = [];
if (!valid_person_name($name)) $errors[] = 'Enter your full name (letters only).';
if (!valid_phone($phone))      $errors[] = 'Enter a valid phone number (10–13 digits).';
if (!valid_future_date($date)) $errors[] = 'Choose a visit date from today up to one year ahead.';
if ($guests === false)         $errors[] = 'Guests must be between 1 and 10.';
if ($errors) { flash('error', implode(' ', $errors)); redirect('../visitor.php'); }

// Flood protection: limit sign-ups per IP per hour
$ip = client_ip();
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM audit_log WHERE action = 'visitor_registered' AND ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
$stmt->bind_param('s', $ip);
$stmt->execute();
$recent = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
if ($recent >= REGISTRATIONS_PER_HOUR) {
    flash('error', 'Too many registrations from this connection. Please try again later.');
    redirect('../visitor.php');
}

// Insert with a unique, unguessable pass ID (retry on the rare collision)
$passId = null;
for ($attempt = 0; $attempt < 5 && $passId === null; $attempt++) {
    $candidate = generate_pass_id();
    try {
        $stmt = $conn->prepare("INSERT INTO visitors (name, phone, visit_date, guests, pass_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssis', $name, $phone, $date, $guests, $candidate);
        $stmt->execute();
        $stmt->close();
        $passId = $candidate;
    } catch (mysqli_sql_exception $ex) {
        if ((int)$ex->getCode() !== 1062) { error_log($ex->getMessage()); break; }
    }
}
if ($passId === null) {
    flash('error', 'Something went wrong. Please try again.');
    redirect('../visitor.php');
}

audit($conn, 'visitor', $name, 'visitor_registered', $passId);
flash('success', 'Pass generated — show the QR code at the gate.');
redirect('../visitor.php?pass=' . urlencode($passId));
