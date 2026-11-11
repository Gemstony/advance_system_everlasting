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

// Fetch term data for statistics
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT * FROM tbl_terms");
    $stmt->execute();
    $terms = $stmt->fetchAll();

    // Compute stats
    $total_terms = count($terms);
    $active_terms = 0;
    foreach ($terms as $term) {
        if ($term[2] == 1) $active_terms++;
    }
    $inactive_terms = $total_terms - $active_terms;

} catch(PDOException $e) {
    $terms = [];
    $total_terms = $active_terms = $inactive_terms = 0;
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
                <i class="bi bi-calendar-week me-2"></i>
                Academic Terms
                <small class="text-muted d-block fs-6 mt-1">Manage examination periods</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Terms</li>
        </ul>
    </div>

    <!-- Reply / Alert Messages -->
    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bi bi-calendar-week"></div>
            <div class="stat-value"><?= $total_terms ?></div>
            <div class="stat-label">Total Terms</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-check-circle-fill text-success"></div>
            <div class="stat-value"><?= $active_terms ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-x-circle-fill text-danger"></div>
            <div class="stat-value"><?= $inactive_terms ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Academic Terms:</strong> Terms define examination periods. Only active terms appear in dropdowns when importing or viewing results.
    </div>

    <!-- Main Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Terms List</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered terms-table" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th width="150" align="center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($terms as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row[1]) ?></td>
                                    <td align="center">
                                        <?php if ($row[2] == 1): ?>
                                            <span class="status-badge badge-active"><i class="bi bi-check-circle-fill me-1"></i> ACTIVE</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-inactive"><i class="bi bi-x-circle-fill me-1"></i> INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($terms)): ?>
                                    <tr><td colspan="2" class="text-center text-muted">No terms found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Insight -->
    <?php if ($total_terms > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        <strong>Note:</strong> Inactive terms are hidden from selection forms but preserved for historical data.
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
            "emptyTable": "No terms available"
        }
    });
});
</script>

<style>
/* Terms‑page specific styles (glass cards, stat grid) */
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
.info-box {
    border-left: 4px solid #3b7cff;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.8rem;
    border-radius: 2rem;
    font-weight: 500;
    font-size: 0.75rem;
    text-transform: uppercase;
}
.badge-active {
    background: #d1fae5;
    color: #065f46;
}
.badge-inactive {
    background: #fee2e2;
    color: #991b1b;
}
.terms-table th {
    background: linear-gradient(145deg, #f8fafc, #e9eef2);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}
.terms-table td {
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