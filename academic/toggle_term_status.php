<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Only academic staff (level 1) can toggle
if ($res != "1" || $level != "1") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_POST['term_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$term_id = (int)$_POST['term_id'];
$status = (int)$_POST['status']; // 1 = open (editable), 0 = closed (locked)

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("UPDATE tbl_terms SET results_editable = ? WHERE id = ?");
    $stmt->execute([$status, $term_id]);
    
    echo json_encode(['success' => true, 'message' => 'Term status updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>