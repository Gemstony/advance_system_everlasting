<?php
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

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

// Hardcoded contact details – enforced regardless of DB
$phone = '+255 719 629 304';
$email = 'everlastingacademy24@gmail.com';
$whatsapp = '+255 719 629 304';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Student details for header
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

    // Class name for display
    $stmt = $pdo->prepare("SELECT c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $classInfo = $stmt->fetch();
    $className = $classInfo['class_name'] ?? '';

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-headset me-2"></i> Support Center</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Support</li>
        </ul>
    </div>

    <div class="row g-4 mb-4">
        <!-- WhatsApp Card -->
        <div class="col-md-4">
            <div class="support-card whatsapp-card">
                <div class="support-icon pulse-icon">
                    <i class="bi bi-whatsapp text-success"></i>
                </div>
                <h5 class="fw-bold">WhatsApp Support</h5>
                <p class="small text-muted">Chat instantly with our IT team</p>
                <p class="small response-time"><i class="bi bi-clock me-1"></i> Response within minutes</p>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="btn-support btn-whatsapp" target="_blank">
                    <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($whatsapp); ?>
                </a>
            </div>
        </div>
        <!-- Email Card -->
        <div class="col-md-4">
            <div class="support-card email-card">
                <div class="support-icon pulse-icon">
                    <i class="bi bi-envelope-fill text-primary"></i>
                </div>
                <h5 class="fw-bold">Email Support</h5>
                <p class="small text-muted">Send us your queries anytime</p>
                <p class="small response-time"><i class="bi bi-clock me-1"></i> Response within 24 hours</p>
                <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="btn-support btn-email">
                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($email); ?>
                </a>
            </div>
        </div>
        <!-- Phone Card -->
        <div class="col-md-4">
            <div class="support-card phone-card">
                <div class="support-icon pulse-icon">
                    <i class="bi bi-telephone-fill text-secondary"></i>
                </div>
                <h5 class="fw-bold">Call Us</h5>
                <p class="small text-muted">Speak directly to our staff</p>
                <p class="small response-time"><i class="bi bi-clock me-1"></i> Available during office hours</p>
                <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="btn-support btn-phone">
                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($phone); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="alert-glass">
                <i class="bi bi-clock-history me-2"></i> 
                <strong>Support Hours:</strong> Monday–Friday, 8am–5pm (EAT) 
                <span class="badge bg-success ms-2">Online</span>
                <span class="ms-3"><i class="bi bi-chat-dots me-1"></i> We’re here to help!</span>
            </div>
        </div>
    </div>

    <!-- Optional: quick FAQ or additional info -->
    <div class="row">
        <div class="col-12">
            <div class="faq-glass">
                <h6 class="fw-bold"><i class="bi bi-question-circle me-2"></i> Frequently Asked Questions</h6>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-chevron-right text-primary me-1"></i> How do I reset my password? – Visit <a href="forgot-password">Forgot Password</a></li>
                    <li><i class="bi bi-chevron-right text-primary me-1"></i> Where can I view my results? – Go to <a href="results">My Results</a></li>
                    <li><i class="bi bi-chevron-right text-primary me-1"></i> Need technical help? – Reach us via WhatsApp or Email above.</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<!-- Page-specific styles (glass‑morphic, enhanced cards) -->
<style>
    /* Base adjustments */
    .app-content {
        background: transparent !important;
    }
    .support-card {
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(16px);
        border-radius: 40px;
        padding: 1.8rem 1.2rem;
        text-align: center;
        height: 100%;
        border: 1px solid rgba(255,255,255,0.7);
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255,255,255,0.9);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), 
                    box-shadow 0.4s ease,
                    background 0.3s ease;
        opacity: 0;
        transform: translateY(40px) scale(0.96);
        animation: cardGlideIn 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
        position: relative;
        overflow: hidden;
    }
    .support-card::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        border-radius: 42px;
        background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.1));
        z-index: -1;
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    .support-card:hover::before {
        opacity: 1;
    }
    /* Individual card accents */
    .whatsapp-card:hover {
        box-shadow: 0 28px 48px -16px rgba(37,211,102,0.35), 0 0 0 1px rgba(37,211,102,0.2);
    }
    .email-card:hover {
        box-shadow: 0 28px 48px -16px rgba(59,130,246,0.35), 0 0 0 1px rgba(59,130,246,0.2);
    }
    .phone-card:hover {
        box-shadow: 0 28px 48px -16px rgba(108,117,125,0.35), 0 0 0 1px rgba(108,117,125,0.2);
    }
    @keyframes cardGlideIn {
        0% { opacity: 0; transform: translateY(40px) scale(0.96); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    .support-card:hover {
        transform: translateY(-8px) scale(1.02);
        background: rgba(255, 255, 255, 0.92);
    }
    .support-icon {
        font-size: 3.4rem;
        margin-bottom: 0.8rem;
        display: inline-block;
        transition: transform 0.3s ease;
    }
    .support-card:hover .support-icon {
        transform: scale(1.1) rotate(-3deg);
    }
    .pulse-icon {
        animation: softPulse 2.5s ease-in-out infinite;
    }
    @keyframes softPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.08); }
        100% { transform: scale(1); }
    }
    .support-card h5 {
        font-weight: 700;
        color: #0f2b4d;
        letter-spacing: -0.02em;
    }
    .support-card .text-muted {
        color: #475569 !important;
    }
    .response-time {
        color: #64748b;
        font-size: 0.8rem;
        margin-top: -0.2rem;
        margin-bottom: 0.7rem;
    }
    .btn-support {
        border-radius: 60px;
        padding: 0.55rem 1.6rem;
        font-weight: 600;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        margin-top: 0.3rem;
        border: none;
        letter-spacing: 0.3px;
    }
    .btn-whatsapp {
        background: linear-gradient(135deg, #25D366, #1A9B4C);
        color: white;
        box-shadow: 0 6px 16px rgba(37,211,102,0.35);
    }
    .btn-email {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        box-shadow: 0 6px 16px rgba(59,130,246,0.35);
    }
    .btn-phone {
        background: linear-gradient(135deg, #6c757d, #343a40);
        color: white;
        box-shadow: 0 6px 16px rgba(108,117,125,0.35);
    }
    .btn-support:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.2) !important;
        color: white;
        text-decoration: none;
    }
    .btn-whatsapp:hover { background: linear-gradient(135deg, #20b85f, #128C7E); }
    .btn-email:hover { background: linear-gradient(135deg, #2563eb, #1e40af); }
    .btn-phone:hover { background: linear-gradient(135deg, #5a6268, #212529); }

    .alert-glass {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(8px);
        border-radius: 60px;
        padding: 0.9rem 1.8rem;
        text-align: center;
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
        transform: translateY(20px);
        animation-delay: 0.5s;
        color: #1e293b;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 6px 20px -8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .alert-glass .badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.8rem;
        border-radius: 30px;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    /* FAQ glass */
    .faq-glass {
        background: rgba(255,255,255,0.5);
        backdrop-filter: blur(6px);
        border-radius: 30px;
        padding: 1.2rem 1.8rem;
        border: 1px solid rgba(255,255,255,0.5);
        box-shadow: 0 6px 20px -8px rgba(0,0,0,0.04);
        transition: background 0.3s ease;
    }
    .faq-glass:hover {
        background: rgba(255,255,255,0.7);
    }
    .faq-glass ul li {
        padding: 0.2rem 0;
        color: #1e293b;
    }
    .faq-glass ul li a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
    }
    .faq-glass ul li a:hover {
        text-decoration: underline;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #0f2b4d, #2c6e9e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .breadcrumb, .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
    .breadcrumb {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        border-radius: 40px;
        padding: 0.4rem 1.2rem;
    }
    @media (max-width: 768px) {
        .support-card { padding: 1.4rem 1rem; }
        .alert-glass { flex-direction: column; gap: 0.3rem; }
        .faq-glass { padding: 1rem; }
    }
</style>

<!-- Page-specific scripts (jQuery, Bootstrap, main.js) -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>