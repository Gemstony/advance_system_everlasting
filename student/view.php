<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

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

// Fetch all data for the profile
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $grading = $pdo->query("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();
    $divisionSystem = $pdo->query("SELECT * FROM tbl_division_system")->fetchAll();
    $GLOBALS['gradeSystem'] = $grading;
    $GLOBALS['divisionSystem'] = $divisionSystem;

    $current_term = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $current_term_id = $current_term ? $current_term['id'] : 0;
    $current_term_name = $current_term ? $current_term['name'] : 'None';

    $stmt = $pdo->prepare("
        SELECT sc.id, sc.class, sc.teacher, s.name AS subject_name, st.fname, st.lname
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        LEFT JOIN tbl_staff st ON sc.teacher = st.id
    ");
    $stmt->execute();
    $all_combs = $stmt->fetchAll();

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
            $subjects[] = [
                'subject' => $comb['subject_name'] ?? 'Unknown',
                'teacher' => $teacher_name ?: 'Not assigned',
                'class'   => $act_class
            ];
        }
    }
    $teachers_list = !empty($teachers) ? implode(', ', array_unique($teachers)) : 'Not assigned';

    $stmt = $pdo->prepare("
        SELECT t.id, t.name, AVG(r.score) as avg_score, SUM(r.score) as total, COUNT(r.score) as subj_count
        FROM tbl_exam_results r
        LEFT JOIN tbl_terms t ON r.term = t.id
        WHERE r.student = ?
        GROUP BY t.id
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$account_id]);
    $latest = $stmt->fetch();

    $latest_term_avg = 0;
    $latest_term_total = 0;
    $latest_term_subj = 0;
    $latest_term_name = 'No results';
    $latest_term_grade = '';
    $latest_term_remark = '';
    $latest_term_division = '';
    $latest_term_points = 0;

    if ($latest) {
        $latest_term_avg = round($latest['avg_score']);
        $latest_term_total = round($latest['total']);
        $latest_term_subj = $latest['subj_count'];
        $latest_term_name = $latest['name'];

        foreach ($grading as $g) {
            if ($latest_term_avg >= $g['min'] && $latest_term_avg <= $g['max']) {
                $latest_term_grade = $g['name'];
                $latest_term_remark = $g['remark'];
                break;
            }
        }

        $stmt2 = $pdo->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt2->execute([$account_id, $latest['id']]);
        $scores = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        $latest_term_division = get_division($scores);
        $latest_term_points = get_points($scores);
    }

    $prev_avg = null;
    if ($latest) {
        $stmt = $pdo->prepare("
            SELECT AVG(score) as avg_score
            FROM tbl_exam_results
            WHERE student = ? AND term < ?
            ORDER BY term DESC
            LIMIT 1
        ");
        $stmt->execute([$account_id, $latest['id']]);
        $prev_avg = $stmt->fetchColumn();
        if ($prev_avg) $prev_avg = round($prev_avg);
    }

    $stmt = $pdo->prepare("SELECT MAX(score) FROM tbl_exam_results WHERE student = ?");
    $stmt->execute([$account_id]);
    $best_score = $stmt->fetchColumn();
    $best_grade = 'N/A';
    if ($best_score) {
        foreach ($grading as $g) {
            if ($best_score >= $g['min'] && $best_score <= $g['max']) {
                $best_grade = $g['name'];
                break;
            }
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE student = ? AND score >= 50");
    $stmt->execute([$account_id]);
    $passed = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE student = ?");
    $stmt->execute([$account_id]);
    $total_exams = $stmt->fetchColumn();
    $pass_rate = ($total_exams > 0) ? round(($passed / $total_exams) * 100) . '%' : 'N/A';

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT term) FROM tbl_exam_results WHERE student = ?");
    $stmt->execute([$account_id]);
    $terms_with_results = $stmt->fetchColumn() ?: 0;

    $subjects_with_scores = [];
    $stmt = $pdo->prepare("
        SELECT s.name AS subject_name,
               r.score AS latest_score,
               g.name AS grade,
               g.remark AS remark,
               r.term
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        LEFT JOIN (
            SELECT subject_combination, score, term
            FROM tbl_exam_results
            WHERE student = ?
            ORDER BY term DESC
        ) r ON r.subject_combination = sc.id
        LEFT JOIN tbl_grade_system g ON r.score BETWEEN g.min AND g.max
        WHERE FIND_IN_SET(?, sc.class) > 0
        GROUP BY sc.id
        ORDER BY s.name
    ");
    $stmt->execute([$account_id, $class]);
    $subjects_with_scores = $stmt->fetchAll();

} catch (PDOException $e) {
    $subject_count = 0;
    $teachers_list = 'Not assigned';
    $subjects = [];
    $latest_term_avg = 0;
    $latest_term_name = 'No results';
    $latest_term_grade = '';
    $latest_term_remark = '';
    $latest_term_division = '';
    $latest_term_points = 0;
    $best_score = null;
    $best_grade = 'N/A';
    $pass_rate = 'N/A';
    $terms_with_results = 0;
    $prev_avg = null;
    $subjects_with_scores = [];
    $grading = [];
}

// Profile image - use person icon if no image
$profile_image = null;
if ($img != "DEFAULT" && !empty($img) && file_exists('../images/students/' . $img)) {
    $profile_image = 'images/students/' . $img;
} else {
    $profile_image = null;
}

// Build $student array for header compatibility
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender,
    'email' => $email
];
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="dash-shell">

        <!-- ============================================================
             PAGE HEADING (black text as requested)
             ============================================================ -->
        <div class="page-heading reveal" data-delay="50">
            <div class="page-heading-text">
                <span class="eyebrow">Student Profile</span>
                <h1>My Profile</h1>
                <p>Your personal and academic information at a glance.</p>
            </div>
        </div>

        <!-- ============================================================
             QUICK ACTIONS
             ============================================================ -->
        <nav class="quick-actions reveal" data-delay="100" aria-label="Quick actions">
            <a href="student" class="qa-btn qa-profile">
                <span class="qa-icon"><i class="bi bi-house-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">Dashboard</span>
                    <span class="qa-sub">Back to home</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
            <a href="student/results" class="qa-btn qa-results">
                <span class="qa-icon"><i class="bi bi-clipboard-data-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">My Results</span>
                    <span class="qa-sub">Term by term</span>
                </span>
                <i class="bi bi-chevron-right qa-arrow"></i>
            </a>
            <a href="student/settings" class="qa-btn qa-subjects">
                <span class="qa-icon"><i class="bi bi-key-fill"></i></span>
                <span class="qa-body">
                    <span class="qa-label">Change Password</span>
                    <span class="qa-sub">Account security</span>
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
                    <?php if ($profile_image): ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="info-avatar-img">
                    <?php else: ?>
                        <div class="info-avatar-placeholder"><i class="bi bi-person-circle"></i></div>
                    <?php endif; ?>
                    <div class="info-name">
                        <strong><?php echo htmlspecialchars($fname.' '.$mname.' '.$lname); ?></strong>
                        <small><?php echo htmlspecialchars($act_class); ?></small>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-hash"></i></span>
                        <div>
                            <small>Reg No</small>
                            <strong><?php echo htmlspecialchars($account_id); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-building"></i></span>
                        <div>
                            <small>Class</small>
                            <strong><?php echo htmlspecialchars($act_class); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-gender-<?php echo strtolower($gender); ?>"></i></span>
                        <div>
                            <small>Gender</small>
                            <strong><?php echo htmlspecialchars($gender); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-envelope"></i></span>
                        <div>
                            <small>Email</small>
                            <strong class="wrap"><?php echo htmlspecialchars($email); ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-bookmarks"></i></span>
                        <div>
                            <small>Subjects</small>
                            <strong><?php echo $subject_count; ?></strong>
                        </div>
                    </div>
                    <div class="info-tile">
                        <span class="info-tile-icon"><i class="bi bi-calendar-check"></i></span>
                        <div>
                            <small>Latest Term</small>
                            <strong><?php echo htmlspecialchars($latest_term_name); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             PERSONAL & ACADEMIC INFORMATION
             ============================================================ -->
        <section class="section-heading-card reveal" data-delay="200" data-accent="violet">
            <div class="shc-icon"><i class="bi bi-person-lines-fill"></i></div>
            <div class="shc-text">
                <h2>Personal & Academic Information</h2>
                <p>Your registered details and academic enrollment.</p>
            </div>
            <div class="shc-tag">Profile</div>
        </section>

        <div class="row g-3 mb-2">
            <div class="col-md-6 reveal" data-delay="250">
                <div class="panel-card accent-blue">
                    <div class="panel-head">
                        <h4><i class="bi bi-person-vcard me-2"></i> Personal Information</h4>
                    </div>
                    <div class="panel-body">
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">First Name</span><span class="detail-value"><?php echo htmlspecialchars($fname); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Middle Name</span><span class="detail-value"><?php echo htmlspecialchars($mname); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Last Name</span><span class="detail-value"><?php echo htmlspecialchars($lname); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?php echo htmlspecialchars($gender); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value wrap"><?php echo htmlspecialchars($email); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 reveal" data-delay="300">
                <div class="panel-card accent-violet">
                    <div class="panel-head">
                        <h4><i class="bi bi-mortarboard me-2"></i> Academic Information</h4>
                    </div>
                    <div class="panel-body">
                        <div class="detail-list">
                            <div class="detail-row"><span class="detail-label">Registration No.</span><span class="detail-value"><?php echo htmlspecialchars($account_id); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Class</span><span class="detail-value"><?php echo htmlspecialchars($act_class); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Teachers</span><span class="detail-value wrap"><?php echo htmlspecialchars($teachers_list); ?></span></div>
                            <div class="detail-row"><span class="detail-label">Subjects Enrolled</span><span class="detail-value"><?php echo $subject_count; ?></span></div>
                            <div class="detail-row"><span class="detail-label">Latest Term</span><span class="detail-value"><?php echo htmlspecialchars($latest_term_name); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             LATEST PERFORMANCE SUMMARY
             ============================================================ -->
        <?php if ($latest): ?>
            <section class="section-heading-card reveal" data-delay="350" data-accent="green">
                <div class="shc-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="shc-text">
                    <h2>Latest Term Performance</h2>
                    <p>Your performance summary for <?php echo htmlspecialchars($latest_term_name); ?>.</p>
                </div>
                <div class="shc-tag">Results</div>
            </section>

            <div class="row g-3 mb-2">
                <div class="col-6 col-lg-3 reveal" data-delay="400">
                    <div class="stat-tile accent-blue">
                        <span class="stat-tile-icon"><i class="bi bi-bar-chart"></i></span>
                        <div class="stat-tile-value"><?php echo $latest_term_avg; ?>%</div>
                        <div class="stat-tile-label">Average</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="420">
                    <div class="stat-tile accent-green">
                        <span class="stat-tile-icon"><i class="bi bi-trophy"></i></span>
                        <div class="stat-tile-value"><?php echo $latest_term_grade; ?></div>
                        <div class="stat-tile-label">Grade</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="440">
                    <div class="stat-tile accent-amber">
                        <span class="stat-tile-icon"><i class="bi bi-layers"></i></span>
                        <div class="stat-tile-value"><?php echo $latest_term_division; ?></div>
                        <div class="stat-tile-label">Division</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 reveal" data-delay="460">
                    <div class="stat-tile accent-violet">
                        <span class="stat-tile-icon"><i class="bi bi-star"></i></span>
                        <div class="stat-tile-value"><?php echo $latest_term_points; ?></div>
                        <div class="stat-tile-label">Points</div>
                    </div>
                </div>
            </div>

            <div class="advice-card reveal" data-delay="480" style="--accent: #16a34a;">
                <div class="advice-icon"><i class="bi bi-chat"></i></div>
                <div class="advice-body">
                    <h5>Remark</h5>
                    <p><?php echo htmlspecialchars($latest_term_remark); ?></p>
                    <div class="advice-chips">
                        <span class="chip"><i class="bi bi-bar-chart"></i> Total <strong><?php echo $latest_term_total; ?></strong></span>
                        <span class="chip"><i class="bi bi-collection"></i> Subjects <strong><?php echo $latest_term_subj; ?></strong></span>
                        <?php if ($prev_avg !== null): ?>
                            <?php $diff = $latest_term_avg - $prev_avg; ?>
                            <span class="chip <?php echo $diff >= 0 ? 'chip-pass' : 'chip-fail'; ?>">
                                <i class="bi bi-arrow-<?php echo $diff >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo $diff >= 0 ? '+' : ''; ?><?php echo $diff; ?>% vs previous
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             QUICK STATS
             ============================================================ -->
        <section class="section-heading-card reveal" data-delay="500" data-accent="blue">
            <div class="shc-icon"><i class="bi bi-info-circle"></i></div>
            <div class="shc-text">
                <h2>Quick Stats</h2>
                <p>Key academic statistics at a glance.</p>
            </div>
            <div class="shc-tag">Summary</div>
        </section>

        <div class="row g-3 mb-2">
            <div class="col-6 col-lg-3 reveal" data-delay="520">
                <div class="stat-tile accent-blue">
                    <span class="stat-tile-icon"><i class="bi bi-book"></i></span>
                    <div class="stat-tile-value"><?php echo $subject_count ?: 'N/A'; ?></div>
                    <div class="stat-tile-label">Subjects</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 reveal" data-delay="540">
                <div class="stat-tile accent-green">
                    <span class="stat-tile-icon"><i class="bi bi-calendar-check"></i></span>
                    <div class="stat-tile-value"><?php echo $terms_with_results; ?></div>
                    <div class="stat-tile-label">Terms with Results</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 reveal" data-delay="560">
                <div class="stat-tile accent-amber">
                    <span class="stat-tile-icon"><i class="bi bi-trophy"></i></span>
                    <div class="stat-tile-value"><?php echo $best_score ? $best_score.'%' : 'N/A'; ?></div>
                    <div class="stat-tile-label">Best Score (<?php echo $best_grade; ?>)</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 reveal" data-delay="580">
                <div class="stat-tile accent-violet">
                    <span class="stat-tile-icon"><i class="bi bi-arrow-up"></i></span>
                    <div class="stat-tile-value"><?php echo $pass_rate; ?></div>
                    <div class="stat-tile-label">Pass Rate</div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             SUBJECTS WITH LATEST SCORES TABLE
             ============================================================ -->
        <?php if (!empty($subjects_with_scores)): ?>
            <section class="section-heading-card reveal" data-delay="600" data-accent="violet">
                <div class="shc-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
                <div class="shc-text">
                    <h2>Subjects & Latest Results</h2>
                    <p>Your enrolled subjects with the most recent scores.</p>
                </div>
                <div class="shc-tag">Results</div>
            </section>

            <div class="reveal" data-delay="620">
                <div class="panel-card accent-blue">
                    <div class="panel-head">
                        <h4>Detailed Subject Results</h4>
                        <span class="panel-tag">All Subjects</span>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table dash-table" id="subjectsTable">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th class="text-center">Latest Score (%)</th>
                                        <th class="text-center">Grade</th>
                                        <th class="text-center">Remark</th>
                                        <th class="text-center">Term</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects_with_scores as $sub): ?>
                                        <?php
                                            $grade_class = 'pill-muted';
                                            if ($sub['grade'] == 'A') $grade_class = 'pill-pass';
                                            elseif ($sub['grade'] == 'B') $grade_class = 'pill-pass';
                                            elseif ($sub['grade'] == 'C') $grade_class = 'pill-warn';
                                            elseif ($sub['grade'] == 'D') $grade_class = 'pill-warn';
                                            elseif ($sub['grade'] == 'F') $grade_class = 'pill-fail';
                                        ?>
                                        <tr>
                                            <td><span class="td-subject"><?php echo htmlspecialchars($sub['subject_name']); ?></span></td>
                                            <td class="text-center"><strong><?php echo $sub['latest_score'] !== null ? $sub['latest_score'].'%' : '—'; ?></strong></td>
                                            <td class="text-center">
                                                <?php if ($sub['grade']): ?>
                                                    <span class="pill <?php echo $grade_class; ?>"><?php echo $sub['grade']; ?></span>
                                                <?php else: ?>
                                                    <span class="pill pill-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center td-remark"><?php echo $sub['remark'] ?? '—'; ?></td>
                                            <td class="text-center"><?php echo $sub['term'] ? htmlspecialchars($sub['term']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Floating Scroll to Top Button -->
<div id="scrollTopBtn" class="scroll-top-btn" title="Scroll to top">
    <i class="bi bi-arrow-up"></i>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>
<script>
$(document).ready(function(){
    if ($.fn.DataTable) {
        $('#subjectsTable').DataTable({
            "pageLength": 25,
            "ordering": true,
            "responsive": true,
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ subjects"
            }
        });
    }

    // Scroll to Top Button
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollBtn.classList.add('show');
            } else {
                scrollBtn.classList.remove('show');
            }
        });
        scrollBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

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
   PROFESSIONAL DASHBOARD STYLES — SAME AS INDEX PAGE
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
    background: var(--surface);
    border: 1px solid var(--line);
    box-shadow: 0 8px 26px -18px rgba(15,23,42,.18);
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
    background: radial-gradient(circle, rgba(37,99,235,.08), rgba(37,99,235,0) 70%);
    pointer-events: none;
}
.page-heading-text { min-width: 0; flex: 1 1 220px; }
.page-heading-text .eyebrow {
    display: inline-block;
    font-size: .72rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--accent-blue);
    font-weight: 700;
    margin-bottom: 6px;
}
.page-heading-text h1 {
    font-size: clamp(1.1rem, 2.4vw, 1.7rem);
    font-weight: 800;
    margin: 0 0 4px;
    font-family: 'Poppins', sans-serif;
    letter-spacing: -0.01em;
    color: #000000 !important;
}
.page-heading-text p {
    margin: 0;
    font-size: clamp(.8rem, 1.6vw, .94rem);
    color: var(--ink-500);
}

/* ---------- Quick actions ---------- */
.quick-actions {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
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
.qa-btn:hover .qa-arrow { transform: translateX(3px); }

.qa-profile::before  { background: var(--accent-blue); }
.qa-profile  .qa-icon{ background: linear-gradient(135deg, #3b82f6, #2563eb); }
.qa-profile:hover .qa-arrow { color: var(--accent-blue); }

.qa-subjects::before { background: var(--accent-violet); }
.qa-subjects .qa-icon{ background: linear-gradient(135deg, #a78bfa, #7c3aed); }
.qa-subjects:hover .qa-arrow { color: var(--accent-violet); }

.qa-results::before  { background: var(--accent-amber); }
.qa-results  .qa-icon{ background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.qa-results:hover .qa-arrow { color: var(--accent-amber); }

/* =========================================================
   SECTION HEADING CARD
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

/* ---------- Detail list (replaces tables in panels) ---------- */
.detail-list { display: flex; flex-direction: column; gap: .35rem; }
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    padding: .6rem .8rem;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    min-width: 0;
}
.detail-label {
    font-size: .72rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--ink-500);
    flex: 0 0 auto;
    padding-top: 2px;
}
.detail-value {
    font-size: clamp(.82rem, 1.4vw, .92rem);
    color: var(--ink-900);
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
    word-break: break-word;
    min-width: 0;
    flex: 1 1 auto;
}
.detail-value.wrap { word-break: break-all; }

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
.stat-tile-value { font-size: clamp(1.25rem, 2.6vw, 1.75rem); font-weight: 800; color: var(--ink-900); line-height: 1; overflow-wrap: anywhere; }
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
.advice-body p { font-size: clamp(.8rem, 1.45vw, .9rem); color: var(--ink-700); margin: 0 0 .7rem; line-height: 1.5; overflow-wrap: anywhere; }
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

.pill { padding:.2rem .55rem; border-radius:999px; font-size:.64rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; display:inline-block; }
.pill-pass  { background:#dcfce7; color:#166534; }
.pill-warn  { background:#fef3c7; color:#b45309; }
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
.table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ---------- Scroll to top button ---------- */
.scroll-top-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 52px;
    height: 52px;
    background: linear-gradient(145deg, #00c6ff, #0072ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    cursor: pointer;
    box-shadow: 0 6px 0 #003f7f, 0 8px 18px rgba(0,0,0,0.25);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s, transform 0.2s;
}
.scroll-top-btn.show { opacity: 1; visibility: visible; }
.scroll-top-btn:hover { transform: translateY(-3px); box-shadow: 0 9px 0 #003f7f, 0 12px 22px rgba(0,0,0,0.3); }

/* ---------- Reveal animation ---------- */
.reveal { opacity: 0; transform: translateY(22px); transition: opacity .7s ease, transform .7s cubic-bezier(.22,1,.36,1); }
.reveal.revealed { opacity: 1; transform: translateY(0); }
.reveal[data-delay="50"]  { transition-delay: .05s; }
.reveal[data-delay="100"] { transition-delay: .1s; }
.reveal[data-delay="150"] { transition-delay: .15s; }
.reveal[data-delay="200"] { transition-delay: .2s; }
.reveal[data-delay="250"] { transition-delay: .25s; }
.reveal[data-delay="300"] { transition-delay: .3s; }
.reveal[data-delay="350"] { transition-delay: .35s; }
.reveal[data-delay="400"] { transition-delay: .4s; }
.reveal[data-delay="420"] { transition-delay: .42s; }
.reveal[data-delay="440"] { transition-delay: .44s; }
.reveal[data-delay="460"] { transition-delay: .46s; }
.reveal[data-delay="480"] { transition-delay: .48s; }
.reveal[data-delay="500"] { transition-delay: .5s; }
.reveal[data-delay="520"] { transition-delay: .52s; }
.reveal[data-delay="540"] { transition-delay: .54s; }
.reveal[data-delay="560"] { transition-delay: .56s; }
.reveal[data-delay="580"] { transition-delay: .58s; }
.reveal[data-delay="600"] { transition-delay: .6s; }
.reveal[data-delay="620"] { transition-delay: .62s; }

/* ---------- Dark mode ---------- */
body.dark .page-heading { background: #0f172a; border-color: #1e293b; }
body.dark .page-heading-text h1 { color: #ffffff !important; }
body.dark .info-card,
body.dark .stat-tile,
body.dark .panel-card,
body.dark .advice-card,
body.dark .qa-btn,
body.dark .section-heading-card { background: #0f172a; border-color: #1e293b; color: #e2e8f0; }
body.dark .info-card-header,
body.dark .panel-head { background: #111827; border-color: #1e293b; }
body.dark .info-tile,
body.dark .detail-row,
body.dark .chip { background: #111827; border-color: #1e293b; }
body.dark .qa-btn,
body.dark .qa-label,
body.dark .info-tile strong,
body.dark .info-name strong,
body.dark .detail-value,
body.dark .panel-head h4,
body.dark .advice-body h5,
body.dark .stat-tile-value,
body.dark .shc-text h2 { color: #f1f5f9; }
body.dark .qa-sub,
body.dark .info-tile small,
body.dark .info-name small,
body.dark .detail-label,
body.dark .stat-tile-label,
body.dark .panel-tag,
body.dark .advice-body p,
body.dark .chip,
body.dark .td-remark,
body.dark .shc-text p { color: #94a3b8; }
body.dark .dash-table thead th { background: #111827; color: #cbd5e1; border-color: #1e293b; }
body.dark .dash-table tbody td { color: #e2e8f0; border-color: #1e293b; }
body.dark .panel-tag { background:#0b1220; border-color:#1e293b; }

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width: 1199.98px) {
    .quick-actions { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
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
    .advice-card { flex-direction: column; }
    .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .section-heading-card { flex-wrap: wrap; }
    .shc-tag { margin-left: auto; }
    .detail-row { flex-direction: column; gap: .25rem; }
    .detail-value { text-align: left; }
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
    .scroll-top-btn { width: 46px; height: 46px; font-size: 1.3rem; bottom: 20px; right: 20px; }
}

/* ---------- Print ---------- */
@media print {
    .quick-actions, .app-sidebar, .scroll-top-btn { display: none !important; }
    .info-card, .panel-card, .advice-card, .stat-tile, .section-heading-card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
}

/* ===== SIDEBAR OVERRIDE: BOLD BLACK MENU ITEMS ===== */
.app-sidebar .menu-item a,
.app-sidebar .menu-item a:link,
.app-sidebar .menu-item a:visited,
.app-sidebar .menu-item a:hover,
.app-sidebar .menu-item a:active,
.app-sidebar .nav-link,
.app-sidebar .nav-link:link,
.app-sidebar .nav-link:visited,
.app-sidebar .nav-link:hover,
.app-sidebar .nav-link:active,
.app-sidebar .sidebar-menu li a,
.app-sidebar .sidebar-menu li a:link,
.app-sidebar .sidebar-menu li a:visited,
.app-sidebar .sidebar-menu li a:hover,
.app-sidebar .sidebar-menu li a:active,
.sidebar-nav .nav-item .nav-link,
.sidebar-nav .nav-item .nav-link:link,
.sidebar-nav .nav-item .nav-link:visited,
.sidebar-nav .nav-item .nav-link:hover,
.sidebar-nav .nav-item .nav-link:active {
    color: #000 !important;
    font-weight: 700 !important;
}
</style>
</body>
</html>