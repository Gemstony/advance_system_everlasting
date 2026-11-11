<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the latest numeric part of student IDs (e.g., after E001- or just numeric)
    $stmt = $pdo->query("SELECT id FROM tbl_students ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    
    if ($last) {
        // Assuming format like E001-0001 (first two parts: class prefix? adjust as needed)
        // For simplicity, we extract a numeric suffix and increment.
        preg_match('/(\d+)$/', $last, $matches);
        $next_num = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $next_num = 1;
    }
    
    // Format: E001-0001 (example, you can change)
    $next_id = 'E001-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    
    // Ensure the new ID doesn't already exist (just in case)
    $check = $pdo->prepare("SELECT COUNT(*) FROM tbl_students WHERE id = ?");
    $check->execute([$next_id]);
    if ($check->fetchColumn() > 0) {
        $next_num++;
        $next_id = 'E001-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    }
    
    echo json_encode(['success' => true, 'next_id' => $next_id]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>