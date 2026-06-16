<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require_once 'config.php';

$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? '';

if (empty($username)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit;
}

$details = trim($data['details'] ?? '');

if (empty($details)) {
    echo json_encode(["success" => false, "message" => "Missing description details."]);
    exit;
}

// Get all Admin users to send notifications
$adminRes = $conn->query("SELECT username FROM users WHERE role = 'Admin'");
if (!$adminRes) {
    echo json_encode(["success" => false, "message" => "Failed to fetch administrators: " . $conn->error]);
    exit;
}

$admins = [];
while ($row = $adminRes->fetch_assoc()) {
    $admins[] = $row['username'];
}

$conn->begin_transaction();
$success = true;
$errorMsg = '';

$reportId = bin2hex(random_bytes(16));
$repSql = "INSERT INTO admin_reports (id, username, details, status) VALUES (?, ?, ?, 'Pending')";
$repStmt = $conn->prepare($repSql);
if (!$repStmt) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Prepare admin report statement failed: " . $conn->error]);
    exit;
}
$repStmt->bind_param("sss", $reportId, $username, $details);
if (!$repStmt->execute()) {
    $repStmt->close();
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Failed to save help report: " . $conn->error]);
    exit;
}
$repStmt->close();

// Send alert notification to all admins
if (!empty($admins)) {
    $notifSql = "INSERT INTO notifications (id, username, type, title, msg, month) VALUES (?, ?, ?, ?, ?, NULL)";
    $stmt = $conn->prepare($notifSql);
    if (!$stmt) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Prepare notification statement failed: " . $conn->error]);
        exit;
    }

    $type = 'warning';
    $title = "New Help Report: " . $username;
    $msg = "User " . $username . " submitted a help request/report. Details: " . $details;

    foreach ($admins as $admin) {
        $id = bin2hex(random_bytes(16));
        $stmt->bind_param("sssss", $id, $admin, $type, $title, $msg);
        if (!$stmt->execute()) {
            $success = false;
            $errorMsg = $stmt->error;
            break;
        }
    }
    $stmt->close();
}

if ($success) {
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Report submitted successfully."]);
} else {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Failed to notify administrators: " . $errorMsg]);
}
?>
