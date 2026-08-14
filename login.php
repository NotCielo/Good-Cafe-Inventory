<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in · Good Cafe Inventory</title>
<link rel="stylesheet" href="/assets/css/tailwind.css">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
</head>
<body class="min-h-screen bg-cafe-500 font-body antialiased">
<div class="min-h-screen flex items-center justify-center px-4 py-10">
  <div class="w-full max-w-sm">

    <div class="mb-6 flex flex-col items-center text-center">
      <img src="/assets/img/logo.png" alt="Good Cafe" class="h-16 w-16 rounded-full shadow-card mb-3">
      <h1 class="font-display text-2xl font-bold text-white">good<span class="text-white">cafe</span></h1>
      <p class="text-cafe-50 text-sm mt-1">Inventory Management</p>
    </div>

    <div class="card">
      <h2 class="font-display text-lg font-bold text-ink-800 mb-1">Welcome back</h2>
      <p class="text-sm text-ink-400 mb-5">Log in to manage stock and supplies.</p>

      <?php if ($error): ?>
        <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Username</label>
          <input type="text" name="username" class="input-field" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-600">Password</label>
          <input type="password" name="password" class="input-field" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-primary w-full mt-2">Log in</button>
      </form>
    </div>

    <p class="text-center text-xs text-cafe-50 mt-5">Good Cafe © <?= date('Y') ?></p>
  </div>
</div>
</body>
</html>
