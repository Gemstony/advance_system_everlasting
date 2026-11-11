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
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

// Fetch data for statistics
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all classes for name mapping
    $stmt = $conn->prepare("SELECT id, name FROM tbl_classes");
    $stmt->execute();
    $classes_map = [];
    while ($row = $stmt->fetch()) {
        $classes_map[$row['id']] = $row['name'];
    }

    // Get teacher's subject combinations
    $stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
                             LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
                             LEFT JOIN tbl_staff ON tbl_subject_combinations.teacher = tbl_staff.id
                             WHERE tbl_subject_combinations.teacher = ?");
    $stmt->execute([$account_id]);
    $combinations = $stmt->fetchAll();

    // Compute stats
    $total_combinations = count($combinations);
    $distinct_classes = [];
    $latest_date = '1970-01-01';
    foreach ($combinations as $comb) {
        $class_list = unserialize($comb['class']);
        if (is_array($class_list)) {
            foreach ($class_list as $cid) {
                $distinct_classes[$cid] = true;
            }
        }
        // Track latest registration date
        if (strtotime($comb['reg_date']) > strtotime($latest_date)) {
            $latest_date = $comb['reg_date'];
        }
    }
    $total_classes_covered = count($distinct_classes);
    $latest_date_display = $latest_date !== '1970-01-01' ? date('d M Y', strtotime($latest_date)) : 'N/A';

} catch(PDOException $e) {
    $combinations = [];
    $classes_map = [];
    $total_combinations = $total_classes_covered = 0;
    $latest_date_display = 'N/A';
    $_SESSION['reply'] = [['danger', "Database error: " . $e->getMessage()]];
}

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; // Role‑aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>
                Subject Combinations
                <small class="text-muted d-block fs-6 mt-1">Your assigned subjects and classes</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Combinations</li>
        </ul>
    </div>

    <!-- Reply / Alert Messages -->
    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bi bi-grid-3x3-gap-fill"></div>
            <div class="stat-value"><?= $total_combinations ?></div>
            <div class="stat-label">Total Combinations</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-people-fill"></div>
            <div class="stat-value"><?= $total_classes_covered ?></div>
            <div class="stat-label">Classes Covered</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-calendar-check-fill"></div>
            <div class="stat-value"><?= htmlspecialchars($latest_date_display) ?></div>
            <div class="stat-label">Latest Added</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>What are subject combinations?</strong> Each combination links a subject to one or more classes that you teach. This defines which subjects you are responsible for in each class.
    </div>

    <!-- Main Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Your Subject Combinations</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered combinations-table" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Classes</th>
                                    <th>Added On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($combinations as $row): 
                                    $class_list = unserialize($row['class']);
                                    if (!is_array($class_list)) $class_list = [];
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                    <td>
                                        <?php foreach ($class_list as $cid): 
                                            $class_name = $classes_map[$cid] ?? 'Unknown';
                                        ?>
                                            <span class="class-badge"><?= htmlspecialchars($class_name) ?></span>
                                        <?php endforeach; ?>
                                     </td>
                                    <td><?= date('d M Y', strtotime($row['reg_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($combinations)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No subject combinations found for you.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </tr>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Insight -->
    <?php if ($total_combinations > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Note:</strong> These combinations determine which subjects appear when you import or view results. Only classes listed here will be available in dropdowns.
    
    </div>
                    
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>

<script>
$(document).ready(function() {
    $('#srmsTable').DataTable({
        "sort": false,
        "pageLength": 25,
        "language": {
            "emptyTable": "No combinations available"
        }
    });
});
</script>

<style>
/* Combinations‑page specific styles (glass cards, stat grid) */
.app-content {
    background: transparent !important;
}
.stat-card, .tile, .info-box {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    padding: 1.2rem 1rem;
    text-align: center;
    transition: 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 2rem;
    color: #3b7cff;
    margin-bottom: 0.3rem;
}
.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    color: #1e293b;
}
.stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}
.info-box {
    border-left: 4px solid #3b7cff;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.class-badge {
    background: #e9eef2;
    color: #1e293b;
    padding: 0.2rem 0.6rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
    margin: 0.1rem 0.2rem;
    white-space: nowrap;
}
.combinations-table th {
    background: linear-gradient(145deg, #f8fafc, #e9eef2);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}
.combinations-table td {
    vertical-align: middle;
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
.tile {
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
</style>
</body>
</html>