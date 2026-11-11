<?php
// student/chinese_index.php - Chinese Simulations Hub
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if (!($res == "1" && $level == "3")) {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$subject_name = "Chinese";

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch();
    if (!$studentData) die("Student not found.");

    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-book me-2"></i> Chinese Simulations</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student/learning_program.php">Learn</a></li>
            <li class="breadcrumb-item"><a href="student/subject_simulations.php">Subjects</a></li>
            <li class="breadcrumb-item active">Chinese</li>
        </ul>
    </div>

    <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-book" style="color: #6c757d;"></i></div>
                <h5 class="fw-bold">Core Concepts</h5>
                <p class="small">Interactive introduction to key topics.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-question-circle" style="color: #ffc107;"></i></div>
                <h5 class="fw-bold">Interactive Quiz</h5>
                <p class="small">Test your knowledge with instant feedback.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-file-text" style="color: #17a2b8;"></i></div>
                <h5 class="fw-bold">Case Studies</h5>
                <p class="small">Real‑world examples and analysis.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-flask" style="color: #28a745;"></i></div>
                <h5 class="fw-bold">Virtual Lab</h5>
                <p class="small">Hands‑on experiments in a virtual environment.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-clock-history" style="color: #fd7e14;"></i></div>
                <h5 class="fw-bold">Timeline</h5>
                <p class="small">Historical development of the subject.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi bi-cube" style="color: #6f42c1;"></i></div>
                <h5 class="fw-bold">3D Models</h5>
                <p class="small">Explore concepts in 3D.</p>
                <button class="btn-sim btn-coming-soon disabled" data-bs-toggle="modal" data-bs-target="#comingSoonModal" disabled>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>
    </div>

    <div class="alert-glass">
        <i class="bi bi-info-circle me-2"></i> More Chinese simulations are being added regularly. Stay tuned!
    </div>
</main>

<!-- Generic "Coming Soon" Modal -->
<div class="modal fade" id="comingSoonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Coming Soon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This simulation is under development. Please check back later.</p>
            </div>
        </div>
    </div>
</div>

<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
$(document).ready(function(){
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => { if (window.scrollY > 300) scrollBtn.classList.add('show'); else scrollBtn.classList.remove('show'); });
    scrollBtn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
});
</script>

<style>
/* Glass‑morphic simulation cards */
.app-content { background: transparent !important; }
.simulation-card {
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    border-radius: 40px;
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255,255,255,0.8);
    border: 1px solid rgba(255,255,255,0.6);
    transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s;
    opacity: 0;
    transform: translateY(30px) scale(0.98);
    animation: cardGlideIn 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
    padding: 1.8rem 1.2rem;
    text-align: center;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
@keyframes cardGlideIn {
    0% { opacity: 0; transform: translateY(30px) scale(0.98); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
.simulation-card:hover {
    transform: translateY(-5px) scale(1.01);
    background: rgba(255, 255, 255, 0.85);
    box-shadow: 0 28px 48px -16px rgba(0, 0, 0, 0.15);
}
.simulation-icon { font-size: 3rem; margin-bottom: 0.8rem; }
.simulation-card h5, .simulation-card p { color: #1e293b; }
.btn-sim {
    border-radius: 60px;
    padding: 0.5rem 1.2rem;
    font-weight: 600;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    margin-top: 0.5rem;
    border: none;
    cursor: pointer;
}
.btn-periodic { background: linear-gradient(135deg, #3b82f6, #1e40af); color: white; box-shadow: 0 4px 10px rgba(59,130,246,0.3); }
.btn-coming-soon { background: linear-gradient(135deg, #6c757d, #495057); color: white; opacity: 0.7; cursor: not-allowed; }
.btn-sim:hover:not(.disabled) { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.15); }
.alert-glass {
    background: rgba(255,255,255,0.65);
    backdrop-filter: blur(8px);
    border-radius: 60px;
    padding: 0.8rem 1.5rem;
    margin-top: 2rem;
    text-align: center;
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
    transform: translateY(20px);
    animation-delay: 0.5s;
    color: #1e293b;
}
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #0f2b4d, #2c6e9e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.breadcrumb, .breadcrumb-item a, .breadcrumb-item.active { color: #1e293b !important; }
.breadcrumb { background: rgba(255,255,255,0.7); backdrop-filter: blur(4px); }
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
.scroll-top-btn:hover { transform: translateY(-3px); box-shadow: 0 9px 0 #003f7f, 0 12px 22px rgba(0,0,0,0.3); }
@media (max-width: 768px) { .simulation-card { padding: 1.2rem; } }
</style>
</body>
</html>