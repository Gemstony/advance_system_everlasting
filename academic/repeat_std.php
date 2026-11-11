<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// Prepare staff array for header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

// Get search parameters
$search_classes = isset($_GET['search_classes']) ? $_GET['search_classes'] : [];
if (!is_array($search_classes)) $search_classes = [];
$search_classes = array_map('intval', $search_classes);
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Validate: at least one class must be selected
if (empty($search_classes)) {
    header("location: individual_results");
    exit;
}

$students = [];
$class_names = [];
$total_students = 0;

// Handle flash messages
$success_msg = $_SESSION['success'] ?? '';
$error_msg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch class names for display
    $placeholders = implode(',', array_fill(0, count($search_classes), '?'));
    $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders)");
    $stmt->execute($search_classes);
    $class_names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Build query
    $sql = "SELECT s.id, s.fname, s.mname, s.lname, s.gender, c.name AS class_name
            FROM tbl_students s
            LEFT JOIN tbl_classes c ON s.class = c.id
            WHERE s.status = 1
              AND s.class IN ($placeholders)";

    $params = $search_classes;

    if (!empty($search_name)) {
        $sql .= " AND CONCAT(s.fname, ' ', s.mname, ' ', s.lname) LIKE ?";
        $params[] = '%' . $search_name . '%';
    }

    $sql .= " ORDER BY s.fname, s.lname, s.class";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_students = count($students);

    // -------------------------------------------------------------
    // Fetch term result data for each student (including all terms)
    // -------------------------------------------------------------
    $student_ids = array_column($students, 'id');
    $term_data = [];
    if (!empty($student_ids)) {
        $id_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        // Get all term IDs for each student and count
        $sql = "SELECT r.student, 
                       GROUP_CONCAT(DISTINCT r.term ORDER BY r.term ASC) as term_ids,
                       COUNT(DISTINCT r.term) as term_count,
                       MAX(r.term) as latest_term_id,
                       (SELECT score FROM tbl_exam_results 
                        WHERE student = r.student AND term = MAX(r.term) 
                        ORDER BY id DESC LIMIT 1) as latest_score
                FROM tbl_exam_results r
                WHERE r.student IN ($id_placeholders)
                GROUP BY r.student";
        $stmt = $conn->prepare($sql);
        $stmt->execute($student_ids);
        $term_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Collect all term IDs to fetch their names
        $all_term_ids = [];
        foreach ($term_data_raw as $td) {
            if (!empty($td['term_ids'])) {
                $ids = explode(',', $td['term_ids']);
                $all_term_ids = array_merge($all_term_ids, $ids);
            }
        }
        $all_term_ids = array_unique($all_term_ids);

        // Fetch term names
        $term_names_map = [];
        if (!empty($all_term_ids)) {
            $term_placeholders = implode(',', array_fill(0, count($all_term_ids), '?'));
            $stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id IN ($term_placeholders)");
            $stmt->execute($all_term_ids);
            $term_names_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        // Build term_data array indexed by student id
        foreach ($term_data_raw as $td) {
            $sid = $td['student'];
            $term_ids = !empty($td['term_ids']) ? explode(',', $td['term_ids']) : [];
            $term_names = [];
            foreach ($term_ids as $tid) {
                if (isset($term_names_map[$tid])) {
                    $term_names[] = $term_names_map[$tid];
                }
            }
            $term_data[$sid] = [
                'term_count' => $td['term_count'],
                'term_names' => implode(', ', $term_names),
                'latest_term_id' => $td['latest_term_id'],
                'latest_term_name' => $term_names_map[$td['latest_term_id']] ?? 'N/A',
                'latest_score' => $td['latest_score'] ?? 'N/A',
            ];
        }
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// -------------------------------------------------------------
// ADVANCED DUPLICATE DETECTION (same as before)
// -------------------------------------------------------------

function normalize($str) {
    return preg_replace('/\s+/', ' ', trim(strtolower($str)));
}

function safe_soundex($str) {
    $s = soundex($str);
    return $s ?: '';
}

function similarity_ratio($str1, $str2) {
    if ($str1 === $str2) return 1.0;
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    if ($len1 == 0 || $len2 == 0) return 0;
    $distance = levenshtein($str1, $str2);
    return 1 - ($distance / max($len1, $len2));
}

// Build student data with normalized fields
$student_data = [];
foreach ($students as $s) {
    $first = normalize($s['fname']);
    $middle = normalize($s['mname']);
    $last = normalize($s['lname']);
    $full = trim($first . ' ' . $middle . ' ' . $last);
    $full = preg_replace('/\s+/', ' ', $full);
    $student_data[] = [
        'id'        => $s['id'],
        'full'      => $full,
        'first'     => $first,
        'middle'    => $middle,
        'last'      => $last,
        'gender'    => $s['gender'],
        'class_name'=> $s['class_name'] ?? 'N/A',
        'soundex_first' => safe_soundex($first),
        'soundex_last'  => safe_soundex($last),
        'term_data' => $term_data[$s['id']] ?? [
            'term_count' => 0,
            'term_names' => 'None',
            'latest_term_name' => 'None',
            'latest_score' => 'N/A'
        ]
    ];
}

// Exact groups
$exact_groups = [];
foreach ($student_data as $sd) {
    $key = $sd['first'] . '|' . $sd['last'];
    $exact_groups[$key][] = $sd;
}

// Phonetic groups
$phonetic_groups = [];
foreach ($student_data as $sd) {
    $key = $sd['soundex_first'] . '|' . $sd['soundex_last'];
    if (empty($sd['soundex_first']) || empty($sd['soundex_last'])) {
        $key = $sd['first'] . '|' . $sd['last'];
    }
    $phonetic_groups[$key][] = $sd;
}

// Build duplicate groups
$duplicate_ids = [];
$duplicate_groups = [];

// Process exact groups
foreach ($exact_groups as $key => $entries) {
    if (count($entries) > 1) {
        $first = $entries[0]['first'];
        $last = $entries[0]['last'];
        $display = ucfirst($first) . ' ' . ucfirst($last);
        $duplicate_groups[] = [
            'display_name' => $display,
            'students' => $entries,
            'type' => 'exact',
        ];
        foreach ($entries as $e) {
            $duplicate_ids[$e['id']] = true;
        }
    }
}

// Process phonetic groups not already covered
foreach ($phonetic_groups as $key => $entries) {
    if (count($entries) <= 1) continue;
    $all_covered = true;
    foreach ($entries as $e) {
        if (!isset($duplicate_ids[$e['id']])) {
            $all_covered = false;
            break;
        }
    }
    if ($all_covered) continue;

    $first = $entries[0]['first'];
    $last = $entries[0]['last'];
    $display = ucfirst($first) . ' ' . ucfirst($last) . ' (phonetic)';
    $duplicate_groups[] = [
        'display_name' => $display,
        'students' => $entries,
        'type' => 'phonetic',
    ];
    foreach ($entries as $e) {
        $duplicate_ids[$e['id']] = true;
    }
}

// Compute similarity scores
foreach ($duplicate_groups as &$group) {
    $base_full = $group['students'][0]['full'];
    foreach ($group['students'] as &$student) {
        $student['similarity'] = similarity_ratio($base_full, $student['full']);
        $student['similarity_pct'] = round($student['similarity'] * 100) . '%';
    }
}
unset($group);

// Sort groups
usort($duplicate_groups, function($a, $b) {
    if ($a['type'] !== $b['type']) {
        return ($a['type'] === 'exact') ? -1 : 1;
    }
    return strcmp($a['display_name'], $b['display_name']);
});

$unique_names_count = count($student_data) - count($duplicate_ids);
$duplicate_groups_count = count($duplicate_groups);

$page_title = 'Duplicate Names';
if (!empty($search_name)) {
    $page_title .= " (filter: \"" . htmlspecialchars($search_name) . "\")";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - <?php echo htmlspecialchars($page_title); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: transparent;
            font-family: 'Inter', sans-serif;
        }
        .app-content {
            background: transparent !important;
        }
        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;
            text-shadow: none !important;
        }

        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(67,97,238,0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            padding: 1.2rem 1rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            background: rgba(255,255,255,0.9);
            transform: translateY(-3px);
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #475569;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        .stat-card.duplicate .stat-value { color: #ef476f; }
        .stat-card.unique .stat-value { color: #06d6a0; }

        .duplicate-item {
            background: rgba(255,255,255,0.5);
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            border-left: 6px solid #ffb703;
            transition: all 0.2s ease;
        }
        .duplicate-item.exact {
            border-left-color: #ef476f;
        }
        .duplicate-item.phonetic {
            border-left-color: #4cc9f0;
        }
        .duplicate-item:hover {
            background: rgba(255,255,255,0.8);
            transform: translateX(4px);
        }
        .dup-number {
            background: #1e293b;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            margin-right: 0.6rem;
        }
        .dup-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }
        .dup-count {
            background: #ef476f;
            color: white;
            border-radius: 30px;
            padding: 0.1rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }
        .dup-type {
            background: #4361ee;
            color: white;
            border-radius: 20px;
            padding: 0.1rem 0.7rem;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .student-entry {
            display: inline-block;
            background: rgba(255,255,255,0.4);
            border-radius: 12px;
            padding: 0.3rem 0.8rem;
            margin: 0.2rem 0.3rem 0.2rem 0;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.2s;
        }
        .student-entry .term-info {
            font-size: 0.7rem;
            color: #475569;
            margin-left: 0.3rem;
        }

        #allStudentsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        #allStudentsTable thead th {
            background: rgba(241,245,249,0.7);
            backdrop-filter: blur(4px);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            padding: 0.8rem 0.5rem;
        }
        #allStudentsTable tbody tr {
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            transition: all 0.2s;
        }
        #allStudentsTable tbody tr:hover {
            background: rgba(255,255,255,0.8);
        }
        #allStudentsTable tbody td {
            padding: 0.7rem 0.5rem;
            color: #1e293b;
            border: none;
            vertical-align: middle;
        }
        .duplicate-row {
            background: rgba(239,71,111,0.06) !important;
        }
        .duplicate-row:hover {
            background: rgba(239,71,111,0.12) !important;
        }
        .phonetic-row {
            background: rgba(76,201,240,0.06) !important;
        }
        .phonetic-row:hover {
            background: rgba(76,201,240,0.12) !important;
        }
        .duplicate-badge {
            background: #ffb703;
            color: #1e293b;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .phonetic-badge {
            background: #4cc9f0;
            color: #1e293b;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-back {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 0.45rem 1.3rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #4361ee;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .btn-back:hover {
            background: white;
            transform: translateY(-2px);
            color: #1e40af;
        }

        .flash-success {
            background: #d4edda;
            color: #155724;
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #28a745;
        }
        .flash-error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #dc3545;
        }

        @media (max-width: 768px) {
            .tile { padding: 1.5rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .student-entry { display: block; margin: 0.3rem 0; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h1><i class="bi bi-people-fill me-2"></i> <?php echo htmlspecialchars($page_title); ?></h1></div>
            <div>
                <a href="academic/individual_results" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Search</a>
            </div>
        </div>

        <!-- Flash messages -->
        <?php if ($success_msg): ?>
            <div class="flash-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="flash-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card unique">
                <div class="stat-value"><?php echo $unique_names_count; ?></div>
                <div class="stat-label">Unique Names</div>
            </div>
            <div class="stat-card duplicate">
                <div class="stat-value"><?php echo $duplicate_groups_count; ?></div>
                <div class="stat-label">Duplicate Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($class_names); ?></div>
                <div class="stat-label">Classes Selected</div>
            </div>
        </div>

        <!-- Duplicate / Similar Names -->
        <?php if ($duplicate_groups_count > 0): ?>
        <div class="tile">
            <div class="tile-body">
                <h3 class="tile-title"><i class="bi bi-exclamation-triangle me-2"></i> Duplicate / Similar Names</h3>
                <?php $group_num = 1; ?>
                <?php foreach ($duplicate_groups as $group): ?>
                    <div class="duplicate-item <?php echo $group['type']; ?>" data-group-id="<?php echo $group_num; ?>">
                        <div class="dup-name">
                            <span class="dup-number"><?php echo $group_num++; ?></span>
                            <?php echo htmlspecialchars($group['display_name']); ?>
                            <span class="dup-count"><?php echo count($group['students']); ?> students</span>
                            <span class="dup-type"><?php echo ucfirst($group['type']); ?></span>
                        </div>
                        <div class="dup-details">
                            <?php
                            $class_groups = [];
                            foreach ($group['students'] as $entry) {
                                $class_name = $entry['class_name'] ?? 'N/A';
                                $class_groups[$class_name][] = $entry;
                            }
                            foreach ($class_groups as $class => $students_in_class):
                            ?>
                                <span class="badge-class" style="background:#4361ee; color:white; border-radius:20px; padding:0.15rem 0.6rem; font-size:0.7rem; font-weight:600; margin-right:0.3rem;"><?php echo htmlspecialchars($class); ?></span>
                                <?php
                                foreach ($students_in_class as $e) {
                                    $reg = htmlspecialchars($e['id']);
                                    $full = htmlspecialchars($e['full']);
                                    $sim = $e['similarity_pct'] ?? '100%';
                                    $term_count = $e['term_data']['term_count'] ?? 0;
                                    $latest_term = $e['term_data']['latest_term_name'] ?? 'None';
                                    $latest_score = $e['term_data']['latest_score'] ?? 'N/A';
                                    $term_info = "($term_count term(s), latest: $latest_term – $latest_score%)";
                                    echo '<span class="student-entry">';
                                    echo $reg . ' (' . $full . ', ' . $sim . ')';
                                    echo ' <span class="term-info">' . $term_info . '</span>';
                                    // Delete button removed as requested
                                    echo '</span> ';
                                }
                                ?><br>
                            <?php endforeach; ?>
                            <span class="text-muted" style="font-size:0.8rem;">
                                <?php echo count($group['students']); ?> occurrence(s) across <?php echo count($class_groups); ?> class(es)
                                <?php if ($group['type'] == 'phonetic'): ?>
                                    – <i>Phonetic match</i>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="tile">
            <div class="tile-body">
                <h3 class="tile-title"><i class="bi bi-check-circle me-2" style="color:#06d6a0;"></i> No Duplicate Names</h3>
                <p class="text-muted">All students in the selected classes have unique names (both exact and phonetic).</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Students Table -->
        <div class="tile">
            <div class="tile-body">
                <h3 class="tile-title"><i class="bi bi-list-ul me-2"></i> All Students in Selected Classes</h3>
                <div class="table-responsive">
                    <table class="table table-hover" id="allStudentsTable">
                        <thead>
                            <tr>
                                <th>Reg No</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Class</th>
                                <th>Terms</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $student_status = [];
                            foreach ($duplicate_groups as $group) {
                                $type = $group['type'];
                                foreach ($group['students'] as $entry) {
                                    $sid = $entry['id'];
                                    if (!isset($student_status[$sid])) {
                                        $student_status[$sid] = [];
                                    }
                                    $student_status[$sid][] = $type;
                                }
                            }
                            foreach ($students as $s): 
                                $full = trim($s['fname'] . ' ' . $s['mname'] . ' ' . $s['lname']);
                                $sid = $s['id'];
                                $statuses = $student_status[$sid] ?? [];
                                $is_duplicate = in_array('exact', $statuses);
                                $is_phonetic = in_array('phonetic', $statuses);
                                $row_class = '';
                                if ($is_duplicate) $row_class = 'duplicate-row';
                                elseif ($is_phonetic) $row_class = 'phonetic-row';

                                // Get term names for this student
                                $term_names = $term_data[$sid]['term_names'] ?? 'None';
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo htmlspecialchars($s['id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($full); ?>
                                        <?php if ($is_duplicate): ?>
                                            <span class="duplicate-badge"><i class="bi bi-exclamation-triangle-fill"></i> Repeat</span>
                                        <?php elseif ($is_phonetic): ?>
                                            <span class="phonetic-badge"><i class="bi bi-volume-up-fill"></i> Similar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($s['class_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($term_names); ?></td>
                                    <td>
                                        <?php if ($is_duplicate): ?>
                                            <span class="badge bg-warning text-dark">Duplicate</span>
                                        <?php elseif ($is_phonetic): ?>
                                            <span class="badge bg-info">Phonetic</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Unique</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    <script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#allStudentsTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "language": {
                    "search": "Filter:",
                    "paginate": {
                        "previous": "<i class='bi bi-chevron-left'></i>",
                        "next": "<i class='bi bi-chevron-right'></i>"
                    }
                },
                "order": [[1, "asc"]]
            });
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>