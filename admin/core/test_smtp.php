<?php
// admin/core/test_smtp.php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

// Only allow admin
if ($res != "1" || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Fetch SMTP settings from database
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $stmt = $pdo->query("SELECT * FROM tbl_smtp LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if (!$smtp) {
    echo json_encode(['success' => false, 'error' => 'SMTP settings not found']);
    exit;
}

// Get POST data
$email = $_POST['email'] ?? '';
$message = $_POST['message'] ?? 'This is a test email from SRMS.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid recipient email']);
    exit;
}

// Use PHPMailer (make sure it's installed via Composer)
require_once('vendor/autoload.php'); // adjust path if needed

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $smtp['server'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['username'];
    $mail->Password   = $smtp['password'];
    $mail->SMTPSecure = $smtp['encryption'];
    $mail->Port       = $smtp['port'];

    $mail->setFrom($smtp['username'], 'SRMS Test');
    $mail->addAddress($email);
    $mail->Subject = 'SRMS SMTP Test';
    $mail->Body    = $message;

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}