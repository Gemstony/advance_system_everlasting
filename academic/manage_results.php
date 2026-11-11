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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Manage Results</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== GLOBAL – LEAVE HEADER THEME UNTOUCHED ========== */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
        }
        
        /* ========== GLASS TILE ========== */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 2rem;
            position: relative;
            overflow: hidden;
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
        
        /* Form controls – dark text */
        .form-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-label i {
            color: #4361ee;
        }
        .select2-container--default .select2-selection--single {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            height: 48px;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.25s ease;
        }
        .select2-container--default .select2-selection--single:hover {
            border-color: #4361ee;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            color: #1e293b;
            padding-left: 1rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 11px;
            right: 8px;
        }
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        
        /* Glossy 3D button */
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.8rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.25s ease;
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
            width: 100%;
            margin-top: 1rem;
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(67,97,238,0.5);
            background: linear-gradient(135deg, #3a56d4, #1e40af);
        }
        .btn-primary:active {
            transform: translateY(1px);
        }
        
        /* Center form */
        .center_form {
            margin: 0 auto;
            float: none;
        }
        
        /* Title – keep white for header consistency */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
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
            <div><h1><i class="bi bi-pencil-square me-2"></i> Manage Results</h1></div>
        </div>

        <div class="row">
            <div class="col-md-5 center_form">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-diagram-3 me-2"></i> Select Criteria</h3>
                        <form action="academic/core/start_manage" class="app_frm" method="POST" autocomplete="OFF" id="manageForm">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-building"></i> Class</label>
                                <select class="form-control select2" name="student" required style="width: 100%;">
                                    <option value="" selected disabled> Select Class</option>
                                    <?php
                                    try {
                                        $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                        $stmt = $conn->prepare("SELECT * FROM tbl_classes ORDER BY name");
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
                                            echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
                                        }
                                    } catch(PDOException $e) {
                                        // ignore
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label"><i class="bi bi-calendar-week"></i> Term</label>
                                <select class="form-control select2" name="term" required style="width: 100%;">
                                    <option selected disabled value=""> Select Term</option>
                                    <?php
                                    try {
                                        $stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = 1 ORDER BY name");
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
                                            echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
                                        }
                                    } catch(PDOException $e) {
                                        // ignore
                                    }
                                    ?>
                                </select>
                                <div class="form-text text-muted mt-1">Only active terms are shown.</div>
                            </div>

                            <button class="btn btn-primary" type="submit"><i class="bi bi-eye me-2"></i> View Results</button>
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
    <script src="js/sweetalert2@11.js"></script>
    <script src="js/forms.js"></script>
    <script src="select2/dist/js/select2.full.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.select2').select2({
                placeholder: "Select an option",
                allowClear: false
            });

            $('#manageForm').on('submit', function(e){
                var classVal = $('select[name="student"]').val();
                var termVal = $('select[name="term"]').val();
                if (!classVal || !termVal) {
                    e.preventDefault();
                    Swal.fire('Error', 'Please select both Class and Term.', 'warning');
                    return false;
                }
            });
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>