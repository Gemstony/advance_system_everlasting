<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

// Fetch student data for header (needed by inc/header.php)
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
$school_logo = defined('WBLogo') ? WBLogo : 'logo.png';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Fetch student details (minimal for header)
    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentData) die("Student not found.");

    // Build $student array for modular header
    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];

    // Also fetch school info (optional)
    $stmt2 = $pdo->prepare("SELECT name, logo FROM tbl_school LIMIT 1");
    $stmt2->execute();
    $school = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($school) {
        $school_name = $school['name'];
        $school_logo = $school['logo'];
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== DIVISION SYSTEM QUERY - handle missing columns gracefully ==========
$divisionData = [];
try {
    // First, get column names from the table to know what's available
    $stmt = $pdo->prepare("SELECT * FROM tbl_division_system LIMIT 0");
    $stmt->execute();
    $columnCount = $stmt->columnCount();
    $columns = [];
    for ($i = 0; $i < $columnCount; $i++) {
        $colMeta = $stmt->getColumnMeta($i);
        $columns[] = $colMeta['name'];
    }
    
    // Now fetch all data
    $stmt = $pdo->prepare("SELECT * FROM tbl_division_system ORDER BY division");
    $stmt->execute();
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize each row: map possible column names to expected keys
    foreach ($rawData as $row) {
        $normalized = [];
        
        // Division
        $normalized['division'] = $row['division'] ?? $row['Division'] ?? null;
        
        // Min score (try common names)
        $normalized['min_score'] = $row['min_score'] ?? $row['min'] ?? $row['Min'] ?? $row['minimum'] ?? null;
        
        // Max score
        $normalized['max_score'] = $row['max_score'] ?? $row['max'] ?? $row['Max'] ?? $row['maximum'] ?? null;
        
        // Min point
        $normalized['min_point'] = $row['min_point'] ?? $row['minpoint'] ?? $row['min_pts'] ?? null;
        
        // Max point
        $normalized['max_point'] = $row['max_point'] ?? $row['maxpoint'] ?? $row['max_pts'] ?? null;
        
        // Points
        $normalized['points'] = $row['points'] ?? $row['point'] ?? $row['pts'] ?? null;
        
        $divisionData[] = $normalized;
    }
} catch (PDOException $e) {
    // Log error but continue with empty data
    error_log("Division system query error: " . $e->getMessage());
    $divisionData = [];
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-bar-chart-steps me-2"></i> Division System</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Division System</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="tile-title m-0">Division Classification</h4>
                    <span class="badge bg-secondary fs-6 px-3 py-2">Performance Bands</span>
                </div>

                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-3 me-3" style="color:#3498db;"></i>
                        <div>
                            <h6 class="fw-bold mb-1">How Divisions Are Determined</h6>
                            <p class="mb-0 small">Your division is calculated from the points earned in your three principal subjects. Lower total points represent better performance, with Division I being the highest level of achievement.</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($divisionData)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        No division system data found. Please contact the administrator.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Division</th>
                                    <th>Minimum Point</th>
                                    <th>Maximum Point</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($divisionData as $row): 
                                    // Safe access with null coalescing and default values
                                    $div = isset($row['division']) ? trim($row['division']) : '?';
                                    $min_score = isset($row['min_score']) ? (int)$row['min_score'] : 0;
                                    $max_score = isset($row['max_score']) ? (int)$row['max_score'] : 0;
                                    $min_point = isset($row['min_point']) ? (int)$row['min_point'] : 0;
                                    $max_point = isset($row['max_point']) ? (int)$row['max_point'] : 0;
                                    $points = isset($row['points']) ? (int)$row['points'] : 0;
                                    
                                    $badgeClass = 'division-';
                                    if ($div == '1') $badgeClass .= '1';
                                    elseif ($div == '2') $badgeClass .= '2';
                                    elseif ($div == '3') $badgeClass .= '3';
                                    elseif ($div == '4') $badgeClass .= '4';
                                    else $badgeClass .= '0';
                                ?>
                                <tr>
                                    <td><span class="division-badge <?= $badgeClass; ?>"><?= htmlspecialchars($div); ?></span></td>
                                    <td><?= $min_point; ?></td>
                                    <td><?= $max_point; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="mt-4 p-3 bg-light rounded">
                    <p class="mb-0 small"><i class="bi bi-star-fill text-warning me-2"></i><strong>Note:</strong> The division system applies to your overall performance across all subjects. Higher divisions reflect better academic achievement.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Page‑specific styles (glass‑morphic, theme‑compatible) -->
<style>
    .app-content {
        background: transparent !important;
    }
    .tile {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(4px);
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }
    .table th {
        background-color: #2c3e50;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #1a252f;
    }
    .table td {
        vertical-align: middle;
        padding: 0.75rem;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(52, 152, 219, 0.1);
        transition: background-color 0.2s ease;
    }
    .division-badge {
        display: inline-block;
        padding: 0.35rem 0.65rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 2rem;
        min-width: 3rem;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .division-1 { background-color: #28a745; color: white; }
    .division-2 { background-color: #17a2b8; color: white; }
    .division-3 { background-color: #ffc107; color: #212529; }
    .division-4 { background-color: #fd7e14; color: white; }
    .division-0 { background-color: #dc3545; color: white; }
    .info-card {
        background: rgba(248,249,250,0.9);
        border-left: 4px solid #3498db;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .tile-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
    }
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .breadcrumb {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        border-radius: 40px;
        padding: 0.5rem 1rem;
    }
    .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #1e293b, #2d3a5e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .alert-warning {
        background: rgba(255, 193, 7, 0.9);
        border: none;
        color: #856404;
    }
</style>

<!-- Page‑specific scripts -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
    $(document).ready(function() {
        <?php if (!empty($divisionData)): ?>
        $('#srmsTable').DataTable({
            "sort": false,
            "pageLength": 10,
            "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
            "language": {
                "search": "Filter records:",
                "paginate": {
                    "previous": "<",
                    "next": ">"
                }
            }
        });
        <?php endif; ?>
    });
</script>

</body>
</html>