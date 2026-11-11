<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('inc/finance_functions.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = $account_id;
$academic_year = '2026'; // or dynamically get from settings

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get student details
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Get fee account
    $account = getStudentFeeAccount($pdo, $student_id, $academic_year);
    
    // Get latest invoices
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_invoices WHERE student_id = ? AND academic_year = ? ORDER BY invoice_date DESC LIMIT 5");
    $stmt->execute([$student_id, $academic_year]);
    $invoices = $stmt->fetchAll();
    
    // Get recent payments
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_payments WHERE student_id = ? AND verification_status = 'verified' ORDER BY payment_date DESC LIMIT 10");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$module_enabled = isFeeModuleEnabled($pdo);
$profile_image = null;
$use_icon = false;
// ... (same image logic as your dashboard) ...
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="student/school_fee/" class="btn"><i class="bi bi-wallet2"></i> Fee Dashboard</a>
        <a href="student/school_fee/invoices.php" class="btn"><i class="bi bi-receipt"></i> My Invoices</a>
        <a href="student/school_fee/payments.php" class="btn"><i class="bi bi-credit-card"></i> Payment History</a>
    </div>

    <?php if (!$module_enabled): ?>
        <div class="advice-card"><div class="d-flex"><i class="bi bi-info-circle fs-1 me-3" style="color:#6c757d;"></i><div><h5>Fee Information Unavailable</h5><p>The fee module is currently disabled. Please contact the school accountant.</p></div></div></div>
    <?php else: ?>
        <!-- Student Info Card (similar to results dashboard) -->
        <div class="student-info-card">...</div>

        <!-- Balance Summary -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="tile text-center">
                    <h4>Total Expected</h4>
                    <div style="font-size:2rem;"><?php echo formatCurrency($account['total_expected'] ?? 0); ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="tile text-center">
                    <h4>Total Paid</h4>
                    <div style="font-size:2rem; color:#28a745;"><?php echo formatCurrency($account['total_paid'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <!-- Balance Due with Progress Bar -->
        <div class="tile mb-4">
            <h4>Balance Due: <?php echo formatCurrency($account['balance_due'] ?? 0); ?></h4>
            <?php $percent_paid = (($account['total_expected'] ?? 1) > 0) ? (($account['total_paid'] ?? 0) / ($account['total_expected'] ?? 1)) * 100 : 0; ?>
            <div class="progress-bar-custom" style="height:20px;"><div class="progress-bar-fill" style="width: <?php echo $percent_paid; ?>%; background-color: #3b7cff;"></div></div>
            <div class="mt-2 text-muted"><?php echo round($percent_paid); ?>% of fees paid</div>
        </div>

        <!-- Recent Invoices -->
        <?php if (count($invoices) > 0): ?>
        <div class="tile">
            <h4>Recent Invoices</h4>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Grand Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                            <td><?php echo $inv['invoice_date']; ?></td>
                            <td><?php echo $inv['due_date']; ?></td>
                            <td><?php echo formatCurrency($inv['grand_total']); ?></td>
                            <td><?php echo formatCurrency($inv['balance']); ?></td>
                            <td><span class="badge bg-<?php echo ($inv['status']=='paid'?'success':($inv['status']=='partial'?'warning':'danger')); ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                            <td><a href="student/school_fee/invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment History -->
        <?php if (count($payments) > 0): ?>
        <div class="tile">
            <h4>Recent Payments</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Payment Ref</th><th>Date</th><th>Amount</th><th>Method</th><th>Bank</th><th>Receipt</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pay['payment_ref']); ?></td>
                            <td><?php echo $pay['payment_date']; ?></td>
                            <td><?php echo formatCurrency($pay['paid_amount']); ?></td>
                            <td><?php echo ucfirst($pay['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($pay['bank_name']); ?></td>
                            <td>
                                <?php if ($pay['receipt_status'] == 'issued'): ?>
                                <a href="student/school_fee/receipt.php?payment_id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-outline-success">Receipt</a>
                                <?php else: ?>
                                <span class="text-muted">Not issued</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- scroll top button same as dashboard -->
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
// Same scroll button JS as dashboard
</script>
<style>
/* Reuse the same CSS as your student dashboard */
/* Add any fee‑specific overrides if necessary */
</style>
</body>
</html>