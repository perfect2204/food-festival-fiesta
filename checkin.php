<?php
require_once __DIR__ . "/config/bootstrap.php";
require_admin('dashboard.php');
$pageTitle = "Gate Check-in";

$total     = (int)$conn->query("SELECT COUNT(*) c FROM visitors")->fetch_assoc()['c'];
$in        = (int)$conn->query("SELECT COUNT(*) c FROM visitors WHERE checked_in=1")->fetch_assoc()['c'];
$guestsIn  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) c FROM visitors WHERE checked_in=1")->fetch_assoc()['c'];

$recent = [];
$res = $conn->query("SELECT name, pass_id, guests, checked_in_at FROM visitors WHERE checked_in=1 ORDER BY checked_in_at DESC, id DESC LIMIT 10");
while ($row = $res->fetch_assoc()) { $recent[] = $row; }

include __DIR__ . "/includes/header.php";
?>

<div class="section-head">
  <div>
    <span class="tag">Gate Check-in</span>
    <h2>Scan or enter a visitor pass</h2>
  </div>
  <p>Each pass can be used once. A phone or barcode scanner that "types" the code and presses Enter works with this box.</p>
</div>

<div class="dash-grid">
  <div class="dash-tile"><div class="label">Passes issued</div><div class="value"><?php echo $total; ?></div></div>
  <div class="dash-tile"><div class="label">Checked in</div><div class="value"><?php echo $in; ?></div></div>
  <div class="dash-tile"><div class="label">Guests inside</div><div class="value"><?php echo $guestsIn; ?></div></div>
  <div class="dash-tile"><div class="label">Still to arrive</div><div class="value"><?php echo $total - $in; ?></div></div>
</div>

<div class="panel-card checkin-box">
  <form action="actions/admin_checkin.php" method="POST">
    <?php echo csrf_field(); ?>
    <div class="field">
      <label>Pass ID</label>
      <input type="text" name="pass_id" id="checkinPass" placeholder="FEST-XXXXXXXX" maxlength="13" autocomplete="off" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Check in</button>
  </form>
</div>

<div class="panel-card">
  <h2 class="panel-title">Latest check-ins</h2>
  <?php foreach ($recent as $r): ?>
    <div class="audit-line">
      <span><strong><?php echo e($r['name']); ?></strong> · <?php echo (int)$r['guests']; ?> guest(s) · <span class="mono"><?php echo e($r['pass_id']); ?></span></span>
      <span class="when"><?php echo e($r['checked_in_at']); ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (empty($recent)): ?><p style="color:var(--muted);font-size:13.5px;">Nobody has checked in yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
