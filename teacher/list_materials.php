<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

$teacher_id = $account_id;
$message = '';
$error = '';

// Current page path for safe redirects and delete links
$current_page = strtok($_SERVER['REQUEST_URI'], '?');

// -------------------------------------------------------------------
// Helper to extract YouTube video ID
// -------------------------------------------------------------------
function getYouTubeId($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    if (isset($query['v'])) return $query['v'];
    if (preg_match('/youtu\.be\/([^\/\?&]+)/', $url, $match)) return $match[1];
    return null;
}

// -------------------------------------------------------------------
// Handle Delete (PDF or Video)
// -------------------------------------------------------------------
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && isset($_GET['type'])) {
    $item_id = (int)$_GET['delete'];
    $type = $_GET['type'];
    
    if ($type == 'pdf') {
        $stmt = $pdo->prepare("SELECT file_path FROM tbl_teaching_materials WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$item_id, $teacher_id]);
        $material = $stmt->fetch();
        if ($material) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . $material['file_path'];
            if (file_exists($full_path)) unlink($full_path);
            $del = $pdo->prepare("DELETE FROM tbl_teaching_materials WHERE id = ?");
            $del->execute([$item_id]);
            $_SESSION['reply'] = [['success', 'PDF material deleted successfully.']];
        } else {
            $_SESSION['reply'] = [['danger', 'Material not found or you do not have permission.']];
        }
    } elseif ($type == 'video') {
        $del = $pdo->prepare("DELETE FROM tbl_video_materials WHERE id = ? AND teacher_id = ?");
        $del->execute([$item_id, $teacher_id]);
        if ($del->rowCount() > 0) {
            $_SESSION['reply'] = [['success', 'Video material deleted successfully.']];
        } else {
            $_SESSION['reply'] = [['danger', 'Video not found or you do not have permission.']];
        }
    }

    header("Location: " . $current_page);
    exit;
}

// -------------------------------------------------------------------
// Handle Edit PDF
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_pdf'])) {
    $material_id = (int)$_POST['material_id'];
    $title = trim($_POST['title']);
    $instructions = trim($_POST['instructions']);
    $update_pdf = isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == UPLOAD_ERR_OK;

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        $stmt = $pdo->prepare("SELECT file_path FROM tbl_teaching_materials WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$material_id, $teacher_id]);
        $material = $stmt->fetch();
        if (!$material) {
            $error = "Material not found or you do not have permission.";
        } else {
            $new_file_path = $material['file_path'];
            $new_file_name = null;
            $new_file_size = null;
            $new_mime_type = null;

            if ($update_pdf) {
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
                        $old_path = $_SERVER['DOCUMENT_ROOT'] . $material['file_path'];
                        if (file_exists($old_path)) unlink($old_path);
                        $new_file_path = '/uploads/materials/' . $safe_filename;
                        $new_file_name = $file['name'];
                        $new_file_size = $file['size'];
                        $new_mime_type = $mime_type;
                    } else {
                        $error = "Failed to upload new PDF.";
                    }
                }
            }

            if (empty($error)) {
                if ($update_pdf) {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_teaching_materials 
                        SET title = ?, instructions = ?, file_name = ?, file_path = ?, file_size = ?, mime_type = ?
                        WHERE id = ? AND teacher_id = ?
                    ");
                    $stmt->execute([$title, $instructions, $new_file_name, $new_file_path, $new_file_size, $new_mime_type, $material_id, $teacher_id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE tbl_teaching_materials 
                        SET title = ?, instructions = ?
                        WHERE id = ? AND teacher_id = ?
                    ");
                    $stmt->execute([$title, $instructions, $material_id, $teacher_id]);
                }
                $_SESSION['reply'] = [['success', 'PDF material updated successfully.']];
                header("Location: list_materials");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------------
// Handle Edit Video
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_video'])) {
    $video_id = (int)$_POST['video_id'];
    $title = trim($_POST['video_title']);
    $instructions = trim($_POST['video_instructions']);
    $youtube_url = trim($_POST['youtube_url']);
    
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($youtube_url)) {
        $error = "YouTube URL is required.";
    } else {
        $video_id_from_url = getYouTubeId($youtube_url);
        if (!$video_id_from_url) {
            $error = "Invalid YouTube URL.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE tbl_video_materials 
                SET title = ?, instructions = ?, youtube_url = ?, video_id = ?
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([$title, $instructions, $youtube_url, $video_id_from_url, $video_id, $teacher_id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['reply'] = [['success', 'Video material updated successfully.']];
            } else {
                $_SESSION['reply'] = [['info', 'No changes made or video not found.']];
            }
            header("Location: list_materials");
            exit;
        }
    }
}

// -------------------------------------------------------------------
// Filtering (class & subject)
// -------------------------------------------------------------------
$filter_class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$filter_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Build filter WHERE clauses
$class_condition = $filter_class > 0 ? "AND m.class_id = $filter_class" : "";
$subject_condition = $filter_subject > 0 ? "AND m.subject_combination_id = $filter_subject" : "";

// Query PDF materials
$pdf_query = "
    SELECT 'pdf' as type, m.id, m.title, m.instructions, m.file_path, m.uploaded_at,
           c.name as class_name, s.name as subject_name
    FROM tbl_teaching_materials m
    JOIN tbl_classes c ON m.class_id = c.id
    JOIN tbl_subject_combinations sc ON m.subject_combination_id = sc.id
    JOIN tbl_subjects s ON sc.subject = s.id
    WHERE m.teacher_id = $teacher_id
    $class_condition $subject_condition
";

// Query video materials
$video_query = "
    SELECT 'video' as type, v.id, v.title, v.instructions, v.youtube_url as file_path, v.created_at as uploaded_at,
           c.name as class_name, s.name as subject_name
    FROM tbl_video_materials v
    JOIN tbl_classes c ON v.class_id = c.id
    JOIN tbl_subject_combinations sc ON v.subject_combination_id = sc.id
    JOIN tbl_subjects s ON sc.subject = s.id
    WHERE v.teacher_id = $teacher_id
    $class_condition $subject_condition
";

// Combine and order by upload date
$final_query = "($pdf_query) UNION ALL ($video_query) ORDER BY uploaded_at DESC";
$stmt = $pdo->prepare($final_query);
$stmt->execute();
$all_materials = $stmt->fetchAll();

// Fetch distinct classes and subjects for filter dropdowns (from both tables)
$stmt_classes = $pdo->prepare("
    (SELECT DISTINCT c.id, c.name 
     FROM tbl_teaching_materials m
     JOIN tbl_classes c ON m.class_id = c.id
     WHERE m.teacher_id = ?)
    UNION
    (SELECT DISTINCT c.id, c.name 
     FROM tbl_video_materials v
     JOIN tbl_classes c ON v.class_id = c.id
     WHERE v.teacher_id = ?)
    ORDER BY name
");
$stmt_classes->execute([$teacher_id, $teacher_id]);
$filter_classes = $stmt_classes->fetchAll();

$stmt_subjects = $pdo->prepare("
    (SELECT DISTINCT sc.id, s.name as subject_name
     FROM tbl_teaching_materials m
     JOIN tbl_subject_combinations sc ON m.subject_combination_id = sc.id
     JOIN tbl_subjects s ON sc.subject = s.id
     WHERE m.teacher_id = ?)
    UNION
    (SELECT DISTINCT sc.id, s.name as subject_name
     FROM tbl_video_materials v
     JOIN tbl_subject_combinations sc ON v.subject_combination_id = sc.id
     JOIN tbl_subjects s ON sc.subject = s.id
     WHERE v.teacher_id = ?)
    ORDER BY subject_name
");
$stmt_subjects->execute([$teacher_id, $teacher_id]);
$filter_subjects = $stmt_subjects->fetchAll();

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);

include 'inc/header.php';
include 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-files me-2"></i> My Uploaded Materials</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item active">My Uploads</li>
        </ul>
    </div>

    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="tile mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter by Class</label>
                <select name="class" class="form-select">
                    <option value="0">All Classes</option>
                    <?php foreach ($filter_classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filter_class == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Filter by Subject</label>
                <select name="subject" class="form-select">
                    <option value="0">All Subjects</option>
                    <?php foreach ($filter_subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($filter_subject == $sub['id']) ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Materials Table (PDF + Video) -->
    <div class="tile">
        <div class="d-flex justify-content-between mb-3">
            <h4><i class="bi bi-archive"></i> All Uploaded Materials</h4>
            <a href="teacher/create_material" class="btn btn-success"><i class="bi bi-plus-circle"></i> Upload New</a>
        </div>
        <?php if (count($all_materials) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Uploaded</th>
                            <th>Instructions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($all_materials as $row): ?>
                            <tr>
                                <td><?= $sn++ ?></td>
                                <td>
                                    <?php if ($row['type'] == 'pdf'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-file-pdf"></i> PDF</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><i class="bi bi-youtube"></i> Video</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['subject_name']) ?></span></td>
                                <td><?= date('d M Y, H:i', strtotime($row['uploaded_at'])) ?></td>
                                <td><?= nl2br(htmlspecialchars(substr($row['instructions'], 0, 80))) ?></td>
                                <td>
                                    <?php if ($row['type'] == 'pdf'): ?>
                                        <a href="<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary" title="View PDF">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <button class="btn btn-sm btn-warning edit-pdf-btn" data-bs-toggle="modal" data-bs-target="#editPdfModal"
                                                data-id="<?= $row['id'] ?>" 
                                                data-title="<?= htmlspecialchars($row['title']) ?>" 
                                                data-instructions="<?= htmlspecialchars($row['instructions']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="<?= htmlspecialchars($current_page) ?>?delete=<?= $row['id'] ?>&type=pdf" class="btn btn-sm btn-danger" onclick="return confirm('Delete this PDF material?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary watch-video-btn" data-bs-toggle="modal" data-bs-target="#watchVideoModal"
                                                data-video-url="<?= htmlspecialchars($row['file_path']) ?>"
                                                data-video-title="<?= htmlspecialchars($row['title']) ?>">
                                            <i class="bi bi-play-circle"></i> Watch
                                        </button>
                                        <button class="btn btn-sm btn-warning edit-video-btn" data-bs-toggle="modal" data-bs-target="#editVideoModal"
                                                data-id="<?= $row['id'] ?>" 
                                                data-title="<?= htmlspecialchars($row['title']) ?>" 
                                                data-instructions="<?= htmlspecialchars($row['instructions']) ?>"
                                                data-youtube-url="<?= htmlspecialchars($row['file_path']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="<?= htmlspecialchars($current_page) ?>?delete=<?= $row['id'] ?>&type=video" class="btn btn-sm btn-danger" onclick="return confirm('Delete this video material?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">No materials uploaded yet. <a href="teacher/create_material">Upload your first material</a>.</div>
        <?php endif; ?>
    </div>
</main>

<!-- Edit PDF Modal -->
<div class="modal fade" id="editPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit PDF Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="material_id" id="edit_pdf_id">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="edit_pdf_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instructions (Optional)</label>
                        <textarea name="instructions" id="edit_pdf_instructions" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Replace PDF (Optional)</label>
                        <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                        <small class="text-muted">Leave empty to keep current PDF. Max size: 10MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_pdf" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Video Modal -->
<div class="modal fade" id="editVideoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Edit YouTube Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="video_id" id="edit_video_id">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="video_title" id="edit_video_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instructions (Optional)</label>
                        <textarea name="video_instructions" id="edit_video_instructions" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">YouTube URL *</label>
                        <input type="text" name="youtube_url" id="edit_video_url" class="form-control" required placeholder="https://www.youtube.com/watch?v=...">
                        <div id="editVideoPreview" class="mt-2" style="display: none;">
                            <p>Preview:</p>
                            <iframe width="100%" height="250" src="" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_video" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Watch Video Modal -->
<div class="modal fade" id="watchVideoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="watchVideoTitle">Watch Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-16x9">
                    <iframe id="watchVideoIframe" src="" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
function extractVideoId(url) {
    if (!url) return null;
    var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    var match = url.match(regExp);
    if (match && match[2].length === 11) return match[2];
    var shortMatch = url.match(/youtu\.be\/([^\/\?&]+)/);
    if (shortMatch) return shortMatch[1];
    return null;
}

$(document).ready(function() {
    // Edit PDF modal population
    $('.edit-pdf-btn').on('click', function() {
        var id = $(this).data('id');
        var title = $(this).data('title');
        var instructions = $(this).data('instructions');
        $('#edit_pdf_id').val(id);
        $('#edit_pdf_title').val(title);
        $('#edit_pdf_instructions').val(instructions);
    });

    // Edit Video modal population
    $('.edit-video-btn').on('click', function() {
        var id = $(this).data('id');
        var title = $(this).data('title');
        var instructions = $(this).data('instructions');
        var url = $(this).data('youtube-url');
        $('#edit_video_id').val(id);
        $('#edit_video_title').val(title);
        $('#edit_video_instructions').val(instructions);
        $('#edit_video_url').val(url);
        // Show preview
        var videoId = extractVideoId(url);
        if (videoId) {
            $('#editVideoPreview iframe').attr('src', 'https://www.youtube.com/embed/' + videoId);
            $('#editVideoPreview').show();
        } else {
            $('#editVideoPreview').hide();
        }
    });

    // Live preview when editing video URL
    $('#edit_video_url').on('input', function() {
        var url = $(this).val();
        var videoId = extractVideoId(url);
        if (videoId) {
            $('#editVideoPreview iframe').attr('src', 'https://www.youtube.com/embed/' + videoId);
            $('#editVideoPreview').show();
        } else {
            $('#editVideoPreview').hide();
        }
    });

    // Watch video modal
    $('.watch-video-btn').on('click', function() {
        var url = $(this).data('video-url');
        var title = $(this).data('video-title');
        var videoId = extractVideoId(url);
        if (videoId) {
            $('#watchVideoIframe').attr('src', 'https://www.youtube.com/embed/' + videoId);
            $('#watchVideoTitle').text(title);
        } else {
            $('#watchVideoIframe').attr('src', '');
            $('#watchVideoTitle').text('Invalid video URL');
        }
    });

    // Clear video iframe when modal closes
    $('#watchVideoModal').on('hidden.bs.modal', function() {
        $('#watchVideoIframe').attr('src', '');
    });
});
</script>

<style>
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}
.btn-primary { background: #3b7cff; border: none; }
.btn-primary:hover { background: #2a62d0; }
.btn-success { background: #28a745; border: none; }
.btn-success:hover { background: #1e7e34; }
.table th { background: #f8f9fa; }
.badge { font-size: 0.8rem; }
.modal-content { background: white; border-radius: 20px; }
.app-content { background: transparent !important; }
</style>
</body>
</html>