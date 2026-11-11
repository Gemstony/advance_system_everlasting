<?php
// core/get_notifications.php
session_start();
header('Content-Type: application/json');
require_once('../db/config.php');

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Determine user type
    $userType = 'guest';
    if (isset($_SESSION['student_id'])) {
        $userType = 'student';
    } elseif (isset($_SESSION['staff_id'])) {
        if (isset($_SESSION['level']) && $_SESSION['level'] == 1) {
            $userType = 'academic';
        } else {
            $userType = 'teacher';
        }
    }

    // Fetch notifications from tbl_notification_history for this user type
    // For guests, we show only public ones (if any)
    $sql = "SELECT id, message, sent_at FROM tbl_notification_history WHERE recipient_type = 'both' OR recipient_type = ? ORDER BY id DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userType]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notifications' => $notifications]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}