<?php
// teacher/export_results.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

// permission guard (teacher)
if ($res !== "1" || $level !== "2") {
    header("Location: ../");
    exit;
}

// helpers
function parse_class_field($raw) {
    $out = [];
    if ($raw === null) return $out;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) {
        foreach ($un as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
        foreach ($j as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    preg_match_all('/\d+/', (string)$raw, $m);
    if (!empty($m[0])) foreach ($m[0] as $v) $out[] = (int)$v;
    return array_values(array_unique($out));
}

// Build a robust action URL for the POST handler located in the same directory
$dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
$processPath = $dir . '/export_results_process.php';

// fetch classes + meta
try {
    $dsn = 'mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset;
    $pdo = new PDO($dsn, DBUser, DBPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name ASC");
    $classesList = $stmt->fetchAll();

    $classMeta = [];
    foreach ($classesList as $c) $classMeta[$c['id']] = ['name' => $c['name'], 'subjects' => []];

    $stmt = $pdo->query("SELECT sc.id AS scid, sc.class AS class_raw, sc.subject AS subject_id, sc.teacher AS teacher_id, s.name AS subject_name, st.fname AS t_fname, st.lname AS t_lname
                         FROM tbl_subject_combinations sc
                         LEFT JOIN tbl_subjects s ON sc.subject = s.id
                         LEFT JOIN tbl_staff st ON sc.teacher = st.id
                         ORDER BY sc.id ASC");
    $scRows = $stmt->fetchAll();

    foreach ($scRows as $sc) {
        $classesForSc = parse_class_field($sc['class_raw']);
        $teacherName = trim(($sc['t_fname'] ?? '') . ' ' . ($sc['t_lname'] ?? ''));
        $subjectName = $sc['subject_name'] ?? 'Subject';
        foreach ($classesForSc as $cid) {
            if (!isset($classMeta[$cid])) continue;
            $classMeta[$cid]['subjects'][] = trim($subjectName . ($teacherName ? ' — ' . $teacherName : ''));
        }
    }

    // Compute stats for dashboard
    $total_classes = count($classMeta);
    $total_students = 0;
    if (!empty($classMeta)) {
        $ids = array_keys($classMeta);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders)");
        $stmt->execute($ids);
        $total_students = $stmt->fetchColumn();
    }
    $classes_with_subjects = 0;
    foreach ($classMeta as $meta) {
        if (!empty($meta['subjects'])) $classes_with_subjects++;
    }

} catch (PDOException $e) {
    echo "DB error: " . htmlspecialchars($e->getMessage());
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
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; // Role‑aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-file-spreadsheet me-2"></i>
                Export Results
                <small class="text-muted d-block fs-6 mt-1">Download examination results in Excel format</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Export Results</li>
        </ul>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bi bi-grid-3x3-gap-fill"></div>
            <div class="stat-value"><?= $total_classes ?></div>
            <div class="stat-label">Total Classes</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-people-fill"></div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-book-fill"></div>
            <div class="stat-value"><?= $classes_with_subjects ?></div>
            <div class="stat-label">Classes with Subjects</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Export Options:</strong> Choose export mode, behavior for missing scores, and additional columns. Use "Preview" to see estimated row counts.
    </div>

    <!-- Main Form Card -->
    <div class="form-section">
        <div class="section-title"><i class="bi bi-sliders2"></i> Export Settings</div>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form id="exportForm" method="post" action="<?php echo htmlspecialchars($processPath); ?>">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Export mode</label>
                        <div class="mt-2">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="mode" value="filled" id="modeFilled" checked>
                                <label class="form-check-label" for="modeFilled">
                                    Export filled results <span class="text-muted">(current view)</span>
                                </label>
                                <div class="form-text">Uses the session context (class, subject, term).</div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="all" id="modeAll">
                                <label class="form-check-label" for="modeAll">
                                    Export all students <span class="text-muted">(select classes below)</span>
                                </label>
                                <div class="form-text">Export all students from chosen classes, optionally with scores.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4" id="classesBox" style="display:none;">
                        <label class="form-label fw-bold">Select classes (multiple allowed)</label>
                        
                        <!-- Advanced Chip Selector for Classes -->
                        <div class="advanced-class-selector">
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
                        <div id="classChipsContainer" class="class-chips-container mt-2" style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.5rem;">
                            <?php foreach ($classMeta as $cid => $meta): ?>
                                <div class="class-chip" data-class-id="<?= $cid ?>" data-class-name="<?= strtolower(htmlspecialchars($meta['name'])) ?>">
                                    <div class="chip-info">
                                        <span class="chip-name"><?= htmlspecialchars($meta['name']) ?></span>
                                    </div>
                                    <div class="chip-check">
                                        <input type="checkbox" class="class-checkbox" value="<?= $cid ?>" id="chip_<?= $cid ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Hidden select to hold selected values for form submission -->
                        <select multiple="true" name="classes[]" id="classSelectOriginal" style="display: none;"></select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Missing-score behavior</label>
                        <div class="mt-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="missing" value="zero" id="missingZero" checked>
                                <label class="form-check-label" for="missingZero">Output <strong>0</strong></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="missing" value="blank" id="missingBlank">
                                <label class="form-check-label" for="missingBlank">Leave blank cell</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Advanced options</label>
                        <div class="mt-2">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="only_with_scores" value="1" id="onlyScores">
                                <label class="form-check-label" for="onlyScores">Only students with scores</label>
                                <div class="form-text">Exclude students who have no recorded scores.</div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_meta" value="1" id="includeMeta">
                                <label class="form-check-label" for="includeMeta">Include CLASS/SUBJECT/TEACHER columns</label>
                                <div class="form-text">Adds extra columns for better context.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap mt-3 pt-2">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-download me-2"></i>Export
                </button>
                <button type="button" id="previewBtn" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="bi bi-eye me-2"></i>Preview counts
                </button>
                <a href="teacher/manage_results" class="btn btn-secondary btn-lg px-4">Cancel</a>
                <div class="preview-stats ms-auto" id="previewStats" aria-live="polite"></div>
            </div>
        </form>

        <!-- Live Preview Area -->
        <div class="live-preview" id="livePreviewArea">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calculator-fill text-primary"></i>
                <h6 class="mb-0">Export Preview</h6>
            </div>
            <div id="previewDetails" class="small mt-2"></div>
        </div>
    </div>

    <!-- Classes & Subjects Reference -->
    <div class="form-section">
        <div class="section-title"><i class="bi bi-journal-bookmark-fill"></i> Classes & Assigned Subjects / Teachers</div>
        <div class="class-subject-grid">
            <?php foreach ($classMeta as $cid => $m): ?>
                <div class="class-card">
                    <div class="class-card-header">
                        <i class="bi bi-building"></i>
                        <span><?= htmlspecialchars($m['name']) ?></span>
                    </div>
                    <div class="class-card-body">
                        <?php if (!empty($m['subjects'])): ?>
                            <?php foreach ($m['subjects'] as $subj): ?>
                                <span class="subject-tag"><?= htmlspecialchars($subj) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em class="text-muted small">No subjects assigned</em>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(function(){
    var $mode = $('input[name="mode"]');
    var $classesBox = $('#classesBox');
    var $previewBtn = $('#previewBtn');
    var $previewStats = $('#previewStats');
    var $livePreviewArea = $('#livePreviewArea');
    var $previewDetails = $('#previewDetails');

    // ---------- Advanced Class Selector Logic ----------
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
    }
    
    function updateSelection() {
        updateHiddenSelect();
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
        updateSelection();
    });
    
    $('#clearAllBtn').on('click', function() {
        $('.class-checkbox').prop('checked', false);
        updateSelection();
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
        updateSelection();
    });
    
    // Individual checkbox change
    $(document).on('change', '.class-checkbox', function() {
        updateSelection();
    });
    
    // Initialise (no pre-selected classes)
    updateSelection();

    // Original mode toggle
    function toggleMode() {
        var val = $('input[name="mode"]:checked').val();
        if (val === 'all') $classesBox.slideDown(); else $classesBox.slideUp();
    }
    $mode.on('change', toggleMode);
    toggleMode();

    // Preview functionality (unchanged)
    $previewBtn.on('click', function(){
        var form = $('#exportForm');
        var data = form.serializeArray();
        data.push({name: 'preview', value: '1'});
        $previewStats.html('<span class="spinner-border spinner-border-sm" role="status"></span> Loading...');
        $livePreviewArea.hide();
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            dataType: 'json',
            timeout: 15000
        }).done(function(resp){
            if (!resp || typeof resp.success === 'undefined') {
                $previewStats.text('Preview failed');
                return;
            }
            if (!resp.success) {
                $previewStats.text('Preview: ' + (resp.message || 'No data'));
                return;
            }
            var html = '📊 Students: <strong>' + resp.total_students + '</strong>';
            if (typeof resp.with_scores !== 'undefined') html += ' | ✅ With scores: <strong>' + resp.with_scores + '</strong>';
            if (typeof resp.missing_scores !== 'undefined') html += ' | ❌ Missing scores: <strong>' + resp.missing_scores + '</strong>';
            $previewStats.html(html);

            var details = '';
            if (resp.classes && resp.classes.length) {
                details += '<div class="mt-2"><strong>Classes included:</strong> ' + resp.classes.join(', ') + '</div>';
            }
            if (resp.terms && resp.terms.length) {
                details += '<div><strong>Terms:</strong> ' + resp.terms.join(', ') + '</div>';
            }
            if (details) {
                $previewDetails.html(details);
                $livePreviewArea.slideDown();
            } else {
                $livePreviewArea.slideUp();
            }
        }).fail(function(xhr, st, err){
            $previewStats.text('Preview request failed: ' + (err || st));
            $livePreviewArea.slideUp();
        });
    });
});
</script>

<style>
/* Advanced glass-morphism layout for Export Results */
.app-content {
    background: transparent !important;
}
.stat-card, .form-section, .info-box, .live-preview, .class-card {
    background: rgba(255, 255, 255, 0.96) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.1);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.stat-card {
    padding: 1.5rem 1rem;
    text-align: center;
}
.stat-icon {
    font-size: 2.4rem;
    color: #3b7cff;
    margin-bottom: 0.5rem;
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
}
.stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: #475569;
}
.info-box {
    border-left: 5px solid #3b7cff;
    padding: 1rem 1.5rem;
    border-radius: 20px;
    margin-bottom: 2rem;
}
.form-section {
    padding: 1.8rem;
    margin-bottom: 2rem;
}
.section-title {
    font-weight: 700;
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #0f172a;
    border-bottom: 2px solid rgba(59,124,255,0.2);
    padding-bottom: 0.6rem;
}
.form-label {
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}
.form-text {
    font-size: 0.75rem;
    color: #5b6e8c;
}
.btn-primary {
    background: linear-gradient(105deg, #3b7cff, #2a5ed0);
    border: none;
    border-radius: 40px;
    box-shadow: 0 4px 12px rgba(59,124,255,0.3);
    transition: 0.2s;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59,124,255,0.4);
}
.btn-outline-secondary, .btn-secondary {
    border-radius: 40px;
}
.live-preview {
    margin-top: 1.5rem;
    padding: 1rem 1.5rem;
    display: none;
    border-left: 4px solid #3b7cff;
}
.class-subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 0.25rem;
}
.class-card {
    background: rgba(255,255,255,0.9);
    border-radius: 20px;
    overflow: hidden;
    transition: 0.2s;
}
.class-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}
.class-card-header {
    background: rgba(59,124,255,0.1);
    padding: 0.75rem 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.class-card-body {
    padding: 0.75rem 1rem;
}
.subject-tag {
    background: white;
    border-radius: 30px;
    padding: 0.25rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-block;
    margin: 0.2rem 0.25rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
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
</style>
</body>
</html>