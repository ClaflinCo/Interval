<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

try {
    // Restricted to Admin role
    if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden"]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $signups = [];
        $sql = "SELECT id, email, username, ip_address, status, created_at FROM account_requests ORDER BY created_at DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $signups[] = [
                    'id' => (int)$row['id'],
                    'email' => $row['email'],
                    'username' => $row['username'],
                    'ip_address' => $row['ip_address'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        echo json_encode([
            "success" => true,
            "signups" => $signups
        ]);
        exit;
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data['id'] ?? 0);
        $action = trim($data['action'] ?? ''); // 'approve' or 'reject'
        $role = trim($data['role'] ?? 'Viewer');
        $validRoles = ['Admin', 'C-Suite', 'Supervisor', 'Employee', 'Viewer'];
        if (!in_array($role, $validRoles)) {
            $role = 'Viewer';
        }

        if ($id <= 0 || ($action !== 'approve' && $action !== 'reject')) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid parameters."]);
            exit;
        }

        // Retrieve request information
        $stmt = $conn->prepare("SELECT email, username, password_hash, status FROM account_requests WHERE id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $request = $res->fetch_assoc();
        $stmt->close();

        if (!$request) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Sign up request not found."]);
            exit;
        }

        if ($request['status'] !== 'Pending') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Request has already been processed."]);
            exit;
        }

        $conn->begin_transaction();
        $success = true;
        $errorMsg = '';

        if ($action === 'approve') {
            // 1. Verify if username already exists in users table (just in case)
            $userCheck = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $userCheck->bind_param("s", $request['username']);
            $userCheck->execute();
            $userCheckRes = $userCheck->get_result();
            if ($userCheckRes->num_rows > 0) {
                $success = false;
                $errorMsg = "Username is already registered in the users table.";
            }
            $userCheck->close();

            if ($success) {
                // 2. Insert into users table with selected role and email
                $insStmt = $conn->prepare("INSERT INTO users (username, password_hash, role, display_name, email) VALUES (?, ?, ?, ?, ?)");
                if (!$insStmt) {
                    $success = false;
                    $errorMsg = "Prepare insert user stmt failed: " . $conn->error;
                } else {
                    $insStmt->bind_param("sssss", $request['username'], $request['password_hash'], $role, $request['username'], $request['email']);
                    if (!$insStmt->execute()) {
                        $success = false;
                        $errorMsg = "Failed to create user account: " . $insStmt->error;
                    }
                    $insStmt->close();
                }
            }

            if ($success) {
                // 3. Update status in account_requests
                $upStmt = $conn->prepare("UPDATE account_requests SET status = 'Approved' WHERE id = ?");
                if (!$upStmt) {
                    $success = false;
                    $errorMsg = "Prepare update status stmt failed: " . $conn->error;
                } else {
                    $upStmt->bind_param("i", $id);
                    if (!$upStmt->execute()) {
                        $success = false;
                        $errorMsg = "Failed to update request status: " . $upStmt->error;
                    }
                    $upStmt->close();
                }
            }
        } else {
            // action === 'reject'
            $upStmt = $conn->prepare("UPDATE account_requests SET status = 'Rejected' WHERE id = ?");
            if (!$upStmt) {
                $success = false;
                $errorMsg = "Prepare update status stmt failed: " . $conn->error;
            } else {
                $upStmt->bind_param("i", $id);
                if (!$upStmt->execute()) {
                    $success = false;
                    $errorMsg = "Failed to update request status: " . $upStmt->error;
                }
                $upStmt->close();
            }
        }

        if ($success) {
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Sign up request resolved successfully."]);
        } else {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => $errorMsg]);
        }
        exit;
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
    exit;
}
?>
