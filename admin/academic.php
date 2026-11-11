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

// Fetch initial stats and academic staff
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $total_academic = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1")->fetchColumn();
    $active_academic = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1 AND status = 1")->fetchColumn();
    $inactive_academic = $total_academic - $active_academic;
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_staff WHERE level = 1 ORDER BY id DESC");
    $stmt->execute();
    $academics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $total_academic = $active_academic = $inactive_academic = 0;
    $academics = [];
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Academic Account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <style>
        /* All existing styles remain exactly the same */
        :root {
            --primary: #4361ee;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --light-bg: #f8fafc;
            --border-radius: 16px;
            --card-shadow: 0 20px 35px -8px rgba(0,0,0,0.1), 0 10px 15px -6px rgba(0,0,0,0.05);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.4);
        }
        body {
            background: linear-gradient(145deg, #f0f4fa 0%, #e6ecf4 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content { background: transparent; }
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: all 0.2s ease;
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
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            color: #1e293b;
        }
        .stat-label {
            color: #5b6e8c;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }
        .tile {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: all 0.2s ease;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .tile::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--border-radius);
            padding: 2px;
            background: linear-gradient(135deg, #4361ee, #06d6a0, #ef476f, #ffb703);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            animation: gradientShift 6s ease infinite;
            background-size: 200% 200%;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .tile-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .btn-add {
            background: linear-gradient(135deg, var(--primary), #3651c7);
            color: white;
            border-radius: 40px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
            box-shadow: 0 8px 15px rgba(67,97,238,0.3);
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(67,97,238,0.4);
        }
        .btn-sm-custom {
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 40px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ef476f;
            transition: 0.3s;
            border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #06d6a0;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .status-label {
            font-size: 0.7rem;
            margin-left: 0.5rem;
            vertical-align: middle;
        }
        .table thead th {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            border-bottom: 2px solid rgba(255,255,255,0.8);
        }
        .table tbody tr {
            transition: 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(255,255,255,0.6);
            transform: scale(1.01);
        }
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 32px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .form-label {
            font-weight: 600;
            color: #334155;
        }
        .form-control, .select2-container--default .select2-selection--single {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
        }
        .form-control:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
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
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px;
            cursor: pointer;
        }
        .invalid-feedback {
            display: block;
            font-size: 0.85rem;
            color: var(--danger);
        }
        .dt-buttons .btn {
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
            margin-right: 0.3rem;
            background: white;
            border: 1px solid #dee2e6;
            color: #495057;
        }
        .dt-buttons .btn:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .stat-card { padding: 0.9rem; }
            .stat-icon { width: 44px; height: 44px; font-size: 1.4rem; }
            .stat-value { font-size: 1.4rem; }
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-person-workspace me-2" style="color: var(--primary);"></i> Academic Account</h1></div>
        <div>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAcademicModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Academic
            </button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats Cards (IDs added for dynamic update) -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--primary); background: rgba(67,97,238,0.12);"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-value" id="statTotal"><?php echo $total_academic; ?></div>
                    <div class="stat-label">Total Academic</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success); background: rgba(6,214,160,0.12);"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-value" id="statActive"><?php echo $active_academic; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--danger); background: rgba(239,71,111,0.12);"><i class="bi bi-x-circle-fill"></i></div>
                <div>
                    <div class="stat-value" id="statInactive"><?php echo $inactive_academic; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Staff List Tile -->
    <div class="tile">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h3 class="tile-title mb-0"><i class="bi bi-table me-2"></i> Academic Staff List</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="academicTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($academics)): ?>
                        <tr><td colspan="7" class="text-center">No academic staff found.15+</td>15+
                    <?php else: ?>
                        <?php $i=1; foreach ($academics as $row): ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><i class="bi bi-person-circle me-1 text-primary"></i> <?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
                            <td><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($row['email']); ?></td>
                            <td><i class="bi bi-gender-<?php echo strtolower($row['gender']) == 'male' ? 'male' : 'female'; ?> me-1"></i> <?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" class="status-toggle" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?>" <?php echo ($row['status'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="status-label"><?php echo ($row['status'] ?? 1) == 1 ? 'Active' : 'Blocked'; ?></span>
                            </td>
                            <td>
                                <textarea style="display:none;" id="fname_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['fname']); ?></textarea>
                                <textarea style="display:none;" id="lname_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['lname']); ?></textarea>
                                <textarea style="display:none;" id="email_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['email']); ?></textarea>
                                <textarea style="display:none;" id="phone_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></textarea>
                                
                                <button onclick="editAcademic('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['fname']); ?>', '<?php echo htmlspecialchars($row['lname']); ?>', '<?php echo htmlspecialchars($row['email']); ?>', '<?php echo htmlspecialchars($row['phone'] ?? ''); ?>', '<?php echo $row['gender']; ?>', '<?php echo $row['status']; ?>')" 
                                        data-bs-toggle="modal" data-bs-target="#editAcademicModal" 
                                        class="btn btn-sm-custom btn-primary me-1">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                
                                <button onclick="resetAcademicPassword('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['email']); ?>')" 
                                        class="btn btn-sm-custom btn-warning me-1">
                                    <i class="bi bi-key"></i> Reset
                                </button>
                                
                                <a href="admin/core/drop_user?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm-custom btn-danger delete-link">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                              </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Academic Modal (unchanged) -->
<div class="modal fade" id="addAcademicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Academic Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="admin/core/new_user" method="POST" enctype="multipart/form-data" id="addAcademicForm">
                    <input type="hidden" name="level" value="1">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> First Name</label>
                            <input name="fname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="John">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> Last Name</label>
                            <input name="lname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Doe">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-envelope me-1"></i> Email</label>
                            <input name="email" required class="form-control" type="email" placeholder="academic@school.edu">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-telephone me-1"></i> Phone (optional)</label>
                            <input name="phone" class="form-control" type="text" placeholder="+255 123 456 789">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-gender-ambiguous me-1"></i> Gender</label>
                            <select class="form-control" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-toggle-on me-1"></i> Status</label>
                            <select class="form-control" name="status">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-lock me-1"></i> Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="newPass" required>
                                <span class="input-group-text" onclick="togglePassword('newPass', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="password-strength mt-2">
                                <div class="strength-bar" id="newStrengthBar"></div>
                            </div>
                            <small class="text-muted" id="newStrengthText">Enter a password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-lock me-1"></i> Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newConfirmPass" required>
                                <span class="input-group-text" onclick="togglePassword('newConfirmPass', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="invalid-feedback" id="newMatchError"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-image me-1"></i> Profile Image (optional)</label>
                        <input name="image" class="form-control" type="file" accept=".png,.jpg,.jpeg">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Academic</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Academic Modal (unchanged) -->
<div class="modal fade" id="editAcademicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Academic Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="admin/core/update_user" method="POST" enctype="multipart/form-data" id="editAcademicForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> First Name</label>
                            <input name="fname" id="edit_fname" required class="form-control" type="text" onkeypress="return lettersOnly(event)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-person me-1"></i> Last Name</label>
                            <input name="lname" id="edit_lname" required class="form-control" type="text" onkeypress="return lettersOnly(event)">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-envelope me-1"></i> Email</label>
                            <input name="email" id="edit_email" required class="form-control" type="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-telephone me-1"></i> Phone</label>
                            <input name="phone" id="edit_phone" class="form-control" type="text">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-gender-ambiguous me-1"></i> Gender</label>
                            <select class="form-control" name="gender" id="edit_gender" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-toggle-on me-1"></i> Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-image me-1"></i> New Profile Image (optional)</label>
                        <input name="image" class="form-control" type="file" accept=".png,.jpg,.jpeg">
                        <small class="text-muted">Leave empty to keep current image.</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Academic</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal (unchanged) -->
<div class="modal fade" id="resetPwdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i> Reset Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Resetting password for: <strong id="reset_email_display"></strong></p>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="resetPass">
                        <span class="input-group-text" onclick="togglePassword('resetPass', this)">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                    <div class="password-strength mt-2">
                        <div class="strength-bar" id="resetStrengthBar"></div>
                    </div>
                    <small class="text-muted" id="resetStrengthText">Enter a password</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="resetConfirmPass">
                        <span class="input-group-text" onclick="togglePassword('resetConfirmPass', this)">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                    <div class="invalid-feedback" id="resetMatchError"></div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="notifyUser">
                    <label class="form-check-label" for="notifyUser">
                        Notify user via email (if email is configured)
                    </label>
                </div>
                <button type="button" id="resetSubmitBtn" class="btn btn-primary w-100">Reset Password</button>
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
<script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>
<script src="cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
    $(document).ready(function(){
        // DataTable with export buttons
        $('#academicTable').DataTable({
            paging: true,
            pageLength: 10,
            ordering: false,
            language: { search: "Filter:" },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '<i class="bi bi-files"></i> Copy' },
                { extend: 'csv', text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV' },
                { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel' },
                { extend: 'pdf', text: '<i class="bi bi-file-earmark-pdf"></i> PDF' },
                { extend: 'print', text: '<i class="bi bi-printer"></i> Print' }
            ]
        });

        // Function to refresh stats cards via AJAX
        function refreshStats() {
            $.ajax({
                url: 'admin/core/get_academic_stats.php',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('#statTotal').text(res.total);
                        $('#statActive').text(res.active);
                        $('#statInactive').text(res.inactive);
                    }
                }
            });
        }

        // Enhanced status toggle with confirmation popup
        $(document).on('change', '.status-toggle', function() {
            var $toggle = $(this);
            var academicId = $toggle.data('id');
            var academicName = $toggle.data('name');
            var newState = $toggle.prop('checked') ? 1 : 0;
            var action = newState === 1 ? 'activate' : 'block';
            var actionText = newState === 1 ? 'Active' : 'Blocked';
            var messageTitle = newState === 1 ? 'Activate Academic Staff' : 'Block Academic Staff';
            var confirmText = `Are you sure you want to ${action} <strong>${academicName}</strong> (Academic)?`;
            
            Swal.fire({
                title: messageTitle,
                html: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: newState === 1 ? '#06d6a0' : '#ef476f',
                cancelButtonColor: '#64748b',
                confirmButtonText: `Yes, ${action} it!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX request to toggle status
                    $.ajax({
                        url: 'admin/core/toggle_academic_status.php',
                        type: 'POST',
                        data: { id: academicId, status: newState },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Update status label
                                var $statusLabel = $toggle.closest('td').find('.status-label');
                                $statusLabel.text(actionText);
                                // Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: `${actionText}!`,
                                    html: `<strong>${academicName}</strong> (Academic) has been ${actionText.toLowerCase()}.`,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                // Refresh stats cards
                                refreshStats();
                            } else {
                                // Revert toggle
                                $toggle.prop('checked', !$toggle.prop('checked'));
                                Swal.fire('Error', response.message || 'Failed to update status', 'error');
                            }
                        },
                        error: function() {
                            $toggle.prop('checked', !$toggle.prop('checked'));
                            Swal.fire('Error', 'Network error. Please try again.', 'error');
                        }
                    });
                } else {
                    // Revert the toggle because user cancelled
                    $toggle.prop('checked', !$toggle.prop('checked));
                }
            });
        });

        // Password toggle helper
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

        // Add modal password strength
        const newPass = document.getElementById('newPass');
        const newStrengthBar = document.getElementById('newStrengthBar');
        const newStrengthText = document.getElementById('newStrengthText');
        const newConfirm = document.getElementById('newConfirmPass');
        const newMatchError = document.getElementById('newMatchError');

        if (newPass) {
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
                newStrengthBar.style.width = percent + '%';
                newStrengthBar.className = 'strength-bar ' + color;
                newStrengthText.innerText = 'Password strength: ' + text;
                validateAddMatch();
            });
        }

        function validateAddMatch() {
            if (newPass && newConfirm) {
                if (newPass.value !== newConfirm.value) {
                    newMatchError.innerText = 'Passwords do not match';
                } else {
                    newMatchError.innerText = '';
                }
            }
        }
        if (newConfirm) newConfirm.addEventListener('input', validateAddMatch);

        // Reset password modal logic
        let resetUserId = null;
        window.resetAcademicPassword = function(id, email) {
            resetUserId = id;
            $('#reset_email_display').text(email);
            $('#resetPass').val('');
            $('#resetConfirmPass').val('');
            $('#resetStrengthBar').css('width', '0%').attr('class', 'strength-bar');
            $('#resetStrengthText').text('Enter a password');
            $('#resetMatchError').text('');
            $('#resetPwdModal').modal('show');
        };

        const resetPass = document.getElementById('resetPass');
        const resetStrengthBar = document.getElementById('resetStrengthBar');
        const resetStrengthText = document.getElementById('resetStrengthText');
        const resetConfirm = document.getElementById('resetConfirmPass');
        const resetMatchError = document.getElementById('resetMatchError');

        if (resetPass) {
            resetPass.addEventListener('input', function() {
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
                resetStrengthBar.style.width = percent + '%';
                resetStrengthBar.className = 'strength-bar ' + color;
                resetStrengthText.innerText = 'Password strength: ' + text;
                validateResetMatch();
            });
        }

        function validateResetMatch() {
            if (resetPass && resetConfirm) {
                if (resetPass.value !== resetConfirm.value) {
                    resetMatchError.innerText = 'Passwords do not match';
                } else {
                    resetMatchError.innerText = '';
                }
            }
        }
        if (resetConfirm) resetConfirm.addEventListener('input', validateResetMatch);

        $('#resetSubmitBtn').on('click', function(e) {
            e.preventDefault();
            const newPass = resetPass.value;
            const confirmPass = resetConfirm.value;
            const notify = $('#notifyUser').is(':checked');

            if (!newPass || !confirmPass) {
                Swal.fire('Error', 'Please enter and confirm the new password.', 'error');
                return;
            }
            if (newPass !== confirmPass) {
                Swal.fire('Error', 'Passwords do not match.', 'error');
                return;
            }

            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Resetting...');

            $.ajax({
                url: 'admin/core/reset_password',
                method: 'POST',
                data: {
                    id: resetUserId,
                    password: newPass,
                    notify: notify ? 1 : 0
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire('Success', 'Password reset successfully.', 'success');
                        $('#resetPwdModal').modal('hide');
                    } else {
                        Swal.fire('Error', res.error || 'Failed to reset password.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error. Please try again.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('Reset Password');
                }
            });
        });

        // Edit academic modal data population
        window.editAcademic = function(id, fname, lname, email, phone, gender, status) {
            $('#edit_id').val(id);
            $('#edit_fname').val(fname);
            $('#edit_lname').val(lname);
            $('#edit_email').val(email);
            $('#edit_phone').val(phone);
            $('#edit_gender').val(gender);
            $('#edit_status').val(status);
        };

        // Delete confirmation
        $('.delete-link').on('click', function(e) {
            e.preventDefault();
            const url = $(this).attr('href');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the academic staff account.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });

        // Letters only for name fields
        window.lettersOnly = function(e) {
            var char = String.fromCharCode(e.which);
            if (!/^[a-zA-Z\s]*$/.test(char)) {
                e.preventDefault();
            }
        };
    });
</script>
</body>
</html>