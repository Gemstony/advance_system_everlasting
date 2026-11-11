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

// -------------------------------------------------------------------
// AJAX handler for fetching subjects (used when class changes)
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_subjects') {
    header('Content-Type: application/json');
    try {
        $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
        if (!$class_id) throw new Exception('Invalid class');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT id, class, subject FROM tbl_subject_combinations WHERE teacher = ?");
        $stmt->execute([$teacher_id]);
        $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subjects = [];
        foreach ($combinations as $comb) {
            $classes = unserialize($comb['class']);
            if (is_array($classes) && in_array($class_id, $classes)) {
                $stmt2 = $pdo->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
                $stmt2->execute([$comb['subject']]);
                $name = $stmt2->fetchColumn();
                if ($name) {
                    $subjects[] = ['id' => $comb['id'], 'name' => $name];
                }
            }
        }
        echo json_encode(['success' => true, 'subjects' => $subjects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// Get teacher's classes and terms (same as manage_results.php)
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

// -------------------------------------------------------------------
// Get pre‑selected values from URL (from create_material.php)
// -------------------------------------------------------------------
$preset_class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$preset_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$preset_term = isset($_GET['term']) ? (int)$_GET['term'] : 0;

// -------------------------------------------------------------------
// Process form submissions
// -------------------------------------------------------------------
$error = '';
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Step 1: Save quiz details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $class_id = (int)$_POST['class_id'];
    $subject_comb_id = (int)$_POST['subject_comb_id'];
    $term_id = (int)$_POST['term_id'];
    $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] . ' 00:00:00' : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] . ' 23:59:59' : null;

    if (empty($title) || !$class_id || !$subject_comb_id || !$term_id) {
        $error = "Please fill all required fields.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_quizzes (title, description, instructions, class_id, subject_combination_id, teacher_id, term_id, time_limit_minutes, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $description, $instructions, $class_id, $subject_comb_id, $teacher_id, $term_id, $time_limit, $start_date, $end_date]);
            $quiz_id = $conn->lastInsertId();
            $_SESSION['reply'] = [['success', 'Quiz details saved. Now upload your CSV questions.']];
            header("Location: create_quiz?quiz_id=" . $quiz_id);
            exit;
        } catch (PDOException $e) {
            $error = "Failed to save quiz: " . $e->getMessage();
        }
    }
}

// Process CSV upload (preview)
$preview_data = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preview_csv']) && $quiz_id) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
        $error = "Please select a CSV file.";
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext != 'csv') {
            $error = "Only CSV files are allowed.";
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle);
            if (!$header) {
                $error = "Invalid CSV format.";
            } else {
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) < 5) continue;
                    $preview_data[] = [
                        'type' => trim($data[0]),
                        'question' => trim($data[1]),
                        'options' => trim($data[2]),
                        'correct' => trim($data[3]),
                        'points' => (int)trim($data[4]),
                        'explanation' => isset($data[5]) ? trim($data[5]) : ''
                    ];
                }
                fclose($handle);
                if (empty($preview_data)) {
                    $error = "No valid questions found.";
                } else {
                    $_SESSION['preview_data'] = $preview_data;
                }
            }
        }
    }
}

// Final CSV import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_quiz']) && $quiz_id && isset($_SESSION['preview_data'])) {
    $questions = $_SESSION['preview_data'];
    $question_count = 0;
    $total_points = 0;
    foreach ($questions as $q) {
        $type = $q['type'];
        $q_text = $q['question'];
        $options_raw = $q['options'];
        $correct_answer = $q['correct'];
        $points = $q['points'];
        $explanation = $q['explanation'];
        if (empty($q_text)) continue;
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_quiz_questions (quiz_id, question_text, question_type, points, order_index, explanation) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quiz_id, $q_text, $type, $points, $question_count, $explanation]);
            $question_id = $conn->lastInsertId();
            $total_points += $points;

            if ($type == 'multiple_choice') {
                $options = explode(',', $options_raw);
                $correct_index = (int)$correct_answer - 1;
                foreach ($options as $idx => $opt) {
                    $is_correct = ($idx == $correct_index) ? 1 : 0;
                    $stmt2 = $conn->prepare("INSERT INTO tbl_quiz_options (question_id, option_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$question_id, trim($opt), $is_correct, $idx]);
                }
            } elseif ($type == 'true_false') {
                $is_correct = (strtolower($correct_answer) == 'true') ? 1 : 0;
                $stmt2 = $conn->prepare("INSERT INTO tbl_quiz_options (question_id, option_text, is_correct, order_index) VALUES (?, 'True', ?, 0), (?, 'False', ?, 1)");
                $stmt2->execute([$question_id, $is_correct, $question_id, $is_correct ? 0 : 1]);
            } elseif ($type == 'short_answer') {
                $stmt2 = $conn->prepare("INSERT INTO tbl_quiz_options (question_id, option_text, is_correct) VALUES (?, ?, 1)");
                $stmt2->execute([$question_id, $correct_answer]);
            }
            $question_count++;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            break;
        }
    }
    if ($question_count == 0) {
        $error = "No questions imported.";
    } else {
        $conn->prepare("UPDATE tbl_quizzes SET total_points = ? WHERE id = ?")->execute([$total_points, $quiz_id]);
        unset($_SESSION['preview_data']);
        $_SESSION['reply'] = [['success', "Quiz created! $question_count questions. Total points: $total_points."]];
        header("location:teacher/quizzes");
        exit;
    }
}

// Fetch quiz title for step 2
$quiz_title = '';
if ($quiz_id) {
    $stmt = $conn->prepare("SELECT title FROM tbl_quizzes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$quiz_id, $teacher_id]);
    $quiz = $stmt->fetch();
    if ($quiz) {
        $quiz_title = $quiz['title'];
    } else {
        $quiz_id = 0;
    }
}

include 'inc/header.php';
include 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-plus-circle me-2"></i> Create CSV Quiz</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="create_material">Create Material</a></li>
            <li class="breadcrumb-item active">CSV Quiz</li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Step Indicator -->
    <div class="step-indicator mb-4">
        <div class="step <?= $quiz_id == 0 ? 'active' : '' ?>">
            <div class="circle">1</div>
            <div class="label">Quiz Details</div>
        </div>
        <div class="step <?= $quiz_id > 0 ? 'active' : '' ?>">
            <div class="circle">2</div>
            <div class="label">Upload CSV</div>
        </div>
    </div>

    <?php if (!$quiz_id): ?>
        <!-- Step 1: Quiz details form (pre‑filled from URL) -->
        <div class="tile">
            <h3 class="tile-title">Step 1: Quiz Details</h3>
            <form method="post" id="quizDetailsForm">
                <div class="mb-3">
                    <label class="form-label">Quiz Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (optional)</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Instructions for Students</label>
                    <textarea name="instructions" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Class *</label>
                        <select name="class_id" id="classSelect" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes_list as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($preset_class == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Subject *</label>
                        <select name="subject_comb_id" id="subjectSelect" class="form-select" required <?= $preset_class ? '' : 'disabled' ?>>
                            <option value="">Select class first</option>
                            <?php if ($preset_class && $preset_subject): ?>
                                <?php
                                // Fetch subject name for the preset subject (optional)
                                $subj_stmt = $conn->prepare("SELECT s.name FROM tbl_subject_combinations sc JOIN tbl_subjects s ON sc.subject = s.id WHERE sc.id = ? AND sc.teacher = ?");
                                $subj_stmt->execute([$preset_subject, $teacher_id]);
                                $subj_name = $subj_stmt->fetchColumn();
                                if ($subj_name) echo '<option value="'.$preset_subject.'" selected>'.htmlspecialchars($subj_name).'</option>';
                                ?>
                            <?php endif; ?>
                        </select>
                        <div id="subjectLoading" class="small text-muted" style="display:none;">Loading...</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Term *</label>
                        <select name="term_id" class="form-select" required>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($preset_term == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Time Limit (minutes)</label>
                        <input type="number" name="time_limit" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
                <button type="submit" name="save_quiz" class="btn btn-primary">Save & Continue →</button>
                <a href="create_material" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php elseif (!isset($_SESSION['preview_data'])): ?>
        <!-- Step 2: Upload CSV -->
        <div class="tile">
            <h3 class="tile-title">Step 2: Upload CSV for "<?= htmlspecialchars($quiz_title) ?>"</h3>
            <div class="alert alert-info">
                <strong>CSV Format:</strong>
                <code>type, question_text, options, correct_answer, points, explanation</code><br>
                - <strong>type</strong>: multiple_choice, true_false, short_answer<br>
                - <strong>options</strong>: comma‑separated for MC (e.g., "Paris,London,Berlin")<br>
                - <strong>correct_answer</strong>: 1‑based index for MC, "True"/"False" for TF, exact text for short answer<br>
                - <strong>points</strong>: integer<br>
                - <strong>explanation</strong>: optional feedback<br>
                <a href="#" id="downloadTemplate" class="btn btn-sm btn-outline-primary mt-2">Download CSV Template</a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" name="preview_csv" class="btn btn-primary">Preview Questions</button>
                <a href="teacher/quizzes" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php else: ?>
        <!-- Step 3: Preview and Confirm -->
        <div class="tile">
            <h3 class="tile-title">Step 3: Preview Questions – <?= htmlspecialchars($quiz_title) ?></h3>
            <div class="alert alert-warning">
                <strong>Review the questions below.</strong> Once confirmed, the quiz will be created and visible to students.
            </div>
            <?php
            $questions = $_SESSION['preview_data'];
            $total_points = array_sum(array_column($questions, 'points'));
            ?>
            <div class="mb-3"><strong>Total Questions:</strong> <?= count($questions) ?> | <strong>Total Points:</strong> <?= $total_points ?></div>
            <?php foreach ($questions as $idx => $q): ?>
                <div class="card mb-3">
                    <div class="card-header">Question <?= $idx+1 ?> (<?= $q['points'] ?> pts) – <?= ucfirst($q['type']) ?></div>
                    <div class="card-body">
                        <p><strong><?= htmlspecialchars($q['question']) ?></strong></p>
                        <?php if ($q['type'] == 'multiple_choice'): 
                            $opts = explode(',', $q['options']);
                            ?>
                            <ul>
                            <?php foreach ($opts as $opt): ?>
                                <li><?= htmlspecialchars($opt) ?></li>
                            <?php endforeach; ?>
                            </ul>
                            <p><strong>Correct answer:</strong> Option <?= $q['correct'] ?></p>
                        <?php elseif ($q['type'] == 'true_false'): ?>
                            <p><strong>Correct answer:</strong> <?= $q['correct'] ?></p>
                        <?php elseif ($q['type'] == 'short_answer'): ?>
                            <p><strong>Correct answer:</strong> <?= htmlspecialchars($q['correct']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($q['explanation'])): ?>
                            <p class="text-muted"><strong>Explanation:</strong> <?= htmlspecialchars($q['explanation']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <form method="post">
                <button type="submit" name="confirm_quiz" class="btn btn-success">Confirm & Create Quiz</button>
                <a href="create_quiz?quiz_id=<?= $quiz_id ?>" class="btn btn-secondary">Upload Different CSV</a>
            </form>
        </div>
    <?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    // Load subjects when class changes (only on step 1)
    $('#classSelect').on('change', function() {
        var classId = $(this).val();
        if (classId) {
            $('#subjectSelect').prop('disabled', true).html('<option>Loading...</option>');
            $('#subjectLoading').show();
            $.ajax({
                url: 'teacher/create_quiz.php',
                method: 'GET',
                data: { ajax: 'get_subjects', class_id: classId },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.subjects.length) {
                        var opts = '<option value="">Select Subject</option>';
                        $.each(res.subjects, function(i, s) {
                            opts += '<option value="'+s.id+'">'+s.name+'</option>';
                        });
                        $('#subjectSelect').html(opts).prop('disabled', false);
                    } else {
                        $('#subjectSelect').html('<option value="">No subjects for this class</option>').prop('disabled', true);
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
            $('#subjectSelect').prop('disabled', true).html('<option value="">Select class first</option>');
        }
    });

    // If a class is already pre‑selected, trigger change to load subjects
    if ($('#classSelect').val() && $('#subjectSelect').is(':disabled')) {
        $('#classSelect').trigger('change');
    }

    // Download CSV template
    $('#downloadTemplate').on('click', function(e) {
        e.preventDefault();
        var csvContent = "type,question_text,options,correct_answer,points,explanation\n";
        csvContent += "multiple_choice,What is the capital of France?,\"Paris,London,Berlin,Madrid\",1,1,Paris is the capital of France.\n";
        csvContent += "true_false,The sun rises in the east.,,True,1,The sun rises in the east.\n";
        csvContent += "short_answer,What is the chemical symbol for water?,,H2O,1,H2O is water.\n";
        var blob = new Blob([csvContent], { type: 'text/csv' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'quiz_template.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});
</script>

<style>
.step-indicator {
    display: flex;
    justify-content: space-between;
    max-width: 400px;
    margin: 0 auto 2rem;
}
.step {
    text-align: center;
    flex: 1;
}
.step .circle {
    width: 40px;
    height: 40px;
    background: #e9ecef;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #495057;
}
.step.active .circle {
    background: #3b7cff;
    color: white;
    box-shadow: 0 0 0 3px rgba(59,124,255,0.3);
}
.step .label {
    font-size: 0.8rem;
    margin-top: 8px;
    color: #6c757d;
}
.step.active .label {
    color: #1e293b;
    font-weight: 500;
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

</body>
</html>