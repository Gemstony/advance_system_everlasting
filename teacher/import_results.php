<?php
// teacher/import_results.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Teacher only (level 2)
if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

$teacher_id = $account_id;

// AJAX handler for fetching subjects
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_subjects') {
    header('Content-Type: application/json');
    try {
        $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
        if (!$class_id) throw new Exception('Invalid class');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT id, class, subject FROM tbl_subject_combinations WHERE teacher = ?");
        $stmt->execute([$teacher_id]);
        $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subjects = [];
        foreach ($combinations as $comb) {
            $classes = unserialize($comb['class']);
            if (is_array($classes) && in_array($class_id, $classes)) {
                $stmt2 = $pdo->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
                $stmt2->execute([$comb['subject']]);
                $name = $stmt2->fetchColumn();
                if ($name) {
                    $subjects[] = ['id' => $comb['id'], 'name' => $name];
                }
            }
        }
        echo json_encode(['success' => true, 'subjects' => $subjects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get teacher's classes and terms
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
    $stmt->execute();
    $terms = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
                            LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
                            LEFT JOIN tbl_staff ON tbl_subject_combinations.teacher = tbl_staff.id
                            WHERE tbl_subject_combinations.teacher = ?");
    $stmt->execute([$account_id]);
    $result = $stmt->fetchAll();

    $myclasses = array();
    foreach ($result as $value) {
        $class_arr = unserialize($value[1]);
        foreach ($class_arr as $val) {
            array_push($myclasses, $val);
        }
    }
    $myclasses = array_unique($myclasses);
    sort($myclasses);

    $classes_list = [];
    if (!empty($myclasses)) {
        $placeholders = implode(',', array_fill(0, count($myclasses), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($myclasses);
        $classes_list = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    $terms = [];
    $classes_list = [];
    $_SESSION['import_result'] = ['error', "Database error: " . $e->getMessage()];
}

$importResult = $_SESSION['import_result'] ?? null;
unset($_SESSION['import_result']);

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; // Role‑aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-upload me-2"></i>
                Import Results (CSV)
                <small class="text-muted d-block fs-6 mt-1">Upload examination results from CSV file</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Import Results</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Step 1: Select Details</h3>
                    <form id="importForm" enctype="multipart/form-data" method="POST" autocomplete="OFF">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Term <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="term" id="termSelect" required style="width:100%">
                                <option selected disabled value="">Select Term</option>
                                <?php foreach ($terms as $row): ?>
                                    <option value="<?= $row[0] ?>"><?= htmlspecialchars($row[1]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Class <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="class" id="classSelect" required style="width:100%" onchange="fetch_subjects(this.value)">
                                <option selected disabled value="">Select Class</option>
                                <?php foreach ($classes_list as $row): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <select class="form-control" name="subject" id="subjectSelect" required>
                                <option selected disabled value="">Select Subject</option>
                            </select>
                            <div id="subjectLoading" class="mt-1" style="display:none"><span class="loading-spinner"></span> Loading...</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">CSV File <span class="text-danger">*</span></label>
                            <input required accept=".csv" type="file" name="file" id="csvFile" class="form-control">
                            <div class="form-text">Columns: <code>Registration Number, Student Name, Score</code>. Header required.</div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" id="previewBtn" class="btn btn-info" disabled><i class="bi bi-eye"></i> Preview & Validate</button>
                            <button type="button" id="clearBtn" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Clear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Templates</h3>
                    <div class="d-grid gap-2">
                        <a href="teacher/core/download_template.php?type=empty" class="btn btn-outline-primary" target="_blank"><i class="bi bi-file-earmark-spreadsheet"></i> Empty Template</a>
                        <a href="#" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-file-earmark-person"></i> Filled Template (with students)</a>
                    </div>
                    <hr>
                    <h5>Instructions</h5>
                    <ul>
                        <li>Use exact registration numbers from your class.</li>
                        <li>Scores: numbers 0–100 (integers or decimals).</li>
                        <li>Header row must be exactly: <code>Registration Number, Student Name, Score</code></li>
                        <li>Empty scores are ignored (no update/insert).</li>
                        <li>Existing results will be <strong>updated</strong>; new ones inserted.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section -->
    <div id="previewSection" style="display:none;">
        <div class="tile mt-3">
            <div class="tile-body">
                <h3 class="tile-title">Step 2: Preview & Confirm</h3>
                <div id="validationSummary" class="stats-card"></div>
                <div class="preview-table-container">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered preview-table" id="previewTable">
                            <thead><tr><th>#</th><th>Registration No</th><th>Student Name</th><th>Score</th><th>Status</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
                    <button type="button" id="backToFormBtn" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Form</button>
                    <button type="button" id="confirmImportBtn" class="btn btn-primary btn-lg" disabled><i class="bi bi-check-circle"></i> Confirm Import</button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal for filled template -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Download Filled Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Select class to include all students:</p>
                <select class="form-select mb-3" id="templateClassSelect">
                    <option value="">— Choose class —</option>
                    <?php foreach ($classes_list as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="btn btn-success w-100" id="downloadFilledBtn">Download</a>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>

<script>
$(document).ready(function(){
    // Initialize Select2 with responsive options
    $('.select2').select2({
        width: 'resolve',
        dropdownAutoWidth: true
    });

    window.fetch_subjects = function(classId) {
        if (!classId) { $('#subjectSelect').html('<option selected disabled value="">Select Subject</option>'); return; }
        $('#subjectLoading').show();
        $('#subjectSelect').prop('disabled', true).html('<option>Loading...</option>');
        $.ajax({
            url: 'teacher/import_results.php',
            method: 'GET',
            data: { ajax: 'get_subjects', class_id: classId },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.subjects.length) {
                    var options = '<option selected disabled value="">Select Subject</option>';
                    $.each(res.subjects, function(i, subj) { options += '<option value="' + subj.id + '">' + subj.name + '</option>'; });
                    $('#subjectSelect').html(options).prop('disabled', false);
                } else {
                    $('#subjectSelect').html('<option selected disabled value="">No subjects for this class</option>').prop('disabled', true);
                }
            },
            error: function() { $('#subjectSelect').html('<option>Error loading subjects</option>').prop('disabled', true); },
            complete: function() { $('#subjectLoading').hide(); }
        });
    };

    $('#csvFile').on('change', function() { $('#previewBtn').prop('disabled', !this.files.length); });
    $('#clearBtn').on('click', function() { $('#csvFile').val(''); $('#previewBtn').prop('disabled', true); $('#previewSection').hide(); });

    // Preview and validate
    $('#previewBtn').on('click', function() {
        var file = $('#csvFile')[0].files[0];
        var term = $('#termSelect').val();
        var classId = $('#classSelect').val();
        var subject = $('#subjectSelect').val();
        if (!file || !term || !classId || !subject) {
            Swal.fire('Missing Data', 'Please fill term, class, subject and select a CSV file.', 'error');
            return;
        }
        var formData = new FormData();
        formData.append('term', term);
        formData.append('class', classId);
        formData.append('subject', subject);
        formData.append('file', file);
        formData.append('preview', '1');

        $('#previewBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Validating...');
        $.ajax({
            url: 'teacher/core/import_results.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    displayPreview(res.data);
                    $('#previewSection').show();
                    $('html, body').animate({ scrollTop: $('#previewSection').offset().top }, 500);
                } else {
                    Swal.fire('Validation Error', res.message, 'error');
                }
            },
            error: function(xhr) { Swal.fire('Error', 'Server error: ' + xhr.statusText, 'error'); },
            complete: function() { $('#previewBtn').prop('disabled', false).html('<i class="bi bi-eye"></i> Preview & Validate'); }
        });
    });

    function displayPreview(data) {
        var rows = data.rows;
        var stats = data.stats;
        var tbody = $('#previewTable tbody');
        tbody.empty();

        rows.forEach(function(row, idx) {
            var rowClass = '';
            var statusText = '';
            if (!row.skip && row.score_final !== null) {
                rowClass = 'valid-row';
                statusText = row.existing ? 'Update' : 'Insert';
            } else if (row.score_raw === '(empty)') {
                rowClass = 'ignored-row';
                statusText = 'No score (ignored)';
            } else {
                rowClass = 'invalid-row';
                statusText = row.error ? row.error : 'Invalid';
            }

            tbody.append('<tr class="' + rowClass + '">' +
                '<td>' + (idx+1) + '</td>' +
                '<td>' + escapeHtml(row.reg_no) + '</td>' +
                '<td>' + escapeHtml(row.name) + '</td>' +
                '<td>' + escapeHtml(row.score_raw) + '</td>' +
                '<td>' + escapeHtml(statusText) + '</td>' +
                '</tr>');
        });

        var summaryHtml = '<div class="d-flex flex-wrap gap-3">' +
            '<span class="badge bg-success">Valid scores (will import): ' + stats.valid_count + '</span>' +
            '<span class="badge bg-secondary">Ignored (empty): ' + stats.ignored_empty + '</span>' +
            '<span class="badge bg-danger">Errors (invalid): ' + stats.error_count + '</span>' +
            '<span class="badge bg-info">Total rows: ' + stats.total + '</span>' +
            '</div>';
        if (stats.errors && stats.errors.length) {
            summaryHtml += '<div class="alert alert-warning mt-2 small">Errors: ' + stats.errors.slice(0,5).join('; ') + (stats.errors.length>5 ? '...' : '') + '</div>';
        }
        $('#validationSummary').html(summaryHtml);
        $('#confirmImportBtn').prop('disabled', stats.error_count > 0);
    }

    function escapeHtml(str) { return $('<div>').text(str).html(); }

    $('#confirmImportBtn').on('click', function() {
        var file = $('#csvFile')[0].files[0];
        var term = $('#termSelect').val();
        var classId = $('#classSelect').val();
        var subject = $('#subjectSelect').val();
        var formData = new FormData();
        formData.append('term', term);
        formData.append('class', classId);
        formData.append('subject', subject);
        formData.append('file', file);
        formData.append('import', '1');

        Swal.fire({ title: 'Confirm Import', text: 'Are you sure you want to import these results?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, import' }).then((result) => {
            if (result.isConfirmed) {
                $('#confirmImportBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Importing...');
                $.ajax({
                    url: 'teacher/core/import_results.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire('Success', res.message, 'success').then(() => { window.location.href = 'teacher/manage_results'; });
                        } else {
                            Swal.fire('Import Failed', res.message, 'error');
                            $('#confirmImportBtn').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Confirm Import');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Server error during import', 'error'); $('#confirmImportBtn').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Confirm Import'); }
                });
            }
        });
    });

    $('#backToFormBtn').on('click', function() { $('#previewSection').hide(); });

    $('#downloadFilledBtn').click(function(e) {
        e.preventDefault();
        var classId = $('#templateClassSelect').val();
        if (!classId) { Swal.fire('Error', 'Select a class', 'error'); return; }
        window.location.href = 'teacher/core/download_template.php?type=filled&class=' + classId;
    });

    <?php if ($importResult): ?>
        Swal.fire({ icon: '<?= $importResult[0] === 'success' ? 'success' : ($importResult[0] === 'warning' ? 'warning' : 'error') ?>', title: '<?= $importResult[0] === 'success' ? 'Success' : ($importResult[0] === 'warning' ? 'Warning' : 'Error') ?>', text: '<?= addslashes($importResult[1]) ?>', timer: 5000 });
    <?php endif; ?>
});
</script>

<style>
/* Import page specific styles – fully responsive */
.app-content {
    background: transparent !important;
}
.tile, .stats-card, .info-box, .preview-table-container {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.tile-body {
    background: transparent !important;
}
.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(0,0,0,.1);
    border-left-color: #0d6efd;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.info-box {
    background-color: rgba(248,249,250,0.95) !important;
    border-left: 4px solid #0d6efd;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
}
.preview-table-container {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 1rem;
    padding: 0.5rem;
}
.preview-table {
    font-size: 0.85rem;
    width: 100%;
}
.preview-table th {
    background: #e9ecef;
    position: sticky;
    top: 0;
}
.valid-row { background-color: #d1e7dd; }
.ignored-row { background-color: #fff3cd; }
.invalid-row { background-color: #f8d7da; }
.stats-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.5rem 1rem;
}
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Responsive fixes for small screens */
@media (max-width: 768px) {
    .tile {
        padding: 1rem;
    }
    .tile-title {
        font-size: 1.2rem;
    }
    .preview-table {
        font-size: 0.75rem;
    }
    .preview-table th, .preview-table td {
        padding: 0.3rem 0.5rem;
    }
    .stats-card .badge {
        font-size: 0.7rem;
        margin-bottom: 0.3rem;
    }
    .select2-container--default .select2-selection--single {
        height: auto;
        padding: 0.3rem;
    }
    .modal-dialog {
        margin: 1rem;
    }
    .btn {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
    }
    /* Ensure dropdowns stay within viewport on mobile */
    .select2-container--open .select2-dropdown {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        max-height: 50vh;
        overflow-y: auto;
        border-radius: 12px 12px 0 0;
    }
}
</style>
</body>
</html>