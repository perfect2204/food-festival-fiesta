</div>
<footer>
  <div class="shell">
    <span class="tag mono">FOOD FESTIVAL FIESTA</span> · Vendor registration · Stall &amp; menu management · Visitor feedback · Organizer analytics
  </div>
</footer>
<?php $flash = take_flash(); ?>
<div class="toast<?php echo ($flash && $flash['type'] === 'error') ? ' error' : ''; ?>" id="toast" data-flash="<?php echo e($flash['msg'] ?? ''); ?>"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
