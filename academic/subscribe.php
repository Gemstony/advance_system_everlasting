<?php
// core/subscribe.php
header('Content-Type: application/json');
require_once('../db/config.php');

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tbl_push_subscriptions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `endpoint` varchar(500) NOT NULL,
            `auth` varchar(255) NOT NULL,
            `p256dh` varchar(255) NOT NULL,
            `user_id` varchar(20) DEFAULT NULL,
            `user_type` enum('student','teacher','academic') DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `endpoint` (`endpoint`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }

    if (empty($input['subscription'])) {
        throw new Exception('Missing subscription data');
    }

    $sub = json_decode($input['subscription'], true);
    if (!$sub || empty($sub['endpoint']) || empty($sub['keys'])) {
        throw new Exception('Invalid subscription format');
    }

    $endpoint = $sub['endpoint'];
    $auth = $sub['keys']['auth'] ?? '';
    $p256dh = $sub['keys']['p256dh'] ?? '';

    // If user is logged in, we can get user_id and type from session
    session_start();
    $user_id = null;
    $user_type = null;
    if (isset($_SESSION['student_id'])) {
        $user_id = $_SESSION['student_id'];
        $user_type = 'student';
    } elseif (isset($_SESSION['staff_id'])) {
        $user_id = $_SESSION['staff_id'];
        $user_type = 'teacher'; // could be academic, but we treat as teacher for simplicity
        // check level to differentiate
        if (isset($_SESSION['level']) && $_SESSION['level'] == 1) {
            $user_type = 'academic';
        }
    }

    // Upsert
    $stmt = $pdo->prepare("
        INSERT INTO tbl_push_subscriptions (endpoint, auth, p256dh, user_id, user_type)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            auth = VALUES(auth),
            p256dh = VALUES(p256dh),
            user_id = VALUES(user_id),
            user_type = VALUES(user_type),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$endpoint, $auth, $p256dh, $user_id, $user_type]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}