<?php
/**
 * Advanced Student Results Page
 *
 * A) School total / average / overall grade -> ALL attended subjects.
 * B) Division and points -> computed per term from the student's
 *    combination subjects. If fewer than 3 principal subjects have
 *    marks, the calculation engine promotes the best-scoring non-
 *    principal subjects so a division is still produced.
 *
 * Display rules on this page:
 *   - Score of 0 (or null / '') = not attended; never displayed,
 *     never counted, never passed to the engine.
 *   - No fallback banner, no "with fallback" text, no FALLBACK badges.
 *     The promoted subject simply participates silently.
 *   - No combination line under the class name, no class-level
 *     division box. Division / points are shown only per term so
 *     there is a single source of truth on the page.
 *   - "Save PDF" opens student/save_pdf?term=<id> in a new tab.
 */

chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Student only
if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student = [
    'fname'         => $fname,
    'lname'         => $lname,
    'id'            => $account_id,
    'display_image' => $img ?? null,
    'gender'        => $gender ?? '',
    'email'         => $email ?? ''
];

$resultsDisabled = (WBResAvi != "1");

/*
 * OPTIONAL CLASS -> COMBINATION OVERRIDES
 *
 * Use only when a class name does not contain the combination.
 * Example:
 *   32 => 'PCM',
 *   33 => 'HKL'
 */
$CLASS_COMBINATION_OVERRIDES = [];

try {
    $conn = new PDO(
        'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset,
        DBUser,
        DBPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ------------------------------------------------------------
    // Grade + division configuration
    // ------------------------------------------------------------
    $stmt = $conn->query("SELECT * FROM tbl_grade_system ORDER BY min DESC");
    $grading = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT * FROM tbl_division_system ORDER BY min_point ASC");
    $divisionSystem = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $GLOBALS['gradeSystem']    = $grading;
    $GLOBALS['divisionSystem'] = $divisionSystem;

    // ------------------------------------------------------------
    // Student classes
    // ------------------------------------------------------------
    $stmt = $conn->prepare("SELECT DISTINCT class FROM tbl_exam_results WHERE student = ? ORDER BY class ASC");
    $stmt->execute([$account_id]);
    $studentClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ------------------------------------------------------------
    // Subject-combination records used by this existing SRMS schema
    // ------------------------------------------------------------
    $stmt = $conn->query(" 
        SELECT sc.id, sc.class, sc.subject, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
    ");

    $rawCombinationRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subjectsByClass = [];

    foreach ($rawCombinationRows as $row) {
        $classList = @unserialize($row['class']);

        if (!is_array($classList)) {
            if ($row['class'] !== null && $row['class'] !== '') {
                $classList = [(int)$row['class']];
            } else {
                continue;
            }
        }

        foreach ($classList as $classId) {
            $classId = (int)$classId;
            if ($classId <= 0) continue;

            $subjectsByClass[$classId][] = [
                'id'         => (int)$row['id'],
                'subject_id' => (int)$row['subject'],
                'name'       => $row['subject_name'] ?: 'Unknown Subject'
            ];
        }
    }

    // ------------------------------------------------------------
    // Fetch all student results once
    // ------------------------------------------------------------
    $stmt = $conn->prepare(" 
        SELECT
            er.*,
            c.name AS class_name,
            t.name AS term_name,
            t.id AS term_id
        FROM tbl_exam_results er
        LEFT JOIN tbl_classes c ON er.class = c.id
        LEFT JOIN tbl_terms t ON er.term = t.id
        WHERE er.student = ?
        ORDER BY er.class ASC, er.term ASC, er.subject_combination ASC
    ");
    $stmt->execute([$account_id]);
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------
    // Fast lookup: [class][term][subject_combination_id] = score
    // ------------------------------------------------------------
    $resultLookup = [];
    foreach ($allResults as $r) {
        $classId = (int)$r['class'];
        $termId  = (int)$r['term'];
        $scId    = (int)$r['subject_combination'];

        $resultLookup[$classId][$termId][$scId] = $r['score'];
    }

    // ------------------------------------------------------------
    // Term ordering
    // ------------------------------------------------------------
    $termOrder = [];
    if (!empty($allResults)) {
        $stmt = $conn->prepare(" 
            SELECT DISTINCT t.id, t.name
            FROM tbl_terms t
            INNER JOIN tbl_exam_results er ON er.term = t.id
            WHERE er.student = ?
            ORDER BY t.id ASC
        ");
        $stmt->execute([$account_id]);
        $termOrder = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    $termOrderValues = array_values($termOrder);

    // ------------------------------------------------------------
    // Combination resolver per class (only class meta, no division)
    // ------------------------------------------------------------
    $classMeta = [];
    foreach ($studentClasses as $classId) {
        $classId = (int)$classId;

        $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
        $stmt->execute([$classId]);
        $className = $stmt->fetchColumn() ?: ('Class ' . $classId);

        $combination = null;
        $combinationSource = 'not detected';

        if (isset($CLASS_COMBINATION_OVERRIDES[$classId])) {
            $combination = normalize_combination_code($CLASS_COMBINATION_OVERRIDES[$classId]);
            $combinationSource = 'class override';
        } else {
            $combination = detect_combination_from_class_name($className);
            if ($combination !== null) {
                $combinationSource = 'class name';
            }
        }

        $details = get_combination_details($combination);

        $classMeta[$classId] = [
            'name'                => $className,
            'combination'         => $combination,
            'combination_source'  => $combinationSource,
            'combination_details' => $details,
        ];
    }

    // ------------------------------------------------------------
    // Analytics (all recorded subjects, school-facing).
    // A recorded score of 0 is a valid F mark; only missing/null scores are skipped.
    // ------------------------------------------------------------
    $subjectAnalytics  = [];
    $subjectAllScores  = [];
    $gradeCounts       = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'E'=>0, 'S'=>0, 'F'=>0];

    foreach ($allResults as $r) {
        $classId = (int)$r['class'];
        $scId    = (int)$r['subject_combination'];
        $subjectName = '';

        foreach (($subjectsByClass[$classId] ?? []) as $subject) {
            if ((int)$subject['id'] === $scId) {
                $subjectName = $subject['name'];
                break;
            }
        }

        if ($subjectName === '') continue;

        $termName = $r['term_name'] ?: ('Term ' . (int)$r['term']);
        $score    = $r['score'];

        if ($score === null || $score === '') continue;

        $subjectAnalytics[$subjectName][$termName] = $score;
        $subjectAllScores[$subjectName][] = $score;

        $g = get_grade($score, $grading);
        if (isset($gradeCounts[$g])) {
            $gradeCounts[$g]++;
        }
    }

    // Subject averages
    $subjectAverages = [];
    foreach ($subjectAllScores as $subject => $scores) {
        $valid = array_values(array_filter($scores, function ($s) {
            return $s !== null && $s !== '' && (float)$s != 0;
        }));
        if (!empty($valid)) {
            $subjectAverages[$subject] = round(array_sum($valid) / count($valid));
        }
    }

    arsort($subjectAverages);

    // Term averages
    $termAverages = [];
    foreach ($termOrderValues as $termName) {
        $scores = [];
        foreach ($subjectAnalytics as $terms) {
            if (isset($terms[$termName]) && $terms[$termName] !== null && $terms[$termName] !== '' && (float)$terms[$termName] != 0) {
                $scores[] = (float)$terms[$termName];
            }
        }
        $termAverages[$termName] = !empty($scores)
            ? round(array_sum($scores) / count($scores))
            : 0;
    }

    // Radar data
    $radarLabels = array_keys($subjectAverages);
    $radarData   = array_values($subjectAverages);

    // ------------------------------------------------------------
    // Latest term / class
    // ------------------------------------------------------------
    $latestTermId    = null;
    $latestTermName  = null;
    $latestClassId   = null;
    $latestClassName = null;

    if (!empty($allResults)) {
        $latestTermId = max(array_map('intval', array_column($allResults, 'term')));

        foreach ($allResults as $r) {
            if ((int)$r['term'] === $latestTermId) {
                $latestClassId   = (int)$r['class'];
                $latestClassName = $r['class_name'];
                $latestTermName  = $r['term_name'];
                break;
            }
        }
    }

    // ------------------------------------------------------------
    // Class averages for latest term (used by subject analytics)
    // ------------------------------------------------------------
    $classAverages = [];

    if ($latestTermId && $latestClassId) {
        $stmt = $conn->prepare(" 
            SELECT sc.id, sc.subject, AVG(r.score) AS avg_score
            FROM tbl_exam_results r
            INNER JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id
            WHERE r.class = ?
              AND r.term = ?
              AND r.score > 0
            GROUP BY sc.id, sc.subject
        ");
        $stmt->execute([$latestClassId, $latestTermId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classAverages[(int)$row['id']] = round((float)$row['avg_score']);
        }
    }

    $totalTerms     = count($termOrderValues);
    $overallAverage = !empty($subjectAverages)
        ? round(array_sum($subjectAverages) / count($subjectAverages))
        : 0;

} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>My Examination Results</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Results</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="results-shell">

                <?php if ($resultsDisabled): ?>
                    <div class="release-card text-center">
                        <h3>Results are being prepared</h3>
                        <p class="text-muted">Your results will appear here when the school releases them.</p>
                        <div class="progress mx-auto" style="max-width:460px;height:18px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:72%">Checking...</div>
                        </div>
                        <button class="btn btn-primary mt-4" onclick="location.reload()">
                            Refresh
                        </button>
                    </div>
                <?php elseif (empty($studentClasses)): ?>
                    <div class="alert alert-info mb-0">No examination results found for your account.</div>
                <?php else: ?>

                    <!-- =====================================================
                         OVERVIEW
                    ====================================================== -->
                    <div class="hero-card mb-4">
                        <div class="hero-content">
                            <div>
                                <span class="eyebrow">Academic performance</span>
                                <h2><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></h2>
                                <p class="mb-0">
                                    <?php echo $latestClassName ? htmlspecialchars($latestClassName) : 'Advanced Level'; ?>
                                    <?php if ($latestTermName): ?>
                                        <span class="dot">|</span> <?php echo htmlspecialchars($latestTermName); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="hero-stat">
                                <span>Overall Average</span>
                                <strong><?php echo $overallAverage; ?>%</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-lg-3">
                            <div class="metric-card">
                                <span>Terms</span>
                                <strong><?php echo $totalTerms; ?></strong>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="metric-card">
                                <span>Subjects</span>
                                <strong><?php echo count($subjectAverages); ?></strong>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="metric-card">
                                <span>Average</span>
                                <strong><?php echo $overallAverage; ?>%</strong>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="metric-card">
                                <span>Best Grade</span>
                                <strong>
                                    <?php
                                    $bestGrade = 'N/A';
                                    foreach (['A','B','C','D','E','S','F'] as $g) {
                                        if (($gradeCounts[$g] ?? 0) > 0) {
                                            $bestGrade = $g;
                                            break;
                                        }
                                    }
                                    echo $bestGrade;
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- =====================================================
                         CHARTS
                    ====================================================== -->
                    <div class="row g-3 mb-4">
                        <div class="col-lg-4">
                            <div class="panel-card h-100">
                                <div class="panel-title"><span>Grade Distribution</span></div>
                                <div class="chart-box"><canvas id="gradePieChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="panel-card h-100">
                                <div class="panel-title"><span>Term Averages</span></div>
                                <div class="chart-box"><canvas id="termBarChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="panel-card h-100">
                                <div class="panel-title"><span>Subject Profile</span></div>
                                <div class="chart-box"><canvas id="radarChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- =====================================================
                         SUBJECT SEARCH / ANALYTICS
                    ====================================================== -->
                    <div class="panel-card mb-4">
                        <div class="panel-title mb-3">
                            <span>Subject Performance Analytics</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-5">
                                <label class="form-label fw-semibold">Search subject</label>
                                <input type="text" id="subjectSearch" class="form-control search-input" placeholder="e.g. Chemistry, History..." autocomplete="off">
                                <div id="subjectSuggestions" class="list-group mt-1"></div>
                            </div>
                            <div class="col-lg-7">
                                <div id="subjectStats" class="analytics-placeholder">
                                    Select a subject to view its performance trend and statistics.
                                </div>
                            </div>
                        </div>

                        <div id="subjectTrendContainer" class="mt-4" style="display:none;">
                            <hr>
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <div class="chart-box wide"><canvas id="subjectTrendChart"></canvas></div>
                                </div>
                                <div class="col-lg-4">
                                    <div id="subjectAdvice" class="advice-box">
                                        <h6>Performance Insight</h6>
                                        <p id="adviceText" class="mb-0"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =====================================================
                         RESULTS BY CLASS / TERM
                    ====================================================== -->
                    <?php foreach ($studentClasses as $classId): ?>
                        <?php
                        $classId = (int)$classId;
                        $meta = $classMeta[$classId];
                        $className = $meta['name'];
                        $combination = $meta['combination'];
                        $combinationDetails = $meta['combination_details'];

                        $stmt = $conn->prepare("SELECT DISTINCT term FROM tbl_exam_results WHERE class = ? AND student = ? ORDER BY term DESC");
                        $stmt->execute([$classId, $account_id]);
                        $terms = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                        ?>

                        <div class="panel-card mb-4">
                            <div class="class-heading mb-3">
                                <span class="class-label">CLASS</span>
                                <h4><?php echo htmlspecialchars($className); ?></h4>
                            </div>

                            <ul class="nav nav-tabs result-tabs" role="tablist">
                                <?php $firstTab = true; foreach ($terms as $termId): ?>
                                    <?php
                                    $stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ?");
                                    $stmt->execute([$termId]);
                                    $termName = $stmt->fetchColumn() ?: ('Term ' . $termId);
                                    $tabId = 'result_' . $classId . '_' . $termId;
                                    ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#<?php echo $tabId; ?>" type="button">
                                            <?php echo htmlspecialchars($termName); ?>
                                        </button>
                                    </li>
                                    <?php $firstTab = false; ?>
                                <?php endforeach; ?>
                            </ul>

                            <div class="tab-content pt-4">
                                <?php $firstPane = true; foreach ($terms as $termId): ?>
                                    <?php
                                    $tabId = 'result_' . $classId . '_' . $termId;
                                    $resultsForTerm = $resultLookup[$classId][$termId] ?? [];
                                    $subjects = $subjectsByClass[$classId] ?? [];

                                    // Build the recorded subject list for this term.
                                    // A score of 0 is a valid F mark; a missing row is absent.
                                    $subjectObjects = [];
                                    foreach ($subjects as $subject) {
                                        $sid = (int)$subject['id'];
                                        if (!array_key_exists($sid, $resultsForTerm)) continue;

                                        $raw = $resultsForTerm[$sid];
                                        if ($raw === null || $raw === '') continue;

                                        $subjectObjects[] = [
                                            'name'  => $subject['name'],
                                            'score' => (float)$raw,
                                            'id'    => $sid
                                        ];
                                    }

                                    // School result (all attended subjects)
                                    $schoolResult = calculate_school_result($subjectObjects, $grading);

                                    // Division + points (single source of truth for this term)
                                    $acsee = null;
                                    if ($combination) {
                                        $acsee = calculate_acsee_result(
                                            $subjectObjects,
                                            $combination,
                                            $grading,
                                            $divisionSystem
                                        );
                                    }

                                    // Names of subjects actually used (principal + any promoted)
                                    $usedNames = [];
                                    if ($acsee) {
                                        foreach (($acsee['used_subjects'] ?? []) as $u) {
                                            $usedNames[] = normalize_subject_name($u['name'] ?? '');
                                        }
                                    }
                                    ?>

                                    <div class="tab-pane fade <?php echo $firstPane ? 'show active' : ''; ?>" id="<?php echo $tabId; ?>" role="tabpanel">

                                        <?php if (!empty($schoolResult['attended'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle result-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:60px">#</th>
                                                            <th>SUBJECT</th>
                                                            <th class="text-center">SCORE</th>
                                                            <th class="text-center">GRADE</th>
                                                            <th class="text-center">REMARK</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($schoolResult['attended'] as $index => $row): ?>
                                                            <?php
                                                            $normRow = normalize_subject_name($row['name']);
                                                            $isUsed = in_array($normRow, $usedNames, true);

                                                            $isPrincipal = false;
                                                            if ($combinationDetails) {
                                                                foreach ($combinationDetails['subjects'] as $p) {
                                                                    if (subjects_match($row['name'], $p)) {
                                                                        $isPrincipal = true;
                                                                        break;
                                                                    }
                                                                }
                                                            }

                                                            // Badge rule:
                                                            //   principal                       -> PRINCIPAL
                                                            //   used but not principal          -> (no badge)
                                                            //   not principal and not used      -> NOT COUNTED
                                                            $badgeClass = '';
                                                            $badgeLabel = '';
                                                            if ($isPrincipal) {
                                                                $badgeClass = 'principal-badge';
                                                                $badgeLabel = 'PRINCIPAL';
                                                            } elseif (!$isUsed) {
                                                                $badgeClass = 'excluded-badge';
                                                                $badgeLabel = 'NOT COUNTED';
                                                            }
                                                            ?>
                                                            <tr>
                                                                <td><?php echo $index + 1; ?></td>
                                                                <td>
                                                                    <div class="subject-cell">
                                                                        <span><?php echo htmlspecialchars($row['name']); ?></span>
                                                                        <?php if ($badgeLabel !== ''): ?>
                                                                            <span class="<?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center fw-semibold"><?php echo rtrim(rtrim(number_format($row['score'], 2, '.', ''), '0'), '.'); ?>%</td>
                                                                <td class="text-center"><span class="grade-pill grade-<?php echo htmlspecialchars($row['grade']); ?>"><?php echo htmlspecialchars($row['grade']); ?></span></td>
                                                                <td class="text-center"><?php echo htmlspecialchars($row['remark']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-state">No attended subjects for this term.</div>
                                        <?php endif; ?>

                                        <!-- =========================================
                                             SCHOOL SUMMARY (all attended subjects)
                                        ========================================== -->
                                        <div class="summary-section school-summary">
                                            <div class="section-caption">School performance</div>
                                            <div class="summary-row">
                                                <div class="summary-item">
                                                    <span>Total</span>
                                                    <strong><?php echo $schoolResult['total']; ?></strong>
                                                </div>
                                                <div class="summary-item">
                                                    <span>Average</span>
                                                    <strong><?php echo $schoolResult['average']; ?>%</strong>
                                                </div>
                                                <div class="summary-item">
                                                    <span>Overall Grade</span>
                                                    <strong><?php echo htmlspecialchars($schoolResult['average_grade']); ?></strong>
                                                </div>
                                                <div class="summary-item">
                                                    <span>Remark</span>
                                                    <strong><?php echo htmlspecialchars($schoolResult['average_remark']); ?></strong>
                                                </div>
                                            </div>
                                            <div class="formula-note">
                                                School total and average use all attended subjects shown above.
                                            </div>
                                        </div>

                                        <!-- =========================================
                                             DIVISION AND POINTS
                                        ========================================== -->
                                        <div class="summary-section result-summary">
                                            <div class="section-caption">Division and points</div>

                                            <?php if (!$combination): ?>
                                                <div class="projection-warning">
                                                    <div>
                                                        <strong>Combination not detected.</strong>
                                                        <span>The class name should contain a combination code (e.g. PCM, HKL, PCB), or a class override must be set. Division and points are therefore not guessed.</span>
                                                    </div>
                                                </div>

                                            <?php elseif (!$acsee || !$acsee['complete']): ?>
                                                <div class="projection-warning">
                                                    <div>
                                                        <strong>Division and points pending.</strong>
                                                        <span>There were not enough attended subjects to assemble 3 subjects for this term.</span>
                                                    </div>
                                                </div>

                                            <?php else: ?>
                                                <div class="acsee-grid">
                                                    <div class="acsee-principals">
                                                        <?php foreach ($acsee['used_subjects'] as $used): ?>
                                                            <?php $uGrade = get_grade($used['score'], $grading); ?>
                                                            <div class="principal-result">
                                                                <div>
                                                                    <span><?php echo htmlspecialchars($used['name']); ?></span>
                                                                    <small>
                                                                        <?php echo rtrim(rtrim(number_format($used['score'], 2, '.', ''), '0'), '.'); ?>%
                                                                        | Grade <?php echo $uGrade; ?>
                                                                    </small>
                                                                </div>
                                                                <strong><?php echo get_division_point_from_grade($uGrade); ?></strong>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div class="acsee-final">
                                                        <span>POINTS</span>
                                                        <strong><?php echo (int)$acsee['points']; ?></strong>
                                                        <small>Division <?php echo htmlspecialchars($acsee['division']); ?></small>
                                                    </div>
                                                </div>

                                                <?php if (!empty($acsee['excluded_subjects'])): ?>
                                                    <div class="excluded-panel">
                                                        <div class="excluded-heading">Subjects not counted toward division</div>
                                                        <div class="excluded-list">
                                                            <?php foreach ($acsee['excluded_subjects'] as $ex): ?>
                                                                <?php $exGrade = get_grade($ex['score'], $grading); ?>
                                                                <span class="excluded-chip">
                                                                    <?php echo htmlspecialchars($ex['name']); ?>
                                                                    (<?php echo htmlspecialchars($exGrade); ?>)
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="excluded-note">
                                                            These subjects remain part of the school totals but they were not used for the division or points.
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="formula-note acsee-note">
                                                    Points and division were computed from the three subjects shown above.
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="result-actions mt-3">
                                            <a href="javascript:void(0);"
                                               data-pdf-url="student/save_pdf?term=<?php echo $termId; ?>"
                                               class="btn btn-primary btn-pdf">
                                                Save PDF
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
                                                Print
                                            </button>
                                        </div>
                                    </div>

                                    <?php $firstPane = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- =====================================================
                         ALL-TERM TREND
                    ====================================================== -->
                    <?php if (!empty($subjectAnalytics) && count($termOrderValues) > 1): ?>
                        <div class="panel-card">
                            <div class="panel-title mb-3"><span>Subject Performance Trend</span></div>
                            <div class="table-responsive">
                                <table class="table table-bordered trend-table">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <?php foreach ($termOrderValues as $termName): ?>
                                                <th class="text-center"><?php echo htmlspecialchars($termName); ?></th>
                                            <?php endforeach; ?>
                                            <th class="text-center">Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjectAnalytics as $subject => $scores): ?>
                                            <?php $firstScore = null; $lastScore = null; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject); ?></td>
                                                <?php foreach ($termOrderValues as $termName): ?>
                                                    <?php
                                                    $score = $scores[$termName] ?? null;
                                                    if ($score === null || $score === '') {
                                                        $display = '-';
                                                    } else {
                                                        $display = rtrim(rtrim(number_format((float)$score, 2, '.', ''), '0'), '.') . '%';
                                                        if ($firstScore === null) $firstScore = (float)$score;
                                                        $lastScore = (float)$score;
                                                    }
                                                    ?>
                                                    <td class="text-center"><?php echo htmlspecialchars($display); ?></td>
                                                <?php endforeach; ?>
                                                <td class="text-center">
                                                    <?php
                                                    if ($firstScore === null || $lastScore === null) {
                                                        echo 'N/A';
                                                    } elseif ($lastScore > $firstScore) {
                                                        echo '<span class="trend-up">Improved</span>';
                                                    } elseif ($lastScore < $firstScore) {
                                                        echo '<span class="trend-down">Declined</span>';
                                                    } else {
                                                        echo '<span class="trend-steady">Stable</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap');

.app-content{background:transparent!important}
.results-shell{font-family:'Inter',sans-serif}
.hero-card{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;border-radius:28px;padding:30px;box-shadow:0 18px 50px rgba(15,23,42,.16)}
.hero-content{display:flex;justify-content:space-between;gap:20px;align-items:center}.hero-card h2{font-family:'Poppins',sans-serif;font-weight:800;margin:6px 0}.hero-card p{color:#cbd5e1}.eyebrow{font-size:.72rem;text-transform:uppercase;letter-spacing:.13em;color:#93c5fd;font-weight:700}.dot{padding:0 8px;color:#60a5fa}.hero-stat{min-width:170px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:18px;text-align:center}.hero-stat span{display:block;font-size:.75rem;color:#cbd5e1}.hero-stat strong{display:block;font-size:2rem;font-family:'Poppins',sans-serif;margin-top:3px}
.metric-card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;box-shadow:0 8px 24px rgba(15,23,42,.05)}.metric-card span{display:block;color:#64748b;font-size:.8rem;font-weight:600}.metric-card strong{display:block;color:#0f172a;font-size:1.7rem;font-family:'Poppins',sans-serif;margin-top:3px}
.panel-card{background:rgba(255,255,255,.96);border:1px solid #e5e7eb;border-radius:24px;padding:22px;box-shadow:0 10px 32px rgba(15,23,42,.05)}.panel-title{display:flex;justify-content:space-between;align-items:center;font-family:'Poppins',sans-serif;font-weight:700;color:#0f172a}.chart-box{height:210px;position:relative}.chart-box.wide{height:260px}
.class-heading{margin-bottom:18px}.class-label{display:block;font-size:.68rem;letter-spacing:.13em;color:#64748b;font-weight:800}.class-heading h4{font-family:'Poppins',sans-serif;font-weight:800;margin:4px 0 0;color:#0f172a}
.result-tabs .nav-link{border:none;color:#64748b;border-radius:999px;padding:.65rem 1rem;font-weight:700;margin-right:6px}.result-tabs .nav-link.active{background:#2563eb;color:#fff;box-shadow:0 6px 18px rgba(37,99,235,.2)}
.result-table thead th{background:#f8fafc;color:#475569;font-size:.72rem;letter-spacing:.04em}.result-table td{font-size:.87rem}.subject-cell{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.principal-badge{font-size:.58rem;padding:3px 7px;background:#dbeafe;color:#1d4ed8;border-radius:999px;font-weight:800}.excluded-badge{font-size:.58rem;padding:3px 7px;background:#f1f5f9;color:#64748b;border-radius:999px;font-weight:700}
.grade-pill{display:inline-flex;min-width:31px;justify-content:center;padding:4px 8px;border-radius:999px;font-weight:800}.grade-A{background:#dcfce7;color:#166534}.grade-B{background:#dbeafe;color:#1d4ed8}.grade-C{background:#fef3c7;color:#92400e}.grade-D{background:#ffedd5;color:#9a3412}.grade-E{background:#fce7f3;color:#9d174d}.grade-S{background:#ede9fe;color:#6d28d9}.grade-F{background:#fee2e2;color:#991b1b}
.summary-section{margin-top:22px;border-radius:20px;padding:17px}.school-summary{background:#f8fafc;border:1px solid #e2e8f0}.result-summary{background:linear-gradient(145deg,#eff6ff,#f8fafc);border:1px solid #bfdbfe}.section-caption{font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;font-weight:800;color:#64748b;margin-bottom:12px}.summary-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.summary-item{background:#fff;border-radius:15px;padding:12px;border:1px solid #e2e8f0}.summary-item span{display:block;font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em}.summary-item strong{display:block;font-family:'Poppins',sans-serif;font-size:1.12rem;margin-top:2px;color:#0f172a}.formula-note{font-size:.76rem;color:#64748b;margin-top:12px}.acsee-note strong{color:#1d4ed8}
.projection-warning{display:flex;gap:12px;background:#fff;border:1px solid #fde68a;border-radius:16px;padding:15px;color:#713f12}.projection-warning strong,.projection-warning span{display:block}.projection-warning span{font-size:.78rem;color:#78350f;margin-top:3px}
.acsee-grid{display:grid;grid-template-columns:1fr 220px;gap:16px}.acsee-principals{display:grid;gap:8px}.principal-result{display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid #dbeafe;border-radius:15px;padding:10px 13px}.principal-result span{display:block;font-weight:700;color:#0f172a}.principal-result small{display:block;color:#64748b;margin-top:2px}.principal-result strong{display:flex;width:38px;height:38px;border-radius:12px;align-items:center;justify-content:center;background:#dbeafe;color:#1d4ed8;font-family:'Poppins',sans-serif;flex-shrink:0}
.acsee-final{display:flex;flex-direction:column;justify-content:center;align-items:center;background:#0f172a;color:#fff;border-radius:18px;padding:20px;text-align:center}.acsee-final span{font-size:.64rem;letter-spacing:.11em;font-weight:800;color:#93c5fd}.acsee-final strong{font-family:'Poppins',sans-serif;font-size:3rem;line-height:1;margin:6px 0}.acsee-final small{font-size:.9rem;color:#e2e8f0;font-weight:700}
.excluded-panel{margin-top:16px;background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:14px 16px}.excluded-heading{font-size:.72rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px}.excluded-list{display:flex;flex-wrap:wrap;gap:8px}.excluded-chip{background:#f1f5f9;color:#334155;padding:6px 10px;border-radius:999px;font-size:.75rem;font-weight:600}.excluded-note{font-size:.74rem;color:#64748b;margin-top:10px;line-height:1.45}
.result-actions{display:flex;justify-content:flex-end;gap:8px}.search-input{border-radius:999px;padding:.75rem 1rem}.analytics-placeholder{border:1px dashed #cbd5e1;background:#f8fafc;border-radius:16px;padding:16px;color:#64748b;min-height:47px}.advice-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:15px}.advice-box h6{font-family:'Poppins',sans-serif;font-weight:700;color:#0f172a}.advice-box p{font-size:.82rem;color:#475569}
.trend-table thead th{background:#f8fafc;font-size:.72rem}.trend-up{color:#15803d;font-weight:700}.trend-down{color:#b91c1c;font-weight:700}.trend-steady{color:#a16207;font-weight:700}.empty-state{padding:30px;text-align:center;color:#64748b;background:#f8fafc;border-radius:16px}
.release-card{background:#fff;border:1px solid #e5e7eb;border-radius:26px;padding:55px 25px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.release-card h3{font-family:'Poppins',sans-serif;font-weight:800}
@media(max-width:900px){.hero-content{flex-direction:column}.hero-stat{width:100%;max-width:none}.summary-row{grid-template-columns:repeat(2,1fr)}.acsee-grid{grid-template-columns:1fr}.result-actions{justify-content:stretch}.result-actions>*{flex:1}.chart-box{height:190px}}
@media(max-width:576px){.hero-card{padding:22px}.panel-card{padding:16px}.summary-row{grid-template-columns:1fr}.result-table{min-width:760px}.acsee-final{min-height:170px}}

body.dark .metric-card,body.dark .panel-card,body.dark .summary-item,body.dark .principal-result,body.dark .release-card,body.dark .analytics-placeholder,body.dark .excluded-panel{background:#0f172a;color:#e2e8f0;border-color:#334155}.dark .panel-title,.dark .class-heading h4,.dark .summary-item strong,.dark .principal-result span,.dark .advice-box h6{color:#e2e8f0}.dark .formula-note,.dark .analytics-placeholder,.dark .metric-card span,.dark .summary-item span,.dark .principal-result small,.dark .excluded-heading,.dark .excluded-note{color:#94a3b8}.dark .school-summary{background:#111827;border-color:#334155}.dark .result-table thead th,.dark .trend-table thead th{background:#111827;color:#cbd5e1}.dark .result-table td,.dark .trend-table td{color:#e2e8f0}.dark .excluded-chip{background:#1e293b;color:#cbd5e1}

@media print{.app-sidebar,.app-header,.app-title,.hero-card,.metric-card,.panel-card .panel-title,.result-actions,.nav-tabs,.result-tabs,.chart-box{display:none!important}.app-content{margin:0!important;padding:0!important}.panel-card{box-shadow:none;border:0;padding:0;margin-bottom:30px}.summary-section{break-inside:avoid}.result-table{min-width:0!important;width:100%!important}.result-table th,.result-table td{font-size:10pt}.result-summary{border:1px solid #999!important}}
</style>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(function(){

    /* ============================================================
     * SAVE PDF BUTTON
     * Delegated binding. The Print button has no data-pdf-url,
     * so it keeps its own onclick handler.
     * ============================================================ */
    $(document).on('click', '.btn-pdf', function (e) {
        var pdfUrl = $(this).data('pdf-url');
        if (pdfUrl) {
            e.preventDefault();
            window.open(pdfUrl, '_blank');
        }
    });

    const subjectData = <?php echo json_encode($subjectAnalytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const termOrder   = <?php echo json_encode($termOrderValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const subjectList = Object.keys(subjectData);
    let subjectTrendChart = null;

    const gradeData = {
        labels: ['A','B','C','D','E','S','F'],
        datasets: [{
            data: [
                <?php echo (int)$gradeCounts['A']; ?>,
                <?php echo (int)$gradeCounts['B']; ?>,
                <?php echo (int)$gradeCounts['C']; ?>,
                <?php echo (int)$gradeCounts['D']; ?>,
                <?php echo (int)$gradeCounts['E']; ?>,
                <?php echo (int)$gradeCounts['S']; ?>,
                <?php echo (int)$gradeCounts['F']; ?>
            ],
            backgroundColor: ['#16a34a','#2563eb','#f59e0b','#f97316','#ec4899','#8b5cf6','#dc2626'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    if (document.getElementById('gradePieChart')) {
        new Chart(document.getElementById('gradePieChart'), {
            type: 'doughnut',
            data: gradeData,
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
        });
    }

    if (document.getElementById('termBarChart')) {
        new Chart(document.getElementById('termBarChart'), {
            type:'bar',
            data:{ labels: <?php echo json_encode(array_keys($termAverages), JSON_UNESCAPED_UNICODE); ?>,
                datasets:[{ label:'Average %', data: <?php echo json_encode(array_values($termAverages)); ?>, backgroundColor:'#2563eb', borderRadius:8 }] },
            options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,max:100}},plugins:{legend:{display:false}}}
        });
    }

    if (document.getElementById('radarChart')) {
        new Chart(document.getElementById('radarChart'), {
            type:'radar',
            data:{ labels: <?php echo json_encode($radarLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets:[{ label:'Average %', data: <?php echo json_encode($radarData); ?>, backgroundColor:'rgba(37,99,235,.15)', borderColor:'#2563eb', pointBackgroundColor:'#2563eb' }] },
            options:{responsive:true,maintainAspectRatio:false,scales:{r:{min:0,max:100,ticks:{stepSize:20}}},plugins:{legend:{display:false}}}
        });
    }

    $('#subjectSearch').on('input', function(){
        const query = $(this).val().toLowerCase().trim();
        const box = $('#subjectSuggestions');
        box.empty().hide();

        if (!query) {
            $('#subjectStats').text('Select a subject to view its performance trend and statistics.');
            $('#subjectTrendContainer').hide();
            return;
        }

        const matches = subjectList.filter(s => s.toLowerCase().includes(query));
        matches.forEach(subject => {
            $('<button type="button" class="list-group-item list-group-item-action"></button>')
                .text(subject)
                .attr('data-subject', subject)
                .appendTo(box);
        });

        if (matches.length) box.show();
    });

    $(document).on('click', '#subjectSuggestions .list-group-item', function(){
        const subject = $(this).data('subject');
        $('#subjectSearch').val(subject);
        $('#subjectSuggestions').hide();
        showSubjectAnalytics(subject);
    });

    function showSubjectAnalytics(subject) {
        const scores = subjectData[subject] || {};
        let total = 0, count = 0, max = -1, min = 101;
        let first = null, last = null;
        const labels = [], values = [];

        termOrder.forEach(term => {
            const score = scores[term];
            labels.push(term);
            values.push(score !== undefined && score !== null && score !== '' ? Number(score) : null);

            if (score !== undefined && score !== null && score !== '' && Number(score) > 0) {
                const s = Number(score);
                total += s;
                count++;
                max = Math.max(max,s);
                min = Math.min(min,s);
                if (first === null) first = s;
                last = s;
            }
        });

        const avg = count ? Math.round(total/count) : 0;
        let stats = '<div class="row g-2">';
        stats += '<div class="col-6"><strong>Average</strong><div>' + avg + '%</div></div>';
        stats += '<div class="col-6"><strong>Exams</strong><div>' + count + '</div></div>';
        stats += '<div class="col-6"><strong>Highest</strong><div>' + (count ? max + '%' : 'N/A') + '</div></div>';
        stats += '<div class="col-6"><strong>Lowest</strong><div>' + (count ? min + '%' : 'N/A') + '</div></div>';
        stats += '</div>';
        $('#subjectStats').html(stats);
        $('#subjectTrendContainer').show();

        if (subjectTrendChart) subjectTrendChart.destroy();
        subjectTrendChart = new Chart(document.getElementById('subjectTrendChart'), {
            type:'line',
            data:{labels:labels,datasets:[{label:subject+' %',data:values,borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.12)',fill:true,tension:.35,spanGaps:true}]},
            options:{responsive:true,maintainAspectRatio:false,scales:{y:{min:0,max:100}},plugins:{legend:{display:false}}}
        });

        let advice = 'Keep monitoring this subject consistently.';
        if (avg >= 80) advice = 'Excellent performance. Maintain the current study discipline.';
        else if (avg >= 70) advice = 'Very good performance. Focus on small gaps to move toward an A.';
        else if (avg >= 60) advice = 'Good performance. More targeted revision can raise this further.';
        else if (avg >= 50) advice = 'This subject is around the pass-to-good range. Increase practice and revision.';
        else advice = 'Prioritize this subject with structured revision, practice and teacher support.';

        if (first !== null && last !== null) {
            if (last > first) advice += ' The latest recorded score shows improvement.';
            if (last < first) advice += ' The latest recorded score has declined, so review the recent topic areas.';
        }
        $('#adviceText').text(advice);
    }
});
</script>