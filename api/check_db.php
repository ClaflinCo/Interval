<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

$res = [];

// 1. Check logged-in user session
$res['session'] = $_SESSION;

// 2. Fetch projects
$projects = [];
$projRes = $conn->query("SELECT * FROM projects");
if ($projRes) {
    while ($row = $projRes->fetch_assoc()) {
        $projects[] = $row;
    }
}
$res['projects'] = $projects;

// 3. Fetch count of time entries
$entriesCount = 0;
$cntRes = $conn->query("SELECT COUNT(*) as count FROM time_entries");
if ($cntRes && $row = $cntRes->fetch_assoc()) {
    $entriesCount = (int)$row['count'];
}
$res['time_entries_count'] = $entriesCount;

// 4. Fetch specific 'MS Support' entries
$msEntries = [];
$entRes = $conn->query("SELECT * FROM time_entries WHERE project = 'MS Support' LIMIT 5");
if ($entRes) {
    while ($row = $entRes->fetch_assoc()) {
        $msEntries[] = $row;
    }
}
$res['ms_support_entries_preview'] = $msEntries;

// 5. Fetch project allotments for 'MS Support'
$msAllotments = [];
$allotRes = $conn->query("SELECT * FROM project_allotments WHERE project = 'MS Support'");
if ($allotRes) {
    while ($row = $allotRes->fetch_assoc()) {
        $msAllotments[] = $row;
    }
}
$res['ms_support_allotments'] = $msAllotments;

echo json_encode($res, JSON_PRETTY_PRINT);
?>
