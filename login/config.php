<?php
// login/config.php

// Load environment variables from .env file if present
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (!empty($key)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

$host = getenv('LOGIN_DB_HOST');
$user = getenv('LOGIN_DB_USER');
$pass = getenv('LOGIN_DB_PASS');
$dbname = getenv('LOGIN_DB_NAME');

// Fallback to standard DB credentials if login specific ones are empty
if (empty($host)) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $dbname = getenv('DB_NAME');
}

$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log("Login Database Connection Failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error"]);
    exit;
}

$conn->set_charset("utf8mb4");

// Ensure correct timezone matching frontend logic
date_default_timezone_set('America/New_York');
?>
