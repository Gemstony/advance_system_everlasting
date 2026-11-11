<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Teacher only (level 2)
if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

$teacher_id = $account_id;

// -------------------------------------------------------------------
// Get parameters from URL (must be present)
// -------------------------------------------------------------------
$class_id = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$term_id = isset($_GET['term']) ? (int)$_GET['term'] : 0;

// If any parameter missing, redirect back to selection page
if (!$class_id || !$subject_id || !$term_id) {
    $_SESSION['reply'] = [['danger', 'Missing class, subject, or term. Please select again.']];
    header("Location: create_material");
    exit;
}

// -------------------------------------------------------------------
// Verify that the teacher teaches this combination and fetch names
// -------------------------------------------------------------------
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify class exists and teacher teaches it
    $stmt = $conn->prepare("
        SELECT sc.id, s.name as subject_name, c.name as class_name
        FROM tbl_subject_combinations sc
        JOIN tbl_subjects s ON sc.subject = s.id
        JOIN tbl_classes c ON FIND_IN_SET(c.id, sc.class)
        WHERE sc.id = ? AND sc.teacher = ? AND c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$subject_id, $teacher_id, $class_id]);
    $info = $stmt->fetch();

    if (!$info) {
        $_SESSION['reply'] = [['danger', 'Invalid class or subject selection.']];
        header("Location: create_material");
        exit;
    }

    // Verify term exists and is active
    $term_check = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ? AND status = 1");
    $term_check->execute([$term_id]);
    $term_info = $term_check->fetch();
    if (!$term_info) {
        $_SESSION['reply'] = [['danger', 'Invalid or inactive term.']];
        header("Location: create_material");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['reply'] = [['danger', 'Database error: ' . $e->getMessage()]];
    header("Location: create_material");
    exit;
}

// -------------------------------------------------------------------
// Process PDF upload
// -------------------------------------------------------------------
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_material'])) {
    $title = trim($_POST['title']);
    $instructions = trim($_POST['instructions']);

    if (empty($title)) {
        $error = "Please enter a title.";
    } elseif (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] != UPLOAD_ERR_OK) {
        $error = "Please select a valid PDF file.";
    } else {
        $file = $_FILES['pdf_file'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mime_type != 'application/pdf') {
            $error = "Only PDF files are allowed.";
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $error = "File size must be less than 10MB.";
        } else {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $destination = $upload_dir . $safe_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $web_path = '/uploads/materials/' . $safe_filename;
                $stmt = $conn->prepare("
                    INSERT INTO tbl_teaching_materials 
                    (title, instructions, file_name, file_path, file_size, mime_type, class_id, subject_combination_id, teacher_id, term_id, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title,
                    $instructions,
                    $file['name'],
                    $web_path,
                    $file['size'],
                    $mime_type,
                    $class_id,
                    $subject_id,
                    $teacher_id,
                    $term_id
                ]);
                $_SESSION['reply'] = [['success', 'Material uploaded successfully!']];
                header("Location: list_materials");
                exit;
            } else {
                $error = "Failed to move uploaded file. Check folder permissions.";
            }
        }
    }
}

include 'inc/header.php';
include 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-cloud-upload me-2"></i> Upload PDF Material</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="create_material">Create Material</a></li>
            <li class="breadcrumb-item active">Upload PDF</li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="tile">
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Class:</label>
                <strong><?= htmlspecialchars($info['class_name']) ?></strong>
            </div>
            <div class="mb-3">
                <label>Subject:</label>
                <strong><?= htmlspecialchars($info['subject_name']) ?></strong>
            </div>
            <div class="mb-3">
                <label for="title" class="form-label">Material Title *</label>
                <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="instructions" class="form-label">Instructions (Optional)</label>
                <textarea name="instructions" id="instructions" class="form-control" rows="4"></textarea>
            </div>
            <div class="mb-3">
                <label for="pdf_file" class="form-label">PDF File *</label>
                <input type="file" name="pdf_file" id="pdf_file" class="form-control" accept=".pdf" required>
                <small class="text-muted">Max size: 10MB, only PDF allowed.</small>
            </div>
            <button type="submit" name="upload_material" class="btn btn-primary">Upload Material</button>
            <a href="create_material" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</main>

<style>
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.btn-primary {
    background: #3b7cff;
    border: none;
}
.btn-primary:hover {
    background: #2a62d0;
}
.app-content {
    background: transparent !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>