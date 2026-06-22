<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';

$res = [];
try {
    require 'config.php';
    
    // projects
    try {
        $q = $conn->query("SELECT * FROM projects LIMIT 1");
        $res['projects'] = ["success" => true, "rows" => $q ? $q->num_rows : 0];
    } catch (Throwable $e) {
        $res['projects'] = ["success" => false, "error" => $e->getMessage()];
    }

    // time_entries
    try {
        $q = $conn->query("SELECT * FROM time_entries LIMIT 1");
        $res['time_entries'] = ["success" => true, "rows" => $q ? $q->num_rows : 0];
    } catch (Throwable $e) {
        $res['time_entries'] = ["success" => false, "error" => $e->getMessage()];
    }

    // services
    try {
        $q = $conn->query("SELECT * FROM services LIMIT 1");
        $res['services'] = ["success" => true, "rows" => $q ? $q->num_rows : 0];
    } catch (Throwable $e) {
        $res['services'] = ["success" => false, "error" => $e->getMessage()];
    }

    // time_entries.services
    try {
        $q = $conn->query("SELECT services FROM time_entries LIMIT 1");
        $res['time_entries_services'] = ["success" => true, "rows" => $q ? $q->num_rows : 0];
    } catch (Throwable $e) {
        $res['time_entries_services'] = ["success" => false, "error" => $e->getMessage()];
    }

} catch (Throwable $e) {
    $res['error'] = "General error: " . $e->getMessage();
}

echo json_encode($res);
?>
