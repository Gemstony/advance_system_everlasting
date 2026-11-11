<?php
// accountant/receipt.php – print receipt, generates if not exists
require_once __DIR__ . '/../student/inc/finance_functions.php';
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
session_start();
if ($res != "1" || $level != "3") { header("location:../"); exit; }
if (!isset($_GET['payment_id'])) { header("location:accountant/payments.php"); exit; }
$payment_id = intval($_GET['payment_id']);

$pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT p.*, s.fname, s.lname, s.id AS student_id, c.name AS class_name FROM tbl_fee_payments p JOIN tbl_students s ON p.student_id = s.id JOIN tbl_classes c ON s.class = c.id WHERE p.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();
if (!$payment) { header("location:accountant/payments.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM tbl_fee_receipts WHERE payment_id = ?");
$stmt->execute([$payment_id]);
$receipt = $stmt->fetch();
if (!$receipt) {
    $receipt_no = generateReceiptNumber($pdo);
    $stmt = $pdo->prepare("INSERT INTO tbl_fee_receipts (receipt_no, payment_id, printed_by) VALUES (?, ?, ?)");
    $stmt->execute([$receipt_no, $payment_id, $account_id]);
    $stmt = $pdo->prepare("UPDATE tbl_fee_payments SET receipt_status = 'issued' WHERE id = ?");
    $stmt->execute([$payment_id]);
    $stmt = $pdo->prepare("SELECT * FROM tbl_fee_receipts WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $receipt = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html><head><title>Receipt</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>@media print { .no-print { display: none; } } body { background: #f1f5f9; } .receipt-container { max-width: 800px; margin: 30px auto; background: white; border-radius: 20px; padding: 2rem; }</style></head>
<body><div class="receipt-container"><div class="text-center"><h2>Everlasting Academy</h2><p>P.O.BOX 70991, Kibaha | Tel: +255 794457583</p></div><h4 class="text-center">OFFICIAL RECEIPT</h4><p class="text-center">Receipt No: <?php echo htmlspecialchars($receipt['receipt_no']); ?></p><table class="table"><tr><th>Student:</th><td><?php echo htmlspecialchars($payment['fname'].' '.$payment['lname']); ?></td></tr><tr><th>Reg No:</th><td><?php echo $payment['student_id']; ?></td></tr><tr><th>Class:</th><td><?php echo $payment['class_name']; ?></td></tr><tr><th>Payment Ref:</th><td><?php echo $payment['payment_ref']; ?></td></tr><tr><th>Date:</th><td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td></tr><tr><th>Amount:</th><td><strong><?php echo formatCurrency($payment['paid_amount']); ?></strong></td></tr><tr><th>Method:</th><td><?php echo ucfirst($payment['payment_method']); ?></td></tr><tr><th>Bank:</th><td><?php echo htmlspecialchars($payment['bank_name']); ?></td></tr><tr><th>Reference:</th><td><?php echo htmlspecialchars($payment['bank_reference']); ?></td></tr></table><div class="text-center mt-4"><button class="btn btn-primary no-print" onclick="window.print();">Print Receipt</button><br><br><p>Thank you for your payment.</p></div></div><script>window.print();</script></body></html>