<?php
// teacher/manage_results.php
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

// -------------------------------------------------------------------
// AJAX handler for fetching subjects
// -------------------------------------------------------------------
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

// -------------------------------------------------------------------
// AJAX handler for fetching performance statistics (enhanced with top/bottom students)
// -------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_performance') {
    header('Content-Type: application/json');
    try {
        $term = isset($_GET['term']) ? (int)$_GET['term'] : 0;
        $class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
        $subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

        if (!$term || !$class || !$subject) throw new Exception('Missing parameters');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get all results with student details for this subject/class/term
        $stmt = $pdo->prepare("
            SELECT er.score, s.id as reg_no, s.fname, s.mname, s.lname
            FROM tbl_exam_results er
            LEFT JOIN tbl_students s ON er.student = s.id
            WHERE er.class = ? AND er.subject_combination = ? AND er.term = ?
            ORDER BY er.score DESC
        ");
        $stmt->execute([$class, $subject, $term]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $scores = array_column($results, 'score');

        // Get previous term scores for trend
        $prev_term = $term - 1;
        $stmt2 = $pdo->prepare("SELECT score FROM tbl_exam_results
                                 WHERE class = ? AND subject_combination = ? AND term = ?");
        $stmt2->execute([$class, $subject, $prev_term]);
        $prev_scores = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        // Helper functions
        $avg = count($scores) ? round(array_sum($scores)/count($scores), 2) : 0;
        $median = function($arr) {
            sort($arr);
            $c = count($arr);
            if ($c == 0) return 0;
            $mid = floor(($c-1)/2);
            return ($c % 2) ? $arr[$mid] : round(($arr[$mid] + $arr[$mid+1])/2, 2);
        };
        $mode = function($arr) {
            $freq = array_count_values($arr);
            arsort($freq);
            return key($freq);
        };
        $stdDev = function($arr) use ($avg) {
            $variance = 0;
            foreach ($arr as $v) $variance += pow($v - $avg, 2);
            return count($arr) ? round(sqrt($variance / count($arr)), 2) : 0;
        };

        $stats = [
            'count' => count($scores),
            'average' => $avg,
            'median' => $median($scores),
            'mode' => count($scores) ? $mode($scores) : 0,
            'std_dev' => $stdDev($scores),
            'min' => count($scores) ? min($scores) : 0,
            'max' => count($scores) ? max($scores) : 0,
            'pass_rate' => 0,
            'distribution' => [0,0,0,0],
            'prev_average' => count($prev_scores) ? round(array_sum($prev_scores)/count($prev_scores), 2) : 0,
            'top_students' => [],
            'bottom_students' => []
        ];

        if ($stats['count'] > 0) {
            $pass_count = 0;
            foreach ($scores as $score) {
                if ($score >= 30) $pass_count++;
                if ($score >= 0 && $score <= 29) $stats['distribution'][0]++;
                elseif ($score >= 30 && $score <= 44) $stats['distribution'][1]++;
                elseif ($score >= 45 && $score <= 64) $stats['distribution'][2]++;
                else $stats['distribution'][3]++;
            }
            $stats['pass_rate'] = round(($pass_count / $stats['count']) * 100, 1);

            // Top 3 students
            $top3 = array_slice($results, 0, 3);
            foreach ($top3 as $t) {
                $stats['top_students'][] = [
                    'reg' => $t['reg_no'],
                    'name' => trim($t['fname'].' '.$t['mname'].' '.$t['lname']),
                    'score' => $t['score']
                ];
            }
            // Bottom 3 students
            $bottom3 = array_slice($results, -3);
            foreach ($bottom3 as $b) {
                $stats['bottom_students'][] = [
                    'reg' => $b['reg_no'],
                    'name' => trim($b['fname'].' '.$b['mname'].' '.$b['lname']),
                    'score' => $b['score']
                ];
            }
        }

        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// Get teacher's classes and terms
// -------------------------------------------------------------------
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
        foreach ($class_arr as $val) array_push($myclasses, $val);
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
    $_SESSION['reply'] = [['danger', "Database error: " . $e->getMessage()]];
}

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);

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
                <i class="bi bi-table me-2"></i>
                View Results
                <small class="text-muted d-block fs-6 mt-1">Examination results and performance analytics</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Results</li>
        </ul>
    </div>

    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <div class="row">
        <!-- Selection Form (Left) -->
        <div class="col-lg-5">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-funnel me-2"></i>Select Criteria</h3>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2 fs-4"></i> Choose a term, class, and subject to view results.
                    </div>
                    <form method="POST" action="teacher/core/view_results" id="viewForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Term <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="term" id="termSelect" required style="width:100%;">
                                <option selected disabled>Select Term</option>
                                <?php foreach ($terms as $row): ?>
                                    <option value="<?= $row[0] ?>"><?= htmlspecialchars($row[1]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Class <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="class" id="classSelect" required style="width:100%;" onchange="fetchSubjects(this.value);">
                                <option selected disabled>Select Class</option>
                                <?php foreach ($classes_list as $row): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <select class="form-control" name="subject" id="subjectSelect" required>
                                <option selected disabled>Select One</option>
                            </select>
                            <div id="subjectLoading" class="mt-1" style="display:none;"><span class="loading-spinner"></span> Loading subjects...</div>
                        </div>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-eye me-2"></i>View Results</button>
                        <a href="teacher/dashboard" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Performance Analytics (Right) -->
        <div class="col-lg-7">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-graph-up me-2"></i>Performance Insights</h3>

                    <div id="performancePlaceholder" class="text-center py-5 text-muted">
                        <i class="bi bi-bar-chart-line fs-1 d-block mb-3"></i>
                        <p>Select a subject to see detailed analytics and forecasts.</p>
                    </div>

                    <div id="performanceContent" style="display:none;"></div>
                    <div id="performanceChartContainer" style="height:220px; margin:20px 0; display:none;">
                        <canvas id="scoreChart"></canvas>
                    </div>
                    <div id="topStudentsContainer" style="display:none;" class="mb-3"></div>
                    <div id="bottomStudentsContainer" style="display:none;" class="mb-3"></div>
                    <div id="predictionBox" style="display:none;"></div>
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
<script src="select2/dist/js/select2.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    $('.select2').select2();
    let chartInstance = null;

    window.fetchSubjects = function(classId) {
        if (!classId) {
            $('#subjectSelect').html('<option selected disabled>Select One</option>');
            return;
        }
        $('#subjectLoading').show();
        $('#subjectSelect').prop('disabled', true).html('<option>Loading...</option>');
        $.ajax({
            url: 'teacher/manage_results.php',
            method: 'GET',
            data: { ajax: 'get_subjects', class_id: classId },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.subjects.length) {
                    let opts = '<option selected disabled>Select Subject</option>';
                    $.each(res.subjects, function(i, s) { opts += '<option value="'+s.id+'">'+s.name+'</option>'; });
                    $('#subjectSelect').html(opts).prop('disabled', false);
                } else {
                    $('#subjectSelect').html('<option selected disabled>No subjects for this class</option>').prop('disabled', true);
                }
            },
            error: function() {
                $('#subjectSelect').html('<option selected disabled>Failed to load subjects</option>').prop('disabled', true);
            },
            complete: function() { $('#subjectLoading').hide(); }
        });
    };

    $('#subjectSelect').on('change', function() {
        const term = $('#termSelect').val();
        const classId = $('#classSelect').val();
        const subject = $(this).val();
        if (!term || !classId || !subject) {
            $('#performancePlaceholder').show();
            $('#performanceContent, #performanceChartContainer, #topStudentsContainer, #bottomStudentsContainer, #predictionBox').hide();
            return;
        }
        $('#performancePlaceholder').html('<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading analytics...</p>').show();
        $('#performanceContent, #performanceChartContainer, #topStudentsContainer, #bottomStudentsContainer, #predictionBox').hide();

        $.ajax({
            url: 'teacher/manage_results.php',
            method: 'GET',
            data: { ajax: 'get_performance', term: term, class: classId, subject: subject },
            dataType: 'json',
            success: function(res) {
                if (res.success) displayPerformance(res.stats);
                else $('#performancePlaceholder').html('<i class="bi bi-exclamation-triangle fs-1 d-block mb-3 text-warning"></i><p>No data available.</p>').show();
            },
            error: function() {
                $('#performancePlaceholder').html('<i class="bi bi-exclamation-triangle fs-1 d-block mb-3 text-danger"></i><p>Error loading data.</p>').show();
            }
        });
    });

    function displayPerformance(s) {
        $('#performancePlaceholder').hide();
        $('#performanceContent, #performanceChartContainer, #topStudentsContainer, #bottomStudentsContainer, #predictionBox').show();

        // Stats cards
        const cards = `
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon bi bi-people"></div><div class="stat-value">${s.count}</div><div class="stat-label">Students</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-bar-chart"></div><div class="stat-value">${s.average}%</div><div class="stat-label">Average</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-graph-up"></div><div class="stat-value">${s.median}%</div><div class="stat-label">Median</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-star"></div><div class="stat-value">${s.mode}%</div><div class="stat-label">Mode</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-arrow-up-circle"></div><div class="stat-value">${s.max}%</div><div class="stat-label">Max</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-arrow-down-circle"></div><div class="stat-value">${s.min}%</div><div class="stat-label">Min</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-check-circle"></div><div class="stat-value">${s.pass_rate}%</div><div class="stat-label">Pass Rate</div></div>
                <div class="stat-card"><div class="stat-icon bi bi-shuffle"></div><div class="stat-value">${s.std_dev}</div><div class="stat-label">Std Dev</div></div>
            </div>
        `;
        $('#performanceContent').html(cards);

        // Chart
        if (chartInstance) chartInstance.destroy();
        const ctx = document.getElementById('scoreChart').getContext('2d');
        const total = s.distribution.reduce((a,b) => a+b, 0);
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['F (0-29)', 'D (30-44)', 'C (45-64)', 'B/A (65+)'],
                datasets: [{
                    label: 'Students',
                    data: s.distribution,
                    backgroundColor: ['#dc3545', '#ffc107', '#0d6efd', '#198754'],
                    borderRadius: 8,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.raw} student(s) (${((ctx.raw/total)*100).toFixed(1)}%)`
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, stepSize: 1, title: { display: true, text: 'Number of Students' } }
                }
            }
        });

        // Top students
        if (s.top_students.length) {
            let topHtml = '<div class="section-title"><i class="bi bi-trophy-fill text-warning me-1"></i>Top Performers</div>';
            s.top_students.forEach((stu, idx) => {
                const rankClass = idx === 0 ? 'rank-1' : (idx === 1 ? 'rank-2' : 'rank-3');
                topHtml += `
                    <div class="student-row">
                        <div class="d-flex align-items-center">
                            <div class="student-rank ${rankClass}">${idx+1}</div>
                            <span class="student-name">${stu.name}</span>
                        </div>
                        <span class="student-score">${stu.score}%</span>
                    </div>
                `;
            });
            $('#topStudentsContainer').html(topHtml);
        }

        // Bottom students
        if (s.bottom_students.length) {
            let bottomHtml = '<div class="section-title"><i class="bi bi-arrow-down-short text-danger me-1"></i>Needs Improvement</div>';
            s.bottom_students.reverse().forEach((stu, idx) => {
                bottomHtml += `
                    <div class="student-row">
                        <div class="d-flex align-items-center">
                            <span class="student-name">${stu.name}</span>
                        </div>
                        <span class="student-score">${stu.score}%</span>
                    </div>
                `;
            });
            $('#bottomStudentsContainer').html(bottomHtml);
        }

        // Trend and forecast
        let trendHtml = '';
        if (s.prev_average > 0) {
            const change = (s.average - s.prev_average).toFixed(1);
            const arrow = change > 0 ? '▲' : (change < 0 ? '▼' : '•');
            const cls = change > 0 ? 'text-success' : (change < 0 ? 'text-danger' : 'text-secondary');
            trendHtml = `<span class="${cls} fw-bold">${arrow} ${Math.abs(change)}% vs previous term</span>`;
        } else {
            trendHtml = '<span class="text-secondary">No previous term data</span>';
        }
        let forecast = s.average;
        if (s.pass_rate < 80) forecast = Math.min(100, s.average * 1.05).toFixed(1);
        else forecast = s.average.toFixed(1);
        const forecastDiff = (forecast - s.average).toFixed(1);
        const forecastArrow = forecastDiff > 0 ? '▲' : (forecastDiff < 0 ? '▼' : '•');

        const predictionHtml = `
            <div class="insight-badge">
                <div class="d-flex align-items-center mb-2"><i class="bi bi-lightbulb fs-4 me-2 text-primary"></i><strong class="fs-6">Performance Forecast</strong></div>
                <p class="mb-1">Current average: <strong>${s.average}%</strong> ${trendHtml}</p>
                <p class="mb-0">Projected next term: <strong>${forecast}%</strong> <span class="${forecastDiff > 0 ? 'text-success' : (forecastDiff < 0 ? 'text-danger' : 'text-secondary')} fw-bold">${forecastArrow} ${Math.abs(forecastDiff)}%</span></p>
            </div>
        `;
        $('#predictionBox').html(predictionHtml);
    }
});
</script>

<style>
/* Manage results page specific styles */
:root {
    --primary: #0d6efd;
    --success: #198754;
    --warning: #ffc107;
    --danger: #dc3545;
    --light-bg: #f8fafc;
}
.app-content {
    background: transparent !important;
}
.tile, .stat-card, .chart-container, .insight-badge {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(0,0,0,.1);
    border-left-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.8rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    padding: 1rem 0.5rem;
    text-align: center;
    transition: all 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 1.8rem;
    color: var(--primary);
    opacity: 0.8;
    margin-bottom: 0.3rem;
}
.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    line-height: 1.2;
    color: #1e293b;
}
.stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #64748b;
    font-weight: 500;
}
.chart-container {
    padding: 1.2rem;
    margin: 1.5rem 0;
}
.insight-badge {
    border-left: 6px solid var(--primary);
    padding: 1.2rem;
    margin: 1.2rem 0;
}
.student-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.8rem;
    background: white;
    border-radius: 2rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    border: 1px solid #f1f5f9;
}
.student-rank {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 600;
    margin-right: 0.8rem;
}
.rank-1 { background: gold; color: #1e293b; }
.rank-2 { background: silver; color: #1e293b; }
.rank-3 { background: #cd7f32; color: white; }
.student-name {
    flex: 1;
    font-weight: 500;
    color: #1e293b;
}
.student-score {
    font-weight: 600;
    color: var(--primary);
    background: #eef2ff;
    padding: 0.2rem 0.8rem;
    border-radius: 2rem;
    font-size: 0.9rem;
}
.section-title {
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    margin: 1rem 0 0.5rem 0;
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
</style>
</body>
</html>