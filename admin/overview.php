<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_admin();

$page_title = 'Overview';
$active = 'overview';

$total_items = (int)$mysqli->query('SELECT COUNT(*) c FROM items WHERE is_archived = 0')->fetch_assoc()['c'];
$total_categories = (int)$mysqli->query('SELECT COUNT(*) c FROM categories WHERE is_archived = 0 AND parent_id IS NOT NULL')->fetch_assoc()['c'];
$total_departments = (int)$mysqli->query('SELECT COUNT(*) c FROM categories WHERE is_archived = 0 AND parent_id IS NULL')->fetch_assoc()['c'];
$low_stock_count = (int)$mysqli->query('SELECT COUNT(*) c FROM items WHERE is_archived = 0 AND quantity <= reorder_level')->fetch_assoc()['c'];
$flagged_count = (int)$mysqli->query('SELECT COUNT(*) c FROM items WHERE is_archived = 0 AND flagged_for_purchase = 1')->fetch_assoc()['c'];
$extra_count = (int)$mysqli->query('SELECT COUNT(*) c FROM extra_buy_items WHERE is_bought = 0')->fetch_assoc()['c'];
$needs_to_buy_total = $low_stock_count + $extra_count; // flagged items that are also low stock are already counted in low_stock_count
$archived_items = (int)$mysqli->query('SELECT COUNT(*) c FROM items WHERE is_archived = 1')->fetch_assoc()['c'];

$moves_today = (int)$mysqli->query("SELECT COUNT(*) c FROM stock_logs WHERE DATE(logged_at) = CURDATE()")->fetch_assoc()['c'];
$moves_week = (int)$mysqli->query("SELECT COUNT(*) c FROM stock_logs WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
$purchases_week = (int)$mysqli->query("SELECT COUNT(*) c FROM purchase_log WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

$recent_stock = $mysqli->query('SELECT l.*, i.name AS item_name, i.unit FROM stock_logs l JOIN items i ON i.id = l.item_id ORDER BY l.logged_at DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$recent_purchases = $mysqli->query('SELECT * FROM purchase_log ORDER BY logged_at DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-5">
  <h1 class="font-display text-2xl font-bold text-ink-800">Overview</h1>
  <p class="text-sm text-ink-400">A snapshot of your inventory right now.</p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Active items</p>
    <p class="mt-1 font-display text-2xl font-bold text-ink-800"><?= $total_items ?></p>
    <p class="text-xs text-ink-300 mt-0.5"><?= $archived_items ?> archived</p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Categories</p>
    <p class="mt-1 font-display text-2xl font-bold text-ink-800"><?= $total_categories ?></p>
    <p class="text-xs text-ink-300 mt-0.5">across <?= $total_departments ?> departments</p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Needs to buy</p>
    <p class="mt-1 font-display text-2xl font-bold text-red-500"><?= $needs_to_buy_total ?></p>
    <p class="text-xs text-ink-300 mt-0.5"><?= $low_stock_count ?> low stock · <?= $extra_count ?> extra</p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Flagged items</p>
    <p class="mt-1 font-display text-2xl font-bold text-amber-500"><?= $flagged_count ?></p>
    <p class="text-xs text-ink-300 mt-0.5">manually marked to buy</p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Stock updates today</p>
    <p class="mt-1 font-display text-2xl font-bold text-cafe-600"><?= $moves_today ?></p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold text-ink-400">Stock updates (7 days)</p>
    <p class="mt-1 font-display text-2xl font-bold text-cafe-600"><?= $moves_week ?></p>
  </div>
  <div class="card col-span-2">
    <p class="text-xs font-semibold text-ink-400">Purchases logged (7 days)</p>
    <p class="mt-1 font-display text-2xl font-bold text-cafe-600"><?= $purchases_week ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="card !p-0">
    <div class="px-4 py-3 border-b border-ink-100 font-display font-bold text-ink-800">Recent stock updates</div>
    <?php if (empty($recent_stock)): ?>
      <p class="px-4 py-6 text-sm text-ink-400 text-center">No stock updates yet.</p>
    <?php else: ?>
      <div class="divide-y divide-ink-50">
        <?php foreach ($recent_stock as $r): ?>
          <div class="px-4 py-3 flex items-center justify-between text-sm">
            <div>
              <p class="font-medium text-ink-800"><?= h($r['item_name']) ?></p>
              <p class="text-xs text-ink-400"><?= h($r['staff_name']) ?> · <?= date('M j, g:ia', strtotime($r['logged_at'])) ?></p>
            </div>
            <span class="<?= $r['change_amount'] >= 0 ? 'text-cafe-700' : 'text-red-600' ?> font-semibold">
              <?= $r['change_amount'] >= 0 ? '+' : '' ?><?= rtrim(rtrim(number_format($r['change_amount'],2),'0'),'.') ?> <?= h($r['unit']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card !p-0">
    <div class="px-4 py-3 border-b border-ink-100 font-display font-bold text-ink-800">Recent purchases logged</div>
    <?php if (empty($recent_purchases)): ?>
      <p class="px-4 py-6 text-sm text-ink-400 text-center">No purchases logged yet.</p>
    <?php else: ?>
      <div class="divide-y divide-ink-50">
        <?php foreach ($recent_purchases as $r): ?>
          <div class="px-4 py-3 flex items-center justify-between text-sm">
            <div>
              <p class="font-medium text-ink-800"><?= h($r['item_name']) ?></p>
              <p class="text-xs text-ink-400"><?= h($r['logged_by']) ?> · <?= date('M j, g:ia', strtotime($r['logged_at'])) ?></p>
            </div>
            <?php if ($r['quantity_needed'] !== null): ?>
              <span class="text-ink-500 text-xs">qty <?= rtrim(rtrim(number_format($r['quantity_needed'],2),'0'),'.') ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
