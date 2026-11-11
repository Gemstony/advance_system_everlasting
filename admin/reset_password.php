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

// ----- Determine admin ID from various possible sources -----
$admin_id = 0;
if (isset($id) && is_numeric($id)) {
    $admin_id = (int)$id;
} elseif (isset($account_id) && is_numeric($account_id)) {
    $admin_id = (int)$account_id;
} elseif (isset($_SESSION['staff_id']) && is_numeric($_SESSION['staff_id'])) {
    $admin_id = (int)$_SESSION['staff_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $admin_id = (int)$_SESSION['user_id'];
}

if (!$admin_id) {
    die("❌ Admin session expired. Please <a href='../logout'>log in again</a>.");
}
// ------------------------------------------------------------

// Handle password reset submission
$resetMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $userType = $_POST['user_type'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Server-side validation
    if ($newPassword !== $confirmPassword) {
        $resetMessage = '<div class="alert alert-danger">❌ Passwords do not match.</div>';
    } elseif (empty($userType) || empty($userId) || empty($newPassword)) {
        $resetMessage = '<div class="alert alert-danger">❌ All fields are required.</div>';
    } else {
        try {
            $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Verify that the user exists before updating
            if ($userType === 'student') {
                $check = $pdo->prepare("SELECT id FROM tbl_students WHERE id = ?");
            } else {
                $check = $pdo->prepare("SELECT id FROM tbl_staff WHERE id = ?");
            }
            $check->execute([$userId]);
            if (!$check->fetch()) {
                throw new Exception("Selected user does not exist.");
            }

            // Hash the new password
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update the correct table
            if ($userType === 'student') {
                $stmt = $pdo->prepare("UPDATE tbl_students SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $userId]);
            }

            // Ensure log table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(20) NOT NULL,
                user_type ENUM('student','teacher','academic') NOT NULL,
                new_password VARCHAR(255) NOT NULL,
                reset_by INT NOT NULL,
                reset_at DATETIME NOT NULL,
                INDEX (user_id)
            )");

            // Log the reset (reset_by now guaranteed to be not null)
            $logStmt = $pdo->prepare("INSERT INTO tbl_password_resets (user_id, user_type, new_password, reset_by, reset_at) VALUES (?, ?, ?, ?, NOW())");
            $logStmt->execute([$userId, $userType, $newPassword, $admin_id]);

            $resetMessage = '<div class="alert alert-success">✅ Password reset successfully. New password: <strong>' . htmlspecialchars($newPassword) . '</strong></div>';
        } catch (PDOException $e) {
            // Check if it's a foreign key or integrity error
            if ($e->errorInfo[1] == 1452) {
                $resetMessage = '<div class="alert alert-danger">❌ Invalid user selected.</div>';
            } else {
                $resetMessage = '<div class="alert alert-danger">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } catch (Exception $e) {
            $resetMessage = '<div class="alert alert-danger">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Fetch users for dropdowns
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $students = $pdo->query("SELECT id, fname, mname, lname FROM tbl_students ORDER BY fname")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $pdo->query("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 ORDER BY fname")->fetchAll(PDO::FETCH_ASSOC);
    $academics = $pdo->query("SELECT id, fname, lname FROM tbl_staff WHERE level = 1 ORDER BY fname")->fetchAll(PDO::FETCH_ASSOC);

    // Recent resets (with admin name)
    $recentResets = $pdo->query("
        SELECT pr.*, 
               CASE 
                   WHEN pr.user_type = 'student' THEN (SELECT CONCAT(fname, ' ', mname, ' ', lname) FROM tbl_students WHERE id = pr.user_id)
                   ELSE (SELECT CONCAT(fname, ' ', lname) FROM tbl_staff WHERE id = pr.user_id)
               END AS user_name,
               (SELECT CONCAT(fname, ' ', lname) FROM tbl_staff WHERE id = pr.reset_by) AS admin_name
        FROM tbl_password_resets pr
        ORDER BY pr.reset_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
        .tile {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            height: 100%;
        }
        .tile:hover {
            box-shadow: 0 25px 40px -10px rgba(0,0,0,0.15);
            transform: translateY(-3px);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 700;
            color: var(--dark);
            padding: 1.5rem 1.5rem 0.5rem;
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: -0.3px;
            border-bottom: 2px solid var(--primary-light);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.3rem;
        }
        .form-control, .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border-radius: 12px;
            border: 1.5px solid #e9ecef;
            padding: 0.6rem 1rem;
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 30px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(67,97,238,0.3);
        }
        .btn-primary:hover {
            background: #3651d4;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(67,97,238,0.4);
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
        .input-group-text {
            cursor: pointer;
            background: white;
            border: 1.5px solid #e9ecef;
            border-radius: 12px;
        }
        .alert {
            border-radius: 12px;
            border-left: 6px solid;
            margin-bottom: 1.5rem;
            padding: 1rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(4px);
        }
        .alert-success {
            background: rgba(6, 214, 160, 0.15);
            border-left-color: var(--success);
            color: #0b5e42;
        }
        .alert-danger {
            background: rgba(239, 71, 111, 0.15);
            border-left-color: var(--danger);
            color: #a11f3a;
        }
        .invalid-feedback {
            display: block;
            font-size: 0.85rem;
            color: var(--danger);
            margin-top: 0.2rem;
        }
        .table th {
            background: var(--primary-light);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            border-radius: 30px;
            padding: 0.4rem 0.8rem;
        }
        .select2-container--default .select2-selection--single {
            height: 38px;
            display: flex;
            align-items: center;
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
        <div><h1>Reset User Password</h1></div>
    </div>

    <?php echo $resetMessage; ?>

    <div class="row g-4">
        <!-- Left column: Reset form -->
        <div class="col-md-5">
            <div class="tile p-4">
                <h4 class="tile-title mb-4">Set New Password</h4>
                <form method="post" action="" id="resetForm">
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person-badge me-1"></i> User Type</label>
                        <select class="form-control select2" name="user_type" id="userType" required style="width:100%">
                            <option value="">-- Select user type --</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="academic">Academic Staff</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person me-1"></i> User</label>
                        <select class="form-control select2" name="user_id" id="userId" required style="width:100%">
                            <option value="">-- Select user type first --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-lock me-1"></i> New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="newPass" required>
                            <span class="input-group-text" onclick="togglePassword('newPass', this)"><i class="bi bi-eye"></i></span>
                        </div>
                        <div class="password-strength mt-2">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="text-muted" id="strengthText">Enter a password</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-lock me-1"></i> Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirmPass" required>
                            <span class="input-group-text" onclick="togglePassword('confirmPass', this)"><i class="bi bi-eye"></i></span>
                        </div>
                        <div class="invalid-feedback" id="matchError"></div>
                    </div>
                    <button type="submit" name="reset" value="1" class="btn btn-primary w-100">Reset Password</button>
                </form>
            </div>
        </div>

        <!-- Right column: Recent resets -->
        <div class="col-md-7">
            <div class="tile p-4">
                <h4 class="tile-title mb-4">Recent Password Resets</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>New Password</th>
                                <th>Reset By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentResets)): ?>
                                <tr><td colspan="5" class="text-center">No resets yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentResets as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['reset_at']); ?></td>
                                    <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($r['user_type']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($r['new_password']); ?></code></td>
                                    <td><?php echo htmlspecialchars($r['admin_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2();

        // Password strength checker
        function checkStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;
            return strength;
        }

        const newPass = document.getElementById('newPass');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const confirmPass = document.getElementById('confirmPass');
        const matchError = document.getElementById('matchError');

        // Update strength on input
        newPass.addEventListener('input', function() {
            const val = this.value;
            const strength = checkStrength(val);
            let percent = 0, color = '', text = '';
            if (val.length === 0) {
                percent = 0; text = 'Enter a password';
            } else if (strength <= 2) {
                percent = 25; color = 'strength-weak'; text = 'Weak';
            } else if (strength <= 3) {
                percent = 50; color = 'strength-medium'; text = 'Medium';
            } else if (strength <= 4) {
                percent = 75; color = 'strength-medium'; text = 'Good';
            } else {
                percent = 100; color = 'strength-strong'; text = 'Strong';
            }
            strengthBar.style.width = percent + '%';
            strengthBar.className = 'strength-bar ' + color;
            strengthText.innerText = 'Password strength: ' + text;
            validateMatch();
        });

        confirmPass.addEventListener('input', validateMatch);

        function validateMatch() {
            if (newPass.value !== confirmPass.value) {
                matchError.innerText = 'Passwords do not match';
            } else {
                matchError.innerText = '';
            }
        }

        // Form submit validation
        $('#resetForm').on('submit', function(e) {
            if (newPass.value !== confirmPass.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Passwords do not match.',
                    timer: 2000,
                    showConfirmButton: false
                });
                return false;
            }
            return true;
        });

        // Dynamic user dropdown
        $('#userType').on('change', function() {
            const type = $(this).val();
            const userIdSelect = $('#userId');
            userIdSelect.empty().append('<option value="">Loading...</option>');

            let users = [];
            if (type === 'student') {
                users = <?php echo json_encode($students); ?>;
            } else if (type === 'teacher') {
                users = <?php echo json_encode($teachers); ?>;
            } else if (type === 'academic') {
                users = <?php echo json_encode($academics); ?>;
            } else {
                userIdSelect.empty().append('<option value="">-- Select user type first --</option>');
                return;
            }

            userIdSelect.empty().append('<option value="">-- Select user --</option>');
            $.each(users, function(i, u) {
                let name;
                if (type === 'student') {
                    name = u.fname + ' ' + (u.mname || '') + ' ' + u.lname;
                } else {
                    name = u.fname + ' ' + u.lname;
                }
                userIdSelect.append('<option value="' + u.id + '">' + name + '</option>');
            });
        });
    });

    // Password visibility toggle
    window.togglePassword = function(fieldId, el) {
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
    };
</script>
</body>
</html>