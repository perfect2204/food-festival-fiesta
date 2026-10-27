<?php
require_once __DIR__ . '/../config/bootstrap.php';
guard_post('../vendor.php');

$stallName = post_str('stall_name');
$cuisine   = post_str('cuisine');
$contact   = post_str('contact_name');
$phone     = post_str('phone');
$username  = post_str('username');
$password  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$desc      = post_str('description');
$menuRaw   = post_str('menu');

$errors = [];
if (!valid_stall_name($stallName))     $errors[] = 'Stall name: 2–80 characters (letters, numbers, spaces, & . , - \').';
if (!in_array($cuisine, CUISINES, true)) $errors[] = 'Choose a cuisine from the list.';
if (!valid_person_name($contact))      $errors[] = 'Enter the owner\'s name (letters only).';
if (!valid_phone($phone))              $errors[] = 'Enter a valid phone number (10–13 digits).';
if (!valid_username($username))        $errors[] = 'Username: 4–30 letters, numbers or underscores.';
if (!valid_password($password))        $errors[] = 'Password: 8+ characters with at least one letter and one number.';
if (!valid_len($desc, 0, 300))         $errors[] = 'Description can be at most 300 characters.';

$items = array_values(array_unique(array_filter(array_map('trim', explode(',', $menuRaw)), 'strlen')));
if (count($items) < 1 || count($items) > 30) $errors[] = 'Add between 1 and 30 menu items, separated by commas.';
foreach ($items as $it) { if (!valid_len($it, 1, 80)) { $errors[] = 'Each menu item can be at most 80 characters.'; break; } }

if ($errors) { flash('error', implode(' ', $errors)); redirect('../vendor.php'); }

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $conn->begin_transaction();

    // Next stall number (locked so two simultaneous sign-ups cannot clash)
    $nextNum = (int)$conn->query("SELECT COALESCE(MAX(stall_num), 0) + 1 AS n FROM stalls FOR UPDATE")->fetch_assoc()['n'];

    $stmt = $conn->prepare("INSERT INTO stalls (stall_num, name, cuisine, description, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param('isss', $nextNum, $stallName, $cuisine, $desc);
    $stmt->execute();
    $stallId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO vendors (stall_id, contact_name, phone, username, password_hash) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $stallId, $contact, $phone, $username, $hash);
    $stmt->execute();
    $vendorId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO menu_items (stall_id, item_name) VALUES (?, ?)");
    foreach ($items as $it) { $stmt->bind_param('is', $stallId, $it); $stmt->execute(); }
    $stmt->close();

    $conn->commit();
} catch (mysqli_sql_exception $ex) {
    $conn->rollback();
    if ((int)$ex->getCode() === 1062) {
        flash('error', 'That username is already taken — please choose another.');
    } else {
        error_log($ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('../vendor.php');
}

audit($conn, 'vendor', $username, 'stall_registered', "$stallName (#$nextNum) pending approval");
login_session('vendor', ['id' => $vendorId, 'stall_id' => $stallId, 'username' => $username]);
flash('success', "Registration received. Your stall #$nextNum goes live once the organizer approves it.");
redirect('../vendor.php');
