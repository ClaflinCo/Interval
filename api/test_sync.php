<?php
header("Content-Type: application/json");
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';

$res = [];
try {
    define('SILENT_SYNC', true);
    // Include sync_db.php inside try-catch to see if it's the source of the crash
    require_once __DIR__ . '/../sync_db.php';
    $res['sync_db_execution'] = "success";
} catch (Throwable $e) {
    $res['sync_db_execution'] = "failed";
    $res['error'] = $e->getMessage();
    $res['file'] = $e->getFile();
    $res['line'] = $e->getLine();
}

echo json_encode($res, JSON_PRETTY_PRINT);
?>
