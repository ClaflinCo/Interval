<?php
// Prevent duplicate inclusion errors
if (defined('SYNC_DB_RUN')) {
    return;
}
define('SYNC_DB_RUN', true);

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
log_sync("Checked projects table existence.<br>");

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

// Add columns to project_allotments table
addColumn($conn, 'project_allotments', 'assigned', "TEXT DEFAULT NULL");
addColumn($conn, 'project_allotments', 'customer', "VARCHAR(100) DEFAULT NULL");
addColumn($conn, 'project_allotments', 'duration', "INT DEFAULT 1");
addColumn($conn, 'project_allotments', 'created_by', "VARCHAR(100) DEFAULT NULL");

// Add services columns
addColumn($conn, 'projects', 'services', "TEXT DEFAULT NULL");
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

log_sync("<br>=== DATABASE SYNC COMPLETE ===");
?>
