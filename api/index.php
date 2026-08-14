<?php

// Vercel PHP entry point
// Route the incoming PHP request to the existing application file.

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Remove leading slash
$requestPath = ltrim($requestPath, '/');

// Default entry point
if ($requestPath === '' || $requestPath === 'index.php') {
    require_once dirname(__DIR__) . '/index.php';
    exit;
}

// Only allow PHP application files
if (!str_ends_with($requestPath, '.php')) {
    http_response_code(404);
    exit('Not Found');
}

// Prevent directory traversal
if (str_contains($requestPath, '..')) {
    http_response_code(400);
    exit('Invalid request');
}

$basePath = dirname(__DIR__);
$filePath = $basePath . '/' . $requestPath;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('PHP file not found');
}

require_once $filePath;