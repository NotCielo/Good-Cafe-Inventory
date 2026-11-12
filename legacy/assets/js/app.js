// Good Cafe Inventory — shared UI helpers

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

// Auto-dismiss flash messages after 4s
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-flash]').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });

  // Close any open modal when clicking its backdrop
  document.querySelectorAll('[data-modal-backdrop]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (e.target === el) el.classList.add('hidden');
    });
  });
});
