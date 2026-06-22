<?php
header("Content-Type: application/json");
$res = [];
$path = __DIR__ . '/config.php';
if (file_exists($path)) {
    $res['content_preview'] = substr(file_get_contents($path), 0, 200);
} else {
    $res['content_preview'] = "File does not exist";
}
echo json_encode($res, JSON_PRETTY_PRINT);
?>
