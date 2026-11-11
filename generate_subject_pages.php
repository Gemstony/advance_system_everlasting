<?php
// generate_subject_pages.php - Run this once, then delete it
// This script creates student/{slug}_index.php for every subject

$subjects = [
    'Chemistry' => ['icon' => 'bi-calculator-fill', 'color' => '#3b7cff', 'simulations' => [
        ['name' => '3D Periodic Table', 'icon' => 'bi-grid-3x3-gap-fill', 'color' => '#3b7cff', 'desc' => 'Explore all 118 elements, properties, and atomic structure.', 'active' => true],
        ['name' => 'Iron Rusting (Corrosion)', 'icon' => 'bi-droplet-half', 'color' => '#e67e22', 'desc' => 'Understand the electrochemical process of rusting.', 'active' => false],
        ['name' => 'Electrolysis', 'icon' => 'bi-lightning-charge', 'color' => '#f1c40f', 'desc' => 'Visualise electrolysis of water and copper purification.', 'active' => false],
        ['name' => 'Acid-Base Titration', 'icon' => 'bi-eyedropper', 'color' => '#8e44ad', 'desc' => 'Learn about neutralisation reactions and pH curves.', 'active' => false],
        ['name' => 'Molecular Geometry', 'icon' => 'bi-diagram-3', 'color' => '#2ecc71', 'desc' => 'Build 3D molecular models with VSEPR theory.', 'active' => false],
        ['name' => 'Reaction Rates', 'icon' => 'bi-speedometer2', 'color' => '#e74c3c', 'desc' => 'Explore factors affecting reaction rates.', 'active' => false],
    ]],
    'Biology' => ['icon' => 'bi-tree', 'color' => '#28a745', 'simulations' => [
        ['name' => 'Cell Structure (3D)', 'icon' => 'bi-flower1', 'color' => '#28a745', 'desc' => 'Explore plant and animal cells, organelles, and functions.', 'active' => false],
        ['name' => 'DNA Replication', 'icon' => 'bi-dna', 'color' => '#8e44ad', 'desc' => 'Visualise DNA replication and protein synthesis.', 'active' => false],
        ['name' => 'Human Circulatory System', 'icon' => 'bi-heart-pulse', 'color' => '#e74c3c', 'desc' => 'Interactive 3D heart and blood flow simulation.', 'active' => false],
        ['name' => 'Photosynthesis', 'icon' => 'bi-sun', 'color' => '#f1c40f', 'desc' => 'Understand the process of photosynthesis in plants.', 'active' => false],
        ['name' => 'Mitosis & Meiosis', 'icon' => 'bi-arrow-repeat', 'color' => '#17a2b8', 'desc' => 'Step‑by‑step cell division animations.', 'active' => false],
        ['name' => 'Ecosystem Dynamics', 'icon' => 'bi-globe2', 'color' => '#20c997', 'desc' => 'Explore food chains, energy flow, and ecosystems.', 'active' => false],
    ]],
    'Physics' => ['icon' => 'bi-graph-up', 'color' => '#20c997', 'simulations' => [
        ['name' => 'Newton’s Laws', 'icon' => 'bi-cursor', 'color' => '#20c997', 'desc' => 'Interactive simulations of force, mass, and acceleration.', 'active' => false],
        ['name' => 'Electric Circuits', 'icon' => 'bi-lighting', 'color' => '#f1c40f', 'desc' => 'Build and test series/parallel circuits.', 'active' => false],
        ['name' => 'Wave Motion', 'icon' => 'bi-water', 'color' => '#3b82f6', 'desc' => 'Visualise transverse and longitudinal waves.', 'active' => false],
        ['name' => 'Projectile Motion', 'icon' => 'bi-rocket-takeoff', 'color' => '#e74c3c', 'desc' => 'Simulate trajectories under gravity.', 'active' => false],
        ['name' => 'Optics (Lenses & Mirrors)', 'icon' => 'bi-eye', 'color' => '#8e44ad', 'desc' => 'Ray diagrams and image formation.', 'active' => false],
        ['name' => 'Thermodynamics', 'icon' => 'bi-thermometer-half', 'color' => '#fd7e14', 'desc' => 'Heat transfer and gas laws.', 'active' => false],
    ]],
    'Agriculture' => ['icon' => 'bi-flower1', 'color' => '#2e8b57', 'simulations' => [
        ['name' => 'Crop Growth Cycle', 'icon' => 'bi-seedling', 'color' => '#2e8b57', 'desc' => 'Simulate planting, growth, and harvest.', 'active' => false],
        ['name' => 'Soil Profile', 'icon' => 'bi-layers', 'color' => '#8b4513', 'desc' => 'Explore soil layers and composition.', 'active' => false],
        ['name' => 'Irrigation Systems', 'icon' => 'bi-droplet', 'color' => '#0dcaf0', 'desc' => 'Compare drip, sprinkler, and flood irrigation.', 'active' => false],
        ['name' => 'Livestock Management', 'icon' => 'bi-house', 'color' => '#6c757d', 'desc' => 'Virtual farm with animal care modules.', 'active' => false],
        ['name' => 'Pest Control', 'icon' => 'bi-bug', 'color' => '#dc3545', 'desc' => 'Identify pests and select control methods.', 'active' => false],
        ['name' => 'Greenhouse Effect', 'icon' => 'bi-thermometer-sun', 'color' => '#ffc107', 'desc' => 'Climate control in a virtual greenhouse.', 'active' => false],
    ]],
    'Computer Science' => ['icon' => 'bi-pc-display', 'color' => '#6f42c1', 'simulations' => [
        ['name' => 'Algorithm Visualiser', 'icon' => 'bi-diagram-2', 'color' => '#6f42c1', 'desc' => 'Watch sorting and searching algorithms in action.', 'active' => false],
        ['name' => 'Binary & Logic Gates', 'icon' => 'bi-calculator', 'color' => '#0dcaf0', 'desc' => 'Interactive logic gate simulator.', 'active' => false],
        ['name' => 'CPU Simulator', 'icon' => 'bi-cpu', 'color' => '#fd7e14', 'desc' => 'Understand fetch‑decode‑execute cycle.', 'active' => false],
        ['name' => 'Network Topologies', 'icon' => 'bi-diagram-3', 'color' => '#20c997', 'desc' => 'Build and test star, bus, ring networks.', 'active' => false],
        ['name' => 'HTML/CSS Lab', 'icon' => 'bi-code-slash', 'color' => '#e83e8c', 'desc' => 'Live coding environment for web basics.', 'active' => false],
        ['name' => 'Database Design', 'icon' => 'bi-database', 'color' => '#28a745', 'desc' => 'Design tables and run SQL queries.', 'active' => false],
    ]],
    'Geography' => ['icon' => 'bi-globe2', 'color' => '#0d6efd', 'simulations' => [
        ['name' => 'Map Reading', 'icon' => 'bi-map', 'color' => '#0d6efd', 'desc' => 'Interactive topographic map with contour lines.', 'active' => false],
        ['name' => 'Climate Zones', 'icon' => 'bi-cloud-sun', 'color' => '#ffc107', 'desc' => 'Explore global climate patterns.', 'active' => false],
        ['name' => 'Volcano Simulation', 'icon' => 'bi-volcano', 'color' => '#dc3545', 'desc' => 'Understand eruption types and effects.', 'active' => false],
        ['name' => 'River Systems', 'icon' => 'bi-water', 'color' => '#0dcaf0', 'desc' => 'Erosion, deposition, and meander formation.', 'active' => false],
        ['name' => 'Population Density', 'icon' => 'bi-people', 'color' => '#6f42c1', 'desc' => 'Interactive demographic maps.', 'active' => false],
        ['name' => 'Urban Planning', 'icon' => 'bi-building', 'color' => '#fd7e14', 'desc' => 'Design sustainable cities.', 'active' => false],
    ]],
    // For brevity, add all remaining subjects with generic simulation placeholders.
    // The pattern is identical: each subject has an icon, color, and 6 placeholder simulations.
    // Instead of listing all here, the script will generate files for the full list below.
];

// Full list of all subjects from subject_simulations.php
$all_subject_names = [
    'Chemistry', 'Biology', 'Physics', 'Agriculture', 'Computer Science', 'Geography',
    'Historia ya Tanzania na Maadili', 'History', 'Bible Knowledge', 'Elimu ya Dini ya Kiislamu',
    'Basic Mathematics', 'Additional Mathematics', 'Kiswahili', 'English Language', 'Chinese',
    'Arabic', 'French', 'Literature in English', 'Fasihi ya Kiswahili', 'Business Studies',
    'Bookkeeping', 'Textile and Garment Construction', 'Food and Nutrition', 'Music',
    'Sports', 'Fine Arts', 'Performing Arts'
];

// Helper to generate a slug from a subject name
function makeSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    return trim($slug, '_');
}

// Default simulation cards for subjects not explicitly defined above
$default_simulations = [
    ['name' => 'Core Concepts', 'icon' => 'bi-book', 'color' => '#6c757d', 'desc' => 'Interactive introduction to key topics.', 'active' => false],
    ['name' => 'Interactive Quiz', 'icon' => 'bi-question-circle', 'color' => '#ffc107', 'desc' => 'Test your knowledge with instant feedback.', 'active' => false],
    ['name' => 'Case Studies', 'icon' => 'bi-file-text', 'color' => '#17a2b8', 'desc' => 'Real‑world examples and analysis.', 'active' => false],
    ['name' => 'Virtual Lab', 'icon' => 'bi-flask', 'color' => '#28a745', 'desc' => 'Hands‑on experiments in a virtual environment.', 'active' => false],
    ['name' => 'Timeline', 'icon' => 'bi-clock-history', 'color' => '#fd7e14', 'desc' => 'Historical development of the subject.', 'active' => false],
    ['name' => '3D Models', 'icon' => 'bi-cube', 'color' => '#6f42c1', 'desc' => 'Explore concepts in 3D.', 'active' => false],
];

// Template for each subject page
function getSubjectTemplate($subject, $icon, $color, $simulations) {
    $slug = makeSlug($subject);
    $simulationCards = '';
    foreach ($simulations as $sim) {
        $btnClass = $sim['active'] ? 'btn-periodic' : 'btn-coming-soon disabled';
        $disabledAttr = $sim['active'] ? '' : 'disabled';
        $simulationCards .= <<<CARD
        <div class="col-md-6 col-lg-4">
            <div class="simulation-card">
                <div class="simulation-icon"><i class="bi {$sim['icon']}" style="color: {$sim['color']};"></i></div>
                <h5 class="fw-bold">{$sim['name']}</h5>
                <p class="small">{$sim['desc']}</p>
                <button class="btn-sim {$btnClass}" data-bs-toggle="modal" data-bs-target="#comingSoonModal" {$disabledAttr}>
                    <i class="bi bi-play-circle"></i> Launch Simulation
                </button>
            </div>
        </div>
CARD;
    }

    return <<<PHP
<?php
// student/{$slug}_index.php - {$subject} Simulations Hub
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if (!(\$res == "1" && \$level == "3")) {
    header("location:../");
    exit;
}

\$student_id = isset(\$account_id) ? \$account_id : (isset(\$_SESSION['student_id']) ? \$_SESSION['student_id'] : null);
if (!\$student_id) die("Unable to identify student. Please log in again.");

\$subject_name = "{$subject}";

try {
    \$pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    \$stmt = \$pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    \$stmt->execute([\$student_id]);
    \$studentData = \$stmt->fetch();
    if (!\$studentData) die("Student not found.");

    \$student = [
        'fname' => \$studentData['fname'],
        'lname' => \$studentData['lname'],
        'id' => \$student_id,
        'display_image' => \$studentData['display_image'] ?? null,
        'gender' => \$studentData['gender'] ?? null,
        'email' => \$studentData['email'] ?? null
    ];
} catch (PDOException \$e) {
    die("Database error: " . htmlspecialchars(\$e->getMessage()));
}

require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi {$icon} me-2"></i> {$subject} Simulations</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student/learning_program.php">Learn</a></li>
            <li class="breadcrumb-item"><a href="student/subject_simulations.php">Subjects</a></li>
            <li class="breadcrumb-item active">{$subject}</li>
        </ul>
    </div>

    <div class="row g-4 mb-4">
        {$simulationCards}
    </div>

    <div class="alert-glass">
        <i class="bi bi-info-circle me-2"></i> More {$subject} simulations are being added regularly. Stay tuned!
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
\$(document).ready(function(){
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
PHP;
}

// Generate files
foreach ($all_subject_names as $subject) {
    $slug = makeSlug($subject);
    $filename = "student/{$slug}_index.php";
    
    // Use custom simulation list if defined, else default
    if (isset($subjects[$subject])) {
        $data = $subjects[$subject];
        $icon = $data['icon'];
        $color = $data['color'];
        $simulations = $data['simulations'];
    } else {
        $icon = 'bi-book';
        $color = '#6c757d';
        $simulations = $default_simulations;
    }
    
    $content = getSubjectTemplate($subject, $icon, $color, $simulations);
    file_put_contents($filename, $content);
    echo "Created: $filename<br>";
}
echo "Done! All subject simulation hubs have been created.";
?>