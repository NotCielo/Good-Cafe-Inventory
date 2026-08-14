<!-- Log Purchase Modal (shared partial) -->
<div id="purchaseModal" data-modal-backdrop class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 p-4">
  <div class="card w-full max-w-sm">
    <h3 id="purchaseTitle" class="font-display text-lg font-bold text-ink-800 mb-1">Log purchase</h3>
    <p class="text-sm text-ink-400 mb-4">For the record — this does not change stock quantity.</p>

    <form method="POST" action="<?= base_path() ?>/actions.php" class="space-y-4">
      <input type="hidden" name="do" value="log_purchase">
      <input type="hidden" name="redirect_to" value="<?= h($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="item_id" id="purchaseItemId">
      <input type="hidden" name="extra_item_id" id="purchaseExtraItemId">
      <input type="hidden" name="item_name" id="purchaseItemName">
      <input type="hidden" name="quantity_needed" id="purchaseQuantityNeeded">

      <div>
        <label class="mb-1.5 block text-sm font-semibold text-ink-600">Your name</label>
        <input type="text" name="logged_by" id="purchaseLoggedBy" class="input-field" placeholder="Type your name" required>
        <p class="mt-1 text-xs text-ink-400">Date and time are recorded automatically when you save.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button type="button" onclick="closeModal('purchaseModal')" class="btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1">Log Purchase</button>
      </div>
    </form>
  </div>
</div>

<script>
function openPurchase(name, itemId, extraItemId, quantityNeeded) {
  document.getElementById('purchaseTitle').textContent = 'Log purchase — ' + name;
  document.getElementById('purchaseItemId').value = itemId || '';
  document.getElementById('purchaseExtraItemId').value = extraItemId || '';
  document.getElementById('purchaseItemName').value = name;
  document.getElementById('purchaseQuantityNeeded').value = (quantityNeeded !== null && quantityNeeded !== undefined) ? quantityNeeded : '';
  document.getElementById('purchaseLoggedBy').value = '';
  openModal('purchaseModal');
}
</script>
