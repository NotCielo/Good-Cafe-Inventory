<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../config/database.php';
require_admin();

$page_title = 'Items';
$active = 'items';
$flash = '';
$upload_dir = __DIR__ . '/../assets/uploads/items/';
if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

function handle_photo_upload(?array $file): ?string {
    global $upload_dir;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    if ($file['size'] > 3 * 1024 * 1024) return null; // 3MB max

    $filename = 'item_' . uniqid() . '.' . $allowed[$mime];
    move_uploaded_file($file['tmp_name'], $upload_dir . $filename);
    return 'assets/uploads/items/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $name          = trim($_POST['name'] ?? '');
        $category_id   = (int)($_POST['category_id'] ?? 0);
        $quantity      = (float)($_POST['quantity'] ?? 0);
        $unit          = trim($_POST['unit'] ?? 'pcs');
        if ($unit === '__other__') { $unit = trim($_POST['unit_other'] ?? 'pcs'); }
        $reorder_level = (float)($_POST['reorder_level'] ?? 0);
        $buy_location  = trim($_POST['buy_location'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $photo_path    = handle_photo_upload($_FILES['photo'] ?? null);

        if ($name !== '' && $category_id > 0) {
            if ($action === 'create') {
                $stmt = $mysqli->prepare('INSERT INTO items (category_id, name, quantity, unit, reorder_level, buy_location, supplier_contact, photo_path) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('isdsdsss', $category_id, $name, $quantity, $unit, $reorder_level, $buy_location, $supplier_contact, $photo_path);
                $stmt->execute();
                $flash = 'Item added.';
            } else {
                $id = (int)$_POST['id'];
                if ($photo_path) {
                    $stmt = $mysqli->prepare('UPDATE items SET category_id=?, name=?, quantity=?, unit=?, reorder_level=?, buy_location=?, supplier_contact=?, photo_path=? WHERE id=?');
                    $stmt->bind_param('isdsdsssi', $category_id, $name, $quantity, $unit, $reorder_level, $buy_location, $supplier_contact, $photo_path, $id);
                } else {
                    $stmt = $mysqli->prepare('UPDATE items SET category_id=?, name=?, quantity=?, unit=?, reorder_level=?, buy_location=?, supplier_contact=? WHERE id=?');
                    $stmt->bind_param('isdsdssi', $category_id, $name, $quantity, $unit, $reorder_level, $buy_location, $supplier_contact, $id);
                }
                $stmt->execute();
                $flash = 'Item updated.';
            }
        }
    } elseif ($action === 'archive') {
        $id = (int)$_POST['id']; $to = (int)$_POST['to'];
        $stmt = $mysqli->prepare('UPDATE items SET is_archived = ? WHERE id = ?');
        $stmt->bind_param('ii', $to, $id);
        $stmt->execute();
        $flash = $to ? 'Item archived.' : 'Item restored.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $mysqli->prepare('DELETE FROM items WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $flash = 'Item deleted.';
    }
}

// --- Filters ---
$q = trim($_GET['q'] ?? '');
$category_id = $_GET['category'] ?? '';
$show_archived = isset($_GET['archived']);

$where = ['i.is_archived = ?'];
$params = [$show_archived ? 1 : 0];
$types = 'i';
if ($q !== '') { $where[] = 'i.name LIKE ?'; $params[] = "%$q%"; $types .= 's'; }
if ($category_id !== '') { $where[] = 'i.category_id = ?'; $params[] = (int)$category_id; $types .= 'i'; }

$sql = 'SELECT i.*, c.name AS category_name, p.name AS parent_name FROM items i
        JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories p ON p.id = c.parent_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY i.name';
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$leaf_categories = $mysqli->query('SELECT c.id, c.name, p.name AS parent_name FROM categories c JOIN categories p ON p.id = c.parent_id WHERE c.is_archived = 0 AND p.is_archived = 0 ORDER BY p.name, c.name')->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-bold text-ink-800">Items</h1>
    <p class="text-sm text-ink-400">Manage inventory items across all categories.</p>
  </div>
  <button onclick="openCreate()" class="btn-primary">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add item
  </button>
</div>

<?php if ($flash): ?>
  <div data-flash class="mb-4 rounded-xl bg-cafe-50 px-4 py-3 text-sm font-medium text-cafe-700"><?= h($flash) ?></div>
<?php endif; ?>

<form method="GET" class="mb-5 flex flex-wrap gap-3 items-end">
  <div class="flex-1 min-w-[180px]">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Search items</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Item name..." class="input-field">
  </div>
  <div class="min-w-[180px]">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Category</label>
    <select name="category" class="input-field">
      <option value="">All categories</option>
      <?php foreach ($leaf_categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)$category_id === (string)$c['id'] ? 'selected' : '' ?>><?= h($c['parent_name']) ?> — <?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <label class="flex items-center gap-2 text-sm text-ink-500 pb-2.5">
    <input type="checkbox" name="archived" value="1" <?= $show_archived ? 'checked' : '' ?> onchange="this.form.submit()">
    Show archived
  </label>
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
  <div class="card <?= $item['is_archived'] ? 'opacity-60' : '' ?>">
    <div class="flex gap-3">
      <?php if ($item['photo_path']): ?>
        <img src="<?= base_path() ?>/<?= h($item['photo_path']) ?>" class="h-14 w-14 rounded-xl object-cover flex-shrink-0" alt="">
      <?php else: ?>
        <div class="h-14 w-14 rounded-xl bg-cafe-50 flex items-center justify-center flex-shrink-0">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#89c942" stroke-width="2"><path d="M21 15V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10"/><rect x="3" y="7" width="18" height="14" rx="2"/><circle cx="8.5" cy="12.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </div>
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <p class="font-display font-bold text-ink-800 leading-tight truncate"><?= h($item['name']) ?></p>
          <?php if ($item['is_archived']): ?><span class="badge-flagged flex-shrink-0">Archived</span>
          <?php elseif ($is_low): ?><span class="badge-low flex-shrink-0">Low</span>
          <?php else: ?><span class="badge-ok flex-shrink-0">OK</span><?php endif; ?>
        </div>
        <p class="text-xs text-ink-400"><?= h($item['parent_name']) ?> · <?= h($item['category_name']) ?></p>
        <p class="text-sm text-ink-600 mt-1"><?= rtrim(rtrim(number_format($item['quantity'],2),'0'),'.') ?> <?= h($item['unit']) ?> <span class="text-ink-300">/ reorder <?= rtrim(rtrim(number_format($item['reorder_level'],2),'0'),'.') ?></span></p>
        <?php if ($item['supplier_contact']): ?>
          <p class="text-xs text-ink-400 mt-0.5">📞 <?= h($item['supplier_contact']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="mt-3 flex gap-2">
      <button onclick='openEdit(<?= $data ?>)' class="btn-secondary flex-1 !py-1.5 text-xs">Edit</button>
      <form method="POST" class="flex-1" onsubmit="return confirm('<?= $item['is_archived'] ? 'Restore' : 'Archive' ?> this item?');">
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <input type="hidden" name="to" value="<?= $item['is_archived'] ? 0 : 1 ?>">
        <button type="submit" class="btn-secondary w-full !py-1.5 text-xs"><?= $item['is_archived'] ? 'Restore' : 'Archive' ?></button>
      </form>
      <form method="POST" onsubmit="return confirm('Delete this item permanently? This also removes its stock history.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <button type="submit" class="rounded-full p-2 text-ink-400 hover:bg-red-50 hover:text-red-600" title="Delete">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Create/Edit Modal -->
<div id="itemModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4 overflow-y-auto">
  <div class="card w-full max-w-md my-8">
    <h3 id="itemModalTitle" class="font-display text-lg font-bold text-ink-800 mb-4">Add item</h3>
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="itemId">

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Item name</label>
        <input type="text" name="name" id="itemName" class="input-field" required>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Category</label>
        <select name="category_id" id="itemCategory" class="input-field" required>
          <?php foreach ($leaf_categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['parent_name']) ?> — <?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Quantity</label>
          <input type="number" step="0.01" name="quantity" id="itemQuantity" class="input-field" required>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Unit</label>
          <select name="unit" id="itemUnit" class="input-field" onchange="toggleUnitOther()" required>
            <?php foreach (unit_options() as $u): ?>
              <option value="<?= h($u) ?>"><?= h($u) ?></option>
            <?php endforeach; ?>
            <option value="__other__">Others – specify</option>
          </select>
          <input type="text" name="unit_other" id="itemUnitOther" class="input-field mt-2 hidden" placeholder="Enter custom unit">
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Reorder at</label>
        <input type="number" step="0.01" name="reorder_level" id="itemReorder" class="input-field" required>
        <p class="mt-1 text-xs text-ink-400">Item will appear on the "Needs to Buy" list once quantity drops to or below this number.</p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Where to buy</label>
        <input type="text" name="buy_location" id="itemLocation" class="input-field" placeholder="Supplier or store location">
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Supplier contact <span class="font-normal text-ink-400">(optional)</span></label>
        <input type="text" name="supplier_contact" id="itemSupplierContact" class="input-field" placeholder="Phone, email, or Viber/FB name">
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Photo <span class="font-normal text-ink-400">(optional)</span></label>
        <input type="file" name="photo" accept="image/*" class="input-field !py-2">
      </div>

      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('itemModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Save item</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleUnitOther() {
  const sel = document.getElementById('itemUnit');
  const other = document.getElementById('itemUnitOther');
  if (sel.value === '__other__') {
    other.classList.remove('hidden');
    other.required = true;
  } else {
    other.classList.add('hidden');
    other.required = false;
  }
}
function openCreate() {
  document.getElementById('itemModalTitle').textContent = 'Add item';
  document.getElementById('formAction').value = 'create';
  document.getElementById('itemId').value = '';
  document.getElementById('itemName').value = '';
  document.getElementById('itemQuantity').value = 0;
  document.getElementById('itemUnit').value = 'pcs';
  document.getElementById('itemUnitOther').value = '';
  toggleUnitOther();
  document.getElementById('itemReorder').value = 0;
  document.getElementById('itemLocation').value = '';
  document.getElementById('itemSupplierContact').value = '';
  openModal('itemModal');
}
function openEdit(item) {
  document.getElementById('itemModalTitle').textContent = 'Edit item';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('itemId').value = item.id;
  document.getElementById('itemName').value = item.name;
  document.getElementById('itemCategory').value = item.category_id;
  document.getElementById('itemQuantity').value = item.quantity;
  const unitSelect = document.getElementById('itemUnit');
  const knownUnits = Array.from(unitSelect.options).map(o => o.value);
  if (knownUnits.includes(item.unit)) {
    unitSelect.value = item.unit;
  } else {
    unitSelect.value = '__other__';
    document.getElementById('itemUnitOther').value = item.unit;
  }
  toggleUnitOther();
  document.getElementById('itemReorder').value = item.reorder_level;
  document.getElementById('itemLocation').value = item.buy_location || '';
  document.getElementById('itemSupplierContact').value = item.supplier_contact || '';
  openModal('itemModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
