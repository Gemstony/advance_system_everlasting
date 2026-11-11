<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

// Helper function to safely unserialize class list
function parse_class_list($raw) {
    if (is_array($raw)) return $raw;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) return array_map('intval', $un);
    $json = @json_decode($raw, true);
    if (is_array($json)) return array_map('intval', $json);
    $raw_trim = trim((string)$raw);
    if ($raw_trim === '') return [];
    if (ctype_digit($raw_trim)) return [(int)$raw_trim];
    $parts = preg_split('/\s*,\s*/', $raw_trim);
    $ints = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (ctype_digit($p)) $ints[] = (int)$p;
    }
    return $ints;
}

// ========== FETCH STUDENT DATA FOR HEADER ==========
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

// Build $student array for header compatibility
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];

// ========== ORIGINAL SUBJECT DATA FETCH ==========
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Get current active term
    $current_term = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $current_term_id = $current_term ? $current_term['id'] : 0;
    $current_term_name = $current_term ? $current_term['name'] : 'None';

    // Get all subject combinations for the student's class
    $stmt = $pdo->prepare("
        SELECT sc.id, sc.class, sc.teacher, s.name AS subject_name, st.fname, st.lname
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        LEFT JOIN tbl_staff st ON sc.teacher = st.id
    ");
    $stmt->execute();
    $all_combs = $stmt->fetchAll();

    // Filter subjects for this student's class
    $subjects = [];
    $teachers = [];
    $subject_count = 0;

    foreach ($all_combs as $comb) {
        $class_list = parse_class_list($comb['class']);
        if (in_array($class, $class_list)) {
            $subject_count++;
            $teacher_name = trim(($comb['fname'] ?? '') . ' ' . ($comb['lname'] ?? ''));
            if (!empty($teacher_name)) {
                $teachers[] = $teacher_name;
            }
            $subjects[$comb['id']] = [
                'sc_id'       => $comb['id'],
                'subject'     => $comb['subject_name'] ?? 'Unknown',
                'teacher'     => $teacher_name ?: 'Not assigned',
                'teacher_id'  => $comb['teacher']
            ];
        }
    }
    $unique_teachers = count(array_unique($teachers));

    // Get all exam results for this student
    $stmt = $pdo->prepare("
        SELECT r.subject_combination, r.score, r.term, t.name as term_name
        FROM tbl_exam_results r
        LEFT JOIN tbl_terms t ON r.term = t.id
        WHERE r.student = ?
        ORDER BY r.term ASC
    ");
    $stmt->execute([$account_id]);
    $all_results = $stmt->fetchAll();

    // Build data structures
    $scores_by_subject = [];
    $latest_scores = [];
    $subject_averages = [];

    foreach ($all_results as $r) {
        $sc_id = $r['subject_combination'];
        if (!isset($scores_by_subject[$sc_id])) {
            $scores_by_subject[$sc_id] = [];
        }
        $scores_by_subject[$sc_id][] = $r;
    }

    // Compute averages and latest
    foreach ($scores_by_subject as $sc_id => $scores) {
        $total = array_sum(array_column($scores, 'score'));
        $count = count($scores);
        $subject_averages[$sc_id] = round($total / $count);
        $latest_scores[$sc_id] = end($scores);
    }

    // Best and worst subjects by average
    $best_subject = null;
    $worst_subject = null;
    $best_avg = 0;
    $worst_avg = 100;
    foreach ($subject_averages as $sc_id => $avg) {
        if ($avg > $best_avg) {
            $best_avg = $avg;
            $best_subject = $subjects[$sc_id]['subject'] ?? 'Unknown';
        }
        if ($avg < $worst_avg) {
            $worst_avg = $avg;
            $worst_subject = $subjects[$sc_id]['subject'] ?? 'Unknown';
        }
    }

    // Prepare data for current term chart
    $chart_subjects = [];
    $chart_scores = [];
    if ($current_term_id) {
        foreach ($subjects as $sc_id => $subj) {
            $score_for_term = null;
            foreach ($scores_by_subject[$sc_id] ?? [] as $r) {
                if ($r['term'] == $current_term_id) {
                    $score_for_term = $r['score'];
                    break;
                }
            }
            if ($score_for_term !== null) {
                $chart_subjects[] = $subj['subject'];
                $chart_scores[] = $score_for_term;
            }
        }
    }

    // Get grading system (for later use, not directly in this page)
    $grading = $pdo->query("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();

    // Count distinct terms with results
    $terms_with_results = count(array_unique(array_column($all_results, 'term')));

    // For class averages
    $class_averages = [];
    if ($current_term_id) {
        foreach ($subjects as $sc_id => $subj) {
            $stmt = $pdo->prepare("
                SELECT AVG(score) as avg_score
                FROM tbl_exam_results
                WHERE subject_combination = ? AND term = ?
            ");
            $stmt->execute([$sc_id, $current_term_id]);
            $avg = $stmt->fetchColumn();
            $class_averages[$sc_id] = $avg ? round($avg) : null;
        }
    }

    // Prepare trend data for sparklines
    $trend_data = [];
    foreach ($scores_by_subject as $sc_id => $scores) {
        $trend_data[$sc_id] = [
            'terms' => array_column($scores, 'term_name'),
            'scores' => array_column($scores, 'score')
        ];
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-journal-bookmark-fill me-2"></i> My Subjects</h1></div>
        <div>
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <!-- Summary Cards with improved spacing -->
    <div class="summary-card-wrapper mb-4">
        <div class="summary-card">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="stat-badge"><i class="bi bi-book me-1"></i> Total Subjects: <?php echo $subject_count; ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-badge"><i class="bi bi-person-badge me-1"></i> Teachers: <?php echo $unique_teachers; ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-badge"><i class="bi bi-calendar-check me-1"></i> Current Term: <?php echo htmlspecialchars($current_term_name); ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-badge"><i class="bi bi-file-text me-1"></i> Terms with Results: <?php echo $terms_with_results; ?></div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="stat-badge best-badge"><i class="bi bi-trophy me-1"></i> Best Subject: <?php echo htmlspecialchars($best_subject ?: 'N/A'); ?> (Avg: <?php echo $best_avg ?: 'N/A'; ?>%)</div>
                </div>
                <div class="col-md-6">
                    <div class="stat-badge worst-badge"><i class="bi bi-exclamation-triangle me-1"></i> Needs Improvement: <?php echo htmlspecialchars($worst_subject ?: 'N/A'); ?> (Avg: <?php echo $worst_avg ?: 'N/A'; ?>%)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart for current term with toggle – proper spacing and fit -->
    <?php if (!empty($chart_subjects)): ?>
    <div class="chart-card-wrapper mb-4">
        <div class="tile p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="tile-title mb-0">Current Term Performance (<?php echo htmlspecialchars($current_term_name); ?>)</h4>
                <div class="btn-group chart-toggle" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="barBtn">Bar</button>
                    <button type="button" class="btn btn-outline-primary" id="lineBtn">Line</button>
                </div>
            </div>
            <div class="chart-container" style="position: relative; height: 320px; width: 100%;">
                <canvas id="termChart" style="display: block; width: 100%; height: 100%;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Subjects Table -->
    <div class="table-card-wrapper">
        <div class="tile p-4">
            <h4 class="tile-title mb-4">Subject List</h4>
            <div class="table-responsive">
                <table class="table table-hover" id="srmsTable">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Latest Score</th>
                            <th>Trend</th>
                            <th>Class Avg</th>
                            <th>Average</th>
                            <th>Target</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr><td colspan="8" class="text-center">No subjects found for your class.</td></tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $sc_id => $subj): 
                                $latest = $latest_scores[$sc_id] ?? null;
                                $score = $latest ? $latest['score'] : null;
                                $term_name = $latest ? $latest['term_name'] : '—';
                                $avg = $subject_averages[$sc_id] ?? null;
                                $class_avg = $class_averages[$sc_id] ?? null;
                                $trend = $trend_data[$sc_id] ?? null;
                                $score_class = '';
                                if ($score !== null) {
                                    if ($score >= 75) $score_class = 'score-high';
                                    elseif ($score >= 50) $score_class = 'score-medium';
                                    else $score_class = 'score-low';
                                }
                                $target = 75;
                                $progress_deg = $avg ? ($avg / 100) * 360 : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subj['subject']); ?></strong></td>
                                <td><?php echo htmlspecialchars($subj['teacher']); ?></td>
                                <td>
                                    <?php if ($score !== null): ?>
                                        <div class="d-flex align-items-center">
                                            <span class="<?php echo $score_class; ?> me-2"><?php echo $score; ?>%</span>
                                            <div class="progress-bar-custom" style="width: 60px;">
                                                <div class="progress-bar-fill" style="width: <?php echo $score; ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <?php if ($trend && count($trend['scores']) > 1): ?>
                                        <canvas class="trend-sparkline" id="sparkline-<?php echo $sc_id; ?>" data-scores="<?php echo htmlspecialchars(json_encode($trend['scores'])); ?>"></canvas>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <?php if ($class_avg !== null): ?>
                                        <?php echo $class_avg; ?>%
                                        <?php if ($score !== null): ?>
                                            <?php if ($score > $class_avg): ?>
                                                <i class="bi bi-arrow-up-short above-average" title="Above class average"></i>
                                            <?php elseif ($score < $class_avg): ?>
                                                <i class="bi bi-arrow-down-short below-average" title="Below class average"></i>
                                            <?php else: ?>
                                                <i class="bi bi-dash" title="Equal to class average"></i>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </div>
                                <td><?php echo $avg ? $avg.'%' : '—'; ?> </div>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress-circle" style="background: conic-gradient(#4361ee <?php echo $progress_deg; ?>deg, #e9ecef 0deg);"></div>
                                        <span><?php echo $target; ?>%</span>
                                    </div>
                                 </div>
                                <td>
                                    <a href="student/results?subject=<?php echo urlencode($subj['subject']); ?>" class="btn btn-sm btn-outline-primary" title="View all results for this subject">
                                        <i class="bi bi-bar-chart"></i> Results
                                    </a>
                                 </div>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Page-specific styles (glass‑morphic, theme‑compatible, with proper spacing) -->
<style>
    .app-content {
        background: transparent !important;
    }
    /* Wrappers add consistent bottom margin between card blocks */
    .summary-card-wrapper, .chart-card-wrapper, .table-card-wrapper {
        margin-bottom: 2rem;
    }
    /* Glass cards */
    .summary-card, .tile {
        background: rgba(255, 255, 255, 0.92) !important;
        backdrop-filter: blur(8px);
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        padding: 1.2rem;
    }
    .tile {
        padding: 1.5rem;
    }
    /* Chart canvas gets white background for visibility */
    .chart-container canvas {
        background: white !important;
        border-radius: 16px;
        padding: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    /* Ensure all text inside cards is dark enough */
    .summary-card, .tile, .tile *:not(.btn) {
        color: #1e293b !important;
    }
    /* Summary badges */
    .stat-badge {
        background: rgba(238,242,246,0.85);
        border-radius: 40px;
        padding: 0.5rem 1.2rem;
        display: inline-block;
        margin-right: 0.8rem;
        margin-bottom: 0.8rem;
        font-weight: 500;
        transition: all 0.2s;
        color: #1e293b;
    }
    .best-badge { background: #06d6a0; color: white !important; }
    .worst-badge { background: #ef476f; color: white !important; }
    .progress-bar-custom { height: 6px; border-radius: 10px; background: #e9ecef; width: 100%; }
    .progress-bar-fill { height: 6px; border-radius: 10px; background: #4361ee; }
    .score-high { color: #06d6a0; font-weight: 700; }
    .score-medium { color: #ffb703; font-weight: 700; }
    .score-low { color: #ef476f; font-weight: 700; }
    .trend-sparkline { width: 80px; height: 30px; display: inline-block; background: white; border-radius: 6px; padding: 2px; }
    .above-average { color: #06d6a0; }
    .below-average { color: #ef476f; }
    .progress-circle { width: 36px; height: 36px; border-radius: 50%; display: inline-block; margin-right: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .btn-outline-primary { border-color: #4361ee; color: #4361ee; background: transparent; }
    .btn-outline-primary:hover { background: #4361ee; color: white; }
    .table thead th { background: rgba(241,245,249,0.8); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: none; }
    .table td { vertical-align: middle; background: transparent; }
    .table-hover tbody tr:hover { background: rgba(0,0,0,0.02); }
    .chart-toggle .btn { border-radius: 30px; padding: 0.3rem 1.2rem; background: rgba(255,255,255,0.7); backdrop-filter: blur(4px); }
    .chart-toggle .btn.active { background: #4361ee; color: white; border-color: #4361ee; }
    .breadcrumb {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        border-radius: 40px;
        padding: 0.5rem 1rem;
    }
    .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #1e293b, #2d3a5e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    /* Dark mode adjustments */
    body.dark .summary-card, body.dark .tile {
        background: rgba(30, 41, 59, 0.92) !important;
        color: #f1f5f9;
    }
    body.dark .summary-card, body.dark .tile, body.dark .tile *:not(.btn) {
        color: #e2e8f0 !important;
    }
    body.dark .stat-badge {
        background: rgba(51, 65, 85, 0.85);
        color: #e2e8f0;
    }
    body.dark .table thead th {
        background: rgba(30, 41, 59, 0.8);
        color: #cbd5e1;
    }
    body.dark .table td {
        color: #cbd5e1;
    }
    body.dark .chart-container canvas {
        background: #1e293b !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    body.dark .trend-sparkline {
        background: #1e293b;
    }
    body.dark .chart-toggle .btn {
        background: rgba(30, 41, 59, 0.7);
        color: #e2e8f0;
    }
    body.dark .breadcrumb {
        background: rgba(30, 41, 59, 0.7);
    }
    body.dark .breadcrumb-item a, body.dark .breadcrumb-item.active {
        color: #cbd5e1 !important;
    }
    body.dark .app-title h1 {
        background: linear-gradient(135deg, #f1f5f9, #94a3b8);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
</style>

<!-- Required scripts -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function(){
    $('#srmsTable').DataTable({
        "sort": false,
        "pageLength": 25,
        "language": { "search": "Filter subjects:" }
    });

    <?php if (!empty($chart_subjects)): ?>
    const ctx = document.getElementById('termChart').getContext('2d');
    let currentChart;

    function createChart(type) {
        if (currentChart) currentChart.destroy();
        currentChart = new Chart(ctx, {
            type: type,
            data: {
                labels: <?php echo json_encode($chart_subjects); ?>,
                datasets: [{
                    label: 'Score (%)',
                    data: <?php echo json_encode($chart_scores); ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: '#4361ee',
                    borderWidth: type === 'line' ? 2 : 1,
                    tension: 0.1,
                    fill: type === 'line' ? false : true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.1)' } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#fff', titleColor: '#212529', bodyColor: '#495057' }
                }
            }
        });
    }

    createChart('bar');

    $('#barBtn').on('click', function(){
        $(this).addClass('active').siblings().removeClass('active');
        createChart('bar');
    });
    $('#lineBtn').on('click', function(){
        $(this).addClass('active').siblings().removeClass('active');
        createChart('line');
    });
    <?php endif; ?>

    // Draw sparklines
    $('.trend-sparkline').each(function(){
        var canvas = this;
        var scores = $(canvas).data('scores');
        if (!scores || scores.length < 2) return;
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: scores.map((_, i) => i+1),
                datasets: [{
                    data: scores,
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, min: 0, max: 100 } }
            }
        });
    });
});
</script>
</body>
</html>