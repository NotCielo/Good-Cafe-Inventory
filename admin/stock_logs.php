<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_admin();

$page_title = 'Stock Log';
$active = 'logs';

$q = trim($_GET['q'] ?? '');
$where = [];
$params = []; $types = '';
if ($q !== '') {
    $where[] = '(i.name LIKE ? OR l.staff_name LIKE ?)';
    $like = "%$q%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$sql = 'SELECT l.*, i.name AS item_name, i.unit FROM stock_logs l
        JOIN items i ON i.id = l.item_id
        ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
        ORDER BY l.logged_at DESC LIMIT 200';
$stmt = $mysqli->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-5">
  <h1 class="font-display text-2xl font-bold text-ink-800">Stock Log</h1>
  <p class="text-sm text-ink-400">Every quantity change, with who made it and when. Most recent 200.</p>
</div>

<form method="GET" class="mb-5 flex gap-3">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by item or staff name..." class="input-field">
  <button type="submit" class="btn-secondary flex-shrink-0">Search</button>
</form>

<div class="card overflow-x-auto !p-0">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-ink-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-400">
        <th class="px-4 py-3">Item</th>
        <th class="px-4 py-3">Change</th>
        <th class="px-4 py-3">Resulting qty</th>
        <th class="px-4 py-3">By</th>
        <th class="px-4 py-3">When</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" class="px-4 py-8 text-center text-ink-400">No stock changes recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $log): ?>
        <tr class="border-b border-ink-50 last:border-0">
          <td class="px-4 py-3 font-medium text-ink-800"><?= h($log['item_name']) ?></td>
          <td class="px-4 py-3">
            <span class="<?= $log['change_amount'] >= 0 ? 'text-cafe-700' : 'text-red-600' ?> font-semibold">
              <?= $log['change_amount'] >= 0 ? '+' : '' ?><?= rtrim(rtrim(number_format($log['change_amount'],2),'0'),'.') ?> <?= h($log['unit']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-ink-600"><?= rtrim(rtrim(number_format($log['resulting_quantity'],2),'0'),'.') ?> <?= h($log['unit']) ?></td>
          <td class="px-4 py-3 text-ink-600"><?= h($log['staff_name']) ?></td>
          <td class="px-4 py-3 text-ink-400"><?= date('M j, Y g:ia', strtotime($log['logged_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
