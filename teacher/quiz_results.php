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

$quiz_id = isset($_GET['quiz']) ? (int)$_GET['quiz'] : 0;
if (!$quiz_id) {
    header("location:teacher/quizzes");
    exit;
}

$teacher_id = $account_id;
$quiz = $pdo->prepare("SELECT * FROM tbl_quizzes WHERE id = ? AND teacher_id = ?");
$quiz->execute([$quiz_id, $teacher_id]);
$quiz = $quiz->fetch();
if (!$quiz) {
    header("location:teacher/quizzes");
    exit;
}

$submissions = $pdo->prepare("
    SELECT s.*, stu.fname, stu.lname, stu.id as student_id 
    FROM tbl_quiz_submissions s
    JOIN tbl_students stu ON s.student_id = stu.id
    WHERE s.quiz_id = ?
    ORDER BY s.score DESC
");
$submissions->execute([$quiz_id]);
$submissions = $submissions->fetchAll();

include 'inc/header.php';
include 'inc/sidebar.php';
?>
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-bar-chart"></i> Quiz Results: <?= htmlspecialchars($quiz['title']) ?></h1></div>
    </div>
    <div class="tile">
        <?php if (count($submissions) > 0): ?>
            <table class="table table-hover">
                <thead><tr><th>Student</th><th>Score</th><th>Percentage</th><th>Submitted At</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><?= htmlspecialchars($sub['fname'].' '.$sub['lname']) ?></td>
                            <td><?= $sub['score'] ?> / <?= $quiz['total_points'] ?></td>
                            <td><?= $sub['percentage'] ?>%</td>
                            <td><?= date('d M Y H:i', strtotime($sub['submitted_at'])) ?></td>
                            <td><a href="teacher/view_submission?submission=<?= $sub['id'] ?>" class="btn btn-sm btn-info">View Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-secondary">No submissions yet.</div>
        <?php endif; ?>
    </div>
</main>
<?php include 'inc/footer.php'; ?>