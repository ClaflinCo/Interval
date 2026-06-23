<?php
// api/check_session.php
header("Content-Type: application/json");
require_once 'bootstrap.php';

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => true,
        "user" => [
            "id" => $_SESSION['user_id'],
            "username" => $_SESSION['username'],
            "role" => $_SESSION['role'],
            "display" => $_SESSION['display_name'] ?? $_SESSION['username'], // display_name might not be in session yet
        ]
    ]);
} else {
    echo json_encode(["success" => false]);
}
?>
