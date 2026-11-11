<?php
// admin/delete_image.php
chdir(__DIR__ . '/../');
session_start();

require_once 'db/config.php';
require_once 'const/check_session.php';

// Only admin level 0
if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$type = $_POST['type'];
if (!in_array($type, ['signature', 'seal'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get file path before deleting
    $stmt = $pdo->prepare("SELECT file_path FROM tbl_images WHERE image_type = ?");
    $stmt->execute([$type]);
    $file_path = $stmt->fetchColumn();

    // Delete database record
    $stmt = $pdo->prepare("DELETE FROM tbl_images WHERE image_type = ?");
    $stmt->execute([$type]);

    // Delete physical file if it exists
    if ($file_path && file_exists(__DIR__ . '/../' . $file_path)) {
        unlink(__DIR__ . '/../' . $file_path);
    }

    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' deleted successfully.']);
} catch (PDOException $e) {
    error_log('Delete image error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}