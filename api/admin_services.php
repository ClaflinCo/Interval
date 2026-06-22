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

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = trim($data['action'] ?? '');
    
    if ($action === 'create') {
        $service = trim($data['service'] ?? '');
        if (empty($service)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Service name cannot be empty."]);
            exit;
        }
        
        $createdBy = $_SESSION['username'] ?? 'Admin';
        
        $stmt = $conn->prepare("INSERT INTO services (service, created_by) VALUES (?, ?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("ss", $service, $createdBy);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Service created successfully."]);
        } else {
            if ($conn->errno === 1062) { // Duplicate entry error code in MySQL
                echo json_encode(["success" => false, "message" => "Service already exists."]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to create service: " . $stmt->error]);
            }
        }
        $stmt->close();
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid service ID."]);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Service deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to delete service: " . $stmt->error]);
        }
        $stmt->close();
        exit;
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid action."]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}
?>
