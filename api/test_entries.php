<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

$res = [];

$res['session'] = $_SESSION;

// Fetch all projects
$projects = [];
$pRes = $conn->query("SELECT * FROM projects");
if ($pRes) {
    while ($row = $pRes->fetch_assoc()) {
        $projects[] = $row;
    }
}
$res['projects'] = $projects;

// Fetch project count from allotments
$allotmentsCount = 0;
$aRes = $conn->query("SELECT COUNT(*) as count FROM project_allotments");
if ($aRes && $row = $aRes->fetch_assoc()) {
    $allotmentsCount = (int)$row['count'];
}
$res['allotments_count'] = $allotmentsCount;

// Fetch time entries count
$entriesCount = 0;
$tRes = $conn->query("SELECT COUNT(*) as count FROM time_entries");
if ($tRes && $row = $tRes->fetch_assoc()) {
    $entriesCount = (int)$row['count'];
}
$res['time_entries_count'] = $entriesCount;

// Fetch unique project names in time_entries
$uniqueEntryProjects = [];
$uRes = $conn->query("SELECT DISTINCT project FROM time_entries");
if ($uRes) {
    while ($row = $uRes->fetch_assoc()) {
        $uniqueEntryProjects[] = $row['project'];
    }
}
$res['unique_entry_projects'] = $uniqueEntryProjects;

echo json_encode($res, JSON_PRETTY_PRINT);
?>
