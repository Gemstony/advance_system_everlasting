<?php
// accountant/payments.php – record new payment and verify pending ones
require_once __DIR__ . '/../student/inc/finance_functions.php';
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
session_start();
if ($res != "1" || $level != "3") { header("location:../"); exit; }
$accountant = ['fname' => $fname, 'lname' => $lname, 'id' => $account_id, 'email' => $email];

$pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verification handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $status = $_POST['verification_status'];
    $stmt = $pdo->prepare("UPDATE tbl_fee_payments SET verification_status = ?, verified_by = ? WHERE id = ?");
    $stmt->execute([$status, $account_id, $payment_id]);
    if ($status == 'verified') {
        $stmt = $pdo->prepare("SELECT student_id FROM tbl_fee_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $student_id = $stmt->fetchColumn();
        updateFeeAccountBalance($pdo, $student_id, '2026');
    }
    $success = true;
}
// Add payment handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $method = $_POST['payment_method'];
    $bank_name = $_POST['bank_name'];
    $bank_ref = $_POST['bank_reference'];
    $payment_ref = generatePaymentReference($pdo);
    $stmt = $pdo->prepare("INSERT INTO tbl_fee_payments (payment_ref, student_id, paid_amount, payment_date, payment_method, bank_name, bank_reference, received_by, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$payment_ref, $student_id, $amount, $payment_date, $method, $bank_name, $bank_ref, $account_id]);
    $success = true;
}

$filter = $_GET['filter'] ?? '';
if ($filter == 'pending') {
    $stmt = $pdo->prepare("SELECT p.*, CONCAT(s.fname,' ',s.lname) AS student_name FROM tbl_fee_payments p JOIN tbl_students s ON p.student_id = s.id WHERE p.verification_status = 'pending' ORDER BY p.created_at DESC");
    $stmt->execute();
    $pending = $stmt->fetchAll();
    $show_pending_only = true;
} else {
    $show_pending_only = false;
    $stmt = $pdo->prepare("SELECT p.*, CONCAT(s.fname,' ',s.lname) AS student_name FROM tbl_fee_payments p JOIN tbl_students s ON p.student_id = s.id WHERE p.verification_status = 'pending' ORDER BY p.created_at DESC");
    $stmt->execute();
    $pending = $stmt->fetchAll();
}
$students = $pdo->query("SELECT id, CONCAT(fname,' ',lname) AS name FROM tbl_students ORDER BY fname")->fetchAll();
$banks = $pdo->query("SELECT * FROM tbl_bank_accounts WHERE is_active = 1")->fetchAll();

function generatePaymentReference($pdo) {
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_fee_payments WHERE payment_ref LIKE 'PAY-{$year}-%'");
    $count = $stmt->fetchColumn() + 1;
    return "PAY-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
}
$page_title = "Payment Management";
?>
<?php require __DIR__ . '/inc/header.php'; ?>
<?php require __DIR__ . '/inc/sidebar.php'; ?>
<main class="app-content">
    <?php if (isset($success)) echo '<div class="alert alert-success">Operation successful.</div>'; ?>
    <div class="row">
        <div class="col-md-6">
            <div class="tile"><h4>Record New Payment</h4>
                <form method="post">
                    <div class="mb-3"><label>Student</label><select name="student_id" class="form-select" required><?php foreach($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label>Amount</label><input type="number" name="amount" class="form-control" required></div>
                    <div class="mb-3"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" required></div>
                    <div class="mb-3"><label>Method</label><select name="payment_method" class="form-select"><option>cash</option><option>bank</option><option>mobile_money</option></select></div>
                    <div class="mb-3"><label>Bank Name</label><input type="text" name="bank_name" class="form-control"></div>
                    <div class="mb-3"><label>Bank Reference</label><input type="text" name="bank_reference" class="form-control"></div>
                    <button type="submit" name="add_payment" class="btn btn-primary">Record Payment</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="tile"><h4>Pending Verifications <?php echo $show_pending_only ? '(filtered)' : ''; ?></h4>
                <?php if(count($pending)==0): ?><p>No pending payments.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Student</th><th>Ref</th><th>Amount</th><th>Date</th><th>Method</th><th>Action</th></tr></thead><tbody><?php foreach($pending as $p): ?><tr><td><?php echo htmlspecialchars($p['student_name']); ?></td><td><?php echo $p['payment_ref']; ?></td><td><?php echo formatCurrency($p['paid_amount']); ?></td><td><?php echo $p['payment_date']; ?></td><td><?php echo ucfirst($p['payment_method']); ?></td><td><form method="post" style="display:inline-block;"><input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>"><button type="submit" name="verification_status" value="verified" class="btn btn-sm btn-success">Verify</button> <button type="submit" name="verification_status" value="rejected" class="btn btn-sm btn-danger">Reject</button></form></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>