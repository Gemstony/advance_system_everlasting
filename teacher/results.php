<?php
// teacher/edit_results.php
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

// -------------------------------------------------------------------
// AJAX HANDLERS
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_scores') {
    header('Content-Type: application/json');
    try {
        $student = $_GET['student'] ?? '';
        $term    = (int)($_GET['term'] ?? 0);
        if (!$student || !$term) throw new Exception('Missing parameters');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get student's class
        $stmt = $pdo->prepare("SELECT class FROM tbl_students WHERE id = ?");
        $stmt->execute([$student]);
        $class = $stmt->fetchColumn();
        if (!$class) throw new Exception('Student not found');

        // Get all subject combinations where this teacher is assigned AND the class is in the serialized class list
        $stmt = $pdo->prepare("SELECT id, subject, class FROM tbl_subject_combinations WHERE teacher = ?");
        $stmt->execute([$account_id]);
        $allCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $teacherSubjects = [];
        foreach ($allCombos as $combo) {
            $classList = unserialize($combo['class']);
            if (is_array($classList) && in_array($class, $classList)) {
                // Get subject name
                $stmt2 = $pdo->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
                $stmt2->execute([$combo['subject']]);
                $subjectName = $stmt2->fetchColumn();
                if ($subjectName) {
                    // Get existing score for this student, term, class, and subject combination
                    $stmt3 = $pdo->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND term = ? AND class = ? AND subject_combination = ?");
                    $stmt3->execute([$student, $term, $class, $combo['id']]);
                    $score = $stmt3->fetchColumn();
                    $teacherSubjects[] = [
                        'id'    => $combo['id'],
                        'name'  => $subjectName,
                        'score' => $score !== false ? (float)$score : 0
                    ];
                }
            }
        }

        echo json_encode(['success' => true, 'subjects' => $teacherSubjects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update_scores') {
    // Only allowed if term is editable
    header('Content-Type: application/json');
    try {
        $student = $_POST['student'] ?? '';
        $term    = (int)($_POST['term'] ?? 0);
        $scores  = $_POST['scores'] ?? []; // array [subject_comb_id => score]

        if (!$student || !$term || empty($scores)) throw new Exception('Invalid data');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if term is editable
        $stmt = $pdo->prepare("SELECT results_editable FROM tbl_terms WHERE id = ?");
        $stmt->execute([$term]);
        if (!$stmt->fetchColumn()) {
            throw new Exception("Term is closed. Results cannot be edited directly. Please request a change.");
        }

        // Get student's class
        $stmt = $pdo->prepare("SELECT class FROM tbl_students WHERE id = ?");
        $stmt->execute([$student]);
        $class = $stmt->fetchColumn();
        if (!$class) throw new Exception('Student not found');

        // Prepare statements
        $checkStmt = $pdo->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
        $updateStmt = $pdo->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
        $insertStmt = $pdo->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?, ?, ?, ?, ?)");

        // Verify each subject combination belongs to this teacher and the student's class
        foreach ($scores as $combId => $score) {
            $combId = (int)$combId;
            $score = (float)$score;
            if ($score < 0 || $score > 100) {
                throw new Exception("Score must be between 0 and 100 for subject ID $combId");
            }
            // Check teacher assignment
            $stmt = $pdo->prepare("SELECT teacher, class FROM tbl_subject_combinations WHERE id = ?");
            $stmt->execute([$combId]);
            $combo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$combo) throw new Exception("Subject combination ID $combId not found");
            if ((int)$combo['teacher'] !== (int)$account_id) {
                throw new Exception("You are not allowed to modify subject combination ID $combId");
            }
            // Check class belongs to this combination
            $classList = unserialize($combo['class']);
            if (!is_array($classList) || !in_array($class, $classList)) {
                throw new Exception("Subject combination ID $combId does not belong to this class");
            }

            // Proceed with update/insert
            $checkStmt->execute([$student, $class, $combId, $term]);
            $existingId = $checkStmt->fetchColumn();
            if ($existingId) {
                $updateStmt->execute([$score, $existingId]);
            } else {
                $insertStmt->execute([$student, $class, $combId, $term, $score]);
            }
        }

        // Return the score for the currently viewed subject combination (the one from session)
        $currentSubject = (int) $_SESSION['result__data']['subject'];
        $stmtScore = $pdo->prepare("SELECT score FROM tbl_exam_results
                                     WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?");
        $stmtScore->execute([$class, $currentSubject, $term, $student]);
        $newScore = (float) $stmtScore->fetchColumn();

        // Get grading for this score
        $gradingStmt = $pdo->query("SELECT name, remark, min, max FROM tbl_grade_system ORDER BY min DESC");
        $grading = $gradingStmt->fetchAll(PDO::FETCH_ASSOC);
        $grade = 'N/A'; $remark = 'N/A';
        foreach ($grading as $g) {
            if ($newScore >= $g['min'] && $newScore <= $g['max']) {
                $grade = $g['name'];
                $remark = $g['remark'];
                break;
            }
        }

        echo json_encode([
            'success'   => true,
            'new_score' => $newScore,
            'new_grade' => $grade,
            'new_remark'=> $remark
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_results') {
    header('Content-Type: application/json');
    try {
        $student = $_POST['student'] ?? '';
        $term    = (int)($_POST['term'] ?? 0);
        if (!$student || !$term) throw new Exception('Missing parameters');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if term is editable
        $stmt = $pdo->prepare("SELECT results_editable FROM tbl_terms WHERE id = ?");
        $stmt->execute([$term]);
        if (!$stmt->fetchColumn()) {
            throw new Exception("Term is closed. Results cannot be deleted.");
        }

        // Get student's class and the current subject combination
        $stmt = $pdo->prepare("SELECT class FROM tbl_students WHERE id = ?");
        $stmt->execute([$student]);
        $class = $stmt->fetchColumn();
        if (!$class) throw new Exception('Student not found');

        $currentSubject = (int) $_SESSION['result__data']['subject'];

        // Verify teacher is allowed to delete this subject's results
        $stmt = $pdo->prepare("SELECT teacher FROM tbl_subject_combinations WHERE id = ?");
        $stmt->execute([$currentSubject]);
        $teacher = $stmt->fetchColumn();
        if ((int)$teacher !== (int)$account_id) {
            throw new Exception("You are not allowed to delete results for this subject.");
        }

        // Delete only the record for this subject combination, term, and student
        $stmt = $pdo->prepare("DELETE FROM tbl_exam_results WHERE student = ? AND term = ? AND subject_combination = ?");
        $stmt->execute([$student, $term, $currentSubject]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// NORMAL PAGE DISPLAY
// -------------------------------------------------------------------
if (!isset($_SESSION['result__data'])) {
    header("location:./");
    exit;
}

$term = (int) $_SESSION['result__data']['term'];
$class = (int) $_SESSION['result__data']['class'];
$subject_combination = (int) $_SESSION['result__data']['subject'];

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Check if term is editable
    $stmt = $pdo->prepare("SELECT results_editable FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term]);
    $results_editable = (bool)$stmt->fetchColumn();

    // Verify that the logged-in teacher is assigned to this subject combination
    $stmt = $pdo->prepare("SELECT teacher FROM tbl_subject_combinations WHERE id = ?");
    $stmt->execute([$subject_combination]);
    $teacher = $stmt->fetchColumn();
    if ((int)$teacher !== (int)$account_id) {
        // Not assigned, redirect to main results page
        header("location:manage_results");
        exit;
    }

    // Term, class, subject combination names
    $stmt = $pdo->prepare("SELECT id, name FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term]);
    $term_data = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$class]);
    $class_data = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT sc.id, s.name AS subject_name
                           FROM tbl_subject_combinations sc
                           LEFT JOIN tbl_subjects s ON sc.subject = s.id
                           WHERE sc.id = ?");
    $stmt->execute([$subject_combination]);
    $sc_data = $stmt->fetch();

    if (!$term_data || !$class_data || !$sc_data) {
        throw new Exception('Missing term/class/subject combination data.');
    }

    $tit = $sc_data['subject_name'].' - '.$term_data['name'].' - '.$class_data['name'].' Examination Results';

    // Grading system
    $stmt = $pdo->query("SELECT id, name, min, max, remark FROM tbl_grade_system ORDER BY min DESC");
    $grading = $stmt->fetchAll();

    // Exam results with student details (including photo and gender)
    $stmt = $pdo->prepare("SELECT er.id AS result_id, er.student AS student_reg, er.score,
                                  s.fname, s.mname, s.lname, s.display_image, s.gender
                           FROM tbl_exam_results er
                           LEFT JOIN tbl_students s ON er.student = s.id
                           WHERE er.class = ? AND er.subject_combination = ? AND er.term = ?
                           ORDER BY s.fname ASC, s.lname ASC");
    $stmt->execute([$class, $subject_combination, $term]);
    $results = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

function grade_for($score, $grading) {
    foreach ($grading as $g) {
        if ($score >= $g['min'] && $score <= $g['max']) {
            return [$g['name'], $g['remark']];
        }
    }
    return ['N/A', 'N/A'];
}

function student_photo($display_image, $gender) {
    if ($display_image !== 'DEFAULT' && !empty($display_image)) {
        $path = 'images/students/' . $display_image;
        if (file_exists(__DIR__ . '/../' . $path)) {
            return $path;
        }
    }
    $default = 'images/students/' . $gender . '.png';
    if (file_exists(__DIR__ . '/../' . $default)) {
        return $default;
    }
    return 'images/students/default.png';
}

// Compute statistics
$scores = array_column($results, 'score');
$total_students = count($scores);
$avg = $total_students > 0 ? round(array_sum($scores) / $total_students, 2) : 0;
$passing = 0;
foreach ($scores as $s) if ($s >= 30) $passing++;
$pass_rate = $total_students > 0 ? round(($passing / $total_students) * 100, 1) : 0;
$highest = $total_students > 0 ? max($scores) : 0;
$lowest = $total_students > 0 ? min($scores) : 0;

// Top 3 and bottom 3
$students_by_score = $results;
usort($students_by_score, fn($a, $b) => $b['score'] <=> $a['score']);
$top3 = array_slice($students_by_score, 0, 3);
$bottom3 = array_slice($students_by_score, -3);

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; // Role‑aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
<div class="app-title">
    <div>
        <h1><i class="bi bi-table me-2"></i> Examination Results</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item active">Results</li>
    </ul>
</div>

<?php if ($successMsg): ?><div class="flash flash-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="flash flash-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

<!-- Dashboard Stats -->
<div class="dashboard-stats">
    <div class="stat-card"><div class="stat-icon bi bi-people"></div><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
    <div class="stat-card"><div class="stat-icon bi bi-bar-chart"></div><div class="stat-value"><?= $avg ?>%</div><div class="stat-label">Average</div></div>
    <div class="stat-card"><div class="stat-icon bi bi-check-circle"></div><div class="stat-value"><?= $pass_rate ?>%</div><div class="stat-label">Pass Rate</div></div>
    <div class="stat-card"><div class="stat-icon bi bi-arrow-up-circle"></div><div class="stat-value"><?= $highest ?>%</div><div class="stat-label">Highest</div></div>
    <div class="stat-card"><div class="stat-icon bi bi-arrow-down-circle"></div><div class="stat-value"><?= $lowest ?>%</div><div class="stat-label">Lowest</div></div>
</div>

<div class="top-bottom-container">
    <?php if (!empty($top3)): ?>
    <div class="leaderboard">
        <div class="leaderboard-title"><i class="bi bi-trophy-fill text-warning"></i> Top Performers</div>
        <?php foreach ($top3 as $idx => $student): 
            $rankClass = $idx == 0 ? 'rank-1' : ($idx == 1 ? 'rank-2' : 'rank-3');
            $fullname = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);
        ?>
        <div class="leaderboard-item">
            <div class="student-info"><div class="rank <?= $rankClass ?>"><?= $idx+1 ?></div><span class="student-name"><?= htmlspecialchars($fullname) ?></span></div>
            <span class="student-score"><?= $student['score'] ?>%</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($bottom3)): ?>
    <div class="leaderboard">
        <div class="leaderboard-title"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Needs Improvement</div>
        <?php foreach (array_reverse($bottom3) as $student): 
            $fullname = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);
        ?>
        <div class="leaderboard-item">
            <div class="student-info"><i class="bi bi-arrow-down-short text-danger me-2 fs-5"></i><span class="student-name"><?= htmlspecialchars($fullname) ?></span></div>
            <span class="student-score"><?= $student['score'] ?>%</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title"><?php echo htmlspecialchars($tit); ?></h3>

<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
    <th>Photo</th><th>Reg. No.</th><th>Student Name</th><th>Score</th><th>Grade</th><th>Remark</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($results as $row):
    $reg = htmlspecialchars($row['student_reg']);
    $full = htmlspecialchars(trim($row['fname'].' '.$row['mname'].' '.$row['lname']));
    $score = (float)($row['score'] ?? 0);
    list($gname, $gremark) = grade_for($score, $grading);
    $photoPath = student_photo($row['display_image'], $row['gender']);
?>
<tr data-student="<?php echo $reg; ?>" data-term="<?php echo $term; ?>">
    <td class="text-center"><img src="<?php echo $photoPath; ?>" class="student-photo" alt="Photo"></td>
    <td><?php echo $reg; ?></td>
    <td><?php echo $full; ?></td>
    <td class="score-cell"><?php echo number_format($score,2); ?>%</td>
    <td class="grade-cell"><?php echo $gname; ?></td>
    <td class="remark-cell"><?php echo $gremark; ?></td>
    <td>
        <?php if ($results_editable): ?>
            <button class="btn btn-sm btn-primary edit-btn" data-student="<?php echo $reg; ?>" data-term="<?php echo $term; ?>">Edit</button>
            <button class="btn btn-sm btn-danger delete-btn" data-student="<?php echo $reg; ?>" data-term="<?php echo $term; ?>">Delete</button>
        <?php else: ?>
            <button class="btn btn-sm btn-warning request-change-btn" data-student="<?php echo $reg; ?>" data-term="<?php echo $term; ?>" data-current-score="<?php echo $score; ?>" data-student-name="<?php echo htmlspecialchars($full); ?>">Request Change</button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div></div></div></div></div>
</main>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Scores</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <div id="subjects-container"></div>
          <input type="hidden" name="student" id="edit-student">
          <input type="hidden" name="term" id="edit-term">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveScoresBtn">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Request Change Modal -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestModalLabel">Request Score Change</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="requestForm">
          <div class="mb-3">
            <label class="form-label">Student</label>
            <input type="text" class="form-control" id="reqStudentName" readonly>
            <input type="hidden" id="reqStudentId" name="student_id">
            <input type="hidden" id="reqTermId" name="term_id">
          </div>
          <div class="mb-3">
            <label class="form-label">Current Score</label>
            <input type="text" class="form-control" id="reqCurrentScore" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">New Score (%) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="reqNewScore" name="new_score" min="0" max="100" step="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason for Change <span class="text-danger">*</span></label>
            <textarea class="form-control" id="reqReason" name="reason" rows="3" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitRequestBtn">Submit Request</button>
      </div>
    </div>
  </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function(){
    var table = $('#srmsTable').DataTable({
        paging: true, pageLength: 25, lengthMenu: [10,25,50,100],
        ordering: false, info: true,
        columnDefs: [{ orderable: false, targets: [0,6] }]
    });

    // --- Edit button (only if term is editable) ---
    $('.edit-btn').on('click', function(){
        var student = $(this).data('student');
        var term = $(this).data('term');
        $('#edit-student').val(student);
        $('#edit-term').val(term);
        $('#subjects-container').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
        $('#editModal').modal('show');
        $.ajax({
            url: '', method: 'GET', data: { ajax: 'get_scores', student: student, term: term },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    if (res.subjects.length === 0) {
                        $('#subjects-container').html('<div class="alert alert-warning">You are not assigned to any subjects for this student\'s class.</div>');
                    } else {
                        var html = '';
                        $.each(res.subjects, function(i, subj){
                            html += '<div class="subject-input row mb-2">';
                            html += '<div class="col-md-6"><label class="form-label">' + subj.name + '</label></div>';
                            html += '<div class="col-md-6"><input type="number" class="form-control" name="scores[' + subj.id + ']" value="' + subj.score + '" min="0" max="100" step="0.01" required></div>';
                            html += '</div>';
                        });
                        $('#subjects-container').html(html);
                    }
                } else {
                    $('#subjects-container').html('<div class="alert alert-danger">Error loading scores: ' + res.error + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#subjects-container').html('<div class="alert alert-danger">AJAX error: ' + error + '<br><pre>' + xhr.responseText + '</pre></div>');
            }
        });
    });

    // Save scores (only if editable)
    $('#saveScoresBtn').on('click', function(){
        var formData = $('#editForm').serialize() + '&action=update_scores';
        $.ajax({
            url: '', method: 'POST', data: formData, dataType: 'json',
            beforeSend: function() { $('#saveScoresBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Saving...'); },
            success: function(res) {
                if (res.success) {
                    var student = $('#edit-student').val();
                    var term = $('#edit-term').val();
                    var $row = $('tr[data-student="' + student + '"][data-term="' + term + '"]');
                    $row.find('.score-cell').text(res.new_score + '%');
                    $row.find('.grade-cell').text(res.new_grade);
                    $row.find('.remark-cell').text(res.new_remark);
                    $('#editModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Updated!', text: 'Scores saved successfully.', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            },
            error: function(xhr, status, error) { Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error'); },
            complete: function() { $('#saveScoresBtn').prop('disabled', false).html('Save Changes'); }
        });
    });

    // Delete functionality (only if editable)
    $('.delete-btn').on('click', function(){
        var student = $(this).data('student');
        var term = $(this).data('term');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete the result for this student in the current subject.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '', method: 'POST', data: { action: 'delete_results', student: student, term: term }, dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            var $row = $('tr[data-student="' + student + '"][data-term="' + term + '"]');
                            table.row($row).remove().draw();
                            Swal.fire('Deleted!', 'Result has been deleted.', 'success');
                        } else {
                            Swal.fire('Error', res.error, 'error');
                        }
                    },
                    error: function(xhr, status, error) { Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error'); }
                });
            }
        });
    });

    // --- Request Change button (when term closed) ---
    $('.request-change-btn').on('click', function(){
        var student = $(this).data('student');
        var term = $(this).data('term');
        var currentScore = $(this).data('current-score');
        var studentName = $(this).data('student-name');
        $('#reqStudentId').val(student);
        $('#reqTermId').val(term);
        $('#reqStudentName').val(studentName);
        $('#reqCurrentScore').val(currentScore + '%');
        $('#reqNewScore').val('');
        $('#reqReason').val('');
        $('#requestModal').modal('show');
    });

    // Submit request
    $('#submitRequestBtn').on('click', function(){
        var formData = $('#requestForm').serialize() + '&action=submit_change_request';
        $.ajax({
            url: 'teacher/request_change.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() { $('#submitRequestBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...'); },
            success: function(res) {
                if (res.success) {
                    $('#requestModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Request Sent', text: 'Your change request has been submitted to the academic office.', timer: 3000, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            },
            error: function(xhr, status, error) { Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error'); },
            complete: function() { $('#submitRequestBtn').prop('disabled', false).html('Submit Request'); }
        });
    });
});
</script>

<style>
/* Result editing page specific styles */
.app-content {
    background: transparent !important;
}
.flash {
    padding: 8px 12px;
    margin-bottom: 12px;
    border-radius: 4px;
}
.flash-success { background: #e6ffed; border: 1px solid #b6f0c4; color: #1b6f2c; }
.flash-error { background: #ffecec; border: 1px solid #f5bcbc; color: #8a1b1b; }
.modal-body { max-height: 70vh; overflow-y: auto; }
.subject-input { margin-bottom: 10px; }
.student-photo {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
}
.dashboard-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: rgba(255,255,255,0.95);
    border-radius: 1rem;
    padding: 1.2rem 1rem;
    flex: 1 1 160px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    border: 1px solid #eef2f6;
    text-align: center;
    transition: all 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.04); }
.stat-icon { font-size: 2rem; color: #0d6efd; margin-bottom: 0.3rem; }
.stat-value { font-size: 1.8rem; font-weight: 600; line-height: 1.2; color: #1e293b; }
.stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.3px; color: #64748b; }
.top-bottom-container { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
.leaderboard {
    background: rgba(255,255,255,0.95);
    border-radius: 1rem;
    padding: 1rem 1.2rem;
    flex: 1 1 280px;
    border: 1px solid #eef2f6;
}
.leaderboard-title { font-weight: 600; margin-bottom: 1rem; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.leaderboard-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
.leaderboard-item:last-child { border-bottom: none; }
.rank { width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600; margin-right: 0.8rem; }
.rank-1 { background: gold; color: #1e293b; }
.rank-2 { background: silver; color: #1e293b; }
.rank-3 { background: #cd7f32; color: white; }
.student-info { flex: 1; display: flex; align-items: center; }
.student-name { font-weight: 500; color: #1e293b; }
.student-score { font-weight: 600; color: #0d6efd; background: #eef2ff; padding: 0.2rem 0.8rem; border-radius: 2rem; font-size: 0.85rem; }
.tile, .stat-card, .leaderboard, .flash {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.5rem 1rem;
}
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
</style>
</body>
</html>