<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$res = [];
try {
    $res['bootstrap_status'] = "attempting";
    require_once __DIR__ . '/bootstrap.php';
    $res['bootstrap_status'] = "success";
} catch (Throwable $e) {
    $res['bootstrap_status'] = "failed: " . $e->getMessage();
}

try {
    $res['config_status'] = "attempting";
    require_once __DIR__ . '/config.php';
    $res['config_status'] = "success";
} catch (Throwable $e) {
    $res['config_status'] = "failed: " . $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);
?>
