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

// Prepare staff array for header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

$current_page = basename($_SERVER['PHP_SELF']);
$pending_requests = 0;

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $color = trim($_POST['color']);
        if ($name && $color) {
            $stmt = $pdo->prepare("INSERT INTO event_categories (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            $_SESSION['msg'] = "Category added successfully.";
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $color = trim($_POST['color']);
        $stmt = $pdo->prepare("UPDATE event_categories SET name = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $color, $id]);
        $_SESSION['msg'] = "Category updated.";
    }
    header("Location: academic/event-categories.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM event_categories WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['msg'] = "Category deleted.";
    header("Location: academic/event-categories.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM event_categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Categories - SRMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Additional page styles – supplements the unified header theme */
        .app-content {
            background: transparent !important;
        }
        
        /* Glass tiles */
        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            padding: 1.8rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
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
        .form-control, .form-select {
            border-radius: 16px;
            padding: 0.7rem 1.2rem;
            border: 1px solid rgba(0,0,0,0.1);
            background: white;
            transition: all 0.2s;
            color: #1e293b;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        label i {
            color: #4361ee;
        }
        
        /* Buttons */
        .btn-3d {
            border-radius: 40px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            border: none;
        }
        .btn-3d:active {
            transform: translateY(1px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -6px rgba(37,99,235,0.3);
        }
        .btn-warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        .btn-warning:hover {
            background: #fde68a;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .btn-danger:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }
        
        /* Table */
        .table {
            color: #1e293b;
            margin-bottom: 0;
        }
        .table thead th {
            background: rgba(241,245,249,0.7);
            border-bottom: 2px solid #4361ee;
            font-weight: 700;
            padding: 1rem 0.75rem;
            color: #1e293b;
        }
        .table tbody tr {
            transition: background 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }
        .table td {
            vertical-align: middle;
            padding: 0.8rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            color: #1e293b;
        }
        .color-preview {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 2px solid white;
        }
        
        /* Modal – glass light */
        .modal-content {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 28px;
            color: #1e293b;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 1.2rem 1.5rem;
        }
        .modal-title {
            font-weight: 800;
            color: #1e293b;
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.08);
            padding: 1rem 1.5rem;
        }
        .btn-close {
            filter: none;
        }
        
        /* Alerts */
        .alert-success {
            background: rgba(16,185,129,0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 60px;
            color: #065f46;
            font-weight: 500;
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
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-tags me-2"></i> Event Categories</h1></div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><a href="academic">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="academic/event-dashboard.php">Events</a></li>
                <li class="breadcrumb-item active">Categories</li>
            </ul>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Add Category Form -->
            <div class="col-md-4">
                <div class="tile">
                    <h3 class="tile-title"><i class="bi bi-plus-circle me-2"></i> Add New Category</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label><i class="bi bi-tag"></i> Category Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Seminar, Workshop">
                        </div>
                        <div class="mb-3">
                            <label><i class="bi bi-palette"></i> Color</label>
                            <input type="color" name="color" class="form-control w-50" value="#4f46e5" style="height: 45px; border-radius: 30px;">
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary btn-3d w-100"><i class="bi bi-plus-circle me-2"></i> Add Category</button>
                    </form>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-md-8">
                <div class="tile">
                    <h3 class="tile-title"><i class="bi bi-table me-2"></i> Existing Categories</h3>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Color Code</th>
                                    <th>Preview</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                                        <td><code><?= $cat['color'] ?></code></td>
                                        <td><span class="color-preview" style="background: <?= $cat['color'] ?>;"></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editModal<?= $cat['id'] ?>"><i class="bi bi-pencil"></i> Edit</button>
                                            <a href="academic/event-categories.php?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3" onclick="return confirm('Delete this category? Events will keep the category name but lose color association.')"><i class="bi bi-trash"></i> Delete</a>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $cat['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Category</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                        <div class="mb-3">
                                                            <label><i class="bi bi-tag"></i> Name</label>
                                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label><i class="bi bi-palette"></i> Color</label>
                                                            <input type="color" name="color" class="form-control w-50" value="<?= $cat['color'] ?>" style="height: 45px; border-radius: 30px;">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="edit_category" class="btn btn-primary rounded-pill">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>