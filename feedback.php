<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Rate a Stall";

$stalls = [];
$result = $conn->query("SELECT id, stall_num, name FROM stalls WHERE status='approved' ORDER BY name");
while ($row = $result->fetch_assoc()) { $stalls[] = $row; }

$feedbacks = [];
$fResult = $conn->query("
    SELECT f.visitor_name, f.rating, f.comment, f.created_at, s.name AS stall_name
    FROM feedback f JOIN stalls s ON f.stall_id = s.id
    WHERE s.status = 'approved'
    ORDER BY f.created_at DESC, f.id DESC LIMIT 10
");
while ($row = $fResult->fetch_assoc()) { $feedbacks[] = $row; }

$prefillPass = (isset($_GET['pass']) && is_string($_GET['pass']) && valid_pass_id(strtoupper($_GET['pass']))) ? strtoupper($_GET['pass']) : '';

include __DIR__ . "/includes/header.php";
?>

<div class="section-head">
  <div>
    <span class="tag">Verified Feedback</span>
    <h2>Rate a stall</h2>
  </div>
  <p>Reviews are tied to a festival pass, so every rating comes from a real visitor — one review per stall.</p>
</div>

<div class="form-wrap">
  <div class="panel-card">
    <form action="actions/submit_feedback.php" method="POST">
      <?php echo csrf_field(); ?>
      <div class="field">
        <label>Your pass ID</label>
        <input type="text" name="pass_id" value="<?php echo e($prefillPass); ?>" placeholder="FEST-XXXXXXXX" maxlength="13" required>
        <div class="hint">Don't have one yet? <a href="visitor.php">Get your free pass</a> first.</div>
      </div>
      <div class="field">
        <label>Which stall?</label>
        <select name="stall_id" required>
          <?php foreach ($stalls as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['name']); ?> (#<?php echo (int)$s['stall_num']; ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Your rating</label>
        <div class="star-picker">
          <input type="radio" name="rating" id="star5" value="5" required><label for="star5">★</label>
          <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
          <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
          <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
          <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
        </div>
      </div>
      <div class="field">
        <label>Comments</label>
        <textarea name="comment" maxlength="500" placeholder="What did you like? Anything the stall could improve?"></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">Submit feedback</button>
    </form>
  </div>
  <div>
    <span class="tag" style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--marigold);letter-spacing:0.14em;text-transform:uppercase;">Recent reviews</span>
    <div class="feed">
      <?php foreach ($feedbacks as $f): ?>
        <div class="feed-item">
          <div class="feed-top">
            <strong><?php echo e($f['stall_name']); ?></strong>
            <span class="stars"><?php echo stars((float)$f['rating']); ?></span>
          </div>
          <p><?php echo e($f['comment'] !== null && $f['comment'] !== '' ? $f['comment'] : 'No comment left.'); ?></p>
          <div class="feed-meta">— <?php echo e($f['visitor_name']); ?> · <?php echo e(date('d M, g:i A', strtotime($f['created_at']))); ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($feedbacks)): ?>
        <p style="color:var(--muted); font-size:13.5px;">No reviews yet — be the first to rate a stall.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
