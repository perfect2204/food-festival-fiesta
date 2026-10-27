<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Organizer";
$admin = admin_user();

if ($admin) {
    $stallCount   = (int)$conn->query("SELECT COUNT(*) c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
    $pendingCount = (int)$conn->query("SELECT COUNT(*) c FROM stalls WHERE status='pending'")->fetch_assoc()['c'];
    $cuisineCount = (int)$conn->query("SELECT COUNT(DISTINCT cuisine) c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
    $visitorTotal = (int)$conn->query("SELECT COUNT(*) c FROM visitors")->fetch_assoc()['c'];
    $checkedIn    = (int)$conn->query("SELECT COUNT(*) c FROM visitors WHERE checked_in=1")->fetch_assoc()['c'];
    $ratingsTotal = (int)$conn->query("SELECT COALESCE(SUM(reviews_count),0) c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
    $avgRow = $conn->query("SELECT SUM(rating*reviews_count)/NULLIF(SUM(reviews_count),0) a FROM stalls WHERE status='approved'")->fetch_assoc();
    $avgRating = $avgRow['a'] ? round((float)$avgRow['a'], 2) : 0;
    $topStall = $conn->query("SELECT name, rating FROM stalls WHERE status='approved' AND reviews_count > 0 ORDER BY rating DESC, reviews_count DESC LIMIT 1")->fetch_assoc();

    $cuisineAverages = [];
    $cRes = $conn->query("SELECT cuisine, SUM(rating*reviews_count)/NULLIF(SUM(reviews_count),0) AS avg_r
                          FROM stalls WHERE status='approved' GROUP BY cuisine
                          HAVING SUM(reviews_count) > 0 ORDER BY avg_r DESC");
    while ($row = $cRes->fetch_assoc()) { $cuisineAverages[] = $row; }

    $allStalls = [];
    $sRes = $conn->query("SELECT s.id, s.stall_num, s.name, s.cuisine, s.status, v.contact_name
                          FROM stalls s LEFT JOIN vendors v ON v.stall_id = s.id
                          ORDER BY FIELD(s.status,'pending','approved','rejected'), s.stall_num");
    while ($row = $sRes->fetch_assoc()) { $allStalls[] = $row; }

    $auditRows = [];
    $aRes = $conn->query("SELECT actor_type, actor_name, action, details, created_at FROM audit_log ORDER BY id DESC LIMIT 8");
    while ($row = $aRes->fetch_assoc()) { $auditRows[] = $row; }

    $circumference = 235;
    $offset = $circumference - (($avgRating / 5) * $circumference);
}

include __DIR__ . "/includes/header.php";
?>

<?php if (!$admin): ?>

  <div class="login-gate">
    <div class="login-card">
      <span class="tag">Organizer access</span>
      <h3>Sign in to manage the festival</h3>
      <p>Approve stalls, check visitors in at the gate, and view live analytics.</p>
      <form action="actions/admin_login.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="text" name="username" placeholder="Username" maxlength="60" autocomplete="username" required>
        <input type="password" name="password" placeholder="Password" maxlength="200" autocomplete="current-password" required>
        <button type="submit" class="btn btn-primary" style="width:100%;">Sign in</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <div class="section-head">
    <div>
      <span class="tag">Organizer View · <?php echo e($admin['username']); ?></span>
      <h2>Festival at a glance</h2>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="checkin.php" class="btn btn-primary btn-sm">Gate check-in</a>
      <a href="actions/export_report.php" class="btn btn-ghost btn-sm">⬇ Download report (CSV)</a>
      <form action="actions/admin_logout.php" method="POST" class="inline-form">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
      </form>
    </div>
  </div>

  <div class="dash-grid">
    <div class="dash-tile"><div class="label">Approved stalls</div><div class="value"><?php echo $stallCount; ?></div><div class="delta">across <?php echo $cuisineCount; ?> cuisines</div></div>
    <div class="dash-tile"><div class="label">Awaiting approval</div><div class="value"><?php echo $pendingCount; ?></div><div class="delta"><?php echo $pendingCount ? 'needs your review below' : 'all caught up'; ?></div></div>
    <div class="dash-tile"><div class="label">Visitor passes</div><div class="value"><?php echo $visitorTotal; ?></div><div class="delta"><?php echo $checkedIn; ?> checked in</div></div>
    <div class="dash-tile"><div class="label">Average rating</div><div class="value"><?php echo $avgRating ? number_format($avgRating, 1) : '–'; ?></div><div class="delta">from <?php echo $ratingsTotal; ?> ratings<?php echo $topStall ? ' · top: ' . e($topStall['name']) : ''; ?></div></div>
  </div>

  <div class="dash-panels">
    <div class="panel-card">
      <h2 class="panel-title">Average rating by cuisine</h2>
      <?php foreach ($cuisineAverages as $c): ?>
        <div class="bar-row">
          <div class="name"><?php echo e($c['cuisine']); ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?php echo round($c['avg_r'] / 5 * 100); ?>%"></div></div>
          <div class="val"><?php echo number_format((float)$c['avg_r'], 1); ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($cuisineAverages)): ?><p style="color:var(--muted);font-size:13.5px;">No ratings yet.</p><?php endif; ?>
    </div>
    <div class="panel-card">
      <h2 class="panel-title">Overall satisfaction</h2>
      <div class="gauge-wrap">
        <svg width="180" height="110" viewBox="0 0 180 110">
          <path d="M15,100 A75,75 0 0,1 165,100" fill="none" stroke="rgba(246,239,228,0.1)" stroke-width="14" stroke-linecap="round"/>
          <path d="M15,100 A75,75 0 0,1 165,100" fill="none" stroke="url(#gaugeGrad)" stroke-width="14" stroke-linecap="round" stroke-dasharray="<?php echo $circumference; ?>" stroke-dashoffset="<?php echo $offset; ?>"/>
          <defs>
            <linearGradient id="gaugeGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stop-color="#E4572E"/><stop offset="100%" stop-color="#F4A623"/>
            </linearGradient>
          </defs>
        </svg>
        <div class="gauge-num"><?php echo $avgRating ? number_format($avgRating, 1) : '–'; ?></div>
        <div class="gauge-label">Visitor satisfaction</div>
      </div>
    </div>
  </div>

  <div class="section-head" style="margin-top:44px;">
    <div><span class="tag">Stall management</span><h2>Applications &amp; stalls</h2></div>
    <p>Approve new vendor applications before they appear to visitors. Rejected stalls are hidden and their vendors can no longer edit them.</p>
  </div>
  <div class="panel-card table-wrap">
    <table class="data-table">
      <thead><tr><th>#</th><th>Stall</th><th>Cuisine</th><th>Vendor</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($allStalls as $s): ?>
        <tr>
          <td class="mono">#<?php echo (int)$s['stall_num']; ?></td>
          <td><?php echo e($s['name']); ?></td>
          <td><?php echo e($s['cuisine']); ?></td>
          <td><?php echo e($s['contact_name'] ?? '—'); ?></td>
          <td><span class="badge badge-<?php echo e($s['status']); ?>"><?php echo e($s['status']); ?></span></td>
          <td>
            <div class="actions">
              <?php foreach (['approved' => ['Approve','btn-ok'], 'rejected' => ['Reject','btn-danger']] as $newStatus => $meta):
                    if ($s['status'] === $newStatus) continue; ?>
                <form action="actions/admin_stall_status.php" method="POST" class="inline-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="stall_id" value="<?php echo (int)$s['id']; ?>">
                  <input type="hidden" name="status" value="<?php echo e($newStatus); ?>">
                  <button type="submit" class="btn btn-sm <?php echo $meta[1]; ?>"><?php echo $meta[0]; ?></button>
                </form>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="section-head" style="margin-top:44px;">
    <div><span class="tag">Security</span><h2>Recent activity</h2></div>
    <p>Every sign-in, approval, check-in and export is recorded with the time and IP address.</p>
  </div>
  <div class="panel-card">
    <?php foreach ($auditRows as $a): ?>
      <div class="audit-line">
        <span><strong><?php echo e($a['action']); ?></strong> — <?php echo e($a['actor_type']); ?> <?php echo e($a['actor_name']); ?><?php echo $a['details'] !== '' ? ' · ' . e($a['details']) : ''; ?></span>
        <span class="when"><?php echo e($a['created_at']); ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (empty($auditRows)): ?><p style="color:var(--muted);font-size:13.5px;">No activity recorded yet.</p><?php endif; ?>
  </div>

<?php endif; ?>

<?php include __DIR__ . "/includes/footer.php"; ?>
