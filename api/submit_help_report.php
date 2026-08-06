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
    error_log("Failed to fetch administrators in submit_help_report.php: " . $conn->error);
    echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
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
    error_log("Prepare admin report statement failed in submit_help_report.php: " . $conn->error);
    echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
    exit;
}
$repStmt->bind_param("sss", $reportId, $username, $details);
if (!$repStmt->execute()) {
    $repStmt->close();
    $conn->rollback();
    error_log("Failed to save help report in submit_help_report.php: " . $conn->error);
    echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
    exit;
}
$repStmt->close();

// Send alert notification to all admins
if (!empty($admins)) {
    $notifSql = "INSERT INTO notifications (id, username, type, title, msg, month) VALUES (?, ?, ?, ?, ?, NULL)";
    $stmt = $conn->prepare($notifSql);
    if (!$stmt) {
        $conn->rollback();
        error_log("Prepare notification statement failed in submit_help_report.php: " . $conn->error);
        echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
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
            error_log("Failed to send help notification to " . $admin . " in submit_help_report.php: " . $stmt->error);
            $errorMsg = "An internal database error occurred.";
            break;
        }
    }
    $stmt->close();
}

if ($success) {
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Report submitted successfully."]);
    notify_n8n([
        "event" => "help_report",
        "source_user" => $username,
        "detail" => $details,
        "report_id" => $reportId,
        "occurred_at" => date('c')
    ]);
} else {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $errorMsg]);
}
?>
