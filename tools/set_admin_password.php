<?php
/**
 * Create or reset an organizer account from the command line.
 * Usage (XAMPP Shell or any terminal):
 *     php tools/set_admin_password.php organizer
 * It asks for the new password, so the password never appears in a file or in the shell history.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require __DIR__ . '/../config/db.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_]{3,60}$/', $username)) {
    exit("Usage: php tools/set_admin_password.php <username>\n");
}
echo "New password for '$username' (min 10 characters): ";
$pw = trim(fgets(STDIN));
if (strlen($pw) < 10) { exit("Password too short.\n"); }

$hash = password_hash($pw, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
$stmt->bind_param('ss', $username, $hash);
$stmt->execute();
echo "Done. '$username' can now sign in with the new password.\n";
