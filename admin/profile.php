<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/academic_dashboard.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
  <title>SRMS - My Profile</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="../">
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="icon" href="images/icon.ico">
  <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
  <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header">
  <a class="app-header__logo" href="javascript:void(0);">SRMS</a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

  <ul class="app-nav">
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
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
    <div>
      <h1>My Profile</h1>
    </div>
  </div>

  <div class="row">
    <div class="tile">
      <h3 class="tile-title">Profile Information</h3>
      <div class="tile-body">
        <form class="app_frm" action="admin/core/update_profile" method="POST" autocomplete="off">

          <div class="mb-2">
            <label class="form-label">First Name</label>
            <input value="<?php echo $fname; ?>" required name="fname" onkeypress="return lettersOnly(event)" class="form-control" type="text" placeholder="Enter first name">
          </div>

          <div class="mb-2">
            <label class="form-label">Last Name</label>
            <input value="<?php echo $lname; ?>" required name="lname" onkeypress="return lettersOnly(event)" class="form-control" type="text" placeholder="Enter last name">
          </div>

          <div class="mb-2">
            <label class="form-label">Email Address</label>
            <input value="<?php echo $email; ?>" required name="email" class="form-control" type="email" placeholder="Enter email address">
          </div>

          <div class="mb-3">
            <label class="form-label">Gender</label>
            <select class="form-control" name="gender" required>
              <option selected disabled value="">Select gender</option>
              <option <?php if ($gender == "Male") { print ' selected '; } ?> value="Male">Male</option>
              <option <?php if ($gender == "Female") { print ' selected '; } ?> value="Female">Female</option>
            </select>
          </div>

          <button type="submit"  name="submit" value="1" class="btn btn-primary">Update Profile</button>

        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="tile">
      <h3 class="tile-title">Change Password</h3>
      <div class="tile-body">

        <!-- NOTE: confirm input now has name="cnpassword" (was missing before) -->
        <form class="app_frm" action="admin/core/update_password" method="POST" autocomplete="off" id="passwordForm">

          <div class="mb-2">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" id="cpass" name="cpassword" placeholder="Enter your current password" required>
          </div>

          <div class="mb-2">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" id="npass" name="npassword" placeholder="Enter your new password" minlength="8" required>
            <small class="form-text text-muted">Minimum 8 characters recommended.</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="cnpass" name="cnpassword" placeholder="Repeat your new password" required>
          </div>

          <button type="submit" id="sub_btnp" name="submit" value="1" class="btn btn-primary">Change Password</button>

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

<?php require_once('const/check-reply.php'); ?>

<script>
  // Client-side validation for password change to prevent common mistakes
  (function($){
    $(document).ready(function(){
      $('#passwordForm').on('submit', function(e){
        var current = $('#cpass').val().trim();
        var npass = $('#npass').val();
        var cnpass = $('#cnpass').val();

        // Basic checks before submitting to server
        if (!current) {
          e.preventDefault();
          Swal.fire({ icon: 'warning', title: 'Current password required', text: 'Please enter your current password.' });
          return false;
        }

        if (npass.length < 8) {
          e.preventDefault();
          Swal.fire({ icon: 'warning', title: 'Weak password', text: 'New password must be at least 8 characters long.' });
          return false;
        }

        if (npass !== cnpass) {
          e.preventDefault();
          Swal.fire({ icon: 'error', title: 'Passwords do not match', text: 'Please make sure the new password and confirmation match.' });
          return false;
        }

        // Optionally disable submit button to avoid double submits
        $('#sub_btnp').prop('disabled', true).text('Processing...');
        return true;
      });
    });
  })(jQuery);
</script>

</body>
</html>
