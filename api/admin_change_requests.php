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
    $requests = [];
    $sql = "SELECT id, supervisor, project, request_type, details, status, created_at FROM project_change_requests ORDER BY created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $requests[] = [
                'id' => $row['id'],
                'supervisor' => $row['supervisor'],
                'project' => $row['project'],
                'request_type' => $row['request_type'],
                'details' => $row['details'],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
        }
    }
    echo json_encode([
        "success" => true,
        "requests" => $requests
    ]);
    exit;
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = trim($data['id'] ?? '');
    $status = trim($data['status'] ?? ''); // 'Resolved' or 'Denied'

    if (empty($id) || !in_array($status, ['Resolved', 'Denied'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid parameters"]);
        exit;
    }

    // Retrieve supervisor and project info for notification
    $stmt = $conn->prepare("SELECT supervisor, project, request_type FROM project_change_requests WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        error_log("Prepare statement failed in admin_change_requests.php: " . $conn->error);
        echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
        exit;
    }
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $request = $res->fetch_assoc();
    $stmt->close();

    if (!$request) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Change request not found"]);
        exit;
    }

    $supervisor = $request['supervisor'];
    $project = $request['project'];
    $requestType = $request['request_type'];
    $adminUser = $_SESSION['username'];

    $conn->begin_transaction();
    $success = true;
    $errorMsg = '';

    // Update status in project_change_requests
    $upStmt = $conn->prepare("UPDATE project_change_requests SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    if (!$upStmt) {
        $conn->rollback();
        http_response_code(500);
        error_log("Prepare update stmt failed in admin_change_requests.php: " . $conn->error);
        echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
        exit;
    }
    $upStmt->bind_param("sss", $status, $adminUser, $id);
    if (!$upStmt->execute()) {
        $success = false;
        error_log("Failed to update status in admin_change_requests.php: " . $upStmt->error);
        $errorMsg = "An internal database error occurred.";
    }
    $upStmt->close();

    if ($success) {
        // Send a notification to the affected supervisor
        $notifId = bin2hex(random_bytes(16));
        $notifType = ($status === 'Resolved') ? 'info' : 'warning';
        $title = "Project Change Request: " . $project;
        $msg = "Your change request for project " . $project . " (" . $requestType . ") has been " . strtolower($status) . " by Admin " . $adminUser . ".";
        
        $notifSql = "INSERT INTO notifications (id, username, type, title, msg, month) VALUES (?, ?, ?, ?, ?, NULL)";
        $notifStmt = $conn->prepare($notifSql);
        if (!$notifStmt) {
            $success = false;
            error_log("Prepare notification stmt failed in admin_change_requests.php: " . $conn->error);
            $errorMsg = "An internal database error occurred.";
        } else {
            $notifStmt->bind_param("sssss", $notifId, $supervisor, $notifType, $title, $msg);
            if (!$notifStmt->execute()) {
                $success = false;
                error_log("Failed to execute notification in admin_change_requests.php: " . $notifStmt->error);
                $errorMsg = "An internal database error occurred.";
            }
            $notifStmt->close();
        }
    }

    if ($success) {
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Request updated successfully"]);
    } else {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $errorMsg]);
    }
    exit;
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}
?>
