<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

// Restricted to Admin role
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $users = [];
    $sql = "SELECT id, username, display_name, role FROM users ORDER BY username ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'display_name' => $row['display_name'] ?? $row['username'],
                'role' => $row['role']
            ];
        }
    }
    echo json_encode([
        "success" => true,
        "users" => $users
    ]);
    exit;
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = (int)($data['user_id'] ?? 0);
    $newRole = trim($data['role'] ?? '');

    $allowedRoles = ['Admin', 'C-Suite', 'Supervisor', 'Employee', 'Viewer'];
    if ($userId <= 0 || !in_array($newRole, $allowedRoles)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid parameters."]);
        exit;
    }

    // Optional: prevent the logged-in admin from changing their own role
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "You cannot change your own role."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        error_log("Prepare statement failed in admin_users.php: " . $conn->error);
        echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
        exit;
    }

    $stmt->bind_param("si", $newRole, $userId);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User role updated successfully."]);
    } else {
        error_log("Failed to update user role in admin_users.php: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
    }
    $stmt->close();
    exit;
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}
?>
