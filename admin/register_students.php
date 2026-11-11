<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    header("location:../");
    exit;
}

// Fetch stats for the header
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total_students = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $active_students = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE status = 1")->fetchColumn();
    $inactive_students = $total_students - $active_students;
    $total_classes = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
} catch (PDOException $e) {
    $total_students = $active_students = $inactive_students = $total_classes = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Register Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <base href="../">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --dark: #1e293b;
            --light-bg: #f8fafc;
            --border-radius: 20px;
            --card-shadow: 0 20px 35px -8px rgba(0,0,0,0.1), 0 10px 15px -6px rgba(0,0,0,0.05);
            --transition: all 0.2s ease;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: 1px solid rgba(255, 255, 255, 0.3);
        }
        body {
            background: linear-gradient(145deg, #f0f4fa 0%, #e6ecf4 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        .app-content { background: transparent; }
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 40px -10px rgba(0,0,0,0.15);
            background: rgba(255,255,255,0.95);
        }
        .stat-icon {
            font-size: 2.2rem;
            color: var(--primary);
            background: var(--primary-light);
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--dark);
        }
        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .tile {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            padding: 1.5rem;
            height: 100%;
        }
        .tile-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-glass {
            border-radius: 40px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            background: white;
            color: var(--dark);
        }
        .btn-glass-primary { background: var(--primary); color: white; }
        .btn-glass-primary:hover { background: #3451d1; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .btn-glass-success { background: var(--success); color: white; }
        .btn-glass-success:hover { background: #05b588; transform: translateY(-2px); }
        .btn-glass-warning { background: var(--warning); color: white; }
        .btn-glass-warning:hover { background: #e6a502; transform: translateY(-2px); }
        .btn-glass-secondary { background: #64748b; color: white; }
        .btn-glass-secondary:hover { background: #4b5565; transform: translateY(-2px); }
        .btn-glass-info { background: #0ea5e9; color: white; }
        .btn-glass-info:hover { background: #0c8ac9; transform: translateY(-2px); }
        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        .form-control, .select2-container--default .select2-selection--single, .form-select {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
            transition: var(--transition);
            height: auto;
        }
        .form-control:focus, .select2-container--default.select2-container--focus .select2-selection--single, .form-select:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
            outline: none;
        }
        .input-group-text {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px;
            cursor: pointer;
        }
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }
        .input-group .input-group-text {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .password-strength {
            height: 6px;
            border-radius: 10px;
            background: #e9ecef;
            margin-top: 8px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        .strength-weak { background: var(--danger); }
        .strength-medium { background: var(--warning); }
        .strength-strong { background: var(--success); }
        .invalid-feedback {
            display: block;
            font-size: 0.85rem;
            color: var(--danger);
            margin-top: 0.2rem;
        }
        .image-preview {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--card-shadow);
            margin-top: 0.5rem;
        }
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        /* Search section styles */
        .search-result-card {
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
            transition: 0.2s;
            border-radius: 16px;
        }
        .search-result-card:hover {
            background: rgba(67, 97, 238, 0.08);
            transform: translateX(4px);
        }
        .search-loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 0.5rem;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Modal styling */
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 32px;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .student-detail-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
        }
        .student-detail-label {
            width: 140px;
            font-weight: 600;
            color: #5b6e8c;
        }
        .student-detail-value {
            flex: 1;
            color: var(--dark);
        }
        @media (max-width: 768px) {
            .col-md-5, .col-md-7 { margin-bottom: 1.5rem; }
            .student-detail-row {
                flex-direction: column;
            }
            .student-detail-label {
                width: 100%;
                margin-bottom: 0.2rem;
            }
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-person-plus me-2"></i> Register Students</h1></div>
        <div class="quick-actions">
            <a href="admin/import_students" class="btn btn-glass btn-glass-success"><i class="bi bi-upload me-1"></i> Import</a>
            <a href="admin/manage_students" class="btn btn-glass btn-glass-warning"><i class="bi bi-pencil-square me-1"></i> Manage</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success); background: rgba(6,214,160,0.1);"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?php echo $active_students; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--danger); background: rgba(239,71,111,0.1);"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="stat-value"><?php echo $inactive_students; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--warning); background: rgba(255,183,3,0.1);"><i class="bi bi-building"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Classes</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Two-column layout -->
    <div class="row g-4">
        <!-- Left: Registration Form -->
        <div class="col-md-7">
            <div class="tile">
                <h3 class="tile-title"><i class="bi bi-pencil-square me-2"></i> Student Registration</h3>
                <form enctype="multipart/form-data" action="admin/core/new_student" class="app_frm" method="POST" autocomplete="OFF" id="registerForm">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-person-vcard me-1"></i> Registration Number</label>
                            <div class="input-group">
                                <input name="regno" required class="form-control" type="text" placeholder="e.g. E001-0001" id="regno">
                                <button type="button" class="btn btn-glass btn-glass-secondary" onclick="generateRegNo()" title="Generate next available number"><i class="bi bi-magic"></i></button>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> You can enter manually or generate automatically.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-envelope me-1"></i> Email</label>
                            <input name="email" required class="form-control" type="email" placeholder="student@example.com" id="email">
                            <div class="invalid-feedback" id="emailError"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> First Name</label>
                            <input name="fname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="First">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> Middle Name</label>
                            <input name="mname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Middle">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> Last Name</label>
                            <input name="lname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Last">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-gender-ambiguous me-1"></i> Gender</label>
                            <select class="form-select" name="gender" required>
                                <option selected disabled value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-building me-1"></i> Class</label>
                            <select class="form-select select2" name="class" required style="width: 100%;">
                                <option value="" selected disabled> Select One</option>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option disabled>Error loading classes</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-lock me-1"></i> Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="npass" name="password" placeholder="Enter password" required>
                                <button class="btn btn-glass btn-glass-secondary" type="button" onclick="generatePassword()" title="Generate strong password"><i class="bi bi-key"></i></button>
                                <span class="input-group-text" onclick="togglePassword('npass', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="password-strength mt-2">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <small class="text-muted" id="strengthText">Enter a password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-lock me-1"></i> Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="cnpass" placeholder="Confirm password" required>
                                <span class="input-group-text" onclick="togglePassword('cnpass', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" id="matchError"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-image me-1"></i> Display Image (Optional)</label>
                        <input name="image" class="form-control" type="file" accept=".png, .jpg, .jpeg" id="imageInput">
                        <div class="mt-2 d-flex align-items-center">
                            <img id="imagePreview" class="image-preview d-none" src="#" alt="Preview">
                            <small class="text-muted ms-2"><i class="bi bi-info-circle"></i> Allowed: PNG, JPG, JPEG (max 2MB)</small>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button id="sub_btnp2" class="btn btn-glass btn-glass-primary btn-lg" type="submit">
                            <i class="bi bi-person-plus me-1"></i> Register Student
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Student Search Panel -->
        <div class="col-md-5">
            <div class="tile">
                <h3 class="tile-title"><i class="bi bi-search me-2"></i> Search Students</h3>
                <div class="mb-3">
                    <label class="form-label">Find existing student</label>
                    <div class="input-group">
                        <input type="text" id="searchStudentInput" class="form-control" placeholder="Name or registration number..." autocomplete="off">
                        <button class="btn btn-glass btn-glass-primary" id="searchStudentBtn" type="button"><i class="bi bi-search"></i> Search</button>
                    </div>
                    <div id="searchLoading" class="search-loading" style="display: none;"></div>
                </div>
                <div id="searchResultsContainer">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-info-circle-fill fs-1"></i>
                        <p class="mt-2">Start typing or click Search to find a student</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- View Student Modal (Read-Only) -->
<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i> Student Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewStudentContent">
                <!-- dynamic content will be inserted here -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass btn-glass-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
    $('.select2').select2({ width: '100%' });

    // Password toggle
    function togglePassword(fieldId, el) {
        const field = document.getElementById(fieldId);
        const icon = el.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    // Password strength
    const npass = document.getElementById('npass');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchError = document.getElementById('matchError');
    const cnpass = document.getElementById('cnpass');
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('emailError');

    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength += 1;
        if (password.match(/[a-z]+/)) strength += 1;
        if (password.match(/[A-Z]+/)) strength += 1;
        if (password.match(/[0-9]+/)) strength += 1;
        if (password.match(/[$@#&!]+/)) strength += 1;
        return strength;
    }

    npass.addEventListener('input', function() {
        const val = this.value;
        const strength = checkPasswordStrength(val);
        let percent = 0;
        let colorClass = '';
        let text = '';
        if (val.length === 0) {
            percent = 0;
            text = 'Enter a password';
        } else if (strength <= 2) {
            percent = 25;
            colorClass = 'strength-weak';
            text = 'Weak';
        } else if (strength <= 3) {
            percent = 50;
            colorClass = 'strength-medium';
            text = 'Medium';
        } else if (strength <= 4) {
            percent = 75;
            colorClass = 'strength-medium';
            text = 'Good';
        } else {
            percent = 100;
            colorClass = 'strength-strong';
            text = 'Strong';
        }
        strengthBar.style.width = percent + '%';
        strengthBar.className = 'strength-bar ' + colorClass;
        strengthText.innerText = 'Password strength: ' + text;
        validateMatch();
    });

    function validateMatch() {
        if (npass.value !== cnpass.value) {
            matchError.innerText = 'Passwords do not match';
        } else {
            matchError.innerText = '';
        }
    }
    cnpass.addEventListener('input', validateMatch);

    // Email validation
    emailInput.addEventListener('input', function() {
        const email = this.value;
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email && !regex.test(email)) {
            emailError.innerText = 'Please enter a valid email address';
        } else {
            emailError.innerText = '';
        }
    });

    // Form submit validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (npass.value !== cnpass.value) {
            e.preventDefault();
            Swal.fire('Error', 'Passwords do not match', 'error');
            return;
        }
        if (emailInput.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
            e.preventDefault();
            Swal.fire('Error', 'Please enter a valid email address', 'error');
            return;
        }
    });

    // Image preview
    document.getElementById('imageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.src = event.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    });

    // Generate random password
    function generatePassword() {
        const length = 12;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
        let password = "";
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        npass.value = password;
        cnpass.value = password;
        npass.dispatchEvent(new Event('input'));
        cnpass.dispatchEvent(new Event('input'));
    }

    // Generate registration number via AJAX
    function generateRegNo() {
        fetch('admin/core/get_next_student_id.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('regno').value = data.next_id;
                } else {
                    Swal.fire('Error', 'Could not generate registration number', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Network error', 'error'));
    }

    // ----- SEARCH STUDENT FUNCTIONALITY (with View button) -----
    const $searchInput = $('#searchStudentInput');
    const $searchBtn = $('#searchStudentBtn');
    const $searchLoading = $('#searchLoading');
    const $resultsContainer = $('#searchResultsContainer');

    let searchTimeout;

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
    }

    function displayViewModal(student) {
        const fullName = `${student.fname} ${student.mname ? student.mname+' ' : ''}${student.lname}`;
        const statusBadge = student.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
        let imageHtml = '';
        if (student.display_image && student.display_image !== 'Blank' && student.display_image !== 'DEFAULT') {
            imageHtml = `<img src="images/${student.display_image}" class="rounded-circle" width="80" height="80" style="object-fit: cover;">`;
        } else {
            imageHtml = `<div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:80px;height:80px;"><i class="bi bi-person fs-1 text-secondary"></i></div>`;
        }
        
        const content = `
            <div class="row">
                <div class="col-md-3 text-center mb-3">
                    ${imageHtml}
                </div>
                <div class="col-md-9">
                    <div class="student-detail-row">
                        <div class="student-detail-label">Registration No:</div>
                        <div class="student-detail-value"><strong>${escapeHtml(student.id)}</strong></div>
                    </div>
                    <div class="student-detail-row">
                        <div class="student-detail-label">Full Name:</div>
                        <div class="student-detail-value">${escapeHtml(fullName)}</div>
                    </div>
                    <div class="student-detail-row">
                        <div class="student-detail-label">Email:</div>
                        <div class="student-detail-value">${escapeHtml(student.email)}</div>
                    </div>
                    <div class="student-detail-row">
                        <div class="student-detail-label">Gender:</div>
                        <div class="student-detail-value">${escapeHtml(student.gender)}</div>
                    </div>
                    <div class="student-detail-row">
                        <div class="student-detail-label">Class:</div>
                        <div class="student-detail-value">${escapeHtml(student.class_name || '—')}</div>
                    </div>
                    <div class="student-detail-row">
                        <div class="student-detail-label">Status:</div>
                        <div class="student-detail-value">${statusBadge}</div>
                    </div>
                </div>
            </div>
        `;
        $('#viewStudentContent').html(content);
        $('#viewStudentModal').modal('show');
    }

    function performStudentSearch(query) {
        if (!query.trim()) {
            $resultsContainer.html('<div class="text-center text-muted py-4"><i class="bi bi-info-circle-fill fs-1"></i><p class="mt-2">Enter a name or registration number to search</p></div>');
            return;
        }
        $searchLoading.show();
        $.ajax({
            url: 'admin/core/search_students_ajax.php',
            type: 'GET',
            data: { search: query, limit: 10 },
            dataType: 'json',
            success: function(res) {
                $searchLoading.hide();
                if (res.success && res.students.length > 0) {
                    let html = '<div class="list-group">';
                    res.students.forEach(s => {
                        let statusBadge = s.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                        let fullName = `${s.fname} ${s.mname ? s.mname+' ' : ''}${s.lname}`;
                        html += `
                            <div class="list-group-item list-group-item-action search-result-card mb-2 rounded-3 p-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><strong>${escapeHtml(s.id)}</strong> – ${escapeHtml(fullName)}</h6>
                                        <small class="text-muted"><i class="bi bi-building"></i> ${escapeHtml(s.class_name || '—')} &nbsp;|&nbsp; <i class="bi bi-gender-ambiguous"></i> ${escapeHtml(s.gender)}</small><br>
                                        <small><i class="bi bi-envelope"></i> ${escapeHtml(s.email)}</small>
                                        <div class="mt-2">${statusBadge}</div>
                                    </div>
                                    <div class="ms-2 mt-2 mt-sm-0">
                                        <button class="btn btn-sm btn-glass-info view-student-btn" data-student='${JSON.stringify(s)}'><i class="bi bi-eye"></i> View</button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $resultsContainer.html(html);
                    
                    // Attach event listeners to the newly created View buttons
                    $('.view-student-btn').click(function() {
                        const student = $(this).data('student');
                        displayViewModal(student);
                    });
                } else {
                    $resultsContainer.html('<div class="text-center text-muted py-4"><i class="bi bi-search"></i><p class="mt-2">No students found.</p></div>');
                }
            },
            error: function() {
                $searchLoading.hide();
                $resultsContainer.html('<div class="text-center text-danger py-4">Error searching. Please try again.</div>');
            }
        });
    }

    $searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => performStudentSearch(query), 500);
        } else if (query.length === 0) {
            $resultsContainer.html('<div class="text-center text-muted py-4"><i class="bi bi-info-circle-fill fs-1"></i><p class="mt-2">Start typing or click Search to find a student</p></div>');
        } else {
            $resultsContainer.html('<div class="text-center text-muted py-4">Type at least 2 characters...</div>');
        }
    });

    $searchBtn.click(() => performStudentSearch($searchInput.val().trim()));
</script>
</body>
</html>