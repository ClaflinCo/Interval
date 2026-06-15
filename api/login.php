<?php
error_reporting(0);
ini_set('display_errors', 0);

try {
header("Content-Type: application/json");
require 'config.php';

$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if(empty($username) || empty($password)){
    echo json_encode(["status" => "error", "message" => "Username and password required."]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 1. Clean up stale attempts (older than 30 days)
$cleanStmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($cleanStmt) {
    $cleanStmt->execute();
    $cleanStmt->close();
}

// 2. Count failed attempts by IP and username
$ipCount = 0;
$userCount = 0;

$ipStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ?");
if ($ipStmt) {
    $ipStmt->bind_param("s", $ip);
    $ipStmt->execute();
    $res = $ipStmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $ipCount = (int)$row['cnt'];
    }
    $ipStmt->close();
}

$userStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE username = ?");
if ($userStmt) {
    $userStmt->bind_param("s", $username);
    $userStmt->execute();
    $res = $userStmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $userCount = (int)$row['cnt'];
    }
    $userStmt->close();
}

$failures = max($ipCount, $userCount);

// Lockout after 5 failures
if ($failures >= 5) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Too many failed login attempts. Please reach out to an admin for assistance."]);
    exit;
}

// Incrementing delay
if ($failures > 0) {
    usleep($failures * 200000);
}

$stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if($user = $result->fetch_assoc()){
    if(password_verify($password, $user['password_hash'])){
        // Clear previous failed attempts
        $delStmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        if ($delStmt) {
            $delStmt->bind_param("ss", $ip, $username);
            $delStmt->execute();
            $delStmt->close();
        }

        // Start session and return success
        require_once 'bootstrap.php';
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];

        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "role" => $user['role'],
                "display" => $user['display_name'] ?? $user['username'],
            ]
        ]);
    } else {
        // Log failed attempt
        $logStmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("ss", $ip, $username);
            $logStmt->execute();
            $logStmt->close();
        }
        $remaining = max(0, 5 - ($failures + 1));
        echo json_encode(["status" => "error", "message" => "Incorrect username or password. {$remaining} attempts remaining."]);
    }
} else {
    // Log failed attempt
    $logStmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    if ($logStmt) {
        $logStmt->bind_param("ss", $ip, $username);
        $logStmt->execute();
        $logStmt->close();
    }
    $remaining = max(0, 5 - ($failures + 1));
    echo json_encode(["status" => "error", "message" => "Incorrect username or password. {$remaining} attempts remaining."]);
}

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>
