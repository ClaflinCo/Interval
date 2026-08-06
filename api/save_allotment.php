<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$data = json_decode(file_get_contents("php://input"), true);
$month = $data['month'] ?? '';
$assignments = $data['assignments'] ?? [];

if(!$month){
    echo json_encode(["success" => false, "message" => "Missing month"]);
    exit;
}

// Support both bulk allotments format and single project format for backward compatibility
$allotmentsToSave = [];
if (isset($data['allotments']) && is_array($data['allotments'])) {
    foreach ($data['allotments'] as $proj => $val) {
        $allotmentsToSave[] = [
            'project' => $proj,
            'allotment' => (float)$val
        ];
    }
} elseif (isset($data['project'])) {
    $allotmentsToSave[] = [
        'project' => $data['project'],
        'allotment' => isset($data['allotment']) ? (float)$data['allotment'] : 0.00
    ];
}

if (empty($allotmentsToSave)) {
    echo json_encode(["success" => false, "message" => "No allotments to save"]);
    exit;
}

// Only Admin and Supervisor can save allotments
if ($role !== 'Admin' && $role !== 'Supervisor' && $role !== 'C-Suite') {
    echo json_encode(["success" => false, "message" => "Unauthorized. Only Admins, Supervisors, and C-Suite can save allotments."]);
    exit;
}

$conn->begin_transaction();

$success = true;
$errorMsg = '';
foreach ($allotmentsToSave as $item) {
    $proj = $item['project'];
    $allot = $item['allotment'];
    
    // For Supervisor, check if they have access to this project
    if ($role === 'Supervisor') {
        $accessStmt = $conn->prepare("SELECT assigned, created_by FROM projects WHERE name = ?");
        $accessStmt->bind_param("s", $proj);
        $accessStmt->execute();
        $accessResult = $accessStmt->get_result();
        $hasAccess = false;
        if ($aRow = $accessResult->fetch_assoc()) {
            if ($aRow['created_by'] === $username) {
                $hasAccess = true;
            } else if (!empty($aRow['assigned'])) {
                $assigned_users = array_map('trim', explode(',', $aRow['assigned']));
                if (in_array($username, $assigned_users)) {
                    $hasAccess = true;
                }
            }
        }
        $accessStmt->close();
        
        if (!$hasAccess) {
            $success = false;
            $errorMsg = "You are not assigned to project: " . $proj;
            break;
        }
    }
    
    $assignedVal = isset($assignments[$proj]) ? trim($assignments[$proj]) : null;
    
    // Update allotment for the current month (row is guaranteed to exist by sync_db migration)
    $stmt = $conn->prepare("UPDATE project_allotments SET allotment = ?, updated_by = ? WHERE month = ? AND project = ?");
    $stmt->bind_param("dsss", $allot, $username, $month, $proj);
    
    if (!$stmt->execute()) {
        $success = false;
        error_log("Save allotment execute failed in save_allotment.php: " . $stmt->error);
        $errorMsg = "An internal database error occurred.";
        $stmt->close();
        break;
    }
    $stmt->close();
    
    // Propagate assignment list changes directly to projects table for consistency
    if ($assignedVal !== null) {
        $upStmt = $conn->prepare("UPDATE projects SET assigned = ? WHERE name = ?");
        $upStmt->bind_param("ss", $assignedVal, $proj);
        if (!$upStmt->execute()) {
            $success = false;
            error_log("Update project assigned execute failed in save_allotment.php: " . $upStmt->error);
            $errorMsg = "An internal database error occurred.";
            $upStmt->close();
            break;
        }
        $upStmt->close();
    }
}

if ($success) {
    $conn->commit();
    echo json_encode(["success" => true]);
} else {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Save failed: " . $errorMsg]);
}
?>
