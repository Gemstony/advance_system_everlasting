<?php
// RUN THIS ONCE to create the admin user after you import testimonial.sql
// Edit the variables below then run `php setup.php` from the command line or open it in browser.
require_once __DIR__ . '/config.php';

// Change these before running
$adminUsername = 'admin';
$adminPassword = 'ChangeMeStrongPassword!';

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
    $stmt->execute([$adminUsername]);
    if ($stmt->fetchColumn() > 0) {
        echo "Admin user already exists. Exiting.\n";
        exit;
    }
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
    $ins->execute([$adminUsername, $hash]);
    echo "Admin user created successfully. Username: {$adminUsername}\n";
    echo "Please delete or protect setup.php after use.\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>