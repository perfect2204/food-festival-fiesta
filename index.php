<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Home";

// Live stats from the database (approved stalls only)
$stallCount   = (int)$conn->query("SELECT COUNT(*) AS c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
$cuisineCount = (int)$conn->query("SELECT COUNT(DISTINCT cuisine) AS c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
$ratingsTotal = (int)$conn->query("SELECT COALESCE(SUM(reviews_count),0) AS c FROM stalls WHERE status='approved'")->fetch_assoc()['c'];
$avgRow = $conn->query("SELECT SUM(rating * reviews_count) / NULLIF(SUM(reviews_count),0) AS a FROM stalls WHERE status='approved'")->fetch_assoc();
$avgRating = $avgRow['a'] ? number_format((float)$avgRow['a'], 1) : "–";

include __DIR__ . "/includes/header.php";
?>

<div class="bunting">
  <div class="flag"></div><div class="flag"></div><div class="flag"></div><div class="flag"></div>
  <div class="flag"></div><div class="flag"></div><div class="flag"></div><div class="flag"></div>
  <div class="flag"></div><div class="flag"></div><div class="flag"></div><div class="flag"></div>
</div>

<div class="hero">
  <span class="eyebrow"><span class="dot"></span>Festival Management Platform</span>
  <h1>One ticket.<br>Every stall,<br><em>zero chaos.</em></h1>
  <p class="sub">A digital home for vendor registration, stall &amp; menu management, visitor passes, verified feedback, and organizer analytics — replacing clipboards and long queues with one connected platform.</p>
  <div class="hero-cta">
    <a class="btn btn-primary" href="stalls.php">Explore stalls</a>
    <a class="btn btn-ghost" href="visitor.php">Get your pass</a>
  </div>

  <div class="stat-strip">
    <div class="stat-cell"><div class="stat-num"><?php echo $stallCount; ?></div><div class="stat-label">Active stalls</div></div>
    <div class="stat-cell"><div class="stat-num"><?php echo $cuisineCount; ?></div><div class="stat-label">Cuisine types</div></div>
    <div class="stat-cell"><div class="stat-num"><?php echo $ratingsTotal; ?></div><div class="stat-label">Visitor ratings</div></div>
    <div class="stat-cell"><div class="stat-num"><?php echo $avgRating; ?></div><div class="stat-label">Avg. rating</div></div>
  </div>
</div>

<div class="section-head">
  <div>
    <span class="tag">Why it exists</span>
    <h2>Built for organizers, vendors &amp; visitors</h2>
  </div>
  <p>Traditional festival management runs on registers and word of mouth. This platform gives every stakeholder a clear, real-time view.</p>
</div>

<div class="flow">
  <div class="flow-card">
    <div class="flow-num mono">FOR ORGANIZERS</div>
    <h3>Approve, track and check in</h3>
    <p>Review vendor applications, scan visitors in at the gate, and watch stall performance and satisfaction on one dashboard.</p>
  </div>
  <div class="flow-card">
    <div class="flow-num mono">FOR VENDORS</div>
    <h3>Your own stall account</h3>
    <p>Register once, sign in, and keep your menu up to date. Visitors see changes straight away, and ratings come back to you.</p>
  </div>
  <div class="flow-card">
    <div class="flow-num mono">FOR VISITORS</div>
    <h3>Find food, not queues</h3>
    <p>Browse stalls by cuisine, carry a scannable entry pass, and leave a verified review that genuinely reaches the organizer.</p>
  </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
