<?php
// accountant/settings.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('student/inc/finance_functions.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$accountant = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'email' => $email
];

$pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_value = $_POST['fee_module_enabled'] ?? '0';
    $stmt = $pdo->prepare("UPDATE tbl_system_settings SET setting_value = ? WHERE setting_key = 'fee_module_enabled'");
    $stmt->execute([$new_value]);
    $success = true;
}

$stmt = $pdo->prepare("SELECT setting_value FROM tbl_system_settings WHERE setting_key = 'fee_module_enabled'");
$stmt->execute();
$enabled = $stmt->fetchColumn();
?>
<?php require 'accountant/inc/header.php'; ?>
<?php require 'accountant/inc/sidebar.php'; ?>
<main class="app-content">
    <div class="tile">
        <h4>Fee Module Settings</h4>
        <form method="post">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="fee_module_enabled" value="1" <?php if($enabled=='1') echo 'checked'; ?> id="feeToggle">
                <label class="form-check-label" for="feeToggle">Show fee information to students</label>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <?php if(isset($success)) echo '<div class="alert alert-success mt-3">Settings updated.</div>'; ?>
        </form>
    </div>
</main>
<?php require 'accountant/inc/footer.php'; ?>