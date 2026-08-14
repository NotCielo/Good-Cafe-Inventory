<?php
// Good Cafe Inventory - Database connection

// Local Laragon by default.
// When deployed to Vercel, set APP_ENV=production.

if (getenv('APP_ENV') === 'production') {

    // =========================
    // RAILWAY MYSQL - VERCEL
    // =========================

    $host = getenv('MYSQLHOST');
    $port = getenv('MYSQLPORT') ?: 3306;
    $name = getenv('MYSQLDATABASE');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');

} else {

    // =========================
    // LOCAL LARAGON MYSQL
    // =========================

    $host = 'localhost';
    $port = 3306;
    $name = 'good_cafe';
    $user = 'root';
    $pass = '';
}

$mysqli = new mysqli(
    $host,
    $user,
    $pass,
    $name,
    (int)$port
);

if ($mysqli->connect_errno) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');