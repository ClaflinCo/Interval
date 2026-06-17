<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
ini_set('display_errors', 0);
error_reporting(0);

try {
    require 'config.php';
    
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed.");
    }

    $username = $_SESSION['username'] ?? '';
    $user_role = $_SESSION['role'] ?? '';

    if (empty($username)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }

    // Only Admin can update/delete projects directly
    if ($user_role !== 'Admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden. Admin role required."]);
        exit;
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Invalid JSON input.");
    }

    $action = trim($data['action'] ?? '');
    $originalName = trim($data['originalName'] ?? '');

    if (empty($action) || empty($originalName)) {
        throw new Exception("Missing required fields (action or originalName).");
    }

    if ($action === 'delete') {
        $conn->begin_transaction();
        
        // 1. Delete from projects
        $stmt = $conn->prepare("DELETE FROM projects WHERE name = ?");
        $stmt->bind_param("s", $originalName);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete project: " . $stmt->error);
        }
        $stmt->close();

        // 2. Delete from project_allotments
        $stmt = $conn->prepare("DELETE FROM project_allotments WHERE project = ?");
        $stmt->bind_param("s", $originalName);
        $stmt->execute();
        $stmt->close();

        // 3. Delete from time_entries
        $stmt = $conn->prepare("DELETE FROM time_entries WHERE project = ?");
        $stmt->bind_param("s", $originalName);
        $stmt->execute();
        $stmt->close();
        
        // 4. Delete from project_change_requests
        $stmt = $conn->prepare("DELETE FROM project_change_requests WHERE project = ?");
        $stmt->bind_param("s", $originalName);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Project deleted successfully."]);
        exit;
        
    } elseif ($action === 'update') {
        $name = trim($data['name'] ?? '');
        $customer = trim($data['customer'] ?? '');
        $assigned = trim($data['assigned'] ?? '');
        $allotment = isset($data['allotment']) ? (float)$data['allotment'] : 0.00;
        $month = trim($data['month'] ?? '');
        
        $servicesRaw = $data['services'] ?? '';
        if (is_array($servicesRaw)) {
            $services = implode(', ', array_filter(array_map('trim', $servicesRaw)));
        } else {
            $services = trim($servicesRaw);
        }

        if (empty($name)) {
            throw new Exception("Project name cannot be empty.");
        }

        $conn->begin_transaction();

        // Check if renaming to a name that already exists
        if (strcasecmp($name, $originalName) !== 0) {
            $stmt = $conn->prepare("SELECT name FROM projects WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $stmt->close();
                throw new Exception("A project with the name '$name' already exists.");
            }
            $stmt->close();
        }

        // Update projects table
        $stmt = $conn->prepare("UPDATE projects SET name = ?, customer = ?, allotment = ?, assigned = ?, services = ? WHERE name = ?");
        $stmt->bind_param("ssdsss", $name, $customer, $allotment, $assigned, $services, $originalName);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update project data: " . $stmt->error);
        }
        $stmt->close();

        // Update other references if name changed
        if (strcasecmp($name, $originalName) !== 0) {
            $stmt = $conn->prepare("UPDATE project_allotments SET project = ? WHERE project = ?");
            $stmt->bind_param("ss", $name, $originalName);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE time_entries SET project = ? WHERE project = ?");
            $stmt->bind_param("ss", $name, $originalName);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE project_change_requests SET project = ? WHERE project = ?");
            $stmt->bind_param("ss", $name, $originalName);
            $stmt->execute();
            $stmt->close();
        }

        // Ensure the active month's allotment is updated/created
        if (!empty($month)) {
            $stmt = $conn->prepare("INSERT INTO project_allotments (month, project, allotment, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE allotment = VALUES(allotment), updated_by = VALUES(updated_by)");
            $stmt->bind_param("ssds", $month, $name, $allotment, $username);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Project updated successfully."]);
        exit;
    } else {
        throw new Exception("Invalid action: " . $action);
    }

} catch (Throwable $t) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $t->getMessage()]);
}
?>
