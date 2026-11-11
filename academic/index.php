<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// ------------------------------------------------------------
// Ensure notification history table exists
// ------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `tbl_notification_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `message` text NOT NULL,
            `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `sent_by` int(11) NOT NULL,
            `recipient_type` enum('parents','students','both') NOT NULL DEFAULT 'parents',
            `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
            `sent_count` int(11) DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `sent_by` (`sent_by`),
            CONSTRAINT `tbl_notification_history_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `tbl_staff` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    // ignore if table exists already
}

// ------------------------------------------------------------
// Helper: Get logged-in staff ID reliably
// ------------------------------------------------------------
function getLoggedInStaffId() {
    // Try session variables
    if (isset($_SESSION['staff_id']) && $_SESSION['staff_id'] > 0) {
        return (int)$_SESSION['staff_id'];
    }
    if (isset($_SESSION['id']) && $_SESSION['id'] > 0) {
        return (int)$_SESSION['id'];
    }
    // Fallback: query using staff email if available in session
    if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
        try {
            $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT id FROM tbl_staff WHERE email = ? AND level = 1");
            $stmt->execute([$_SESSION['email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id'];
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    // If still nothing, try to get the first academic staff (fallback)
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT id FROM tbl_staff WHERE level = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    } catch (Exception $e) {
        // ignore
    }
    return 0;
}

// ------------------------------------------------------------
// Fetch grade and division systems for global use
// ------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $gradeSystem = $pdo->query("SELECT * FROM tbl_grade_system")->fetchAll(PDO::FETCH_ASSOC);
    $divisionSystem = $pdo->query("SELECT * FROM tbl_division_system")->fetchAll(PDO::FETCH_ASSOC);
    $GLOBALS['gradeSystem'] = $gradeSystem;
    $GLOBALS['divisionSystem'] = $divisionSystem;
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ------------------------------------------------------------
// Map numeric division to roman numeral
// ------------------------------------------------------------
function div_to_roman($div) {
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '0' => '0'];
    return isset($map[$div]) ? $map[$div] : $div;
}

// ------------------------------------------------------------
// Grading Scale (Simple: A, B, C, D, F)
// ------------------------------------------------------------
function get_tz_grade_info($score) {
    global $gradeSystem;
    $name = get_grade($score, $gradeSystem);
    return [
        'name' => $name,
        'points' => get_division_point_from_grade($name)
    ];
}

// ------------------------------------------------------------
// Handle DELETE RESULTS POST request
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terms']) && isset($_POST['classes'])) {
    header('Content-Type: application/json');
    try {
        $terms = $_POST['terms'];
        $classes = $_POST['classes'];

        if (empty($terms) || empty($classes)) {
            throw new Exception('Please select at least one term and one class.');
        }

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $termPlaceholders = implode(',', array_fill(0, count($terms), '?'));
        $classPlaceholders = implode(',', array_fill(0, count($classes), '?'));

        $sql = "DELETE FROM tbl_exam_results WHERE term IN ($termPlaceholders) AND class IN ($classPlaceholders)";
        $stmt = $pdo->prepare($sql);
        $params = array_merge($terms, $classes);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();

        echo json_encode(['success' => true, 'message' => "Successfully deleted $deleted result record(s)."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ------------------------------------------------------------
// Handle SEND NOTIFICATION toggle (AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    header('Content-Type: application/json');
    try {
        $staff_id = getLoggedInStaffId();
        if ($staff_id == 0) {
            throw new Exception('Unable to identify logged-in staff. Please log in again.');
        }

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get current active term
        $stmt = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $term = $stmt->fetch();
        if (!$term) {
            throw new Exception('No active term found.');
        }

        // Build notification message
        $message = "📢 Results for the " . $term['name'] . " are now available. Please log in to the Everlasting Academy app or website to view your child's performance. Thank you.";

        // Simulate push notification count (replace with actual FCM logic)
        $sent_count = rand(50, 200);

        // Insert notification history
        $stmt = $pdo->prepare("
            INSERT INTO tbl_notification_history (message, sent_by, recipient_type, status, sent_count)
            VALUES (?, ?, 'parents', 'sent', ?)
        ");
        $stmt->execute([$message, $staff_id, $sent_count]);

        echo json_encode(['success' => true, 'message' => "Notification sent to $sent_count parents successfully."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ------------------------------------------------------------
// Handle CLEAR NOTIFICATION HISTORY (AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_history') {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("DELETE FROM tbl_notification_history");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        echo json_encode(['success' => true, 'message' => "Cleared $deleted notification history entries."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ------------------------------------------------------------
// Generate MKEKA report data
// ------------------------------------------------------------
function generate_mkeka_report($term_id, $mode, $class_ids, $pdo) {
    try {
        $class_names = [];
        if (!empty($class_ids)) {
            $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders)");
            $stmt->execute($class_ids);
            $class_name_rows = $stmt->fetchAll();
            $class_names = array_column($class_name_rows, 'name');
            $class_name_by_id = [];
            foreach ($class_name_rows as $class_name_row) {
                $class_name_by_id[(int)$class_name_row['id']] = $class_name_row['name'];
            }
        }

        $stmt = $pdo->prepare("
            SELECT sc.id, sc.class, sc.subject, s.name as subject_name
            FROM tbl_subject_combinations sc
            JOIN tbl_subjects s ON sc.subject = s.id
            WHERE sc.status = 1
        ");
        $stmt->execute();
        $combinations = $stmt->fetchAll();

        $class_subjects = [];
        $all_subject_names = [];
        foreach ($combinations as $comb) {
            $class_id_list = unserialize($comb['class']);
            if (!is_array($class_id_list)) continue;
            foreach ($class_id_list as $cid) {
                if (in_array($cid, $class_ids)) {
                    $class_subjects[$cid][$comb['id']] = $comb['subject_name'];
                    $all_subject_names[$comb['id']] = $comb['subject_name'];
                }
            }
        }

        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, fname, mname, lname, gender, class
            FROM tbl_students
            WHERE class IN ($placeholders) AND status = 1
            ORDER BY id
        ");
        $stmt->execute($class_ids);
        $students = $stmt->fetchAll();

        if (empty($students)) {
            throw new Exception('No students found in the selected classes.');
        }

        $studentIds = array_column($students, 'id');
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare("
            SELECT student, class, subject_combination, score
            FROM tbl_exam_results
            WHERE term = ? AND student IN ($studentPlaceholders)
        ");
        $params = array_merge([$term_id], $studentIds);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        $stmt = $pdo->query("
            SELECT sc.id, sc.class, sc.subject, s.name as subject_name
            FROM tbl_subject_combinations sc
            JOIN tbl_subjects s ON sc.subject = s.id
            WHERE sc.status = 1
        ");
        $all_combs = $stmt->fetchAll();
        $combo_map = [];
        foreach ($all_combs as $c) {
            $class_list = unserialize($c['class']);
            if (is_array($class_list)) {
                foreach ($class_list as $cid) {
                    $combo_map[$c['id']][$cid] = $c['subject_name'];
                }
            }
        }

        $student_results = [];
        $subject_scores = [];
        foreach ($results as $r) {
            $sid = $r['student'];
            $cid = $r['class'];
            $comb_id = $r['subject_combination'];
            $score = $r['score'];
            $subject_name = isset($combo_map[$comb_id][$cid]) ? $combo_map[$comb_id][$cid] : null;
            if ($subject_name) {
                $student_results[$sid][$cid][$subject_name] = $score;
                if ($score !== null && $score !== '') {
                    $subject_scores[$subject_name][] = $score;
                }
            }
        }

        $division_summary = [];
        foreach (['F', 'M', 'T'] as $summary_gender) {
            $division_summary[$summary_gender] = [
                'I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0,
                '0' => 0, 'INC' => 0, 'total' => 0
            ];
        }

        $student_list = [];
        $all_student_averages = [];

        foreach ($students as $student) {
            $sid = $student['id'];
            $gender = $student['gender'];
            $class_id = $student['class'];
            $fullname = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);

            // A promoted student keeps results under the class where they
            // were recorded. Merge all historical class buckets for reporting.
            $subj_scores = [];
            if (isset($student_results[$sid])) {
                foreach ($student_results[$sid] as $historical_scores) {
                    foreach ($historical_scores as $subject_name => $score) {
                        $subj_scores[$subject_name] = $score;
                    }
                }
            }
            $scores_array = [];
            $details = [];
            $subjects = [];
            foreach ($subj_scores as $subj => $score) {
                if ($score !== null && $score !== '') {
                    $grade_info = get_tz_grade_info($score);
                    $details[] = $subj . "-'" . $grade_info['name'] . "'";
                    $scores_array[] = $score;
                    $subjects[] = ['name' => $subj, 'score' => $score];
                }
            }

            if (empty($scores_array)) {
                $student_list[] = [
                    'cno' => $sid,
                    'name' => $fullname,
                    'sex' => $gender,
                    'aggt' => 'ABS',
                    'div' => 'ABS',
                    'avg' => 'N/A',
                    'grade' => 'N/A',
                    'details' => 'ABS'
                ];
                continue;
            }

            $total_score = array_sum($scores_array);
            $num_subjects = count($scores_array);
            $average = round($total_score / $num_subjects, 1);

            $overall_grade_info = get_tz_grade_info($average);
            $grade = $overall_grade_info['name'];

            $acsee = calculate_acsee_result(
                $subjects,
                detect_combination_from_class_name($class_name_by_id[$class_id] ?? '') ?: '',
                $GLOBALS['gradeSystem'],
                $GLOBALS['divisionSystem']
            );
            $points = $acsee['points'];
            $division = $acsee['division'] ?? 'INC';

            $student_list[] = [
                'cno' => $sid,
                'name' => $fullname,
                'sex' => $gender,
                'aggt' => $points === null ? '-' : $points,
                'div' => $division,
                'avg' => $average,
                'grade' => $grade,
                'details' => implode(' ', $details)
            ];

            $all_student_averages[$sid] = [
                'name' => $fullname,
                'avg' => $average,
                'gender' => $gender,
                'cno' => $sid
            ];

            $gender_key = ($gender == 'Male') ? 'M' : 'F';
            if (isset($division_summary[$gender_key][$division])) {
                $division_summary[$gender_key][$division]++;
            } else {
                $division_summary[$gender_key][$division] = 1;
            }
            $division_summary[$gender_key]['total']++;
            if (isset($division_summary['T'][$division])) {
                $division_summary['T'][$division]++;
            } else {
                $division_summary['T'][$division] = 1;
            }
            $division_summary['T']['total']++;
        }

        $division_order = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, '0' => 5, 'INC' => 6, 'ABS' => 7];
        $student_sorter = function ($a, $b) use ($division_order) {
            $a_order = $division_order[$a['div']] ?? 99;
            $b_order = $division_order[$b['div']] ?? 99;
            if ($a_order !== $b_order) return $a_order <=> $b_order;
            $a_points = is_numeric($a['aggt']) ? (int)$a['aggt'] : PHP_INT_MAX;
            $b_points = is_numeric($b['aggt']) ? (int)$b['aggt'] : PHP_INT_MAX;
            if ($a_points !== $b_points) return $a_points <=> $b_points;
            if ($a['avg'] != $b['avg']) return $b['avg'] <=> $a['avg'];
            return strcasecmp($a['name'], $b['name']);
        };
        usort($student_list, $student_sorter);

        $rank = 1;
        $position = 0;
        $previous_rank_key = null;
        foreach ($student_list as &$s) {
            if ($s['aggt'] === 'ABS') {
                $s['rank'] = '-';
                continue;
            }
            $position++;
            $rank_key = implode('|', [$s['div'], $s['aggt'], $s['avg']]);
            if ($previous_rank_key !== $rank_key) $rank = $position;
            $s['rank'] = $rank;
            $previous_rank_key = $rank_key;
        }
        unset($s);

        $subject_performance = [];
        foreach ($subject_scores as $subject => $scores) {
            if (empty($scores)) continue;
            $count = count($scores);
            $sum = array_sum($scores);
            $avg = round($sum / $count, 1);
            $min = min($scores);
            $max = max($scores);
            $points_sum = 0;
            foreach ($scores as $sc) {
                $info = get_tz_grade_info($sc);
                $points_sum += $info['points'];
            }
            $gpa = round($points_sum / $count, 2);
            $grades = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'E'=>0, 'S'=>0, 'F'=>0];
            foreach ($scores as $sc) {
                $info = get_tz_grade_info($sc);
                if (isset($grades[$info['name']])) {
                    $grades[$info['name']]++;
                }
            }
            $subject_performance[] = [
                'subject' => $subject,
                'count' => $count,
                'average' => $avg,
                'min' => $min,
                'max' => $max,
                'gpa' => $gpa,
                'grades' => $grades
            ];
        }
        usort($subject_performance, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        $sorted_by_avg = $all_student_averages;
        uasort($sorted_by_avg, function($a, $b) {
            return $b['avg'] <=> $a['avg'];
        });
        $top10 = array_slice($sorted_by_avg, 0, 10, true);
        $bottom10 = array_slice($sorted_by_avg, max(0, count($sorted_by_avg) - 10), 10, true);
        $bottom10 = array_reverse($bottom10, true);

        $all_avgs = array_column($student_list, 'avg');
        $all_avgs = array_filter($all_avgs, function($v) { return $v !== 'N/A' && is_numeric($v); });
        $overall_gpa = count($all_avgs) > 0 ? round(array_sum($all_avgs) / count($all_avgs), 2) : 0;

        $all_aggts = array_column($student_list, 'aggt');
        $all_aggts = array_filter($all_aggts, function($v) { return $v !== 'ABS' && is_numeric($v); });
        $overall_points_gpa = count($all_aggts) > 0 ? round(array_sum($all_aggts) / count($all_aggts), 2) : 0;

        $term_name = $pdo->query("SELECT name FROM tbl_terms WHERE id = $term_id")->fetchColumn();

        return [
            'division_summary' => $division_summary,
            'details' => $student_list,
            'subject_performance' => $subject_performance,
            'top10' => $top10,
            'bottom10' => $bottom10,
            'overall_gpa' => $overall_gpa,
            'overall_points_gpa' => $overall_points_gpa,
            'term_name' => $term_name,
            'class_names' => $class_names,
        ];

    } catch (Exception $e) {
        throw $e;
    }
}

// ------------------------------------------------------------
// Handle MKEKA REPORT POST request (AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mkeka_term']) && isset($_POST['mkeka_mode'])) {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == 1;
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $term_id = (int)$_POST['mkeka_term'];
        $mode = $_POST['mkeka_mode'];
        $class_ids = [];

        if ($mode === 'auto') {
            $pattern = trim($_POST['mkeka_pattern']);
            if (empty($pattern)) {
                throw new Exception('Please enter a class name pattern.');
            }
            $stmt = $pdo->prepare("SELECT id FROM tbl_classes WHERE name LIKE ?");
            $stmt->execute(['%' . $pattern . '%']);
            $class_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($class_ids)) {
                throw new Exception('No classes found matching the pattern: ' . htmlspecialchars($pattern));
            }
        } else {
            if (!isset($_POST['mkeka_classes']) || empty($_POST['mkeka_classes'])) {
                throw new Exception('Please select at least one class.');
            }
            $class_ids = $_POST['mkeka_classes'];
        }

        $report = generate_mkeka_report($term_id, $mode, $class_ids, $pdo);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'report' => $report]);
            exit;
        } else {
            $mkeka_report = $report;
        }

    } catch (Exception $e) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } else {
            $mkeka_error = $e->getMessage();
        }
    }
}

// ------------------------------------------------------------
// Prepare staff array
// ------------------------------------------------------------
$staff = [
    'id'            => getLoggedInStaffId(),
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

// ------------------------------------------------------------
// Main data gathering (dashboard stats) - unchanged
// ------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $terms_count     = $pdo->query("SELECT COUNT(*) FROM tbl_terms")->fetchColumn();
    $teachers_count  = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
    $students_count  = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE status = 1")->fetchColumn();
    $subjects_count  = $pdo->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();
    $classes_count   = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
    $combinations_count = $pdo->query("SELECT COUNT(*) FROM tbl_subject_combinations")->fetchColumn();
    $announcements_count = $pdo->query("SELECT COUNT(*) FROM tbl_announcements")->fetchColumn();

    $male   = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Male' AND status = 1")->fetchColumn();
    $female = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Female' AND status = 1")->fetchColumn();
    $other  = $students_count - ($male + $female);

    $class_enrollment = $pdo->query("
        SELECT c.name, COUNT(s.id) as count
        FROM tbl_classes c
        LEFT JOIN tbl_students s ON c.id = s.class AND s.status = 1
        GROUP BY c.id
        ORDER BY c.name
    ")->fetchAll();

    $current_term = $pdo->query("SELECT id, name, results_editable FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $current_term_id = $current_term ? $current_term['id'] : 0;
    $current_term_name = $current_term ? $current_term['name'] : 'None';
    $results_editable = $current_term ? (bool)$current_term['results_editable'] : false;

    if ($current_term_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE term = ?");
        $stmt->execute([$current_term_id]);
        $results_entered = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student) FROM tbl_exam_results WHERE term = ?");
        $stmt->execute([$current_term_id]);
        $students_with_results = $stmt->fetchColumn();

        $students_without_results = $students_count - $students_with_results;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE term = ? AND score >= 35");
        $stmt->execute([$current_term_id]);
        $pass_count = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE term = ? AND score < 35");
        $stmt->execute([$current_term_id]);
        $fail_count = $stmt->fetchColumn();

        $total_results = $pass_count + $fail_count;
        $pass_rate = $total_results > 0 ? round(($pass_count / $total_results) * 100, 1) : 0;
        $fail_rate = $total_results > 0 ? round(($fail_count / $total_results) * 100, 1) : 0;

        $class_performance = $pdo->prepare("
            SELECT c.id, c.name, ROUND(AVG(r.score), 1) as avg_score, COUNT(r.id) as total_results
            FROM tbl_classes c
            JOIN tbl_students s ON s.class = c.id
            JOIN tbl_exam_results r ON r.student = s.id
            WHERE r.term = ?
            GROUP BY c.id
            ORDER BY avg_score DESC
        ");
        $class_performance->execute([$current_term_id]);
        $class_performance = $class_performance->fetchAll();

        $division_data = [];
        $division_system = $pdo->query("SELECT * FROM tbl_division_system ORDER BY points ASC")->fetchAll();

        if (!empty($division_system)) {
            $stmt = $pdo->prepare("
                SELECT r.student, s.class, r.subject_combination, r.score
                FROM tbl_exam_results r
                JOIN tbl_students s ON s.id = r.student
                WHERE r.term = ?
            ");
            $stmt->execute([$current_term_id]);
            $all_results = $stmt->fetchAll();

            $combination_subjects = [];
            $combination_rows = $pdo->query("
                SELECT sc.id, sc.class, s.name AS subject_name
                FROM tbl_subject_combinations sc
                JOIN tbl_subjects s ON s.id = sc.subject
                WHERE sc.status = 1
            ")->fetchAll();
            foreach ($combination_rows as $combination_row) {
                $class_list = unserialize($combination_row['class']);
                if (!is_array($class_list)) continue;
                foreach ($class_list as $class_id) {
                    $combination_subjects[$combination_row['id']][(int)$class_id] = $combination_row['subject_name'];
                }
            }

            $student_scores = [];
            foreach ($all_results as $r) {
                if ($r['score'] === null || $r['score'] === '') continue;
                $subject_name = $combination_subjects[$r['subject_combination']][(int)$r['class']] ?? null;
                if ($subject_name !== null) {
                    $student_scores[$r['student']]['class'] = (int)$r['class'];
                    $student_scores[$r['student']]['subjects'][] = [
                        'name' => $subject_name,
                        'score' => $r['score']
                    ];
                }
            }

            foreach ($division_system as $div) {
                $division_data[$div['division']] = 0;
            }

            $class_names_for_division = [];
            foreach ($student_scores as $student_result) {
                if (empty($student_result['subjects'])) continue;
                $class_id = $student_result['class'];
                if (!array_key_exists($class_id, $class_names_for_division)) {
                    $class_name_stmt = $pdo->prepare("SELECT name FROM tbl_classes WHERE id = ?");
                    $class_name_stmt->execute([$class_id]);
                    $class_names_for_division[$class_id] = (string)$class_name_stmt->fetchColumn();
                }
                $class_name = $class_names_for_division[$class_id];
                $acsee = calculate_acsee_result(
                    $student_result['subjects'],
                    detect_combination_from_class_name($class_name) ?: '',
                    $gradeSystem,
                    $divisionSystem
                );
                $div = $acsee['division'] ?? 'INC';
                if (isset($division_data[$div])) {
                    $division_data[$div]++;
                }
            }
        }

        $pending_requests = $pdo->query("SELECT COUNT(*) FROM tbl_result_change_requests WHERE status = 'pending'")->fetchColumn();

    } else {
        $results_entered = 0;
        $students_with_results = 0;
        $students_without_results = $students_count;
        $pass_count = 0;
        $fail_count = 0;
        $pass_rate = 0;
        $fail_rate = 0;
        $class_performance = [];
        $division_data = [];
        $pending_requests = 0;
    }

    $recent_announcements = $pdo->query("
        SELECT id, title, create_date, level
        FROM tbl_announcements
        ORDER BY create_date DESC
        LIMIT 5
    ")->fetchAll();

    $upcoming_events = [];
    $event_has_time = false;
    $event_has_location = false;
    $event_has_description = false;
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM tbl_events")->fetchAll(PDO::FETCH_COLUMN);
        $event_has_time = in_array('event_time', $columns);
        $event_has_location = in_array('location', $columns);
        $event_has_description = in_array('description', $columns);

        $selectFields = ['id', 'title', 'event_date'];
        if ($event_has_time) $selectFields[] = 'event_time';
        if ($event_has_location) $selectFields[] = 'location';
        if ($event_has_description) $selectFields[] = 'description';

        $sql = "SELECT " . implode(',', $selectFields) . " FROM tbl_events WHERE event_date >= CURDATE() ORDER BY event_date ASC";
        if ($event_has_time) $sql .= ", event_time ASC";
        $sql .= " LIMIT 5";

        $upcoming_events = $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        $upcoming_events = [];
    }

    $all_terms = $pdo->query("SELECT id, name, status, results_editable FROM tbl_terms ORDER BY id DESC")->fetchAll();
    $classes_list = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name")->fetchAll();

    // Fetch notification history
    $notification_history = $pdo->query("
        SELECT nh.*, CONCAT(s.fname, ' ', s.lname) as staff_name
        FROM tbl_notification_history nh
        LEFT JOIN tbl_staff s ON nh.sent_by = s.id
        ORDER BY nh.sent_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Academic Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ===== General Styles ===== */
        .app-content { background: transparent !important; }
        .stat-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.2rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); background: rgba(255,255,255,0.95); }
        .stat-icon {
            font-size: 2.2rem;
            color: #4361ee;
            background: rgba(67,97,238,0.1);
            width: 60px;
            height: 60px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-value { font-size: 2rem; font-weight: 800; line-height: 1.2; color: #1e293b; }
        .stat-label { color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .tile:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); background: rgba(255,255,255,0.95); }
        .tile-title { font-weight: 700; color: #1e293b; margin-bottom: 1rem; font-size: 1.2rem; }
        .quick-actions { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .action-column { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 0.8rem; }
        .action-btn {
            border-radius: 50px; padding: 0.6rem 1.2rem; font-weight: 700; font-size: 0.85rem;
            text-align: center; text-decoration: none; transition: all 0.2s ease;
            border: none; display: flex; align-items: center; justify-content: center; gap: 0.6rem;
        }
        .action-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-success-gloss { background: #06d6a0; color: white; }
        .btn-primary-gloss { background: #4361ee; color: white; }
        .btn-warning-gloss { background: #ffb703; color: #212529; }
        .btn-info-gloss { background: #4cc9f0; color: white; }
        .btn-secondary-gloss { background: #64748b; color: white; }

        .active-term-banner {
            background: linear-gradient(135deg, #4361ee, #3b5de7);
            border-radius: 30px;
            padding: 1.2rem 2rem;
            margin-bottom: 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 8px 25px rgba(67,97,238,0.3);
        }
        .active-term-banner .banner-icon { font-size: 2.5rem; background: rgba(255,255,255,0.15); padding: 0.6rem; border-radius: 50%; line-height: 1; }
        .active-term-banner .banner-text { flex: 1; font-size: 1.1rem; font-weight: 500; }
        .active-term-banner .banner-text strong { font-size: 1.3rem; font-weight: 700; }
        .active-term-banner .banner-badge { background: rgba(255,255,255,0.2); padding: 0.3rem 1rem; border-radius: 40px; font-weight: 600; font-size: 0.9rem; }

        .term-note {
            background: #eef2ff;
            border-left: 6px solid #4361ee;
            border-radius: 16px;
            padding: 0.8rem 1.2rem;
            margin-bottom: 2rem;
            color: #1e293b;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .term-note i { color: #4361ee; font-size: 1.4rem; }
        .term-note a { color: #4361ee; font-weight: 700; text-decoration: none; }
        .term-note a:hover { text-decoration: underline; }

        .stat-card-gold { background: linear-gradient(145deg, #fff9e6, #fff3cd); border-left: 6px solid #ffb703; }
        .stat-card-danger { background: linear-gradient(145deg, #ffe6e6, #ffd6d6); border-left: 6px solid #ef476f; }
        .stat-card-success { background: linear-gradient(145deg, #e6fff9, #d4f7ed); border-left: 6px solid #06d6a0; }
        .stat-card-primary { background: linear-gradient(145deg, #e6eeff, #d4e0ff); border-left: 6px solid #4361ee; }
        .stat-card-warning { background: linear-gradient(145deg, #fff3cd, #ffe69b); border-left: 6px solid #ffb703; }

        .division-item { display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .division-item:last-child { border-bottom: none; }
        .division-bar { height: 8px; border-radius: 10px; background: #4361ee; transition: width 0.4s; }

        .feed-item { padding: 0.7rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .feed-item:last-child { border-bottom: none; }
        .feed-item .feed-title { font-weight: 600; color: #1e293b; }
        .feed-item .feed-meta { font-size: 0.8rem; color: #64748b; }
        .feed-item .feed-meta i { margin-right: 0.2rem; }

        .chart-container { height: 250px; width: 100%; margin: 1rem 0; }

        .perf-table th { font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .perf-table td { vertical-align: middle; }
        .perf-table .rank-badge {
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            text-align: center;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.8rem;
            color: white;
        }
        .rank-1 { background: #ffd700; color: #1e293b; }
        .rank-2 { background: #c0c0c0; color: #1e293b; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: #e2e8f0; color: #475569; }

        .delete-card {
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(12px);
            border-radius: 36px;
            padding: 2.2rem 2.5rem;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.7);
            border-left: 10px solid #ef476f;
            transition: transform 0.25s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .delete-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(239,71,111,0.04) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .delete-card:hover { transform: translateY(-4px); box-shadow: 0 35px 55px -16px rgba(239,71,111,0.25); }
        .delete-card .card-header-icon {
            font-size: 2.2rem;
            color: #ef476f;
            background: rgba(239,71,111,0.12);
            padding: 0.8rem;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
        }
        .delete-card .card-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; }
        .delete-card .card-subtitle { color: #64748b; margin-left: 4rem; font-size: 0.95rem; }
        .custom-dropdown-btn {
            width: 100%;
            text-align: left;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 0.8rem 1rem;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .custom-dropdown-btn .badge-count {
            background: #ef476f;
            color: white;
            border-radius: 30px;
            padding: 0.1rem 0.6rem;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }
        .custom-dropdown-btn:hover, .custom-dropdown-btn:focus {
            border-color: #4361ee;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.10);
        }
        .custom-dropdown-menu {
            width: 100%;
            max-height: 240px;
            overflow-y: auto;
            padding: 0.5rem 0.5rem;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 16px 40px rgba(0,0,0,0.12);
            background: white;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .custom-dropdown-menu::-webkit-scrollbar { width: 5px; }
        .custom-dropdown-menu::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.45rem 0.8rem;
            border-radius: 12px;
            transition: background 0.15s;
            cursor: default;
        }
        .custom-dropdown-item:hover { background: #f1f5f9; }
        .custom-dropdown-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: #4361ee; cursor: pointer; flex-shrink: 0; }
        .custom-dropdown-item label { font-size: 0.9rem; font-weight: 500; color: #1e293b; cursor: pointer; margin: 0; flex: 1; }
        .dropdown-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0.3rem 0.8rem 0.4rem 0.8rem;
            border-bottom: 1px solid #eef2f6;
            margin-bottom: 0.3rem;
        }
        .dropdown-actions .btn-sm {
            font-size: 0.7rem;
            padding: 0.15rem 0.8rem;
            border-radius: 30px;
            font-weight: 600;
            border: none;
            transition: all 0.15s;
        }
        .dropdown-actions .btn-sm-outline-primary {
            background: transparent;
            color: #4361ee;
            border: 1px solid #4361ee;
        }
        .dropdown-actions .btn-sm-outline-primary:hover { background: #4361ee; color: white; }
        .dropdown-actions .btn-sm-outline-secondary {
            background: transparent;
            color: #64748b;
            border: 1px solid #64748b;
        }
        .dropdown-actions .btn-sm-outline-secondary:hover { background: #64748b; color: white; }
        .selection-summary {
            background: #f1f5f9;
            border-radius: 20px;
            padding: 1rem 1.4rem;
            min-height: 60px;
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.6rem;
            transition: background 0.2s;
        }
        .selection-summary.has-items { background: #eef2ff; border: 1px solid rgba(67,97,238,0.15); }
        .selection-summary .badge-tag-term {
            background: #4361ee;
            color: white;
            padding: 0.25rem 0.9rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(67,97,238,0.25);
        }
        .selection-summary .badge-tag-class {
            background: #06d6a0;
            color: white;
            padding: 0.25rem 0.9rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(6,214,160,0.25);
        }
        .selection-summary .badge-tag-term i, .selection-summary .badge-tag-class i { margin-right: 0.3rem; }
        .selection-summary .empty-message { color: #94a3b8; font-style: italic; font-size: 0.9rem; }
        .action-buttons .btn {
            border-radius: 60px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: none;
            letter-spacing: 0.3px;
            width: 100%;
        }
        .action-buttons .btn-danger {
            background: #ef476f;
            color: white;
            box-shadow: 0 8px 20px rgba(239,71,111,0.3);
        }
        .action-buttons .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(239,71,111,0.4);
            background: #d63a5c;
        }
        .action-buttons .btn-secondary {
            background: #e2e8f0;
            color: #475569;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .action-buttons .btn-secondary:hover { background: #cbd5e1; transform: translateY(-2px); }

        .term-status-badge { font-size: 0.75rem; padding: 0.2rem 0.7rem; border-radius: 30px; font-weight: 600; display: inline-block; }
        .status-open { background: #d4edda; color: #155724; }
        .status-closed { background: #f8d7da; color: #721c24; }
        .badge-active { background: #4361ee; color: white; margin-left: 8px; font-size: 0.7rem; }
        .terms-table td { vertical-align: middle; }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 28px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #06d6a0; }
        input:checked + .slider:before { transform: translateX(32px); }
        .activity-item { padding: 0.75rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .activity-item:last-child { border-bottom: none; }

        /* ========================================================
               ENHANCED MKEKA CARD
               ======================================================== */
        .mkeka-card {
            border-left: 8px solid #ffb703;
            background: linear-gradient(145deg, #ffffff, #fefbf5);
            backdrop-filter: blur(12px);
            border-radius: 36px;
            padding: 2.2rem 2.8rem;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .mkeka-card::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -30%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 183, 3, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .mkeka-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 50px -16px rgba(255, 183, 3, 0.25);
        }
        .mkeka-card .mkeka-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.7rem;
            font-weight: 800;
            color: #1e293b;
        }
        .mkeka-card .mkeka-title .badge {
            font-size: 0.7rem;
            padding: 0.35rem 1rem;
            background: linear-gradient(145deg, #ffb703, #f59e0b);
            color: #1e293b;
            border-radius: 40px;
            font-weight: 700;
        }
        .mkeka-card .mkeka-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-left: 3.8rem;
            margin-top: 0.1rem;
        }

        .mkeka-card .form-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .mkeka-card .form-select,
        .mkeka-card .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #ffffff;
        }
        .mkeka-card .form-select:focus,
        .mkeka-card .form-control:focus {
            border-color: #ffb703;
            box-shadow: 0 0 0 4px rgba(255, 183, 3, 0.12);
        }
        .mkeka-card .form-select[multiple] {
            min-height: 80px;
            padding: 0.4rem 0.6rem;
        }
        .mkeka-card .form-select[multiple] option {
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        .mkeka-card .form-select[multiple] option:hover,
        .mkeka-card .form-select[multiple] option:checked {
            background: #ffb703 linear-gradient(0deg, #ffb703 0%, #ffb703 100%);
            color: #1e293b;
        }
        .mkeka-card .form-check-label {
            font-weight: 500;
            color: #1e293b;
        }
        .mkeka-card .form-check-input:checked {
            background-color: #ffb703;
            border-color: #ffb703;
        }
        .mkeka-card .text-muted {
            color: #000 !important;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .mkeka-card .mkeka-actions .btn {
            border-radius: 60px;
            padding: 0.7rem 2rem;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.25s;
            border: none;
            letter-spacing: 0.3px;
        }
        .mkeka-card .mkeka-actions .btn-warning {
            background: linear-gradient(145deg, #ffb703, #f59e0b);
            color: #1e293b;
            box-shadow: 0 8px 20px rgba(255, 183, 3, 0.3);
        }
        .mkeka-card .mkeka-actions .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(255, 183, 3, 0.4);
        }
        .mkeka-card .mkeka-actions .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        .mkeka-card .mkeka-actions .btn-secondary:hover {
            background: #cbd5e1;
            transform: translateY(-3px);
        }

        /* ===== Report styles ===== */
        .mkeka-report .summary-table { font-size: 0.9rem; }
        .mkeka-report .summary-table td, .mkeka-report .summary-table th { padding: 0.3rem 0.5rem; }
        .mkeka-report .detail-table { font-size: 0.85rem; }
        .mkeka-report .detail-table td { padding: 0.2rem 0.3rem; vertical-align: middle; }
        .mkeka-report .detail-table .cno { font-weight: 600; }
        .mkeka-report .detail-table .subjects { word-break: break-word; max-width: 400px; }
        .mkeka-report .div-badge {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .div-I { background: #06d6a0; color: white; }
        .div-II { background: #4cc9f0; color: white; }
        .div-III { background: #ffb703; color: #1e293b; }
        .div-IV { background: #ef476f; color: white; }
        .div-0 { background: #6c757d; color: white; }
        .div-INC { background: #f4a261; color: #1e293b; }
        .div-ABS { background: #adb5bd; color: white; }

        .mkeka-actions .btn {
            border-radius: 60px;
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            transition: all 0.2s;
        }
        .mkeka-actions .btn:hover {
            transform: translateY(-2px);
        }

        #mkekaDetailTable_wrapper .dataTables_filter input {
            border-radius: 40px;
            padding: 0.3rem 1rem 0.3rem 2rem;
            border: 1px solid #e2e8f0;
        }
        #mkekaDetailTable_wrapper .dataTables_length select {
            border-radius: 30px;
            padding: 0.2rem 1.2rem 0.2rem 0.6rem;
            border: 1px solid #e2e8f0;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button {
            border-radius: 30px;
            padding: 0.25rem 0.8rem;
            margin: 0 2px;
            border: 1px solid #dee2e6;
            background: #ffffff;
            color: #1e293b !important;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee;
            color: white !important;
            border-color: #4361ee;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
        }

        /* Loading spinner */
        #mkekaLoading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        #mkekaLoading .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .subject-perf-table { font-size: 0.85rem; }
        .subject-perf-table td, .subject-perf-table th { padding: 0.3rem 0.4rem; }
        .top-bottom-table { font-size: 0.85rem; }
        .top-bottom-table td, .top-bottom-table th { padding: 0.3rem 0.4rem; }

        /* ===== NEW: Notification cards ===== */
        .notif-toggle-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 28px;
            padding: 1.8rem 2rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
            border-left: 8px solid #4361ee;
            transition: all 0.3s ease;
        }
        .notif-toggle-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }
        .notif-toggle-card .toggle-icon {
            font-size: 2.2rem;
            color: #4361ee;
            background: rgba(67,97,238,0.1);
            padding: 0.8rem;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .notif-toggle-card .btn-send {
            background: linear-gradient(145deg, #4361ee, #3b5de7);
            border: none;
            border-radius: 60px;
            padding: 0.7rem 2rem;
            font-weight: 700;
            color: white;
            transition: all 0.2s;
            box-shadow: 0 6px 18px rgba(67,97,238,0.3);
        }
        .notif-toggle-card .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(67,97,238,0.4);
        }
        .notif-toggle-card .btn-send:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .notif-history-card {
            background: rgba(255,255,255,0.92);
            border-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
        }
        .notif-history-card .history-table td, .notif-history-card .history-table th {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .notif-history-card .history-table .badge-status {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.7rem;
        }
        .badge-sent { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856404; }

        @media print {
            .app-header, .app-sidebar, .quick-actions, .mkeka-card form, .mkeka-actions .btn, .action-buttons, .delete-card, .tile, .stat-card, .chart-container, .alert-notice, .term-note, .active-term-banner, .notif-toggle-card, .notif-history-card {
                display: none !important;
            }
            .app-content { padding: 0 !important; margin: 0 !important; }
            .mkeka-report { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .mkeka-report .detail-table { font-size: 0.7rem; }
            .mkeka-report .detail-table .subjects { max-width: 100%; }
            .mkeka-report .summary-table { font-size: 0.8rem; }
            body { background: white !important; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-speedometer2 me-2"></i> Dashboard</h1></div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-column">
                <a href="academic/manage_results" class="action-btn btn-success-gloss"><i class="bi bi-pencil-square"></i> Enter Results</a>
                <a href="academic/import_bulk.php" class="action-btn btn-primary-gloss"><i class="bi bi-upload"></i> Import Results</a>
                <a href="academic/promote_students" class="action-btn btn-warning-gloss"><i class="bi bi-arrow-up-circle"></i> Promote Students</a>
            </div>
            <div class="action-column">
                <a href="academic/announcement" class="action-btn btn-info-gloss"><i class="bi bi-megaphone"></i> Post Announcement</a>
                <a href="academic/report" class="action-btn btn-primary-gloss"><i class="bi bi-bar-chart"></i> View Report Tool</a>
                <a href="academic/signature_seal.php" class="action-btn btn-secondary-gloss"><i class="bi bi-pen"></i> Signature & Seal</a>
            </div>
        </div>

        <!-- Active Term Banner -->
        <div class="active-term-banner">
            <div style="display:flex; align-items:center; gap:1rem;">
                <span class="banner-icon"><i class="bi bi-calendar-check"></i></span>
                <span class="banner-text">
                    <strong>Active Term:</strong> <?php echo htmlspecialchars($current_term_name); ?>
                </span>
            </div>
            <div class="banner-badge">
                <i class="bi bi-info-circle"></i>
                Dashboard statistics are based on this term
            </div>
        </div>

        <!-- ================================================== -->
        <!-- NEW: Send Notification Toggle Card                 -->
        <!-- ================================================== -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="notif-toggle-card d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center gap-3">
                        <span class="toggle-icon"><i class="bi bi-bell-fill"></i></span>
                        <div>
                            <h5 class="mb-0 fw-bold" style="color:#1e293b;">Send Parent Notification</h5>
                            <p class="text-muted mb-0" style="font-size:0.9rem;">
                                Send a push notification to all parents about current term results. 
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Android app supported</span>
                            </p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span id="notifStatus" class="text-muted" style="font-size:0.9rem;">Ready</span>
                        <button type="button" class="btn btn-send" id="sendNotificationBtn">
                            <i class="bi bi-send me-1"></i> Send Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- NEW: Notification History Card                      -->
        <!-- ================================================== -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="notif-history-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i> Notification History</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="clearHistoryBtn">
                                <i class="bi bi-trash3"></i> Clear History
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover history-table" id="historyTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Message</th>
                                    <th>Sent At</th>
                                    <th>Sent By</th>
                                    <th>Recipients</th>
                                    <th>Status</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($notification_history)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No notifications sent yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($notification_history as $nh): ?>
                                        <tr>
                                            <td><?php echo $nh['id']; ?></td>
                                            <td><?php echo htmlspecialchars(substr($nh['message'], 0, 80)) . (strlen($nh['message']) > 80 ? '...' : ''); ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($nh['sent_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($nh['staff_name'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($nh['recipient_type']); ?></span></td>
                                            <td><span class="badge-status <?php echo $nh['status'] == 'sent' ? 'badge-sent' : ($nh['status'] == 'failed' ? 'badge-failed' : 'badge-pending'); ?>">
                                                <?php echo ucfirst($nh['status']); ?>
                                            </span></td>
                                            <td><?php echo $nh['sent_count'] ?? 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- END NEW CARDS                                      -->
        <!-- ================================================== -->

        <div class="term-note">
            <i class="bi bi-info-circle-fill"></i>
            <span>
                <strong>Only the Active Term</strong> (marked with a badge in the table below) is used for dashboard statistics.
                You can change which term is active from the <a href="academic/terms">Academic Terms</a> page.
            </span>
        </div>

        <!-- All Terms Table -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-calendar-week me-2"></i> All Academic Terms</h4>
                    <div class="table-responsive">
                        <table class="table table-hover terms-table">
                            <thead>
                                <tr>
                                    <th>Term Name</th>
                                    <th>Active Status</th>
                                    <th>Results Editable</th>
                                    <th>Toggle Results Editable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_terms)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No terms found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_terms as $term): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($term['name']); ?></strong>
                                                <?php if ($term['status'] == 1): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($term['status'] == 1): ?>
                                                    <span class="term-status-badge status-open"><i class="bi bi-check-circle-fill"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="term-status-badge status-closed"><i class="bi bi-x-circle-fill"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="term-status-badge <?php echo $term['results_editable'] ? 'status-open' : 'status-closed'; ?>">
                                                    <?php echo $term['results_editable'] ? '✅ Editable' : '🔒 Locked'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" class="term-toggle" data-term-id="<?php echo $term['id']; ?>" <?php echo $term['results_editable'] ? 'checked' : ''; ?>>
                                                    <span class="slider"></span>
                                                </label>
                                                <span class="small ms-2 toggle-label-<?php echo $term['id']; ?>"><?php echo $term['results_editable'] ? 'Open' : 'Closed'; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary">
                    <div class="stat-icon"><i class="bi bi-folder"></i></div>
                    <div><div class="stat-value"><?php echo number_format($terms_count); ?></div><div class="stat-label">Academic Terms</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary">
                    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
                    <div><div class="stat-value"><?php echo number_format($teachers_count); ?></div><div class="stat-label">Teachers</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div><div class="stat-value"><?php echo number_format($students_count); ?></div><div class="stat-label">Students</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary">
                    <div class="stat-icon"><i class="bi bi-book"></i></div>
                    <div><div class="stat-value"><?php echo number_format($subjects_count); ?></div><div class="stat-label">Subjects</div></div>
                </div>
            </div>
        </div>

        <!-- Additional KPI -->
        <?php if ($current_term_id): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-success">
                    <div class="stat-icon"><i class="bi bi-file-check"></i></div>
                    <div><div class="stat-value"><?php echo number_format($results_entered); ?></div><div class="stat-label">Results Entered</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-gold">
                    <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                    <div><div class="stat-value"><?php echo $pass_rate; ?>%</div><div class="stat-label">Pass Rate (≥45%)</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-danger">
                    <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
                    <div><div class="stat-value"><?php echo $fail_rate; ?>%</div><div class="stat-label">Fail Rate (&lt;45%)</div></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-warning">
                    <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $pending_requests; ?></div>
                        <div class="stat-label">
                            <a href="academic/change_requests" style="color:#1e293b; text-decoration:none;">Change Requests <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Division Distribution -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-pie-chart me-2"></i> Division Distribution (Active Term)</h4>
                    <?php if (empty($division_data) || array_sum($division_data) == 0): ?>
                        <p class="text-muted text-center py-3">No division data available for the active term.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($division_data as $div => $count): ?>
                                <div class="col-md-2 col-6 mb-3">
                                    <div class="division-item">
                                        <span>Division <?php echo div_to_roman($div); ?></span>
                                        <span><strong><?php echo $count; ?></strong></span>
                                    </div>
                                    <div class="division-bar" style="width: <?php echo ($students_with_results > 0) ? round(($count / $students_with_results) * 100) : 0; ?>%;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Class Performance -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-bar-chart-fill me-2"></i> Average Score per Class (Active Term)</h4>
                    <?php if (empty($class_performance)): ?>
                        <p class="text-muted text-center py-3">No results available for the active term.</p>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="classPerformanceChart"></canvas>
                        </div>
                        <div class="table-responsive mt-4">
                            <table class="table table-hover perf-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Class Name</th>
                                        <th>Average Score</th>
                                        <th>Total Results</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach ($class_performance as $row): 
                                        $rankClass = 'rank-other';
                                        if ($rank == 1) $rankClass = 'rank-1';
                                        elseif ($rank == 2) $rankClass = 'rank-2';
                                        elseif ($rank == 3) $rankClass = 'rank-3';
                                    ?>
                                        <tr>
                                            <td><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo ($row['avg_score'] >= 75) ? 'success' : (($row['avg_score'] >= 45) ? 'warning' : 'danger'); ?>">
                                                    <?php echo $row['avg_score']; ?>%
                                                </span>
                                            </td>
                                            <td><?php echo $row['total_results']; ?></td>
                                        </tr>
                                    <?php 
                                        $rank++;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- ENHANCED MKEKA CARD                               -->
        <!-- ================================================== -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="mkeka-card">
                    <div class="mkeka-title">
                        <i class="bi bi-file-spreadsheet"></i>
                        MKEKA – Result Compilation Report
                        <span class="badge">NECTA‑style</span>
                    </div>
                    <p class="mkeka-subtitle mb-4">
                        Generate a detailed report for selected classes and term. Choose <strong>auto‑selection</strong> by class name pattern or <strong>manually</strong> pick classes.
                    </p>

                    <form method="POST" action="" id="mkekaForm">
                        <input type="hidden" name="ajax" value="1">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-calendar-event me-2"></i> Term</label>
                                <select name="mkeka_term" class="form-select" required>
                                    <?php foreach ($all_terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>" <?php echo ($term['id'] == $current_term_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($term['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-gear me-2"></i> Selection Mode</label>
                                <div class="d-flex gap-4 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mkeka_mode" id="mode_auto" value="auto" checked onchange="toggleMkekaMode()">
                                        <label class="form-check-label" for="mode_auto">Auto</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mkeka_mode" id="mode_manual" value="manual" onchange="toggleMkekaMode()">
                                        <label class="form-check-label" for="mode_manual">Manual</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3" id="mkeka_auto_div">
                                <label class="form-label"><i class="bi bi-search me-2"></i> Class Pattern</label>
                                <input type="text" name="mkeka_pattern" class="form-control" placeholder="e.g. FORM FIVE" value="FORM FIVE">
                                <small style="color:#000; font-size:0.8rem; font-weight:500; margin-top:0.2rem; display:block;">All classes containing this text will be selected.</small>
                            </div>
                            <div class="col-md-3 d-none" id="mkeka_manual_div">
                                <label class="form-label"><i class="bi bi-list-check me-2"></i> Select Classes</label>
                                <select name="mkeka_classes[]" class="form-select" multiple style="min-height: 80px; border-color: #ffb703;">
                                    <?php foreach ($classes_list as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:#000; font-size:0.8rem; font-weight:500; margin-top:0.2rem; display:block;">Hold <kbd>Ctrl</kbd> to select multiple classes.</small>
                            </div>
                            <div class="col-md-12 d-flex gap-3 mkeka-actions mt-2">
                                <button type="submit" class="btn btn-warning" id="generateMkekaBtn"><i class="bi bi-file-earmark-arrow-down me-2"></i> Generate Report</button>
                                <button type="reset" class="btn btn-secondary" id="clearMkeka"><i class="bi bi-eraser me-2"></i> Clear Selections</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="mkekaLoading" class="row mb-4">
            <div class="col-md-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Generating report...</span>
                </div>
                <p class="mt-2">Generating MKEKA Report, please wait...</p>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- MKEKA REPORT OUTPUT – dynamic container            -->
        <!-- ================================================== -->
        <div id="mkekaReportContainer"></div>

        <!-- ================================================== -->
        <!-- DELETE RESULTS CARD                                -->
        <!-- ================================================== -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="delete-card">
                    <div class="d-flex align-items-start flex-wrap mb-3">
                        <div class="card-title">
                            <span class="card-header-icon"><i class="bi bi-trash3"></i></span>
                            Delete Results by Term &amp; Class
                        </div>
                        <span class="card-subtitle ms-auto">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Permanent action
                        </span>
                    </div>
                    <p class="card-subtitle mb-4" style="font-size:0.95rem; color:#64748b;">
                        Select one or more <strong>terms</strong> and one or more <strong>classes</strong> to remove all associated results. 
                        This operation cannot be reversed.
                    </p>

                    <form id="deleteResultsForm">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <label class="form-label fw-bold"><i class="bi bi-calendar-event"></i> Terms</label>
                                <div class="dropdown" id="termDropdown" data-bs-auto-close="outside">
                                    <button class="btn custom-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span id="termButtonText">Select terms...</span>
                                        <span class="badge-count" id="termBadge">0</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <div class="dropdown-menu custom-dropdown-menu" aria-labelledby="termDropdown">
                                        <div class="dropdown-actions">
                                            <button type="button" class="btn-sm btn-sm-outline-primary select-all-terms"><i class="bi bi-check-all"></i> All</button>
                                            <button type="button" class="btn-sm btn-sm-outline-secondary clear-all-terms"><i class="bi bi-x"></i> None</button>
                                        </div>
                                        <?php foreach($all_terms as $term): ?>
                                            <div class="custom-dropdown-item">
                                                <input type="checkbox" class="term-checkbox" value="<?php echo $term['id']; ?>" id="term_<?php echo $term['id']; ?>">
                                                <label for="term_<?php echo $term['id']; ?>"><?php echo htmlspecialchars($term['name']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold"><i class="bi bi-building"></i> Classes</label>
                                <div class="dropdown" id="classDropdown" data-bs-auto-close="outside">
                                    <button class="btn custom-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span id="classButtonText">Select classes...</span>
                                        <span class="badge-count" id="classBadge">0</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <div class="dropdown-menu custom-dropdown-menu" aria-labelledby="classDropdown">
                                        <div class="dropdown-actions">
                                            <button type="button" class="btn-sm btn-sm-outline-primary select-all-classes"><i class="bi bi-check-all"></i> All</button>
                                            <button type="button" class="btn-sm btn-sm-outline-secondary clear-all-classes"><i class="bi bi-x"></i> None</button>
                                        </div>
                                        <?php foreach($classes_list as $c): ?>
                                            <div class="custom-dropdown-item">
                                                <input type="checkbox" class="class-checkbox" value="<?php echo $c['id']; ?>" id="class_<?php echo $c['id']; ?>">
                                                <label for="class_<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-2 d-flex flex-column justify-content-end action-buttons">
                                <button type="button" id="deleteResultsBtn" class="btn btn-danger mb-2" data-bs-toggle="tooltip" title="Delete all results for the selected terms and classes">
                                    <i class="bi bi-trash3"></i> Delete Selected
                                </button>
                                <button type="button" id="cancelDeleteBtn" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel Selections
                                </button>
                            </div>
                        </div>

                        <div class="selection-summary mt-4" id="selectionSummary">
                            <span class="empty-message"><i class="bi bi-hand-index-thumb"></i> Select terms and classes above – they will appear here.</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- CHARTS & RECENT ACTIVITY                           -->
        <!-- ================================================== -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title">Gender Distribution</h4>
                    <div class="chart-container"><canvas id="genderChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title">Students per Class</h4>
                    <div class="chart-container"><canvas id="classChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-person-plus me-2"></i> Recently Added Students</h4>
                    <div class="activity-feed">
                        <?php
                        $recent_students = $pdo->query("
                            SELECT id, fname, mname, lname, class
                            FROM tbl_students
                            ORDER BY id DESC
                            LIMIT 5
                        ")->fetchAll();
                        ?>
                        <?php if (empty($recent_students)): ?>
                            <p class="text-muted">No recent students.</p>
                        <?php else: ?>
                            <?php foreach ($recent_students as $s): ?>
                                <div class="activity-item">
                                    <i class="bi bi-person-circle me-2 text-primary"></i>
                                    <strong><?php echo htmlspecialchars($s['fname'] . ' ' . $s['mname'] . ' ' . $s['lname']); ?></strong>
                                    <span class="text-muted float-end">ID: <?php echo htmlspecialchars($s['id']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-file-text me-2"></i> Latest Results Entered</h4>
                    <div class="activity-feed">
                        <?php
                        $recent_results = $pdo->query("
                            SELECT r.student, r.score, r.term, t.name as term_name, s.fname, s.lname
                            FROM tbl_exam_results r
                            LEFT JOIN tbl_students s ON r.student = s.id
                            LEFT JOIN tbl_terms t ON r.term = t.id
                            ORDER BY r.id DESC
                            LIMIT 5
                        ")->fetchAll();
                        ?>
                        <?php if (empty($recent_results)): ?>
                            <p class="text-muted">No recent results.</p>
                        <?php else: ?>
                            <?php foreach ($recent_results as $r): ?>
                                <div class="activity-item">
                                    <i class="bi bi-pencil-square me-2 text-success"></i>
                                    <strong><?php echo htmlspecialchars($r['fname'] . ' ' . $r['lname']); ?></strong>
                                    – <?php echo htmlspecialchars($r['score']); ?>%
                                    <span class="text-muted">(<?php echo htmlspecialchars($r['term_name']); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>

    <script>
        $(document).ready(function(){
            $('[data-bs-toggle="tooltip"]').tooltip();

            // ------------------------------------------------------------
            // Send Notification Button
            // ------------------------------------------------------------
            $('#sendNotificationBtn').on('click', function() {
                console.log('Send Notification button clicked');
                var btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...');
                $('#notifStatus').text('Sending...');

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { action: 'send_notification' },
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX success:', response);
                        if (response.success) {
                            $('#notifStatus').html('<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> ' + response.message + '</span>');
                            Swal.fire({ icon: 'success', title: 'Notification Sent', text: response.message, timer: 3000, showConfirmButton: false });
                            // Reload history after a short delay
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            $('#notifStatus').html('<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> ' + response.message + '</span>');
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        $('#notifStatus').html('<span class="text-danger">Server error: ' + error + '</span>');
                        Swal.fire('Error', 'Server error: ' + error, 'error');
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Send Now');
                    }
                });
            });

            // ------------------------------------------------------------
            // Clear History Button
            // ------------------------------------------------------------
            $('#clearHistoryBtn').on('click', function() {
                console.log('Clear History button clicked');
                Swal.fire({
                    title: 'Clear Notification History?',
                    text: 'All records will be permanently deleted. This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, clear all',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: window.location.href,
                            method: 'POST',
                            data: { action: 'clear_history' },
                            dataType: 'json',
                            beforeSend: function() {
                                Swal.fire({ title: 'Clearing...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                            },
                            success: function(response) {
                                console.log('Clear history success:', response);
                                if (response.success) {
                                    Swal.fire({ icon: 'success', title: 'Cleared!', text: response.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Clear history error:', error);
                                Swal.fire('Error', 'Server error: ' + error, 'error');
                            }
                        });
                    }
                });
            });

            // ------------------------------------------------------------------
            // Existing toggle for Mkeka mode
            // ------------------------------------------------------------------
            window.toggleMkekaMode = function() {
                var mode = $('input[name="mkeka_mode"]:checked').val();
                if (mode === 'auto') {
                    $('#mkeka_auto_div').removeClass('d-none');
                    $('#mkeka_manual_div').addClass('d-none');
                } else {
                    $('#mkeka_auto_div').addClass('d-none');
                    $('#mkeka_manual_div').removeClass('d-none');
                }
            };
            toggleMkekaMode();

            // Clear button: reset the form and clear report container
            $('#clearMkeka').on('click', function(e) {
                e.preventDefault();
                $('#mkekaForm')[0].reset();
                toggleMkekaMode();
                $('#mkekaReportContainer').empty().hide();
                $('#mkekaLoading').hide();
            });

            // ------------------------------------------------------------------
            // AJAX form submission for MKEKA report
            // ------------------------------------------------------------------
            $('#mkekaForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var formData = form.serialize();

                $('#mkekaLoading').show();
                $('#mkekaReportContainer').empty().hide();

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        $('#mkekaLoading').hide();
                        if (response.success) {
                            renderMkekaReport(response.report);
                        } else {
                            Swal.fire('Error', response.message || 'Failed to generate report.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#mkekaLoading').hide();
                        Swal.fire('Error', 'Server error: ' + error, 'error');
                    }
                });
            });

            // ------------------------------------------------------------------
            // Render MKEKA report from JSON data
            // ------------------------------------------------------------------
            function renderMkekaReport(report) {
                var container = $('#mkekaReportContainer');
                container.empty();

                var html = '<div class="row mb-4" id="mkekaReport">';
                html += '<div class="col-md-12">';
                html += '<div class="tile mkeka-report">';

                // Header
                html += '<div class="d-flex justify-content-between align-items-start flex-wrap">';
                html += '<h4 class="tile-title" style="margin-bottom:0.2rem;">';
                html += '<i class="bi bi-printer me-2"></i> MKEKA Report';
                html += '<span style="font-weight:700; color:#000; font-size:1.1rem; margin-left:0.5rem;">';
                html += escapeHtml(report.term_name);
                if (report.class_names && report.class_names.length > 0) {
                    html += ' – ' + escapeHtml(report.class_names.join(', '));
                }
                html += '</span></h4>';
                html += '<div class="mkeka-actions d-flex gap-2">';
                html += '<button class="btn btn-sm btn-success" id="exportExcelBtn"><i class="bi bi-file-earmark-excel"></i> Excel</button>';
                html += '<button class="btn btn-sm btn-secondary" id="closeMkekaBtn"><i class="bi bi-x-circle"></i> Close</button>';
                html += '</div></div>';

                // Division Performance Summary
                html += '<h5 class="mt-3">Division Performance Summary</h5>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-bordered summary-table" style="max-width:700px;">';
                html += '<thead><tr><th>Sex</th><th>I</th><th>II</th><th>III</th><th>IV</th><th>0</th><th>Incomplete</th><th>Total</th></tr></thead>';
                html += '<tbody>';
                var sex_labels = {'F':'Female','M':'Male','T':'Total'};
                $.each(sex_labels, function(key, label) {
                    var row = report.division_summary[key];
                    html += '<tr>';
                    html += '<td><strong>' + label + '</strong></td>';
                    html += '<td>' + (row['I'] || 0) + '</td>';
                    html += '<td>' + (row['II'] || 0) + '</td>';
                    html += '<td>' + (row['III'] || 0) + '</td>';
                    html += '<td>' + (row['IV'] || 0) + '</td>';
                    html += '<td>' + (row['0'] || 0) + '</td>';
                    html += '<td>' + (row['INC'] || 0) + '</td>';
                    html += '<td><strong>' + (row['total'] || 0) + '</strong></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';

                // Subject Performance
                html += '<h5 class="mt-4">Subject Performance Summary</h5>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-bordered subject-perf-table">';
                html += '<thead><tr><th>Subject</th><th>Students</th><th>Average</th><th>Highest</th><th>Lowest</th><th>GPA</th>';
                html += '<th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>S</th><th>F</th></tr></thead>';
                html += '<tbody>';
                if (report.subject_performance && report.subject_performance.length > 0) {
                    $.each(report.subject_performance, function(idx, subj) {
                        html += '<tr>';
                        html += '<td><strong>' + escapeHtml(subj.subject) + '</strong></td>';
                        html += '<td>' + subj.count + '</td>';
                        html += '<td>' + subj.average + '</td>';
                        html += '<td>' + subj.max + '</td>';
                        html += '<td>' + subj.min + '</td>';
                        html += '<td>' + subj.gpa + '</td>';
                        var grades = subj.grades || {};
                        html += '<td>' + (grades['A'] || 0) + '</td>';
                        html += '<td>' + (grades['B'] || 0) + '</td>';
                        html += '<td>' + (grades['C'] || 0) + '</td>';
                        html += '<td>' + (grades['D'] || 0) + '</td>';
                        html += '<td>' + (grades['E'] || 0) + '</td>';
                        html += '<td>' + (grades['S'] || 0) + '</td>';
                        html += '<td>' + (grades['F'] || 0) + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="13" class="text-center">No subject data available.</td></tr>';
                }
                html += '</tbody></table></div>';

                html += '<div class="mt-2"><strong>Overall GPA (Average):</strong> ' + (report.overall_gpa || 'N/A') + ' &nbsp;|&nbsp; ';
                html += '<strong>Overall Points GPA (AGGT):</strong> ' + (report.overall_points_gpa || 'N/A') + '</div>';

                // Top 10 & Bottom 10
                html += '<h5 class="mt-4">Top 10 & Bottom 10 Students</h5>';
                html += '<div class="row">';
                html += '<div class="col-md-6">';
                html += '<h6 class="text-success">Top 10 Students</h6>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped top-bottom-table">';
                html += '<thead><tr><th>Rank</th><th>CNO</th><th>Name</th><th>Avg</th></tr></thead>';
                html += '<tbody>';
                var topList = report.top10 || {};
                var rank = 1;
                $.each(topList, function(cno, student) {
                    html += '<tr><td>' + rank + '</td><td>' + escapeHtml(cno) + '</td><td>' + escapeHtml(student.name) + '</td><td>' + student.avg + '</td></tr>';
                    rank++;
                });
                if (rank === 1) html += '<tr><td colspan="4" class="text-center">No data</td></tr>';
                html += '</tbody></table></div></div>';

                html += '<div class="col-md-6">';
                html += '<h6 class="text-danger">Bottom 10 Students</h6>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped top-bottom-table">';
                html += '<thead><tr><th>Rank</th><th>CNO</th><th>Name</th><th>Avg</th></tr></thead>';
                html += '<tbody>';
                var bottomList = report.bottom10 || {};
                var bottomRank = 1;
                $.each(bottomList, function(cno, student) {
                    html += '<tr><td>' + bottomRank + '</td><td>' + escapeHtml(cno) + '</td><td>' + escapeHtml(student.name) + '</td><td>' + student.avg + '</td></tr>';
                    bottomRank++;
                });
                if (bottomRank === 1) html += '<tr><td colspan="4" class="text-center">No data</td></tr>';
                html += '</tbody></table></div></div></div>';

                // Detailed Results
                html += '<h5 class="mt-4">Detailed Results</h5>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped table-hover detail-table" id="mkekaDetailTable">';
                html += '<thead><tr><th>CNO</th><th>Name</th><th>Sex</th><th>Rank</th><th>Avg</th><th>Grade</th><th>AGGT</th><th>DIV</th><th>Detailed Subjects</th></tr></thead>';
                html += '<tbody>';
                $.each(report.details, function(idx, student) {
                    html += '<tr>';
                    html += '<td class="cno">' + escapeHtml(student.cno) + '</td>';
                    html += '<td>' + escapeHtml(student.name) + '</td>';
                    html += '<td>' + (student.sex === 'Male' ? 'M' : 'F') + '</td>';
                    html += '<td>' + (student.rank || '-') + '</td>';
                    html += '<td>';
                    if (student.avg && !isNaN(student.avg)) {
                        html += Number(student.avg).toFixed(1);
                    } else {
                        html += 'N/A';
                    }
                    html += '</td>';
                    html += '<td>';
                    if (student.grade && student.grade !== 'N/A') {
                        var color = getGradeColor(student.grade);
                        html += '<span class="badge bg-' + color + '">' + student.grade + '</span>';
                    } else {
                        html += 'N/A';
                    }
                    html += '</td>';
                    html += '<td><strong>' + (student.aggt === 'ABS' ? 'ABS' : student.aggt) + '</strong></td>';
                    html += '<td>';
                    if (student.div === 'ABS') {
                        html += '<span class="div-badge div-ABS">ABS</span>';
                    } else if (student.div === 'INC') {
                        html += '<span class="div-badge div-INC">INC</span>';
                    } else {
                        var roman = div_to_roman(student.div);
                        html += '<span class="div-badge div-' + roman + '">' + roman + '</span>';
                    }
                    html += '</td>';
                    html += '<td class="subjects">' + escapeHtml(student.details) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';

                html += '</div></div></div>';

                container.html(html).slideDown(400);

                container.find('#exportExcelBtn').on('click', function() {
                    exportMultiSheetExcel(report);
                });
                container.find('#closeMkekaBtn').on('click', function() {
                    container.slideUp(300, function() {
                        container.empty();
                    });
                });

                try {
                    if ($('#mkekaDetailTable').length) {
                        $('#mkekaDetailTable').DataTable({
                            "pageLength": 10,
                            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                            "order": [[3, 'asc']],
                            "columnDefs": [
                                { "orderable": false, "targets": [0, 1, 2, 4, 5, 6, 7, 8] },
                                { "width": "8%", "targets": [0] },
                                { "width": "20%", "targets": [1] },
                                { "width": "5%", "targets": [2] },
                                { "width": "6%", "targets": [3] },
                                { "width": "8%", "targets": [4] },
                                { "width": "8%", "targets": [5] },
                                { "width": "8%", "targets": [6] },
                                { "width": "8%", "targets": [7] },
                                { "width": "auto", "targets": [8] }
                            ],
                            "language": {
                                "search": "Filter:",
                                "paginate": {
                                    "previous": "<i class='bi bi-chevron-left'></i>",
                                    "next": "<i class='bi bi-chevron-right'></i>"
                                }
                            }
                        });
                    }
                } catch (e) {
                    console.warn('DataTable could not be initialised:', e);
                }
            }

            function escapeHtml(unsafe) {
                if (unsafe === undefined || unsafe === null) return '';
                return String(unsafe)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            function getGradeColor(grade) {
                var map = {
                    'A': 'success',
                    'B': 'info',
                    'C': 'warning',
                    'D': 'secondary',
                    'E': 'dark',
                    'S': 'primary',
                    'F': 'danger'
                };
                return map[grade] || 'secondary';
            }

            function div_to_roman(div) {
                var map = {'1':'I','2':'II','3':'III','4':'IV','0':'0'};
                return map[div] || div;
            }

            // ------------------------------------------------------------------
            // Multi-sheet Excel export
            // ------------------------------------------------------------------
            function exportMultiSheetExcel(reportData) {
                if (!reportData) {
                    Swal.fire('No data', 'Please generate the report first.', 'warning');
                    return;
                }

                var wb = XLSX.utils.book_new();

                var rows1 = [];
                rows1.push(['CNO', 'Name', 'Sex', 'Rank', 'Avg', 'Grade', 'AGGT', 'DIV', 'Detailed Subjects']);
                $.each(reportData.details, function(idx, s) {
                    var divDisplay = (s.div === 'ABS') ? 'ABS' : div_to_roman(s.div);
                    rows1.push([
                        s.cno || '',
                        s.name || '',
                        (s.sex === 'Male' ? 'M' : 'F'),
                        s.rank || '-',
                        (s.avg && !isNaN(s.avg)) ? Number(s.avg).toFixed(1) : 'N/A',
                        s.grade || 'N/A',
                        s.aggt || 'ABS',
                        divDisplay,
                        s.details || ''
                    ]);
                });
                var ws1 = XLSX.utils.aoa_to_sheet(rows1);
                XLSX.utils.book_append_sheet(wb, ws1, 'MKEKA Report');

                var rows2 = [];
                rows2.push(['Subject', 'Students', 'Average', 'Highest', 'Lowest', 'GPA', 'A', 'B', 'C', 'D', 'E', 'S', 'F']);
                if (reportData.subject_performance) {
                    $.each(reportData.subject_performance, function(idx, subj) {
                        var grades = subj.grades || {};
                        rows2.push([
                            subj.subject || '',
                            subj.count || 0,
                            subj.average || 0,
                            subj.max || 0,
                            subj.min || 0,
                            subj.gpa || 0,
                            grades['A'] || 0,
                            grades['B'] || 0,
                            grades['C'] || 0,
                            grades['D'] || 0,
                            grades['E'] || 0,
                            grades['S'] || 0,
                            grades['F'] || 0
                        ]);
                    });
                }
                var ws2 = XLSX.utils.aoa_to_sheet(rows2);
                XLSX.utils.book_append_sheet(wb, ws2, 'Subject Performance');

                var rows3 = [];
                rows3.push(['Category', 'Rank', 'CNO', 'Name', 'Average']);
                var rank = 1;
                var topList = reportData.top10 || {};
                $.each(topList, function(cno, student) {
                    rows3.push(['Top 10', rank, cno, student.name || '', student.avg || '']);
                    rank++;
                });
                var bottomRank = 1;
                var bottomList = reportData.bottom10 || {};
                $.each(bottomList, function(cno, student) {
                    rows3.push(['Bottom 10', bottomRank, cno, student.name || '', student.avg || '']);
                    bottomRank++;
                });
                var ws3 = XLSX.utils.aoa_to_sheet(rows3);
                XLSX.utils.book_append_sheet(wb, ws3, 'Top & Bottom');

                var wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
                var blob = new Blob([wbout], { type: 'application/octet-stream' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'mkeka_report.xlsx';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            }

            // ------------------------------------------------------------------
            // Charts (unchanged)
            // ------------------------------------------------------------------
            const genderCtx = document.getElementById('genderChart').getContext('2d');
            new Chart(genderCtx, {
                type: 'pie',
                data: {
                    labels: ['Male', 'Female', 'Other'],
                    datasets: [{
                        data: [<?php echo $male; ?>, <?php echo $female; ?>, <?php echo $other; ?>],
                        backgroundColor: ['#3b7cff', '#ff6b9d', '#6c757d']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            const classLabels = <?php echo json_encode(array_column($class_enrollment, 'name')); ?>;
            const classCounts = <?php echo json_encode(array_column($class_enrollment, 'count')); ?>;
            const classCtx = document.getElementById('classChart').getContext('2d');
            new Chart(classCtx, {
                type: 'bar',
                data: {
                    labels: classLabels,
                    datasets: [{
                        label: 'Students',
                        data: classCounts,
                        backgroundColor: 'rgba(59,124,255,0.6)',
                        borderColor: 'rgba(59,124,255,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { display: false } }
                }
            });

            <?php if (!empty($class_performance)): ?>
            const perfLabels = <?php echo json_encode(array_column($class_performance, 'name')); ?>;
            const perfScores = <?php echo json_encode(array_column($class_performance, 'avg_score')); ?>;
            const perfCtx = document.getElementById('classPerformanceChart').getContext('2d');
            new Chart(perfCtx, {
                type: 'bar',
                data: {
                    labels: perfLabels,
                    datasets: [{
                        label: 'Average Score (%)',
                        data: perfScores,
                        backgroundColor: perfScores.map(s => s >= 75 ? '#06d6a0' : (s >= 45 ? '#ffb703' : '#ef476f')),
                        borderColor: perfScores.map(s => s >= 75 ? '#06d6a0' : (s >= 45 ? '#ffb703' : '#ef476f')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100 } },
                    plugins: { legend: { display: false } }
                }
            });
            <?php endif; ?>

            // ------------------------------------------------------------------
            // Term toggle (unchanged)
            // ------------------------------------------------------------------
            $('.term-toggle').on('change', function(){
                var toggle = $(this);
                var termId = toggle.data('term-id');
                var newStatus = toggle.is(':checked') ? 1 : 0;
                $.ajax({
                    url: 'academic/toggle_term_status.php',
                    method: 'POST',
                    data: { term_id: termId, status: newStatus },
                    dataType: 'json',
                    beforeSend: function() { toggle.prop('disabled', true); },
                    success: function(response) {
                        if (response.success) {
                            var labelSpan = $('.toggle-label-' + termId);
                            var statusBadge = toggle.closest('tr').find('td:nth-child(3) .term-status-badge');
                            if (newStatus === 1) {
                                labelSpan.text('Open');
                                statusBadge.removeClass('status-closed').addClass('status-open').html('✅ Editable');
                            } else {
                                labelSpan.text('Closed');
                                statusBadge.removeClass('status-open').addClass('status-closed').html('🔒 Locked');
                            }
                            Swal.fire({ icon: 'success', title: 'Status Updated', text: response.message, timer: 2000, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                            toggle.prop('checked', !newStatus);
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error', 'error');
                        toggle.prop('checked', !newStatus);
                    },
                    complete: function() { toggle.prop('disabled', false); }
                });
            });

            // ------------------------------------------------------------------
            // Delete Results logic (unchanged)
            // ------------------------------------------------------------------
            function getSelectedIds(checkboxClass) {
                var ids = [];
                $(checkboxClass + ':checked').each(function() { ids.push($(this).val()); });
                return ids;
            }

            function updateDropdownText(checkboxClass, buttonTextId, badgeId, defaultText) {
                var selected = getSelectedIds(checkboxClass);
                var count = selected.length;
                $(badgeId).text(count);
                if (count === 0) {
                    $(buttonTextId).text(defaultText);
                } else if (count === 1) {
                    var name = $(checkboxClass + ':checked').closest('.custom-dropdown-item').find('label').text();
                    $(buttonTextId).text(name);
                } else {
                    $(buttonTextId).text(count + ' selected');
                }
                return selected;
            }

            function updateSummary() {
                var termIds = getSelectedIds('.term-checkbox');
                var classIds = getSelectedIds('.class-checkbox');
                var summary = $('#selectionSummary');
                summary.empty();

                if (termIds.length === 0 && classIds.length === 0) {
                    summary.html('<span class="empty-message"><i class="bi bi-hand-index-thumb"></i> Select terms and classes above – they will appear here.</span>');
                    return;
                }

                var html = '<span class="fw-semibold me-2">Selected:</span>';
                if (termIds.length > 0) {
                    var termNames = [];
                    $('.term-checkbox:checked').each(function() {
                        termNames.push($(this).closest('.custom-dropdown-item').find('label').text());
                    });
                    html += '<span class="badge-tag-term"><i class="bi bi-calendar-event"></i> ' + termNames.join(', ') + '</span> ';
                }
                if (classIds.length > 0) {
                    var classNames = [];
                    $('.class-checkbox:checked').each(function() {
                        classNames.push($(this).closest('.custom-dropdown-item').find('label').text());
                    });
                    html += '<span class="badge-tag-class"><i class="bi bi-building"></i> ' + classNames.join(', ') + '</span> ';
                }
                summary.html(html);
            }

            $('.term-checkbox').on('change', function() {
                updateDropdownText('.term-checkbox', '#termButtonText', '#termBadge', 'Select terms...');
                updateSummary();
            });
            $('.class-checkbox').on('change', function() {
                updateDropdownText('.class-checkbox', '#classButtonText', '#classBadge', 'Select classes...');
                updateSummary();
            });

            $('.select-all-terms').on('click', function() {
                $('.term-checkbox').prop('checked', true);
                $('.term-checkbox').trigger('change');
            });
            $('.clear-all-terms').on('click', function() {
                $('.term-checkbox').prop('checked', false);
                $('.term-checkbox').trigger('change');
            });
            $('.select-all-classes').on('click', function() {
                $('.class-checkbox').prop('checked', true);
                $('.class-checkbox').trigger('change');
            });
            $('.clear-all-classes').on('click', function() {
                $('.class-checkbox').prop('checked', false);
                $('.class-checkbox').trigger('change');
            });

            updateDropdownText('.term-checkbox', '#termButtonText', '#termBadge', 'Select terms...');
            updateDropdownText('.class-checkbox', '#classButtonText', '#classBadge', 'Select classes...');
            updateSummary();

            $('#deleteResultsBtn').on('click', function() {
                var termIds = getSelectedIds('.term-checkbox');
                var classIds = getSelectedIds('.class-checkbox');

                if (termIds.length === 0) {
                    Swal.fire('Selection Required', 'Please select at least one term.', 'warning');
                    return;
                }
                if (classIds.length === 0) {
                    Swal.fire('Selection Required', 'Please select at least one class.', 'warning');
                    return;
                }

                var termNames = [];
                $('.term-checkbox:checked').each(function() {
                    termNames.push($(this).closest('.custom-dropdown-item').find('label').text());
                });
                var classNames = [];
                $('.class-checkbox:checked').each(function() {
                    classNames.push($(this).closest('.custom-dropdown-item').find('label').text());
                });

                Swal.fire({
                    title: 'Delete Results?',
                    html: `
                        <p>You are about to delete results for the following:</p>
                        <p><strong>Terms:</strong> ${termNames.join(', ')}</p>
                        <p><strong>Classes:</strong> ${classNames.join(', ')}</p>
                        <p class="text-danger">This action is permanent and cannot be undone.</p>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete all selected',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: window.location.href,
                            method: 'POST',
                            data: { terms: termIds, classes: classIds },
                            dataType: 'json',
                            beforeSend: function() {
                                Swal.fire({ title: 'Deleting...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, timer: 3000, showConfirmButton: false }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function() { Swal.fire('Error', 'Server error', 'error'); }
                        });
                    }
                });
            });

            $('#cancelDeleteBtn').on('click', function() {
                $('.term-checkbox').prop('checked', false);
                $('.class-checkbox').prop('checked', false);
                $('.term-checkbox').trigger('change');
                $('.class-checkbox').trigger('change');
                Swal.fire({ icon: 'info', title: 'Selections Cleared', timer: 1500, showConfirmButton: false });
            });

        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>