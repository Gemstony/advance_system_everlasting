<?php
chdir('../../');
session_start();
require_once('../db/config.php');
require_once('../const/check_session.php');

// Only admin (level 0)
if ($res != "1" || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$ids = $_POST['ids'] ?? [];
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No teachers selected']);
    exit;
}

// Ensure ids is an array
if (!is_array($ids)) {
    $ids = [$ids];
}
$ids = array_map('intval', $ids);

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, email FROM tbl_staff WHERE id IN ($placeholders) AND level > 1");
    $stmt->execute($ids);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($teachers)) {
        echo json_encode(['success' => false, 'error' => 'No valid teachers found']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
    $resultData = [];

    foreach ($teachers as $teacher) {
        // Generate random password (8 characters, alphanumeric + special)
        $newPassword = bin2hex(random_bytes(4)); // 8 chars hex
        // You can make it more complex if desired

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt->execute([$hashedPassword, $teacher['id']]);

        $resultData[] = [
            'email' => $teacher['email'],
            'password' => $newPassword
        ];
    }

    echo json_encode(['success' => true, 'data' => $resultData]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}