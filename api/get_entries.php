<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require_once 'config.php';

define('SILENT_SYNC', true);
require_once '../sync_db.php';

$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}
$user_role = $_SESSION['role'] ?? 'Employee';

$entries = [];
$pending = [];

// Fetch approved entries for the main log (filtered below)
$sql = "SELECT id, submitted_by, project, entry_date as date, check_in as checkIn, 
               check_out as checkOut, staff_attended as staff, hours_override as hoursOverride, 
               status, client_contact as client, notes, approval_status, edit_of_id as originalId, services 
         FROM time_entries WHERE approval_status = 'Approved'";
$result = $conn->query($sql);

// Fetch distinct projects and metadata from projects table (filtered by access)
$allowed_projects = [];
$projectsMeta = [];

$projSql = "SELECT name, customer, duration, allotment, assigned, created_by, services FROM projects";
$projRes = $conn->query($projSql);
if ($projRes) {
    while($row = $projRes->fetch_assoc()) {
        $p = trim($row['name']);
        
        $assigned_users = [];
        if (!empty($row['assigned'])) {
            $assigned_users = array_map('trim', explode(',', $row['assigned']));
        }
        $is_assigned = in_array($username, $assigned_users);
        $is_creator = ($row['created_by'] === $username);
        
        if ($user_role === 'Admin' || $is_assigned || $is_creator) {
            if (!in_array($p, $allowed_projects)) {
                $allowed_projects[] = $p;
            }
            if (!isset($projectsMeta[$p])) {
                $projectsMeta[$p] = [
                    "assigned" => $row['assigned'] ?? '',
                    "customer" => $row['customer'] ?? '',
                    "duration" => (int)($row['duration'] ?? 1),
                    "allotment" => (float)($row['allotment'] ?? 0.00),
                    "created_by" => $row['created_by'] ?? '',
                    "services" => $row['services'] ?? ''
                ];
            }
        }
    }
}

// Fetch allotments for allowed projects from project_allotments table
$allotments = [];
$allotSql = "SELECT month, project, allotment FROM project_allotments";
$allotRes = $conn->query($allotSql);
if ($allotRes) {
    while($aRow = $allotRes->fetch_assoc()) {
        $m = $aRow['month'];
        $p = $aRow['project'];
        if (in_array($p, $allowed_projects)) {
            if (!isset($allotments[$m])) $allotments[$m] = [];
            $allotments[$m][$p] = (float)$aRow['allotment'];
        }
    }
}

if ($result) {
    while($row = $result->fetch_assoc()) {
        if ($row['hoursOverride'] !== null) {
            $row['hoursOverride'] = (float)$row['hoursOverride'];
        }
        if ($user_role === 'Admin' || in_array($row['project'], $allowed_projects)) {
            $entries[] = $row;
        }
    }
}

// Fetch pending entries for the approvals panel
$pendingSql = "SELECT id, submitted_by, project, entry_date as date, check_in as checkIn, 
               check_out as checkOut, staff_attended as staff, hours_override as hoursOverride, 
               status, client_contact as client, notes, approval_status, edit_of_id as originalId, services 
         FROM time_entries WHERE approval_status = 'Pending'";
$pendingResult = $conn->query($pendingSql);

if ($pendingResult) {
    while($row = $pendingResult->fetch_assoc()) {
        if ($row['hoursOverride'] !== null) {
            $row['hoursOverride'] = (float)$row['hoursOverride'];
        }
        if ($user_role === 'Admin' || in_array($row['project'], $allowed_projects)) {
            $pending[] = $row;
        }
    }
}

// Also fetch any unique project names from time_entries that might not have an allotment yet
if ($user_role === 'Admin') {
    $entryProjSql = "SELECT DISTINCT project FROM time_entries";
    $entryProjRes = $conn->query($entryProjSql);
    if ($entryProjRes) {
        while($epRow = $entryProjRes->fetch_assoc()) {
            $p = trim($epRow['project']);
            if (!empty($p) && !in_array($p, $allowed_projects)) {
                $allowed_projects[] = $p;
                if (!isset($projectsMeta[$p])) {
                    $projectsMeta[$p] = [
                        "assigned" => '',
                        "customer" => '',
                        "duration" => 1,
                        "created_by" => ''
                    ];
                }
            }
        }
    }
}
$projects = array_map('trim', $allowed_projects);
$projects = array_unique($projects);
natcasesort($projects);
$projects = array_values($projects);

// Fetch notifications for the current user
$notifSql = "SELECT id, type, title, msg, month, is_read, is_completed, created_at 
             FROM notifications 
             WHERE username = ? 
             ORDER BY created_at DESC 
             LIMIT 100";
$notifStmt = $conn->prepare($notifSql);
$userNotifications = [];
if ($notifStmt) {
    $notifStmt->bind_param("s", $username);
    $notifStmt->execute();
    $notifRes = $notifStmt->get_result();
    while ($nRow = $notifRes->fetch_assoc()) {
        $nRow['read'] = (bool)$nRow['is_read'];
        $nRow['completed'] = (bool)$nRow['is_completed'];
        $timestamp = strtotime($nRow['created_at']);
        $nRow['time'] = date("M j, g:i A", $timestamp);
        $userNotifications[] = $nRow;
    }
    $notifStmt->close();
}

// Return state bundle - flattened for Frontend
$response = [
    "success" => true,
    "entries" => $entries,
    "pending" => $pending,
    "allotments" => $allotments,
    "projects" => $projects,
    "projectsMeta" => $projectsMeta,
    "notifications" => $userNotifications
];

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

$clean_response = sanitize_utf8($response);
$json = json_encode($clean_response);

if ($json === false) {
    echo '{"success":false,"message":"JSON Encode Error: fatal encoding breakdown"}';
} else {
    echo $json;
}
?>
