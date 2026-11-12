<!-- Update Stock Modal (shared partial) -->
<div id="adjustModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4">
  <div class="card w-full max-w-sm">
    <h3 id="adjustTitle" class="font-display text-lg font-bold text-ink-800">Item name</h3>
    <p id="adjustCurrent" class="text-sm text-ink-400 mb-4">Current: 0</p>

    <form method="POST" action="<?= base_path() ?>/actions.php" class="space-y-4">
      <input type="hidden" name="do" value="update_stock">
      <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="item_id" id="adjustItemId">

      <div class="flex rounded-full bg-ink-50 p-1">
        <button type="button" id="btnAdd" onclick="setDirection('add')" class="flex-1 rounded-full py-2 text-sm font-semibold transition">+ Add stock</button>
        <button type="button" id="btnSub" onclick="setDirection('subtract')" class="flex-1 rounded-full py-2 text-sm font-semibold transition">− Subtract stock</button>
      </div>
      <input type="hidden" name="direction" id="adjustDirection" value="add">

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Amount</label>
        <input type="number" step="0.01" min="0.01" name="amount" id="adjustAmount" class="input-field text-lg font-bold" required>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Your name</label>
        <input type="text" name="staff_name" id="adjustStaffName" class="input-field" placeholder="Type your name" required>
        <p class="mt-1 text-xs text-ink-400">Date and time are recorded automatically when you save.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('adjustModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function setDirection(dir) {
  document.getElementById('adjustDirection').value = dir;
  const add = document.getElementById('btnAdd');
  const sub = document.getElementById('btnSub');
  if (dir === 'add') {
    add.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition bg-cafe-500 text-white shadow-soft';
    sub.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition text-ink-500';
  } else {
    sub.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition bg-red-500 text-white shadow-soft';
    add.className = 'flex-1 rounded-full py-2 text-sm font-semibold transition text-ink-500';
  }
}
function openAdjust(item) {
  document.getElementById('adjustTitle').textContent = item.name;
  document.getElementById('adjustCurrent').textContent = 'Current: ' + item.quantity + ' ' + item.unit;
  document.getElementById('adjustItemId').value = item.id;
  document.getElementById('adjustAmount').value = '';
  document.getElementById('adjustStaffName').value = '';
  setDirection('add');
  openModal('adjustModal');
}
</script>
