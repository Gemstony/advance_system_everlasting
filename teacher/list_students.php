<?php
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

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

$teacher_id = $account_id;

// Compute stats for the dashboard
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all subject combinations for this teacher
    $stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ?");
    $stmt->execute([$teacher_id]);
    $combs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $class_ids = [];
    foreach ($combs as $serialized) {
        $classes = unserialize($serialized);
        if (is_array($classes)) {
            $class_ids = array_merge($class_ids, $classes);
        }
    }
    $class_ids = array_unique($class_ids);
    sort($class_ids);

    $total_classes = count($class_ids);

    // Get total students in those classes
    $total_students = 0;
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders)");
        $stmt->execute($class_ids);
        $total_students = $stmt->fetchColumn();
    }

    // Get class details for the chip selector
    $classes_list = [];
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($class_ids);
        $classes_list = $stmt->fetchAll();
    }

} catch(PDOException $e) {
    $total_classes = 0;
    $total_students = 0;
    $classes_list = [];
    $_SESSION['reply'] = [['danger', "Database error: " . $e->getMessage()]];
}

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);

// -------------------------------------------------------------------
// AJAX handler: get student counts for given class IDs (for analytics)
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_student_counts') {
    header('Content-Type: application/json');
    try {
        $class_ids = isset($_GET['class_ids']) ? $_GET['class_ids'] : '';
        if (empty($class_ids)) throw new Exception('No class selected');
        $class_array = array_map('intval', explode(',', $class_ids));
        if (empty($class_array)) throw new Exception('Invalid class IDs');
        
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $placeholders = implode(',', array_fill(0, count($class_array), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($class_array);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($classes as $class) {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ? AND status = 1");
            $stmt2->execute([$class['id']]);
            $count = (int)$stmt2->fetchColumn();
            $result[] = [
                'id' => $class['id'],
                'name' => $class['name'],
                'student_count' => $count
            ];
        }
        echo json_encode(['success' => true, 'classes' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-people me-2"></i>
                List Students
                <small class="text-muted d-block fs-6 mt-1">View and manage students in your classes</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Students</li>
        </ul>
    </div>

    <!-- Reply / Alert Messages -->
    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bi bi-grid-3x3-gap-fill"></div>
            <div class="stat-value"><?= $total_classes ?></div>
            <div class="stat-label">Classes You Teach</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-people-fill"></div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card" id="selectedCountCard">
            <div class="stat-icon bi bi-check-square-fill"></div>
            <div class="stat-value" id="selectedCount">0</div>
            <div class="stat-label">Selected Classes</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Select one or more classes</strong> using the enhanced selector below. The list will open in a new page where you can view, export, or manage students.
    </div>

    <div class="row">
        <!-- Left Column: Class Selection -->
        <div class="col-md-6">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Choose Classes</h3>
                    <form class="app_frm list-form" method="POST" autocomplete="OFF" action="teacher/core/list_students" id="studentForm">
                        <!-- Hidden select to hold selected values for form submission -->
                        <select multiple="true" name="class[]" id="classSelectOriginal" style="display: none;"></select>
                        
                        <!-- Enhanced Chip Selector UI -->
                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <div class="flex-grow-1">
                                    <input type="text" id="classSearchInput" class="form-control" placeholder="Search classes...">
                                </div>
                                <button type="button" id="selectAllBtn" class="btn btn-outline-primary">Select All</button>
                                <button type="button" id="clearAllBtn" class="btn btn-outline-secondary">Clear</button>
                            </div>
                            <div>
                                <small class="text-muted">Quick select by level:</small>
                                <div class="mt-1">
                                    <button type="button" class="btn btn-sm btn-outline-info quick-level" data-level="Form One">Form One</button>
                                    <button type="button" class="btn btn-sm btn-outline-info quick-level" data-level="Form Two">Form Two</button>
                                    <button type="button" class="btn btn-sm btn-outline-info quick-level" data-level="Form Three">Form Three</button>
                                    <button type="button" class="btn btn-sm btn-outline-info quick-level" data-level="Form Four">Form Four</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Class Chips Container -->
                        <div id="classChipsContainer" class="class-chips-container mb-3" style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.5rem;">
                            <?php foreach ($classes_list as $class): ?>
                                <div class="class-chip" data-class-id="<?= $class['id'] ?>" data-class-name="<?= strtolower(htmlspecialchars($class['name'])) ?>">
                                    <div class="chip-info">
                                        <span class="chip-name"><?= htmlspecialchars($class['name']) ?></span>
                                    </div>
                                    <div class="chip-check">
                                        <input type="checkbox" class="class-checkbox" value="<?= $class['id'] ?>" id="chip_<?= $class['id'] ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <button type="submit" name="submit" value="1" class="btn btn-primary px-4"><i class="bi bi-list-ul me-2"></i>List Students</button>
                            <a href="teacher/list_students" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>

                    <!-- Live Preview of Selected Classes -->
                    <div class="selection-preview" id="previewBox" style="display: none;">
                        <h6 class="mb-2"><i class="bi bi-eye me-2"></i>Current Selection</h6>
                        <div id="selectedPreview"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Quick Tips + Analytics (below tips) -->
        <div class="col-md-6">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Quick Tips</h3>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Select multiple classes to see students from all at once.</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> The result page allows you to view, search, and export student lists.</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> If a class is missing, ensure it's assigned to you in Subject Combinations.</li>
                    </ul>
                </div>
            </div>

            <!-- Analytics Panel (appears only when classes are selected) -->
            <div id="analyticsContainer" style="display: none;">
                <div class="tile mt-4">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-graph-up me-2"></i>Selection Analytics</h3>
                        <div id="analyticsPanel">
                            <div class="mb-3">
                                <strong>Selected classes:</strong>
                                <div id="selectedBadges" class="mt-2"></div>
                            </div>
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="analytics-card text-center p-2">
                                        <div class="analytics-value" id="analyticsTotalClasses">0</div>
                                        <div class="analytics-label">Classes</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="analytics-card text-center p-2">
                                        <div class="analytics-value" id="analyticsTotalStudents">0</div>
                                        <div class="analytics-label">Total Students</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="analytics-card text-center p-2">
                                        <div class="analytics-value" id="analyticsAvg">0</div>
                                        <div class="analytics-label">Average per Class</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="analytics-card p-2">
                                    <div class="small text-muted mb-2">Class size distribution</div>
                                    <div id="classSizeBars"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<?php require_once('const/check-reply.php'); ?>

<script>
$(document).ready(function() {
    // ---------- Enhanced Chip Selector Logic ----------
    function getSelectedClassIds() {
        var ids = [];
        $('.class-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }
    
    function updateHiddenSelect() {
        var ids = getSelectedClassIds();
        var $hidden = $('#classSelectOriginal');
        $hidden.empty();
        $.each(ids, function(i, val) {
            $hidden.append('<option value="'+val+'" selected>');
        });
        $('#selectedCount').text(ids.length);
    }
    
    function updatePreviewAndAnalytics() {
        var ids = getSelectedClassIds();
        // Update preview badges
        if (ids.length > 0) {
            var names = [];
            $('.class-checkbox:checked').each(function() {
                var $chip = $(this).closest('.class-chip');
                var className = $chip.find('.chip-name').text();
                names.push('<span class="preview-badge">' + className + '</span>');
            });
            $('#selectedPreview').html(names.join(' '));
            $('#previewBox').show();
        } else {
            $('#previewBox').hide();
        }
        updateHiddenSelect();
        
        // Show/hide analytics container and load data
        if (ids.length > 0) {
            $('#analyticsContainer').show();
            loadAnalytics(ids);
        } else {
            $('#analyticsContainer').hide();
        }
    }
    
    // Analytics: load student counts and update panel
    function loadAnalytics(classIds) {
        var classIdStr = classIds.join(',');
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { ajax: 'get_student_counts', class_ids: classIdStr },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.classes.length) {
                    var totalStudents = 0;
                    var badgesHtml = '';
                    var barsHtml = '';
                    var maxStudents = Math.max(...res.classes.map(c => c.student_count), 1);
                    res.classes.forEach(function(cls) {
                        totalStudents += cls.student_count;
                        badgesHtml += '<span class="preview-badge">' + escapeHtml(cls.name) + ' <small>('+cls.student_count+')</small></span>';
                        var percent = (cls.student_count / maxStudents) * 100;
                        barsHtml += '<div class="mb-2">';
                        barsHtml += '<div class="d-flex justify-content-between small"><span>' + escapeHtml(cls.name) + '</span><span>' + cls.student_count + '</span></div>';
                        barsHtml += '<div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: ' + percent + '%;"></div></div>';
                        barsHtml += '</div>';
                    });
                    var avg = (totalStudents / res.classes.length).toFixed(1);
                    $('#analyticsTotalClasses').text(res.classes.length);
                    $('#analyticsTotalStudents').text(totalStudents);
                    $('#analyticsAvg').text(avg);
                    $('#selectedBadges').html(badgesHtml);
                    $('#classSizeBars').html(barsHtml);
                } else {
                    // Hide analytics if no data (should not happen, but fallback)
                    $('#analyticsContainer').hide();
                }
            },
            error: function() {
                $('#analyticsContainer').hide();
            }
        });
    }
    
    // Search filter
    $('#classSearchInput').on('input', function() {
        var filter = $(this).val().toLowerCase();
        $('.class-chip').each(function() {
            var text = $(this).data('class-name');
            if (text.indexOf(filter) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Select all visible chips
    $('#selectAllBtn').on('click', function() {
        $('.class-chip:visible .class-checkbox').prop('checked', true);
        updatePreviewAndAnalytics();
    });
    
    // Clear all chips (also hides analytics)
    $('#clearAllBtn').on('click', function() {
        $('.class-checkbox').prop('checked', false);
        updatePreviewAndAnalytics();
    });
    
    // Quick level select (additive)
    $('.quick-level').on('click', function() {
        var level = $(this).data('level');
        $('.class-chip').each(function() {
            var name = $(this).data('class-name');
            if (name.indexOf(level.toLowerCase()) === 0) {
                $(this).find('.class-checkbox').prop('checked', true);
            }
        });
        updatePreviewAndAnalytics();
    });
    
    // Individual checkbox change
    $(document).on('change', '.class-checkbox', function() {
        updatePreviewAndAnalytics();
    });
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    // Initial update
    updatePreviewAndAnalytics();
});
</script>

<style>
/* Existing styles (unchanged) plus analytics placement */
.app-content {
    background: transparent !important;
}
.stat-card, .tile, .info-box, .analytics-card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    padding: 1.2rem 1rem;
    text-align: center;
    transition: 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 2rem;
    color: #3b7cff;
    margin-bottom: 0.3rem;
}
.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    color: #1e293b;
}
.stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}
.info-box {
    border-left: 4px solid #3b7cff;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.selection-preview {
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid #e9eef2;
}
.preview-badge {
    background: white;
    padding: 0.4rem 0.8rem;
    border-radius: 2rem;
    display: inline-block;
    margin: 0.2rem;
    border: 1px solid #dee2e6;
    font-size: 0.85rem;
}
.list-form {
    background: white;
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
.tile {
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Chip selector styles */
.class-chips-container {
    background: #f8fafc;
}
.class-chip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0.8rem;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}
.class-chip:last-child {
    margin-bottom: 0;
}
.class-chip:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.chip-info {
    flex-grow: 1;
}
.chip-name {
    font-weight: 500;
}
.chip-check input {
    width: 1.2rem;
    height: 1.2rem;
    cursor: pointer;
}

/* Analytics card styles */
.analytics-card {
    padding: 0.75rem;
    text-align: center;
}
.analytics-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
}
.analytics-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}
.progress {
    background-color: #e2e8f0;
    border-radius: 1rem;
}
</style>
</body>
</html>