<?php
define('DB_HOST', '162.214.152.209');
define('DB_NAME', 'everlast_testimonial');   // Change to your actual database name
define('DB_USER', 'everlast_everlastingacademy');
define('DB_PASS', 'Everlastingacademy@24');
define('DB_CHARSET', 'utf8mb4');

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PDO connection failed: ' . $e->getMessage());
            die('Testimonials database connection failed.');
        }
    }
    return $pdo;
}
?>