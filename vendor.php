<?php
require_once __DIR__ . "/config/bootstrap.php";
$pageTitle = "Vendor Portal";

// If a vendor is signed in, load THEIR stall (the id comes from the session, never from the URL)
$vendor = vendor_user();
$myStall = null; $myItems = [];
if ($vendor) {
    $stmt = $conn->prepare("SELECT id, stall_num, name, cuisine, status, rating, reviews_count FROM stalls WHERE id = ?");
    $stmt->bind_param("i", $vendor['stall_id']);
    $stmt->execute();
    $myStall = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, item_name FROM menu_items WHERE stall_id = ? ORDER BY id");
    $stmt->bind_param("i", $vendor['stall_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $myItems[] = $row; }
    $stmt->close();
}
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'login') ? 'login' : 'register';

include __DIR__ . "/includes/header.php";
?>

<div class="section-head">
  <div>
    <span class="tag">Vendor Portal</span>
    <h2><?php echo $myStall ? 'Manage your stall' : 'Bring your stall to the festival'; ?></h2>
  </div>
  <?php if ($vendor): ?>
    <form action="actions/vendor_logout.php" method="POST" class="inline-form">
      <?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($myStall): ?>

  <div class="form-wrap">
    <div class="panel-card">
      <div class="stall-summary">
        <div><div class="k">Stall</div><div class="v">#<?php echo (int)$myStall['stall_num']; ?></div></div>
        <div><div class="k">Status</div><div class="v"><span class="badge badge-<?php echo e($myStall['status']); ?>"><?php echo e($myStall['status']); ?></span></div></div>
        <div><div class="k">Rating</div><div class="v"><?php echo (int)$myStall['reviews_count'] > 0 ? number_format((float)$myStall['rating'], 1) . ' ★' : '—'; ?></div></div>
      </div>

      <h2 class="panel-title"><?php echo e($myStall['name']); ?> — menu</h2>
      <?php if ($myStall['status'] === 'pending'): ?>
        <div class="alert alert-error" style="background:rgba(244,166,35,0.12);border-color:rgba(244,166,35,0.4);color:#F7D58B;">Awaiting organizer approval. You can prepare your menu now — your stall goes public once approved.</div>
      <?php elseif ($myStall['status'] === 'rejected'): ?>
        <div class="alert alert-error">This stall was not approved, so the menu can't be edited. Please contact the organizer.</div>
      <?php endif; ?>

      <?php foreach ($myItems as $item): ?>
        <div class="manage-item-row">
          <span><?php echo e($item['item_name']); ?></span>
          <?php if ($myStall['status'] !== 'rejected'): ?>
          <form action="actions/remove_menu_item.php" method="POST" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
            <button type="submit">Remove</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($myItems)): ?>
        <p style="color:var(--muted); font-size:13.5px; margin-bottom:12px;">No menu items yet — add your first dish below.</p>
      <?php endif; ?>

      <?php if ($myStall['status'] !== 'rejected'): ?>
      <form action="actions/add_menu_item.php" method="POST" class="field-row" style="margin-top:16px; grid-template-columns:1fr auto;">
        <?php echo csrf_field(); ?>
        <div class="field" style="margin-bottom:0;">
          <input type="text" name="item_name" placeholder="Add a new menu item..." maxlength="80" required>
        </div>
        <button type="submit" class="btn btn-ghost" style="height:44px;">Add item</button>
      </form>
      <?php endif; ?>
    </div>
    <div class="side-note">
      <span class="tag">Vendor self-service</span>
      <h3>Only you can edit this stall</h3>
      <p>You're signed in as the owner of this stall, so menu changes are limited to your own listing. Visitors see updates on the Stalls page as soon as you save them.</p>
      <ul>
        <li>Menu changes go live immediately once your stall is approved.</li>
        <li>Ratings come from verified visitor passes only.</li>
        <li>You're signed out automatically after 30 minutes of inactivity.</li>
      </ul>
    </div>
  </div>

<?php else: ?>

  <div class="vendor-tabs">
    <button type="button" class="vtab <?php echo $activeTab === 'register' ? 'active' : ''; ?>" data-vtab="register">Register a new stall</button>
    <button type="button" class="vtab <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-vtab="login">Vendor sign in</button>
  </div>

  <div class="vtab-panel <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="vtab-register">
    <div class="form-wrap">
      <div class="panel-card">
        <form action="actions/register_vendor.php" method="POST">
          <?php echo csrf_field(); ?>
          <div class="field-row">
            <div class="field">
              <label>Stall name</label>
              <input type="text" name="stall_name" placeholder="e.g. Tandoori Trails" maxlength="80" required>
            </div>
            <div class="field">
              <label>Cuisine type</label>
              <select name="cuisine" required>
                <option value="" disabled selected>Select cuisine</option>
                <?php foreach (CUISINES as $c): ?><option><?php echo e($c); ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Vendor / contact name</label>
              <input type="text" name="contact_name" placeholder="Owner name" maxlength="80" required>
            </div>
            <div class="field">
              <label>Contact number</label>
              <input type="tel" name="phone" placeholder="+91 98xxxxxxx0" maxlength="20" required>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Choose a username</label>
              <input type="text" name="username" placeholder="letters, numbers, _" maxlength="30" autocomplete="username" required>
            </div>
            <div class="field">
              <label>Choose a password</label>
              <input type="password" name="password" placeholder="8+ characters, letters &amp; numbers" maxlength="72" autocomplete="new-password" required>
            </div>
          </div>
          <div class="field">
            <label>Menu items (comma separated)</label>
            <input type="text" name="menu" placeholder="e.g. Tandoori Chaap, Amritsari Kulcha, Lassi" maxlength="900" required>
          </div>
          <div class="field">
            <label>Short stall description</label>
            <textarea name="description" maxlength="300" placeholder="A line about what makes this stall worth the queue..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">Submit registration</button>
        </form>
      </div>
      <div class="side-note">
        <span class="tag">What happens next</span>
        <h3>From form to festival floor</h3>
        <p>Your application goes to the organizer for approval, and you get your own vendor account straight away.</p>
        <ul>
          <li>You're signed in right after registering, so you can prepare your menu.</li>
          <li>Once the organizer approves, your stall appears in the public directory with an assigned stall number.</li>
          <li>Only you can edit your menu, from your vendor account.</li>
          <li>Visitor ratings feed back to this same stall record.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="vtab-panel <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="vtab-login">
    <div class="form-wrap">
      <div class="panel-card">
        <form action="actions/vendor_login.php" method="POST">
          <?php echo csrf_field(); ?>
          <div class="field">
            <label>Username</label>
            <input type="text" name="username" maxlength="30" autocomplete="username" required>
          </div>
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" maxlength="72" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">Sign in</button>
        </form>
      </div>
      <div class="side-note">
        <span class="tag">Vendor self-service</span>
        <h3>Keep your stall up to date</h3>
        <p>Sign in to add or remove menu items and see your stall's approval status and rating.</p>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php include __DIR__ . "/includes/footer.php"; ?>
