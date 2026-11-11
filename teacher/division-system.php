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

// Fetch division data for statistics
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT * FROM tbl_division_system");
    $stmt->execute();
    $divisions = $stmt->fetchAll();

    // Compute simple stats
    $total_divisions = count($divisions);
    $min_points = $total_divisions ? min(array_column($divisions, 5)) : 0;
    $max_points = $total_divisions ? max(array_column($divisions, 5)) : 0;
    $min_score = $total_divisions ? min(array_column($divisions, 1)) : 0;
    $max_score = $total_divisions ? max(array_column($divisions, 2)) : 0;

} catch(PDOException $e) {
    $divisions = [];
    $total_divisions = $min_points = $max_points = $min_score = $max_score = 0;
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
                <i class="bi bi-layers me-2"></i>
                Division System
                <small class="text-muted d-block fs-6 mt-1">Score‑based division and points for ranking</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Division System</li>
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
            <div class="stat-value"><?= $total_divisions ?></div>
            <div class="stat-label">Total Divisions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-star-fill"></div>
            <div class="stat-value"><?= $min_points ?></div>
            <div class="stat-label">Min Points</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-trophy-fill"></div>
            <div class="stat-value"><?= $max_points ?></div>
            <div class="stat-label">Max Points</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-rulers"></div>
            <div class="stat-value"><?= $min_score ?> - <?= $max_score ?>%</div>
            <div class="stat-label">Score Range</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>How it works:</strong> Divisions group students based on their average score. The points column is used for ranking and division assignment.
    </div>

    <!-- Main Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Division Definitions</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered division-table" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Division</th>
                                    <th>Minimum Score</th>
                                    <th>Maximum Score</th>
                                    <th>Minimum Point</th>
                                    <th>Maximum Point</th>
                                    <th>Points</th>
                                    <th>Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($divisions as $row): 
                                    // Calculate fill percentage for visual bar
                                    $range_width = $max_score - $min_score;
                                    $fill = $range_width > 0 ? (($row[2] - $row[1]) / $range_width) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row[0]) ?></strong></td>
                                    <td><?= $row[1] ?>%</td>
                                    <td><?= $row[2] ?>%</td>
                                    <td><?= $row[3] ?></td>
                                    <td><?= $row[4] ?></td>
                                    <td><?= $row[5] ?></td>
                                    <td>
                                        <div class="range-bar">
                                            <div class="range-fill" style="width: <?= $fill ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Insight -->
    <?php if ($total_divisions > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        <strong>Note:</strong> Divisions are typically used for final certification. The points system allows precise ranking even within the same division.
                    </p>
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
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    $('#srmsTable').DataTable({
        "sort": false,
        "pageLength": 25,
        "language": {
            "emptyTable": "No division data available"
        }
    });
});
</script>

<style>
/* Division system page specific styles (glass cards, stat grid) */
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
.range-bar {
    height: 8px;
    background: #e9eef2;
    border-radius: 4px;
    overflow: hidden;
    width: 100px;
}
.range-fill {
    height: 100%;
    background: #3b7cff;
    border-radius: 4px;
}
.info-box {
    border-left: 4px solid #3b7cff;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.division-table th {
    background: linear-gradient(145deg, #f8fafc, #e9eef2);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}
.division-table td {
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