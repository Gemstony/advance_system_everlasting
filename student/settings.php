<?php
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

// Build $student array for modular header
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? null,
    'email' => $email ?? null
];

// Fetch additional data for the profile page
try {
    $pdo = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Class name
    $stmt = $pdo->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$class]);
    $class_name = $stmt->fetchColumn() ?: 'Not assigned';

    // Subject count for this class (for quick stats)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT subject) FROM tbl_subject_combinations WHERE FIND_IN_SET(?, class)");
    $stmt->execute([$class]);
    $subject_count = (int)$stmt->fetchColumn();

    // Latest term results (only for the latest term average, not displayed in a summary card)
    $stmt = $pdo->prepare("
        SELECT t.name AS term_name, AVG(r.score) AS avg_score
        FROM tbl_exam_results r
        LEFT JOIN tbl_terms t ON r.term = t.id
        WHERE r.student = ?
        GROUP BY r.term
        ORDER BY r.term DESC
        LIMIT 1
    ");
    $stmt->execute([$account_id]);
    $latest = $stmt->fetch();
    if ($latest) {
        $latest_avg = round($latest['avg_score']);
        $latest_term = $latest['term_name'];
    } else {
        $latest_avg = 0;
        $latest_term = 'No results';
    }

    // Fee module
    $fee_module_enabled = isFeeModuleEnabled($pdo);
    $fee_account = null;
    if ($fee_module_enabled) {
        $academic_year = date('Y');
        $fee_account = getStudentFeeAccount($pdo, $account_id, $academic_year);
    }

    // Recent results (last 5)
    $stmt = $pdo->prepare("
        SELECT s.name AS subject_name, r.score, t.name AS term_name
        FROM tbl_exam_results r
        LEFT JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        LEFT JOIN tbl_terms t ON r.term = t.id
        WHERE r.student = ?
        ORDER BY r.id DESC
        LIMIT 5
    ");
    $stmt->execute([$account_id]);
    $recent_results = $stmt->fetchAll();

    // Recent announcements
    $stmt = $pdo->prepare("SELECT title, create_date FROM tbl_announcements WHERE level IN (1,2) ORDER BY create_date DESC LIMIT 3");
    $stmt->execute();
    $announcements = $stmt->fetchAll();

} catch (PDOException $e) {
    // Fallback values
    $class_name = 'Not assigned';
    $subject_count = 0;
    $latest_avg = 0;
    $latest_term = 'No results';
    $fee_module_enabled = false;
    $fee_account = null;
    $recent_results = [];
    $announcements = [];
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-person-gear me-2"></i> My Profile</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">My Profile</li>
        </ul>
    </div>

    <div class="row g-4">
        <!-- Left Column: Profile Picture + Personal Info + Quick Actions -->
        <div class="col-md-4">
            <!-- Profile Picture -->
            <div class="tile card-custom p-4 text-center">
                <div class="profile-picture-wrapper mb-3">
                    <?php if (!empty($img) && $img !== 'DEFAULT' && file_exists('../images/students/' . $img)): ?>
                        <img src="images/students/<?php echo htmlspecialchars($img); ?>" alt="Profile Picture" class="profile-picture img-fluid rounded-circle" style="width:150px; height:150px; object-fit:cover; border:3px solid #3498db;">
                    <?php else: ?>
                        <div class="default-avatar" style="width:150px; height:150px; border-radius:50%; background:#e9ecef; display:flex; align-items:center; justify-content:center; margin:0 auto; font-size:4rem; color:#6c757d; border:3px solid #3498db;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-0" style="color:#000;"><?php echo htmlspecialchars($fname . ' ' . $lname); ?></h5>
                <p class="small" style="color:#000;"><?php echo htmlspecialchars($account_id); ?></p>
                <p class="small" style="color:#000;"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($email); ?></p>
                <hr>
                <div class="d-grid gap-2">
                    <a href="student/results" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-text"></i> View Results</a>
                    <a href="student/fees" class="btn btn-outline-success btn-sm"><i class="bi bi-credit-card"></i> Fee Dashboard</a>
                    <a href="student/subjects" class="btn btn-outline-info btn-sm"><i class="bi bi-book"></i> My Subjects</a>
                </div>
            </div>

            <!-- Personal Information (Read-Only) -->
            <div class="tile card-custom p-4 mt-3">
                <h6 class="fw-bold" style="color:#000;"><i class="bi bi-person-badge"></i> Personal Information</h6>
                <div class="mb-2">
                    <label class="text-muted small" style="color:#6c757d;">Registration Number</label>
                    <p class="fw-bold mb-0" style="color:#000;"><?php echo htmlspecialchars($account_id); ?></p>
                </div>
                <div class="mb-2">
                    <label class="text-muted small" style="color:#6c757d;">Full Name</label>
                    <p class="fw-bold mb-0" style="color:#000;"><?php echo htmlspecialchars($fname . ' ' . $mname . ' ' . $lname); ?></p>
                </div>
                <div class="mb-2">
                    <label class="text-muted small" style="color:#6c757d;">Gender</label>
                    <p class="fw-bold mb-0" style="color:#000;"><?php echo htmlspecialchars($gender); ?></p>
                </div>
                <div class="mb-2">
                    <label class="text-muted small" style="color:#6c757d;">Class</label>
                    <p class="fw-bold mb-0" style="color:#000;"><?php echo htmlspecialchars($class_name); ?></p>
                </div>
                <div>
                    <label class="text-muted small" style="color:#6c757d;">Email Address</label>
                    <p class="fw-bold mb-0" style="color:#000;"><?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>
        </div>

        <!-- Right Column: Updates, Fee, Recent Results, Announcements -->
        <div class="col-md-8">
            <!-- Update Email & Change Password (side by side) -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="tile card-custom p-4">
                        <h5 class="fw-bold" style="color:#000;"><i class="bi bi-envelope"></i> Update Email</h5>
                        <form action="student/core/update_email" method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label" style="color:#000;">New Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <button type="submit" name="submit" value="1" class="btn btn-primary w-100">Update Email</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="tile card-custom p-4">
                        <h5 class="fw-bold" style="color:#000;"><i class="bi bi-key"></i> Change Password</h5>
                        <form action="student/core/update_password" method="POST" autocomplete="off" id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label" style="color:#000;">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="cpass" name="cpassword" placeholder="Enter current password" required>
                                    <span class="input-group-text bg-white border-start-0 toggle-password" data-target="cpass"><i class="bi bi-eye-slash"></i></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" style="color:#000;">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="npass" name="npassword" placeholder="Enter new password" required>
                                    <span class="input-group-text bg-white border-start-0 toggle-password" data-target="npass"><i class="bi bi-eye-slash"></i></span>
                                </div>
                                <div class="password-strength" id="strengthBar"></div>
                                <div class="strength-text text-muted" id="strengthText"></div>
                                <div class="invalid-feedback" id="passwordHelp">Password must be at least 8 characters, include uppercase, lowercase, number, and special character.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" style="color:#000;">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-check-circle"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="cnpass" placeholder="Repeat new password" required>
                                    <span class="input-group-text bg-white border-start-0 toggle-password" data-target="cnpass"><i class="bi bi-eye-slash"></i></span>
                                </div>
                                <div class="invalid-feedback" id="confirmHelp">Passwords do not match.</div>
                            </div>
                            <button type="submit" name="submit" value="1" class="btn btn-primary w-100">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Fee Summary (if enabled) -->
            <?php if ($fee_module_enabled && $fee_account): ?>
            <div class="tile card-custom p-4 mt-3">
                <h5 class="fw-bold" style="color:#000;"><i class="bi bi-credit-card"></i> Fee Summary</h5>
                <div class="row text-center">
                    <div class="col-4">
                        <span class="text-muted d-block" style="color:#6c757d;">Expected</span>
                        <strong class="fs-5" style="color:#000;"><?php echo number_format($fee_account['total_expected'] ?? 0, 0, '.', ','); ?> TZS</strong>
                    </div>
                    <div class="col-4">
                        <span class="text-muted d-block" style="color:#6c757d;">Paid</span>
                        <strong class="fs-5 text-success" style="color:#28a745;"><?php echo number_format($fee_account['total_paid'] ?? 0, 0, '.', ','); ?> TZS</strong>
                    </div>
                    <div class="col-4">
                        <span class="text-muted d-block" style="color:#6c757d;">Balance</span>
                        <strong class="fs-5 <?php echo ($fee_account['balance_due'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>" style="color:<?php echo ($fee_account['balance_due'] ?? 0) > 0 ? '#dc3545' : '#28a745'; ?>;"><?php echo number_format($fee_account['balance_due'] ?? 0, 0, '.', ','); ?> TZS</strong>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Results -->
            <?php if (!empty($recent_results)): ?>
            <div class="tile card-custom p-4 mt-3">
                <h5 class="fw-bold" style="color:#000;"><i class="bi bi-clock-history"></i> Recent Results</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th style="color:#000;">Subject</th>
                                <th style="color:#000;">Score</th>
                                <th style="color:#000;">Term</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_results as $row): ?>
                            <tr>
                                <td style="color:#000;"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td style="color:#000;"><?php echo $row['score']; ?>%</td>
                                <td style="color:#000;"><?php echo htmlspecialchars($row['term_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="student/results" class="btn btn-sm btn-outline-primary">View All Results <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php endif; ?>

            <!-- Recent Announcements -->
            <?php if (!empty($announcements)): ?>
            <div class="tile card-custom p-4 mt-3">
                <h5 class="fw-bold" style="color:#000;"><i class="bi bi-megaphone"></i> Recent Announcements</h5>
                <ul class="list-unstyled">
                    <?php foreach ($announcements as $ann): ?>
                    <li class="mb-2">
                        <strong style="color:#000;"><?php echo htmlspecialchars($ann['title']); ?></strong><br>
                        <small class="text-muted" style="color:#6c757d;"><?php echo date('M d, Y', strtotime($ann['create_date'])); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="student/announcements" class="btn btn-sm btn-outline-primary">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Include message handler -->
<?php require_once('const/check-reply.php'); ?>

<style>
    .password-strength {
        height: 5px;
        width: 0%;
        background: #ddd;
        transition: width 0.3s, background 0.3s;
        border-radius: 5px;
        margin-top: 5px;
    }
    .strength-text {
        font-size: 0.75rem;
        margin-top: 3px;
    }
    .input-group-text {
        cursor: pointer;
    }
    .card-custom {
        border: none;
        border-radius: 28px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        background: rgba(255,255,255,0.92) !important;
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255,255,255,0.6);
        padding: 1.8rem !important;
    }
    .form-control {
        background: rgba(255,255,255,0.8);
        border: 1px solid rgba(0,0,0,0.15);
        border-radius: 30px;
        padding: 0.75rem 1.2rem;
        color: #000 !important;
        font-weight: 500;
    }
    .form-control:focus {
        box-shadow: none;
        border-color: #3498db;
        background: rgba(255,255,255,0.95);
    }
    .form-control::placeholder {
        color: rgba(0,0,0,0.4);
    }
    .btn-primary {
        background: #3498db;
        border: none;
        border-radius: 30px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(52,152,219,0.3);
    }
    .btn-outline-primary, .btn-outline-success, .btn-outline-info {
        border-radius: 30px;
        font-weight: 500;
        border-width: 2px;
    }
    .btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-info:hover {
        transform: translateY(-1px);
    }
    .invalid-feedback {
        font-size: 0.8rem;
    }
    hr {
        margin: 1.5rem 0;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #1e293b, #2d3a5e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .app-content {
        background: transparent !important;
    }
    .breadcrumb {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        border-radius: 40px;
        padding: 0.5rem 1rem;
    }
    .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
    .profile-picture {
        border: 3px solid #3498db;
        transition: transform 0.2s;
    }
    .profile-picture:hover {
        transform: scale(1.02);
    }
    .profile-picture-wrapper {
        margin-bottom: 1rem;
    }
    .text-muted {
        color: #6c757d !important;
    }
    .form-label {
        font-weight: 600;
        color: #000;
    }
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0,0,0,0.02);
    }
    .table td, .table th {
        border-color: rgba(0,0,0,0.05);
    }
    .list-unstyled li {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding-bottom: 0.75rem;
    }
    .list-unstyled li:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    @media (max-width: 768px) {
        .card-custom {
            padding: 1.2rem !important;
        }
        .profile-picture-wrapper {
            margin-bottom: 0.5rem;
        }
        .col-md-4, .col-md-8 {
            margin-bottom: 1rem;
        }
    }
</style>

<script>
$(document).ready(function(){
    // Password strength meter
    function checkStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        let width = (strength / 5) * 100;
        let color = '';
        let text = '';
        if (strength <= 2) {
            color = '#dc3545';
            text = 'Weak';
        } else if (strength <= 3) {
            color = '#ffc107';
            text = 'Fair';
        } else if (strength <= 4) {
            color = '#17a2b8';
            text = 'Good';
        } else {
            color = '#28a745';
            text = 'Strong';
        }
        
        $('#strengthBar').css({'width': width+'%', 'background': color});
        $('#strengthText').html(text).css('color', color);
        
        if (password.length > 0 && strength < 4) {
            $('#passwordHelp').show();
        } else {
            $('#passwordHelp').hide();
        }
        return strength >= 4;
    }
    
    // Toggle password visibility
    $('.toggle-password').click(function(){
        let target = $(this).data('target');
        let input = $('#'+target);
        let type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });
    
    // Real-time validation for new password and confirmation
    $('#npass').on('keyup', function() {
        checkStrength($(this).val());
        $('#cnpass').trigger('keyup');
    });
    
    $('#cnpass').on('keyup', function() {
        let newPass = $('#npass').val();
        let confirmPass = $(this).val();
        if (confirmPass !== newPass) {
            $(this).addClass('is-invalid');
            $('#confirmHelp').show();
        } else {
            $(this).removeClass('is-invalid');
            $('#confirmHelp').hide();
        }
    });
    
    // Form submission validation for password form
    $('#passwordForm').on('submit', function(e) {
        let newPass = $('#npass').val();
        let confirmPass = $('#cnpass').val();
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Password Mismatch',
                text: 'The new password and confirmation do not match.',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (!checkStrength(newPass)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Weak Password',
                text: 'Your password does not meet the security requirements. Please use at least 8 characters with uppercase, lowercase, number, and special character.',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        return true;
    });
});
</script>

</body>
</html>