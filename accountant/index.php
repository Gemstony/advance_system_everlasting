<?php
// accountant/index.php
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

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT SUM(paid_amount) FROM tbl_fee_payments WHERE verification_status='verified' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())");
    $stmt->execute();
    $monthly_collection = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT SUM(balance_due) FROM tbl_student_fee_accounts WHERE balance_due > 0");
    $stmt->execute();
    $total_outstanding = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_student_fee_accounts WHERE status = 'overdue'");
    $stmt->execute();
    $overdue_count = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_fee_payments WHERE verification_status='pending'");
    $stmt->execute();
    $pending_verifications = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT p.*, CONCAT(s.fname,' ',s.lname) AS student_name FROM tbl_fee_payments p JOIN tbl_students s ON p.student_id = s.id WHERE p.verification_status='verified' ORDER BY p.payment_date DESC LIMIT 10");
    $stmt->execute();
    $recent_payments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<?php require 'accountant/inc/header.php'; ?>
<?php require 'accountant/inc/sidebar.php'; ?>
<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="accountant/fee_structures.php" class="btn"><i class="bi bi-diagram-3"></i> Fee Structures</a>
        <a href="accountant/invoices.php" class="btn"><i class="bi bi-file-text"></i> Invoices</a>
        <a href="accountant/payments.php" class="btn"><i class="bi bi-cash-stack"></i> Record Payment</a>
        <a href="accountant/reports.php" class="btn"><i class="bi bi-graph-up"></i> Reports</a>
        <a href="accountant/settings.php" class="btn"><i class="bi bi-gear"></i> Settings</a>
    </div>
    <div class="row mb-4">
        <div class="col-md-3"><div class="tile text-center"><h6>This Month</h6><div class="fs-2"><?php echo formatCurrency($monthly_collection); ?></div></div></div>
        <div class="col-md-3"><div class="tile text-center"><h6>Total Outstanding</h6><div class="fs-2 text-danger"><?php echo formatCurrency($total_outstanding); ?></div></div></div>
        <div class="col-md-3"><div class="tile text-center"><h6>Overdue Accounts</h6><div class="fs-2"><?php echo $overdue_count; ?></div></div></div>
        <div class="col-md-3"><div class="tile text-center"><h6>Pending Verifications</h6><div class="fs-2"><?php echo $pending_verifications; ?></div><a href="accountant/payments.php?filter=pending" class="btn btn-sm btn-warning">Verify</a></div></div>
    </div>
    <div class="tile">
        <h4>Recent Verified Payments</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Student</th><th>Payment Ref</th><th>Amount</th><th>Date</th><th>Method</th><th>Receipt</th></tr></thead>
                <tbody><?php foreach ($recent_payments as $pay): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pay['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($pay['payment_ref']); ?></td>
                    <td><?php echo formatCurrency($pay['paid_amount']); ?></td>
                    <td><?php echo $pay['payment_date']; ?></td>
                    <td><?php echo ucfirst($pay['payment_method']); ?></td>
                    <td><?php echo ($pay['receipt_status']=='issued') ? 'Issued' : '<a href="accountant/receipt.php?payment_id='.$pay['id'].'" class="btn btn-sm btn-outline-success">Print</a>'; ?></td>
                </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
</main>
<?php require 'accountant/inc/footer.php'; ?>