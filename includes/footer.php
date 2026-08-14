<?php if (is_logged_in()): ?>
    </div>
  </main>
</div>

<script>
  const navToggle = document.getElementById('navToggle');
  const navClose = document.getElementById('navClose');
  const navDrawer = document.getElementById('navDrawer');
  const navOverlay = document.getElementById('navOverlay');
  function openNav() { navDrawer.classList.remove('hidden'); }
  function closeNav() { navDrawer.classList.add('hidden'); }
  if (navToggle) navToggle.addEventListener('click', openNav);
  if (navClose) navClose.addEventListener('click', closeNav);
  if (navOverlay) navOverlay.addEventListener('click', closeNav);
</script>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
