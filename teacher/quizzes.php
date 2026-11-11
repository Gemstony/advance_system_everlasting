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

// Delete quiz
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $quiz_id = (int)$_GET['delete'];
    $check = $pdo->prepare("SELECT id FROM tbl_quizzes WHERE id = ? AND teacher_id = ?");
    $check->execute([$quiz_id, $teacher_id]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM tbl_quizzes WHERE id = ?")->execute([$quiz_id]);
        $_SESSION['reply'] = [['success', 'Quiz deleted successfully.']];
    } else {
        $_SESSION['reply'] = [['danger', 'Quiz not found or you do not have permission.']];
    }
    header("location:teacher/quizzes");
    exit;
}

// Toggle publish status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $quiz_id = (int)$_GET['toggle'];
    $check = $pdo->prepare("SELECT is_published FROM tbl_quizzes WHERE id = ? AND teacher_id = ?");
    $check->execute([$quiz_id, $teacher_id]);
    $quiz = $check->fetch();
    if ($quiz) {
        $new_status = $quiz['is_published'] ? 0 : 1;
        $pdo->prepare("UPDATE tbl_quizzes SET is_published = ? WHERE id = ?")->execute([$new_status, $quiz_id]);
        $_SESSION['reply'] = [['success', 'Quiz status updated.']];
    }
    header("location:teacher/quizzes");
    exit;
}

// Fetch all quizzes created by this teacher
$stmt = $pdo->prepare("
    SELECT q.*, c.name as class_name, s.name as subject_name, t.name as term_name,
           (SELECT COUNT(*) FROM tbl_quiz_submissions WHERE quiz_id = q.id) as submissions_count
    FROM tbl_quizzes q
    JOIN tbl_classes c ON q.class_id = c.id
    JOIN tbl_subject_combinations sc ON q.subject_combination_id = sc.id
    JOIN tbl_subjects s ON sc.subject = s.id
    JOIN tbl_terms t ON q.term_id = t.id
    WHERE q.teacher_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$teacher_id]);
$quizzes = $stmt->fetchAll();

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);

include 'inc/header.php';
include 'inc/sidebar.php';
?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-pencil-square me-2"></i> My Quizzes</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item active">Quizzes</li>
        </ul>
    </div>

    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <div class="tile">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="tile-title"><i class="bi bi-list-ul"></i> All Quizzes</h3>
            <a href="teacher/create_quiz" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create New Quiz</a>
        </div>
        <?php if (count($quizzes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Term</th>
                            <th>Points</th>
                            <th>Submissions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($quizzes as $row): ?>
                            <tr>
                                <td><?= $sn++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['title']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars(substr($row['description'], 0, 50)) ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><?= htmlspecialchars($row['term_name']) ?></td>
                                <td><?= $row['total_points'] ?></td>
                                <td><?= $row['submissions_count'] ?></td>
                                <td>
                                    <?php if ($row['is_published']): ?>
                                        <span class="badge bg-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="teacher/edit_quiz?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    <a href="teacher/quiz_results?quiz=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-bar-chart"></i> Results</a>
                                    <a href="teacher/quizzes?toggle=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><?= $row['is_published'] ? 'Unpublish' : 'Publish' ?></a>
                                    <a href="teacher/quizzes?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this quiz? All questions and submissions will be lost.')"><i class="bi bi-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">No quizzes created yet. <a href="teacher/create_quiz">Create your first quiz</a>.</div>
        <?php endif; ?>
    </div>
</main>

<style>
.tile {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
.btn-warning {
    background: #ffc107;
    border: none;
    color: #1e293b;
}
.btn-warning:hover {
    background: #e0a800;
}
.btn-danger {
    background: #dc3545;
    border: none;
}
.btn-danger:hover {
    background: #c82333;
}
.table th {
    background: #f8f9fa;
}
.badge {
    font-size: 0.8rem;
}
.app-content {
    background: transparent !important;
}
</style>

</body>
</html>