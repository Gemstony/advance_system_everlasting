<?php
// student/pay-fees.php
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
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if the bank accounts table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_bank_accounts'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_bank_accounts WHERE is_active = 1");
        $stmt->execute();
        $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback: use default bank accounts from the invoice
        $banks = [
            ['bank_name' => 'CRDB Bank', 'account_name' => 'EVERLASTING ACADEMY', 'account_number' => '0150803146400', 'branch' => 'Kibaha'],
            ['bank_name' => 'NMB Bank', 'account_name' => 'EVERLASTING ACADEMY', 'account_number' => '23110083454', 'branch' => 'Kibaha']
        ];
    }
    
    // Get student name
    $stmt = $pdo->prepare("SELECT fname, lname FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new Exception("Student not found.");
    }
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

$fname = $student['fname'];
$lname = $student['lname'];
?>
<?php require __DIR__ . '/inc/header.php'; ?>
<?php require __DIR__ . '/inc/sidebar.php'; ?>
<main class="app-content">
    <div class="tile">
        <h4>Make a Payment</h4>
        <div class="alert alert-info">
            <p><strong>Payment Instructions:</strong></p>
            <ul>
                <li>Make your payment to any of the bank accounts below.</li>
                <li>Use your <strong>Student Registration Number (<?php echo htmlspecialchars($student_id); ?>)</strong> as the payment reference.</li>
                <li>After making the payment, submit the bank deposit slip to the school accountant or upload via this portal (coming soon).</li>
                <li>The accountant will verify your payment and issue an official receipt.</li>
            </ul>
        </div>
        <h5>School Bank Accounts</h5>
        <?php if (count($banks) > 0): ?>
        <table class="table table-bordered">
            <thead><tr><th>Bank Name</th><th>Account Name</th><th>Account Number</th><th>Branch</th></tr></thead>
            <tbody>
                <?php foreach ($banks as $bank): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bank['bank_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bank['account_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bank['account_number'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bank['branch'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-warning">No bank accounts configured. Please contact the school accountant.</div>
        <?php endif; ?>
        <p><strong>Important:</strong> Always include your student ID as reference. Late payments may incur penalties.</p>
        <a href="student/fees" class="btn btn-secondary">Back to Dashboard</a>
    </div>
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
</body>
</html>