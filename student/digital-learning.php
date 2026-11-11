<?php
// student/digital-learning.php - Subject-based learning hub
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = $account_id ?? $_SESSION['student_id'] ?? null;
if (!$student_id) {
    die("Unable to identify student. Please log in again.");
}

// Build $student array for header/sidebar compatibility
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];

try {
    $conn = new PDO(
        'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset . ';collation=' . DBCollation,
        DBUser, DBPass
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get student's class
    $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentRow) throw new Exception("Student record not found");
    $classId = (int)$studentRow['class'];

    // Get all subjects assigned to this class via subject combinations
    $stmt = $conn->prepare("
        SELECT sc.id, sc.class, sc.subject, s.name AS subject_name
        FROM tbl_subject_combinations sc
        JOIN tbl_subjects s ON sc.subject = s.id
        WHERE s.status = 1
    ");
    $stmt->execute();
    $allCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subjects = [];
    foreach ($allCombos as $combo) {
        $classList = unserialize($combo['class']);
        if (is_array($classList) && in_array($classId, $classList)) {
            $subjects[$combo['subject']] = [
                'id' => $combo['subject'],
                'name' => $combo['subject_name']
            ];
        }
    }

    // Icon mapping for Tanzanian A‑Level subjects
    $subjectIcons = [
        'Biology' => 'bi bi-flower1',
        'Physics' => 'bi bi-lightning-charge',
        'Chemistry' => 'bi bi-droplet',
        'Mathematics' => 'bi bi-calculator',
        'Additional Mathematics' => 'bi bi-calculator-fill',
        'English Language' => 'bi bi-chat-dots',
        'Kiswahili' => 'bi bi-chat',
        'History' => 'bi bi-clock-history',
        'Geography' => 'bi bi-globe',
        'Computer Science' => 'bi bi-pc-display',
        'Business Studies' => 'bi bi-briefcase',
        'Bookkeeping' => 'bi bi-journal-bookmark-fill',
        'Literature in English' => 'bi bi-book',
        'Fasihi ya Kiswahili' => 'bi bi-book-half',
        'Agriculture' => 'bi bi-tree',
        'Bible Knowledge' => 'bi bi-cross',
        'Elimu ya Dini ya Kiislamu' => 'bi bi-star',
        'Chinese' => 'bi bi-translate',
        'Arabic' => 'bi bi-translate',
        'French' => 'bi bi-translate',
        'Textile and Garment Construction' => 'bi bi-scissors',
        'Food and Nutrition' => 'bi bi-egg-fried',
        'Music' => 'bi bi-music-note-beamed',
        'Sports' => 'bi bi-trophy',
        'Fine Arts' => 'bi bi-brush',
        'Performing Arts' => 'bi bi-theater',
    ];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-journal-bookmark-fill me-2"></i> My Subjects</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Digital Learning</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <div class="row">
                        <?php if (empty($subjects)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">No subjects assigned to your class yet.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($subjects as $subject): 
                                $icon = $subjectIcons[$subject['name']] ?? 'bi bi-journal-bookmark-fill';
                            ?>
                                <div class="col-md-3 col-sm-6 mb-4">
                                    <a href="student/subject.php?id=<?= $subject['id'] ?>" class="subject-card-link">
                                        <div class="subject-card text-center">
                                            <div class="subject-icon">
                                                <i class="<?= $icon ?> fs-1"></i>
                                            </div>
                                            <h5 class="subject-title mt-3"><?= htmlspecialchars($subject['name']) ?></h5>
                                            <p class="text-muted small">Click to explore tasks & games</p>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="scrollTopBtn" class="scroll-top-btn" title="Scroll to top">
    <i class="bi bi-arrow-up"></i>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) scrollBtn.classList.add('show');
    else scrollBtn.classList.remove('show');
});
scrollBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<style>
.subject-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(4px);
    border-radius: 28px;
    padding: 1.5rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}
.subject-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}
.subject-icon {
    color: #4361ee;
    font-size: 2.5rem;
}
.subject-title {
    font-weight: 700;
}
.subject-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
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
.scroll-top-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 52px;
    height: 52px;
    background: linear-gradient(145deg, #00c6ff, #0072ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    cursor: pointer;
    box-shadow: 0 6px 0 #003f7f, 0 8px 18px rgba(0,0,0,0.25);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}
.scroll-top-btn.show { opacity: 1; visibility: visible; }
@media (max-width: 768px) {
    .subject-title { font-size: 1rem; }
}
</style>
</body>
</html>