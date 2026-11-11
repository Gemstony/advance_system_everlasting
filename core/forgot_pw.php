<?php
session_start();
chdir('../');
require_once('db/config.php');
require_once('const/rand.php'); // for GP() function
require_once('const/school.php'); // WBName constant

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'mail/src/Exception.php';
require 'mail/src/PHPMailer.php';
require 'mail/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = trim($_POST['username']); // sanitize input

    if (empty($input)) {
        $_SESSION['reply'] = [['danger', 'Please enter a username or email']];
        header("Location: ../");
        exit;
    }

    try {
        $conn = new PDO(
            'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset,
            DBUser, DBPass
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Look up user in staff and students
        $stmt = $conn->prepare("
            SELECT id, fname, email, level, 'staff' AS type FROM tbl_staff WHERE id = ? OR email = ?
            UNION
            SELECT id, fname, email, level, 'student' AS type FROM tbl_students WHERE id = ? OR email = ?
        ");
        $stmt->execute([$input, $input, $input, $input]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            $_SESSION['reply'] = [['danger', 'Account was not found']];
            header("Location: ../");
            exit;
        }

        foreach ($result as $row) {

            $accountId = $row['id'];
            $name = $row['fname'];
            $email = $row['email'];
            $level = $row['level'];
            $userType = $row['type'];

            // Generate secure token for password reset
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in a password_resets table
            $stmtToken = $conn->prepare("
                INSERT INTO password_resets (user_id, user_type, token, expires)
                VALUES (?, ?, ?, ?)
            ");
            $stmtToken->execute([$accountId, $userType, $token, $expires]);

            // Reset link
            $resetLink = "https://yourdomain.com/reset_password.php?token=$token";

            // Email message
            $msg = "<h3 style='font-size:22px;'>Password Reset Request</h3>
                    <p style='font-size:18px;'>Hello $name,<br>
                    We received a request to reset your password.<br>
                    Click the link below to set a new password. This link will expire in 1 hour.<br><br>
                    <a href='$resetLink'>$resetLink</a></p>";

            // Send email
            $mail = new PHPMailer;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->isSMTP();
            $mail->Host = $smtp_server;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = $smtp_conn_type;
            $mail->Port = $smtp_conn_port;

            $mail->setFrom($smtp_username, WBName);
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset';
            $mail->Body = $msg;
            $mail->AltBody = "Reset your password using this link: $resetLink";

            if (!$mail->send()) {
                $_SESSION['reply'] = [['danger', 'Mail error: ' . $mail->ErrorInfo]];
            } else {
                $_SESSION['reply'] = [['success', "If this account exists, a reset link was sent to $email"]];
            }
        }

        header("Location: ../");
        exit;

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
} else {
    header("Location: ../");
    exit;
}
?>
