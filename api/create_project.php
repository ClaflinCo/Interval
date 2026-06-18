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

try {
    require 'config.php';
    
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed.");
    }

    $username = $_SESSION['username'] ?? '';
    $user_role = $_SESSION['role'] ?? '';

    if (empty($username)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }

    // Only Admin and Supervisor can create projects
    if ($user_role !== 'Admin' && $user_role !== 'Supervisor') {
        throw new Exception("Unauthorized. Only Admins and Supervisors can create projects.");
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Invalid JSON input.");
    }

    $projectName = trim($data['projectName'] ?? '');
    $customer = trim($data['customer'] ?? '');
    $duration = isset($data['duration']) ? (int)$data['duration'] : 1;
    $allotment = isset($data['allotment']) ? (float)$data['allotment'] : 0.00;
    $assigned = trim($data['assigned'] ?? '');
    $startMonth = trim($data['startMonth'] ?? '');
    $servicesRaw = $data['services'] ?? '';
    if (is_array($servicesRaw)) {
        $services = implode(', ', array_filter(array_map('trim', $servicesRaw)));
    } else {
        $services = trim($servicesRaw);
    }

    if (empty($projectName) || empty($startMonth) || $duration < 1) {
        throw new Exception("Missing required fields (Project Name, Start Month, or Duration).");
    }

    // Admins and Supervisors must be in the assigned list
    if ($user_role === 'Admin' || $user_role === 'Supervisor') {
        $assigned_list = array_map('trim', explode(',', $assigned));
        // Remove empty strings
        $assigned_list = array_filter($assigned_list);
        if (!in_array($username, $assigned_list)) {
            $assigned_list[] = $username;
        }
        $assigned = implode(', ', $assigned_list);
    }

    // Months list to calculate wrap-around
    $MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    
    // Find index of startMonth
    $startIndex = -1;
    foreach ($MONTHS as $idx => $m) {
        if (strcasecmp($m, $startMonth) === 0) {
            $startIndex = $idx;
            break;
        }
    }

    if ($startIndex === -1) {
        throw new Exception("Invalid start month: " . $startMonth);
    }

    $conn->begin_transaction();

    // Prepare statement to insert/update projects table
    $projSql = "INSERT INTO projects (name, customer, duration, allotment, assigned, start_month, created_by, services) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    customer=VALUES(customer), 
                    duration=VALUES(duration), 
                    allotment=VALUES(allotment), 
                    assigned=VALUES(assigned), 
                    start_month=VALUES(start_month),
                    services=VALUES(services)";
    $projStmt = $conn->prepare($projSql);
    if (!$projStmt) {
        throw new Exception("Prepare projects statement failed: " . $conn->error);
    }
    $projStmt->bind_param("ssidssss", $projectName, $customer, $duration, $allotment, $assigned, $startMonth, $username, $services);
    if (!$projStmt->execute()) {
        $projStmt->close();
        throw new Exception("Execute projects statement failed: " . $conn->error);
    }
    $projStmt->close();

    // Prepare statement to insert/update project allotments
    $sql = "INSERT INTO project_allotments (month, project, allotment, updated_by, assigned, customer, duration, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                allotment=VALUES(allotment), 
                updated_by=VALUES(updated_by),
                assigned=VALUES(assigned),
                customer=VALUES(customer),
                duration=VALUES(duration),
                created_by=VALUES(created_by)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $success = true;
    $errorMsg = '';
    
    // Determine active months
    $activeMonths = [];
    for ($i = 0; $i < $duration; $i++) {
        $activeMonths[] = $MONTHS[($startIndex + $i) % 12];
    }

    foreach ($MONTHS as $m) {
        $mAllot = in_array($m, $activeMonths) ? $allotment : 0.00;
        $stmt->bind_param("ssdsdsis", $m, $projectName, $mAllot, $username, $assigned, $customer, $duration, $username);
        if (!$stmt->execute()) {
            $success = false;
            $errorMsg = $stmt->error;
            break;
        }
    }
    $stmt->close();

    if ($success) {
        $conn->commit();
        echo json_encode(sanitize_utf8(["success" => true, "message" => "Project created successfully."]));
    } else {
        $conn->rollback();
        throw new Exception("Database transaction failed: " . $errorMsg);
    }

} catch (Throwable $t) {
    $err = [
        "status" => "error", 
        "message" => $t->getMessage()
    ];
    echo json_encode(sanitize_utf8($err));
}
?>
