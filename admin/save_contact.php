<?php
chdir(__DIR__ . '/../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$location = trim($_POST['location'] ?? '');
$phone1   = trim($_POST['phone1'] ?? '');
$phone2   = trim($_POST['phone2'] ?? '');
$email    = trim($_POST['email'] ?? '');

try {
    $pdo = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table exists, create if not
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_school_contacts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `location` varchar(255) DEFAULT NULL,
        `phone1` varchar(20) DEFAULT NULL,
        `phone2` varchar(20) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if a record already exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_school_contacts");
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        // Update the first row
        $stmt = $pdo->prepare("UPDATE tbl_school_contacts SET location = ?, phone1 = ?, phone2 = ?, email = ? WHERE id = 1");
        $stmt->execute([$location, $phone1, $phone2, $email]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO tbl_school_contacts (location, phone1, phone2, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$location, $phone1, $phone2, $email]);
    }

    echo json_encode(['success' => true, 'message' => 'Contact information saved successfully.']);
} catch (PDOException $e) {
    error_log("Contact save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}