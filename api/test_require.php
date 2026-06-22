<?php
header("Content-Type: application/json");
$res = [];
$res['config_exists'] = file_exists(__DIR__ . '/config.php');
$res['config_readable'] = is_readable(__DIR__ . '/config.php');
$res['cwd'] = getcwd();
$res['dir'] = __DIR__;
echo json_encode($res, JSON_PRETTY_PRINT);
?>
