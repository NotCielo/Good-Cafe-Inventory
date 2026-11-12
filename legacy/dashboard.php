<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/config/database.php';
require_login();

$page_title = 'Needs to Buy';
$active = 'dashboard';
$flash = flash_get();

// --- Filters ---
$q            = trim($_GET['q'] ?? '');
$category_id  = $_GET['category'] ?? '';
$status       = $_GET['status'] ?? '';   // '', 'low', 'flagged'
$location     = trim($_GET['location'] ?? '');

// Build WHERE clause for inventory items
$where  = ['i.is_archived = 0', '(i.quantity <= i.reorder_level OR i.flagged_for_purchase = 1)'];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = '(i.name LIKE ? OR i.buy_location LIKE ?)';
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($category_id !== '') {
    $where[] = 'i.category_id = ?';
    $params[] = (int)$category_id; $types .= 'i';
}
if ($status === 'low') {
    $where[] = 'i.quantity <= i.reorder_level';
} elseif ($status === 'flagged') {
    $where[] = 'i.flagged_for_purchase = 1';
}
if ($location !== '') {
    $where[] = 'i.buy_location LIKE ?';
    $params[] = "%$location%"; $types .= 's';
}

$sql = 'SELECT i.*, c.name AS category_name, p.name AS parent_name
        FROM items i
        JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories p ON p.id = c.parent_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY (i.quantity <= i.reorder_level) DESC, i.name ASC';

$stmt = $mysqli->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Extra (non-inventory) buy items, with the same search/location filters applied
$ewhere = ['is_bought = 0'];
$eparams = []; $etypes = '';
if ($q !== '') { $ewhere[] = '(name LIKE ? OR buy_location LIKE ?)'; $eparams[] = "%$q%"; $eparams[] = "%$q%"; $etypes .= 'ss'; }
if ($location !== '') { $ewhere[] = 'buy_location LIKE ?'; $eparams[] = "%$location%"; $etypes .= 's'; }
if ($category_id !== '') {
    // Extra items have no category — hide them when a category filter is active
    $ewhere[] = '1 = 0';
}
$esql = 'SELECT * FROM extra_buy_items WHERE ' . implode(' AND ', $ewhere) . ' ORDER BY created_at DESC';
$estmt = $mysqli->prepare($esql);
if ($etypes !== '') { $estmt->bind_param($etypes, ...$eparams); }
$estmt->execute();
$extra_items = $estmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $mysqli->query('SELECT id, name, parent_id FROM categories WHERE is_archived = 0 ORDER BY parent_id IS NULL DESC, name')->fetch_all(MYSQLI_ASSOC);
$leaf_categories = $mysqli->query('SELECT c.id, c.name, p.name AS parent_name FROM categories c JOIN categories p ON p.id = c.parent_id WHERE c.is_archived = 0 AND p.is_archived = 0 ORDER BY p.name, c.name')->fetch_all(MYSQLI_ASSOC);
$all_items_for_flag = $mysqli->query('SELECT i.id, i.name, c.name AS category_name, p.name AS parent_name FROM items i JOIN categories c ON c.id=i.category_id LEFT JOIN categories p ON p.id=c.parent_id WHERE i.is_archived = 0 ORDER BY i.name')->fetch_all(MYSQLI_ASSOC);

// Distinct known locations, for the location filter combo (datalist)
$locations_result = $mysqli->query("SELECT DISTINCT buy_location FROM items WHERE buy_location IS NOT NULL AND buy_location != ''
                                     UNION
                                     SELECT DISTINCT buy_location FROM extra_buy_items WHERE buy_location IS NOT NULL AND buy_location != ''");
$known_locations = array_column($locations_result->fetch_all(MYSQLI_ASSOC), 'buy_location');

$total_count = count($items) + count($extra_items);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-5 flex items-start justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-bold text-ink-800">Needs to Buy</h1>
    <p class="text-sm text-ink-400">Low stock, flagged, and extra items to purchase.</p>
  </div>
  <span class="badge-low text-sm !px-3 !py-1.5"><?= $total_count ?> item<?= $total_count === 1 ? '' : 's' ?></span>
</div>

<?php if ($flash): ?>
  <div data-flash class="mb-4 rounded-xl px-4 py-3 text-sm font-medium <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-600' : 'bg-cafe-50 text-cafe-700' ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <button onclick="openAddToBuyList()" class="btn-primary">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add item to buy list
  </button>
</div>

<!-- Filters -->
<form method="GET" class="card mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
  <div class="lg:col-span-2">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Search</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Item name or supplier..." class="input-field">
  </div>
  <div>
    <label class="mb-1 block text-xs font-semibold text-ink-500">Category</label>
    <select name="category" class="input-field">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): if (!$c['parent_id']) continue; ?>
        <option value="<?= $c['id'] ?>" <?= (string)$category_id === (string)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-xs font-semibold text-ink-500">Status</label>
    <select name="status" class="input-field">
      <option value="">Low stock or flagged</option>
      <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low stock only</option>
      <option value="flagged" <?= $status === 'flagged' ? 'selected' : '' ?>>Flagged only</option>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-xs font-semibold text-ink-500">Location</label>
    <input type="text" name="location" value="<?= h($location) ?>" list="knownLocations" placeholder="Type or pick a location" class="input-field">
    <datalist id="knownLocations">
      <?php foreach ($known_locations as $loc): ?>
        <option value="<?= h($loc) ?>">
      <?php endforeach; ?>
    </datalist>
  </div>
  <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
    <button type="submit" class="btn-primary">Apply filters</button>
    <a href="<?= base_path() ?>/dashboard.php" class="btn-secondary">Clear</a>
  </div>
</form>

<!-- Results -->
<?php if (empty($items) && empty($extra_items)): ?>
  <div class="card flex flex-col items-center py-12 text-center">
    <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-cafe-50">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#61b425" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
    </div>
    <p class="font-display font-bold text-ink-700">Nothing needed right now</p>
    <p class="text-sm text-ink-400 mt-1">Everything is stocked above its reorder level.</p>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
    <?php foreach ($items as $item):
      $is_low = $item['quantity'] <= $item['reorder_level'];
      $data = json_encode($item);
    ?>
      <div class="card">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-display font-bold text-ink-800 leading-tight"><?= h($item['name']) ?></p>
            <p class="text-xs text-ink-400 mt-0.5"><?= h($item['parent_name']) ?> · <?= h($item['category_name']) ?></p>
          </div>
          <?php if ($is_low): ?>
            <span class="badge-low flex-shrink-0">Low stock</span>
          <?php else: ?>
            <span class="badge-flagged flex-shrink-0">Flagged</span>
          <?php endif; ?>
        </div>

        <div class="mt-3 flex items-baseline gap-1">
          <span class="text-2xl font-bold text-ink-800"><?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?></span>
          <span class="text-sm text-ink-400"><?= h($item['unit']) ?></span>
          <span class="text-xs text-ink-300 ml-1">/ reorder at <?= rtrim(rtrim(number_format($item['reorder_level'], 2), '0'), '.') ?></span>
        </div>

        <?php if ($item['buy_location']): ?>
          <p class="mt-2 text-xs text-ink-500 flex items-center gap-1">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= h($item['buy_location']) ?>
          </p>
        <?php endif; ?>
        <?php if ($item['supplier_contact']): ?>
          <p class="text-xs text-ink-400 mt-0.5">📞 <?= h($item['supplier_contact']) ?></p>
        <?php endif; ?>

        <!-- Quantity needed to buy -->
        <form method="POST" action="<?= base_path() ?>/actions.php" class="mt-3 flex items-end gap-2">
          <input type="hidden" name="do" value="set_quantity_needed">
          <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
          <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
          <div class="flex-1">
            <label class="mb-1 block text-xs font-semibold text-ink-500">Qty needed to buy</label>
            <input type="number" step="0.01" min="0" name="quantity_needed" value="<?= $item['quantity_needed'] !== null ? rtrim(rtrim(number_format($item['quantity_needed'],2),'0'),'.') : '' ?>" placeholder="0" class="input-field !py-1.5">
          </div>
          <button type="submit" class="btn-secondary !py-1.5 !px-3 text-xs">Save</button>
        </form>

        <div class="mt-3 flex flex-col gap-2">
          <button onclick='openAdjust(<?= $data ?>)' class="btn-primary w-full !py-2 text-xs">Update Stock</button>
          <div class="flex gap-2">
            <button onclick="openPurchase('<?= h(addslashes($item['name'])) ?>', <?= $item['id'] ?>, null, <?= $item['quantity_needed'] !== null ? $item['quantity_needed'] : 'null' ?>)" class="btn-secondary flex-1 !py-1.5 text-xs">Log Purchase</button>
            <?php if ($item['flagged_for_purchase']): ?>
              <form method="POST" action="<?= base_path() ?>/actions.php">
                <input type="hidden" name="do" value="unflag_item">
                <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="rounded-full p-2 text-ink-400 hover:bg-ink-50 hover:text-red-600" title="Remove from buy list">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php foreach ($extra_items as $ex): ?>
      <div class="card">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-display font-bold text-ink-800 leading-tight"><?= h($ex['name']) ?></p>
            <p class="text-xs text-ink-400 mt-0.5">Not in inventory</p>
          </div>
          <span class="badge-flagged flex-shrink-0">Extra</span>
        </div>

        <?php if ($ex['buy_location']): ?>
          <p class="mt-2 text-xs text-ink-500 flex items-center gap-1">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= h($ex['buy_location']) ?>
          </p>
        <?php endif; ?>
        <?php if ($ex['supplier_contact']): ?>
          <p class="text-xs text-ink-400 mt-0.5">📞 <?= h($ex['supplier_contact']) ?></p>
        <?php endif; ?>

        <form method="POST" action="<?= base_path() ?>/actions.php" class="mt-3 flex items-end gap-2">
          <input type="hidden" name="do" value="set_quantity_needed">
          <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
          <input type="hidden" name="extra_item_id" value="<?= $ex['id'] ?>">
          <div class="flex-1">
            <label class="mb-1 block text-xs font-semibold text-ink-500">Qty needed to buy (<?= h($ex['unit']) ?>)</label>
            <input type="number" step="0.01" min="0" name="quantity_needed" value="<?= $ex['quantity_needed'] !== null ? rtrim(rtrim(number_format($ex['quantity_needed'],2),'0'),'.') : '' ?>" placeholder="0" class="input-field !py-1.5">
          </div>
          <button type="submit" class="btn-secondary !py-1.5 !px-3 text-xs">Save</button>
        </form>

        <div class="mt-3 flex gap-2">
          <button onclick="openPurchase('<?= h(addslashes($ex['name'])) ?>', null, <?= $ex['id'] ?>, <?= $ex['quantity_needed'] !== null ? $ex['quantity_needed'] : 'null' ?>)" class="btn-primary flex-1 !py-2 text-xs">Log Purchase</button>
          <form method="POST" action="<?= base_path() ?>/actions.php" onsubmit="return confirm('Remove this item from the buy list?');">
            <input type="hidden" name="do" value="delete_extra_item">
            <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
            <input type="hidden" name="extra_item_id" value="<?= $ex['id'] ?>">
            <button type="submit" class="rounded-full p-2 text-ink-400 hover:bg-red-50 hover:text-red-600" title="Remove">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add item to buy list modal -->
<div id="addBuyModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4 overflow-y-auto">
  <div class="card w-full max-w-md my-8">
    <h3 class="font-display text-lg font-bold text-ink-800 mb-4">Add item to buy list</h3>

    <div class="flex rounded-full bg-ink-50 p-1 mb-4">
      <button type="button" id="tabInventory" onclick="switchBuyTab('inventory')" class="flex-1 rounded-full py-2 text-sm font-semibold transition">From inventory</button>
      <button type="button" id="tabNew" onclick="switchBuyTab('new')" class="flex-1 rounded-full py-2 text-sm font-semibold transition">New item</button>
    </div>

    <!-- From inventory -->
    <form id="formInventory" method="POST" action="<?= base_path() ?>/actions.php" class="space-y-4">
      <input type="hidden" name="do" value="flag_item">
      <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Item</label>
        <select name="item_id" class="input-field" required>
          <?php foreach ($all_items_for_flag as $it): ?>
            <option value="<?= $it['id'] ?>"><?= h($it['parent_name']) ?> — <?= h($it['category_name']) ?> — <?= h($it['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Qty needed to buy <span class="font-normal text-ink-400">(optional)</span></label>
        <input type="number" step="0.01" min="0" name="quantity_needed" class="input-field" placeholder="0">
      </div>
      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('addBuyModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Add to buy list</button>
      </div>
    </form>

    <!-- New (non-inventory) item -->
    <form id="formNew" method="POST" action="<?= base_path() ?>/actions.php" class="space-y-4 hidden">
      <input type="hidden" name="do" value="add_extra_item">
      <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Item name</label>
        <input type="text" name="name" class="input-field" placeholder="Something you need but won't track in inventory" required>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Qty needed <span class="font-normal text-ink-400">(optional)</span></label>
          <input type="number" step="0.01" min="0" name="quantity_needed" class="input-field" placeholder="0">
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Unit</label>
          <select name="unit" id="newItemUnit" class="input-field" onchange="toggleNewItemUnitOther()">
            <?php foreach (unit_options() as $u): ?>
              <option value="<?= h($u) ?>"><?= h($u) ?></option>
            <?php endforeach; ?>
            <option value="__other__">Others – specify</option>
          </select>
          <input type="text" name="unit_other" id="newItemUnitOther" class="input-field mt-2 hidden" placeholder="Custom unit">
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Where to buy <span class="font-normal text-ink-400">(optional)</span></label>
        <input type="text" name="buy_location" list="knownLocations" class="input-field" placeholder="Supplier or store location">
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Supplier contact <span class="font-normal text-ink-400">(optional)</span></label>
        <input type="text" name="supplier_contact" class="input-field" placeholder="Phone, email, or Viber/FB name">
      </div>
      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('addBuyModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Add to buy list</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchBuyTab(tab) {
  const invBtn = document.getElementById('tabInventory');
  const newBtn = document.getElementById('tabNew');
  const invForm = document.getElementById('formInventory');
  const newForm = document.getElementById('formNew');
  if (tab === 'inventory') {
    invBtn.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition bg-cafe-500 text-white shadow-soft';
    newBtn.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition text-ink-500';
    invForm.classList.remove('hidden');
    newForm.classList.add('hidden');
  } else {
    newBtn.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition bg-cafe-500 text-white shadow-soft';
    invBtn.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition text-ink-500';
    newForm.classList.remove('hidden');
    invForm.classList.add('hidden');
  }
}
function toggleNewItemUnitOther() {
  const sel = document.getElementById('newItemUnit');
  const other = document.getElementById('newItemUnitOther');
  if (sel.value === '__other__') { other.classList.remove('hidden'); } else { other.classList.add('hidden'); }
}
function openAddToBuyList() {
  switchBuyTab('inventory');
  openModal('addBuyModal');
}
</script>

<?php require_once __DIR__ . '/includes/stock_modal.php'; ?>
<?php require_once __DIR__ . '/includes/purchase_modal.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
