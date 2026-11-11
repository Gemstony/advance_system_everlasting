<?php
// core/send_push.php
header('Content-Type: application/json');
require_once('../db/config.php');

// Only allow POST from academic dashboard (check session)
session_start();
if (!isset($_SESSION['staff_id']) || $_SESSION['level'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$message = $_POST['message'] ?? 'New notification from academic.';
$title = $_POST['title'] ?? 'SRMS Notification';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all subscriptions
    $stmt = $pdo->query("SELECT endpoint, auth, p256dh FROM tbl_push_subscriptions");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $successCount = 0;
    $failCount = 0;

    // VAPID keys (replace with your own)
    $vapidPublicKey = 'YOUR_VAPID_PUBLIC_KEY';
    $vapidPrivateKey = 'YOUR_VAPID_PRIVATE_KEY';

    foreach ($subscriptions as $sub) {
        $payload = json_encode([
            'title' => $title,
            'body'  => $message,
            'icon'  => '/images/icon-192.png',
            'badge' => '/images/badge-72.png',
            'url'   => '/'
        ]);

        // Build headers for Web Push
        $headers = [
            'Content-Type: application/json',
            'TTL: 86400', // 1 day
        ];

        // VAPID signature
        $jwt = generateVapidJWT($vapidPublicKey, $vapidPrivateKey, $sub['endpoint']);
        $headers[] = 'Authorization: WebPush ' . $jwt;

        // Encrypt payload
        $encrypted = encryptPayload($payload, $sub['p256dh'], $sub['auth']);

        $ch = curl_init($sub['endpoint']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            $successCount++;
        } else {
            $failCount++;
            // If endpoint is invalid (410 Gone), we could delete it
        }
    }

    echo json_encode(['success' => true, 'sent' => $successCount, 'failed' => $failCount]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Helper functions for encryption (use web-push-php library or implement manually)
// For simplicity, we assume you have a library like minishlink/web-push installed.
// If not, you need to implement the encryption according to RFC 8291.
// The following functions are placeholders – you must implement or use a library.
function generateVapidJWT($publicKey, $privateKey, $audience) {
    // Implement VAPID JWT generation
    // Use a library like 'lcobucci/jwt' or manual
    // Return the JWT string
    return ''; // placeholder
}
function encryptPayload($payload, $p256dh, $auth) {
    // Implement RFC 8291 encryption
    // Return the encrypted binary string
    return ''; // placeholder
}