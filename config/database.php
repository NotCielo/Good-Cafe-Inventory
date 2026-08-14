<?php

$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$name = getenv('MYSQLDATABASE');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

$mysqli = new mysqli(
    $host,
    $user,
    $pass,
    $name,
    (int) $port
);

if ($mysqli->connect_errno) {
    http_response_code(500);
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');