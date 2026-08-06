<?php
// Prevent duplicate inclusion errors
if (defined('SYNC_DB_RUN')) {
    return;
}
define('SYNC_DB_RUN', true);

// Check authentication: allow if CLI, require Admin session if Web
$isCli = (php_sapi_name() === 'cli');
$isAdmin = false;

if (!$isCli) {
    $bootstrapPath = file_exists('api/bootstrap.php') ? 'api/bootstrap.php' : '../api/bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        $bootstrapPath = 'bootstrap.php';
    }
    if (file_exists($bootstrapPath)) {
        require_once $bootstrapPath;
    }
    
    $isAdmin = (isset($_SESSION['username']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');
}

if (!$isCli && !$isAdmin) {
    http_response_code(403);
    header("Content-Type: text/plain");
    echo "403 Forbidden - Admin or CLI access required.";
    exit;
}

// Support relative path for config depending on where it was included from
$configPath = file_exists('api/config.php') ? 'api/config.php' : '../api/config.php';
if (!file_exists($configPath)) {
    $configPath = 'config.php';
}
require_once $configPath;

function log_sync($msg) {
    if (!defined('SILENT_SYNC') || !SILENT_SYNC) {
        echo $msg;
    }
}

log_sync("=== DATABASE SYNC START ===<br><br>");

// 1. Ensure projects table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        customer VARCHAR(100) DEFAULT NULL,
        duration INT DEFAULT 1,
        allotment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        assigned TEXT DEFAULT NULL,
        start_month VARCHAR(20) DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        subscription_hours TEXT DEFAULT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked projects table existence.<br>");

// 1b. Ensure time_entries table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS time_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submitted_by VARCHAR(100) NOT NULL,
        project VARCHAR(100) NOT NULL,
        entry_date DATE NOT NULL,
        check_in VARCHAR(20) NOT NULL,
        check_out VARCHAR(20) NOT NULL,
        staff_attended VARCHAR(255) DEFAULT NULL,
        hours_override DECIMAL(10,2) DEFAULT NULL,
        status VARCHAR(50) DEFAULT NULL,
        client_contact VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        services VARCHAR(100) DEFAULT NULL,
        approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        edit_of_id INT DEFAULT NULL,
        KEY idx_submitted_by (submitted_by),
        KEY idx_project (project),
        KEY idx_entry_date (entry_date),
        KEY idx_approval_status (approval_status),
        KEY idx_edit_of_id (edit_of_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked time_entries table existence.<br>");

// 2. Ensure project_allotments table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS project_allotments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        month VARCHAR(20) NOT NULL,
        project VARCHAR(100) NOT NULL,
        allotment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        updated_by VARCHAR(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked project_allotments table existence.<br>");

// 2b. Ensure notifications table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL,
        title VARCHAR(255) NOT NULL,
        msg TEXT NOT NULL,
        month VARCHAR(20) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked notifications table existence.<br>");

// 2c. Ensure login_attempts table structure for login throttling
$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(100) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ip_time (ip_address, attempt_time),
        KEY idx_user_time (username, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked login_attempts table existence.<br>");

// 2d. Ensure project_change_requests table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS project_change_requests (
        id VARCHAR(50) PRIMARY KEY,
        supervisor VARCHAR(100) NOT NULL,
        project VARCHAR(100) NOT NULL,
        request_type VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_by VARCHAR(100) DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked project_change_requests table existence.<br>");

// 2e. Ensure admin_reports table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_reports (
        id VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_by VARCHAR(100) DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked admin_reports table existence.<br>");

// 2f. Ensure account_requests table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS account_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        username VARCHAR(100) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked account_requests table existence.<br>");

// 2g. Ensure services table structure and constraints
$conn->query("
    CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service VARCHAR(100) NOT NULL UNIQUE,
        created_by VARCHAR(100) DEFAULT NULL,
        is_subscription TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked services table existence.<br>");

// Ensure is_subscription column exists in services table
$r = $conn->query("SHOW COLUMNS FROM services LIKE 'is_subscription'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE services ADD COLUMN is_subscription TINYINT(1) NOT NULL DEFAULT 0");
    log_sync("Added column is_subscription to services table.<br>");
}

// Seed default services if empty
$chk = $conn->query("SELECT COUNT(*) as count FROM services");
if ($chk && $row = $chk->fetch_assoc()) {
    if ((int)$row['count'] === 0) {
        $defaultServices = ['vCIO', 'Shadow IT', 'Professional Services', 'Network Admin', 'IT Project Manager'];
        $stmt = $conn->prepare("INSERT INTO services (service, created_by) VALUES (?, 'System')");
        if ($stmt) {
            foreach ($defaultServices as $ds) {
                $stmt->bind_param("s", $ds);
                $stmt->execute();
            }
            $stmt->close();
            log_sync("Seeded default services.<br>");
        }
    }
}

// Drop the unique constraint on username in account_requests if it exists.
// Usernames only need to be unique in the active users table — historical
// account_request records (Approved/Rejected) should not block re-registration.
$idxCheck = $conn->query("SHOW INDEX FROM account_requests WHERE Key_name = 'username'");
if ($idxCheck && $idxCheck->num_rows > 0) {
    $conn->query("ALTER TABLE account_requests DROP INDEX username");
    log_sync("Dropped unique index on account_requests.username to allow re-registration of previously used usernames.<br>");
} else {
    log_sync("No unique index on account_requests.username (correct).<br>");
}


// Ensure month column is VARCHAR(20)
$r = $conn->query("SHOW COLUMNS FROM project_allotments LIKE 'month'");
if ($r && $row = $r->fetch_assoc()) {
    $type = strtolower($row['Type']);
    if (strpos($type, 'varchar') === false || (preg_match('/varchar\((\d+)\)/', $type, $m) && (int)$m[1] < 20)) {
        $conn->query("ALTER TABLE project_allotments MODIFY COLUMN month VARCHAR(20) NOT NULL");
        log_sync("Widened month column in project_allotments to VARCHAR(20).<br>");
    }
}

// Ensure project column is VARCHAR(100)
$r = $conn->query("SHOW COLUMNS FROM project_allotments LIKE 'project'");
if ($r && $row = $r->fetch_assoc()) {
    $type = strtolower($row['Type']);
    if (strpos($type, 'varchar') === false || (preg_match('/varchar\((\d+)\)/', $type, $m) && (int)$m[1] < 100)) {
        $conn->query("ALTER TABLE project_allotments MODIFY COLUMN project VARCHAR(100) NOT NULL");
        log_sync("Widened project column in project_allotments to VARCHAR(100).<br>");
    }
}

// Repair truncated month names
$monthRepairs = [
    'Septem' => 'September',
    'Septemb' => 'September',
    'Septembe' => 'September',
    'Novemb' => 'November',
    'Novembe' => 'November',
    'Decemb' => 'December',
    'Decembe' => 'December',
    'Februa' => 'February',
    'Februar' => 'February'
];
foreach ($monthRepairs as $truncated => $full) {
    $stmt = $conn->prepare("UPDATE project_allotments SET month = ? WHERE month = ?");
    $stmt->bind_param("ss", $full, $truncated);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        log_sync("Repaired truncated month: '$truncated' -> '$full' ({$stmt->affected_rows} rows updated).<br>");
    }
    $stmt->close();
}

// Ensure unique index uniq_month_project exists
$r = $conn->query("SHOW INDEX FROM project_allotments WHERE Key_name = 'uniq_month_project'");
if ($r && $r->num_rows == 0) {
    // Remove duplicates first, keeping the latest row (highest ID)
    $conn->query("
        DELETE pa1 FROM project_allotments pa1
        INNER JOIN project_allotments pa2
        WHERE pa1.id < pa2.id
        AND pa1.month = pa2.month
        AND pa1.project = pa2.project
    ");
    $conn->query("ALTER TABLE project_allotments ADD UNIQUE INDEX uniq_month_project (month, project)");
    log_sync("Added UNIQUE index uniq_month_project on (month, project).<br>");
} else {
    log_sync("UNIQUE index uniq_month_project already exists.<br>");
}

// 3. Ensure users table columns exist
function addColumn($conn, $table, $column, $type) {
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $type");
        if (!defined('SILENT_SYNC') || !SILENT_SYNC) {
            echo "Added column $column to $table.<br>";
        }
    } else {
        if (!defined('SILENT_SYNC') || !SILENT_SYNC) {
            echo "Column $column already exists in $table.<br>";
        }
    }
}

addColumn($conn, 'users', 'role', "VARCHAR(20) DEFAULT 'Viewer'");
addColumn($conn, 'users', 'display_name', "VARCHAR(100)");
addColumn($conn, 'users', 'email', "VARCHAR(255) DEFAULT NULL");

// Add columns to project_allotments table
addColumn($conn, 'project_allotments', 'assigned', "TEXT DEFAULT NULL");
addColumn($conn, 'project_allotments', 'customer', "VARCHAR(100) DEFAULT NULL");
addColumn($conn, 'project_allotments', 'duration', "INT DEFAULT 1");
addColumn($conn, 'project_allotments', 'created_by', "VARCHAR(100) DEFAULT NULL");

// Add services columns
addColumn($conn, 'projects', 'services', "TEXT DEFAULT NULL");
addColumn($conn, 'projects', 'subscription_hours', "TEXT DEFAULT NULL");
addColumn($conn, 'projects', 'completed', "TINYINT(1) NOT NULL DEFAULT 0");
addColumn($conn, 'time_entries', 'services', "VARCHAR(100) DEFAULT NULL");

// Migrate/Add columns to notifications table
$notifCols = [];
$res = $conn->query("SHOW COLUMNS FROM notifications");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notifCols[] = strtolower($row['Field']);
    }
}
if (in_array('read', $notifCols) && !in_array('is_read', $notifCols)) {
    $conn->query("ALTER TABLE notifications CHANGE COLUMN `read` is_read TINYINT(1) NOT NULL DEFAULT 0");
    log_sync("Renamed column 'read' to 'is_read' in notifications.<br>");
}
if (in_array('time', $notifCols) && !in_array('created_at', $notifCols)) {
    $conn->query("ALTER TABLE notifications CHANGE COLUMN `time` created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    log_sync("Renamed column 'time' to 'created_at' in notifications.<br>");
}

addColumn($conn, 'notifications', 'is_read', "TINYINT(1) NOT NULL DEFAULT 0");
addColumn($conn, 'notifications', 'is_completed', "TINYINT(1) NOT NULL DEFAULT 0");
addColumn($conn, 'notifications', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// 4. Create default users if they don't exist (disabled after initial setup)
/*
$defaults = [
    ['admin', 'admin123', 'Admin', 'Administrator'],
    ['viewer', 'view456', 'Viewer', 'Viewer User'],
    ['supervisor', 'super123', 'Supervisor', 'Supervisor User'],
    ['employee', 'emp123', 'Employee', 'Employee User']
];

foreach ($defaults as $u) {
    $username = $conn->real_escape_string($u[0]);
    $pass = $u[1];
    $role = $conn->real_escape_string($u[2]);
    $display = $conn->real_escape_string($u[3]);
    
    $res = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($res && $res->num_rows == 0) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, display_name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $hash, $role, $display);
        $stmt->execute();
        log_sync("Created user: $username<br>");
        $stmt->close();
    } else {
        log_sync("User already exists: $username<br>");
    }
}
*/

// 5. Ensure all existing projects have all 12 monthly allotments in project_allotments
$projRes = $conn->query("SELECT name, customer, duration, assigned, created_by FROM projects");
if ($projRes) {
    $MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $insStmt = $conn->prepare("
        INSERT INTO project_allotments (month, project, allotment, updated_by, assigned, customer, duration, created_by) 
        VALUES (?, ?, 0.00, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            assigned=VALUES(assigned), 
            customer=VALUES(customer), 
            duration=VALUES(duration), 
            created_by=VALUES(created_by)
    ");
    if ($insStmt) {
        while ($row = $projRes->fetch_assoc()) {
            $pName = $row['name'];
            $cust = $row['customer'];
            $dur = (int)$row['duration'];
            $ass = $row['assigned'];
            $cb = $row['created_by'];
            
            foreach ($MONTHS as $m) {
                $insStmt->bind_param("sssssis", $m, $pName, $cb, $ass, $cust, $dur, $cb);
                $insStmt->execute();
            }
        }
        $insStmt->close();
    }
}

log_sync("<br>=== DATABASE SYNC COMPLETE ===");
?>
