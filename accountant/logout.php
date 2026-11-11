<?php
// accountant/logout.php – dedicated logout for accountant users
chdir('../');
session_start();

// Destroy PHP session
$_SESSION = array();
session_destroy();

// Delete the authentication cookies
setcookie("__SRMS__logged", "", time() - 3600, "/");
setcookie("__SRMS__key", "", time() - 3600, "/");

// Remove the session record from the database
try {
    if (isset($_COOKIE["__SRMS__key"])) {
        $session_key = $_COOKIE["__SRMS__key"];
        require_once('db/config.php');
        $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
        $stmt->execute([$session_key]);
    }
} catch (Exception $e) {
    // Silent fail – not critical
}

// Redirect to the login page (root index)
header("Location: ../index.php");
exit;
?>