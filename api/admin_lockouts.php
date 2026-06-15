<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
require_once 'bootstrap.php';
require 'config.php';

// Restricted to Admin role
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $lockouts = [];

    // Query locked out usernames (>= 5 failed attempts)
    $userQuery = "SELECT username, COUNT(*) as count, MAX(attempt_time) as last_attempt, MAX(ip_address) as ip_address
                  FROM login_attempts 
                  GROUP BY username 
                  HAVING count >= 5";
    $res = $conn->query($userQuery);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $u = $row['username'] ?? '';
            $ip = $row['ip_address'] ?? '';
            $key = $u . '|' . $ip;
            $lockouts[$key] = [
                'username' => $u,
                'ip_address' => $ip,
                'count' => (int)$row['count'],
                'last_attempt' => $row['last_attempt'],
                'type' => 'username'
            ];
        }
    }

    // Query locked out IPs (>= 5 failed attempts)
    $ipQuery = "SELECT ip_address, COUNT(*) as count, MAX(attempt_time) as last_attempt, MAX(username) as username
                FROM login_attempts 
                GROUP BY ip_address 
                HAVING count >= 5";
    $res = $conn->query($ipQuery);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $u = $row['username'] ?? '';
            $ip = $row['ip_address'] ?? '';
            $key = $u . '|' . $ip;
            if (isset($lockouts[$key])) {
                $lockouts[$key]['count'] = max($lockouts[$key]['count'], (int)$row['count']);
                $lockouts[$key]['last_attempt'] = max($lockouts[$key]['last_attempt'], $row['last_attempt']);
                $lockouts[$key]['type'] = 'both';
            } else {
                $lockouts[$key] = [
                    'username' => $u,
                    'ip_address' => $ip,
                    'count' => (int)$row['count'],
                    'last_attempt' => $row['last_attempt'],
                    'type' => 'ip'
                ];
            }
        }
    }

    echo json_encode([
        "success" => true,
        "lockouts" => array_values($lockouts)
    ]);
    exit;
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? '';
    $target = $data['target'] ?? '';

    if (empty($type) || empty($target)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid parameters"]);
        exit;
    }

    if ($type === 'username') {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $target);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true, "message" => "User lockout reset successfully"]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error"]);
            exit;
        }
    } elseif ($type === 'ip') {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        if ($stmt) {
            $stmt->bind_param("s", $target);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true, "message" => "IP lockout reset successfully"]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error"]);
            exit;
        }
    } elseif ($type === 'both') {
        $parts = explode('|', $target);
        $uTarget = $parts[0] ?? '';
        $ipTarget = $parts[1] ?? '';
        
        if (empty($uTarget) && empty($ipTarget)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid targets"]);
            exit;
        }
        
        $sql = "DELETE FROM login_attempts WHERE 1=0";
        $params = [];
        $types = "";
        
        if (!empty($uTarget)) {
            $sql .= " OR username = ?";
            $params[] = $uTarget;
            $types .= "s";
        }
        if (!empty($ipTarget)) {
            $sql .= " OR ip_address = ?";
            $params[] = $ipTarget;
            $types .= "s";
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true, "message" => "Lockout reset successfully"]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error"]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid lockout type"]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}
?>
