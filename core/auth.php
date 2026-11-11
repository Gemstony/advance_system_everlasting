<?php
// core/auth.php – works for ALL users
session_start();
chdir('../'); // go to project root
require_once('db/config.php');
require_once('const/rand.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:../");
    exit;
}

$_username = trim($_POST['username']);
$_password = $_POST['password'];
$cookie_length = "4320";

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Try staff table first
    $stmt = $conn->prepare("SELECT id, email, password, level, status FROM tbl_staff WHERE id = ? OR email = ?");
    $stmt->execute([$_username, $_username]);
    $result = $stmt->fetchAll();

    $is_staff = false;
    $row = null;

    if (count($result) > 0) {
        $row = $result[0];
        $is_staff = true;
    } else {
        // Try student table
        $stmt = $conn->prepare("SELECT id, email, password, level, status FROM tbl_students WHERE id = ? OR email = ?");
        $stmt->execute([$_username, $_username]);
        $result = $stmt->fetchAll();
        if (count($result) > 0) {
            $row = $result[0];
            $is_staff = false;
        }
    }

    if (!$row) {
        $_SESSION['reply'] = [["danger", "Invalid login credentials"]];
        header("location:../");
        exit;
    }

    if ($row['status'] == 0) {
        $_SESSION['reply'] = [["danger", "Your account is blocked"]];
        header("location:../");
        exit;
    }

    if (!password_verify($_password, $row['password'])) {
        $_SESSION['reply'] = [["danger", "Invalid login credentials"]];
        header("location:../");
        exit;
    }

    $account_id = $row['id'];
    $session_id = mb_strtoupper(GRS(20));
    $ip = $_SERVER['REMOTE_ADDR'];

    // Delete old sessions
    if ($is_staff) {
        $stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff = ?");
        $stmt->execute([$account_id]);
    } else {
        $stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student = ?");
        $stmt->execute([$account_id]);
    }

    // Insert new session with NULL for unused column
    if ($is_staff) {
        $stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, student, ip_address) VALUES (?, ?, NULL, ?)");
        $stmt->execute([$session_id, $account_id, $ip]);
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, student, ip_address) VALUES (?, NULL, ?, ?)");
        $stmt->execute([$session_id, $account_id, $ip]);
    }

    setcookie("__SRMS__logged", $row['level'], time() + (60 * $cookie_length), "/");
    setcookie("__SRMS__key", $session_id, time() + (60 * $cookie_length), "/");

    // Redirect
    if ($is_staff) {
        switch ($row['level']) {
            case '0': header("location:../admin"); break;
            case '1': header("location:../academic"); break;
            case '2': header("location:../teacher"); break;
            case '3': header("location:../accountant"); break;
            default: header("location:../"); break;
        }
    } else {
        header("location:../student");
    }
    exit;

} catch(PDOException $e) {
    error_log("Auth error: " . $e->getMessage());
    $_SESSION['reply'] = [["danger", "Database connection failed. Please try again later."]];
    header("location:../");
    exit;
}
?>