<?php
session_start();
require_once('../db/config.php');
header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get last 24 hours of notifications
    $stmt = $pdo->prepare("
        SELECT id, message, sent_at 
        FROM tbl_notification_history 
        WHERE sent_at > NOW() - INTERVAL 1 DAY 
          AND status = 'sent'
        ORDER BY sent_at DESC
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notifications' => $notifications]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}