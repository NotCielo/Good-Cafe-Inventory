<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function current_user_name(): ?string {
    return $_SESSION['full_name'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (current_role() !== 'admin') {
        header('Location: /dashboard.php');
        exit;
    }
}

function base_path(): string {
    // Adjusts links depending on whether we're inside /admin or /staff
    return (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false)
        ? '..'
        : '.';
}

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_set(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function nav_items(?string $role): array {
    $items = [];
    if ($role === 'admin') {
        $items['overview'] = ['label' => 'Overview', 'href' => '/admin/overview.php'];
    }
    $items['dashboard'] = ['label' => 'Needs to Buy', 'href' => '/dashboard.php'];
    $items['stock']     = ['label' => 'Update Stock',  'href' => '/staff/update_stock.php'];
    if ($role === 'admin') {
        $items['items']      = ['label' => 'Items',      'href' => '/admin/items.php'];
        $items['categories'] = ['label' => 'Categories',  'href' => '/admin/categories.php'];
        $items['logs']       = ['label' => 'Stock Log',   'href' => '/admin/stock_logs.php'];
    }
    return $items;
}
