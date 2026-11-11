<?php
define('DBHost', '162.214.152.209');
define('DBName', 'everlast_alevel_db');    // or your actual database name
define('DBUser', 'everlast_everlastingacademy.sql');
define('DBPass', 'Everlastingacademy@24');
define('DBCharset', 'utf8mb4');
define('DBCollation', 'utf8mb4_general_ci');

$con = mysqli_connect(DBHost, DBUser, DBPass, DBName);
if (!$con) {
    error_log("MySQLi connection failed: " . mysqli_connect_error());
    die("Database connection failed.");
}
mysqli_set_charset($con, DBCharset);

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset;
            $pdo = new PDO($dsn, DBUser, DBPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PDO connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }
    return $pdo;
}
?>