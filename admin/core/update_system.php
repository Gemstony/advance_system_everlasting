<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:../");
    exit;
}

$schoolName = trim($_POST['name'] ?? '');
$oldLogo    = $_POST['old_logo'] ?? '';
$removeLogo = isset($_POST['remove_logo']) ? (int)$_POST['remove_logo'] : 0;

$redirect = isset($_GET['redirect']) && $_GET['redirect'] === 'dashboard' ? '../admin' : '../system';

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---- Correct logo directory (relative to project root after chdir) ----
    $target_dir = 'images/logo/';   // Note: no leading slash, relative to current dir (project root)
    $absolute_dir = __DIR__ . '/../../' . $target_dir; // for file operations

    // Create directory if it doesn't exist
    if (!is_dir($absolute_dir)) {
        if (!mkdir($absolute_dir, 0755, true)) {
            throw new Exception("Failed to create logo directory. Check permissions.");
        }
    }
    if (!is_writable($absolute_dir)) {
        throw new Exception("Logo directory is not writable. Check permissions.");
    }

    // Handle logo removal (if requested)
    if ($removeLogo && !empty($oldLogo)) {
        $oldPath = $absolute_dir . $oldLogo;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
        $oldLogo = ''; // clear logo in database
    }

    // Handle new logo upload
    if (!empty($_FILES['company_logo']['name'])) {
        $file = $_FILES['company_logo'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in HTML form.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.'
            ];
            $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
            throw new Exception("Upload error: $errorMsg");
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size exceeds 2MB limit.");
        }

        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Only JPG, JPEG and PNG files are allowed.");
        }

        // Optional: verify MIME type for security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMime = ['image/jpeg', 'image/png'];
        if (!in_array($mime, $allowedMime)) {
            throw new Exception("Invalid image type. Only JPEG and PNG allowed.");
        }

        // Generate unique filename
        $newLogo = 'school_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadPath = $absolute_dir . $newLogo;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to move uploaded file. Check directory permissions.");
        }

        // Delete old logo if it exists and is different
        if (!empty($oldLogo) && file_exists($absolute_dir . $oldLogo) && $oldLogo !== $newLogo) {
            unlink($absolute_dir . $oldLogo);
        }

        // Update database with new logo (only name and logo – other settings untouched)
        $stmt = $conn->prepare("UPDATE tbl_school SET name = ?, logo = ? WHERE id = 1");
        $stmt->execute([$schoolName, $newLogo]);

    } else {
        // No new logo – just update name (keep current logo, or cleared if removed)
        $stmt = $conn->prepare("UPDATE tbl_school SET name = ?, logo = ? WHERE id = 1");
        $stmt->execute([$schoolName, $oldLogo]);
    }

    $_SESSION['reply'] = [["success", "System settings updated successfully."]];
    header("location: $redirect");
    exit;

} catch (Exception $e) {
    error_log("System update error: " . $e->getMessage());
    $_SESSION['reply'] = [["danger", "Update failed: " . $e->getMessage()]];
    header("location: $redirect");
    exit;
}
?>