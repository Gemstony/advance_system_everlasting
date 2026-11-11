<?php
// student/payment-history.php
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
require_once __DIR__ . '/inc/finance_functions.php';
session_start();

if ($res != "1" || $level != "3") { header("location:../"); exit; }

$student_id = $account_id;

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_payments WHERE student_id = ? AND verification_status = 'verified' ORDER BY payment_date DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

$fname = $student['fname']; $lname = $student['lname'];
?>
<?php require __DIR__ . '/inc/header.php'; ?>
<?php require __DIR__ . '/inc/sidebar.php'; ?>
<main class="app-content">
    <div class="tile">
        <h4>Payment History</h4>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Payment Ref</th><th>Date</th><th>Amount</th><th>Method</th><th>Bank</th><th>Reference</th><th>Receipt</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pay['payment_ref']); ?></td>
                        <td><?php echo $pay['payment_date']; ?></td>
                        <td><?php echo formatCurrency($pay['paid_amount']); ?></td>
                        <td><?php echo ucfirst($pay['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($pay['bank_name']); ?></td>
                        <td><?php echo htmlspecialchars($pay['bank_reference']); ?></td>
                        <td><?php if ($pay['receipt_status']=='issued'): ?><a href="student/receipt.php?payment_id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-outline-primary">Receipt</a><?php else: ?>Not issued<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>