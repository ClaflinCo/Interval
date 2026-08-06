<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require_once 'config.php';

$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON"]);
    exit;
}

$id = trim($data['id'] ?? '');
$action = trim($data['action'] ?? '');

if (empty($id) || empty($action)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

if ($action === 'read') {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND username = ?";
} else if ($action === 'unread') {
    $sql = "UPDATE notifications SET is_read = 0 WHERE id = ? AND username = ?";
} else if ($action === 'complete') {
    $sql = "UPDATE notifications SET is_read = 1, is_completed = 1 WHERE id = ? AND username = ?";
} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
    exit;
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare statement failed in update_notification.php: " . $conn->error);
    echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
    exit;
}

$stmt->bind_param("ss", $id, $username);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "affected" => $stmt->affected_rows]);
} else {
    error_log("Execute statement failed in update_notification.php: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "An internal database error occurred."]);
}
$stmt->close();
?>
