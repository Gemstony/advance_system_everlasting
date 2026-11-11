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
$error = '';
$pdf_error = '';
$video_error = '';

// -------------------------------------------------------------------
// AJAX handler for fetching subjects (supports multiple class IDs)
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_subjects') {
    header('Content-Type: application/json');
    try {
        $class_ids = isset($_GET['class_ids']) ? $_GET['class_ids'] : '';
        if (empty($class_ids)) throw new Exception('No class selected');
        
        $class_array = array_map('intval', explode(',', $class_ids));
        if (empty($class_array)) throw new Exception('Invalid class IDs');
        
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT id, class, subject FROM tbl_subject_combinations WHERE teacher = ?");
        $stmt->execute([$teacher_id]);
        $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subject_ids = [];
        $subject_names = [];
        foreach ($combinations as $comb) {
            $classes = unserialize($comb['class']);
            if (is_array($classes)) {
                foreach ($class_array as $cid) {
                    if (in_array($cid, $classes)) {
                        if (!in_array($comb['subject'], $subject_ids)) {
                            $subject_ids[] = $comb['subject'];
                            $stmt2 = $pdo->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
                            $stmt2->execute([$comb['subject']]);
                            $name = $stmt2->fetchColumn();
                            if ($name) {
                                $subject_names[$comb['subject']] = $name;
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        $subjects = [];
        foreach ($subject_ids as $sid) {
            $subjects[] = ['id' => $sid, 'name' => $subject_names[$sid]];
        }
        
        echo json_encode(['success' => true, 'subjects' => $subjects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// AJAX handler for fetching class details (names + student counts)
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_class_details') {
    header('Content-Type: application/json');
    try {
        $class_ids = isset($_GET['class_ids']) ? $_GET['class_ids'] : '';
        if (empty($class_ids)) throw new Exception('No class selected');
        
        $class_array = array_map('intval', explode(',', $class_ids));
        if (empty($class_array)) throw new Exception('Invalid class IDs');
        
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $placeholders = implode(',', array_fill(0, count($class_array), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($class_array);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get student count per class
        $class_details = [];
        foreach ($classes as $class) {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ? AND status = 1");
            $stmt2->execute([$class['id']]);
            $student_count = (int)$stmt2->fetchColumn();
            $class_details[] = [
                'id' => $class['id'],
                'name' => $class['name'],
                'student_count' => $student_count
            ];
        }
        
        echo json_encode(['success' => true, 'classes' => $class_details]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// Helper: Get teacher's classes and terms
// -------------------------------------------------------------------
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations WHERE teacher = ?");
    $stmt->execute([$teacher_id]);
    $combinations = $stmt->fetchAll();
    
    $myclasses = [];
    foreach ($combinations as $comb) {
        $class_arr = unserialize($comb['class']);
        if (is_array($class_arr)) {
            foreach ($class_arr as $val) $myclasses[] = (int)$val;
        }
    }
    $myclasses = array_unique($myclasses);
    sort($myclasses);
    
    $classes_list = [];
    if (!empty($myclasses)) {
        $placeholders = implode(',', array_fill(0, count($myclasses), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($myclasses);
        $classes_list = $stmt->fetchAll();
    }
    
    $terms = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC");
    $terms->execute();
    $terms = $terms->fetchAll();
    
} catch (PDOException $e) {
    $classes_list = [];
    $terms = [];
    $error = "Database error: " . $e->getMessage();
}

function getYouTubeId($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    if (isset($query['v'])) return $query['v'];
    if (preg_match('/youtu\.be\/([^\/\?&]+)/', $url, $match)) return $match[1];
    return null;
}

function findCombinationId($conn, $teacher_id, $class_id, $subject_id) {
    $stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations WHERE teacher = ? AND subject = ?");
    $stmt->execute([$teacher_id, $subject_id]);
    $combs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($combs as $comb) {
        $classes = unserialize($comb['class']);
        if (is_array($classes) && in_array($class_id, $classes)) {
            return $comb['id'];
        }
    }
    return false;
}

// -------------------------------------------------------------------
// Handle PDF upload
// -------------------------------------------------------------------
$selected_classes = [];
$selected_subject_id = 0;
$selected_term = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_pdf'])) {
    $title = trim($_POST['pdf_title']);
    $instructions = trim($_POST['pdf_instructions']);
    $class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    
    $selected_classes = $class_ids;
    $selected_subject_id = $subject_id;
    $selected_term = $term_id;
    
    if (empty($title)) {
        $pdf_error = "Please enter a title.";
    } elseif (empty($class_ids)) {
        $pdf_error = "Please select at least one class.";
    } elseif ($subject_id <= 0) {
        $pdf_error = "Please select a subject.";
    } elseif (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] != UPLOAD_ERR_OK) {
        $pdf_error = "Please select a valid PDF file.";
    } else {
        $file = $_FILES['pdf_file'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type != 'application/pdf') {
            $pdf_error = "Only PDF files are allowed.";
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $pdf_error = "File size must be less than 10MB.";
        } else {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $destination = $upload_dir . $safe_filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $web_path = '/uploads/materials/' . $safe_filename;
                $success_count = 0;
                $error_classes = [];
                
                foreach ($class_ids as $class_id) {
                    $comb_id = findCombinationId($conn, $teacher_id, $class_id, $subject_id);
                    if (!$comb_id) {
                        $error_classes[] = "Class ID $class_id (no matching subject combination)";
                        continue;
                    }
                    
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
                        $comb_id,
                        $teacher_id,
                        $term_id
                    ]);
                    $success_count++;
                }
                
                if ($success_count > 0) {
                    $msg = "PDF material uploaded successfully to $success_count class(es).";
                    if (!empty($error_classes)) {
                        $msg .= " Failed for: " . implode(', ', $error_classes);
                    }
                    $_SESSION['reply'] = [['success', $msg]];
                    header("Location: list_materials");
                    exit;
                } else {
                    $pdf_error = "Failed to upload for any class. " . implode(', ', $error_classes);
                    @unlink($destination);
                }
            } else {
                $pdf_error = "Failed to move uploaded file. Check folder permissions.";
            }
        }
    }
}

// -------------------------------------------------------------------
// Handle YouTube video upload
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_video'])) {
    $title = trim($_POST['video_title']);
    $instructions = trim($_POST['video_instructions']);
    $youtube_url = trim($_POST['youtube_url']);
    $class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    
    $selected_classes = $class_ids;
    $selected_subject_id = $subject_id;
    $selected_term = $term_id;
    
    if (empty($title)) {
        $video_error = "Please enter a title.";
    } elseif (empty($class_ids)) {
        $video_error = "Please select at least one class.";
    } elseif ($subject_id <= 0) {
        $video_error = "Please select a subject.";
    } elseif (empty($youtube_url)) {
        $video_error = "Please enter a YouTube URL.";
    } else {
        $video_id = getYouTubeId($youtube_url);
        if (!$video_id) {
            $video_error = "Invalid YouTube URL.";
        } else {
            $success_count = 0;
            $error_classes = [];
            
            foreach ($class_ids as $class_id) {
                $comb_id = findCombinationId($conn, $teacher_id, $class_id, $subject_id);
                if (!$comb_id) {
                    $error_classes[] = "Class ID $class_id (no matching subject combination)";
                    continue;
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO tbl_video_materials 
                    (title, instructions, youtube_url, video_id, class_id, subject_combination_id, teacher_id, term_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title,
                    $instructions,
                    $youtube_url,
                    $video_id,
                    $class_id,
                    $comb_id,
                    $teacher_id,
                    $term_id
                ]);
                $success_count++;
            }
            
            if ($success_count > 0) {
                $msg = "YouTube video added successfully to $success_count class(es).";
                if (!empty($error_classes)) {
                    $msg .= " Failed for: " . implode(', ', $error_classes);
                }
                $_SESSION['reply'] = [['success', $msg]];
                header("Location: list_materials");
                exit;
            } else {
                $video_error = "Failed to add video for any class. " . implode(', ', $error_classes);
            }
        }
    }
}

// -------------------------------------------------------------------
// Handle CSV Quiz redirect
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'csv_quiz') {
    $class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    $term_id = (int)$_POST['term_id'];
    
    if (count($class_ids) != 1) {
        $error = "Please select exactly ONE class to create a CSV quiz.";
    } elseif (!$subject_id) {
        $error = "Please select a subject.";
    } elseif (!$term_id) {
        $error = "Please select a term.";
    } else {
        $comb_id = findCombinationId($conn, $teacher_id, $class_ids[0], $subject_id);
        if (!$comb_id) {
            $error = "No valid subject combination found for the selected class and subject.";
        } else {
            header("Location: create_quiz?class=" . $class_ids[0] . "&subject=" . $comb_id . "&term=" . $term_id);
            exit;
        }
    }
}

$preset_class_ids = $selected_classes ?: [];
$preset_subject_id = $selected_subject_id;
$preset_term = $selected_term;

include 'inc/header.php';
include 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-plus-circle me-2"></i> Create Teaching Material</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Step 1: Selection Card with Advanced Class Selector -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h5 class="card-title mb-0">
                <span class="badge bg-primary rounded-pill me-2">1</span> 
                Select Classes, Subject & Term
            </h5>
        </div>
        <div class="card-body">
            <form method="post" id="selectionForm">
                <div class="row g-4">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Classes <span class="text-danger">*</span></label>
                        <!-- Advanced Multi-Select Component -->
                        <div class="advanced-class-selector">
                            <div class="input-group mb-2">
                                <input type="text" id="classSearchInput" class="form-control" placeholder="Search classes..." autocomplete="off">
                                <button type="button" id="selectAllClassesBtn" class="btn btn-outline-secondary">Select All</button>
                                <button type="button" id="deselectAllClassesBtn" class="btn btn-outline-secondary">Clear</button>
                            </div>
                            <div class="class-checkbox-list border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($classes_list as $c): ?>
                                    <div class="form-check class-item" data-class-name="<?= strtolower(htmlspecialchars($c['name'])) ?>">
                                        <input class="form-check-input class-checkbox" type="checkbox" name="temp_class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>" 
                                            <?= in_array($c['id'], $preset_class_ids) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="class_<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted" id="selectedCountDisplay">0 class(es) selected</small>
                                <button type="button" id="applyClassSelectionBtn" class="btn btn-sm btn-primary">Apply Selection</button>
                            </div>
                            <!-- Hidden input to store final selected class IDs for form submission -->
                            <input type="hidden" name="class_ids[]" id="finalClassIds" value="">
                        </div>
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle-fill me-1"></i> Search, check/uncheck classes, then click "Apply Selection". Material will be added to all selected classes.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="subjectSelect" class="form-select form-select-lg" required <?= empty($preset_class_ids) ? 'disabled' : '' ?>>
                            <option value="">Select classes first</option>
                            <?php if ($preset_subject_id): ?>
                                <?php
                                $subj_stmt = $conn->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
                                $subj_stmt->execute([$preset_subject_id]);
                                $subj_name = $subj_stmt->fetchColumn();
                                if ($subj_name): ?>
                                    <option value="<?= $preset_subject_id ?>" selected><?= htmlspecialchars($subj_name) ?></option>
                                <?php endif; ?>
                            <?php endif; ?>
                        </select>
                        <div id="subjectLoading" class="mt-2 text-muted small" style="display: none;">
                            <i class="bi bi-arrow-repeat spinner-grow spinner-grow-sm me-1"></i> Loading subjects...
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
                        <select name="term_id" class="form-select form-select-lg" required>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($preset_term == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Analytical Details Panel (initially hidden) -->
                <div id="classDetailsPanel" class="mt-4 p-3 bg-light rounded-3" style="display: none;">
                    <h6 class="mb-3"><i class="bi bi-bar-chart-steps me-2"></i> Selected Classes Analysis</h6>
                    <div id="classDetailsContent">
                        <!-- Dynamic content loaded via AJAX -->
                    </div>
                </div>
                
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="csv_quiz" class="btn btn-primary" id="csvQuizBtn">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i> Create CSV Quiz (single class)
                    </button>
                    <button type="button" id="showPdfFormBtn" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf me-2"></i> Upload PDF Material
                    </button>
                    <button type="button" id="showVideoFormBtn" class="btn btn-outline-info">
                        <i class="bi bi-youtube me-2"></i> Add YouTube Video
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: PDF Upload Card -->
    <div id="pdfUploadForm" class="card shadow-sm border-0 rounded-4 mb-4" style="display: none;">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h5 class="card-title mb-0">
                <span class="badge bg-secondary rounded-pill me-2">2</span> 
                Upload PDF Material
            </h5>
        </div>
        <div class="card-body">
            <?php if ($pdf_error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($pdf_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="pdfForm">
                <div id="pdfHiddenClassContainer"></div>
                <input type="hidden" name="subject_id" id="pdf_subject_id" value="">
                <input type="hidden" name="term_id" id="pdf_term_id" value="">
                
                <div class="mb-3">
                    <label for="pdf_title" class="form-label fw-semibold">Material Title <span class="text-danger">*</span></label>
                    <input type="text" name="pdf_title" id="pdf_title" class="form-control form-control-lg" required>
                </div>
                <div class="mb-3">
                    <label for="pdf_instructions" class="form-label fw-semibold">Instructions (Optional)</label>
                    <textarea name="pdf_instructions" id="pdf_instructions" class="form-control" rows="4"></textarea>
                </div>
                <div class="mb-4">
                    <label for="pdf_file" class="form-label fw-semibold">PDF File <span class="text-danger">*</span></label>
                    <input type="file" name="pdf_file" id="pdf_file" class="form-control form-control-lg" accept=".pdf" required>
                    <div class="form-text">Max size: 10MB, only PDF files are allowed.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="upload_pdf" class="btn btn-primary">
                        <i class="bi bi-cloud-upload me-2"></i> Upload PDF
                    </button>
                    <button type="button" id="cancelPdfBtn" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: YouTube Video Card -->
    <div id="videoUploadForm" class="card shadow-sm border-0 rounded-4 mb-4" style="display: none;">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h5 class="card-title mb-0">
                <span class="badge bg-secondary rounded-pill me-2">2</span> 
                Add YouTube Video
            </h5>
        </div>
        <div class="card-body">
            <?php if ($video_error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($video_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <form method="post" id="videoForm">
                <div id="videoHiddenClassContainer"></div>
                <input type="hidden" name="subject_id" id="video_subject_id" value="">
                <input type="hidden" name="term_id" id="video_term_id" value="">
                
                <div class="mb-3">
                    <label for="video_title" class="form-label fw-semibold">Video Title <span class="text-danger">*</span></label>
                    <input type="text" name="video_title" id="video_title" class="form-control form-control-lg" required>
                </div>
                <div class="mb-3">
                    <label for="video_instructions" class="form-label fw-semibold">Instructions (Optional)</label>
                    <textarea name="video_instructions" id="video_instructions" class="form-control" rows="4"></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">YouTube URL <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <input type="text" name="youtube_url" id="youtube_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." required>
                        <button type="button" id="browseYoutubeBtn" class="btn btn-outline-secondary">
                            <i class="bi bi-search me-1"></i> Browse
                        </button>
                    </div>
                    <div id="videoPreview" class="mt-3" style="display: none;">
                        <div class="ratio ratio-16x9">
                            <iframe src="" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                    <div class="form-text mt-2">Paste a YouTube URL or click "Browse" to search and select a video.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="upload_video" class="btn btn-primary">
                        <i class="bi bi-youtube me-2"></i> Add Video
                    </button>
                    <button type="button" id="cancelVideoBtn" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- YouTube Search Modal -->
<div class="modal fade" id="youtubeBrowserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-youtube text-danger me-2"></i> Browse YouTube Videos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8 mx-auto">
                        <div class="input-group">
                            <input type="text" id="youtubeSearchQuery" class="form-control form-control-lg" placeholder="e.g., mathematics tutorial grade 10">
                            <button id="searchYoutubeBtn" class="btn btn-primary btn-lg">Search</button>
                        </div>
                    </div>
                </div>
                <div id="youtubeLoading" class="text-center my-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading videos...</p>
                </div>
                <div id="youtubeSearchResults" class="row g-4 mt-2">
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-youtube fs-1"></i>
                        <p class="mt-2">Enter a keyword and click Search to find videos.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    const youtubeApiKey = <?php echo defined('YOUTUBE_API_KEY') ? json_encode(YOUTUBE_API_KEY) : 'null'; ?>;
    
    // ---------- Advanced Class Selector Logic ----------
    function updateSelectedCount() {
        var count = $('.class-checkbox:checked').length;
        $('#selectedCountDisplay').text(count + ' class(es) selected');
    }
    
    function applyClassSelection() {
        var selectedVals = [];
        $('.class-checkbox:checked').each(function() {
            selectedVals.push($(this).val());
        });
        var classIdStr = selectedVals.join(',');
        $('#finalClassIds').val(classIdStr);
        
        // Trigger subject loading
        $('#classSelectTrigger').val(classIdStr).trigger('change');
        
        // Load class details for analytical panel
        if (classIdStr) {
            $.ajax({
                url: 'teacher/create_material.php',
                method: 'GET',
                data: { ajax: 'get_class_details', class_ids: classIdStr },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.classes.length) {
                        var totalStudents = 0;
                        var detailsHtml = '<div class="row g-3">';
                        detailsHtml += '<div class="col-md-6">';
                        detailsHtml += '<div class="d-flex justify-content-between mb-2"><strong>Total classes:</strong> <span class="badge bg-primary rounded-pill">'+res.classes.length+'</span></div>';
                        detailsHtml += '<div class="d-flex justify-content-between mb-2"><strong>Class list:</strong> <span>';
                        res.classes.forEach(function(cls, idx) {
                            detailsHtml += '<span class="badge bg-secondary me-1 mb-1">'+escapeHtml(cls.name)+'</span>';
                        });
                        detailsHtml += '</span></div>';
                        detailsHtml += '</div><div class="col-md-6">';
                        
                        // Calculate total students
                        res.classes.forEach(function(cls) {
                            totalStudents += cls.student_count;
                        });
                        var avgStudents = (totalStudents / res.classes.length).toFixed(1);
                        detailsHtml += '<div class="d-flex justify-content-between mb-2"><strong>Total students:</strong> <span>'+totalStudents+'</span></div>';
                        detailsHtml += '<div class="d-flex justify-content-between mb-2"><strong>Average per class:</strong> <span>'+avgStudents+'</span></div>';
                        detailsHtml += '</div></div>';
                        
                        // Simple bar chart representation (relative class sizes)
                        detailsHtml += '<hr><div class="mt-2"><small class="text-muted">Class size distribution:</small><div class="mt-2">';
                        var maxStudents = Math.max(...res.classes.map(c => c.student_count), 1);
                        res.classes.forEach(function(cls) {
                            var percent = (cls.student_count / maxStudents) * 100;
                            detailsHtml += '<div class="mb-2">';
                            detailsHtml += '<div class="d-flex justify-content-between small"><span>'+escapeHtml(cls.name)+'</span><span>'+cls.student_count+' students</span></div>';
                            detailsHtml += '<div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: '+percent+'%;"></div></div>';
                            detailsHtml += '</div>';
                        });
                        detailsHtml += '</div></div>';
                        
                        $('#classDetailsContent').html(detailsHtml);
                        $('#classDetailsPanel').slideDown();
                    } else {
                        $('#classDetailsPanel').slideUp();
                    }
                },
                error: function() {
                    $('#classDetailsPanel').slideUp();
                }
            });
        } else {
            $('#classDetailsPanel').slideUp();
        }
        
        // Visual feedback
        $('#applyClassSelectionBtn').html('<i class="bi bi-check-circle me-1"></i> Applied');
        setTimeout(function() {
            $('#applyClassSelectionBtn').html('Apply Selection');
        }, 1500);
    }
    
    function getSelectedClassIds() {
        var ids = $('#finalClassIds').val();
        return ids ? ids.split(',') : [];
    }
    
    // Search filter
    $('#classSearchInput').on('input', function() {
        var filter = $(this).val().toLowerCase();
        $('.class-item').each(function() {
            var text = $(this).data('class-name');
            if (text.indexOf(filter) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Select all (only visible items)
    $('#selectAllClassesBtn').on('click', function() {
        $('.class-item:visible .class-checkbox').prop('checked', true);
        updateSelectedCount();
    });
    
    $('#deselectAllClassesBtn').on('click', function() {
        $('.class-checkbox').prop('checked', false);
        updateSelectedCount();
    });
    
    $('.class-checkbox').on('change', updateSelectedCount);
    updateSelectedCount();
    
    // Apply selection button
    $('#applyClassSelectionBtn').on('click', applyClassSelection);
    
    // Hidden trigger for subject loading
    $('form').append('<input type="hidden" id="classSelectTrigger" value="">');
    
    // Initialize with preselected classes
    <?php if (!empty($preset_class_ids)): ?>
        var presetIds = <?= json_encode($preset_class_ids) ?>;
        $('.class-checkbox').each(function() {
            if (presetIds.includes($(this).val())) {
                $(this).prop('checked', true);
            }
        });
        updateSelectedCount();
        applyClassSelection();
    <?php endif; ?>
    
    // Load subjects when class selection changes
    $('#classSelectTrigger').on('change', function() {
        var classIdStr = $(this).val();
        if (classIdStr) {
            $('#subjectSelect').prop('disabled', true).html('<option>Loading...</option>');
            $('#subjectLoading').show();
            $.ajax({
                url: 'teacher/create_material.php',
                method: 'GET',
                data: { ajax: 'get_subjects', class_ids: classIdStr },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.subjects.length) {
                        var opts = '<option value="">Select Subject</option>';
                        $.each(res.subjects, function(i, s) {
                            var selected = (s.id == <?= json_encode($preset_subject_id) ?>) ? 'selected' : '';
                            opts += '<option value="'+s.id+'" '+selected+'>'+s.name+'</option>';
                        });
                        $('#subjectSelect').html(opts).prop('disabled', false);
                    } else {
                        $('#subjectSelect').html('<option value="">No subjects for selected classes</option>').prop('disabled', true);
                    }
                },
                error: function() {
                    $('#subjectSelect').html('<option value="">Failed to load subjects</option>').prop('disabled', true);
                },
                complete: function() {
                    $('#subjectLoading').hide();
                }
            });
        } else {
            $('#subjectSelect').prop('disabled', true).html('<option value="">Select classes first</option>');
        }
    });
    
    // Show PDF form
    $('#showPdfFormBtn').on('click', function() {
        var classIds = getSelectedClassIds();
        var subjectId = $('#subjectSelect').val();
        var termId = $('select[name="term_id"]').val();
        if (!classIds.length) { alert('Please select at least one class.'); return; }
        if (!subjectId) { alert('Please select a subject.'); return; }
        if (!termId) { alert('Please select a term.'); return; }
        
        $('#pdfHiddenClassContainer').empty();
        $.each(classIds, function(i, cid) {
            $('#pdfHiddenClassContainer').append('<input type="hidden" name="class_ids[]" value="' + cid + '">');
        });
        $('#pdf_subject_id').val(subjectId);
        $('#pdf_term_id').val(termId);
        $('#pdfUploadForm').slideDown();
        $('#videoUploadForm').slideUp();
    });
    
    // Show video form
    $('#showVideoFormBtn').on('click', function() {
        var classIds = getSelectedClassIds();
        var subjectId = $('#subjectSelect').val();
        var termId = $('select[name="term_id"]').val();
        if (!classIds.length) { alert('Please select at least one class.'); return; }
        if (!subjectId) { alert('Please select a subject.'); return; }
        if (!termId) { alert('Please select a term.'); return; }
        
        $('#videoHiddenClassContainer').empty();
        $.each(classIds, function(i, cid) {
            $('#videoHiddenClassContainer').append('<input type="hidden" name="class_ids[]" value="' + cid + '">');
        });
        $('#video_subject_id').val(subjectId);
        $('#video_term_id').val(termId);
        $('#videoUploadForm').slideDown();
        $('#pdfUploadForm').slideUp();
    });
    
    $('#cancelPdfBtn').on('click', function() { 
        $('#pdfUploadForm').slideUp(); 
        $('#pdfForm')[0].reset(); 
        $('#pdfHiddenClassContainer').empty();
    });
    $('#cancelVideoBtn').on('click', function() { 
        $('#videoUploadForm').slideUp(); 
        $('#videoForm')[0].reset(); 
        $('#videoPreview').hide(); 
        $('#videoHiddenClassContainer').empty();
    });
    
    // CSV Quiz validation
    $('#csvQuizBtn').on('click', function(e) {
        var classIds = getSelectedClassIds();
        if (classIds.length !== 1) {
            e.preventDefault();
            alert('Please select exactly ONE class to create a CSV quiz.');
            return false;
        }
        if (!$('#subjectSelect').val()) {
            e.preventDefault();
            alert('Please select a subject.');
            return false;
        }
        if (!$('select[name="term_id"]').val()) {
            e.preventDefault();
            alert('Please select a term.');
            return false;
        }
        $('#selectionForm').submit();
    });
    
    // YouTube URL preview
    $('#youtube_url').on('input', function() {
        var url = $(this).val();
        var videoId = extractVideoId(url);
        if (videoId) {
            $('#videoPreview iframe').attr('src', 'https://www.youtube.com/embed/' + videoId);
            $('#videoPreview').slideDown();
        } else {
            $('#videoPreview').slideUp();
        }
    });
    
    function extractVideoId(url) {
        if (!url) return null;
        var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        var match = url.match(regExp);
        if (match && match[2].length === 11) return match[2];
        var shortMatch = url.match(/youtu\.be\/([^\/\?&]+)/);
        if (shortMatch) return shortMatch[1];
        return null;
    }
    
    // YouTube Browse modal
    $('#browseYoutubeBtn').on('click', function() {
        if (!youtubeApiKey) {
            alert('YouTube API key is not configured. Please paste the URL manually.');
            window.open('https://www.youtube.com', '_blank');
            return;
        }
        $('#youtubeSearchResults').html('<div class="col-12 text-center text-muted py-5"><i class="bi bi-youtube fs-1"></i><p class="mt-2">Enter a keyword and click Search.</p></div>');
        $('#youtubeSearchQuery').val('');
        $('#youtubeBrowserModal').modal('show');
    });
    
    $('#searchYoutubeBtn').on('click', function() {
        var query = $('#youtubeSearchQuery').val().trim();
        if (!query) {
            alert('Please enter a search keyword.');
            return;
        }
        $('#youtubeLoading').show();
        $('#youtubeSearchResults').html('<div class="col-12 text-center">Loading...</div>');
        $.ajax({
            url: 'https://www.googleapis.com/youtube/v3/search',
            method: 'GET',
            data: {
                part: 'snippet',
                q: query,
                maxResults: 15,
                type: 'video',
                key: youtubeApiKey
            },
            dataType: 'json',
            success: function(data) {
                $('#youtubeLoading').hide();
                if (data.items && data.items.length) {
                    var html = '';
                    $.each(data.items, function(i, item) {
                        var videoId = item.id.videoId;
                        var title = item.snippet.title;
                        var thumb = item.snippet.thumbnails.medium.url;
                        html += '<div class="col-md-6 col-lg-4">';
                        html += '<div class="card h-100 shadow-sm">';
                        html += '<img src="'+thumb+'" class="card-img-top" alt="Thumbnail">';
                        html += '<div class="card-body">';
                        html += '<h6 class="card-title">'+escapeHtml(title)+'</h6>';
                        html += '<button class="btn btn-sm btn-danger select-video mt-2" data-video-id="'+videoId+'" data-video-title="'+escapeHtml(title)+'"><i class="bi bi-youtube me-1"></i> Select</button>';
                        html += '</div></div></div>';
                    });
                    $('#youtubeSearchResults').html(html);
                } else {
                    $('#youtubeSearchResults').html('<div class="col-12 text-center text-warning py-5">No videos found. Try another keyword.</div>');
                }
            },
            error: function() {
                $('#youtubeLoading').hide();
                $('#youtubeSearchResults').html('<div class="col-12 text-center text-danger py-5">Search failed. Check API key or quota.</div>');
            }
        });
    });
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    $(document).on('click', '.select-video', function() {
        var videoId = $(this).data('video-id');
        var videoTitle = $(this).data('video-title');
        var videoUrl = 'https://www.youtube.com/watch?v=' + videoId;
        $('#youtube_url').val(videoUrl);
        $('#video_title').val(videoTitle);
        $('#videoPreview iframe').attr('src', 'https://www.youtube.com/embed/' + videoId);
        $('#videoPreview').slideDown();
        $('#youtubeBrowserModal').modal('hide');
    });
    
    <?php if ($pdf_error): ?> $('#pdfUploadForm').show(); <?php endif; ?>
    <?php if ($video_error): ?> $('#videoUploadForm').show(); <?php endif; ?>
});
</script>

<style>
.app-content {
    background: transparent !important;
}
.card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
}
.class-checkbox-list {
    background: #f8f9fa;
}
.class-checkbox-list .form-check {
    padding: 0.5rem 0.75rem;
    margin: 0;
    border-bottom: 1px solid #e9ecef;
}
.class-checkbox-list .form-check:last-child {
    border-bottom: none;
}
.class-checkbox-list .form-check:hover {
    background: #e9ecef;
}
.btn-outline-secondary:hover, .btn-outline-info:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
}
.modal-content {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #f8f9fa;
    border: none;
}
.modal-header .btn-close {
    filter: invert(1);
}
.card-img-top {
    object-fit: cover;
    height: 180px;
}
@media (max-width: 768px) {
    .btn-lg, .form-control-lg {
        font-size: 0.95rem;
    }
}
</style>
</body>
</html>