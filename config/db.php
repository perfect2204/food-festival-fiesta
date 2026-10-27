<?php
/**
 * Database connection.
 * Defaults match a fresh XAMPP/WAMP install (root user, empty password).
 * On a live host, either edit the four values below or set the environment
 * variables DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT.
 */
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$DB_NAME = getenv('DB_NAME') ?: 'food_festival_fiesta';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);

// Make every database error throw an exception (same behaviour on every PHP version)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    error_log('DB connection failed: ' . $ex->getMessage());
    http_response_code(500);
    // Never print credentials or server details to the visitor
    die('The service is temporarily unavailable. If you are running this locally, make sure '
      . 'MySQL is started in XAMPP and that database/schema.sql has been imported.');
}
