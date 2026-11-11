<?php
// student/invoice.php
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
require_once __DIR__ . '/inc/finance_functions.php';
session_start();

if ($res != "1" || $level != "3") { header("location:../"); exit; }
if (!isset($_GET['id'])) { header("location:student/invoices"); exit; }
$invoice_id = intval($_GET['id']);
$student_id = $account_id;

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_invoices WHERE id = ? AND student_id = ?");
    $stmt->execute([$invoice_id, $student_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) { header("location:student/invoices"); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_invoice_lines WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $lines = $stmt->fetchAll();
    
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
        <h4>Invoice Details</h4>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($invoice['invoice_no']); ?></p>
                <p><strong>Date:</strong> <?php echo $invoice['invoice_date']; ?></p>
                <p><strong>Due Date:</strong> <?php echo $invoice['due_date']; ?></p>
                <p><strong>Status:</strong> <span class="badge bg-<?php echo ($invoice['status']=='paid'?'success':($invoice['status']=='partial'?'warning':'danger')); ?>"><?php echo ucfirst($invoice['status']); ?></span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Student:</strong> <?php echo htmlspecialchars($student['fname'].' '.$student['lname']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name']); ?></p>
                <p><strong>Academic Year:</strong> <?php echo $invoice['academic_year']; ?></p>
            </div>
        </div>
        <table class="table table-bordered">
            <thead><tr><th>Description</th><th>Amount</th></tr></thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                <tr><td><?php echo htmlspecialchars($line['description']); ?></td><td><?php echo formatCurrency($line['amount']); ?></td></tr>
                <?php endforeach; ?>
                <tr class="table-light"><td><strong>Subtotal</strong></td><td><?php echo formatCurrency($invoice['subtotal']); ?></td></tr>
                <?php if ($invoice['discount'] > 0): ?><tr><td>Discount</td><td>- <?php echo formatCurrency($invoice['discount']); ?></td></tr><?php endif; ?>
                <?php if ($invoice['penalty'] > 0): ?><tr><td>Penalty</td><td>+ <?php echo formatCurrency($invoice['penalty']); ?></td></tr><?php endif; ?>
                <tr class="table-primary"><td><strong>Grand Total</strong></td><td><strong><?php echo formatCurrency($invoice['grand_total']); ?></strong></td></tr>
                <tr><td>Amount Paid</td><td><?php echo formatCurrency($invoice['grand_total'] - $invoice['balance']); ?></td></tr>
                <tr class="table-warning"><td><strong>Balance Due</strong></td><td><strong><?php echo formatCurrency($invoice['balance']); ?></strong></td></tr>
            </tbody>
        </table>
        <?php if ($invoice['balance'] > 0): ?>
            <a href="student/pay-fees?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-success">Pay Now</a>
        <?php endif; ?>
        <a href="student/invoices" class="btn btn-secondary">Back to Invoices</a>
    </div>
</main>
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>