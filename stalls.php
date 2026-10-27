<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Stalls";

$activeFilter = (isset($_GET['cuisine']) && is_string($_GET['cuisine'])) ? $_GET['cuisine'] : 'All';

// Only organizer-approved stalls are public
$stalls = [];
$res = $conn->query("SELECT id, stall_num, name, cuisine, description, rating, reviews_count
                     FROM stalls WHERE status='approved' ORDER BY rating DESC, reviews_count DESC");
while ($row = $res->fetch_assoc()) { $row['items'] = []; $stalls[(int)$row['id']] = $row; }

$res = $conn->query("SELECT m.stall_id, m.item_name FROM menu_items m
                     JOIN stalls s ON s.id = m.stall_id WHERE s.status='approved' ORDER BY m.id");
while ($r = $res->fetch_assoc()) { $stalls[(int)$r['stall_id']]['items'][] = $r['item_name']; }

$cuisines = ['All'];
foreach ($stalls as $s) { if (!in_array($s['cuisine'], $cuisines, true)) $cuisines[] = $s['cuisine']; }

include __DIR__ . "/includes/header.php";
?>

<div class="section-head">
  <div>
    <span class="tag">Stall Directory</span>
    <h2>Every stall, one ticket each</h2>
  </div>
  <p>Filter by cuisine, search for a dish, and tap "View menu" to see what each stall serves.</p>
</div>

<div class="stalls-toolbar">
  <div class="search-box">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="stallSearch" placeholder="Search stalls or dishes...">
  </div>
  <select id="stallSort">
    <option value="rating">Sort: Highest rated</option>
    <option value="reviews">Sort: Most reviewed</option>
    <option value="num">Sort: Stall number</option>
    <option value="name">Sort: A–Z</option>
  </select>
</div>

<div class="filter-row">
  <?php foreach ($cuisines as $c): ?>
    <a href="stalls.php?cuisine=<?php echo urlencode($c); ?>"
       class="chip <?php echo $c === $activeFilter ? 'active' : ''; ?>"><?php echo e($c); ?></a>
  <?php endforeach; ?>
</div>

<div class="ticket-grid" id="ticketGrid">
  <?php foreach ($stalls as $id => $s):
      if ($activeFilter !== 'All' && $s['cuisine'] !== $activeFilter) continue;
      $searchBlob = $s['name'] . ' ' . $s['cuisine'] . ' ' . implode(' ', $s['items']);
  ?>
  <div class="ticket"
       data-search="<?php echo e($searchBlob); ?>"
       data-rating="<?php echo e($s['rating']); ?>"
       data-reviews="<?php echo (int)$s['reviews_count']; ?>"
       data-num="<?php echo (int)$s['stall_num']; ?>"
       data-name="<?php echo e($s['name']); ?>">
    <div class="ticket-main">
      <span class="ticket-cuisine"><?php echo e($s['cuisine']); ?></span>
      <h3><?php echo e($s['name']); ?></h3>
      <?php if ($s['description'] !== null && $s['description'] !== ''): ?>
        <div class="desc"><?php echo e($s['description']); ?></div>
      <?php endif; ?>
      <div class="rating">
        <?php if ((int)$s['reviews_count'] > 0): ?>
          <span class="stars"><?php echo stars((float)$s['rating']); ?></span>
          <span class="mono" style="color:var(--muted);font-size:12px;"><?php echo number_format((float)$s['rating'], 1); ?> · <?php echo (int)$s['reviews_count']; ?> ratings</span>
        <?php else: ?>
          <span class="mono" style="color:var(--muted);font-size:12px;">New stall · no ratings yet</span>
        <?php endif; ?>
      </div>
      <button type="button" class="ticket-toggle" data-idx="<?php echo $id; ?>">View menu ↓</button>
      <div class="menu-list" id="menu-<?php echo $id; ?>">
        <?php foreach ($s['items'] as $it): ?>
          <div><span><?php echo e($it); ?></span></div>
        <?php endforeach; ?>
        <?php if (empty($s['items'])): ?><div><span>Menu coming soon</span></div><?php endif; ?>
      </div>
    </div>
    <div class="ticket-stub">
      <div class="stub-label">Stall</div>
      <div class="stub-num mono">#<?php echo (int)$s['stall_num']; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<p id="noResults" style="display:none; color:var(--muted); font-size:14px; padding:20px 0;">No stalls match that search — try another dish or cuisine.</p>

<?php include __DIR__ . "/includes/footer.php"; ?>
