<?php
header("Content-Type: application/json");
$res = [];

function get_last_lines($filepath, $num_lines = 30) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return "File does not exist or is not readable";
    }
    $file = file($filepath);
    $lines = array_slice($file, -$num_lines);
    return implode("", $lines);
}

$res['api_error_log'] = get_last_lines(__DIR__ . '/error_log');
$res['root_error_log'] = get_last_lines(dirname(__DIR__) . '/error_log');

echo json_encode($res, JSON_PRETTY_PRINT);
?>
