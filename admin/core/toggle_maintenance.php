<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['state'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$state = (int)$_POST['state'];

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure column exists
    $pdo->exec("ALTER TABLE tbl_school ADD COLUMN IF NOT EXISTS maintenance_mode TINYINT NOT NULL DEFAULT 0");

    $stmt = $pdo->prepare("UPDATE tbl_school SET maintenance_mode = ? WHERE id = 1");
    $stmt->execute([$state]);

    echo json_encode(['success' => true, 'state' => $state]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}