<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$page_title = 'Update Stock';
$active = 'stock';
$flash = flash_get();

$q = trim($_GET['q'] ?? '');
$category_id = $_GET['category'] ?? '';

$where = ['i.is_archived = 0'];
$params = []; $types = '';
if ($q !== '') { $where[] = 'i.name LIKE ?'; $params[] = "%$q%"; $types .= 's'; }
if ($category_id !== '') { $where[] = 'i.category_id = ?'; $params[] = (int)$category_id; $types .= 'i'; }

$sql = 'SELECT i.*, c.name AS category_name, p.name AS parent_name FROM items i
        JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories p ON p.id = c.parent_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY i.name';
$stmt = $mysqli->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$leaf_categories = $mysqli->query('SELECT c.id, c.name, p.name AS parent_name FROM categories c JOIN categories p ON p.id = c.parent_id WHERE c.is_archived = 0 AND p.is_archived = 0 ORDER BY p.name, c.name')->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-5">
  <h1 class="font-display text-2xl font-bold text-ink-800">Update Stock</h1>
  <p class="text-sm text-ink-400">Tap an item to add or subtract its quantity.</p>
</div>

<?php if ($flash): ?>
  <div data-flash class="mb-4 rounded-xl px-4 py-3 text-sm font-medium <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-600' : 'bg-cafe-50 text-cafe-700' ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<form method="GET" class="mb-5 flex flex-wrap gap-3 items-end">
  <div class="flex-1 min-w-[180px]">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Search items</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Item name..." class="input-field">
  </div>
  <div class="min-w-[180px]">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Category</label>
    <select name="category" class="input-field" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($leaf_categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)$category_id === (string)$c['id'] ? 'selected' : '' ?>><?= h($c['parent_name']) ?> — <?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn-secondary">Search</button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
<?php if (empty($items)): ?>
  <div class="card col-span-full text-center py-10 text-sm text-ink-400">No items found.</div>
<?php endif; ?>
<?php foreach ($items as $item):
  $is_low = $item['quantity'] <= $item['reorder_level'];
  $data = json_encode($item);
?>
  <button onclick='openAdjust(<?= $data ?>)' class="card text-left hover:ring-cafe-300 transition">
    <div class="flex items-start justify-between gap-2">
      <p class="font-display font-bold text-ink-800 leading-tight"><?= h($item['name']) ?></p>
      <?php if ($is_low): ?><span class="badge-low flex-shrink-0">Low</span><?php endif; ?>
    </div>
    <p class="text-xs text-ink-400 mt-0.5"><?= h($item['parent_name']) ?> · <?= h($item['category_name']) ?></p>
    <p class="mt-2 text-xl font-bold text-ink-800"><?= rtrim(rtrim(number_format($item['quantity'],2),'0'),'.') ?> <span class="text-sm font-normal text-ink-400"><?= h($item['unit']) ?></span></p>
  </button>
<?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/stock_modal.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
