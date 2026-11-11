<?php
// admin/save_image.php
chdir(__DIR__ . '/../');
session_start();

require_once 'db/config.php';
require_once 'const/check_session.php';

// Only admin level 0
if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image']) || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$type = $_POST['type']; // 'signature' or 'seal'
if (!in_array($type, ['signature', 'seal'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type']);
    exit;
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error code: ' . $file['error']]);
    exit;
}

// Validate file type
$allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Only images (PNG, JPG, GIF, WEBP) are allowed.']);
    exit;
}

// Define upload directory
$uploadDir = __DIR__ . '/../images/' . $type . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid($type . '_', true) . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

$relativePath = 'images/' . $type . '/' . $filename;

// Database update
try {
    $pdo = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure table has file_path column (if not, add it)
    $pdo->exec("ALTER TABLE tbl_images ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NULL AFTER image_type");
    $pdo->exec("ALTER TABLE tbl_images ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER file_path");

    // Insert or update – now using file_path (image_data can be NULL)
    $sql = "INSERT INTO tbl_images (image_type, file_path, file_name, updated_at)
            VALUES (:type, :path, :name, NOW())
            ON DUPLICATE KEY UPDATE
                file_path = VALUES(file_path),
                file_name = VALUES(file_name),
                updated_at = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type' => $type,
        ':path' => $relativePath,
        ':name' => $filename
    ]);

    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' uploaded successfully.']);

} catch (PDOException $e) {
    error_log('Image upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}