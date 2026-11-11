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

// Prepare data for header
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

// Helper: parse class list
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

// ------------------------------------------------------------
// Gather dashboard statistics
// ------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $teacher_id = isset($account_id) ? (int)$account_id : (int)$id;
    if ($teacher_id <= 0) throw new Exception("Invalid teacher ID.");

    // 1. Subject combinations
    $stmt = $pdo->prepare("
        SELECT sc.id, sc.class, s.id AS subject_id, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        WHERE sc.teacher = ?
    ");
    $stmt->execute([$teacher_id]);
    $combinations = $stmt->fetchAll();
    if (empty($combinations)) throw new Exception("You are not assigned to any subject combination.");

    // 2. Collect class IDs and details
    $class_ids = [];
    $comb_details = [];
    $subject_ids = [];
    foreach ($combinations as $c) {
        $class_list = parse_class_list($c['class']);
        $class_list = array_values(array_filter(array_map('intval', $class_list)));
        $class_ids = array_merge($class_ids, $class_list);
        $subject_ids[] = $c['subject_id'];
        $comb_details[$c['id']] = [
            'subject'   => $c['subject_name'] ?? 'Unknown',
            'classes'   => $class_list,
            'sc_id'     => $c['id'],
            'subject_id'=> $c['subject_id']
        ];
    }
    $class_ids = array_unique($class_ids);
    $subject_ids = array_unique($subject_ids);

    $my_subject = count($subject_ids);
    $my_class   = count($class_ids);

    // 3. Students count
    $my_students = 0;
    $students_by_class = [];
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt = $pdo->prepare("SELECT class, COUNT(*) as cnt FROM tbl_students WHERE class IN ($placeholders) AND status = 1 GROUP BY class");
        $stmt->execute(array_values($class_ids));
        $students_by_class = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $my_students = array_sum($students_by_class);
    }

    // 4. Current active term
    $current_term = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $current_term_id = $current_term ? (int)$current_term['id'] : 0;
    $current_term_name = $current_term ? $current_term['name'] : 'None';

    // 5. All terms (for rotation)
    $all_terms = $pdo->query("SELECT id, name FROM tbl_terms ORDER BY id DESC")->fetchAll();
    $all_term_ids = array_column($all_terms, 'id');
    $term_names = array_column($all_terms, 'name');

    // 6. For each term, compute:
    //    - per-subject average (for subject progress bars)
    //    - per-student overall average (for top/bottom progress bars)
    $sc_ids = array_keys($comb_details);
    $term_subject_averages = []; // term_id => [subject_name => avg]
    $term_student_rankings = []; // term_id => array of [name, avg] sorted desc

    if (!empty($sc_ids) && !empty($all_term_ids)) {
        $sc_placeholders = implode(',', array_fill(0, count($sc_ids), '?'));
        $class_placeholders = implode(',', array_fill(0, count($class_ids), '?'));

        foreach ($all_term_ids as $tid) {
            // Subject averages for this term
            $stmt = $pdo->prepare("
                SELECT sc.id, AVG(r.score) as avg_score
                FROM tbl_exam_results r
                JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id
                WHERE r.term = ? AND sc.id IN ($sc_placeholders)
                GROUP BY sc.id
            ");
            $stmt->execute(array_merge([$tid], $sc_ids));
            $subj_avgs = [];
            foreach ($stmt->fetchAll() as $row) {
                $subj_avgs[$comb_details[$row['id']]['subject']] = round($row['avg_score'], 1);
            }
            $term_subject_averages[$tid] = $subj_avgs;

            // Student overall averages (only those in teacher's classes)
            $stmt = $pdo->prepare("
                SELECT r.student, AVG(r.score) as avg_score, s.fname, s.lname
                FROM tbl_exam_results r
                JOIN tbl_students s ON r.student = s.id
                WHERE r.term = ? AND r.subject_combination IN ($sc_placeholders)
                  AND s.class IN ($class_placeholders)
                GROUP BY r.student
            ");
            $params = array_merge([$tid], $sc_ids, array_values($class_ids));
            $stmt->execute($params);
            $students = [];
            foreach ($stmt->fetchAll() as $row) {
                $students[] = [
                    'name' => $row['fname'] . ' ' . $row['lname'],
                    'avg' => round($row['avg_score'], 1)
                ];
            }
            usort($students, fn($a, $b) => $b['avg'] <=> $a['avg']);
            $term_student_rankings[$tid] = $students;
        }
    }

    // 7. Subject analytics & pending counts (current term only – for the subject performance cards)
    $subject_analytics = [];
    $pending_by_comb = [];
    $results_entered_current = 0;
    if ($current_term_id && !empty($comb_details)) {
        foreach ($comb_details as $sc_id => $comb) {
            $class_list = $comb['classes'];
            if (empty($class_list)) continue;

            $placeholders = implode(',', array_fill(0, count($class_list), '?'));
            $params = array_merge([$current_term_id, $sc_id], array_values($class_list));

            // Count pending results
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT s.id)
                FROM tbl_students s
                LEFT JOIN tbl_exam_results r 
                    ON s.id = r.student 
                    AND r.term = ? 
                    AND r.subject_combination = ?
                WHERE s.class IN ($placeholders) 
                    AND s.status = 1 
                    AND r.id IS NULL
            ");
            $stmt->execute($params);
            $pending = (int)$stmt->fetchColumn();
            if ($pending > 0) {
                $pending_by_comb[] = [
                    'subject' => $comb['subject'],
                    'count'   => $pending,
                    'sc_id'   => $sc_id
                ];
            }

            // Get results for this subject & term
            $stmt = $pdo->prepare("
                SELECT r.score
                FROM tbl_exam_results r
                JOIN tbl_students s ON r.student = s.id
                WHERE r.term = ? AND r.subject_combination = ?
                AND s.class IN ($placeholders)
            ");
            $stmt->execute(array_merge([$current_term_id, $sc_id], array_values($class_list)));
            $results = $stmt->fetchAll();
            $num_results = count($results);
            $results_entered_current += $num_results;

            if ($num_results > 0) {
                $scores = array_column($results, 'score');
                $avg_score = array_sum($scores) / $num_results;
                $pass_count = count(array_filter($scores, fn($s) => $s >= 50));
                $subject_analytics[$sc_id] = [
                    'subject'    => $comb['subject'],
                    'avg'        => round($avg_score, 1),
                    'pass_rate'  => round(($pass_count / $num_results) * 100, 1),
                    'total'      => $num_results,
                    'pending'    => $pending
                ];
            } else {
                $subject_analytics[$sc_id] = [
                    'subject'    => $comb['subject'],
                    'avg'        => null,
                    'pass_rate'  => null,
                    'total'      => 0,
                    'pending'    => $pending
                ];
            }
        }
    }

    // 8. Grade distribution data (linear progress bars)
    $grade_labels = [];
    $grade_counts = [];
    $grade_percentages = [];
    if ($current_term_id && !empty($comb_details)) {
        $sc_placeholders = implode(',', array_fill(0, count($sc_ids), '?'));
        $grading = $pdo->query("SELECT name, min, max FROM tbl_grade_system ORDER BY min")->fetchAll();
        $stmt = $pdo->prepare("
            SELECT score
            FROM tbl_exam_results
            WHERE term = ? AND subject_combination IN ($sc_placeholders)
        ");
        $stmt->execute(array_merge([$current_term_id], $sc_ids));
        $scores = $stmt->fetchAll();
        $grade_count = array_fill_keys(array_column($grading, 'name'), 0);
        foreach ($scores as $row) {
            $score = (float)$row['score'];
            foreach ($grading as $g) {
                if ($score >= $g['min'] && $score <= $g['max']) {
                    $grade_count[$g['name']]++;
                    break;
                }
            }
        }
        $grade_labels = array_keys($grade_count);
        $grade_counts = array_values($grade_count);
        $total_grade = array_sum($grade_counts);
        foreach ($grade_counts as $cnt) {
            $grade_percentages[] = $total_grade > 0 ? round(($cnt / $total_grade) * 100, 1) : 0;
        }
    }

    // 9. Recent results (last 10)
    $recent_results = [];
    if (!empty($comb_details)) {
        $sc_placeholders = implode(',', array_fill(0, count($sc_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT r.student, r.score, r.term, t.name as term_name, s.fname, s.lname, sub.name as subject_name
            FROM tbl_exam_results r
            LEFT JOIN tbl_students s ON r.student = s.id
            LEFT JOIN tbl_terms t ON r.term = t.id
            LEFT JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id
            LEFT JOIN tbl_subjects sub ON sc.subject = sub.id
            WHERE sc.id IN ($sc_placeholders)
            ORDER BY r.id DESC
            LIMIT 10
        ");
        $stmt->execute($sc_ids);
        $recent_results = $stmt->fetchAll();
    }

    // 10. Upcoming events
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM tbl_events 
        WHERE audience IN ('teachers', 'both') 
          AND event_date >= ?
        ORDER BY event_date ASC, start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $upcoming_events = $stmt->fetchAll();

} catch (Exception $e) {
    die("<h3>Database Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-emoji-smile me-2"></i>
                Hello, <?php echo htmlspecialchars($fname . ' ' . $lname); ?>
                <small class="text-muted d-block fs-6 mt-1">Teacher Dashboard – <?php echo htmlspecialchars($school_name); ?></small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Dashboard</li>
        </ul>
    </div>

    <!-- Quick Actions -->
    <div class="quick-action mb-4">
        <a href="teacher/manage_results" class="btn btn-success"><i class="bi bi-pencil-square"></i> Enter Results</a>
        <a href="teacher/import_results" class="btn btn-primary"><i class="bi bi-upload"></i> Import Results</a>
        <a href="teacher/list_students" class="btn btn-info"><i class="bi bi-people"></i> My Students</a>
        <a href="teacher/grading-system" class="btn btn-secondary"><i class="bi bi-award"></i> Grading System</a>
    </div>

    <!-- Pending tasks alert -->
    <?php if (!empty($pending_by_comb)): ?>
        <div class="alert-box mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Pending Results:</strong> You have missing results for the following subjects.
            <ul class="mt-2 mb-0">
                <?php foreach ($pending_by_comb as $p): ?>
                    <li><?php echo htmlspecialchars($p['subject']); ?> – <?php echo $p['count']; ?> student(s) without results
                        <a href="teacher/manage_results?subject=<?php echo $p['sc_id']; ?>" class="btn btn-sm btn-link p-0 ms-2">Enter now</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- KPI Cards (linked) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="teacher/combinations" class="text-decoration-none">
                <div class="stat-card d-flex align-items-center">
                    <div class="stat-icon me-3"><i class="bi bi-book"></i></div>
                    <div><div class="stat-value"><?php echo number_format($my_subject); ?></div><div class="stat-label">Subjects Taught</div></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="teacher/list_students" class="text-decoration-none">
                <div class="stat-card d-flex align-items-center">
                    <div class="stat-icon me-3"><i class="bi bi-building"></i></div>
                    <div><div class="stat-value"><?php echo number_format($my_class); ?></div><div class="stat-label">Classes</div></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="teacher/list_students" class="text-decoration-none">
                <div class="stat-card d-flex align-items-center">
                    <div class="stat-icon me-3"><i class="bi bi-people"></i></div>
                    <div><div class="stat-value"><?php echo number_format($my_students); ?></div><div class="stat-label">Total Students</div></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="teacher/manage_results" class="text-decoration-none">
                <div class="stat-card d-flex align-items-center">
                    <div class="stat-icon me-3"><i class="bi bi-clipboard-data"></i></div>
                    <div><div class="stat-value"><?php echo number_format($results_entered_current); ?></div><div class="stat-label">Results Entered (<?php echo htmlspecialchars($current_term_name); ?>)</div></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Subject Performance Overview (Cards) – unchanged -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="tile p-3">
                <h4 class="tile-title"><i class="bi bi-bar-chart-steps me-2"></i> Subject Performance – <?php echo htmlspecialchars($current_term_name); ?></h4>
                <div class="row">
                    <?php if (empty($subject_analytics)): ?>
                        <div class="col-12"><p class="text-muted">No results entered yet for the current term.</p></div>
                    <?php else: ?>
                        <?php foreach ($subject_analytics as $sc_id => $subj): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="subject-card">
                                    <div class="subject-header">
                                        <strong><?php echo htmlspecialchars($subj['subject']); ?></strong>
                                        <?php if ($subj['pending'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $subj['pending']; ?> pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($subj['total'] > 0): ?>
                                        <div class="subject-stats">
                                            <div>Average: <strong><?php echo $subj['avg']; ?>%</strong></div>
                                            <div>Pass Rate: <strong><?php echo $subj['pass_rate']; ?>%</strong></div>
                                            <div class="progress mt-2" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $subj['pass_rate']; ?>%"></div>
                                                <div class="progress-bar bg-danger" style="width: <?php echo 100 - $subj['pass_rate']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">No results entered yet.</div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <a href="teacher/manage_results?subject=<?php echo $sc_id; ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress-bar style panels (Top 5, Needs Improvement, Subject Averages) – rotate every 8 seconds -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="tile p-3 h-100 progress-panel">
                <h4 class="tile-title"><i class="bi bi-trophy-fill me-2 text-warning"></i> <span id="topPanelTitle">Top 5 Students</span></h4>
                <div id="topStudentsProgress" class="progress-list">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tile p-3 h-100 progress-panel">
                <h4 class="tile-title"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <span id="bottomPanelTitle">Needs Improvement</span></h4>
                <div id="bottomStudentsProgress" class="progress-list">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tile p-3 h-100 progress-panel">
                <h4 class="tile-title"><i class="bi bi-bar-chart me-2"></i> <span id="subjectAvgPanelTitle">Subject Averages</span></h4>
                <div id="subjectAvgProgress" class="progress-list">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Linear Grade Distribution (replaces the circular menu) – white card, progress bars -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="tile p-3">
                <h4 class="tile-title"><i class="bi bi-bar-chart-steps me-2"></i> Grade Distribution – <?php echo htmlspecialchars($current_term_name); ?></h4>
                <div id="gradeDistributionLinear" class="progress-list mt-3">
                    <?php if (!empty($grade_labels)): ?>
                        <?php 
                        $gradeColors = [
                            'A' => '#2ecc71',
                            'B' => '#3498db',
                            'C' => '#f39c12',
                            'D' => '#e67e22',
                            'F' => '#e74c3c',
                        ];
                        ?>
                        <?php for ($i = 0; $i < count($grade_labels); $i++): 
                            $label = $grade_labels[$i];
                            $count = $grade_counts[$i];
                            $percent = $grade_percentages[$i];
                            $color = $gradeColors[$label] ?? '#7f8c8d';
                        ?>
                            <div class="grade-progress-item mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold" style="color: <?php echo $color; ?>;">Grade <?php echo $label; ?></div>
                                    <div><span class="fw-bold"><?php echo $count; ?></span> students (<span><?php echo $percent; ?>%</span>)</div>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%; background-color: <?php echo $color; ?>;"></div>
                                    <span class="progress-percent"><?php echo $percent; ?>%</span>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <p class="text-muted">No grade data available for the current term.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Results & Upcoming Events (unchanged) -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="tile p-3">
                <h4 class="tile-title"><i class="bi bi-clock-history me-2"></i> Recent Results Entered</h4>
                <div class="activity-feed" style="max-height: 280px; overflow-y: auto;">
                    <?php if (empty($recent_results)): ?>
                        <p class="text-muted">No recent results.</p>
                    <?php else: ?>
                        <?php foreach ($recent_results as $r): ?>
                            <div class="activity-item">
                                <i class="bi bi-pencil-square me-2 text-success"></i>
                                <strong><?php echo htmlspecialchars($r['fname'] . ' ' . $r['lname']); ?></strong>
                                – <?php echo htmlspecialchars($r['score']); ?>% 
                                <span class="text-muted">(<?php echo htmlspecialchars($r['subject_name']); ?>, <?php echo htmlspecialchars($r['term_name']); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="tile p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="tile-title mb-0"><i class="bi bi-calendar-event me-2"></i> Upcoming Events</h4>
                    <a href="teacher/events.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar2-week"></i> View All</a>
                </div>
                <?php if (empty($upcoming_events)): ?>
                    <p class="text-muted">No upcoming events.</p>
                <?php else: ?>
                    <?php foreach ($upcoming_events as $ev): 
                        $event_date = date('d/m/Y', strtotime($ev['event_date']));
                        $event_time = $ev['start_time'] ? date('H:i', strtotime($ev['start_time'])) : '';
                    ?>
                        <div class="event-item mb-2">
                            <i class="bi bi-star-fill text-warning me-2"></i>
                            <strong><?php echo htmlspecialchars($ev['title']); ?></strong>
                            <div class="small text-muted"><?php echo $event_date; ?><?php if ($event_time): ?> at <?php echo $event_time; endif; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // Data for term rotation (for the three progress panels)
    var termIds = <?php echo json_encode($all_term_ids); ?>;
    var termNames = <?php echo json_encode($term_names); ?>;
    var subjectAveragesByTerm = <?php echo json_encode($term_subject_averages); ?>;
    var studentRankingsByTerm = <?php echo json_encode($term_student_rankings); ?>;

    var currentTermIndex = 0;
    var rotationInterval;

    // Helper: generate progress bar HTML (same as used in panels)
    function progressBarHTML(percentage, label, valueText = null) {
        var percent = Math.min(100, Math.max(0, percentage));
        var barColor = percent >= 75 ? '#2ecc71' : (percent >= 50 ? '#f39c12' : '#e74c3c');
        var displayValue = valueText !== null ? valueText : percent + '%';
        return `
            <div class="progress-item">
                <div class="progress-label">${escapeHtml(label)}</div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: ${percent}%; background-color: ${barColor};"></div>
                    <span class="progress-percent">${displayValue}</span>
                </div>
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Update all three panels for a given term ID
    function updatePanelsForTerm(termId, termName) {
        // 1. Top 5 students
        var students = studentRankingsByTerm[termId] || [];
        var top5 = students.slice(0, 5);
        var topHtml = '';
        if (top5.length === 0) {
            topHtml = '<p class="text-muted">No data available.</p>';
        } else {
            top5.forEach(function(s, idx) {
                topHtml += progressBarHTML(s.avg, `${idx+1}. ${s.name}`, `${s.avg}%`);
            });
        }
        document.getElementById('topStudentsProgress').innerHTML = topHtml;
        document.getElementById('topPanelTitle').innerHTML = '<i class="bi bi-trophy-fill me-2 text-warning"></i> Top 5 Students - ' + termName;

        // 2. Bottom 5 students (needs improvement)
        var bottom5 = students.slice(-5).reverse();
        var bottomHtml = '';
        if (bottom5.length === 0) {
            bottomHtml = '<p class="text-muted">No data available.</p>';
        } else {
            bottom5.forEach(function(s, idx) {
                bottomHtml += progressBarHTML(s.avg, `${idx+1}. ${s.name}`, `${s.avg}%`);
            });
        }
        document.getElementById('bottomStudentsProgress').innerHTML = bottomHtml;
        document.getElementById('bottomPanelTitle').innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Needs Improvement - ' + termName;

        // 3. Subject averages
        var subjects = subjectAveragesByTerm[termId] || {};
        var subjectEntries = Object.entries(subjects);
        var subjectHtml = '';
        if (subjectEntries.length === 0) {
            subjectHtml = '<p class="text-muted">No data available.</p>';
        } else {
            subjectEntries.forEach(function([subjName, avg]) {
                subjectHtml += progressBarHTML(avg, subjName, `${avg}%`);
            });
        }
        document.getElementById('subjectAvgProgress').innerHTML = subjectHtml;
        document.getElementById('subjectAvgPanelTitle').innerHTML = '<i class="bi bi-bar-chart me-2"></i> Subject Averages - ' + termName;
    }

    function rotateToNextTerm() {
        if (termIds.length === 0) return;
        currentTermIndex = (currentTermIndex + 1) % termIds.length;
        var termId = termIds[currentTermIndex];
        var termName = termNames[currentTermIndex];
        updatePanelsForTerm(termId, termName);
    }

    // Start rotation for the three panels (8 seconds interval)
    if (termIds.length > 0) {
        updatePanelsForTerm(termIds[0], termNames[0]);
        rotationInterval = setInterval(rotateToNextTerm, 8000);
    } else {
        document.getElementById('topStudentsProgress').innerHTML = '<p class="text-muted">No term data available.</p>';
        document.getElementById('bottomStudentsProgress').innerHTML = '<p class="text-muted">No term data available.</p>';
        document.getElementById('subjectAvgProgress').innerHTML = '<p class="text-muted">No term data available.</p>';
    }
</script>

<style>
/* Dashboard styles – white cards, glass effect, black text */
.app-content { background: transparent !important; }
.stat-card, .tile, .alert-box, .subject-card, .event-item {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card { padding: 1.2rem; }
.stat-card:hover, .subject-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
.stat-icon { font-size: 2.5rem; color: #3b7cff; }
.stat-value { font-size: 2rem; font-weight: 600; line-height: 1.2; color: #1e293b; }
.stat-label { color: #6b7280; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.tile { padding: 1.5rem; margin-bottom: 1rem; }
.subject-card { padding: 1rem; margin-bottom: 0.5rem; height: 100%; }
.subject-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.subject-stats { font-size: 0.9rem; }
.activity-item { padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; color: #1e293b; }
.alert-box { background: #fff3cd !important; border-color: #ffe69c; color: #856404; }
.quick-action .btn { margin-right: 0.5rem; margin-bottom: 0.5rem; border-radius: 40px; }
.event-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px dashed #dee2e6; }
.event-item:last-child { border-bottom: none; }
.breadcrumb { background: rgba(255,255,255,0.7); backdrop-filter: blur(4px); border-radius: 40px; padding: 0.5rem 1rem; }
.breadcrumb-item a, .breadcrumb-item.active { color: #1e293b !important; }
.app-title h1 { font-weight: 800; background: linear-gradient(135deg, #1e293b, #2d3a5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
.text-muted { color: #6c757d !important; }
.progress { background-color: #e9ecef; border-radius: 10px; overflow: hidden; }

/* Progress list styles (used in Top 5, Needs Improvement, Subject Averages, and now Grade Distribution) */
.progress-panel {
    background: rgba(255,255,255,0.95);
}
.progress-list {
    margin-top: 0.5rem;
}
.progress-item, .grade-progress-item {
    margin-bottom: 1rem;
}
.progress-label {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 4px;
    color: #1e293b;
}
.progress-bar-container {
    position: relative;
    background: #e9ecef;
    border-radius: 20px;
    height: 28px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 20px;
    transition: width 0.3s ease;
}
.progress-percent {
    position: absolute;
    top: 0;
    right: 10px;
    line-height: 28px;
    font-size: 0.8rem;
    font-weight: bold;
    color: #1e293b;
    text-shadow: 0 0 2px white;
}
/* For the grade distribution progress bars, we keep the same style */
.grade-progress-item .progress-bar-container {
    background: #e9ecef;
}
</style>
</body>
</html>