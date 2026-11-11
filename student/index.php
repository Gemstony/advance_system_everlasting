<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');
require_once('student/inc/finance_functions.php'); // for fee functions

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

// ===== Result visibility check (same as results page) =====
$resultsDisabled = (WBResAvi != "1");

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET SESSION sql_mode = ''");

    $stmt = $pdo->prepare("SELECT s.*, CAST(s.class AS UNSIGNED) AS class_int, c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) die("Student not found.");

    $class_id = (int) $student['class_int'];

    $grading = $pdo->query("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();
    $GLOBALS['gradeSystem'] = $grading;
    $divisionSystem = $pdo->query("SELECT * FROM tbl_division_system")->fetchAll();
    $GLOBALS['divisionSystem'] = $divisionSystem;

    // 1. Fetch all active subject combinations
    $stmt = $pdo->query("SELECT id, class, subject FROM tbl_subject_combinations WHERE status = 1");
    $all_combos = $stmt->fetchAll();

    // 2. Filter those that include this student's class
    $assigned_subjects = [];
    foreach ($all_combos as $combo) {
        $class_array = unserialize($combo['class']);
        if (is_array($class_array) && in_array($class_id, $class_array)) {
            $stmt = $pdo->prepare("SELECT name FROM tbl_subjects WHERE id = ?");
            $stmt->execute([$combo['subject']]);
            $subj = $stmt->fetch();
            if ($subj) {
                $assigned_subjects[] = [
                    'subject_id' => $combo['subject'],
                    'subject_name' => $subj['name']
                ];
            }
        }
    }

    // 3. Get terms with results for this student
    $stmt = $pdo->prepare("SELECT DISTINCT CAST(t.id AS UNSIGNED) AS term_id, t.name, t.status FROM tbl_exam_results r LEFT JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY t.id DESC");
    $stmt->execute([$student_id]);
    $terms_with_results = $stmt->fetchAll();

    // 4. Latest term (if any)
    $latest_term = null;
    $term_id = null;
    if (!empty($terms_with_results)) {
        $latest_term = $terms_with_results[0];
        $term_id = (int) $latest_term['term_id'];
    }

    // 5. Fetch results for the latest term
    $results_by_subject = [];
    $class_avg_by_subject = [];
    if ($term_id) {
        $stmt = $pdo->prepare("SELECT r.score, sc.subject AS subject_id FROM tbl_exam_results r LEFT JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id WHERE r.student = ? AND r.term = ?");
        $stmt->execute([$student_id, $term_id]);
        while ($row = $stmt->fetch()) {
            $results_by_subject[$row['subject_id']] = $row['score'];
        }

        $subject_ids_with_results = array_keys($results_by_subject);
        if (!empty($subject_ids_with_results)) {
            $placeholders = implode(',', array_fill(0, count($subject_ids_with_results), '?'));
            $stmt = $pdo->prepare("SELECT sc.subject, AVG(r.score) AS avg_score FROM tbl_exam_results r LEFT JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id WHERE r.term = ? AND sc.subject IN ($placeholders) GROUP BY sc.subject");
            $params = array_merge([$term_id], $subject_ids_with_results);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $class_avg_by_subject[$row['subject']] = round($row['avg_score']);
            }
        }
    }

    // 6. Build subject performance list
    $subject_performance = [];
    $total_score = 0;
    $attended_count = 0;
    $pass_count = 0;
    $fail_count = 0;
    $failing_subjects = [];
    $grade_counts = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0,'S'=>0,'F'=>0];
    $lowest_score = 100;
    $lowest_subject = '';

    foreach ($assigned_subjects as $subj) {
        $sid = $subj['subject_id'];
        $score = isset($results_by_subject[$sid]) ? $results_by_subject[$sid] : null;
        $attended = ($score !== null && $score !== '');

        $subject_performance[] = [
            'subject_id' => $sid,
            'subject_name' => $subj['subject_name'],
            'score' => $attended ? $score : null,
            'attended' => $attended,
            'class_avg' => isset($class_avg_by_subject[$sid]) ? $class_avg_by_subject[$sid] : null
        ];

        if ($attended) {
            $total_score += $score;
            $attended_count++;
            if ($score >= 50) $pass_count++;
            else {
                $fail_count++;
                $failing_subjects[] = $subj['subject_name'];
            }
            if ($score < $lowest_score) {
                $lowest_score = $score;
                $lowest_subject = $subj['subject_name'];
            }
            $grade_counts[get_grade($score, $grading)]++;
        }
    }

    $avg_score = ($attended_count > 0) ? round($total_score / $attended_count, 1) : 0;
    $latest_term_grade = $attended_count > 0 ? get_grade($avg_score, $grading) : 'N/A';
    $latest_term_remark = $attended_count > 0 ? get_grade_remark($avg_score, $grading) : 'N/A';

    $resultSubjects = array_map(function ($subject) {
        return [
            'name' => $subject['subject_name'],
            'score' => $subject['score']
        ];
    }, array_filter($subject_performance, function ($subject) {
        return $subject['attended'];
    }));
    $combination = detect_combination_from_class_name($student['class_name'] ?? '');
    $latestAcsee = $combination
        ? calculate_acsee_result($resultSubjects, $combination, $grading, $divisionSystem)
        : null;
    $latest_term_division = $latestAcsee['division'] ?? null;
    $latest_term_points = $latestAcsee['points'] ?? null;

    $pass_percent = ($attended_count > 0) ? round(($pass_count / $attended_count) * 100) : 0;
    $fail_percent = ($attended_count > 0) ? round(($fail_count / $attended_count) * 100) : 0;

    $attended_subjects = array_filter($subject_performance, function($s) { return $s['attended']; });
    usort($attended_subjects, function($a, $b) { return $b['score'] <=> $a['score']; });
    $top_subjects = array_slice($attended_subjects, 0, 3);
    $bottom_subjects = array_slice($attended_subjects, -3, 3);

    // 7. Term averages for trend chart
    $chart_labels = []; $chart_data = [];
    foreach ($terms_with_results as $t) {
        $tid = (int) $t['term_id'];
        $stmt = $pdo->prepare("SELECT AVG(score) as avg_score FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt->execute([$student_id, $tid]);
        $avg = round($stmt->fetchColumn());
        $chart_labels[] = $t['name']; $chart_data[] = $avg;
    }

    $previous_term_avg = null;
    if (count($terms_with_results) > 1) {
        $prev_term = $terms_with_results[1];
        $stmt = $pdo->prepare("SELECT AVG(score) as avg_score FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt->execute([$student_id, (int) $prev_term['term_id']]);
        $previous_term_avg = round($stmt->fetchColumn());
    }

    // Fee module data
    $fee_module_enabled = isFeeModuleEnabled($pdo);
    $fee_account = null;
    if ($fee_module_enabled) {
        $academic_year = '2026';
        $fee_account = getStudentFeeAccount($pdo, $student_id, $academic_year);
    }

} catch (PDOException $e) { die("Database error: " . htmlspecialchars($e->getMessage())); }

// Advice and styling
$advice = ''; $advice_icon = 'bi-info-circle'; $advice_color = '#3b7cff';
if ($attended_count > 0) {
    if ($avg_score >= 75) { $advice = "Excellent performance! Your average is outstanding. Keep up the great work!"; $advice_icon = 'bi-emoji-smile'; $advice_color = '#16a34a'; }
    elseif ($avg_score >= 50) {
        if ($fail_count > 0) { $advice = "Good effort, but you need to improve in " . implode(', ', array_slice($failing_subjects, 0, 2)) . (count($failing_subjects) > 2 ? ' and others' : '') . ". Focus on these subjects."; $advice_icon = 'bi-exclamation-triangle'; $advice_color = '#f59e0b'; }
        else { $advice = "Well done! You passed all subjects. Aim higher to reach excellence."; $advice_icon = 'bi-emoji-neutral'; $advice_color = '#0ea5e9'; }
    } else { $advice = "You need significant improvement. Focus on " . implode(', ', array_slice($failing_subjects, 0, 3)) . ". Consider extra tuition."; $advice_icon = 'bi-emoji-frown'; $advice_color = '#dc2626'; }
    if ($previous_term_avg !== null) { $diff = $avg_score - $previous_term_avg; if ($diff > 5) $advice .= " Your average has improved by {$diff} points. Great progress!"; elseif ($diff < -5) $advice .= " Your average has dropped by " . abs($diff) . " points. Let's work harder."; else $advice .= " Your performance is stable compared to previous term."; }
} else { $advice = "No results available for any term. Check back later."; $advice_icon = 'bi-clock-history'; $advice_color = '#64748b'; }

// Profile image
$profile_image = null; $use_icon = false;
if (!empty($student['display_image']) && $student['display_image'] !== 'DEFAULT') {
    $custom = 'images/students/' . $student['display_image'];
    if (file_exists(__DIR__ . '/../' . $custom)) $profile_image = $custom;
}
if (!$profile_image) {
    $gender_img = 'images/students/' . $student['gender'] . '.png';
    if (file_exists(__DIR__ . '/../' . $gender_img)) $profile_image = $gender_img;
}
if (!$profile_image) $use_icon = true;

$grade_labels = array_keys($grade_counts);
$grade_values = array_values($grade_counts);

$attended_subjects_for_chart = array_filter($subject_performance, function($s) { return $s['attended']; });
$bar_labels = array_column($attended_subjects_for_chart, 'subject_name');
$bar_scores = array_column($attended_subjects_for_chart, 'score');

// Status derived for professional accent
$overallStatus = 'neutral';
$overallStatusLabel = 'No data';
if ($attended_count > 0) {
    if ($avg_score >= 75)      { $overallStatus = 'excellent'; $overallStatusLabel = 'Excellent'; }
    elseif ($avg_score >= 60)  { $overallStatus = 'good';      $overallStatusLabel = 'Good'; }
    elseif ($avg_score >= 50)  { $overallStatus = 'fair';      $overallStatusLabel = 'Fair'; }
    else                       { $overallStatus = 'poor';      $overallStatusLabel = 'Needs Work'; }
}
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="dash-shell">

        <!-- ============================================================
             PAGE HEADING
             ============================================================ -->
        <div class="page-heading reveal" data-delay="50">
            <div class="page-heading-text">
                <span class="eyebrow">Student Dashboard</span>
                <h1>Welcome back, <?php echo htmlspecialchars($student['fname']); ?></h1>
                <p>Here is a snapshot of your academic progress and fee status.</p>
            </div>
            <div class="page-heading-badge status-<?php echo $overallStatus; ?>">
                <span class="status-dot"></span>
                <div>
                    <small>Overall Status</small>
                    <strong><?php echo $overallStatusLabel; ?></strong>
                </div>
            </div>
        </div>

        <!-- ============================================================
             QUICK ACTIONS – 4 equal, professional buttons
             ============================================================ -->
        <nav class="quick-actions reveal" data-delay="100" aria-label="Quick actions">
            <a href="student/view" class="qa-btn qa-profile">
                <span class="qa-icon"><i class="bi bi-person-badge-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">My Profile</span>
                    <span class="qa-sub">Personal details</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
            <a href="student/subjects" class="qa-btn qa-subjects">
                <span class="qa-icon"><i class="bi bi-journal-bookmark-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">My Subjects</span>
                    <span class="qa-sub">Enrolled courses</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
            <a href="student/results" class="qa-btn qa-results">
                <span class="qa-icon"><i class="bi bi-clipboard-data-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">Full Results</span>
                    <span class="qa-sub">Term by term</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
            <a href="student/save_dashboard_pdf" target="_blank" class="qa-btn qa-report">
                <span class="qa-icon"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">General Report</span>
                    <span class="qa-sub">Download PDF</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
        </nav>

        <!-- ============================================================
             STUDENT INFO CARD
             ============================================================ -->
        <section class="info-card reveal" data-delay="150" data-accent="blue">
            <div class="info-card-header">
                <span class="info-tag"><i class="bi bi-person-vcard"></i> Student Information</span>
            </div>
            <div class="info-card-body">
                <div class="info-avatar-col">
                    <?php if ($use_icon): ?>
                        <div class="info-avatar-placeholder"><i class="bi bi-person-circle"></i></div>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="info-avatar-img">
                    <?php endif; ?>
                    <div class="info-name">
                        <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                        <small><?php echo htmlspecialchars($student['class_name']); ?></small>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-hash"></i></span>
                        <div>
                            <small>Reg No</small>
                            <strong><?php echo htmlspecialchars($student['id']); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-building"></i></span>
                        <div>
                            <small>Class</small>
                            <strong><?php echo htmlspecialchars($student['class_name']); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-gender-<?php echo strtolower($student['gender'] ?: 'ambiguous'); ?>"></i></span>
                        <div>
                            <small>Gender</small>
                            <strong><?php echo htmlspecialchars($student['gender'] ?: '—'); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-envelope"></i></span>
                        <div>
                            <small>Email</small>
                            <strong class="wrap"><?php echo htmlspecialchars($student['email'] ?: '—'); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-bookmarks"></i></span>
                        <div>
                            <small>Subjects</small>
                            <strong><?php echo count($assigned_subjects); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-calendar-check"></i></span>
                        <div>
                            <small>Current Term</small>
                            <strong><?php echo $resultsDisabled ? '—' : htmlspecialchars($latest_term['name'] ?? 'N/A'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             FEE SECTION
             ============================================================ -->
        <?php if ($fee_module_enabled): ?>
            <?php if ($fee_account): ?>
                <section class="section-heading-card reveal" data-delay="200" data-accent="violet">
                    <div class="shc-icon"><i class="bi bi-wallet2"></i></div>
                    <div class="shc-text">
                        <h2>Fee Summary</h2>
                        <p>Your current fee standing for the academic year.</p>
                    </div>
                    <div class="shc-tag">Finance</div>
                </section>
                <div class="row g-3 mb-2">
                    <div class="col-md-4 reveal" data-delay="250">
                        <a href="student/fee-structure" class="fee-tile accent-blue">
                            <div class="fee-tile-top">
                                <span class="fee-tile-icon"><i class="bi bi-calculator-fill"></i></span>
                                <span class="fee-tile-tag">View structure</span>
                            </div>
                            <div class="fee-tile-body">
                                <small>Total Expected</small>
                                <div class="fee-tile-value"><?php echo formatCurrency($fee_account['total_expected'] ?? 0); ?></div>
                            </div>
                            <i class="bi bi-arrow-right fee-tile-arrow"></i>
                        </a>
                    </div>
                    <div class="col-md-4 reveal" data-delay="300">
                        <a href="student/payment-history" class="fee-tile accent-green">
                            <div class="fee-tile-top">
                                <span class="fee-tile-icon"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="fee-tile-tag">History</span>
                            </div>
                            <div class="fee-tile-body">
                                <small>Total Paid</small>
                                <div class="fee-tile-value"><?php echo formatCurrency($fee_account['total_paid'] ?? 0); ?></div>
                            </div>
                            <i class="bi bi-arrow-right fee-tile-arrow"></i>
                        </a>
                    </div>
                    <div class="col-md-4 reveal" data-delay="350">
                        <?php $hasBalance = ($fee_account['balance_due'] ?? 0) > 0; ?>
                        <a href="student/invoices" class="fee-tile <?php echo $hasBalance ? 'accent-red' : 'accent-slate'; ?>">
                            <div class="fee-tile-top">
                                <span class="fee-tile-icon"><i class="bi <?php echo $hasBalance ? 'bi-exclamation-triangle-fill' : 'bi-shield-check'; ?>"></i></span>
                                <span class="fee-tile-tag">Invoices</span>
                            </div>
                            <div class="fee-tile-body">
                                <small>Balance Due</small>
                                <div class="fee-tile-value"><?php echo formatCurrency($fee_account['balance_due'] ?? 0); ?></div>
                            </div>
                            <i class="bi bi-arrow-right fee-tile-arrow"></i>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice-card accent-slate reveal" data-delay="250">
                    <div class="notice-icon"><i class="bi bi-info-circle"></i></div>
                    <div>
                        <h5>Fee Information Not Available</h5>
                        <p>Your fee account has not been set up yet. Please contact the school accountant for assistance.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="notice-card accent-slate reveal" data-delay="250">
                <div class="notice-icon"><i class="bi bi-shield-slash"></i></div>
                <div>
                    <h5>Fee Information Unavailable</h5>
                    <p>The fee module is currently disabled. Please contact the school accountant for any fee-related inquiries.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             RESULTS
             ============================================================ -->
        <?php if ($resultsDisabled): ?>
            <div class="notice-card accent-amber reveal" data-delay="300">
                <div class="notice-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="w-100">
                    <h5>Stay Tuned!</h5>
                    <p>The current results are being uploaded now. They will appear here automatically when released.</p>
                    <div class="progress mx-auto mt-3" style="height: 14px; max-width: 380px; border-radius: 30px; background: #e2e8f0; overflow: hidden;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar"
                             style="width: 75%; height: 14px; background: linear-gradient(90deg, #fbbf24, #f59e0b, #f97316, #ef4444, #ec4899, #8b5cf6, #3b82f6, #22d3ee, #34d399, #fbbf24); background-size: 300% 100%;"
                             aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm mt-3" onclick="location.reload();">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Now
                    </button>
                </div>
            </div>
        <?php else: ?>

            <!-- ========================================================
                 ACADEMIC INSIGHTS (classic card heading)
                 ======================================================== -->
            <section class="section-heading-card reveal" data-delay="300" data-accent="blue">
                <div class="shc-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="shc-text">
                    <h2>Academic Insights</h2>
                    <p>Your performance summary and key statistics at a glance.</p>
                </div>
                <div class="shc-tag">Results</div>
            </section>

            <div class="row g-3 mb-2">
                <div class="col-6 col-lg-3 reveal" data-delay="320">
                    <div class="stat-tile accent-blue">
                        <span class="stat-tile-icon"><i class="bi bi-journal-text"></i></span>
                        <div class="stat-tile-value"><?php echo count($assigned_subjects); ?></div>
                        <div class="stat-tile-label">Assigned Subjects</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="360">
                    <div class="stat-tile accent-green">
                        <span class="stat-tile-icon"><i class="bi bi-check-circle-fill"></i></span>
                        <div class="stat-tile-value"><?php echo $pass_count; ?></div>
                        <div class="stat-tile-label">Passed</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="400">
                    <div class="stat-tile accent-red">
                        <span class="stat-tile-icon"><i class="bi bi-x-circle-fill"></i></span>
                        <div class="stat-tile-value"><?php echo $fail_count; ?></div>
                        <div class="stat-tile-label">Failed</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="440">
                    <div class="stat-tile accent-amber">
                        <span class="stat-tile-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="stat-tile-value"><?php echo $avg_score; ?>%</div>
                        <div class="stat-tile-label">Overall Average</div>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 ACADEMIC INSIGHT (advice card)
                 ======================================================== -->
            <?php if ($attended_count > 0): ?>
                <div class="advice-card reveal" data-delay="460" style="--accent: <?php echo $advice_color; ?>;">
                    <div class="advice-icon"><i class="bi <?php echo $advice_icon; ?>"></i></div>
                    <div class="advice-body">
                        <h5>Academic Insight</h5>
                        <p><?php echo $advice; ?></p>
                        <div class="advice-chips">
                            <span class="chip"><i class="bi bi-bar-chart"></i> Avg <?php echo $avg_score; ?>%</span>
                            <span class="chip chip-pass"><i class="bi bi-check-circle"></i> Pass <?php echo $pass_percent; ?>%</span>
                            <span class="chip chip-fail"><i class="bi bi-x-circle"></i> Fail <?php echo $fail_percent; ?>%</span>
                            <span class="chip"><i class="bi bi-trophy"></i> Grade <?php echo $latest_term_grade ?: 'N/A'; ?></span>
                            <span class="chip"><i class="bi bi-award"></i> Division <?php echo $latest_term_division ?: 'N/A'; ?></span>
                            <span class="chip"><i class="bi bi-star"></i> Points <?php echo $latest_term_points ?: '0'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- ====================================================
                     PERFORMANCE GRID
                     ==================================================== -->
                <div class="row g-3 mb-2">
                    <div class="col-lg-7 reveal" data-delay="500">
                        <div class="panel-card accent-blue">
                            <div class="panel-head">
                                <h4>Subject Performance</h4>
                                <span class="panel-tag"><?php echo htmlspecialchars($latest_term['name'] ?? ''); ?></span>
                            </div>
                            <div class="panel-body">
                                <?php foreach ($subject_performance as $subj):
                                    $score = $subj['score'];
                                    $attended = $subj['attended'];
                                    $bar_color = $attended ? ($score >= 50 ? '#16a34a' : '#dc2626') : '#94a3b8';
                                    $display_score = $attended ? $score.'%' : 'No result';
                                    $class_avg_score = $subj['class_avg'];
                                    $badge = $attended ? ($score >= 50 ? '<span class="pill pill-pass">Pass</span>' : '<span class="pill pill-fail">Fail</span>') : '<span class="pill pill-muted">N/A</span>';
                                ?>
                                <div class="subject-row">
                                    <div class="subject-row-head">
                                        <span class="subject-name"><?php echo htmlspecialchars($subj['subject_name']); ?></span>
                                        <span class="subject-score">
                                            <?php echo $display_score; ?>
                                            <?php if ($class_avg_score && $attended): ?>
                                                <em>(avg <?php echo $class_avg_score; ?>%)</em>
                                            <?php endif; ?>
                                            <?php echo $badge; ?>
                                        </span>
                                    </div>
                                    <div class="subject-bar">
                                        <div class="subject-bar-fill" style="width: <?php echo $attended ? $score : 0; ?>%; background-color: <?php echo $bar_color; ?>;"></div>
                                    </div>
                                    <?php if ($class_avg_score && $attended): $diff = $score - $class_avg_score; ?>
                                        <div class="subject-note">
                                            <?php if ($diff > 0): ?>
                                                <span class="note-good"><i class="bi bi-arrow-up-short"></i> <?php echo $diff; ?>% above class average</span>
                                            <?php elseif ($diff < 0): ?>
                                                <span class="note-bad"><i class="bi bi-arrow-down-short"></i> <?php echo abs($diff); ?>% below class average</span>
                                            <?php else: ?>
                                                <span>Equal to class average</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!$attended): ?>
                                        <div class="subject-note"><i class="bi bi-exclamation-circle"></i> No result available for this subject.</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="reveal" data-delay="540">
                            <div class="panel-card accent-green mb-3">
                                <div class="panel-head">
                                    <h4>Overall Average</h4>
                                    <span class="panel-tag">Latest Term</span>
                                </div>
                                <div class="panel-body text-center">
                                    <div class="big-score"><?php echo $avg_score; ?>%</div>
                                    <div class="subject-bar mt-2" style="height:12px;">
                                        <div class="subject-bar-fill" style="width: <?php echo $avg_score; ?>%; background-color:#3b7cff; height:12px;"></div>
                                    </div>
                                    <div class="chips-row mt-3 justify-content-center">
                                        <span class="chip"><i class="bi bi-trophy"></i> Division <?php echo $latest_term_division ?: 'N/A'; ?></span>
                                        <span class="chip"><i class="bi bi-star"></i> Points <?php echo $latest_term_points ?: '0'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="reveal" data-delay="580">
                            <div class="panel-card accent-violet">
                                <div class="panel-head">
                                    <h4>Subject Highlights</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="mini-box">
                                                <div class="mini-box-title"><i class="bi bi-star-fill"></i> Top 3</div>
                                                <ul>
                                                    <?php foreach ($top_subjects as $sub): ?>
                                                        <li><?php echo htmlspecialchars($sub['subject_name']); ?> <strong><?php echo $sub['score']; ?>%</strong></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mini-box mini-box-warn">
                                                <div class="mini-box-title"><i class="bi bi-emoji-frown"></i> Needs Work</div>
                                                <ul>
                                                    <?php foreach ($bottom_subjects as $sub): ?>
                                                        <li><?php echo htmlspecialchars($sub['subject_name']); ?> <strong><?php echo $sub['score']; ?>%</strong></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====================================================
                     GRADE DISTRIBUTION
                     ==================================================== -->
                <div class="reveal" data-delay="620">
                    <div class="panel-card accent-amber">
                        <div class="panel-head">
                            <h4>Grade Distribution</h4>
                            <span class="panel-tag">All attended</span>
                        </div>
                        <div class="panel-body">
                            <div class="row align-items-center g-3">
                                <div class="col-md-5">
                                    <div class="grade-chart"><canvas id="gradeChart"></canvas></div>
                                </div>
                                <div class="col-md-7">
                                    <div class="grade-list">
                                        <div class="grade-row"><span class="grade-dot" style="background:#16a34a;"></span><span class="grade-name">A</span><strong><?php echo $grade_counts['A']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#0ea5e9;"></span><span class="grade-name">B</span><strong><?php echo $grade_counts['B']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#f59e0b;"></span><span class="grade-name">C</span><strong><?php echo $grade_counts['C']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#f97316;"></span><span class="grade-name">D</span><strong><?php echo $grade_counts['D']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#64748b;"></span><span class="grade-name">E</span><strong><?php echo $grade_counts['E']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#2563eb;"></span><span class="grade-name">S</span><strong><?php echo $grade_counts['S']; ?></strong></div>
                                        <div class="grade-row"><span class="grade-dot" style="background:#dc2626;"></span><span class="grade-name">F</span><strong><?php echo $grade_counts['F']; ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice-card accent-slate reveal" data-delay="460">
                    <div class="notice-icon"><i class="bi bi-info-circle"></i></div>
                    <div>
                        <h5>No Results Yet</h5>
                        <p>You have no examination results available. Check back after your exams are published.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ========================================================
                 DETAILED RESULTS TABLE
                 ======================================================== -->
            <div class="reveal" data-delay="680">
                <div class="panel-card accent-blue">
                    <div class="panel-head">
                        <h4>Detailed Results</h4>
                        <span class="panel-tag"><?php echo htmlspecialchars($latest_term['name'] ?? 'Latest'); ?></span>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table dash-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Grade</th>
                                        <th>Remark</th>
                                        <?php if (!empty($class_avg_by_subject)) echo '<th class="text-center">Class Avg</th>'; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject_performance as $subj):
                                        $score = $subj['score'];
                                        $attended = $subj['attended'];
                                        if ($attended) {
                                            $subj_grade = get_grade($score, $grading);
                                            $subj_remark = get_grade_remark($score, $grading);
                                            $row_class = ($score < 50) ? 'row-fail' : '';
                                        } else {
                                            $subj_grade = 'N/A';
                                            $subj_remark = 'Not uploaded for this term.';
                                            $row_class = 'row-muted';
                                        }
                                        $class_avg_score = $subj['class_avg'];
                                    ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><span class="td-subject"><?php echo htmlspecialchars($subj['subject_name']); ?></span></td>
                                            <td class="text-center"><strong><?php echo $attended ? $score.'%' : '—'; ?></strong></td>
                                            <td class="text-center"><?php echo $subj_grade; ?></td>
                                            <td class="td-remark"><?php echo $subj_remark; ?></td>
                                            <?php if (!empty($class_avg_by_subject)): ?>
                                                <td class="text-center">
                                                    <?php if ($class_avg_score): ?>
                                                        <?php echo $class_avg_score; ?>%
                                                        <?php if ($attended): ?>
                                                            <?php if ($score > $class_avg_score): ?><i class="bi bi-arrow-up text-success"></i>
                                                            <?php elseif ($score < $class_avg_score): ?><i class="bi bi-arrow-down text-danger"></i><?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>—<?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="chips-row mt-3">
                            <span class="chip"><i class="bi bi-calculator"></i> Total <strong><?php echo $total_score; ?></strong></span>
                            <span class="chip"><i class="bi bi-bar-chart"></i> Average <strong><?php echo $avg_score; ?>%</strong></span>
                            <span class="chip"><i class="bi bi-trophy"></i> Grade <strong><?php echo $latest_term_grade ?: 'N/A'; ?></strong></span>
                            <span class="chip"><i class="bi bi-info-circle"></i> <?php echo $latest_term_remark ?: '—'; ?></span>
                            <span class="chip"><i class="bi bi-award"></i> Division <strong><?php echo $latest_term_division ?: 'N/A'; ?></strong></span>
                            <span class="chip"><i class="bi bi-star"></i> Points <strong><?php echo $latest_term_points ?: '0'; ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 PERFORMANCE TREND
                 ======================================================== -->
            <?php if (count($chart_labels) > 1): ?>
                <div class="reveal" data-delay="740">
                    <div class="panel-card accent-violet">
                        <div class="panel-head">
                            <h4>Performance Trend</h4>
                            <span class="panel-tag">All terms</span>
                        </div>
                        <div class="panel-body">
                            <div class="trend-chart"><canvas id="trendChart"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; // end results disabled check ?>

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
$(document).ready(function(){
    <?php if (!$resultsDisabled && count($chart_labels) > 1): ?>
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Average Score (%)',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#3b7cff',
                backgroundColor: 'rgba(59,124,255,0.10)',
                tension: 0.25,
                fill: true,
                pointBackgroundColor: '#3b7cff',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
    <?php endif; ?>

    <?php if (!$resultsDisabled && $attended_count > 0): ?>
    new Chart(document.getElementById('gradeChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($grade_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($grade_values); ?>,
                backgroundColor: ['#16a34a', '#0ea5e9', '#f59e0b', '#f97316', '#dc2626'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { display: false } }
        }
    });
    <?php endif; ?>

    // ---- CINEMATIC SCROLL REVEAL ----
    const revealElements = document.querySelectorAll('.reveal');
    const observerOptions = { root: null, rootMargin: '0px 0px -40px 0px', threshold: 0.08 };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    revealElements.forEach(el => observer.observe(el));
});
</script>

<style>
/* =========================================================
   PROFESSIONAL DASHBOARD STYLES
   Strict, defensive width constraints — nothing crosses
   the right margin, everything always fits the viewport.
   ========================================================= */

html, body {
    overflow-x: hidden !important;
    max-width: 100% !important;
    width: 100% !important;
}

*, *::before, *::after { box-sizing: border-box; }
img, canvas, svg { max-width: 100%; height: auto; }

:root {
    --radius-lg: 20px;
    --radius-md: 14px;
    --radius-sm: 10px;
    --card-pad: clamp(0.9rem, 1.8vw, 1.4rem);
    --card-gap: clamp(0.8rem, 1.4vw, 1.1rem);
    --edge-safe: clamp(0.75rem, 1.8vw, 1.5rem);

    --accent-blue:   #2563eb;
    --accent-green:  #16a34a;
    --accent-red:    #dc2626;
    --accent-amber:  #f59e0b;
    --accent-violet: #7c3aed;
    --accent-slate:  #64748b;

    --ink-900: #0f172a;
    --ink-700: #334155;
    --ink-500: #64748b;
    --ink-300: #cbd5e1;
    --surface: #ffffff;
    --surface-2: #f8fafc;
    --line: #e2e8f0;
}

.app-content {
    position: relative;
    z-index: 1;
    background: transparent;
    overflow-x: hidden !important;
    max-width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.dash-shell {
    display: block !important;
    position: relative !important;
    width: 100% !important;
    max-width: 1320px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding: clamp(0.75rem, 1.5vw, 1.25rem) var(--edge-safe) 2.5rem !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
}

.dash-shell .row,
.dash-shell > .row {
    margin-left: 0 !important;
    margin-right: 0 !important;
    --bs-gutter-x: clamp(0.75rem, 1.4vw, 1.1rem);
    --bs-gutter-y: clamp(0.75rem, 1.4vw, 1.1rem);
}
.dash-shell .row > [class*="col-"] {
    padding-left: calc(var(--bs-gutter-x) * .5) !important;
    padding-right: calc(var(--bs-gutter-x) * .5) !important;
    min-width: 0 !important;
}
.dash-shell > * { max-width: 100%; box-sizing: border-box; }

/* ---------- Page heading ---------- */
.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: var(--card-gap);
    padding: clamp(1rem, 2vw, 1.5rem) clamp(1rem, 2vw, 1.6rem);
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, rgba(15,23,42,0.92), rgba(30,58,138,0.92));
    color: #fff;
    box-shadow: 0 18px 40px -18px rgba(15,23,42,.55);
    position: relative;
    overflow: hidden;
    width: 100%;
}
.page-heading::after {
    content: "";
    position: absolute;
    right: -60px; top: -60px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(96,165,250,.35), rgba(96,165,250,0) 70%);
    pointer-events: none;
}
.page-heading-text { min-width: 0; flex: 1 1 220px; }
.page-heading-text .eyebrow {
    display: inline-block;
    font-size: .72rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #93c5fd;
    font-weight: 700;
    margin-bottom: 6px;
}
.page-heading-text h1 {
    font-size: clamp(1.1rem, 2.4vw, 1.7rem);
    font-weight: 800;
    margin: 0 0 4px;
    font-family: 'Poppins', sans-serif;
    letter-spacing: -0.01em;
    color: #fff;
}
.page-heading-text p {
    margin: 0;
    font-size: clamp(.8rem, 1.6vw, .94rem);
    color: #cbd5e1;
}
.page-heading-badge {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .6rem 1rem;
    border-radius: var(--radius-md);
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    backdrop-filter: blur(6px);
    min-width: 130px;
    flex: 0 0 auto;
    max-width: 100%;
}
.page-heading-badge .status-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: #94a3b8;
    box-shadow: 0 0 0 4px rgba(148,163,184,.25);
    flex: 0 0 auto;
}
.page-heading-badge small { display: block; font-size: .68rem; color: #cbd5e1; letter-spacing: .08em; text-transform: uppercase; }
.page-heading-badge strong { display: block; font-size: .95rem; font-weight: 700; color: #fff; }
.page-heading-badge.status-excellent .status-dot { background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,.3); }
.page-heading-badge.status-good      .status-dot { background: #0ea5e9; box-shadow: 0 0 0 4px rgba(14,165,233,.3); }
.page-heading-badge.status-fair      .status-dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.3); }
.page-heading-badge.status-poor      .status-dot { background: #ef4444; box-shadow: 0 0 0 4px rgba(239,68,68,.3); }

/* ---------- Quick actions ---------- */
.quick-actions {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: clamp(.5rem, 1vw, .8rem);
    margin-bottom: var(--card-gap);
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
}
.qa-btn {
    display: flex;
    align-items: center;
    gap: .7rem;
    padding: clamp(.75rem, 1.4vw, 1rem) clamp(.85rem, 1.6vw, 1.1rem);
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--line);
    text-decoration: none;
    color: var(--ink-900);
    position: relative;
    overflow: hidden;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    box-shadow: 0 4px 14px -8px rgba(15,23,42,.12);
    min-height: 72px;
    min-width: 0 !important;
    width: 100%;
    box-sizing: border-box;
}
.qa-btn::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: var(--accent-blue);
    transition: width .25s ease;
}
.qa-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 28px -14px rgba(15,23,42,.28);
    border-color: transparent;
    color: var(--ink-900);
}
.qa-btn:hover::before { width: 6px; }
.qa-icon {
    flex: 0 0 auto;
    width: 40px; height: 40px;
    border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    color: #fff;
    background: var(--accent-blue);
}
.qa-body { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
.qa-label {
    font-weight: 700;
    font-size: clamp(.82rem, 1.4vw, .95rem);
    line-height: 1.15;
    color: var(--ink-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.qa-sub {
    font-size: clamp(.66rem, 1.2vw, .76rem);
    color: var(--ink-500);
    line-height: 1.25;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.qa-arrow { color: var(--ink-300); font-size: 1rem; transition: transform .25s ease, color .25s ease; flex-shrink: 0; }
.qa-btn:hover .qa-arrow { transform: translateX(3px); color: var(--accent-blue); }

.qa-profile::before  { background: var(--accent-blue); }
.qa-profile  .qa-icon{ background: linear-gradient(135deg, #3b82f6, #2563eb); }
.qa-profile:hover .qa-arrow { color: var(--accent-blue); }

.qa-subjects::before { background: var(--accent-violet); }
.qa-subjects .qa-icon{ background: linear-gradient(135deg, #a78bfa, #7c3aed); }
.qa-subjects:hover .qa-arrow { color: var(--accent-violet); }

.qa-results::before  { background: var(--accent-amber); }
.qa-results  .qa-icon{ background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.qa-results:hover .qa-arrow { color: var(--accent-amber); }

.qa-report::before   { background: var(--accent-green); }
.qa-report   .qa-icon{ background: linear-gradient(135deg, #34d399, #16a34a); }
.qa-report:hover .qa-arrow { color: var(--accent-green); }

/* =========================================================
   SECTION HEADING AS A CLASSIC CARD
   Replaces the old thin line with a full bordered card.
   ========================================================= */
.section-heading-card {
    --accent: var(--accent-blue);
    position: relative;
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: clamp(.85rem, 1.6vw, 1.1rem) clamp(1rem, 1.8vw, 1.3rem);
    margin: 1rem 0 .85rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    background: var(--surface);
    border: 1px solid var(--line);
    border-left: 5px solid var(--accent);
    border-radius: var(--radius-md);
    box-shadow: 0 6px 20px -14px rgba(15,23,42,.22);
}
.section-heading-card[data-accent="blue"]   { --accent: var(--accent-blue); }
.section-heading-card[data-accent="green"]  { --accent: var(--accent-green); }
.section-heading-card[data-accent="amber"]  { --accent: var(--accent-amber); }
.section-heading-card[data-accent="violet"] { --accent: var(--accent-violet); }
.section-heading-card[data-accent="red"]    { --accent: var(--accent-red); }
.section-heading-card[data-accent="slate"]  { --accent: var(--accent-slate); }

.shc-icon {
    flex: 0 0 auto;
    width: 42px; height: 42px;
    border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    background: color-mix(in srgb, var(--accent) 12%, #fff);
    color: var(--accent);
}
.shc-text { flex: 1 1 auto; min-width: 0; }
.shc-text h2 {
    font-size: clamp(.95rem, 1.7vw, 1.1rem);
    font-weight: 800;
    color: var(--ink-900);
    margin: 0;
    letter-spacing: -0.005em;
    font-family: 'Poppins', sans-serif;
}
.shc-text p {
    margin: 2px 0 0;
    font-size: clamp(.72rem, 1.3vw, .82rem);
    color: var(--ink-500);
    line-height: 1.35;
}
.shc-tag {
    flex: 0 0 auto;
    font-size: .66rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 10%, #fff);
    padding: .3rem .7rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--accent) 22%, transparent);
    white-space: nowrap;
}

/* ---------- Info card ---------- */
.info-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--line);
    box-shadow: 0 8px 26px -18px rgba(15,23,42,.22);
    overflow: hidden;
    margin-bottom: var(--card-gap);
    position: relative;
    width: 100%;
    max-width: 100%;
}
.info-card[data-accent="blue"]::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, #3b82f6, #1d4ed8);
}
.info-card-header {
    padding: .7rem 1.1rem;
    border-bottom: 1px solid var(--line);
    background: var(--surface-2);
}
.info-tag {
    font-size: .7rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--ink-500);
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.info-tag i { color: var(--accent-blue); }
.info-card-body {
    padding: var(--card-pad);
    display: grid;
    grid-template-columns: minmax(0, 200px) minmax(0, 1fr);
    gap: clamp(.85rem, 1.6vw, 1.3rem);
    align-items: flex-start;
    width: 100%;
    box-sizing: border-box;
}
.info-avatar-col { text-align: center; min-width: 0; }
.info-avatar-img, .info-avatar-placeholder {
    width: clamp(76px, 10vw, 100px);
    height: clamp(76px, 10vw, 100px);
    border-radius: 50%;
    object-fit: cover;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--accent-blue);
    box-shadow: 0 8px 20px -10px rgba(37,99,235,.55);
    background: #eff6ff;
    color: var(--accent-blue);
    font-size: clamp(2.6rem, 5vw, 3.5rem);
}
.info-name { margin-top: .6rem; }
.info-name strong { display: block; font-size: clamp(.9rem, 1.5vw, 1rem); font-weight: 700; color: var(--ink-900); overflow-wrap: anywhere; }
.info-name small  { display: block; font-size: .78rem; color: var(--ink-500); margin-top: 2px; }
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .65rem;
    align-content: flex-start;
    width: 100%;
    min-width: 0;
}
.info-tile {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .65rem .75rem;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    min-width: 0 !important;
    overflow: hidden;
    transition: border-color .2s ease, transform .2s ease;
}
.info-tile:hover { border-color: #c7d2fe; transform: translateY(-1px); }
.info-tile-icon {
    flex: 0 0 auto;
    width: 32px; height: 32px;
    border-radius: 9px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #eff6ff;
    color: var(--accent-blue);
    font-size: 1rem;
}
.info-tile > div { min-width: 0; flex: 1 1 auto; overflow: hidden; }
.info-tile small { display: block; font-size: .64rem; color: var(--ink-500); text-transform: uppercase; letter-spacing: .07em; font-weight: 700; }
.info-tile strong {
    display: block;
    font-size: clamp(.8rem, 1.3vw, .88rem);
    color: var(--ink-900);
    font-weight: 600;
    margin-top: 1px;
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
    line-height: 1.3;
}
.info-tile strong.wrap { word-break: break-all; }

/* ---------- Fee tiles ---------- */
.fee-tile {
    display: flex;
    flex-direction: column;
    gap: .55rem;
    padding: clamp(.9rem, 1.6vw, 1.2rem);
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--ink-900);
    position: relative;
    overflow: hidden;
    transition: transform .25s ease, box-shadow .25s ease;
    box-shadow: 0 6px 20px -14px rgba(15,23,42,.22);
    height: 100%;
    min-height: 120px;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}
.fee-tile::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 5px;
    background: var(--accent-slate);
}
.fee-tile:hover { transform: translateY(-4px); box-shadow: 0 18px 32px -18px rgba(15,23,42,.32); color: var(--ink-900); }
.fee-tile-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap; }
.fee-tile-icon {
    width: 38px; height: 38px;
    border-radius: 11px;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--surface-2);
    color: var(--accent-slate);
    font-size: 1.15rem;
}
.fee-tile-tag {
    font-size: .64rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--ink-500);
    font-weight: 700;
    white-space: nowrap;
}
.fee-tile-body small {
    display: block;
    font-size: .7rem;
    color: var(--ink-500);
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.fee-tile-value {
    font-size: clamp(1.05rem, 2.2vw, 1.4rem);
    font-weight: 800;
    color: var(--ink-900);
    line-height: 1.15;
    margin-top: .15rem;
    overflow-wrap: anywhere;
}
.fee-tile-arrow {
    position: absolute;
    right: 1rem; bottom: 1rem;
    color: var(--ink-300);
    font-size: 1rem;
    transition: transform .25s ease, color .25s ease;
}
.fee-tile:hover .fee-tile-arrow { transform: translateX(4px); }

.fee-tile.accent-blue::before   { background: var(--accent-blue);   }
.fee-tile.accent-blue   .fee-tile-icon { background: #eff6ff; color: var(--accent-blue); }
.fee-tile.accent-blue:hover .fee-tile-arrow { color: var(--accent-blue); }

.fee-tile.accent-green::before  { background: var(--accent-green);  }
.fee-tile.accent-green  .fee-tile-icon { background: #dcfce7; color: var(--accent-green); }
.fee-tile.accent-green:hover .fee-tile-arrow { color: var(--accent-green); }

.fee-tile.accent-red::before    { background: var(--accent-red);    }
.fee-tile.accent-red    .fee-tile-icon { background: #fee2e2; color: var(--accent-red); }
.fee-tile.accent-red:hover .fee-tile-arrow { color: var(--accent-red); }

.fee-tile.accent-slate::before  { background: var(--accent-slate);  }
.fee-tile.accent-slate  .fee-tile-icon { background: #f1f5f9; color: var(--accent-slate); }

/* ---------- Stat tiles ---------- */
.stat-tile {
    position: relative;
    padding: clamp(.85rem, 1.6vw, 1.15rem);
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--line);
    box-shadow: 0 6px 20px -16px rgba(15,23,42,.22);
    overflow: hidden;
    transition: transform .25s ease, box-shadow .25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}
.stat-tile::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 5px;
    background: var(--accent-blue);
}
.stat-tile:hover { transform: translateY(-3px); box-shadow: 0 18px 30px -18px rgba(15,23,42,.3); }
.stat-tile-icon {
    width: 36px; height: 36px;
    border-radius: 11px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    background: #eff6ff;
    color: var(--accent-blue);
}
.stat-tile-value { font-size: clamp(1.25rem, 2.6vw, 1.75rem); font-weight: 800; color: var(--ink-900); line-height: 1; }
.stat-tile-label { font-size: clamp(.7rem, 1.3vw, .8rem); color: var(--ink-500); font-weight: 600; }

.stat-tile.accent-blue::before   { background: var(--accent-blue);   } .stat-tile.accent-blue   .stat-tile-icon { background:#eff6ff; color:var(--accent-blue); }
.stat-tile.accent-green::before  { background: var(--accent-green);  } .stat-tile.accent-green  .stat-tile-icon { background:#dcfce7; color:var(--accent-green); }
.stat-tile.accent-red::before    { background: var(--accent-red);    } .stat-tile.accent-red    .stat-tile-icon { background:#fee2e2; color:var(--accent-red); }
.stat-tile.accent-amber::before  { background: var(--accent-amber);  } .stat-tile.accent-amber  .stat-tile-icon { background:#fef3c7; color:#b45309; }
.stat-tile.accent-violet::before { background: var(--accent-violet); } .stat-tile.accent-violet .stat-tile-icon { background:#ede9fe; color:var(--accent-violet); }
.stat-tile.accent-slate::before  { background: var(--accent-slate);  } .stat-tile.accent-slate  .stat-tile-icon { background:#f1f5f9; color:var(--accent-slate); }

/* ---------- Advice card ---------- */
.advice-card {
    --accent: var(--accent-blue);
    display: flex;
    gap: .85rem;
    align-items: flex-start;
    padding: clamp(.9rem, 1.8vw, 1.3rem);
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--line);
    border-left: 5px solid var(--accent);
    box-shadow: 0 6px 22px -16px rgba(15,23,42,.22);
    margin-bottom: var(--card-gap);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.advice-icon {
    flex: 0 0 auto;
    width: 44px; height: 44px;
    border-radius: 13px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 10%, #fff);
}
.advice-body { min-width: 0; flex: 1 1 auto; }
.advice-body h5 { font-size: clamp(.92rem, 1.6vw, 1.05rem); font-weight: 800; color: var(--ink-900); margin: 0 0 .3rem; }
.advice-body p { font-size: clamp(.8rem, 1.45vw, .9rem); color: var(--ink-700); margin: 0 0 .7rem; line-height: 1.5; }
.advice-chips, .chips-row { display: flex; flex-wrap: wrap; gap: .35rem; }

/* ---------- Chips / pills ---------- */
.chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .28rem .65rem;
    border-radius: 999px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    color: var(--ink-700);
    font-size: clamp(.66rem, 1.2vw, .76rem);
    font-weight: 600;
    white-space: nowrap;
    max-width: 100%;
}
.chip i { font-size: .95em; color: var(--ink-500); }
.chip strong { color: var(--ink-900); font-weight: 800; }
.chip-pass { background:#dcfce7; border-color:#bbf7d0; color:#166534; }
.chip-pass i { color:#16a34a; }
.chip-fail { background:#fee2e2; border-color:#fecaca; color:#991b1b; }
.chip-fail i { color:#dc2626; }

.pill { padding:.15rem .5rem; border-radius:999px; font-size:.62rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
.pill-pass  { background:#dcfce7; color:#166534; }
.pill-fail  { background:#fee2e2; color:#991b1b; }
.pill-muted { background:#f1f5f9; color:#64748b; }

/* ---------- Panel cards ---------- */
.panel-card {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 26px -18px rgba(15,23,42,.22);
    overflow: hidden;
    margin-bottom: var(--card-gap);
    height: 100%;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.panel-card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 5px;
    background: var(--accent-blue);
}
.panel-card.accent-blue::before   { background: linear-gradient(180deg, #3b82f6, #1d4ed8); }
.panel-card.accent-green::before  { background: linear-gradient(180deg, #34d399, #15803d); }
.panel-card.accent-amber::before  { background: linear-gradient(180deg, #fbbf24, #d97706); }
.panel-card.accent-violet::before { background: linear-gradient(180deg, #a78bfa, #6d28d9); }
.panel-card.accent-red::before    { background: linear-gradient(180deg, #f87171, #b91c1c); }

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .5rem;
    padding: .85rem 1.1rem;
    border-bottom: 1px solid var(--line);
    background: var(--surface-2);
    flex-wrap: wrap;
}
.panel-head h4 {
    font-size: clamp(.88rem, 1.6vw, 1rem);
    font-weight: 800;
    color: var(--ink-900);
    margin: 0;
    font-family: 'Poppins', sans-serif;
    letter-spacing: -0.005em;
}
.panel-tag {
    font-size: .66rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--ink-500);
    padding: .22rem .55rem;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 999px;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}
.panel-body { padding: var(--card-pad); flex: 1 1 auto; min-width: 0; }

/* ---------- Subject progress rows ---------- */
.subject-row { margin-bottom: .9rem; }
.subject-row:last-child { margin-bottom: 0; }
.subject-row-head {
    display: flex; justify-content: space-between; align-items: center;
    gap: .5rem; flex-wrap: wrap;
    font-size: clamp(.78rem, 1.4vw, .88rem);
    margin-bottom: .3rem;
}
.subject-name { font-weight: 700; color: var(--ink-900); overflow-wrap: anywhere; }
.subject-score { color: var(--ink-700); display: inline-flex; align-items: center; gap: .35rem; font-weight: 600; flex-wrap: wrap; }
.subject-score em { font-style: normal; color: var(--ink-500); font-weight: 500; font-size: .85em; }
.subject-bar { height: 8px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.subject-bar-fill { height: 100%; border-radius: 999px; background: var(--accent-blue); transition: width .8s cubic-bezier(.22,1,.36,1); }
.subject-note { font-size: .72rem; color: var(--ink-500); margin-top: .3rem; }
.note-good { color: #15803d; font-weight: 600; }
.note-bad  { color: #b91c1c; font-weight: 600; }

.big-score { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; color: var(--accent-blue); line-height: 1; letter-spacing: -.02em; }

/* ---------- Mini box ---------- */
.mini-box {
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    padding: .75rem;
    height: 100%;
    min-width: 0;
}
.mini-box-warn { background: #fff7ed; border-color: #fed7aa; }
.mini-box-title {
    font-size: .7rem;
    font-weight: 800;
    color: var(--ink-700);
    letter-spacing: .05em;
    text-transform: uppercase;
    margin-bottom: .45rem;
    display: flex;
    align-items: center;
    gap: .3rem;
}
.mini-box-title i { color: #f59e0b; }
.mini-box-warn .mini-box-title i { color: #dc2626; }
.mini-box ul { list-style: none; margin: 0; padding: 0; font-size: .78rem; }
.mini-box li { display: flex; justify-content: space-between; gap: .4rem; padding: .22rem 0; color: var(--ink-700); border-bottom: 1px dashed var(--line); overflow-wrap: anywhere; }
.mini-box li:last-child { border-bottom: none; }
.mini-box li strong { color: var(--ink-900); white-space: nowrap; }

/* ---------- Grade list ---------- */
.grade-list { display: flex; flex-direction: column; gap: .35rem; }
.grade-row {
    display: grid;
    grid-template-columns: 16px 1fr auto;
    align-items: center;
    gap: .6rem;
    padding: .45rem .7rem;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
}
.grade-dot { width: 14px; height: 14px; border-radius: 4px; }
.grade-name { font-weight: 700; color: var(--ink-700); font-size: .82rem; }
.grade-row strong { color: var(--ink-900); font-weight: 800; }
.grade-chart { position: relative; width: 100%; height: clamp(170px, 20vw, 210px); }

/* ---------- Table ---------- */
.dash-table {
    width: 100%;
    margin: 0;
    font-size: clamp(.76rem, 1.4vw, .88rem);
    border-collapse: separate;
    border-spacing: 0;
}
.dash-table thead th {
    background: var(--surface-2);
    color: var(--ink-700);
    font-weight: 700;
    font-size: .68rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: .65rem .7rem;
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
}
.dash-table tbody td {
    padding: .65rem .7rem;
    border-bottom: 1px solid var(--line);
    color: var(--ink-700);
    vertical-align: middle;
}
.dash-table tbody tr:hover td { background: rgba(37,99,235,.04); }
.dash-table tbody tr:last-child td { border-bottom: none; }
.td-subject { font-weight: 700; color: var(--ink-900); }
.td-remark { color: var(--ink-500); font-size: .85em; }
.dash-table tr.row-fail td { background: #fef2f2; }
.dash-table tr.row-fail td.td-subject { color: #991b1b; }
.dash-table tr.row-muted td { opacity: .68; background: #f8fafc; }
.table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ---------- Notice card ---------- */
.notice-card {
    display: flex;
    gap: .85rem;
    align-items: flex-start;
    padding: clamp(.9rem, 1.8vw, 1.3rem);
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--line);
    border-left: 5px solid var(--accent-slate);
    box-shadow: 0 6px 22px -16px rgba(15,23,42,.22);
    margin-bottom: var(--card-gap);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.notice-card.accent-amber { border-left-color: var(--accent-amber); }
.notice-card.accent-slate { border-left-color: var(--accent-slate); }
.notice-icon {
    flex: 0 0 auto;
    width: 44px; height: 44px;
    border-radius: 13px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    background: #f1f5f9;
    color: var(--accent-slate);
}
.notice-card.accent-amber .notice-icon { background:#fef3c7; color:#b45309; }
.notice-card h5 { font-weight: 800; color: var(--ink-900); font-size: clamp(.92rem, 1.6vw, 1.05rem); margin: 0 0 .25rem; }
.notice-card p { color: var(--ink-700); margin: 0; font-size: clamp(.8rem, 1.45vw, .9rem); line-height: 1.5; }

/* ---------- Trend chart ---------- */
.trend-chart { position: relative; width: 100%; height: clamp(190px, 30vw, 280px); }

/* ---------- Reveal animation ---------- */
.reveal { opacity: 0; transform: translateY(22px); transition: opacity .7s ease, transform .7s cubic-bezier(.22,1,.36,1); }
.reveal.revealed { opacity: 1; transform: translateY(0); }
.reveal[data-delay="50"]  { transition-delay: .05s; }
.reveal[data-delay="100"] { transition-delay: .1s; }
.reveal[data-delay="150"] { transition-delay: .15s; }
.reveal[data-delay="200"] { transition-delay: .2s; }
.reveal[data-delay="250"] { transition-delay: .25s; }
.reveal[data-delay="300"] { transition-delay: .3s; }
.reveal[data-delay="320"] { transition-delay: .32s; }
.reveal[data-delay="350"] { transition-delay: .35s; }
.reveal[data-delay="360"] { transition-delay: .36s; }
.reveal[data-delay="400"] { transition-delay: .4s; }
.reveal[data-delay="440"] { transition-delay: .44s; }
.reveal[data-delay="460"] { transition-delay: .46s; }
.reveal[data-delay="500"] { transition-delay: .5s; }
.reveal[data-delay="540"] { transition-delay: .54s; }
.reveal[data-delay="580"] { transition-delay: .58s; }
.reveal[data-delay="620"] { transition-delay: .62s; }
.reveal[data-delay="680"] { transition-delay: .68s; }
.reveal[data-delay="740"] { transition-delay: .74s; }

/* ---------- Sidebar toggle ---------- */
body.sidebar-hidden .app-sidebar { display: none !important; }
body.sidebar-hidden .app-content { margin-left: 0 !important; width: 100% !important; }
.app-sidebar { transition: all .3s ease; }

/* ---------- Dark mode ---------- */
body.dark .page-heading { background: linear-gradient(135deg, #0b1220, #1e293b); }
body.dark .info-card,
body.dark .fee-tile,
body.dark .stat-tile,
body.dark .panel-card,
body.dark .advice-card,
body.dark .notice-card,
body.dark .qa-btn,
body.dark .section-heading-card { background: #0f172a; border-color: #1e293b; color: #e2e8f0; }
body.dark .info-card-header,
body.dark .panel-head { background: #111827; border-color: #1e293b; }
body.dark .info-tile,
body.dark .mini-box,
body.dark .grade-row,
body.dark .chip,
body.dark .subject-bar { background: #111827; border-color: #1e293b; }
body.dark .qa-btn,
body.dark .qa-label,
body.dark .info-tile strong,
body.dark .info-name strong,
body.dark .panel-head h4,
body.dark .advice-body h5,
body.dark .notice-card h5,
body.dark .subject-name,
body.dark .stat-tile-value,
body.dark .fee-tile-value,
body.dark .shc-text h2 { color: #f1f5f9; }
body.dark .qa-sub,
body.dark .info-tile small,
body.dark .info-name small,
body.dark .info-tile-icon,
body.dark .stat-tile-label,
body.dark .fee-tile-tag,
body.dark .fee-tile-body small,
body.dark .panel-tag,
body.dark .advice-body p,
body.dark .notice-card p,
body.dark .chip,
body.dark .subject-note,
body.dark .td-remark,
body.dark .shc-text p { color: #94a3b8; }
body.dark .dash-table thead th { background: #111827; color: #cbd5e1; border-color: #1e293b; }
body.dark .dash-table tbody td { color: #e2e8f0; border-color: #1e293b; }
body.dark .dash-table tr.row-fail td { background: #2a1010; }
body.dark .dash-table tr.row-muted td { background: #0b1220; }
body.dark .panel-tag { background:#0b1220; border-color:#1e293b; }

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width: 1199.98px) {
    .quick-actions { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
    .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 991.98px) {
    :root { --edge-safe: clamp(0.75rem, 2vw, 1rem); }
    .dash-shell { max-width: 100% !important; }
    .info-card-body { grid-template-columns: minmax(0, 180px) minmax(0, 1fr); }
    .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}

@media (max-width: 767.98px) {
    :root { --edge-safe: clamp(0.65rem, 2vw, 0.9rem); }
    .info-card-body { grid-template-columns: 1fr; text-align: center; }
    .info-avatar-col { margin: 0 auto; }
    .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); text-align: left; }
    .page-heading { padding: .9rem 1rem; }
    .advice-card, .notice-card { flex-direction: column; }
    .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .section-heading-card { flex-wrap: wrap; }
    .shc-tag { margin-left: auto; }
}

@media (max-width: 480px) {
    :root { --edge-safe: 0.6rem; }
    .quick-actions { grid-template-columns: 1fr !important; }
    .info-grid { grid-template-columns: 1fr; }
    .qa-btn { min-height: 62px; }
    .panel-head { padding: .7rem .85rem; }
    .panel-body { padding: .85rem; }
    .qa-label { font-size: .84rem; }
    .qa-sub { font-size: .68rem; }
}

/* ---------- Print ---------- */
@media print {
    .quick-actions, .app-sidebar { display: none !important; }
    .page-heading { background: #fff !important; color: #000 !important; box-shadow: none; }
    .page-heading-text h1, .page-heading-text p, .page-heading-text .eyebrow { color: #000 !important; }
    .info-card, .panel-card, .advice-card, .notice-card, .stat-tile, .fee-tile, .section-heading-card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
}
</style>
</body>
</html>