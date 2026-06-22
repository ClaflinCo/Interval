<?php
header("Content-Type: application/json");
require_once 'bootstrap.php';

$res = [];
$envPath = dirname(__DIR__) . '/.env';
$res['env_file_exists'] = file_exists($envPath);

if ($res['env_file_exists']) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $res['env_lines_count'] = count($lines);
}

// Load env variables
if ($res['env_file_exists']) {
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            putenv(trim($key) . "=" . trim($val));
        }
    }
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

$res['db_config'] = [
    'host_set' => !empty($host),
    'user_set' => !empty($user),
    'pass_set' => !empty($pass),
    'dbname_set' => !empty($dbname)
];

$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    $res['db_connected'] = false;
    $res['db_error'] = $conn->connect_error;
} else {
    $res['db_connected'] = true;
    $res['db_charset'] = $conn->character_set_name();
    $conn->close();
}

echo json_encode($res, JSON_PRETTY_PRINT);
?>
