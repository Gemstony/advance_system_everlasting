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

// Fetch result change requests made by this teacher
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT rcr.*,
                   s.fname, s.mname, s.lname,
                   c.name AS class_name,
                   t.name AS term_name,
                   sc.id AS sc_id,
                   subj.name AS subject_name,
                   st.fname AS proc_fname, st.lname AS proc_lname
            FROM tbl_result_change_requests rcr
            LEFT JOIN tbl_students s ON rcr.student_id = s.id
            LEFT JOIN tbl_classes c ON rcr.class_id = c.id
            LEFT JOIN tbl_terms t ON rcr.term_id = t.id
            LEFT JOIN tbl_subject_combinations sc ON rcr.subject_combination_id = sc.id
            LEFT JOIN tbl_subjects subj ON sc.subject = subj.id
            LEFT JOIN tbl_staff st ON rcr.processed_by = st.id
            WHERE rcr.teacher_id = ?
            ORDER BY rcr.requested_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$account_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $requests = [];
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
                <i class="bi bi-arrow-repeat me-2"></i>
                Result Change Requests
                <small class="text-muted d-block fs-6 mt-1">Track your submitted score correction requests</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Result Requests</li>
        </ul>
    </div>

    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <!-- Stats Cards -->
    <?php
    $total = count($requests);
    $pending = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
    $approved = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
    $denied = count(array_filter($requests, fn($r) => $r['status'] === 'denied'));
    ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon bi bi-files"></div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Requests</div></div>
        <div class="stat-card"><div class="stat-icon bi bi-hourglass-split"></div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div>
        <div class="stat-card"><div class="stat-icon bi bi-check-circle-fill text-success"></div><div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved</div></div>
        <div class="stat-card"><div class="stat-icon bi bi-x-circle-fill text-danger"></div><div class="stat-value"><?= $denied ?></div><div class="stat-label">Denied</div></div>
    </div>

    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Request Status:</strong> When a term is closed, you can request a score change. The academic office will review your request and update the result if approved.
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-clock-history me-2"></i>Your Change Requests</h3>
                    <?php if (empty($requests)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                            You haven't submitted any result change requests yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="requestsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Term</th>
                                        <th>Subject</th>
                                        <th>Current Score</th>
                                        <th>Requested Score</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Processed By</th>
                                        <th>Processed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $req): 
                                        $studentName = htmlspecialchars(trim($req['fname'] . ' ' . $req['mname'] . ' ' . $req['lname']));
                                        $subject = htmlspecialchars($req['subject_name'] ?? 'N/A');
                                        $term = htmlspecialchars($req['term_name'] ?? 'N/A');
                                        $class = htmlspecialchars($req['class_name'] ?? 'N/A');
                                        $reason = nl2br(htmlspecialchars($req['reason']));
                                        $status = $req['status'];
                                        $badgeClass = $status === 'pending' ? 'badge-pending' : ($status === 'approved' ? 'badge-approved' : 'badge-denied');
                                        $statusText = ucfirst($status);
                                        $processedBy = $req['processed_by'] ? htmlspecialchars($req['proc_fname'] . ' ' . $req['proc_lname']) : '—';
                                        $processedAt = $req['processed_at'] ? date('d M Y, H:i', strtotime($req['processed_at'])) : '—';
                                    ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?= date('d M Y, H:i', strtotime($req['requested_at'])) ?></td>
                                        <td><?= $studentName ?><br><small class="text-muted"><?= $req['student_id'] ?></small></td>
                                        <td><?= $term ?><br><small class="text-muted"><?= $class ?></small></td>
                                        <td><?= $subject ?></td>
                                        <td class="text-center"><?= number_format($req['current_score'], 2) ?>%</td>
                                        <td class="text-center"><?= number_format($req['requested_score'], 2) ?>%</td>
                                        <td><?= $reason ?></td>
                                        <td class="text-center"><span class="status-badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                                        <td><?= $processedBy ?></td>
                                        <td><?= $processedAt ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <p class="mb-0 text-muted" style="color: #000 !important;">
                        <i class="bi bi-envelope-paper me-1"></i>
                        <strong>Note:</strong> Only requests submitted by you are shown. Approved requests will reflect in the examination results after the academic office updates the scores.
                    </p>
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
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    $('#requestsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "language": {
            "emptyTable": "No requests found",
            "search": "Filter:",
            "lengthMenu": "Show _MENU_ requests per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ requests"
        },
        "columnDefs": [
            { "orderable": false, "targets": [4,5,6,7,8,9] }
        ]
    });
});
</script>

<style>
/* Result requests page specific styles (glass cards, black text) */
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
.badge-pending {
    background: #fff3cd;
    color: #856404;
}
.badge-approved {
    background: #d1fae5;
    color: #065f46;
}
.badge-denied {
    background: #fee2e2;
    color: #991b1b;
}
.table th {
    background: linear-gradient(145deg, #f8fafc, #e9eef2);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
    color: #000 !important;
}
.table td {
    color: #000 !important;
    vertical-align: middle;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_paginate {
    color: #000 !important;
}
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
    color: #000;
    background: rgba(255,255,255,0.9);
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: #000 !important;
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
.tile-body h3, .tile-body p, .tile-body .text-muted {
    color: #000 !important;
}
.alert-info {
    color: #0c5460 !important;
    background-color: #d1ecf1;
}
.text-muted {
    color: rgba(0,0,0,0.6) !important;
}
</style>
</body>
</html>