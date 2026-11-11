<?php
// const/school.php - School Configuration with YouTube API Key

// Define your YouTube API Key here (get from Google Cloud Console)
// IMPORTANT: Replace 'YOUR_ACTUAL_YOUTUBE_API_KEY' with your real key
define('YOUTUBE_API_KEY', 'AIzaSyDVgsYMCY_dYxjFpZhblvkr2CS4ow3aADM');

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT * FROM tbl_school LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetchAll();
    foreach($result as $row) {
        define('WBName', $row[1]);
        define('WBLogo', $row[2]);
        define('WBResSys', $row[3]);
        define('WBResAvi', $row[4]);
    }
} catch(PDOException $e) {
    // Handle error silently or log as needed
}
?>