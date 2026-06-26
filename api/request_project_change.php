<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require_once 'config.php';
require_once 'notify_n8n.php';

$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? '';

if (empty($username)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

if ($user_role !== 'Supervisor' && $user_role !== 'C-Suite') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Only Supervisors and C-Suite can request project changes."]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit;
}

$project = trim($data['project'] ?? '');
$requestType = trim($data['requestType'] ?? '');
$details = trim($data['details'] ?? '');

if (empty($project) || empty($requestType) || empty($details)) {
    echo json_encode(["success" => false, "message" => "Missing required fields (Project, Request Type, or Details)."]);
    exit;
}

// Get all Admin users
$adminRes = $conn->query("SELECT username FROM users WHERE role = 'Admin'");
if (!$adminRes) {
    echo json_encode(["success" => false, "message" => "Failed to fetch administrators: " . $conn->error]);
    exit;
}

$admins = [];
while ($row = $adminRes->fetch_assoc()) {
    $admins[] = $row['username'];
}

if (empty($admins)) {
    echo json_encode(["success" => false, "message" => "No administrators found in the system."]);
    exit;
}

// Start transaction to insert notifications
$conn->begin_transaction();
$success = true;
$errorMsg = '';

$requestId = bin2hex(random_bytes(16));
$reqSql = "INSERT INTO project_change_requests (id, supervisor, project, request_type, details, status) VALUES (?, ?, ?, ?, ?, 'Pending')";
$reqStmt = $conn->prepare($reqSql);
if (!$reqStmt) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Prepare change request statement failed: " . $conn->error]);
    exit;
}
$reqStmt->bind_param("sssss", $requestId, $username, $project, $requestType, $details);
if (!$reqStmt->execute()) {
    $reqStmt->close();
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Failed to save change request: " . $conn->error]);
    exit;
}
$reqStmt->close();

$notifSql = "INSERT INTO notifications (id, username, type, title, msg, month) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($notifSql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit;
}

$type = 'warning';
$title = "Project Change Request: " . $project;
$msg = $user_role . " " . $username . " requested: " . $requestType . ". Details: " . $details;
$month = null;

foreach ($admins as $admin) {
    $id = bin2hex(random_bytes(16));
    $stmt->bind_param("ssssss", $id, $admin, $type, $title, $msg, $month);
    if (!$stmt->execute()) {
        $success = false;
        $errorMsg = $stmt->error;
        break;
    }
}
$stmt->close();

if ($success) {
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Request submitted successfully to administrators."]);
    notify_n8n([
        "event" => "project_change_request",
        "source_user" => $username,
        "detail" => "Project: " . $project . " | Type: " . $requestType . " | Details: " . $details,
        "request_id" => $requestId,
        "project" => $project,
        "request_type" => $requestType,
        "occurred_at" => date('c')
    ]);
} else {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Failed to save notifications: " . $errorMsg]);
}
?>
