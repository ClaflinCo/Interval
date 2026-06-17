<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
require 'config.php';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit;
}

$email = trim($data['email'] ?? '');
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$passwordConfirm = $data['passwordConfirm'] ?? '';

if (empty($email) || empty($username) || empty($password) || empty($passwordConfirm)) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Please enter a valid work email."]);
    exit;
}

if ($password !== $passwordConfirm) {
    echo json_encode(["success" => false, "message" => "Passwords do not match."]);
    exit;
}

// Ensure first initial last name format or at least some basic username length check (e.g. >= 3 characters)
if (strlen($username) < 3) {
    echo json_encode(["success" => false, "message" => "Username must be at least 3 characters long."]);
    exit;
}

// 1. Check if username is already taken in the users table
$userCheck = $conn->prepare("SELECT id FROM users WHERE username = ?");
if (!$userCheck) {
    echo json_encode(["success" => false, "message" => "Database check prepare error."]);
    exit;
}
$userCheck->bind_param("s", $username);
$userCheck->execute();
$userCheckRes = $userCheck->get_result();
if ($userCheckRes->num_rows > 0) {
    $userCheck->close();
    echo json_encode(["success" => false, "message" => "This username is unavailable"]);
    exit;
}
$userCheck->close();

// 2. Check if username already has a pending sign-up request in account_requests table
$reqCheck = $conn->prepare("SELECT id FROM account_requests WHERE username = ? AND status = 'Pending'");
if (!$reqCheck) {
    echo json_encode(["success" => false, "message" => "Database check prepare error."]);
    exit;
}
$reqCheck->bind_param("s", $username);
$reqCheck->execute();
$reqCheckRes = $reqCheck->get_result();
if ($reqCheckRes->num_rows > 0) {
    $reqCheck->close();
    echo json_encode(["success" => false, "message" => "A registration request for this username is already pending review."]);
    exit;
}
$reqCheck->close();

// Hash password with PASSWORD_BCRYPT (ensures no plain-text passwords stored)
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$stmt = $conn->prepare("INSERT INTO account_requests (email, username, password_hash, ip_address) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database prepare statement failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("ssss", $email, $username, $passwordHash, $ipAddress);
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Sign up request submitted successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to submit sign up request: " . $stmt->error]);
}
$stmt->close();
?>
