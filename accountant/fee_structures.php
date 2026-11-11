<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res != "1" || $level != "3") { header("location:../"); exit; }

$pdo = new PDO("mysql:host=".DBHost.";dbname=".DBName.";charset=".DBCharset, DBUser, DBPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle add/edit/delete actions (simplified)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_structure'])) {
        $stmt = $pdo->prepare("INSERT INTO tbl_fee_structures (academic_year, class_id, tuition_fee, boarding_fee, development_fee, other_mandatory_fee) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['academic_year'], $_POST['class_id'], $_POST['tuition_fee'], $_POST['boarding_fee'], $_POST['development_fee'], $_POST['other_mandatory_fee']]);
    } elseif (isset($_POST['delete_structure'])) {
        $stmt = $pdo->prepare("DELETE FROM tbl_fee_structures WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("location:accountant/fee_structures.php");
    exit;
}

$structures = $pdo->query("SELECT fs.*, c.name AS class_name FROM tbl_fee_structures fs JOIN tbl_classes c ON fs.class_id = c.id ORDER BY fs.academic_year DESC, c.name")->fetchAll();
$classes = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name")->fetchAll();
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>
<main class="app-content">
    <div class="tile">
        <h4>Fee Structures</h4>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal">+ Add New Structure</button>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Academic Year</th><th>Class</th><th>Tuition</th><th>Boarding</th><th>Development</th><th>Other</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($structures as $fs): ?>
                    <tr>
                        <td><?php echo $fs['academic_year']; ?></td>
                        <td><?php echo htmlspecialchars($fs['class_name']); ?></td>
                        <td><?php echo formatCurrency($fs['tuition_fee']); ?></td>
                        <td><?php echo formatCurrency($fs['boarding_fee']); ?></td>
                        <td><?php echo formatCurrency($fs['development_fee']); ?></td>
                        <td><?php echo formatCurrency($fs['other_mandatory_fee']); ?></td>
                        <td><?php echo $fs['active'] ? 'Yes' : 'No'; ?></td>
                        <td><form method="post" onsubmit="return confirm('Delete?');"><input type="hidden" name="id" value="<?php echo $fs['id']; ?>"><button type="submit" name="delete_structure" class="btn btn-sm btn-danger">Delete</button></form></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Add Fee Structure</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label>Academic Year</label><input type="text" name="academic_year" class="form-control" value="2026" required></div><div class="mb-3"><label>Class</label><select name="class_id" class="form-select" required><?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label>Tuition Fee</label><input type="number" name="tuition_fee" class="form-control" required></div><div class="mb-3"><label>Boarding Fee</label><input type="number" name="boarding_fee" class="form-control"></div><div class="mb-3"><label>Development Fee</label><input type="number" name="development_fee" class="form-control"></div><div class="mb-3"><label>Other Mandatory Fee</label><input type="number" name="other_mandatory_fee" class="form-control"></div></div><div class="modal-footer"><button type="submit" name="add_structure" class="btn btn-primary">Save</button></div></form></div></div></div>
</main>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>