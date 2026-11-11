<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res == "1" && $level == "0") {} else { header("location:../"); }

// Determine current logo path for preview
$logoPath = 'images/logo/' . WBLogo;
$logoFullPath = __DIR__ . '/../' . $logoPath;
$logoExists = file_exists($logoFullPath) && !empty(WBLogo);

// Fetch counts and system states
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $academic_count = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1")->fetchColumn();
    $teacher_count = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
    $student_count = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();

    // Check if maintenance_mode column exists, get current state
    $maintenance_mode = 0;
    $check = $pdo->query("SHOW COLUMNS FROM tbl_school LIKE 'maintenance_mode'");
    if ($check->rowCount() > 0) {
        $maintenance_mode = $pdo->query("SELECT maintenance_mode FROM tbl_school WHERE id = 1")->fetchColumn();
    }

    // Check if allow_results column exists, get current state
    $allow_results = 1; // default if column missing
    $check = $pdo->query("SHOW COLUMNS FROM tbl_school LIKE 'allow_results'");
    if ($check->rowCount() > 0) {
        $allow_results = $pdo->query("SELECT allow_results FROM tbl_school WHERE id = 1")->fetchColumn();
    }
} catch (Exception $e) {
    $academic_count = $teacher_count = $student_count = 0;
    $maintenance_mode = 0;
    $allow_results = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - System Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --info: #4cc9f0;
            --dark: #1e293b;
            --light-bg: #f8fafc;
            --border-radius: 24px;
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
            border-radius: var(--border-radius);
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto 2rem auto;
        }
        .tile:hover {
            box-shadow: 0 25px 40px -10px rgba(0,0,0,0.15);
            background: rgba(255,255,255,0.95);
        }
        .tile-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-light);
        }
        .tile-header i {
            font-size: 1.8rem;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.5rem;
            border-radius: 16px;
        }
        .tile-header h3 {
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .control-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.75rem 1.2rem;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            border-color: var(--primary);
            background: white;
        }
        .form-control[type="file"] {
            padding: 0.5rem 1rem;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 40px;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -8px rgba(67,97,238,0.5);
            background: #3a56d4;
        }
        .btn-danger {
            background: var(--danger);
            border: none;
            border-radius: 40px;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 8px 16px -6px rgba(239,71,111,0.4);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -8px rgba(239,71,111,0.5);
            background: #e14c6e;
        }
        .btn-outline-secondary {
            border-radius: 40px;
            padding: 0.4rem 1.2rem;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(4px);
        }
        .btn-info {
            background: var(--info);
            border: none;
            border-radius: 40px;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 8px 16px -6px rgba(76,201,240,0.4);
        }
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -8px rgba(76,201,240,0.5);
            background: #3db8e0;
        }
        .btn-success {
            background: var(--success);
            border: none;
            border-radius: 40px;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 8px 16px -6px rgba(6,214,160,0.4);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -8px rgba(6,214,160,0.5);
            background: #05c098;
        }
        .logo-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px dashed rgba(0,0,0,0.1);
        }
        .logo-preview img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 12px;
            object-fit: contain;
            background: white;
            padding: 0.25rem;
        }
        .logo-preview span {
            color: var(--dark);
            font-size: 0.9rem;
        }
        .app-title h1 {
            font-weight: 700;
            color: var(--dark);
        }
        .reset-options {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .reset-option {
            flex: 1 1 200px;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.5);
            transition: var(--transition);
        }
        .reset-option:hover {
            background: rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }
        .reset-option .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            margin-right: 0.5rem;
        }
        .reset-option .count-badge {
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 40px;
            padding: 0.2rem 0.8rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        hr {
            margin: 2rem 0;
            border: none;
            border-top: 2px dashed rgba(0,0,0,0.1);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .info-item {
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(4px);
            border-radius: 16px;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.5);
        }
        .info-item strong {
            color: var(--dark);
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }
        .info-item span {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 56px;
            height: 28px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--success);
        }
        input:checked + .slider:before {
            transform: translateX(28px);
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
        <div><h1>System Settings</h1></div>
    </div>

    <!-- School Information Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-building"></i>
                    <h3>School Information</h3>
                </div>
                <div class="tile-body">
                    <!-- Fixed: action now includes .php extension -->
                    <form class="app_frm" method="POST" enctype="multipart/form-data" autocomplete="OFF" action="admin/core/update_system.php">
                        <div class="form-group">
                            <label class="control-label"><i class="bi bi-building me-2"></i>School Name</label>
                            <input required type="text" name="name" value="<?php echo htmlspecialchars(WBName); ?>" class="form-control" placeholder="Enter School Name">
                        </div>

                        <div class="form-group">
                            <label class="control-label"><i class="bi bi-image me-2"></i>School Logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <div class="form-text text-muted small mt-2">Recommended size: 200x200px. PNG or JPG.</div>
                            <?php if ($logoExists): ?>
                                <div class="logo-preview">
                                    <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Current Logo">
                                    <span>Current logo: <strong><?php echo htmlspecialchars(WBLogo); ?></strong></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="old_logo" value="<?php echo htmlspecialchars(WBLogo); ?>">

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="submit" value="1" class="btn btn-primary px-5">
                                <i class="bi bi-check-circle me-2"></i>Update Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-cpu"></i>
                    <h3>System Information</h3>
                </div>
                <div class="tile-body">
                    <div class="info-grid" id="systemInfo">
                        <div class="info-item"><strong>PHP Version</strong><span id="phpVersion">Loading...</span></div>
                        <div class="info-item"><strong>MySQL Version</strong><span id="mysqlVersion">Loading...</span></div>
                        <div class="info-item"><strong>Server Software</strong><span id="serverSoftware">Loading...</span></div>
                        <div class="info-item"><strong>HTTP Host</strong><span id="httpHost">Loading...</span></div>
                        <div class="info-item"><strong>Document Root</strong><span id="documentRoot">Loading...</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-shield-lock"></i>
                    <h3>Maintenance Mode</h3>
                </div>
                <div class="tile-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1">When maintenance mode is enabled, students and teachers cannot access the system. Only administrators can log in.</p>
                            <small class="text-muted">Current status: <strong id="maintenanceStatus"><?php echo $maintenance_mode ? 'Enabled' : 'Disabled'; ?></strong></small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="maintenanceToggle" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Visibility Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-eye"></i>
                    <h3>Results Visibility</h3>
                </div>
                <div class="tile-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1">When enabled, students can view their examination results. Disable this during result processing or maintenance.</p>
                            <small class="text-muted">Current status: <strong id="resultsStatus"><?php echo $allow_results ? 'Enabled' : 'Disabled'; ?></strong></small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="resultsToggle" <?php echo $allow_results ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Backup Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-database"></i>
                    <h3>Database Backup</h3>
                </div>
                <div class="tile-body">
                    <p>Download a complete SQL dump of the current database. This can be used to restore the system if needed.</p>
                    <a href="admin/core/backup_database.php" class="btn btn-success px-5" download>
                        <i class="bi bi-download me-2"></i>Download Backup
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Reset Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-header">
                    <i class="bi bi-exclamation-triangle-fill" style="color: var(--danger);"></i>
                    <h3>Reset Data</h3>
                </div>
                <div class="tile-body">
                    <p class="text-muted mb-3">Select categories to reset. <strong class="text-danger">This action cannot be undone.</strong> Admin accounts will remain untouched.</p>

                    <div class="reset-options">
                        <div class="reset-option">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="academics" id="resetAcademics">
                                <label class="form-check-label" for="resetAcademics">
                                    Academics <span class="count-badge"><?php echo $academic_count; ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="reset-option">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="teachers" id="resetTeachers">
                                <label class="form-check-label" for="resetTeachers">
                                    Teachers <span class="count-badge"><?php echo $teacher_count; ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="reset-option">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="students" id="resetStudents">
                                <label class="form-check-label" for="resetStudents">
                                    Students <span class="count-badge"><?php echo $student_count; ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="reset-option">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="all_accounts" id="resetAll">
                                <label class="form-check-label" for="resetAll">
                                    All Non-Admin Accounts
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" id="resetDataBtn" class="btn btn-danger px-5">
                            <i class="bi bi-trash me-2"></i>Reset Selected
                        </button>
                    </div>
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
<script>
$(document).ready(function() {
    // Load system info
    $.ajax({
        url: 'admin/core/get_system_info.php',
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                $('#phpVersion').text(res.info.php_version);
                $('#mysqlVersion').text(res.info.mysql_version);
                $('#serverSoftware').text(res.info.server_software);
                $('#httpHost').text(res.info.http_host);
                $('#documentRoot').text(res.info.document_root);
            } else {
                $('#systemInfo').html('<div class="alert alert-warning">Could not load system info.</div>');
            }
        },
        error: function() {
            $('#systemInfo').html('<div class="alert alert-danger">Error loading system info.</div>');
        }
    });

    // Maintenance toggle
    $('#maintenanceToggle').on('change', function() {
        var state = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: 'admin/core/toggle_maintenance.php',
            type: 'POST',
            data: { state: state },
            dataType: 'json',
            beforeSend: function() {
                $('#maintenanceToggle').prop('disabled', true);
            },
            success: function(res) {
                if (res.success) {
                    $('#maintenanceStatus').text(state ? 'Enabled' : 'Disabled');
                    Swal.fire({
                        icon: 'success',
                        title: 'Maintenance mode ' + (state ? 'enabled' : 'disabled'),
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // If maintenance was disabled, ask about enabling results
                    if (state === 0) {
                        Swal.fire({
                            icon: 'question',
                            title: 'Enable Results Visibility?',
                            text: 'Maintenance mode is off. Would you like to enable results visibility for students now?',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, enable results',
                            cancelButtonText: 'No, keep disabled'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Trigger results toggle to ON
                                if (!$('#resultsToggle').is(':checked')) {
                                    $('#resultsToggle').prop('checked', true).trigger('change');
                                }
                            }
                        });
                    }
                } else {
                    Swal.fire('Error', res.message, 'error');
                    $('#maintenanceToggle').prop('checked', !state);
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error.', 'error');
                $('#maintenanceToggle').prop('checked', !state);
            },
            complete: function() {
                $('#maintenanceToggle').prop('disabled', false);
            }
        });
    });

    // Results visibility toggle
    $('#resultsToggle').on('change', function() {
        var state = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: 'admin/core/toggle_results.php',
            type: 'POST',
            data: { state: state },
            dataType: 'json',
            beforeSend: function() {
                $('#resultsToggle').prop('disabled', true);
            },
            success: function(res) {
                if (res.success) {
                    $('#resultsStatus').text(state ? 'Enabled' : 'Disabled');
                    Swal.fire({
                        icon: 'success',
                        title: 'Results visibility ' + (state ? 'enabled' : 'disabled'),
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    $('#resultsToggle').prop('checked', !state);
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error.', 'error');
                $('#resultsToggle').prop('checked', !state);
            },
            complete: function() {
                $('#resultsToggle').prop('disabled', false);
            }
        });
    });

    // Data reset checkbox logic
    $('#resetAll').on('change', function() {
        if ($(this).is(':checked')) {
            $('#resetAcademics, #resetTeachers, #resetStudents').prop('checked', false).prop('disabled', true);
        } else {
            $('#resetAcademics, #resetTeachers, #resetStudents').prop('disabled', false);
        }
    });

    $('#resetAcademics, #resetTeachers, #resetStudents').on('change', function() {
        if ($('#resetAll').is(':checked')) {
            $('#resetAll').prop('checked', false).trigger('change');
        }
    });

    // Reset button
    $('#resetDataBtn').on('click', function() {
        var selected = [];
        if ($('#resetAcademics').is(':checked')) selected.push('academics');
        if ($('#resetTeachers').is(':checked')) selected.push('teachers');
        if ($('#resetStudents').is(':checked')) selected.push('students');
        if ($('#resetAll').is(':checked')) selected.push('all_accounts');

        if (selected.length === 0) {
            Swal.fire('Info', 'Please select at least one category to reset.', 'info');
            return;
        }

        Swal.fire({
            title: 'Are you absolutely sure?',
            html: `You are about to reset <strong>${selected.join(', ')}</strong>. This will permanently delete all related data.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            confirmButtonText: 'Yes, reset data',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Resetting...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'admin/core/reset_data.php',
                    type: 'POST',
                    data: { categories: selected },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: res.message,
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error. Check logs.', 'error');
                    }
                });
            }
        });
    });
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>