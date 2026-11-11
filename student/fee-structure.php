<?php
// student/fee-structure.php
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../const/school.php';
require_once __DIR__ . '/../const/check_session.php';
require_once __DIR__ . '/inc/finance_functions.php';
session_start();

if ($res != "1" || $level != "3") { header("location:../"); exit; }

$student_id = $account_id;

try {
    $pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
    $stmt = $pdo->prepare("SELECT class FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $class_id = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT fs.*, c.name AS class_name FROM tbl_fee_structures fs JOIN tbl_classes c ON fs.class_id = c.id WHERE fs.class_id = ? AND fs.academic_year = '2026' AND fs.active = 1");
    $stmt->execute([$class_id]);
    $structure = $stmt->fetch();
    
    if ($structure) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_fee_items WHERE fee_structure_id = ?");
        $stmt->execute([$structure['id']]);
        $items = $stmt->fetchAll();
    }
    
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
        <h4>Fee Structure for <?php echo htmlspecialchars($student['class_name']); ?> (2026)</h4>
        <?php if ($structure): ?>
            <table class="table table-bordered">
                <thead><tr><th>Item</th><th>Amount (TZS)</th></tr></thead>
                <tbody>
                    <tr><td>Tuition Fee</td><td><?php echo formatCurrency($structure['tuition_fee']); ?></td></tr>
                    <tr><td>Boarding Fee</td><td><?php echo formatCurrency($structure['boarding_fee']); ?></td></tr>
                    <tr><td>Development Fee</td><td><?php echo formatCurrency($structure['development_fee']); ?></td></tr>
                    <tr><td>Other Mandatory Fee</td><td><?php echo formatCurrency($structure['other_mandatory_fee']); ?></td></tr>
                    <?php foreach ($items as $item): ?>
                    <tr><td><?php echo htmlspecialchars($item['item_name']); ?></td><td><?php echo formatCurrency($item['amount']); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-primary"><td><strong>Total Expected (Yearly)</strong></td><td><strong><?php echo formatCurrency($structure['tuition_fee'] + $structure['boarding_fee'] + $structure['development_fee'] + $structure['other_mandatory_fee'] + array_sum(array_column($items, 'amount'))); ?></strong></td></tr>
                </tbody>
            </table>
            <p class="text-muted">Note: Fees are payable in installments as per invoice. Bank details: CRDB 0150803146400, NMB 23110083454.</p>
        <?php else: ?>
            <div class="alert alert-warning">Fee structure not defined for your class yet. Please contact accountant.</div>
        <?php endif; ?>
    </div>
</main>
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>