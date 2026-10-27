<?php
/**
 * config/security.php
 * Shared security layer used by every page and action:
 *  - hardened sessions and HTTP security headers
 *  - CSRF protection, output escaping, input validation
 *  - login rate limiting (brute-force protection) and audit logging
 *  - role guards for organizer and vendor areas
 */

define('SESSION_IDLE_LIMIT', 1800);   // sign out after 30 minutes of inactivity
define('LOGIN_FAILS_PER_USER', 5);    // failed logins per username before lockout
define('LOGIN_FAILS_PER_IP', 15);     // failed logins per IP before lockout
define('LOGIN_WINDOW_MINUTES', 15);   // lockout window
define('REGISTRATIONS_PER_HOUR', 20); // visitor sign-ups allowed per IP per hour
// A throw-away bcrypt hash: keeps login timing identical when a username does not exist
define('DUMMY_HASH', '$2y$10$tcwG30c.DQbCK4X5vNxYH.LGI8rQUQ2VMbliLkHiVsfqH/bDvprOe');

const CUISINES = ['North Indian', 'Indo-Chinese', 'Street Food', 'Desserts',
                  'Punjabi', 'Japanese', 'South Indian', 'Continental'];

/* ---------- Error display: only show details to the local developer ---------- */
$viaProxy = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
$localClient = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && !$viaProxy;
error_reporting(E_ALL);
ini_set('display_errors', $localClient ? '1' : '0');
ini_set('log_errors', '1');

/* ---------- HTTP security headers ---------- */
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com; img-src 'self' data:; "
         . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
}

/* ---------- Hardened session ---------- */
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('FFF_SESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/* ---------- Output + redirect + flash messages ---------- */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url) {
    header('Location: ' . $url);
    exit;
}
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF protection ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
/** Every action file calls this first: POST only, and a valid CSRF token. */
function guard_post(string $backUrl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect($backUrl);
    }
    $sent  = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        error_log('CSRF check failed from ' . client_ip() . ' on ' . ($_SERVER['REQUEST_URI'] ?? ''));
        flash('error', 'Your session expired or the form was invalid. Please try again.');
        redirect($backUrl);
    }
}

/* ---------- Input validation helpers ---------- */
function post_str(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function valid_len(string $s, int $min, int $max): bool {
    $n = mb_strlen($s, 'UTF-8');
    return $n >= $min && $n <= $max;
}
function valid_person_name(string $s): bool {
    return (bool)preg_match("/^[\\p{L}][\\p{L} .'\\-]{1,79}$/u", $s);
}
function valid_stall_name(string $s): bool {
    return (bool)preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} &'.,\\-]{1,79}$/u", $s);
}
function valid_phone(string $p): bool {
    if (!preg_match('/^\\+?[0-9][0-9 \\-]{8,17}$/', $p)) return false;
    $d = strlen(preg_replace('/\\D/', '', $p));
    return $d >= 10 && $d <= 13;
}
function valid_username(string $s): bool {
    return (bool)preg_match('/^[A-Za-z0-9_]{4,30}$/', $s);
}
function valid_password(string $s): bool {
    return strlen($s) >= 8 && strlen($s) <= 72
        && preg_match('/[A-Za-z]/', $s) && preg_match('/[0-9]/', $s);
}
function valid_pass_id(string $s): bool {
    return (bool)preg_match('/^FEST-[A-Z0-9]{8}$/', $s);
}
/** A real calendar date in YYYY-MM-DD form, from today up to one year ahead. */
function valid_future_date(string $s): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) return false;
    $today = new DateTime('today');
    return $d >= $today && $d <= (clone $today)->modify('+1 year');
}
function generate_pass_id(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O/1/I confusion
    $id = '';
    for ($i = 0; $i < 8; $i++) { $id .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
    return 'FEST-' . $id;
}
function public_name(string $full): string {          // "Simran Kaur" -> "Simran K."
    $parts = preg_split('/\\s+/', trim($full));
    if (count($parts) < 2) return $parts[0];
    return $parts[0] . ' ' . mb_substr(end($parts), 0, 1, 'UTF-8') . '.';
}
function stars(float $r): string {
    $full = max(0, min(5, (int)round($r)));
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}
/** Stops spreadsheet formula injection (=, +, -, @) in CSV exports. */
function csv_safe($v) {
    if (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
        return "'" . $v;
    }
    return $v;
}
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // X-Forwarded-For is only believed when the connection comes from a proxy on this same machine
    // (a local tunnel agent). From the open internet it could be forged, so it is ignored.
    if (in_array($remote, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return $remote;
}

/* ---------- Audit log + brute-force protection ---------- */
function audit(mysqli $conn, string $actorType, string $actorName, string $action, string $details = ''): void {
    $actorName = mb_substr($actorName, 0, 80, 'UTF-8');
    $details   = mb_substr($details, 0, 255, 'UTF-8');
    $ip = client_ip();
    $stmt = $conn->prepare('INSERT INTO audit_log (actor_type, actor_name, action, details, ip) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $actorType, $actorName, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
function login_locked(mysqli $conn, string $scope, string $username): bool {
    $ip = client_ip();
    $username = mb_substr($username, 0, 60, 'UTF-8');
    $sql = 'SELECT SUM(username = ?) AS by_user, SUM(ip = ?) AS by_ip FROM login_attempts '
          . 'WHERE scope = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $username, $ip, $scope);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['by_user'] >= LOGIN_FAILS_PER_USER || (int)$row['by_ip'] >= LOGIN_FAILS_PER_IP;
}
function record_login_attempt(mysqli $conn, string $scope, string $username, bool $success): void {
    $ip = client_ip();
    $username = mb_substr($username, 0, 60, 'UTF-8');
    $ok = $success ? 1 : 0;
    $stmt = $conn->prepare('INSERT INTO login_attempts (scope, username, ip, success) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $scope, $username, $ip, $ok);
    $stmt->execute();
    $stmt->close();
    if ($success) {   // a good login clears earlier failures for that account
        $del = $conn->prepare('DELETE FROM login_attempts WHERE scope = ? AND username = ? AND success = 0');
        $del->bind_param('ss', $scope, $username);
        $del->execute();
        $del->close();
    }
}

/* ---------- Roles and guards ---------- */
function admin_user(): ?array  { return $_SESSION['admin']  ?? null; }
function vendor_user(): ?array { return $_SESSION['vendor'] ?? null; }

function login_session(string $role, array $data): void {
    session_regenerate_id(true);          // defeats session fixation
    unset($_SESSION['csrf']);             // fresh CSRF token after privilege change
    $_SESSION[$role] = $data;
    $_SESSION['last_activity'] = time();
}
function logout_session(string $role): void {
    unset($_SESSION[$role]);
    session_regenerate_id(true);
}
function require_admin(string $backUrl): void {
    if (!admin_user()) {
        flash('error', 'Organizer sign-in required.');
        redirect($backUrl);
    }
}
function require_vendor(string $backUrl): void {
    if (!vendor_user()) {
        flash('error', 'Please sign in to your vendor account first.');
        redirect($backUrl);
    }
}
/** Recalculate a stall's rating: previous-edition baseline + every verified review. */
function recalc_stall_rating(mysqli $conn, int $stallId): void {
    $sql = 'UPDATE stalls s '
          . 'LEFT JOIN (SELECT stall_id, COUNT(*) AS c, SUM(rating) AS t FROM feedback GROUP BY stall_id) f '
          . '  ON f.stall_id = s.id '
          . 'SET s.reviews_count = s.seed_reviews + COALESCE(f.c, 0), '
          . '    s.rating = CASE WHEN s.seed_reviews + COALESCE(f.c, 0) = 0 THEN 0 '
          . '               ELSE ROUND((s.seed_rating * s.seed_reviews + COALESCE(f.t, 0)) '
          . '                          / (s.seed_reviews + COALESCE(f.c, 0)), 2) END '
          . 'WHERE s.id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $stallId);
    $stmt->execute();
    $stmt->close();
}

/* ---------- Idle timeout for signed-in users ---------- */
if (!empty($_SESSION['admin']) || !empty($_SESSION['vendor'])) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_LIMIT) {
        unset($_SESSION['admin'], $_SESSION['vendor']);
        flash('error', 'You were signed out after 30 minutes of inactivity.');
    }
    $_SESSION['last_activity'] = time();
}
