<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Get Pass";

// The pass is always read back from the database, never from values in the URL
$pass = null;
$lookup = isset($_GET['pass']) && is_string($_GET['pass']) ? strtoupper(trim($_GET['pass'])) : '';
if ($lookup !== '') {
    if (valid_pass_id($lookup)) {
        $stmt = $conn->prepare("SELECT name, visit_date, guests, pass_id, checked_in FROM visitors WHERE pass_id = ?");
        $stmt->bind_param("s", $lookup);
        $stmt->execute();
        $pass = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$pass) { flash('error', 'No pass found with that ID.'); }
}

include __DIR__ . "/includes/header.php";
?>

<div class="section-head">
  <div>
    <span class="tag">Visitor Registration</span>
    <h2>Get your festival pass</h2>
  </div>
  <p>One quick sign-up generates a scannable entry pass — no printed tickets, no queue at the gate.</p>
</div>

<div class="form-wrap">
  <div class="panel-card">
    <form action="actions/register_visitor.php" method="POST">
      <?php echo csrf_field(); ?>
      <div class="field-row">
        <div class="field">
          <label>Full name</label>
          <input type="text" name="name" placeholder="Your name" maxlength="80" required>
        </div>
        <div class="field">
          <label>Phone number</label>
          <input type="tel" name="phone" placeholder="+91 98xxxxxxx0" maxlength="20" required>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Visit date</label>
          <input type="date" name="visit_date" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="field">
          <label>Number of guests</label>
          <input type="number" name="guests" min="1" max="10" value="1" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">Generate my pass</button>
    </form>
    <form action="visitor.php" method="GET" style="margin-top:22px; padding-top:18px; border-top:1px dashed var(--line);">
      <div class="field" style="margin-bottom:0;">
        <label>Already registered? Find your pass</label>
        <div class="field-row" style="grid-template-columns:1fr auto;">
          <input type="text" name="pass" placeholder="FEST-XXXXXXXX" maxlength="13">
          <button type="submit" class="btn btn-ghost btn-sm">Find</button>
        </div>
      </div>
    </form>
  </div>

  <div class="pass-area">
    <?php if ($pass): ?>
      <div class="pass-card">
        <span class="tag">Festival entry pass</span>
        <h3><?php echo e($pass['name']); ?></h3>
        <div class="pass-id mono"><?php echo e($pass['pass_id']); ?></div>
        <div class="qr-box" id="qrBox" data-qr-payload="<?php echo e($pass['pass_id']); ?>"></div>
        <div class="pass-meta">
          <div><strong><?php echo e($pass['visit_date']); ?></strong>Visit date</div>
          <div><strong><?php echo (int)$pass['guests']; ?></strong>Guest(s)</div>
          <div><strong class="<?php echo $pass['checked_in'] ? 'pass-status-in' : ''; ?>"><?php echo $pass['checked_in'] ? 'Checked in' : 'Valid'; ?></strong>Status</div>
        </div>
      </div>
    <?php else: ?>
      <div class="pass-placeholder">
        <div class="pass-placeholder-icon">🎟</div>
        <p>Your digital festival pass will appear here once you register — complete with a QR code for gate entry.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
