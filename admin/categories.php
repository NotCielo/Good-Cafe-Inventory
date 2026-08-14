<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_admin();

$page_title = 'Categories';
$active = 'categories';
$flash = '';

// --- Handle actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $parent_id = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
        if ($name !== '') {
            $stmt = $mysqli->prepare('INSERT INTO categories (parent_id, name) VALUES (?, ?)');
            $stmt->bind_param('is', $parent_id, $name);
            $stmt->execute();
            $flash = 'Category added.';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $mysqli->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $flash = 'Category updated.';
        }
    } elseif ($action === 'archive') {
        $id = (int)$_POST['id'];
        $to = (int)$_POST['to']; // 1 = archive, 0 = restore
        $stmt = $mysqli->prepare('UPDATE categories SET is_archived = ? WHERE id = ?');
        $stmt->bind_param('ii', $to, $id);
        $stmt->execute();
        $flash = $to ? 'Category archived.' : 'Category restored.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $mysqli->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $flash = 'Category deleted.';
    }
}

$q = trim($_GET['q'] ?? '');
$show_archived = isset($_GET['archived']);

$sql = 'SELECT * FROM categories WHERE is_archived = ?' . ($q !== '' ? ' AND name LIKE ?' : '') . ' ORDER BY parent_id IS NULL DESC, parent_id, name';
$stmt = $mysqli->prepare($sql);
$archived_flag = $show_archived ? 1 : 0;
if ($q !== '') {
    $like = "%$q%";
    $stmt->bind_param('is', $archived_flag, $like);
} else {
    $stmt->bind_param('i', $archived_flag);
}
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$top_levels = array_values(array_filter($all, fn($c) => $c['parent_id'] === null));
$children = [];
foreach ($all as $c) {
    if ($c['parent_id'] !== null) $children[$c['parent_id']][] = $c;
}

// For the "parent" dropdown when adding a sub-category, use active (non-archived) top levels regardless of current filter
$active_top_levels = $mysqli->query('SELECT id, name FROM categories WHERE parent_id IS NULL AND is_archived = 0 ORDER BY name')->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-bold text-ink-800">Categories</h1>
    <p class="text-sm text-ink-400">Departments (e.g. Kitchen, Station) and their sub-categories.</p>
  </div>
  <button onclick="openModal('addModal')" class="btn-primary">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add category
  </button>
</div>

<?php if ($flash): ?>
  <div data-flash class="mb-4 rounded-xl bg-cafe-50 px-4 py-3 text-sm font-medium text-cafe-700"><?= h($flash) ?></div>
<?php endif; ?>

<form method="GET" class="mb-5 flex flex-wrap gap-3 items-end">
  <div class="flex-1 min-w-[180px]">
    <label class="mb-1 block text-xs font-semibold text-ink-500">Search categories</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search..." class="input-field">
  </div>
  <label class="flex items-center gap-2 text-sm text-ink-500 pb-2.5">
    <input type="checkbox" name="archived" value="1" <?= $show_archived ? 'checked' : '' ?> onchange="this.form.submit()">
    Show archived
  </label>
  <button type="submit" class="btn-secondary">Search</button>
</form>

<?php if (empty($top_levels)): ?>
  <div class="card text-center py-10 text-sm text-ink-400">No categories found.</div>
<?php endif; ?>

<div class="space-y-4">
<?php foreach ($top_levels as $top): ?>
  <div class="card">
    <div class="flex items-center justify-between gap-2 mb-3">
      <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cafe-100 text-cafe-700 font-display font-bold text-sm"><?= mb_substr($top['name'], 0, 1) ?></span>
        <p class="font-display font-bold text-ink-800"><?= h($top['name']) ?></p>
        <?php if ($top['is_archived']): ?><span class="badge-flagged">Archived</span><?php endif; ?>
      </div>
      <div class="flex gap-1.5">
        <button onclick="openEdit(<?= $top['id'] ?>, '<?= h(addslashes($top['name'])) ?>')" class="rounded-lg p-2 text-ink-400 hover:bg-ink-50 hover:text-cafe-600" title="Edit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <form method="POST" onsubmit="return confirm('<?= $top['is_archived'] ? 'Restore' : 'Archive' ?> this category?');">
          <input type="hidden" name="action" value="archive">
          <input type="hidden" name="id" value="<?= $top['id'] ?>">
          <input type="hidden" name="to" value="<?= $top['is_archived'] ? 0 : 1 ?>">
          <button type="submit" class="rounded-lg p-2 text-ink-400 hover:bg-ink-50 hover:text-amber-600" title="<?= $top['is_archived'] ? 'Restore' : 'Archive' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
          </button>
        </form>
        <form method="POST" onsubmit="return confirm('Delete this category permanently? This also removes its sub-categories.');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $top['id'] ?>">
          <button type="submit" class="rounded-lg p-2 text-ink-400 hover:bg-red-50 hover:text-red-600" title="Delete">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
          </button>
        </form>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <?php foreach (($children[$top['id']] ?? []) as $sub): ?>
        <div class="flex items-center gap-1 rounded-full border border-ink-100 pl-3 pr-1.5 py-1 text-xs font-semibold text-ink-600 <?= $sub['is_archived'] ? 'opacity-50' : '' ?>">
          <?= h($sub['name']) ?>
          <?php if ($sub['is_archived']): ?><span class="text-amber-600">(archived)</span><?php endif; ?>
          <button onclick="openEdit(<?= $sub['id'] ?>, '<?= h(addslashes($sub['name'])) ?>')" class="rounded-full p-1 text-ink-400 hover:bg-ink-100" title="Edit">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <form method="POST" onsubmit="return confirm('<?= $sub['is_archived'] ? 'Restore' : 'Archive' ?> this sub-category?');">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            <input type="hidden" name="to" value="<?= $sub['is_archived'] ? 0 : 1 ?>">
            <button type="submit" class="rounded-full p-1 text-ink-400 hover:bg-ink-100" title="<?= $sub['is_archived'] ? 'Restore' : 'Archive' ?>">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
      <button onclick="openAddSub(<?= $top['id'] ?>)" class="rounded-full border border-dashed border-cafe-300 px-3 py-1 text-xs font-semibold text-cafe-600 hover:bg-cafe-50">+ Add sub-category</button>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Add Modal -->
<div id="addModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4">
  <div class="card w-full max-w-sm">
    <h3 class="font-display text-lg font-bold text-ink-800 mb-4">Add category</h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Type</label>
        <select id="parentSelect" name="parent_id" class="input-field">
          <option value="">Top-level department (e.g. Kitchen, Station)</option>
          <?php foreach ($active_top_levels as $t): ?>
            <option value="<?= $t['id'] ?>">Sub-category of: <?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Name</label>
        <input type="text" name="name" id="addName" class="input-field" placeholder="e.g. Rice Meals, or Others - specify" required>
      </div>
      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('addModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4">
  <div class="card w-full max-w-sm">
    <h3 class="font-display text-lg font-bold text-ink-800 mb-4">Edit category</h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Name</label>
        <input type="text" name="name" id="editName" class="input-field" required>
      </div>
      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('editModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Save changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, name) {
  document.getElementById('editId').value = id;
  document.getElementById('editName').value = name;
  openModal('editModal');
}
function openAddSub(parentId) {
  openModal('addModal');
  document.getElementById('parentSelect').value = parentId;
  document.getElementById('addName').focus();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
