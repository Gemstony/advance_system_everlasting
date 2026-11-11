<?php
// student/learning_program.php - Main Learn Dashboard
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');
require_once('student/inc/finance_functions.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET SESSION sql_mode = ''");

    $stmt = $pdo->prepare("SELECT s.* FROM tbl_students s WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) die("Student not found.");

    $fname = $student['fname'];
    $lname = $student['lname'];

} catch (PDOException $e) { die("Database error: " . htmlspecialchars($e->getMessage())); }

$student_header = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="student/view" class="btn"><i class="bi bi-person"></i> My Profile</a>
        <a href="student/subjects" class="btn"><i class="bi bi-book"></i> My Subjects</a>
        <a href="student/results" class="btn"><i class="bi bi-file-text"></i> Full Results</a>
        <button class="btn" data-bs-toggle="modal" data-bs-target="#aiChatModal">
            <i class="bi bi-robot"></i> AI Tutor
        </button>
    </div>

    <div class="student-info-card" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="mb-2">Welcome back, <?php echo htmlspecialchars($fname); ?>!</h3>
                <p class="mb-0">Explore interactive simulations, AI tutoring, and study tools.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="bi bi-mortarboard" style="font-size: 3.5rem; opacity: 0.7;"></i>
            </div>
        </div>
    </div>

    <!-- 5 Learning Cards (removed Learning Resources & Study Groups) -->
    <div class="row">
        <!-- Card 1: AI Study Assistant -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="tile text-center h-100">
                <div class="tile-body">
                    <i class="bi bi-robot fs-1 text-info mb-3 d-inline-block"></i>
                    <h5 class="tile-title">AI Study Assistant</h5>
                    <p class="text-muted">Get instant help with homework, exam prep, or subject questions.</p>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#aiChatModal">
                        <i class="bi bi-chat-dots"></i> Open AI Tutor
                    </button>
                </div>
            </div>
        </div>

        <!-- Card 2: Subject Simulations (NEW) -->
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="student/subject_simulations.php" class="text-decoration-none">
                <div class="tile text-center h-100">
                    <div class="tile-body">
                        <i class="bi bi-bezier2 fs-1 text-success mb-3 d-inline-block"></i>
                        <h5 class="tile-title">Subject Simulations</h5>
                        <p class="text-muted">Interactive 3D simulations – Chemistry, Physics & more.</p>
                        <span class="btn btn-outline-success rounded-pill">Explore →</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Card 3: Study Planner -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="tile text-center h-100">
                <div class="tile-body">
                    <i class="bi bi-calendar-check fs-1 text-secondary mb-3 d-inline-block"></i>
                    <h5 class="tile-title">Study Planner</h5>
                    <p class="text-muted">Create a custom study schedule.</p>
                    <a href="#" class="btn btn-outline-secondary rounded-pill disabled">Coming Soon</a>
                </div>
            </div>
        </div>

        <!-- Card 4: Learning Analytics -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="tile text-center h-100">
                <div class="tile-body">
                    <i class="bi bi-graph-up fs-1 text-danger mb-3 d-inline-block"></i>
                    <h5 class="tile-title">Learning Analytics</h5>
                    <p class="text-muted">See your strengths and areas for improvement.</p>
                    <a href="#" class="btn btn-outline-secondary rounded-pill disabled">Coming Soon</a>
                </div>
            </div>
        </div>

        <!-- Card 5: Study Resources (original, keep as placeholder) -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="tile text-center h-100">
                <div class="tile-body">
                    <i class="bi bi-link-45deg fs-1 text-primary mb-3 d-inline-block"></i>
                    <h5 class="tile-title">Study Resources</h5>
                    <p class="text-muted">Useful links, e‑books, and reference materials.</p>
                    <a href="#" class="btn btn-outline-secondary rounded-pill disabled">Coming Soon</a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-dark text-white-50 bg-dark border-secondary" role="alert">
        <i class="bi bi-info-circle"></i> More features are being added regularly. Stay tuned!
    </div>
</main>

<!-- AI Chat Modal (same as before) -->
<div class="modal fade" id="aiChatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-robot"></i> AI Study Assistant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="chatModalMessages" style="height: 55vh; overflow-y: auto; background: rgba(0,0,0,0.7);">
                <div class="d-flex mb-3">
                    <div class="bg-secondary bg-opacity-25 rounded-3 p-3">
                        <i class="bi bi-robot"></i> Hello! I'm your personal learning assistant. Ask me anything about your subjects, exam results, study tips, or school activities.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <div class="input-group">
                    <input type="text" id="modalChatInput" class="form-control bg-dark text-white border-secondary" placeholder="Type your question...">
                    <button class="btn btn-primary" id="modalSendBtn">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
$(document).ready(function() {
    // AI Chat logic (same as before)
    const modalMessages = document.getElementById('chatModalMessages');
    const modalInput = document.getElementById('modalChatInput');
    const modalSend = document.getElementById('modalSendBtn');
    let isWaiting = false;

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function appendMessage(sender, text, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'd-flex mb-3';
        if (isUser) messageDiv.classList.add('justify-content-end');
        const bubbleClass = isUser ? 'bg-primary' : 'bg-secondary bg-opacity-25';
        const icon = isUser ? '<i class="bi bi-person-circle me-1"></i>' : '<i class="bi bi-robot me-1"></i>';
        messageDiv.innerHTML = `<div class="${bubbleClass} rounded-3 p-3" style="max-width: 85%;">${icon} ${escapeHtml(text)}</div>`;
        modalMessages.appendChild(messageDiv);
        modalMessages.scrollTop = modalMessages.scrollHeight;
    }

    async function sendMessage() {
        const message = modalInput.value.trim();
        if (!message || isWaiting) return;
        appendMessage('You', message, true);
        modalInput.value = '';
        isWaiting = true;
        modalSend.disabled = true;
        
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typingIndicator';
        typingDiv.className = 'd-flex mb-2';
        typingDiv.innerHTML = '<div class="bg-secondary bg-opacity-25 rounded-3 p-2"><i class="bi bi-three-dots"></i> Thinking...</div>';
        modalMessages.appendChild(typingDiv);
        modalMessages.scrollTop = modalMessages.scrollHeight;
        
        try {
            const response = await fetch('api/student_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });
            const data = await response.json();
            document.getElementById('typingIndicator')?.remove();
            if (data.error) appendMessage('AI', '⚠️ ' + data.error, false);
            else appendMessage('AI', data.reply, false);
        } catch (err) {
            document.getElementById('typingIndicator')?.remove();
            appendMessage('AI', 'Sorry, I encountered an error. Please try again.', false);
        } finally {
            isWaiting = false;
            modalSend.disabled = false;
            modalInput.focus();
        }
    }

    modalSend.addEventListener('click', sendMessage);
    modalInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
    $('#aiChatModal').on('shown.bs.modal', function() { modalInput.focus(); });

    const scrollBtn = document.getElementById('scrollTopBtn');
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 300) scrollBtn.classList.add('show');
        else scrollBtn.classList.remove('show');
    });
    scrollBtn.addEventListener('click', () => $('html, body').animate({ scrollTop: 0 }, 500));
});
</script>
<style>
.app-content { position: relative; z-index: 1; background: transparent; }
.tile { background: #ffffff; border-radius: 28px; box-shadow: 0 15px 35px -10px rgba(0,0,0,0.1); transition: all 0.25s ease; padding: 1.5rem; margin-bottom: 1.5rem; }
.tile:hover { transform: translateY(-5px); box-shadow: 0 25px 40px -12px rgba(0,0,0,0.2); }
.student-info-card { background: #ffffff; border-radius: 28px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 15px 35px -10px rgba(0,0,0,0.1); }
.quick-actions .btn { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 40px; margin-right: 0.5rem; margin-bottom: 0.5rem; padding: 0.5rem 1.2rem; }
.quick-actions .btn:hover { background: #f1f5f9; transform: translateY(-2px); }
.scroll-top-btn { position: fixed; bottom: 30px; right: 30px; width: 52px; height: 52px; background: linear-gradient(145deg, #00c6ff, #0072ff); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.6rem; cursor: pointer; box-shadow: 0 6px 0 #003f7f, 0 8px 18px rgba(0,0,0,0.25); z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.scroll-top-btn.show { opacity: 1; visibility: visible; }
.scroll-top-btn:hover { transform: translateY(-3px); box-shadow: 0 9px 0 #003f7f, 0 12px 22px rgba(0,0,0,0.3); }
body.dark .tile, body.dark .student-info-card { background: rgba(30,41,59,0.92); color: #e2e8f0; }
body.dark .quick-actions .btn { background: #1e293b; border-color: #334155; color: #cbd5e1; }
</style>
</body>
</html>