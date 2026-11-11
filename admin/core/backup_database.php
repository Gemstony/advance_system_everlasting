<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    exit;
}

// Stream the database dump
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="srms_backup_' . date('Y-m-d_H-i-s') . '.sql"');

$pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName, DBUser, DBPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    echo "\n\n-- Structure for `$table`\n\n";
    echo $create['Create Table'] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `$table`");
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $values = array_map(function($val) use ($pdo) {
            return $val === null ? 'NULL' : $pdo->quote($val);
        }, $row);
        echo "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
    }
}