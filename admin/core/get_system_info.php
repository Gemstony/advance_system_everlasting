<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$info = [
    'php_version' => phpversion(),
    'mysql_version' => '',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
];

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName, DBUser, DBPass);
    $stmt = $pdo->query("SELECT VERSION()");
    $info['mysql_version'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $info['mysql_version'] = 'Error: ' . $e->getMessage();
}

echo json_encode(['success' => true, 'info' => $info]);