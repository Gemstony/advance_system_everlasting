<?php
// Database configuration for Everlasting Academy
define('DBHost', 'localhost'); // Your server IP
define('DBName', 'everlast_alevel2'); // New database
define('DBUser', 'root'); // New DB user
define('DBPass', ''); // DB password
define('DBCharset', 'utf8mb4'); 
define('DBCollation', 'utf8mb4_general_ci'); 
define('DBPrefix', ''); // leave empty if no prefix
define('OLLAMA_API_KEY', 'c5d66dee6b2e4dfcba1361764c16a6fd.q0UNIGEHQxKwKpnmb9sjhOKu');
// Optional mysqli connection
$con = mysqli_connect(DBHost, DBUser, DBPass, DBName);

// Check connection
if (!$con) {
    error_log("MySQLi connection failed: " . mysqli_connect_error());
    die("Database connection failed.");
}

// Set charset
mysqli_set_charset($con, DBCharset);

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("PDO Database connection failed: " . $e->getMessage());
}
?>
