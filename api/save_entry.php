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

if ($role === 'Viewer') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Viewers are not allowed to add or edit entries."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$project = $data['project'] ?? '';
$entry_date = $data['date'] ?? '';
$check_in = $data['checkIn'] ?? '';
$check_out = $data['checkOut'] ?? '';
$staff_attended = $data['staff'] ?? '';
$hours_override = isset($data['hoursOverride']) && $data['hoursOverride'] !== null && $data['hoursOverride'] !== '' ? (float)$data['hoursOverride'] : null;
$status = $data['status'] ?? '';
$client_contact = $data['client'] ?? '';
$notes = $data['notes'] ?? '';
$services = trim($data['services'] ?? '');
$action = $data['action'] ?? '';
$id = isset($data['id']) ? (int)$data['id'] : null; // Entry ID for updates

if(empty($project) || empty($entry_date) || empty($check_in) || empty($check_out)){
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Helper function to check project access
function check_project_access($conn, $username, $role, $proj) {
    if ($role === 'Admin') return true;
    $accessStmt = $conn->prepare("SELECT assigned, created_by FROM projects WHERE name = ?");
    if (!$accessStmt) return false;
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
    return $hasAccess;
}

// 1. Enforce project access on new project name
if (!check_project_access($conn, $username, $role, $project)) {
    echo json_encode(["success" => false, "message" => "You are not assigned to this project."]);
    exit;
}

// 2. Handle updates or insertions
if ($action === 'update' && !empty($id)) {
    // Verify edit permissions for the entry
    $entryStmt = $conn->prepare("SELECT submitted_by, project FROM time_entries WHERE id = ?");
    $entryStmt->bind_param("i", $id);
    $entryStmt->execute();
    $entryRes = $entryStmt->get_result();
    if ($eRow = $entryRes->fetch_assoc()) {
        $owner = $eRow['submitted_by'];
        $old_project = $eRow['project'];
        
        $can_edit = false;
        if ($role === 'Admin') {
            $can_edit = true;
        } elseif ($role === 'Supervisor') {
            $has_proj_access = check_project_access($conn, $username, $role, $old_project);
            $can_edit = ($owner === $username) || $has_proj_access;
        } elseif ($role === 'Employee') {
            $can_edit = ($owner === $username);
        } else {
            $can_edit = ($owner === $username);
        }

        if (!$can_edit) {
            echo json_encode(["success" => false, "message" => "You do not have permission to edit this entry."]);
            exit;
        }
    } else {
        echo json_encode(["success" => false, "message" => "Target entry not found."]);
        exit;
    }
    $entryStmt->close();

    // Directly update the entry
    $sql = "UPDATE time_entries SET project=?, entry_date=?, check_in=?, check_out=?, staff_attended=?, hours_override=?, status=?, client_contact=?, notes=?, services=?, approval_status='Approved' WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssdssssi", $project, $entry_date, $check_in, $check_out, $staff_attended, $hours_override, $status, $client_contact, $notes, $services, $id);
} else {
    // New entry: Directly insert as Approved
    $approval_status = 'Approved';
    $sql = "INSERT INTO time_entries (submitted_by, project, entry_date, check_in, check_out, staff_attended, hours_override, status, client_contact, notes, services, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssdsssss", $username, $project, $entry_date, $check_in, $check_out, $staff_attended, $hours_override, $status, $client_contact, $notes, $services, $approval_status);
}

if($stmt->execute()){
    echo json_encode(["success" => true, "id" => ($action === 'update' ? $id : $stmt->insert_id)]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $stmt->error]);
}
?>
