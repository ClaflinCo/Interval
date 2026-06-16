<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
ini_set('display_errors', 0);
error_reporting(0);

function sanitize_utf8($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_utf8($value);
        }
        return $data;
    } elseif (is_string($data)) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        } else {
            return utf8_encode($data);
        }
    }
    return $data;
}

// Helper function to check project access
function check_project_access($conn, $username, $role, $proj) {
    if ($role === 'Admin') return true;
    $accessStmt = $conn->prepare("SELECT assigned, created_by FROM project_allotments WHERE project = ?");
    if (!$accessStmt) return false;
    $accessStmt->bind_param("s", $proj);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();
    $hasAccess = false;
    while ($aRow = $accessResult->fetch_assoc()) {
        if ($aRow['created_by'] === $username) {
            $hasAccess = true;
            break;
        }
        if (!empty($aRow['assigned'])) {
            $assigned_users = array_map('trim', explode(',', $aRow['assigned']));
            if (in_array($username, $assigned_users)) {
                $hasAccess = true;
                break;
            }
        }
    }
    $accessStmt->close();
    return $hasAccess;
}

try {
    require 'config.php';
    
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed.");
    }

    $username = $_SESSION['username'] ?? '';
    $user_role = $_SESSION['role'] ?? 'Employee';

    if (empty($username)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }

    if ($user_role === 'Viewer') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Viewers are not allowed to perform this action."]);
        exit;
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Invalid JSON input.");
    }

    $action = $data['action'] ?? '';
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    if (!$id) {
        throw new Exception("Missing or invalid ID.");
    }

    $resultData = [];

    if ($action === 'delete') {
        // Enforce delete permissions
        $entryStmt = $conn->prepare("SELECT submitted_by, project FROM time_entries WHERE id = ?");
        if (!$entryStmt) throw new Exception("Prepare failed: " . $conn->error);
        $entryStmt->bind_param("i", $id);
        $entryStmt->execute();
        $entryRes = $entryStmt->get_result();
        if ($eRow = $entryRes->fetch_assoc()) {
            $owner = $eRow['submitted_by'];
            $project = $eRow['project'];
            
            // Employees can only delete their own entries
            if ($user_role === 'Employee' && $owner !== $username) {
                throw new Exception("You can only delete your own entries.");
            }
            
            // Users must have access to the project
            if (!check_project_access($conn, $username, $user_role, $project)) {
                throw new Exception("You do not have access to this project.");
            }
        } else {
            throw new Exception("Entry not found.");
        }
        $entryStmt->close();

        // Perform deletion
        $stmt = $conn->prepare("DELETE FROM time_entries WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $resultData = ["status" => "success", "success" => true, "affected" => $stmt->affected_rows, "idSent" => $id];
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } else if ($action === 'approve') {
        if ($user_role !== 'Admin') {
            throw new Exception("Unauthorized access.");
        }
        $stmt = $conn->prepare("UPDATE time_entries SET approval_status = 'Approved' WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $origStmt = $conn->prepare("SELECT edit_of_id FROM time_entries WHERE id = ?");
        if (!$origStmt) throw new Exception("Prepare failed: " . $conn->error);
        $origStmt->bind_param("i", $id);
        $origStmt->execute();
        $res = $origStmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['edit_of_id']) {
                $orig = (int)$row['edit_of_id'];
                $updateSql = "UPDATE time_entries t1, time_entries t2 
                               SET t1.project = t2.project, t1.entry_date = t2.entry_date, 
                                   t1.check_in = t2.check_in, t1.check_out = t2.check_out, 
                                   t1.staff_attended = t2.staff_attended, t1.hours_override = t2.hours_override, 
                                   t1.status = t2.status, t1.client_contact = t2.client_contact, 
                                   t1.notes = t2.notes, t1.services = t2.services 
                               WHERE t1.id = ? AND t2.id = ?";
                $upStmt = $conn->prepare($updateSql);
                if (!$upStmt) throw new Exception("Prepare failed: " . $conn->error);
                $upStmt->bind_param("ii", $orig, $id);
                $upStmt->execute();
                $upStmt->close();

                $delStmt = $conn->prepare("DELETE FROM time_entries WHERE id = ?");
                if (!$delStmt) throw new Exception("Prepare failed: " . $conn->error);
                $delStmt->bind_param("i", $id);
                $delStmt->execute();
                $delStmt->close();
            }
        }
        $origStmt->close();
        $resultData = ["status" => "success", "success" => true];
    } else if ($action === 'reject') {
        if ($user_role !== 'Admin') {
            throw new Exception("Unauthorized access.");
        }
        $stmt = $conn->prepare("UPDATE time_entries SET approval_status = 'Rejected' WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultData = ["status" => "success", "success" => true];
    } else {
        throw new Exception("Invalid action: " . $action);
    }

    echo json_encode(sanitize_utf8($resultData));

} catch (Throwable $t) {
    $err = [
        "status" => "error", 
        "message" => "Server Error: " . $t->getMessage()
    ];
    echo json_encode(sanitize_utf8($err));
}
?>
