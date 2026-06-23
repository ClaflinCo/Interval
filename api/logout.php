<?php
// api/logout.php
require_once 'bootstrap.php';
session_destroy();
header("Content-Type: application/json");
echo json_encode(["success" => true]);
?>
