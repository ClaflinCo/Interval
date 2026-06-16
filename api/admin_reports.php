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
    $reports = [];
    $sql = "SELECT id, username, details, status, created_at FROM admin_reports ORDER BY created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $reports[] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'details' => $row['details'],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
        }
    }
    echo json_encode([
        "success" => true,
        "reports" => $reports
    ]);
    exit;
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = trim($data['id'] ?? '');
    $status = trim($data['status'] ?? ''); // 'Resolved'

    if (empty($id) || $status !== 'Resolved') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid parameters"]);
        exit;
    }

    // Retrieve username and details info for notification
    $stmt = $conn->prepare("SELECT username, details FROM admin_reports WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $report = $res->fetch_assoc();
    $stmt->close();

    if (!$report) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Help report not found"]);
        exit;
    }

    $reportUser = $report['username'];
    $adminUser = $_SESSION['username'];

    $conn->begin_transaction();
    $success = true;
    $errorMsg = '';

    // Update status in admin_reports
    $upStmt = $conn->prepare("UPDATE admin_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    if (!$upStmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare update stmt failed: " . $conn->error]);
        exit;
    }
    $upStmt->bind_param("sss", $status, $adminUser, $id);
    if (!$upStmt->execute()) {
        $success = false;
        $errorMsg = "Failed to update report status: " . $upStmt->error;
    }
    $upStmt->close();

    if ($success) {
        // Send a notification to the user who submitted the report
        $notifId = bin2hex(random_bytes(16));
        $notifType = 'info';
        $title = "Help Report Resolved";
        $msg = "Your help report/request has been marked as resolved by Admin " . $adminUser . ".";
        
        $notifSql = "INSERT INTO notifications (id, username, type, title, msg, month) VALUES (?, ?, ?, ?, ?, NULL)";
        $notifStmt = $conn->prepare($notifSql);
        if (!$notifStmt) {
            $success = false;
            $errorMsg = "Prepare notification stmt failed: " . $conn->error;
        } else {
            $notifStmt->bind_param("sssss", $notifId, $reportUser, $notifType, $title, $msg);
            if (!$notifStmt->execute()) {
                $success = false;
                $errorMsg = "Failed to execute notification: " . $notifStmt->error;
            }
            $notifStmt->close();
        }
    }

    if ($success) {
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Report resolved successfully."]);
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
