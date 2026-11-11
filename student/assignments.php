<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = $account_id;

// Helper to unserialize class list (identical to teacher pages)
function parse_class_list($raw) {
    if (is_array($raw)) return $raw;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) return array_map('intval', $un);
    $json = @json_decode($raw, true);
    if (is_array($json)) return array_map('intval', $json);
    $raw_trim = trim((string)$raw);
    if ($raw_trim === '') return [];
    if (ctype_digit($raw_trim)) return [(int)$raw_trim];
    $parts = preg_split('/\s*,\s*/', $raw_trim);
    $ints = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (ctype_digit($p)) $ints[] = (int)$p;
    }
    return $ints;
}

// Helper to extract YouTube video ID and get thumbnail
function getYouTubeId($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    if (isset($query['v'])) return $query['v'];
    if (preg_match('/youtu\.be\/([^\/\?&]+)/', $url, $match)) return $match[1];
    return null;
}

$error = '';
$materials = [];
$quizzes = [];
$videos = [];

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Get student's class
    $stmt = $pdo->prepare("SELECT class FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_class = $stmt->fetchColumn();
    if (!$student_class) {
        throw new Exception("Your account is not linked to any class. Please contact admin.");
    }

    // 2. Find all subject combinations that include this class (unserialize)
    $stmt = $pdo->query("SELECT id, class FROM tbl_subject_combinations WHERE status = 1");
    $all_combs = $stmt->fetchAll();
    $subject_ids = [];
    foreach ($all_combs as $comb) {
        $classes = parse_class_list($comb['class']);
        if (in_array($student_class, $classes)) {
            $subject_ids[] = (int)$comb['id'];
        }
    }
    $subject_ids = array_unique($subject_ids);

    // 3. Fetch teaching materials (PDFs) with teacher name
    if (!empty($subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   s.name as subject_name,
                   CONCAT(st.fname, ' ', st.lname) as teacher_name
            FROM tbl_teaching_materials m
            JOIN tbl_subject_combinations sc ON m.subject_combination_id = sc.id
            JOIN tbl_subjects s ON sc.subject = s.id
            JOIN tbl_staff st ON m.teacher_id = st.id
            WHERE m.class_id = ? AND m.subject_combination_id IN ($placeholders)
            ORDER BY m.uploaded_at DESC
        ");
        $params = array_merge([$student_class], $subject_ids);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();
    }

    // 4. Fetch available quizzes (published, within date range, not taken) with teacher name
    $now = date('Y-m-d H:i:s');
    if (!empty($subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT q.*, 
                   s.name as subject_name,
                   CONCAT(st.fname, ' ', st.lname) as teacher_name,
                (SELECT COUNT(*) FROM tbl_quiz_submissions WHERE quiz_id = q.id AND student_id = ?) as taken
            FROM tbl_quizzes q
            JOIN tbl_subject_combinations sc ON q.subject_combination_id = sc.id
            JOIN tbl_subjects s ON sc.subject = s.id
            JOIN tbl_staff st ON q.teacher_id = st.id
            WHERE q.class_id = ? 
              AND q.subject_combination_id IN ($placeholders)
              AND q.is_published = 1
              AND (q.start_date IS NULL OR q.start_date <= ?)
              AND (q.end_date IS NULL OR q.end_date >= ?)
            ORDER BY q.created_at DESC
        ");
        $params = array_merge([$student_id, $student_class], $subject_ids, [$now, $now]);
        $stmt->execute($params);
        $quizzes = $stmt->fetchAll();
    }

    // 5. Fetch video materials with teacher name and generate thumbnail URL
    if (!empty($subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   s.name as subject_name,
                   CONCAT(st.fname, ' ', st.lname) as teacher_name
            FROM tbl_video_materials v
            JOIN tbl_subject_combinations sc ON v.subject_combination_id = sc.id
            JOIN tbl_subjects s ON sc.subject = s.id
            JOIN tbl_staff st ON v.teacher_id = st.id
            WHERE v.class_id = ? AND v.subject_combination_id IN ($placeholders)
            ORDER BY v.created_at DESC
        ");
        $params = array_merge([$student_class], $subject_ids);
        $stmt->execute($params);
        $videos = $stmt->fetchAll();

        // Add thumbnail URL for each video
        foreach ($videos as &$video) {
            $video_id = getYouTubeId($video['youtube_url']);
            if ($video_id) {
                $video['video_id'] = $video_id;
                $video['thumbnail'] = 'https://img.youtube.com/vi/' . $video_id . '/mqdefault.jpg';
                $video['embed_url'] = 'https://www.youtube.com/embed/' . $video_id;
            } else {
                $video['video_id'] = null;
                $video['thumbnail'] = null;
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Build $student array for modular header
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-journal-bookmark-fill me-2"></i> Assignments & Quizzes</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Assignments</li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Tabs for PDF Materials, Quizzes, and Videos -->
    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
                📄 Teaching Materials (<?= count($materials) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes" type="button" role="tab">
                📝 Quizzes (<?= count($quizzes) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos" type="button" role="tab">
                🎬 Video Materials (<?= count($videos) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- PDF Materials Tab (unchanged) -->
        <div class="tab-pane fade show active" id="materials" role="tabpanel">
            <div class="tile">
                <?php if (count($materials) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Uploaded</th>
                                    <th>Instructions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($row['subject_name']) ?></span></td>
                                        <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                        <td><?= date('d M Y, H:i', strtotime($row['uploaded_at'])) ?></td>
                                        <td><?= nl2br(htmlspecialchars(substr($row['instructions'], 0, 100))) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary" title="View PDF">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="<?= htmlspecialchars($row['file_path']) ?>" download class="btn btn-sm btn-success" title="Download PDF">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No teaching materials have been uploaded for your class yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quizzes Tab (unchanged) -->
        <div class="tab-pane fade" id="quizzes" role="tabpanel">
            <div class="tile">
                <?php if (count($quizzes) > 0): ?>
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-6 mb-3">
                                <div class="quiz-card">
                                    <div class="quiz-header">
                                        <strong><?= htmlspecialchars($quiz['title']) ?></strong>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($quiz['subject_name']) ?></span>
                                    </div>
                                    <div class="quiz-body">
                                        <p><?= nl2br(htmlspecialchars($quiz['description'])) ?></p>
                                        <small class="text-muted">Teacher: <?= htmlspecialchars($quiz['teacher_name']) ?></small><br>
                                        <small class="text-muted">Points: <?= $quiz['total_points'] ?></small><br>
                                        <?php if ($quiz['time_limit_minutes']): ?>
                                            <small class="text-muted">⏱️ Time limit: <?= $quiz['time_limit_minutes'] ?> min</small><br>
                                        <?php endif; ?>
                                        <?php if ($quiz['start_date']): ?>
                                            <small class="text-muted">📅 Available from: <?= date('d M Y', strtotime($quiz['start_date'])) ?></small><br>
                                        <?php endif; ?>
                                        <?php if ($quiz['end_date']): ?>
                                            <small class="text-muted">⛔ Deadline: <?= date('d M Y', strtotime($quiz['end_date'])) ?></small><br>
                                        <?php endif; ?>
                                    </div>
                                    <div class="quiz-footer">
                                        <?php if ($quiz['taken'] > 0): ?>
                                            <span class="badge bg-success">✅ Completed</span>
                                        <?php else: ?>
                                            <a href="student/take_quiz?id=<?= $quiz['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-play-fill"></i> Take Quiz
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No quizzes are currently available for your class.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Video Materials Tab (Enhanced with thumbnails) -->
        <div class="tab-pane fade" id="videos" role="tabpanel">
            <div class="tile">
                <?php if (count($videos) > 0): ?>
                    <div class="row">
                        <?php foreach ($videos as $video): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="video-card">
                                    <?php if ($video['thumbnail']): ?>
                                        <div class="video-thumbnail-wrapper" data-video-url="<?= htmlspecialchars($video['youtube_url']) ?>" data-video-title="<?= htmlspecialchars($video['title']) ?>">
                                            <img src="<?= htmlspecialchars($video['thumbnail']) ?>" class="video-thumbnail" alt="Thumbnail">
                                            <div class="play-overlay">
                                                <i class="bi bi-play-circle-fill"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="video-thumbnail-wrapper no-thumbnail" data-video-url="<?= htmlspecialchars($video['youtube_url']) ?>" data-video-title="<?= htmlspecialchars($video['title']) ?>">
                                            <div class="no-thumbnail-content">
                                                <i class="bi bi-youtube"></i>
                                                <span>Preview not available</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="video-card-body">
                                        <h5 class="video-title"><?= htmlspecialchars($video['title']) ?></h5>
                                        <div class="video-meta">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($video['subject_name']) ?></span>
                                            <small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($video['teacher_name']) ?></small>
                                            <small class="text-muted"><i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($video['created_at'])) ?></small>
                                        </div>
                                        <?php if (!empty($video['instructions'])): ?>
                                            <div class="video-instructions">
                                                <i class="bi bi-info-circle"></i> <?= nl2br(htmlspecialchars(substr($video['instructions'], 0, 120))) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="video-footer">
                                            <button class="btn btn-primary btn-sm watch-video-btn" 
                                                    data-video-url="<?= htmlspecialchars($video['youtube_url']) ?>"
                                                    data-video-title="<?= htmlspecialchars($video['title']) ?>">
                                                <i class="bi bi-play-circle"></i> Watch Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No video materials have been uploaded for your class yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Watch Video Modal -->
<div class="modal fade" id="watchVideoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="watchVideoTitle">Watch Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9">
                    <iframe id="watchVideoIframe" src="" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/sweetalert2@11.js"></script>
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
    // Watch video from button click
    $('.watch-video-btn, .video-thumbnail-wrapper').on('click', function(e) {
        // Prevent if clicking on button inside thumbnail (event bubbling handled)
        if ($(e.target).closest('.watch-video-btn').length) {
            var btn = $(e.target).closest('.watch-video-btn');
            var url = btn.data('video-url');
            var title = btn.data('video-title');
        } else {
            var wrapper = $(this).closest('.video-thumbnail-wrapper');
            var url = wrapper.data('video-url');
            var title = wrapper.data('video-title');
        }
        
        if (!url) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Video',
                text: 'Video URL is missing. Please contact your teacher.'
            });
            return;
        }
        
        var videoId = extractVideoId(url);
        if (videoId) {
            $('#watchVideoIframe').attr('src', 'https://www.youtube.com/embed/' + videoId + '?autoplay=1');
            $('#watchVideoTitle').text(title || 'Watch Video');
            $('#watchVideoModal').modal('show');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Invalid YouTube URL',
                text: 'The video URL could not be recognized. Please contact your teacher.'
            });
        }
    });

    // Clear video iframe when modal closes
    $('#watchVideoModal').on('hidden.bs.modal', function() {
        $('#watchVideoIframe').attr('src', '');
    });
});
</script>

<style>
/* Glass card styling – matches teacher dashboard */
.app-content {
    background: transparent !important;
}
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}
.nav-tabs .nav-link {
    background: rgba(255,255,255,0.7);
    border-radius: 30px 30px 0 0;
    margin-right: 5px;
    transition: all 0.2s;
}
.nav-tabs .nav-link.active {
    background: white;
    font-weight: bold;
    border-bottom: 3px solid #3b7cff;
}
/* Video Card Styles */
.video-card {
    background: rgba(255,255,255,0.95);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
}
.video-thumbnail-wrapper {
    position: relative;
    cursor: pointer;
    background: #000;
    overflow: hidden;
    aspect-ratio: 16 / 9;
}
.video-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.video-thumbnail-wrapper:hover .video-thumbnail {
    transform: scale(1.05);
}
.play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}
.video-thumbnail-wrapper:hover .play-overlay {
    opacity: 1;
}
.play-overlay i {
    font-size: 4rem;
    color: white;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5));
}
.no-thumbnail {
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    aspect-ratio: 16 / 9;
    display: flex;
    align-items: center;
    justify-content: center;
}
.no-thumbnail-content {
    text-align: center;
    color: white;
}
.no-thumbnail-content i {
    font-size: 3rem;
    display: block;
    margin-bottom: 0.5rem;
}
.video-card-body {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.video-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
}
.video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.75rem;
}
.video-meta small {
    font-size: 0.75rem;
}
.video-instructions {
    background: rgba(0,0,0,0.03);
    padding: 0.5rem;
    border-radius: 12px;
    font-size: 0.85rem;
    color: #475569;
    margin: 0.5rem 0;
    flex: 1;
}
.video-footer {
    margin-top: 0.8rem;
    text-align: right;
}
.quiz-card {
    background: rgba(255,255,255,0.9);
    border-radius: 16px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.quiz-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
}
.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
}
.quiz-body {
    flex: 1;
    margin: 0.5rem 0;
}
.quiz-footer {
    margin-top: 0.8rem;
    text-align: right;
}
.btn-primary {
    background: #3b7cff;
    border: none;
}
.btn-primary:hover {
    background: #2a62d0;
}
.btn-success {
    background: #28a745;
    border: none;
}
.btn-success:hover {
    background: #1e7e34;
}
.table th {
    background: #f8f9fa;
}
.badge {
    font-size: 0.8rem;
}
/* Dark mode compatibility */
body.dark .tile {
    background: rgba(30, 41, 59, 0.92) !important;
    color: #e2e8f0;
}
body.dark .nav-tabs .nav-link {
    background: rgba(30, 41, 59, 0.8);
    color: #cbd5e1;
}
body.dark .nav-tabs .nav-link.active {
    background: #1e293b;
    color: white;
    border-bottom-color: #60a5fa;
}
body.dark .video-card {
    background: rgba(30, 41, 59, 0.95);
}
body.dark .video-title {
    color: #f1f5f9;
}
body.dark .video-instructions {
    background: rgba(255,255,255,0.05);
    color: #cbd5e1;
}
body.dark .quiz-card {
    background: rgba(30, 41, 59, 0.9);
    color: #e2e8f0;
}
body.dark .table th {
    background: #1e293b;
    color: #e2e8f0;
}
body.dark .table td {
    color: #cbd5e1;
}
body.dark .alert-info {
    background: rgba(13, 110, 130, 0.85);
    color: #e2e8f0;
}
</style>
</body>
</html>