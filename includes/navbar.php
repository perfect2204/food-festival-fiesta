<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($pages, $current) { return in_array($current, (array)$pages, true) ? 'active' : ''; }
?>
<nav class="topnav">
  <div class="topnav-inner">
    <div class="brand">
      <div class="brand-mark">FF</div>
      <div class="brand-name">Food Festival Fiesta<small>Festival Management Platform</small></div>
    </div>
    <div class="nav-links">
      <a href="index.php" class="<?php echo navActive('index.php', $currentPage); ?>">Home</a>
      <a href="stalls.php" class="<?php echo navActive('stalls.php', $currentPage); ?>">Stalls</a>
      <a href="visitor.php" class="<?php echo navActive('visitor.php', $currentPage); ?>">Get Pass</a>
      <a href="vendor.php" class="<?php echo navActive('vendor.php', $currentPage); ?>">Vendor Portal</a>
      <a href="feedback.php" class="<?php echo navActive('feedback.php', $currentPage); ?>">Rate a Stall</a>
      <a href="dashboard.php" class="<?php echo navActive('dashboard.php', $currentPage); ?>">Organizer</a>
      <?php if (admin_user()): ?>
        <a href="checkin.php" class="<?php echo navActive('checkin.php', $currentPage); ?>">Gate Check-in</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
