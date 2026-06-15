<?php
// config.sample.php
// Copy this file to config.php

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

if (!$host || !$user || !$pass || !$dbname) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server configuration error."]);
    exit;
}

$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error"]);
    exit;
}

$conn->set_charset("utf8mb4");

// Ensure correct timezone matching frontend logic
date_default_timezone_set('America/New_York');
?>
