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
        $isSub = isset($data['is_subscription']) && $data['is_subscription'] ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO services (service, created_by, is_subscription) VALUES (?, ?, ?)");
        if (!$stmt) {
            http_response_code(500);
            error_log("Prepare statement failed in admin_services.php: " . $conn->error);
            echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
            exit;
        }
        
        $stmt->bind_param("ssi", $service, $createdBy, $isSub);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Service created successfully."]);
        } else {
            if ($conn->errno === 1062) { // Duplicate entry error code in MySQL
                echo json_encode(["success" => false, "message" => "Service already exists."]);
            } else {
                error_log("Failed to create service in admin_services.php: " . $stmt->error);
                echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
            }
        }
        $stmt->close();
        exit;
    } elseif ($action === 'update_subscription') {
        $id = (int)($data['id'] ?? 0);
        $isSub = isset($data['is_subscription']) && $data['is_subscription'] ? 1 : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid service ID."]);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE services SET is_subscription = ? WHERE id = ?");
        if (!$stmt) {
            http_response_code(500);
            error_log("Prepare statement failed in admin_services.php: " . $conn->error);
            echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
            exit;
        }
        
        $stmt->bind_param("ii", $isSub, $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Service subscription status updated."]);
        } else {
            error_log("Failed to update service subscription in admin_services.php: " . $stmt->error);
            echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
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
            error_log("Prepare statement failed in admin_services.php: " . $conn->error);
            echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
            exit;
        }
        
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Service deleted successfully."]);
        } else {
            error_log("Failed to delete service in admin_services.php: " . $stmt->error);
            echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
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
