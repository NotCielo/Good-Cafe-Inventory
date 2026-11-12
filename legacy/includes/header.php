<?php
// Expects: $page_title (string), $active (string key for nav highlighting)
$bp = base_path();
$role = current_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? 'Good Cafe') ?> · Good Cafe Inventory</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
</head>
<body class="min-h-screen bg-ink-50 font-body text-ink-800 antialiased">

<?php if (is_logged_in()): ?>
<div class="min-h-screen flex flex-col">

  <!-- Top bar -->
  <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-ink-100">
    <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button id="navToggle" class="md:hidden -ml-1 rounded-lg p-2 text-ink-600 hover:bg-ink-100" aria-label="Open menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="<?= $bp ?>/dashboard.php" class="flex items-center gap-2">
          <img src="/assets/img/logo.png" alt="Good Cafe" class="h-9 w-9 rounded-full shadow-soft">
          <span class="font-display text-lg font-bold text-cafe-700 leading-none">good<span class="text-cafe-700">cafe</span></span>
        </a>
      </div>

      <div class="flex items-center gap-3">
        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-cafe-50 px-3 py-1 text-xs font-semibold text-cafe-700">
          <?= $role === 'admin' ? 'Admin' : 'Staff' ?> · <?= h(current_user_name()) ?>
        </span>
        <a href="<?= $bp ?>/logout.php" class="rounded-full p-2 text-ink-500 hover:bg-ink-100" title="Log out" aria-label="Log out">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </div>

    <!-- Desktop nav -->
    <nav class="hidden md:block border-t border-ink-100">
      <div class="mx-auto max-w-6xl px-4 flex gap-1 text-sm font-semibold">
        <?php foreach (nav_items($role) as $key => $item): ?>
          <a href="<?= $bp . $item['href'] ?>"
             class="px-4 py-3 border-b-2 <?= ($active ?? '') === $key ? 'border-cafe-500 text-cafe-700' : 'border-transparent text-ink-500 hover:text-cafe-600' ?>">
            <?= h($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>
  </header>

  <!-- Mobile drawer -->
  <div id="navDrawer" class="fixed inset-0 z-40 hidden md:hidden">
    <div id="navOverlay" class="absolute inset-0 bg-ink-900/40"></div>
    <div class="absolute left-0 top-0 h-full w-72 bg-white shadow-card p-4 flex flex-col gap-1">
      <div class="flex items-center justify-between mb-4">
        <span class="flex items-center gap-2 font-display text-lg font-bold text-cafe-700">
          <img src="/assets/img/logo.png" alt="Good Cafe" class="h-8 w-8 rounded-full">
          good<span class="text-ink-800">cafe</span>
        </span>
        <button id="navClose" class="rounded-lg p-2 text-ink-500 hover:bg-ink-100" aria-label="Close menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <?php foreach (nav_items($role) as $key => $item): ?>
        <a href="<?= $bp . $item['href'] ?>"
           class="rounded-xl px-3 py-2.5 text-sm font-semibold <?= ($active ?? '') === $key ? 'bg-cafe-50 text-cafe-700' : 'text-ink-600 hover:bg-ink-50' ?>">
          <?= h($item['label']) ?>
        </a>
      <?php endforeach; ?>
      <div class="mt-auto pt-4 border-t border-ink-100 text-xs text-ink-400">
        Signed in as <span class="font-semibold text-ink-600"><?= h(current_user_name()) ?></span> (<?= h($role) ?>)
      </div>
    </div>
  </div>

  <main class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-5 sm:py-6">
<?php endif; ?>
