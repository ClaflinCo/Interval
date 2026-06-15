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

$type = trim($data['type'] ?? 'info');
$title = trim($data['title'] ?? '');
$msg = trim($data['msg'] ?? '');
$month = trim($data['month'] ?? '');

if (empty($title) || empty($msg)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$id = bin2hex(random_bytes(16));

$sql = "INSERT INTO notifications (id, username, type, title, msg, month) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare statement failed: " . $conn->error]);
    exit;
}

$month_param = empty($month) ? null : $month;
$stmt->bind_param("ssssss", $id, $username, $type, $title, $msg, $month_param);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $id]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}
$stmt->close();
?>
