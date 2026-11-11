<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Teacher only (level 2)
if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; // Role‑aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-person-circle me-2"></i>
                My Profile
                <small class="text-muted d-block fs-6 mt-1">View and update your account details</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Profile</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="tile">
                <h3 class="tile-title">Profile Information</h3>
                <div class="tile-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">First Name</label>
                        <input value="<?php echo htmlspecialchars($fname); ?>" disabled name="fname" class="form-control" type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Last Name</label>
                        <input value="<?php echo htmlspecialchars($lname); ?>" disabled name="lname" class="form-control" type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input value="<?php echo htmlspecialchars($email); ?>" disabled name="email" class="form-control" type="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Gender</label>
                        <select disabled class="form-control" name="gender">
                            <option selected disabled value="">Select gender</option>
                            <option <?php if ($gender == "Male") echo 'selected'; ?> value="Male">Male</option>
                            <option <?php if ($gender == "Female") echo 'selected'; ?> value="Female">Female</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="tile">
                <h3 class="tile-title">Change Password</h3>
                <div class="tile-body">
                    <form class="app_frm" action="teacher/core/update_password" method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Password</label>
                            <input type="password" class="form-control" id="cpass" name="cpassword" placeholder="Enter your current password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password</label>
                            <input type="password" class="form-control" id="npass" name="npassword" placeholder="Enter your new password">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Confirm New Password</label>
                            <input type="password" class="form-control" id="cnpass" placeholder="Repeat your new password">
                        </div>
                        <button type="submit" id="sub_btnp" name="submit" value="1" class="btn btn-primary px-4">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
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
<?php require_once('const/check-reply.php'); ?>

<script>
$(document).ready(function() {
    // No sidebar toggle script needed – handled globally by inc/header.php
});
</script>

<style>
/* Profile page specific styles (glass cards) */
.app-content {
    background: transparent !important;
}
.tile, .tile-body {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
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
</style>
</body>
</html>