<?php
// student/fees.php
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
require_once __DIR__ . '/inc/finance_functions.php';

session_start();

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = $account_id;
$academic_year = '2026';

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $account = getStudentFeeAccount($pdo, $student_id, $academic_year);
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_invoices WHERE student_id = ? AND academic_year = ? ORDER BY invoice_date DESC LIMIT 5");
    $stmt->execute([$student_id, $academic_year]);
    $invoices = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_payments WHERE student_id = ? AND verification_status = 'verified' ORDER BY payment_date DESC LIMIT 10");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$module_enabled = isFeeModuleEnabled($pdo);
$photoData = getStudentPhoto($student);
$profile_image = $photoData['image'];
$use_icon = $photoData['use_icon'];
$fname = $student['fname'];
$lname = $student['lname'];
?>
<?php require __DIR__ . '/inc/header.php'; ?>
<?php require __DIR__ . '/inc/sidebar.php'; ?>

<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="student/fees" class="btn"><i class="bi bi-wallet2"></i> Fee Dashboard</a>
        <a href="student/invoices" class="btn"><i class="bi bi-receipt"></i> My Invoices</a>
        <a href="student/payment-history" class="btn"><i class="bi bi-clock-history"></i> Payment History</a>
        <a href="student/fee-structure" class="btn"><i class="bi bi-file-text"></i> Fee Structure</a>
        <a href="student/pay-fees" class="btn"><i class="bi bi-cash-stack"></i> Make Payment</a>
    </div>

    <?php if (!$module_enabled): ?>
        <div class="advice-card"><div class="d-flex"><i class="bi bi-info-circle fs-1 me-3" style="color:#6c757d;"></i><div><h5>Fee Information Unavailable</h5><p>The fee module is currently disabled. Please contact the school accountant.</p></div></div></div>
    <?php else: ?>
        <div class="student-info-card">
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <?php if ($use_icon): ?>
                        <div class="profile-icon"><i class="bi bi-person-circle"></i></div>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile-img">
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-4"><div class="info-item"><span class="info-icon"><i class="bi bi-person-badge"></i></span><span><strong>Reg No:</strong> <?php echo htmlspecialchars($student['id']); ?></span></div><div class="info-item"><span class="info-icon"><i class="bi bi-building"></i></span><span><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name']); ?></span></div></div>
                        <div class="col-md-4"><div class="info-item"><span class="info-icon"><i class="bi bi-gender-<?php echo strtolower($student['gender']); ?>"></i></span><span><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender']); ?></span></div><div class="info-item"><span class="info-icon"><i class="bi bi-envelope"></i></span><span><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></span></div></div>
                    </div>
                </div>
            </div>
        </div>

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

        <div class="tile mb-4">
            <h4>Balance Due: <?php echo formatCurrency($account['balance_due'] ?? 0); ?></h4>
            <?php $percent_paid = (($account['total_expected'] ?? 1) > 0) ? (($account['total_paid'] ?? 0) / ($account['total_expected'] ?? 1)) * 100 : 0; ?>
            <div class="progress-bar-custom" style="height:20px;"><div class="progress-bar-fill" style="width: <?php echo $percent_paid; ?>%; background-color: #3b7cff;"></div></div>
            <div class="mt-2 text-muted"><?php echo round($percent_paid); ?>% of fees paid</div>
        </div>

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
                            <td><a href="student/invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

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
                                <a href="student/receipt.php?payment_id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-outline-success">Receipt</a>
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

<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) scrollBtn.classList.add('show');
    else scrollBtn.classList.remove('show');
});
scrollBtn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
</script>
<style>
/* Include the same style as your dashboard – copy from the example if needed */
.app-content { position: relative; z-index: 1; background: transparent; }
.student-info-card, .advice-card, .tile, .quick-actions .btn {
    background: #ffffff; border-radius: 28px; margin-bottom: 1.5rem;
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.02);
}
.profile-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #3b7cff; }
.profile-icon { font-size: 5rem; color: #3b7cff; text-align: center; }
.info-item { display: flex; align-items: center; margin-bottom: 0.8rem; color: #1e293b; }
.info-icon { width: 30px; color: #3b7cff; font-size: 1.3rem; }
.quick-actions .btn { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 40px; margin-right: 0.5rem; margin-bottom: 0.5rem; }
.progress-bar-custom { height: 8px; border-radius: 10px; background: #e2e8f0; overflow: hidden; }
.progress-bar-fill { height: 8px; border-radius: 10px; background: #3b7cff; width: 0%; transition: width 0.8s ease; }
.tile { padding: 1.5rem; }
.scroll-top-btn { position: fixed; bottom: 30px; right: 30px; width: 52px; height: 52px; background: linear-gradient(145deg, #00c6ff, #0072ff); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; opacity: 0; visibility: hidden; transition: opacity 0.3s; }
.scroll-top-btn.show { opacity: 1; visibility: visible; }
</style>
</body>
</html>