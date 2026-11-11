<?php
// student/invoices.php
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
require_once __DIR__ . '/inc/finance_functions.php';
session_start();

if ($res != "1" || $level != "3") { header("location:../"); exit; }

$student_id = $account_id;
$academic_year = '2026';

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_invoices WHERE student_id = ? AND academic_year = ? ORDER BY invoice_date DESC");
    $stmt->execute([$student_id, $academic_year]);
    $invoices = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

$module_enabled = isFeeModuleEnabled($pdo);
$fname = $student['fname']; $lname = $student['lname'];
?>
<?php require __DIR__ . '/inc/header.php'; ?>
<?php require __DIR__ . '/inc/sidebar.php'; ?>
<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="student/fees" class="btn"><i class="bi bi-wallet2"></i> Fee Dashboard</a>
        <a href="student/invoices" class="btn"><i class="bi bi-receipt"></i> My Invoices</a>
        <a href="student/payment-history" class="btn"><i class="bi bi-clock-history"></i> Payment History</a>
    </div>
    <?php if (!$module_enabled): ?>
        <div class="advice-card"><h5>Fee Module Disabled</h5><p>Please contact accountant.</p></div>
    <?php else: ?>
        <div class="tile">
            <h4>All Invoices</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Grand Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $inv):
                            $paid = $inv['grand_total'] - $inv['balance'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                            <td><?php echo $inv['invoice_date']; ?></td>
                            <td><?php echo $inv['due_date']; ?></td>
                            <td><?php echo formatCurrency($inv['grand_total']); ?></td>
                            <td><?php echo formatCurrency($paid); ?></td>
                            <td><?php echo formatCurrency($inv['balance']); ?></td>
                            <td><span class="badge bg-<?php echo ($inv['status']=='paid'?'success':($inv['status']=='partial'?'warning':'danger')); ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                            <td><a href="student/invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>// scroll button as before</script>
</body>
</html>