<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

$res = [];

function test_query($conn, $sql) {
    try {
        $q = $conn->query($sql);
        if ($q === false) {
            return ["success" => false, "error" => "Returned false"];
        }
        $count = 0;
        if ($q instanceof mysqli_result) {
            $count = $q->num_rows;
        }
        return ["success" => true, "num_rows" => $count];
    } catch (Throwable $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}

$res['query_projects'] = test_query($conn, "SELECT * FROM projects LIMIT 1");
$res['query_time_entries'] = test_query($conn, "SELECT * FROM time_entries LIMIT 1");
$res['query_project_allotments'] = test_query($conn, "SELECT * FROM project_allotments LIMIT 1");
$res['query_services'] = test_query($conn, "SELECT * FROM services LIMIT 1");

// Test selecting services from time_entries
$res['query_time_entries_services'] = test_query($conn, "SELECT services FROM time_entries LIMIT 1");

echo json_encode($res, JSON_PRETTY_PRINT);
?>
