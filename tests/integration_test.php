<?php
/**
 * Food Festival Fiesta — automated functional + security test suite.
 *
 * Run from a terminal (XAMPP Shell works) on a FRESH database import:
 *     php tests/integration_test.php http://localhost/food-festival-fiesta
 *
 * It signs in as a visitor, vendor and organizer exactly like a browser would,
 * tries normal use AND attacks (CSRF, SQL injection, XSS, tampering, brute force),
 * then reports PASS / FAIL for every case.
 */
if (PHP_SAPI !== 'cli') { exit('Command line only.'); }
require __DIR__ . '/../config/db.php';   // gives us $conn so tests can verify the database directly

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$results = [];
$failures = 0;

class Client {
    public $jar; public $base;
    function __construct($base) { $this->base = $base; $this->jar = tempnam(sys_get_temp_dir(), 'ffjar'); }
    function req($method, $path, $data = null, $follow = true) {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
        ]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? [])); }
        $raw = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $headers = substr($raw, 0, $size);
        // with redirects there are several header blocks; keep the last one for header checks
        $blocks = preg_split("/\r\n\r\n/", trim($headers));
        return ['code' => $code, 'headers' => end($blocks), 'all_headers' => $headers, 'body' => substr($raw, $size)];
    }
    function get($p, $follow = true) { return $this->req('GET', $p, null, $follow); }
    function token($page = '/index.php') {
        $r = $this->get($page);
        return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m) ? $m[1] : '';
    }
    /** POST with a fresh CSRF token (taken from $tokenPage) unless $token === false */
    function post($path, $data, $tokenPage = '/index.php', $token = null) {
        if ($token !== false) { $data['csrf_token'] = $token ?? $this->token($tokenPage); }
        return $this->req('POST', $path, $data);
    }
    function cookie($name) {
        $txt = @file_get_contents($this->jar);
        return preg_match('/' . $name . '\s+(\S+)/', $txt, $m) ? $m[1] : '';
    }
}

function check($id, $desc, $ok, $detail = '') {
    global $results, $failures;
    $results[] = [$id, $desc, $ok];
    if (!$ok) $failures++;
    printf("[%s] %-5s %s%s\n", $ok ? 'PASS' : 'FAIL', $id, $desc, (!$ok && $detail) ? "  -> $detail" : '');
}
function flash_of($html) {
    return preg_match('/id="toast" data-flash="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
}
function one($sql) { global $conn; $r = $conn->query($sql)->fetch_row(); return $r ? $r[0] : null; }
function row($sql) { global $conn; return $conn->query($sql)->fetch_assoc(); }
function esc($s) { global $conn; return $conn->real_escape_string($s); }
function section($t) { echo "\n--- $t ---\n"; }

$tag = substr(md5(uniqid('', true)), 0, 6);
$future = date('Y-m-d', strtotime('+10 days'));
$anon = new Client($base); $admin = new Client($base); $vend = new Client($base); $visit = new Client($base);

/* ============ A. Public pages ============ */
section('A. Public pages');
$r = $anon->get('/index.php');
check('A1', 'Home page loads with live statistics', $r['code'] === 200 && strpos($r['body'], 'Active stalls') !== false);
$approved = (int)one("SELECT COUNT(*) FROM stalls WHERE status='approved'");
$r = $anon->get('/stalls.php');
check('A2', 'Stall directory shows every approved stall', substr_count($r['body'], 'class="ticket"') === $approved, 'cards=' . substr_count($r['body'], 'class="ticket"') . " db=$approved");
$r = $anon->get('/stalls.php?cuisine=Punjabi');
check('A3', 'Cuisine filter works', strpos($r['body'], 'Punjab Da Tadka') !== false && strpos($r['body'], 'Sushi Junction') === false);
$r = $anon->get('/stalls.php?cuisine=' . urlencode("' OR 1=1 -- "));
check('A4', 'SQL injection in cuisine filter is harmless', $r['code'] === 200 && stripos($r['body'], 'sql') === false && substr_count($r['body'], 'class="ticket"') === 0);

/* ============ B. Visitor registration & pass ============ */
section('B. Visitor registration and pass');
$before = (int)one("SELECT COUNT(*) FROM visitors");
$r = $visit->post('/actions/register_visitor.php', ['name' => 'Test Visitor', 'phone' => '+91 98765 43210', 'visit_date' => $future, 'guests' => 2], '/visitor.php');
preg_match('/data-qr-payload="(FEST-[A-Z0-9]{8})"/', $r['body'], $m); $passA = $m[1] ?? '';
check('B1', 'Valid registration creates a pass and shows it', $passA !== '' && (int)one("SELECT COUNT(*) FROM visitors") === $before + 1, "pass=$passA");
check('B2', 'QR code payload on the pass is only the pass ID', strpos($r['body'], 'data-qr-payload="' . $passA . '"') !== false);

$n = (int)one("SELECT COUNT(*) FROM visitors");
$bad = [
  'B3' => ['Invalid phone number rejected',  ['name' => 'Test Visitor', 'phone' => 'abc', 'visit_date' => $future, 'guests' => 1]],
  'B4' => ['Past visit date rejected',       ['name' => 'Test Visitor', 'phone' => '9876543210', 'visit_date' => '2020-01-01', 'guests' => 1]],
  'B5' => ['Guest count above limit rejected', ['name' => 'Test Visitor', 'phone' => '9876543210', 'visit_date' => $future, 'guests' => 99]],
  'B6' => ['Script tag in name rejected',    ['name' => '<script>alert(1)</script>', 'phone' => '9876543210', 'visit_date' => $future, 'guests' => 1]],
  'B7' => ['Impossible date (31 Feb) rejected', ['name' => 'Test Visitor', 'phone' => '9876543210', 'visit_date' => date('Y') + 1 . '-02-31', 'guests' => 1]],
];
foreach ($bad as $id => [$desc, $data]) {
    $r = $visit->post('/actions/register_visitor.php', $data, '/visitor.php');
    check($id, $desc, (int)one("SELECT COUNT(*) FROM visitors") === $n && flash_of($r['body']) !== '', flash_of($r['body']));
}
$r = $visit->req('POST', '/actions/register_visitor.php', ['name' => ['a'], 'phone' => ['b'], 'visit_date' => ['c'], 'guests' => ['d'], 'csrf_token' => $visit->token('/visitor.php')]);
check('B8', 'Array-typed (malformed) input is handled without errors', stripos($r['body'], 'warning') === false && stripos($r['body'], 'fatal') === false && (int)one("SELECT COUNT(*) FROM visitors") === $n);
$r = $anon->get('/visitor.php?pass=FEST-ZZZZZZZZ');
check('B9', 'Unknown pass ID shows "not found"', strpos(flash_of($r['body']), 'No pass found') !== false);
$r = $anon->get('/visitor.php?pass=' . urlencode("' OR '1'='1"));
check('B10', 'SQL injection in pass lookup leaks nothing', strpos($r['body'], 'Demo Visitor') === false && strpos($r['body'], 'pass-card') === false);

/* ============ C. Vendor accounts ============ */
section('C. Vendor registration and stall management');
$vuser = "vendor_$tag";
$vdata = ['stall_name' => "Test Stall $tag", 'cuisine' => 'Desserts', 'contact_name' => 'Test Owner', 'phone' => '9876500099',
          'username' => $vuser, 'password' => 'Secret123', 'menu' => 'Brownie, Waffle', 'description' => 'A test stall'];
$r = $vend->post('/actions/register_vendor.php', $vdata, '/vendor.php');
$stall = row("SELECT s.id, s.status, s.stall_num FROM stalls s JOIN vendors v ON v.stall_id = s.id WHERE v.username = '" . esc($vuser) . "'");
$sid = (int)($stall['id'] ?? 0);
check('C1', 'Vendor registration creates a PENDING stall', $stall && $stall['status'] === 'pending');
check('C2', 'New vendor is signed in and sees the approval notice', strpos($r['body'], 'Awaiting organizer approval') !== false);
$r = $anon->get('/stalls.php');
check('C3', 'Pending stall is NOT visible to the public', strpos($r['body'], "Test Stall $tag") === false);
check('C4', 'Vendor password is stored as a bcrypt hash', strpos((string)one("SELECT password_hash FROM vendors WHERE username='" . esc($vuser) . "'"), '$2y$') === 0);

$v2 = new Client($base);
$v2->post('/actions/register_vendor.php', array_merge($vdata, ['stall_name' => "Other Stall $tag"]), '/vendor.php');
check('C5', 'Duplicate vendor username rejected', (int)one("SELECT COUNT(*) FROM vendors WHERE username='" . esc($vuser) . "'") === 1);
$cnt = (int)one("SELECT COUNT(*) FROM stalls");
$r = $v2->post('/actions/register_vendor.php', array_merge($vdata, ['username' => "weak_$tag", 'password' => 'password']), '/vendor.php');
check('C6', 'Weak password (no number) rejected', (int)one("SELECT COUNT(*) FROM stalls") === $cnt && stripos(flash_of($r['body']), 'Password') !== false);
$r = $v2->post('/actions/register_vendor.php', array_merge($vdata, ['username' => "bad_$tag", 'cuisine' => 'Hacker Food']), '/vendor.php');
check('C7', 'Cuisine outside the allowed list rejected', (int)one("SELECT COUNT(*) FROM stalls") === $cnt);
$r = $v2->post('/actions/register_vendor.php', array_merge($vdata, ['username' => "bad2_$tag", 'stall_name' => '<script>x</script>']), '/vendor.php');
check('C8', 'Script tag in stall name rejected', (int)one("SELECT COUNT(*) FROM stalls") === $cnt);

$xss = '<img src=x onerror=alert(1)>';
$r = $vend->post('/actions/add_menu_item.php', ['item_name' => $xss], '/vendor.php');
check('C9', 'Vendor can add a menu item to their own stall', (int)one("SELECT COUNT(*) FROM menu_items WHERE stall_id=$sid AND item_name='" . esc($xss) . "'") === 1);
check('C10', 'Stored XSS in a menu item is escaped on output', strpos($r['body'], '&lt;img src=x') !== false && strpos($r['body'], '<img src=x') === false);

$otherItem = (int)one("SELECT m.id FROM menu_items m JOIN stalls s ON s.id=m.stall_id WHERE s.stall_num=7 LIMIT 1");
$vend->post('/actions/remove_menu_item.php', ['item_id' => $otherItem], '/vendor.php');
check('C11', 'Vendor CANNOT delete another stall\'s menu item (IDOR)', (int)one("SELECT COUNT(*) FROM menu_items WHERE id=$otherItem") === 1);
$vend->post('/actions/add_menu_item.php', ['item_name' => "Tampered_$tag", 'stall_id' => 1], '/vendor.php');
check('C12', 'Vendor CANNOT add items to another stall by editing stall_id', (int)one("SELECT COUNT(*) FROM menu_items WHERE stall_id=1 AND item_name='Tampered_$tag'") === 0
      && (int)one("SELECT COUNT(*) FROM menu_items WHERE stall_id=$sid AND item_name='Tampered_$tag'") === 1);
$mine = (int)one("SELECT id FROM menu_items WHERE stall_id=$sid AND item_name='Brownie'");
$vend->post('/actions/remove_menu_item.php', ['item_id' => $mine], '/vendor.php');
check('C13', 'Vendor can remove their own menu item', (int)one("SELECT COUNT(*) FROM menu_items WHERE id=$mine") === 0);

$before = (int)one("SELECT COUNT(*) FROM menu_items");
$anon->post('/actions/add_menu_item.php', ['item_name' => "Anon_$tag"], '/vendor.php');
check('C14', 'Signed-out user cannot add menu items', (int)one("SELECT COUNT(*) FROM menu_items") === $before);

$vend->post('/actions/vendor_logout.php', [], '/vendor.php');
$r = $vend->get('/vendor.php');
check('C15', 'After sign-out the vendor sees the login form again', strpos($r['body'], 'Vendor sign in') !== false && strpos($r['body'], 'Manage your stall') === false);
$r = $vend->post('/actions/vendor_login.php', ['username' => $vuser, 'password' => 'WrongPass1'], '/vendor.php');
check('C16', 'Wrong vendor password gives a generic error', flash_of($r['body']) === 'Incorrect username or password.');
$r = $vend->post('/actions/vendor_login.php', ['username' => 'nobody_' . $tag, 'password' => 'WrongPass1'], '/vendor.php');
check('C17', 'Unknown username gives the SAME message (no user enumeration)', flash_of($r['body']) === 'Incorrect username or password.');
$r = $vend->post('/actions/vendor_login.php', ['username' => $vuser, 'password' => 'Secret123'], '/vendor.php');
check('C18', 'Correct vendor credentials sign in', strpos($r['body'], 'Manage your stall') !== false);
$r = (new Client($base))->post('/actions/vendor_login.php', ['username' => "' OR '1'='1", 'password' => "' OR '1'='1"], '/vendor.php');
check('C19', 'SQL injection on vendor login fails', strpos($r['body'], 'Manage your stall') === false);

/* ============ D. Organizer ============ */
section('D. Organizer sign-in, approvals, reports');
$conn->query("DELETE FROM login_attempts");
$r = $admin->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => 'wrong-password'], '/dashboard.php');
check('D1', 'Wrong organizer password rejected', flash_of($r['body']) === 'Incorrect username or password.' && strpos($r['body'], 'Festival at a glance') === false);
foreach (["admin' OR '1'='1", "organizer' -- ", "' OR 1=1 #"] as $i => $payload) {
    $r = $admin->post('/actions/admin_login.php', ['username' => $payload, 'password' => $payload], '/dashboard.php');
    $okSqli = strpos($r['body'], 'Festival at a glance') === false;
    if (!$okSqli) break;
}
check('D2', 'SQL injection on organizer login fails (3 payloads)', $okSqli);
$r = $anon->get('/dashboard.php');
check('D3', 'Signed-out visitors see only the login form, no statistics', strpos($r['body'], 'Festival at a glance') === false && strpos($r['body'], 'Sign in to manage') !== false);
$r = $anon->get('/checkin.php');
check('D4', 'Check-in page redirects signed-out users to the login', strpos($r['body'], 'Sign in to manage') !== false && strpos($r['body'], 'Scan or enter') === false);
$st = one("SELECT status FROM stalls WHERE id=$sid");
$anon->post('/actions/admin_stall_status.php', ['stall_id' => $sid, 'status' => 'approved'], '/dashboard.php');
check('D5', 'Signed-out user cannot approve a stall (even with a valid CSRF token)', one("SELECT status FROM stalls WHERE id=$sid") === $st);
$cookieBefore = $admin->cookie('FFF_SESSID');
$r = $admin->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => 'organizer2026'], '/dashboard.php');
check('D6', 'Correct organizer credentials open the dashboard', strpos($r['body'], 'Festival at a glance') !== false);
check('D7', 'Session ID changes after login (session-fixation defence)', $cookieBefore !== '' && $admin->cookie('FFF_SESSID') !== '' && $cookieBefore !== $admin->cookie('FFF_SESSID'));
check('D8', 'Pending vendor application is listed for the organizer', strpos($r['body'], "Test Stall $tag") !== false);
$admin->post('/actions/admin_stall_status.php', ['stall_id' => $sid, 'status' => 'approved'], '/dashboard.php');
$r = $anon->get('/stalls.php');
check('D9', 'Approved stall becomes publicly visible', one("SELECT status FROM stalls WHERE id=$sid") === 'approved' && strpos($r['body'], "Test Stall $tag") !== false);
$admin->post('/actions/admin_stall_status.php', ['stall_id' => $sid, 'status' => 'rejected'], '/dashboard.php');
$r = $anon->get('/stalls.php');
check('D10', 'Rejected stall disappears from the public directory', strpos($r['body'], "Test Stall $tag") === false);
$vend->post('/actions/add_menu_item.php', ['item_name' => "AfterReject_$tag"], '/vendor.php');
check('D11', 'Vendor of a rejected stall can no longer edit the menu', (int)one("SELECT COUNT(*) FROM menu_items WHERE item_name='AfterReject_$tag'") === 0);
$admin->post('/actions/admin_stall_status.php', ['stall_id' => $sid, 'status' => 'bogus'], '/dashboard.php');
check('D12', 'Invalid status value is refused', one("SELECT status FROM stalls WHERE id=$sid") === 'rejected');

/* ============ E. Verified reviews ============ */
section('E. Verified feedback and rating maths');
$wok = (int)one("SELECT id FROM stalls WHERE stall_num=12");
$r = $visit->post('/actions/submit_feedback.php', ['pass_id' => $passA, 'stall_id' => $wok, 'rating' => 5, 'comment' => 'Great noodles'], '/feedback.php');
$fb = (int)one("SELECT COUNT(*) FROM feedback WHERE stall_id=$wok AND visitor_id=(SELECT id FROM visitors WHERE pass_id='$passA')");
check('E1', 'Visitor with a valid pass can review a stall', $fb === 1, flash_of($r['body']));
$s = row("SELECT seed_rating, seed_reviews, rating, reviews_count FROM stalls WHERE id=$wok");
$agg = row("SELECT COUNT(*) c, COALESCE(SUM(rating),0) t FROM feedback WHERE stall_id=$wok");
$expected = round(($s['seed_rating'] * $s['seed_reviews'] + $agg['t']) / ($s['seed_reviews'] + $agg['c']), 2);
check('E2', 'Stall rating is recalculated as baseline + verified reviews', abs($expected - $s['rating']) < 0.006 && (int)$s['reviews_count'] === $s['seed_reviews'] + $agg['c'], "expected=$expected stored={$s['rating']}");
$r = $visit->post('/actions/submit_feedback.php', ['pass_id' => $passA, 'stall_id' => $wok, 'rating' => 1, 'comment' => 'Trying again'], '/feedback.php');
check('E3', 'Same pass cannot review the same stall twice', (int)one("SELECT COUNT(*) FROM feedback WHERE stall_id=$wok AND visitor_id=(SELECT id FROM visitors WHERE pass_id='$passA')") === 1 && stripos(flash_of($r['body']), 'already') !== false);
$r = $visit->post('/actions/submit_feedback.php', ['pass_id' => 'FEST-ZZZZZZZZ', 'stall_id' => $wok, 'rating' => 5, 'comment' => 'x'], '/feedback.php');
check('E4', 'Unknown pass ID cannot leave a review', stripos(flash_of($r['body']), 'could not find') !== false);
$c0 = (int)one("SELECT COUNT(*) FROM feedback");
foreach ([6, 0, -1, 'abc'] as $bad) { $visit->post('/actions/submit_feedback.php', ['pass_id' => 'FEST-DEMO0001', 'stall_id' => $wok, 'rating' => $bad, 'comment' => 'x'], '/feedback.php'); }
check('E5', 'Out-of-range or non-numeric ratings rejected (6, 0, -1, "abc")', (int)one("SELECT COUNT(*) FROM feedback") === $c0);
$visit->post('/actions/submit_feedback.php', ['pass_id' => 'FEST-DEMO0001', 'stall_id' => $sid, 'rating' => 5, 'comment' => 'x'], '/feedback.php');
check('E6', 'Reviews for a non-public (rejected) stall refused', (int)one("SELECT COUNT(*) FROM feedback WHERE stall_id=$sid") === 0);
$visit->post('/actions/submit_feedback.php', ['pass_id' => 'FEST-DEMO0001', 'stall_id' => $wok, 'rating' => 4, 'comment' => str_repeat('a', 501)], '/feedback.php');
check('E7', 'Comment longer than 500 characters rejected', (int)one("SELECT COUNT(*) FROM feedback") === $c0);
$r = $visit->post('/actions/register_visitor.php', ['name' => 'Third Visitor', 'phone' => '9876543213', 'visit_date' => $future, 'guests' => 1], '/visitor.php');
preg_match('/data-qr-payload="(FEST-[A-Z0-9]{8})"/', $r['body'], $m); $passC = $m[1] ?? '';
$r = $visit->post('/actions/submit_feedback.php', ['pass_id' => $passC, 'stall_id' => $wok, 'rating' => 4, 'comment' => '=HYPERLINK("http://evil") <script>alert(1)</script>'], '/feedback.php');
check('E8', 'Second visitor can review the same stall', (int)one("SELECT COUNT(*) FROM feedback WHERE stall_id=$wok") === $agg['c'] + 1);
check('E9', 'Stored XSS in a review comment is escaped on output', strpos($r['body'], '&lt;script&gt;alert(1)') !== false && strpos($r['body'], '<script>alert(1)') === false);

/* ============ F. Gate check-in ============ */
section('F. Gate check-in');
$r = $admin->post('/actions/admin_checkin.php', ['pass_id' => $passA], '/checkin.php');
check('F1', 'Organizer can check a valid pass in', (int)one("SELECT checked_in FROM visitors WHERE pass_id='$passA'") === 1 && strpos(flash_of($r['body']), 'Welcome in') !== false);
$r = $admin->post('/actions/admin_checkin.php', ['pass_id' => $passA], '/checkin.php');
check('F2', 'The same pass cannot be used twice', stripos(flash_of($r['body']), 'already checked in') !== false);
$r = $admin->post('/actions/admin_checkin.php', ['pass_id' => 'FEST-ZZZZZZZZ'], '/checkin.php');
check('F3', 'Unknown pass is refused at the gate', stripos(flash_of($r['body']), 'No pass found') !== false);
$r = $admin->post('/actions/admin_checkin.php', ['pass_id' => "'; DROP TABLE visitors; --"], '/checkin.php');
check('F4', 'SQL injection in the check-in box is refused and tables survive', (int)one("SELECT COUNT(*) FROM visitors") > 0 && stripos(flash_of($r['body']), 'valid pass') !== false);
$r = $visit->post('/actions/register_visitor.php', ['name' => 'Second Visitor', 'phone' => '9876543212', 'visit_date' => $future, 'guests' => 1], '/visitor.php');
preg_match('/data-qr-payload="(FEST-[A-Z0-9]{8})"/', $r['body'], $m); $passB = $m[1] ?? '';
$anon->post('/actions/admin_checkin.php', ['pass_id' => $passB], '/checkin.php');
check('F5', 'Signed-out user cannot check anyone in', (int)one("SELECT checked_in FROM visitors WHERE pass_id='$passB'") === 0);

/* ============ G. Reports ============ */
section('G. Reports');
$r = $anon->get('/actions/export_report.php');
check('G1', 'CSV report is not available to signed-out users', strpos($r['body'], 'STALLS') === false);
$r = $admin->get('/actions/export_report.php');
check('G2', 'Organizer can download the CSV report', strpos($r['headers'], 'text/csv') !== false && strpos($r['body'], 'STALLS') !== false && strpos($r['body'], 'ATTENDANCE') !== false);
check('G3', 'CSV formula injection is neutralised (cell starting with = gets a leading quote)', strpos($r['body'], "'=HYPERLINK") !== false && strpos($r['body'], ',=HYPERLINK') === false);

/* ============ H. Security controls ============ */
section('H. Security controls');
$vend2 = new Client($base); $vend2->post('/actions/vendor_login.php', ['username' => $vuser, 'password' => 'Secret123'], '/vendor.php');
$n1 = (int)one("SELECT COUNT(*) FROM visitors"); $n2 = (int)one("SELECT COUNT(*) FROM feedback"); $n3 = (int)one("SELECT COUNT(*) FROM stalls"); $n4 = (int)one("SELECT COUNT(*) FROM menu_items");
$visit->post('/actions/register_visitor.php', ['name' => 'No Token', 'phone' => '9876543210', 'visit_date' => $future, 'guests' => 1], '/visitor.php', false);
$visit->post('/actions/submit_feedback.php', ['pass_id' => 'FEST-DEMO0001', 'stall_id' => $wok, 'rating' => 5, 'comment' => 'x'], '/feedback.php', false);
(new Client($base))->post('/actions/register_vendor.php', array_merge($vdata, ['username' => "csrf_$tag"]), '/vendor.php', false);
$vend2->post('/actions/add_menu_item.php', ['item_name' => "NoToken_$tag"], '/vendor.php', false);
check('H1', 'CSRF: forms without a token change nothing (visitor, feedback, vendor, menu)',
      (int)one("SELECT COUNT(*) FROM visitors") === $n1 && (int)one("SELECT COUNT(*) FROM feedback") === $n2 && (int)one("SELECT COUNT(*) FROM stalls") === $n3 && (int)one("SELECT COUNT(*) FROM menu_items") === $n4);
$before = one("SELECT status FROM stalls WHERE id=$sid");
$admin->post('/actions/admin_stall_status.php', ['stall_id' => $sid, 'status' => 'approved'], '/dashboard.php', false);
check('H2', 'CSRF: organizer approval without a token is refused', one("SELECT status FROM stalls WHERE id=$sid") === $before);
$fresh = new Client($base);
$r = $fresh->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => 'organizer2026'], '/dashboard.php', false);
check('H3', 'CSRF: organizer login without a token is refused', strpos($r['body'], 'Festival at a glance') === false);
$foreignToken = $visit->token('/visitor.php');
$r = $fresh->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => 'organizer2026'], '/dashboard.php', $foreignToken);
check('H4', 'CSRF: a token stolen from another session is refused', strpos($r['body'], 'Festival at a glance') === false);
$before = one("SELECT status FROM stalls WHERE id=$sid");
$admin->req('GET', '/actions/admin_stall_status.php?stall_id=' . $sid . '&status=approved');
check('H5', 'State-changing endpoints ignore GET requests', one("SELECT status FROM stalls WHERE id=$sid") === $before);
$r = $anon->get('/index.php');
check('H6', 'Security headers present (X-Frame-Options, nosniff, CSP, Referrer-Policy)',
      stripos($r['headers'], 'X-Frame-Options: DENY') !== false && stripos($r['headers'], 'X-Content-Type-Options: nosniff') !== false
      && stripos($r['headers'], 'Content-Security-Policy') !== false && stripos($r['headers'], 'Referrer-Policy') !== false);
$raw = (new Client($base))->get('/index.php', false);
check('H7', 'Session cookie is HttpOnly and SameSite', preg_match('/Set-Cookie: FFF_SESSID=[^;]+;.*HttpOnly/i', $raw['headers']) && stripos($raw['headers'], 'SameSite=Lax') !== false);
check('H8', 'Staff pages are never served with a stored password in the HTML', strpos($admin->get('/dashboard.php')['body'], 'organizer2026') === false);

$conn->query("DELETE FROM login_attempts");
$bf = new Client($base);
for ($i = 0; $i < 5; $i++) { $bf->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => "guess$i"], '/dashboard.php'); }
$r = $bf->post('/actions/admin_login.php', ['username' => 'organizer', 'password' => 'organizer2026'], '/dashboard.php');
check('H9', 'Brute force: after 5 wrong passwords even the CORRECT password is blocked', strpos(flash_of($r['body']), 'Too many failed attempts') !== false && strpos($r['body'], 'Festival at a glance') === false);
check('H10', 'Lockouts and failed logins are recorded in the audit log', (int)one("SELECT COUNT(*) FROM audit_log WHERE action IN ('login_failed','login_blocked')") >= 5);
$conn->query("DELETE FROM login_attempts");

check('H11', 'No auth cookie or password appears in page source for visitors', strpos($anon->get('/dashboard.php')['body'], 'password_hash') === false);
$r = $anon->get('/config/db.php', true);
check('H12', 'Opening config/db.php in a browser reveals no credentials', strpos($r['body'], 'DB_PASS') === false && strpos($r['body'], 'mysqli') === false);

/* ============ Summary ============ */
$total = count($results);
echo "\n==============================\n";
printf("TOTAL %d   PASSED %d   FAILED %d\n", $total, $total - $failures, $failures);
echo "==============================\n";
exit($failures ? 1 : 0);
