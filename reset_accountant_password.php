<?php
// reset_accountant_password.php – run once, then delete
require_once('db/config.php');

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Generate correct hash for "accountant123"
    $hash = password_hash('accountant123', PASSWORD_DEFAULT);

    // Update the accountant's password
    $stmt = $pdo->prepare("UPDATE tbl_staff SET password = ? WHERE email = 'accountant@everlasting.academy' AND level = 3");
    $stmt->execute([$hash]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Password for accountant@everlasting.academy has been reset to: <strong>accountant123</strong><br>";
        echo "Hash used: " . $hash;
    } else {
        echo "❌ Accountant not found. Please insert the accountant first.<br>";
        echo "Run this SQL to create the accountant:<br>";
        echo "INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES ('John', 'Accountant', 'Male', 'accountant@everlasting.academy', '" . $hash . "', 3, 1);";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>