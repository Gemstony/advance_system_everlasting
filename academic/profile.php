<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// Use $account_id from check_session (it contains the logged-in staff ID)
if (!isset($account_id) || empty($account_id)) {
    die("Unable to identify user. Please log in again.");
}

// Fetch staff details (only columns that exist in tbl_staff)
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT fname, lname, email, gender FROM tbl_staff WHERE id = ?");
    $stmt->execute([$account_id]);
    $staffData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staffData) {
        die("Staff not found.");
    }
    $fname = $staffData['fname'];
    $lname = $staffData['lname'];
    $email = $staffData['email'];
    $gender = $staffData['gender'];
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Prepare staff array for unified header (display_image is not in the table, so set null)
$staff = [
    'id'            => $account_id,
    'fname'         => $fname,
    'lname'         => $lname,
    'display_image' => null,
    'email'         => $email,
    'gender'        => $gender
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* Additional styles – supplements unified header theme */
        .app-content {
            background: transparent !important;
        }
        /* Glass tiles */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(67,97,238,0.3);
        }
        /* Form controls */
        .form-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        .form-control, .form-select {
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 16px;
            padding: 0.7rem 1rem;
            color: #1e293b;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            border: none;
            border-radius: 40px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67,97,238,0.3);
        }
        /* Title – keep white for header consistency */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
        }
        .breadcrumb {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 0.5rem 1rem;
        }
        .breadcrumb-item a, .breadcrumb-item.active {
            color: white !important;
        }
        @media (max-width: 768px) {
            .tile {
                padding: 1.2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-person-circle me-2"></i> My Profile</h1></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <!-- Profile Information -->
                <div class="tile">
                    <h3 class="tile-title"><i class="bi bi-info-circle me-2"></i> Profile Information</h3>
                    <form class="app_frm" action="academic/core/update_profile" method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input value="<?php echo htmlspecialchars($fname); ?>" required name="fname" class="form-control letters-only" type="text" placeholder="Enter first name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input value="<?php echo htmlspecialchars($lname); ?>" required name="lname" class="form-control letters-only" type="text" placeholder="Enter last name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input value="<?php echo htmlspecialchars($email); ?>" required name="email" class="form-control" type="email" placeholder="Enter email address">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select gender</option>
                                <option value="Male" <?php echo ($gender == "Male") ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($gender == "Female") ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <button type="submit" name="submit" value="1" class="btn btn-primary w-100"><i class="bi bi-save me-2"></i> Update Profile</button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Change Password -->
                <div class="tile">
                    <h3 class="tile-title"><i class="bi bi-shield-lock me-2"></i> Change Password</h3>
                    <form class="app_frm" action="academic/core/update_password" method="POST" autocomplete="off" id="passwordForm">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="cpassword" required placeholder="Enter your current password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="npassword" id="npass" required placeholder="Enter your new password">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="cnpass" required placeholder="Repeat your new password">
                        </div>
                        <button type="submit" name="submit" value="1" class="btn btn-primary w-100"><i class="bi bi-key me-2"></i> Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="loader/waitMe.js"></script>
    <script src="js/forms.js"></script>
    <script src="js/sweetalert2@11.js"></script>
    <script>
        // Letters only validation
        document.querySelectorAll('.letters-only').forEach(function(input) {
            input.addEventListener('keypress', function(e) {
                var char = String.fromCharCode(e.which);
                if (!/[a-zA-Z\s]/.test(char)) {
                    e.preventDefault();
                }
            });
        });

        // Confirm password match before submitting change password form
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            var newPass = document.getElementById('npass').value;
            var confirmPass = document.getElementById('cnpass').value;
            if (newPass !== confirmPass) {
                e.preventDefault();
                Swal.fire('Error', 'New password and confirmation do not match.', 'error');
            }
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>