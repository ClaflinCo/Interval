<?php
// rebuild_time_entries.php
// Standalone script to recreate the dropped time_entries table.

header("Content-Type: text/plain");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Locate configuration files
$configPath = 'api/config.php';
if (!file_exists($configPath)) {
    $configPath = '../api/config.php';
}
if (!file_exists($configPath)) {
    $configPath = 'config.php';
}

if (!file_exists($configPath)) {
    die("Error: Could not locate api/config.php or config.php file.\n");
}

require_once $configPath;

// Check authentication if accessed via Web browser (CLI runs automatically)
$isCli = (php_sapi_name() === 'cli');
$isAdmin = false;

if (!$isCli) {
    $bootstrapPath = 'api/bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        $bootstrapPath = '../api/bootstrap.php';
    }
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
    die("403 Forbidden - Admin session or CLI access required to run this script.\n");
}

// SQL query to reconstruct the time_entries table
$sql = "
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
";

echo "Rebuilding 'time_entries' table...\n";

if ($conn->query($sql)) {
    echo "SUCCESS: 'time_entries' table has been successfully verified/recreated.\n";
} else {
    echo "ERROR: Failed to create table: " . $conn->error . "\n";
}
?>
