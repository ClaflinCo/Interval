<?php
// login/logout.php
require_once '../api/bootstrap.php';
session_destroy();
header("Content-Type: application/json");
echo json_encode(["success" => true]);
?>
