<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    die("Access denied.");
}

if (!isset($_POST['submit'])) {
    header("location:../");
    exit;
}

$email = trim($_POST['email']);

if (empty($email)) {
    $_SESSION['fail'] = "Email address is required.";
    header("location:../settings");
    exit;
}

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if email already exists for another student
    $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE email = ? AND id != ?");
    $stmt->execute([$email, $account_id]);
    if ($stmt->fetch()) {
        $_SESSION['fail'] = "Email address is already in use by another student.";
        header("location:../settings");
        exit;
    }
    
    // Update email
    $stmt = $conn->prepare("UPDATE tbl_students SET email = ? WHERE id = ?");
    $stmt->execute([$email, $account_id]);
    
    // Update session variable for email
    $_SESSION['email'] = $email;
    
    $_SESSION['success'] = "Email address updated successfully.";
    header("location:../settings");
    
} catch (PDOException $e) {
    $_SESSION['fail'] = "Database error: " . $e->getMessage();
    header("location:../settings");
}
?>