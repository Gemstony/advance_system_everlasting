<?php
/**
 * Student Header – Trilingual AI with Continuous Voice Input + Voice Output
 * Default theme: Rose Garden (theme-26)
 * 3D Blue Robot with cursor-tracking eyes, frequent idle expressions,
 * six special activities, adaptive 3D cloud thought bubble with rippled
 * edges, unique weather per grade (sun / rainbow / fireworks / rain / storm),
 * collision-safe anchoring during subject analysis, sad face on logout,
 * guided results analysis (result-page aware), animated stop button,
 * career suggestions, animated 3D desk lamp, triple-click routing,
 * logout confirmation, and a scroll-to-top button pinned to the
 * bottom-right corner.
 */

// -------------------------------------------------------------------
// 1) LOAD CONFIG & START SESSION
// -------------------------------------------------------------------
if (file_exists(__DIR__ . '/../db/config/db.php')) {
    require_once __DIR__ . '/../db/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} elseif (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
} else {
    if (!defined('DBHost')) {
        define('DBHost', 'localhost');
        define('DBName', 'everlast_alevel_db');
        define('DBUser', 'root');
        define('DBPass', '');
        define('DBCharset', 'utf8mb4');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------------
// 2) HELPER FUNCTIONS
// -------------------------------------------------------------------
if (!function_exists('h')) {
    function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('safe_first_name')) {
    function safe_first_name($fullName): string {
        $fullName = trim((string)$fullName);
        if ($fullName === '') return 'Student';
        $parts = preg_split('/\s+/', $fullName);
        return $parts[0] ?? 'Student';
    }
}
if (!function_exists('db_query_all')) {
    function db_query_all($pdo, string $sql, array $params = []): array {
        if (!$pdo || !($pdo instanceof PDO)) return [];
        try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { error_log('db_query_all failed: ' . $e->getMessage()); return []; }
    }
}
if (!function_exists('db_query_one')) {
    function db_query_one($pdo, string $sql, array $params = []) {
        if (!$pdo || !($pdo instanceof PDO)) return null;
        try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (Throwable $e) { error_log('db_query_one failed: ' . $e->getMessage()); return null; }
    }
}
if (!function_exists('detect_language_mode')) {
    function detect_language_mode(string $text): string {
        $text = strtolower($text);
        if (preg_match('/\p{Han}/u', $text)) return 'zh';
        $swKeywords = ['habari','msaada','ada','matokeo','ratiba','som','shule','kwanini','nina','vipi','je'];
        $enKeywords = ['hello','hi','hey','help','fee','result','results','timetable','subject','school','courses','calendar'];
        $sw = 0; $en = 0;
        foreach ($swKeywords as $w) if (str_contains($text, $w)) $sw++;
        foreach ($enKeywords as $w) if (str_contains($text, $w)) $en++;
        if ($sw > $en) return 'sw';
        if ($en > $sw) return 'en';
        if (preg_match('/(你好|您好|嗨|嘿|怎么样)/i', $text)) return 'zh';
        return 'en';
    }
}
if (!function_exists('build_navigation_map')) {
    function build_navigation_map(): array {
        return [
            'dashboard' => ['name' => 'Dashboard', 'menu' => 'Dashboard'],
            'my profile' => ['name' => 'My Profile', 'menu' => 'Student Info -> My Profile'],
            'my subjects' => ['name' => 'My Subjects', 'menu' => 'Courses -> My Subjects'],
            'timetable' => ['name' => 'Timetable', 'menu' => 'Courses -> Timetable'],
            'syllabus' => ['name' => 'Syllabus / Materials', 'menu' => 'Courses -> Syllabus / Materials'],
            'examination results' => ['name' => 'Examination Results', 'menu' => 'Assessment -> Examination Results'],
            'assignments' => ['name' => 'Assignments & Quizzes', 'menu' => 'Assessment -> Assignments & Quizzes'],
            'grading system' => ['name' => 'Grading System', 'menu' => 'Assessment -> Grading System'],
            'division system' => ['name' => 'Division System', 'menu' => 'Assessment -> Division System'],
            'fee dashboard' => ['name' => 'Fee Dashboard', 'menu' => 'Finance & Fees -> Fee Dashboard'],
            'my invoices' => ['name' => 'My Invoices', 'menu' => 'Finance & Fees -> My Invoices'],
            'payment history' => ['name' => 'Payment History', 'menu' => 'Finance & Fees -> Payment History'],
            'fee structure' => ['name' => 'Fee Structure', 'menu' => 'Finance & Fees -> Fee Structure'],
            'make payment' => ['name' => 'Make Payment', 'menu' => 'Finance & Fees -> Make Payment'],
            'announcements' => ['name' => 'Announcements', 'menu' => 'Communication -> Announcements'],
            'helpdesk' => ['name' => 'Helpdesk / Support', 'menu' => 'Support & Extras -> Helpdesk / Support'],
            'school calendar' => ['name' => 'School Calendar', 'menu' => 'School Calendar'],
            'learn' => ['name' => 'Learn', 'menu' => 'Learn'],
        ];
    }
}
if (!function_exists('get_navigation_help')) {
    function get_navigation_help(string $query): string {
        $map = build_navigation_map();
        $query_lower = strtolower($query);
        $best_match = null; $best_score = 0;
        foreach ($map as $key => $item) {
            similar_text($query_lower, strtolower($key), $percent);
            if ($percent > $best_score) { $best_score = $percent; $best_match = $item; }
        }
        if ($best_score > 40) {
            return "You can find " . $best_match['name'] . " by clicking " . $best_match['menu'] . " in the sidebar. Just open that menu and you'll see it.";
        }
        return '';
    }
}
if (!function_exists('guess_page_info')) {
    function guess_page_info(): array {
        $path = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/'));
        $file = basename($path);
        $pages = [
            'index.php' => ['name' => 'Dashboard', 'desc' => 'Your main overview.'],
            'view.php' => ['name' => 'My Profile', 'desc' => 'Your personal information.'],
            'subjects.php' => ['name' => 'My Subjects', 'desc' => 'Subjects you are enrolled in.'],
            'timetable.php' => ['name' => 'Timetable', 'desc' => 'Weekly class schedule.'],
            'syllabus.php' => ['name' => 'Syllabus / Materials', 'desc' => 'Learning materials and resources.'],
            'results.php' => ['name' => 'Examination Results', 'desc' => 'Your exam scores and grades.'],
            'assignments.php' => ['name' => 'Assignments & Quizzes', 'desc' => 'Homework and quizzes.'],
            'grading-system.php' => ['name' => 'Grading System', 'desc' => 'How grades are calculated.'],
            'division-system.php' => ['name' => 'Division System', 'desc' => 'How divisions are grouped.'],
            'fees.php' => ['name' => 'Fee Dashboard', 'desc' => 'Fee balance and payments.'],
            'invoices.php' => ['name' => 'My Invoices', 'desc' => 'Your fee invoices.'],
            'payment-history.php' => ['name' => 'Payment History', 'desc' => 'Record of your payments.'],
            'fee-structure.php' => ['name' => 'Fee Structure', 'desc' => 'Breakdown of fees.'],
            'pay-fees.php' => ['name' => 'Make Payment', 'desc' => 'Pay your fees online.'],
            'announcements.php' => ['name' => 'Announcements', 'desc' => 'Official school updates.'],
            'helpdesk.php' => ['name' => 'Helpdesk / Support', 'desc' => 'Get help when you need it.'],
            'year.php' => ['name' => 'School Calendar', 'desc' => 'Term dates and holidays.'],
            'learning_program.php' => ['name' => 'Learn', 'desc' => 'Extra learning paths.'],
        ];
        foreach ($pages as $key => $info) {
            if ($file === $key || str_contains($path, str_replace('.php', '', $key))) {
                return $info;
            }
        }
        return ['name' => 'Student Portal', 'desc' => 'Use the sidebar to navigate.'];
    }
}

// -------------------------------------------------------------------
// 3) ENSURE PDO EXISTS
// -------------------------------------------------------------------
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO("mysql:host=" . DBHost . ";dbname=" . DBName . ";charset=" . DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Throwable $e) { error_log('PDO connection failed: ' . $e->getMessage()); }
}

// -------------------------------------------------------------------
// 4) STUDENT DATA
// -------------------------------------------------------------------
if (!isset($student) || empty($student)) {
    if (isset($_SESSION['student_id']) && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_students WHERE id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$student) {
        $student = ['id' => $_SESSION['student_id'] ?? 'unknown', 'fname' => 'Student', 'lname' => '', 'display_image' => null, 'class' => 0];
    }
}
$studentFirstName = safe_first_name($student['fname'] ?? 'Student');
$studentClassId = $student['class'] ?? 0;
$studentId = $student['id'];

// -------------------------------------------------------------------
// 5) AI CHAT HANDLER
// -------------------------------------------------------------------
if (isset($_GET['ai_action']) && $_GET['ai_action'] === 'chat' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');
    if ($message === '') { echo json_encode(['reply' => "Go ahead and ask me something - I am here to help."]); exit; }
    $lang = detect_language_mode($message);
    $msg_lower = strtolower($message);
    $reply = '';

    if (preg_match('/(result|grade|score|marks|performance|exam|matokeo|alama|成绩|分数|考试)/i', $msg_lower)) {
        $results = db_query_all($pdo, "SELECT s.name, r.score, t.name AS term_name FROM tbl_exam_results r JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id JOIN tbl_subjects s ON sc.subject = s.id LEFT JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY r.term DESC LIMIT 6", [$studentId]);
        if (!empty($results)) {
            $reply_en = "Hey " . $studentFirstName . ", here are your latest exam results:\n";
            $reply_sw = "Hujambo " . $studentFirstName . ", hizi ndizo matokeo yako ya mtihani ya hivi karibuni:\n";
            $reply_zh = "Nǐ hǎo " . $studentFirstName . ", zhè shì nǐ zuì xīn de kǎoshì chéngjì:\n";
            foreach ($results as $row) {
                $grade = $row['score'] >= 75 ? 'A (Excellent)' : ($row['score'] >= 65 ? 'B (Very Good)' : ($row['score'] >= 45 ? 'C (Good)' : ($row['score'] >= 30 ? 'D (Satisfactory)' : 'F (Fail)')));
                $reply_en .= "- " . $row['name'] . ": " . $row['score'] . "% - " . $grade . "\n";
                $reply_sw .= "- " . $row['name'] . ": " . $row['score'] . "% - " . $grade . "\n";
                $reply_zh .= "- " . $row['name'] . ": " . $row['score'] . "% - " . $grade . "\n";
            }
            $reply_en .= "\nSee all results under Assessment -> Examination Results.";
            $reply_sw .= "\nOna matokeo yote kwenye Assessment -> Examination Results.";
            $reply_zh .= "\nZài Assessment -> Examination Results zhōng chákàn quánbù chéngjì.";
            $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
        } else {
            $reply = ($lang === 'sw') ? "Hujambo, sikuweza kupata matokeo bado." : (($lang === 'zh') ? "Wǒ hái méi zhǎodào nǐ de chéngjì." : "No exam results found yet. Check the Assessment section later.");
        }
    }
    elseif (preg_match('/(fee|payment|balance|owe|invoice|ada|malipo|salio|school fee|费用|支付|余额|发票)/i', $msg_lower)) {
        $academic_year = date('Y');
        $fee_structure = db_query_one($pdo, "SELECT * FROM tbl_fee_structures WHERE class_id = ? AND academic_year = ? AND active = 1", [$studentClassId, $academic_year]);
        if ($fee_structure) {
            $total_fees = $fee_structure['tuition_fee'] + $fee_structure['boarding_fee'] + $fee_structure['development_fee'] + $fee_structure['other_mandatory_fee'];
            $paid = db_query_one($pdo, "SELECT SUM(paid_amount) as total_paid FROM tbl_fee_payments WHERE student_id = ? AND verification_status = 'verified'", [$studentId]);
            $total_paid = $paid ? $paid['total_paid'] : 0;
            $balance = $total_fees - $total_paid;
            $reply_en = "Your total fees are " . number_format($total_fees, 0) . " TZS. Paid: " . number_format($total_paid, 0) . " TZS. Balance: " . number_format($balance, 0) . " TZS.";
            $reply_sw = "Ada yako jumla ni " . number_format($total_fees, 0) . " TZS. Umelipa: " . number_format($total_paid, 0) . " TZS. Salio: " . number_format($balance, 0) . " TZS.";
            $reply_zh = "Zǒng fèiyòng " . number_format($total_fees, 0) . " TZS. Yǐ fù " . number_format($total_paid, 0) . " TZS. Yú'é " . number_format($balance, 0) . " TZS.";
            if ($balance > 0) {
                $reply_en .= " Pay via CRDB (0150803146400) or NMB (23110083454).";
                $reply_sw .= " Lipa kwa CRDB (0150803146400) au NMB (23110083454).";
                $reply_zh .= " Tōngguò CRDB (0150803146400) huò NMB (23110083454) zhīfù.";
            }
            $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
        } else {
            $reply = ($lang === 'sw') ? "Muundo wa ada bado haujawekwa." : (($lang === 'zh') ? "Fèiyòng jiégòu hái méi shèzhì." : "Fee structure not set up yet.");
        }
    }
    elseif (preg_match('/(subject|teacher|timetable|schedule|som|walimu|ratiba|科目|老师|课程表|时间表)/i', $msg_lower)) {
        $subjects = db_query_all($pdo, "SELECT s.name, CONCAT(st.fname,' ',st.lname) as teacher FROM tbl_subject_combinations sc JOIN tbl_subjects s ON sc.subject=s.id JOIN tbl_staff st ON sc.teacher=st.id WHERE sc.class LIKE ? AND sc.status=1", ['%"'.$studentClassId.'"%']);
        if ($subjects) {
            $reply_en = "Your subjects:\n"; $reply_sw = "Masomo yako:\n"; $reply_zh = "Nǐ de kēmù:\n";
            foreach ($subjects as $s) {
                $reply_en .= "- " . $s['name'] . " (" . $s['teacher'] . ")\n";
                $reply_sw .= "- " . $s['name'] . " (" . $s['teacher'] . ")\n";
                $reply_zh .= "- " . $s['name'] . " (" . $s['teacher'] . ")\n";
            }
            $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
        } else {
            $reply = ($lang === 'sw') ? "Sikupata masomo." : (($lang === 'zh') ? "Wèi zhǎodào kēmù." : "No subjects found.");
        }
    }
    elseif (preg_match('/(calendar|year|holiday|term|日历|校历|假期|学期)/i', $msg_lower)) {
        $reply_en = "Find the academic calendar in the sidebar under School Calendar.";
        $reply_sw = "Kalenda ya shule iko kwenye sidebar chini ya School Calendar.";
        $reply_zh = "Zài cèbiān lán de School Calendar zhōng zhǎodào xuéxiào rìlì.";
        $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
    }
    elseif (preg_match('/(announcement|notice|news|tangazo|公告|通知)/i', $msg_lower)) {
        $ann = db_query_all($pdo, "SELECT title, create_date FROM tbl_announcements WHERE level IN (1,2) ORDER BY create_date DESC LIMIT 3");
        if ($ann) {
            $reply_en = "Latest announcements:\n"; $reply_sw = "Matangazo ya hivi karibuni:\n"; $reply_zh = "Zuì xīn gōnggào:\n";
            foreach ($ann as $a) {
                $reply_en .= "- " . $a['title'] . " (" . date('M j', strtotime($a['create_date'])) . ")\n";
                $reply_sw .= "- " . $a['title'] . " (" . date('j M', strtotime($a['create_date'])) . ")\n";
                $reply_zh .= "- " . $a['title'] . " (" . date('n/j', strtotime($a['create_date'])) . ")\n";
            }
            $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
        } else { $reply = "No announcements right now."; }
    }
    elseif (preg_match('/(where|how to find|navigate|menu|sidebar|在哪里|导航|找)/i', $msg_lower)) {
        $navHelp = get_navigation_help($message);
        $reply = $navHelp ?: "Tell me what you're looking for and I'll point you to it.";
    }
    elseif (preg_match('/(hello|hi|hey|habari|jambo|你好|您好)/i', $msg_lower)) {
        $reply_en = "Hi " . $studentFirstName . ". Ask me about your results, fees, subjects, or the calendar.";
        $reply_sw = "Habari " . $studentFirstName . ". Niulize kuhusu matokeo, ada, masomo, au kalenda.";
        $reply_zh = "Nǐ hǎo " . $studentFirstName . ". Wèn wǒ chéngjì, fèiyòng, kēmù huò rìlì.";
        $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
    }
    else {
        $reply_en = "Try: results, fees, subjects, calendar, timetable, or announcements.";
        $reply_sw = "Jaribu: matokeo, ada, masomo, kalenda, ratiba, au matangazo.";
        $reply_zh = "Shìzhe: chéngjì, fèiyòng, kēmù, rìlì, kèchéng biǎo, huò gōnggào.";
        $reply = ($lang === 'sw') ? $reply_sw : (($lang === 'zh') ? $reply_zh : $reply_en);
    }

    $reply = strip_tags($reply);
    $reply = preg_replace('/[ \t]+/', ' ', $reply);
    echo json_encode(['reply' => trim($reply)]);
    exit;
}

// -------------------------------------------------------------------
// 6) SCHOOL INFO, NOTIFICATIONS, HEADER DATA
// -------------------------------------------------------------------
if (!isset($school_name) || empty($school_name)) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $pdo = new PDO("mysql:host=" . DBHost . ";dbname=" . DBName . ";charset=" . DBCharset, DBUser, DBPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $stmt = $pdo->prepare("SELECT name, logo FROM tbl_school LIMIT 1");
        $stmt->execute();
        $school = $stmt->fetch();
        $school_name = $school['name'] ?? 'SRMS';
        $school_logo = $school['logo'] ?? 'default-logo.png';
    } catch (Throwable $e) {
        $school_name = defined('WBName') ? WBName : 'SRMS';
        $school_logo = defined('WBLogo') ? WBLogo : 'logo.png';
    }
} else {
    $school_logo = defined('WBLogo') ? WBLogo : 'logo.png';
}

$favicon_path = 'images/logo/' . $school_logo;
$favicon_file = __DIR__ . '/../' . $favicon_path;
if (!file_exists($favicon_file)) $favicon_path = 'images/icon.ico';
$favicon_type = pathinfo($favicon_path, PATHINFO_EXTENSION) === 'ico' ? 'image/x-icon' : 'image/png';

$notification_count = 0;
$latest_announcements = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE level IN (1,2)");
        $stmt->execute();
        $notification_count = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT id, title, announcement, create_date FROM tbl_announcements WHERE level IN (1,2) ORDER BY create_date DESC LIMIT 5");
        $stmt->execute();
        $latest_announcements = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

$header_latest_exam = null;
if (isset($pdo, $student['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT t.name FROM tbl_exam_results r LEFT JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY t.id DESC LIMIT 1");
        $stmt->execute([$student['id']]);
        $header_latest_exam = $stmt->fetchColumn();
    } catch (Throwable $e) {}
}

$saved_theme = $_COOKIE['site_theme'] ?? 'theme-26';
$pageInfo = guess_page_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" type="<?php echo $favicon_type; ?>" href="<?php echo $favicon_path; ?>">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* ========== GLOBAL & THEMES ========== */
        body { transition: background 0.3s ease; min-height: 100vh; margin: 0; padding: 0; overflow-x: hidden; position: relative; }
        body.theme-1 { background: linear-gradient(135deg, rgba(0,0,0,0.85), rgba(0,0,0,0.7)), url('https://csscrafter.com/wp-content/uploads/2023/08/mysticforest.jpeg'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-2 { background: #0a0a2a; }
        body.theme-3 { background: linear-gradient(135deg, #0b2b3b, #1a4a6f); }
        body.theme-4 { background: linear-gradient(135deg, #1a2980, #26d0ce); }
        body.theme-5 { background: #0a0a0a; }
        body.theme-6 { background: #0f172a; }
        body.theme-7 { background: linear-gradient(to bottom, #0b0b2b, #1b2735 70%, #090a0f); }
        body.theme-8 { background: linear-gradient(315deg, #65005e, #3c84ce, #30eee2, #ff1919); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        body.theme-9 { background: url('https://i.pinimg.com/originals/44/6e/3b/446e3b79395a287ca32f7977dd83b290.jpg'); background-size: cover; background-attachment: fixed; }
        body.theme-10 { background: #0b0b2b; }
        body.theme-11 { background: radial-gradient(#333, #000); }
        body.theme-12 { background: linear-gradient(180deg, #04fafd, 5%, #119dff, 50%, #030423); }
        body.theme-13 { background: linear-gradient(315deg, rgba(101,0,94,1) 3%, rgba(60,132,206,1) 38%, rgba(48,238,226,1) 68%, rgba(255,25,25,1) 98%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        body.theme-14 { background: black; }
        body.theme-15 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-16 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1600&q=80'); background-size: cover; background-position: center 30%; background-attachment: fixed; }
        body.theme-17 { background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-18 { background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1522383225653-ed111181a6b1?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-19 { background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-20 { background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.35)), url('https://images.unsplash.com/photo-1503788311183-fa3bf9c4f32b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-21 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1511497584788-876760111969?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-22 { background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.55)), url('https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-23 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-24 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-25 { background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1500673922987-e212871fec22?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-26 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.45)), url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-27 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-28 { background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.45)), url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-29 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1501854140801-50d01698950b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-30 { background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.55)), url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        @keyframes gradientShift { 0% { background-position: 0% 0%; } 50% { background-position: 100% 100%; } 100% { background-position: 0% 0%; } }

        /* ========== HEADER ========== */
        .app-header {
            position: sticky; top: 0; z-index: 1000;
            background: rgba(10,10,20,0.55);
            backdrop-filter: blur(18px);
            padding: 0.5rem 2rem; height: 75px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,215,0,0.3);
            transition: all 0.3s;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .app-header.scrolled {
            background: rgba(5,5,15,0.85);
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(90deg, #38bdf8, #3b82f6, #60a5fa) 1;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .school-name { font-size: 1.2rem; font-weight: 700; background: linear-gradient(135deg, #fff, #60a5fa); background-clip: text; -webkit-background-clip: text; color: transparent; letter-spacing: 0.5px; }

        /* ========== ANIMATED 3D DESK LAMP ========== */
        .lamp-toggle {
            position: relative;
            width: 60px; height: 60px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; text-decoration: none;
            background: transparent; border: none; padding: 0;
            animation: lampButtonDrop 1.6s cubic-bezier(0.34, 1.45, 0.64, 1) both;
            transform-origin: center top;
        }
        @keyframes lampButtonDrop {
            0%   { transform: translateY(-120px) scale(0.6); opacity: 0; }
            55%  { transform: translateY(0) scale(1.06); opacity: 1; }
            72%  { transform: translateY(-10px) scale(1); }
            86%  { transform: translateY(0) scale(1); }
            93%  { transform: translateY(-3px) scale(1); }
            100% { transform: translateY(0) scale(1); }
        }
        .lamp-svg {
            width: 48px; height: 56px; display: block; overflow: visible;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.55)) drop-shadow(0 0 18px rgba(56,189,248,0.35));
            transition: filter 0.35s ease;
        }
        .lamp-toggle:hover .lamp-svg {
            filter: drop-shadow(0 8px 18px rgba(0,0,0,0.6)) drop-shadow(0 0 26px rgba(250,204,21,0.75)) drop-shadow(0 0 40px rgba(250,204,21,0.35));
        }
        .lamp-swing { transform-origin: 30px 6px; animation: lampIdleSway 5s ease-in-out infinite; }
        .lamp-toggle:hover .lamp-swing { animation-duration: 1.6s; animation-timing-function: cubic-bezier(0.45, 0.05, 0.55, 0.95); }
        @keyframes lampIdleSway { 0%, 100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg); } }
        .lamp-glow { animation: lampGlowPulse 2.6s ease-in-out infinite; transform-origin: 30px 38px; }
        @keyframes lampGlowPulse { 0%, 100% { opacity: 0.55; transform: scale(0.94); } 50% { opacity: 1; transform: scale(1.08); } }
        .lamp-bulb-core { animation: bulbFlicker 4s ease-in-out infinite; }
        @keyframes bulbFlicker { 0% { fill: #fef3c7; } 20% { fill: #fde68a; } 45% { fill: #fef9e7; } 70% { fill: #fbbf24; } 100% { fill: #fef3c7; } }
        .lamp-beam { transform-origin: 30px 34px; animation: beamFade 5s ease-in-out infinite; }
        @keyframes beamFade { 0%, 100% { opacity: 0.18; } 50% { opacity: 0.55; } }
        body:not(.sidebar-hidden) .lamp-svg {
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.55)) drop-shadow(0 0 24px rgba(250,204,21,0.85)) drop-shadow(0 0 44px rgba(250,204,21,0.45));
        }
        .lamp-toggle:active .lamp-svg { transform: scale(0.94); transition: transform 0.1s ease; }

        .header-center { text-align: center; flex: 1; color: white; pointer-events: none; padding: 0 10px; }
        .greeting-text { font-size: 1rem; font-weight: 500; letter-spacing: 0.5px; }
        .greeting-name { font-weight: 700; color: #60a5fa; }
        .latest-exam { font-size: 0.75rem; opacity: 0.85; margin-top: 2px; }
        .app-nav { display: flex; align-items: center; gap: 12px; margin: 0; padding: 0; list-style: none; }
        .app-nav__item { padding: 8px 16px; border-radius: 40px; background: rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px; color: white; cursor: pointer; text-decoration: none; backdrop-filter: blur(8px); transition: 0.25s; font-weight: 500; }
        .app-nav__item:hover { background: rgba(96,165,250,0.2); transform: translateY(-2px); color: #60a5fa; }
        .theme-label { font-size: 0.9rem; }

        .theme-menu, .notification-dropdown-menu, .user-menu { max-height: 85vh; overflow-y: auto; background: rgba(10,10,20,0.98); backdrop-filter: blur(25px); border: 1px solid rgba(96,165,250,0.3); border-radius: 24px; margin-top: 12px; box-shadow: 0 20px 35px -12px rgba(0,0,0,0.5); }
        .dropdown-item, .dropdown-header, .dropdown-header .badge, .user-info strong, .user-info small { color: white !important; }
        .dropdown-item:hover { background: rgba(96,165,250,0.2) !important; color: #60a5fa !important; }
        .dropdown-divider { border-top-color: rgba(255,255,255,0.1); }
        .text-muted { color: rgba(255,255,255,0.6) !important; }
        .theme-menu { width: 560px; }
        .theme-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; padding: 20px; }
        .theme-option { cursor: pointer; border-radius: 16px; overflow: hidden; background: rgba(255,255,255,0.05); transition: all 0.3s; border: 1px solid transparent; }
        .theme-option:hover { transform: translateY(-4px); background: rgba(255,255,255,0.12); border-color: rgba(96,165,250,0.4); }
        .theme-option.active { border: 2px solid #60a5fa; background: rgba(96,165,250,0.15); }
        .theme-preview { height: 110px; position: relative; overflow: hidden; background-size: cover; background-position: center; }
        .preview-1 { background: linear-gradient(135deg,#1a3c2c,#0d2818); }
        .preview-2 { background: #0a0a2a; }
        .preview-3 { background: linear-gradient(135deg,#0b2b3b,#1a4a6f); }
        .preview-4 { background: linear-gradient(135deg,#1a2980,#26d0ce); }
        .preview-5 { background: #0a0a0a; }
        .preview-6 { background: #0f172a; }
        .preview-7 { background: linear-gradient(to bottom, #0b0b2b, #1b2735); }
        .preview-8 { background: linear-gradient(315deg,#65005e,#3c84ce,#30eee2,#ff1919); }
        .preview-9 { background: url('https://i.pinimg.com/originals/44/6e/3b/446e3b79395a287ca32f7977dd83b290.jpg') center/cover; }
        .preview-10 { background: #0b0b2b; }
        .preview-11 { background: radial-gradient(#333,#000); }
        .preview-12 { background: linear-gradient(180deg,#04fafd,#119dff,#030423); }
        .preview-13 { background: linear-gradient(315deg,#65005e,#3c84ce,#30eee2,#ff1919); }
        .preview-14 { background: black; }
        .preview-15 { background: url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=400&q=80') center/cover; }
        .preview-16 { background: url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=80') center/cover; }
        .preview-17 { background: url('https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?w=400&q=80') center/cover; }
        .preview-18 { background: url('https://images.unsplash.com/photo-1522383225653-ed111181a6b1?w=400&q=80') center/cover; }
        .preview-19 { background: url('https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?w=400&q=80') center/cover; }
        .preview-20 { background: url('https://images.unsplash.com/photo-1503788311183-fa3bf9c4f32b?w=400&q=80') center/cover; }
        .preview-21 { background: url('https://images.unsplash.com/photo-1511497584788-876760111969?w=400&q=80') center/cover; }
        .preview-22 { background: url('https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80') center/cover; }
        .preview-23 { background: url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=400&q=80') center/cover; }
        .preview-24 { background: url('https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=400&q=80') center/cover; }
        .preview-25 { background: url('https://images.unsplash.com/photo-1500673922987-e212871fec22?w=400&q=80') center/cover; }
        .preview-26 { background: url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=400&q=80') center/cover; }
        .preview-27 { background: url('https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=400&q=80') center/cover; }
        .preview-28 { background: url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=400&q=80') center/cover; }
        .preview-29 { background: url('https://images.unsplash.com/photo-1501854140801-50d01698950b?w=400&q=80') center/cover; }
        .preview-30 { background: url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=400&q=80') center/cover; }
        .theme-info { padding: 8px; text-align: center; color: white; }
        .theme-info strong { font-size: 0.85rem; display: block; }
        .theme-info small { font-size: 0.65rem; opacity: 0.7; }

        .bell-wrapper { position: relative; cursor: pointer; }
        .notification-bell i { color: #60a5fa; font-size: 1.3rem; transition: transform 0.2s; display: inline-block; }
        .notification-bell:hover i { animation: bellRing 0.6s ease both; }
        .shake-bell { animation: shakeEffect 0.5s ease-in-out !important; }
        @keyframes shakeEffect { 0% { transform: translate(0,0) rotate(0deg); } 20% { transform: translate(-2px,0) rotate(-10deg); } 40% { transform: translate(2px,0) rotate(10deg); } 60% { transform: translate(-1px,0) rotate(-5deg); } 80% { transform: translate(1px,0) rotate(5deg); } 100% { transform: translate(0,0) rotate(0deg); } }
        @keyframes bellRing { 0% { transform: rotate(0); } 20% { transform: rotate(15deg); } 40% { transform: rotate(-12deg); } 60% { transform: rotate(8deg); } 80% { transform: rotate(-4deg); } 100% { transform: rotate(0); } }
        .notification-badge { position: absolute; top: -8px; right: -10px; background: #ef476f; color: white; border-radius: 20px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 4px rgba(0,0,0,0.3); }
        .notification-badge.pulse { animation: pulseBadge 1s infinite; }
        @keyframes pulseBadge { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,71,111,0.7); } 70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(239,71,111,0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,71,111,0); } }

        .user-avatar { width: 38px; height: 38px; border-radius: 50%; overflow: hidden; background: rgba(255,255,255,0.2); border: 2px solid #60a5fa; position: relative; display: flex; align-items: center; justify-content: center; }
        .user-avatar:hover { transform: scale(1.05); border-color: white; }
        .user-avatar-placeholder i { font-size: 1.5rem; color: #60a5fa; }
        .user-avatar-img { width: 100%; height: 100%; object-fit: cover; }
        .online-dot { position: absolute; bottom: 2px; right: 2px; width: 10px; height: 10px; background: #4ade80; border-radius: 50%; border: 1px solid white; }
        .user-chevron { color: #60a5fa; transition: transform 0.3s; font-size: 0.9rem; margin-left: 4px; }
        .user-dropdown:hover .user-chevron { transform: rotate(180deg); }

        /* ========== SCROLL TO TOP BUTTON ========== */
        .scroll-top-btn {
            position: fixed;
            bottom: max(18px, env(safe-area-inset-bottom, 18px));
            right: max(18px, env(safe-area-inset-right, 18px));
            width: clamp(42px, 6vw, 52px);
            height: clamp(42px, 6vw, 52px);
            background: linear-gradient(145deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: clamp(1.1rem, 2.4vw, 1.5rem);
            cursor: pointer;
            box-shadow: 0 8px 20px -6px rgba(37,99,235,.55), 0 0 0 1px rgba(255,255,255,0.15) inset;
            z-index: 999;
            opacity: 0; visibility: hidden;
            transition: opacity .3s ease, visibility .3s ease, transform .25s ease, box-shadow .25s ease;
            border: none;
            padding: 0;
            line-height: 1;
            -webkit-tap-highlight-color: transparent;
        }
        .scroll-top-btn.show { opacity: 1; visibility: visible; }
        .scroll-top-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 26px -8px rgba(37,99,235,.7), 0 0 0 1px rgba(255,255,255,0.25) inset;
        }
        .scroll-top-btn:active { transform: translateY(0) scale(.94); }
        .scroll-top-btn i { font-size: 1.1em; font-weight: 700; pointer-events: none; }
        .scroll-top-btn:focus-visible { outline: 3px solid rgba(59,130,246,0.6); outline-offset: 3px; }

        @media (max-width: 768px) {
            .scroll-top-btn { bottom: max(14px, env(safe-area-inset-bottom, 14px)); right: max(14px, env(safe-area-inset-right, 14px)); }
        }

        /* ========== ROBOT ANCHOR ========== */
        .robot-thought-anchor {
            position: fixed;
            top: 92px; right: 22px;
            z-index: 1002;
            width: 74px; height: 74px;
            pointer-events: none;
            transition: right 0.6s cubic-bezier(0.34, 1.3, 0.64, 1);
        }
        .robot-head {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: auto; cursor: pointer; z-index: 3;
            animation: robotFloat 3.6s ease-in-out infinite;
            filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 24px rgba(56,189,248,0.55)) drop-shadow(0 0 60px rgba(59,130,246,0.35));
            transition: filter 0.4s ease, transform 0.4s ease;
        }
        @keyframes robotFloat { 0%, 100% { transform: translateY(0) rotate(-1deg); } 50% { transform: translateY(-7px) rotate(1deg); } }
        .robot-head:hover { animation-play-state: paused; }
        .robot-head svg { width: 100%; height: 100%; display: block; overflow: visible; }

        /* ---- STOP BUTTON ---- */
        .robot-stop-btn {
            position: absolute;
            top: 50%; left: 50%;
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #ffffff; font-size: 1.05rem;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            border: 2px solid rgba(255,255,255,0.7);
            box-shadow: 0 4px 14px rgba(239,68,68,0.6), 0 0 16px rgba(239,68,68,0.45);
            transform: translate(-50%, -50%) scale(0.15);
            opacity: 0; pointer-events: none; z-index: 1;
            transition: top 1.4s cubic-bezier(0.22, 0.75, 0.35, 1), transform 1.4s cubic-bezier(0.22, 0.75, 0.35, 1), opacity 0.9s ease 0.25s;
            user-select: none;
        }
        .robot-stop-btn.show {
            top: calc(50% + 58px);
            transform: translate(-50%, -50%) scale(1);
            opacity: 1; pointer-events: auto; z-index: 4;
            animation: stopPulse 1.6s ease-in-out infinite 1.5s;
        }
        .robot-stop-btn:hover { transform: translate(-50%, -50%) scale(1.12); }
        .robot-stop-btn:active { transform: translate(-50%, -50%) scale(0.94); }
        @keyframes stopPulse { 0%, 100% { box-shadow: 0 4px 14px rgba(239,68,68,0.6), 0 0 16px rgba(239,68,68,0.45); } 50% { box-shadow: 0 4px 22px rgba(239,68,68,0.95), 0 0 34px rgba(239,68,68,0.75); } }

        /* Eye / mouth animation targets */
        .rh-eye { transform-box: fill-box; transform-origin: center; animation: rhBlink 5s ease-in-out infinite; }
        .rh-eye.rh-right { animation-delay: 0.04s; }
        .rh-pupil { transform-box: fill-box; transform-origin: center; transition: transform 0.12s ease-out, fill 0.3s ease; transform: translate(var(--eye-x, 0px), var(--eye-y, 0px)); }
        .rh-mouth { transition: all 0.35s ease; transform-box: fill-box; transform-origin: center; stroke: #7dd3fc; }
        .rh-eyebrow { transform-box: fill-box; transform-origin: center; transition: transform 0.3s ease; }
        @keyframes rhBlink { 0%, 90%, 100% { transform: scaleY(1); } 93% { transform: scaleY(0.08); } }

        /* --- Base emotions --- */
        .robot-thought-anchor.emo-happy .rh-eye { transform: scaleY(0.45); }
        .robot-thought-anchor.emo-happy .rh-mouth { d: path("M 40 68 Q 50 76 60 68"); }
        .robot-thought-anchor.emo-thinking .rh-pupil { transform: translate(var(--eye-x, 0px), calc(var(--eye-y, 0px) - 2px)); }
        .robot-thought-anchor.emo-thinking .rh-eyebrow-l { transform: translateY(-2px) rotate(-4deg); }
        .robot-thought-anchor.emo-thinking .rh-eyebrow-r { transform: translateY(-2px) rotate(4deg); }
        .robot-thought-anchor.emo-surprised .rh-eye { transform: scale(1.28); }
        .robot-thought-anchor.emo-surprised .rh-mouth { d: path("M 50 68 m -4 0 a 4 4 0 1 0 8 0 a 4 4 0 1 0 -8 0"); stroke-width: 2.4; stroke: #fbbf24; }
        .robot-thought-anchor.emo-surprised .rh-eyebrow-l { transform: translateY(-4px) rotate(-10deg); }
        .robot-thought-anchor.emo-surprised .rh-eyebrow-r { transform: translateY(-4px) rotate(10deg); }
        .robot-thought-anchor.emo-proud .robot-head { filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 32px rgba(125,211,252,0.85)) drop-shadow(0 0 80px rgba(59,130,246,0.55)); }
        .robot-thought-anchor.emo-proud .rh-eye { transform: scaleY(0.6); }
        .robot-thought-anchor.emo-proud .rh-mouth { d: path("M 40 66 Q 50 74 60 66"); }
        .robot-thought-anchor.emo-curious .robot-head { transform: rotate(-6deg); animation-play-state: paused; }
        .robot-thought-anchor.emo-curious .rh-pupil { transform: translate(calc(var(--eye-x, 0px) + 2px), calc(var(--eye-y, 0px) - 1px)); }
        .robot-thought-anchor.emo-celebrate .robot-head { animation: robotCelebrate 0.9s ease-in-out infinite; }
        @keyframes robotCelebrate { 0%, 100% { transform: translateY(0) rotate(0deg); } 25% { transform: translateY(-10px) rotate(-4deg); } 75% { transform: translateY(-10px) rotate(4deg); } }
        .robot-thought-anchor.emo-celebrate .rh-eye { transform: scaleY(0.3); }
        .robot-thought-anchor.emo-celebrate .rh-mouth { d: path("M 38 66 Q 50 78 62 66"); }
        .robot-thought-anchor.emo-love .robot-head { animation: robotBounce 0.85s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 32px rgba(239,71,111,0.95)) drop-shadow(0 0 72px rgba(244,63,94,0.65)); }
        @keyframes robotBounce { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-9px) scale(1.04); } }
        .robot-thought-anchor.emo-love .rh-eye { transform: scaleY(0.5); }
        .robot-thought-anchor.emo-love .rh-mouth { d: path("M 40 66 Q 50 78 60 66"); }
        .robot-thought-anchor.emo-love .rh-pupil { fill: #ef476f; }
        .robot-thought-anchor.emo-kiss .robot-head { animation: robotBounce 0.75s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 36px rgba(244,114,182,0.95)) drop-shadow(0 0 78px rgba(236,72,153,0.7)); }
        .robot-thought-anchor.emo-kiss .rh-eye { transform: scaleY(0.5); }
        .robot-thought-anchor.emo-kiss .rh-mouth { d: path("M 46 67 Q 50 65 54 67 Q 50 71 46 67"); stroke-width: 2.4; stroke: #f472b6; }
        .robot-thought-anchor.emo-kiss .rh-pupil { fill: #ec4899; }
        .robot-thought-anchor.emo-kiss .rh-eyebrow-l { transform: translateY(-1px) rotate(-4deg); }
        .robot-thought-anchor.emo-kiss .rh-eyebrow-r { transform: translateY(-1px) rotate(4deg); }
        .robot-thought-anchor.emo-furious .robot-head { animation: robotShake 0.14s linear infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 32px rgba(239,68,68,0.95)) drop-shadow(0 0 70px rgba(220,38,38,0.65)); }
        @keyframes robotShake { 0% { transform: translate(0, 0) rotate(0deg); } 20% { transform: translate(-2px, 0) rotate(-1deg); } 40% { transform: translate(2px, 0) rotate(1deg); } 60% { transform: translate(-1.5px, 0) rotate(-0.5deg); } 80% { transform: translate(1.5px, 0) rotate(0.5deg); } 100% { transform: translate(0, 0) rotate(0deg); } }
        .robot-thought-anchor.emo-furious .rh-eye { transform: scaleY(1.15); }
        .robot-thought-anchor.emo-furious .rh-mouth { d: path("M 38 72 Q 50 64 62 72"); }
        .robot-thought-anchor.emo-furious .rh-eyebrow-l { transform: translateY(3px) rotate(14deg); }
        .robot-thought-anchor.emo-furious .rh-eyebrow-r { transform: translateY(3px) rotate(-14deg); }
        .robot-thought-anchor.emo-furious .rh-pupil { fill: #dc2626; }
        .robot-thought-anchor.emo-worried .robot-head { animation: robotFloat 4.5s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 26px rgba(251,191,36,0.7)) drop-shadow(0 0 60px rgba(217,119,6,0.45)); }
        .robot-thought-anchor.emo-worried .rh-eye { transform: scaleY(0.9); }
        .robot-thought-anchor.emo-worried .rh-eyebrow-l { transform: translateY(-1px) rotate(-10deg); }
        .robot-thought-anchor.emo-worried .rh-eyebrow-r { transform: translateY(-1px) rotate(10deg); }
        .robot-thought-anchor.emo-worried .rh-mouth { d: path("M 42 70 Q 50 66 58 70"); }
        .robot-thought-anchor.emo-worried .rh-pupil { fill: #d97706; }
        .robot-thought-anchor.emo-disappointed .robot-head { animation: robotFloat 5.5s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 24px rgba(148,163,184,0.75)) drop-shadow(0 0 56px rgba(100,116,139,0.5)); }
        .robot-thought-anchor.emo-disappointed .rh-eye { transform: scaleY(0.82); }
        .robot-thought-anchor.emo-disappointed .rh-eyebrow-l { transform: translateY(-2px) rotate(-12deg); }
        .robot-thought-anchor.emo-disappointed .rh-eyebrow-r { transform: translateY(-2px) rotate(12deg); }
        .robot-thought-anchor.emo-disappointed .rh-mouth { d: path("M 42 72 Q 50 66 58 72"); stroke-width: 2.1; stroke: #94a3b8; }
        .robot-thought-anchor.emo-disappointed .rh-pupil { fill: #64748b; }
        .robot-thought-anchor.emo-encouraged .robot-head { animation: robotFloat 3.8s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 30px rgba(74,222,128,0.85)) drop-shadow(0 0 70px rgba(34,197,94,0.55)); }
        .robot-thought-anchor.emo-encouraged .rh-eye { transform: scaleY(0.68); }
        .robot-thought-anchor.emo-encouraged .rh-eyebrow-l { transform: translateY(-1px) rotate(-4deg); }
        .robot-thought-anchor.emo-encouraged .rh-eyebrow-r { transform: translateY(-1px) rotate(4deg); }
        .robot-thought-anchor.emo-encouraged .rh-mouth { d: path("M 42 68 Q 50 75 58 68"); }
        .robot-thought-anchor.emo-encouraged .rh-pupil { fill: #16a34a; }

        /* --- SAD (used for logout) --- */
        .robot-thought-anchor.emo-sad .robot-head {
            animation: robotSadFloat 5s ease-in-out infinite;
            filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 24px rgba(100,116,139,0.75)) drop-shadow(0 0 56px rgba(71,85,105,0.5));
        }
        @keyframes robotSadFloat {
            0%, 100% { transform: translateY(2px) rotate(-2deg); }
            50%      { transform: translateY(-3px) rotate(2deg); }
        }
        .robot-thought-anchor.emo-sad .rh-eye { transform: scaleY(0.75); }
        .robot-thought-anchor.emo-sad .rh-eyebrow-l { transform: translateY(-3px) rotate(-14deg); }
        .robot-thought-anchor.emo-sad .rh-eyebrow-r { transform: translateY(-3px) rotate(14deg); }
        .robot-thought-anchor.emo-sad .rh-mouth { d: path("M 42 74 Q 50 66 58 74"); stroke-width: 2.2; stroke: #94a3b8; }
        .robot-thought-anchor.emo-sad .rh-pupil { fill: #64748b; }
        /* Small tear drop */
        .robot-thought-anchor.emo-sad .robot-head::after {
            content: "";
            position: absolute;
            top: 46%; left: 34%;
            width: 5px; height: 8px;
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            background: linear-gradient(to bottom, #bae6fd, #38bdf8);
            box-shadow: 0 0 8px rgba(56,189,248,0.9);
            animation: teardrop 2.6s ease-in infinite;
            z-index: 5;
        }
        @keyframes teardrop {
            0%   { opacity: 0; transform: translateY(0) scale(0.8); }
            20%  { opacity: 1; transform: translateY(0) scale(1); }
            60%  { opacity: 1; transform: translateY(14px) scale(1); }
            100% { opacity: 0; transform: translateY(28px) scale(0.6); }
        }

        /* ---- Activity emotions ---- */
        .robot-thought-anchor.emo-sneeze .robot-head { animation: robotSneeze 0.95s ease-in-out; }
        @keyframes robotSneeze {
            0%   { transform: translate(0, 0) rotate(0deg) scale(1); }
            15%  { transform: translate(0, -3px) rotate(-4deg) scale(0.98); }
            30%  { transform: translate(0, 1px) rotate(5deg) scale(1.02); }
            40%  { transform: translate(-5px, 2px) rotate(-14deg) scale(1.05); }
            55%  { transform: translate(5px, -2px) rotate(12deg) scale(0.98); }
            70%  { transform: translate(-3px, 0) rotate(-6deg) scale(1.02); }
            85%  { transform: translate(2px, 0) rotate(3deg); }
            100% { transform: translate(0, 0) rotate(0deg) scale(1); }
        }
        .robot-thought-anchor.emo-sneeze .rh-eye { transform: scaleY(0.1); }
        .robot-thought-anchor.emo-sneeze .rh-mouth { d: path("M 42 68 Q 50 78 58 68"); stroke: #fbbf24; stroke-width: 2.6; }
        .robot-thought-anchor.emo-sleep .robot-head { animation: robotSleep 5s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 22px rgba(148,163,184,0.7)) drop-shadow(0 0 52px rgba(100,116,139,0.45)); }
        @keyframes robotSleep { 0%, 100% { transform: translateY(0) rotate(-5deg); } 50% { transform: translateY(-3px) rotate(-5deg); } }
        .robot-thought-anchor.emo-sleep .rh-eye { transform: scaleY(0.05); }
        .robot-thought-anchor.emo-sleep .rh-eyebrow-l { transform: translateY(1px) rotate(-2deg); }
        .robot-thought-anchor.emo-sleep .rh-eyebrow-r { transform: translateY(1px) rotate(2deg); }
        .robot-thought-anchor.emo-sleep .rh-mouth { d: path("M 46 68 Q 50 71 54 68"); stroke: #94a3b8; }
        .robot-thought-anchor.emo-chore .robot-head { animation: robotChore 1.15s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 26px rgba(251,191,36,0.75)) drop-shadow(0 0 62px rgba(217,119,6,0.5)); }
        @keyframes robotChore { 0%, 100% { transform: translateX(-4px) rotate(-7deg); } 50% { transform: translateX(4px) rotate(7deg); } }
        .robot-thought-anchor.emo-chore .rh-eye { transform: scaleY(0.5); }
        .robot-thought-anchor.emo-chore .rh-mouth { d: path("M 42 68 L 58 68"); stroke: #fbbf24; }
        .robot-thought-anchor.emo-sing .robot-head { animation: robotSing 1.35s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 34px rgba(167,139,250,0.9)) drop-shadow(0 0 72px rgba(139,92,246,0.6)); }
        @keyframes robotSing { 0% { transform: translateY(0) rotate(-2deg); } 25% { transform: translateY(-5px) rotate(2deg); } 50% { transform: translateY(0) rotate(-2deg); } 75% { transform: translateY(-5px) rotate(2deg); } 100% { transform: translateY(0) rotate(-2deg); } }
        .robot-thought-anchor.emo-sing .rh-eye { transform: scaleY(0.4); }
        .robot-thought-anchor.emo-sing .rh-mouth { animation: singingMouth 0.55s ease-in-out infinite alternate; fill: #0b1220; stroke: #a78bfa; stroke-width: 2.4; }
        @keyframes singingMouth { 0% { d: path("M 44 68 m -3 0 a 3 3 0 1 0 6 0 a 3 3 0 1 0 -6 0"); } 100% { d: path("M 44 68 m -5 0 a 5 4 0 1 0 10 0 a 5 4 0 1 0 -10 0"); } }
        .robot-thought-anchor.emo-sing .rh-pupil { fill: #a78bfa; }
        .robot-thought-anchor.emo-typing .robot-head { animation: robotTyping 0.36s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 26px rgba(125,211,252,0.85)) drop-shadow(0 0 62px rgba(59,130,246,0.55)); }
        @keyframes robotTyping { 0%, 100% { transform: translateY(0) rotate(-1deg); } 50% { transform: translateY(-3px) rotate(1deg); } }
        .robot-thought-anchor.emo-typing .rh-eye { transform: scaleY(0.55); }
        .robot-thought-anchor.emo-typing .rh-mouth { d: path("M 44 70 L 56 70"); stroke: #7dd3fc; }
        .robot-thought-anchor.robot-running { transition: right 3.5s cubic-bezier(0.5, 0.05, 0.45, 0.95); }
        .robot-thought-anchor.robot-running .robot-head { animation: robotRun 0.28s linear infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 26px rgba(96,165,250,0.9)) drop-shadow(0 0 60px rgba(59,130,246,0.55)); }
        @keyframes robotRun { 0% { transform: translateY(0) rotate(-5deg); } 50% { transform: translateY(-7px) rotate(5deg); } 100% { transform: translateY(0) rotate(-5deg); } }
        .robot-thought-anchor.robot-running .rh-eye { transform: scaleY(1.15); }
        .robot-thought-anchor.robot-running .rh-mouth { d: path("M 42 70 Q 50 64 58 70"); stroke: #fbbf24; stroke-width: 2.2; }
        .robot-thought-anchor.robot-shine .robot-head { animation: robotShine 1.4s ease-in-out infinite; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.55)) drop-shadow(0 0 38px rgba(74,222,128,0.95)) drop-shadow(0 0 84px rgba(34,197,94,0.7)); }
        @keyframes robotShine { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-4px) scale(1.03); } }
        .robot-thought-anchor.robot-shine .rh-eye { transform: scaleY(0.4); }
        .robot-thought-anchor.robot-shine .rh-mouth { d: path("M 40 66 Q 50 78 60 66"); stroke: #7dd3fc; }

        /* =========================================================
           3D CLOUD THOUGHT BUBBLE – REALISTIC LAYERED
           ========================================================= */
        .robot-thought-bubble {
            position: fixed;
            max-width: 340px;
            min-width: 220px;
            padding: 0;
            background: transparent;
            border: none;
            box-shadow: none;
            opacity: 0;
            transform: scale(0.85) translateY(10px);
            transition: left 0.7s cubic-bezier(0.34,1.3,0.64,1),
                        top 0.7s cubic-bezier(0.34,1.3,0.64,1),
                        opacity 0.45s ease,
                        transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
            pointer-events: auto;
            z-index: 1003;
            will-change: left, top;
            overflow: visible;
        }
        .robot-thought-bubble.show { opacity: 1; transform: scale(1) translateY(0); }
        .robot-thought-bubble.no-transition {
            transition: opacity 0.4s ease, transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
        }

        /* Outer ripple halo – soft rippled ring beyond the cloud */
        .robot-thought-bubble .cloud-ripple {
            position: absolute;
            inset: -16px;
            border-radius: 50px;
            background: radial-gradient(ellipse at 50% 50%, rgba(255,255,255,0.35) 0%, rgba(200,225,255,0.22) 45%, rgba(150,190,240,0) 75%);
            filter: url(#cloudRippleSoft);
            z-index: 0;
            pointer-events: none;
            transition: background 0.6s ease;
        }

        /* Main 3D cloud body – layered radial gradients for realism */
        .robot-thought-bubble .cloud-bg {
            position: absolute;
            inset: 0;
            border-radius: 46px;
            background:
                radial-gradient(circle at 14% 18%, #ffffff 0%, #f5faff 9%, transparent 10%),
                radial-gradient(circle at 30% 8%,  #fdfeff 0%, #f0f7ff 13%, transparent 14%),
                radial-gradient(circle at 50% 5%,  #ffffff 0%, #f3f9ff 15%, transparent 16%),
                radial-gradient(circle at 70% 7%,  #fdfeff 0%, #eff6ff 13%, transparent 14%),
                radial-gradient(circle at 86% 18%, #fbfdff 0%, #ebf3ff 10%, transparent 11%),
                radial-gradient(circle at 4% 42%,  #eff6ff 0%, #dae8fb 12%, transparent 13%),
                radial-gradient(circle at 96% 42%, #ebf3fb 0%, #d4e2f7 12%, transparent 13%),
                radial-gradient(circle at 10% 72%, #e4eefc 0%, #cbdcf4 13%, transparent 14%),
                radial-gradient(circle at 90% 72%, #e0ecfa 0%, #c7d9f2 13%, transparent 14%),
                radial-gradient(circle at 25% 92%, #dae7f9 0%, #c1d4ee 13%, transparent 14%),
                radial-gradient(circle at 50% 96%, #d5e3f7 0%, #bccfea 14%, transparent 15%),
                radial-gradient(circle at 75% 92%, #dae7f9 0%, #c1d4ee 13%, transparent 14%),
                linear-gradient(155deg, #eaf3ff 0%, #cadcf5 42%, #8fb5dd 100%);
            filter: url(#cloudRipple);
            z-index: 1;
            pointer-events: none;
            box-shadow:
                inset -10px -14px 30px rgba(50,90,160,0.38),
                inset 12px 16px 32px rgba(255,255,255,0.9),
                inset 0 0 0 1px rgba(220,235,255,0.7),
                0 16px 32px rgba(20,35,60,0.45),
                0 0 36px rgba(150,190,240,0.42);
            transition: background 0.6s ease, box-shadow 0.6s ease, filter 0.6s ease;
        }
        .robot-thought-bubble .cloud-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 46px;
            background:
                radial-gradient(circle at 20% 30%, rgba(255,255,255,0.35) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(180,210,245,0.3) 0%, transparent 45%);
            pointer-events: none;
            animation: cloudDrift 9s ease-in-out infinite alternate;
        }
        @keyframes cloudDrift {
            0%   { transform: translate(0, 0) scale(1); opacity: 0.7; }
            100% { transform: translate(2px, -3px) scale(1.02); opacity: 1; }
        }

        /* =========================================================
           WEATHER EFFECT: SUN (A grade) – rising sun with rays
           ========================================================= */
        .robot-thought-bubble .cloud-sun {
            position: absolute;
            width: 110px; height: 110px;
            top: -42px; right: -30px;
            border-radius: 50%;
            background: radial-gradient(circle at 42% 42%,
                #fffce8 0%, #fef3c7 22%, #fde68a 42%,
                #fbbf24 62%, #f59e0b 78%,
                rgba(245,158,11,0.35) 90%, rgba(245,158,11,0) 100%);
            box-shadow:
                0 0 30px rgba(251,191,36,0.85),
                0 0 70px rgba(251,191,36,0.55),
                0 0 120px rgba(245,158,11,0.35),
                0 0 180px rgba(245,158,11,0.2);
            opacity: 0;
            transform: translateY(40px) scale(0.55);
            transition: opacity 0.7s ease, transform 0.9s cubic-bezier(0.34, 1.4, 0.64, 1);
            z-index: 0;
            pointer-events: none;
        }
        .robot-thought-bubble .cloud-sun::before {
            content: "";
            position: absolute;
            inset: -30px;
            border-radius: 50%;
            background:
                conic-gradient(from 0deg,
                    rgba(253,224,71,0.55) 0deg, rgba(253,224,71,0) 12deg,
                    rgba(253,224,71,0.55) 24deg, rgba(253,224,71,0) 36deg,
                    rgba(253,224,71,0.55) 48deg, rgba(253,224,71,0) 60deg,
                    rgba(253,224,71,0.55) 72deg, rgba(253,224,71,0) 84deg,
                    rgba(253,224,71,0.55) 96deg, rgba(253,224,71,0) 108deg,
                    rgba(253,224,71,0.55) 120deg, rgba(253,224,71,0) 132deg,
                    rgba(253,224,71,0.55) 144deg, rgba(253,224,71,0) 156deg,
                    rgba(253,224,71,0.55) 168deg, rgba(253,224,71,0) 180deg,
                    rgba(253,224,71,0.55) 192deg, rgba(253,224,71,0) 204deg,
                    rgba(253,224,71,0.55) 216deg, rgba(253,224,71,0) 228deg,
                    rgba(253,224,71,0.55) 240deg, rgba(253,224,71,0) 252deg,
                    rgba(253,224,71,0.55) 264deg, rgba(253,224,71,0) 276deg,
                    rgba(253,224,71,0.55) 288deg, rgba(253,224,71,0) 300deg,
                    rgba(253,224,71,0.55) 312deg, rgba(253,224,71,0) 324deg,
                    rgba(253,224,71,0.55) 336deg, rgba(253,224,71,0) 348deg,
                    rgba(253,224,71,0.55) 360deg);
            filter: blur(6px);
            opacity: 0.75;
            animation: sunRaysSpin 22s linear infinite;
        }
        @keyframes sunRaysSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .robot-thought-bubble.cloud-sun .cloud-sun {
            opacity: 1;
            transform: translateY(0) scale(1);
            animation: sunRise 3.2s ease-in-out infinite alternate;
        }
        @keyframes sunRise {
            0%   { transform: translateY(0) scale(1); }
            100% { transform: translateY(-8px) scale(1.06); }
        }

        /* =========================================================
           WEATHER EFFECT: RAINBOW (B grade)
           ========================================================= */
        .robot-thought-bubble .cloud-rainbow {
            position: absolute;
            top: -78px;
            left: 50%;
            transform: translateX(-50%) scaleY(0.4);
            transform-origin: 50% 100%;
            width: 260px;
            height: 130px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.8s ease, transform 1.1s cubic-bezier(0.34, 1.4, 0.64, 1);
            z-index: 0;
            border-radius: 260px 260px 0 0;
            background:
                radial-gradient(ellipse 100% 200% at 50% 100%,
                    transparent 0%,
                    transparent 42%,
                    #ef4444 42% 47%,
                    #f97316 47% 52%,
                    #fbbf24 52% 57%,
                    #4ade80 57% 62%,
                    #38bdf8 62% 67%,
                    #8b5cf6 67% 72%,
                    #ec4899 72% 77%,
                    transparent 77%);
            filter: drop-shadow(0 0 14px rgba(255,255,255,0.55)) drop-shadow(0 0 30px rgba(168,85,247,0.4));
        }
        .robot-thought-bubble.cloud-rainbow .cloud-rainbow {
            opacity: 1;
            transform: translateX(-50%) scaleY(1);
            animation: rainbowGlow 3.4s ease-in-out infinite alternate;
        }
        @keyframes rainbowGlow {
            0%   { filter: drop-shadow(0 0 14px rgba(255,255,255,0.55)) drop-shadow(0 0 30px rgba(168,85,247,0.4)); }
            100% { filter: drop-shadow(0 0 22px rgba(255,255,255,0.85)) drop-shadow(0 0 46px rgba(168,85,247,0.7)); }
        }

        /* =========================================================
           WEATHER EFFECT: RAIN STREAKS (used by C, D and F)
           ========================================================= */
        .robot-thought-bubble .cloud-rain {
            position: absolute;
            left: 0; right: 0;
            top: 100%;
            height: 90px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.6s ease;
            z-index: 0;
            overflow: visible;
        }
        .robot-thought-bubble.cloud-rain-heavy .cloud-rain,
        .robot-thought-bubble.cloud-storm .cloud-rain { opacity: 1; }

        .robot-thought-bubble .cloud-rain .rain-streak {
            position: absolute;
            top: 0;
            width: 2px;
            height: 18px;
            background: linear-gradient(to bottom,
                rgba(147,197,253,0.95) 0%,
                rgba(96,165,250,0.85) 55%,
                rgba(59,130,246,0) 100%);
            border-radius: 2px;
            animation: rainFromCloud linear infinite;
            filter: drop-shadow(0 0 3px rgba(147,197,253,0.7));
        }
        /* Heavy rain drops are thicker and fall faster */
        .robot-thought-bubble.cloud-rain-heavy .cloud-rain .rain-streak {
            width: 3px;
            height: 24px;
            background: linear-gradient(to bottom,
                rgba(191,219,254,0.95) 0%,
                rgba(96,165,250,0.9) 55%,
                rgba(59,130,246,0) 100%);
            filter: drop-shadow(0 0 5px rgba(147,197,253,0.85));
        }
        @keyframes rainFromCloud {
            0%   { transform: translateY(-10px); opacity: 0; }
            15%  { opacity: 1; }
            100% { transform: translateY(80px); opacity: 0; }
        }

        /* =========================================================
           WEATHER EFFECT: LIGHTNING (F grade only)
           ========================================================= */
        .robot-thought-bubble .cloud-lightning {
            position: absolute;
            bottom: -22px;
            width: 32px; height: 46px;
            pointer-events: none;
            opacity: 0;
            z-index: 4;
            filter: drop-shadow(0 0 12px rgba(253,224,71,0.95)) drop-shadow(0 0 24px rgba(250,204,21,0.7));
        }
        .robot-thought-bubble.cloud-storm .cloud-lightning {
            animation: lightningFlash 4.2s ease-in-out infinite;
        }
        @keyframes lightningFlash {
            0%, 72%, 100% { opacity: 0; transform: translateY(0) scale(0.8); }
            74%           { opacity: 1; transform: translateY(0) scale(1.05); }
            76%           { opacity: 0.2; transform: translateY(0) scale(1); }
            78%           { opacity: 1; transform: translateY(0) scale(1.05); }
            80%           { opacity: 0; }
            82%           { opacity: 1; transform: translateY(0) scale(1.05); }
            84%           { opacity: 0; }
        }

        .robot-thought-bubble .cloud-flash {
            position: absolute;
            inset: -10px;
            border-radius: 50px;
            background: radial-gradient(ellipse at 50% 40%, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.35) 40%, rgba(255,255,255,0) 75%);
            pointer-events: none;
            opacity: 0;
            z-index: 2;
        }
        .robot-thought-bubble.cloud-storm .cloud-flash {
            animation: cloudFlash 4.2s ease-in-out infinite;
        }
        @keyframes cloudFlash {
            0%, 71%, 100% { opacity: 0; }
            73%           { opacity: 0.9; }
            74%           { opacity: 0.15; }
            75%           { opacity: 0.85; }
            76%           { opacity: 0; }
            79%           { opacity: 0.75; }
            80%           { opacity: 0; }
        }

        /* Storm cloud body override – dark heavy greys (both D and F) */
        .robot-thought-bubble.cloud-rain-heavy .cloud-bg,
        .robot-thought-bubble.cloud-storm .cloud-bg {
            background:
                radial-gradient(circle at 14% 18%, #5b6a82 0%, #475569 9%, transparent 10%),
                radial-gradient(circle at 30% 8%,  #526079 0%, #3f4b60 13%, transparent 14%),
                radial-gradient(circle at 50% 5%,  #4e5c72 0%, #3a4658 15%, transparent 16%),
                radial-gradient(circle at 70% 7%,  #526079 0%, #3f4b60 13%, transparent 14%),
                radial-gradient(circle at 86% 18%, #5b6a82 0%, #475569 10%, transparent 11%),
                radial-gradient(circle at 4% 42%,  #4a576d 0%, #333e50 12%, transparent 13%),
                radial-gradient(circle at 96% 42%, #46536a 0%, #2f3a4c 12%, transparent 13%),
                radial-gradient(circle at 10% 72%, #3d4a5e 0%, #28334a 13%, transparent 14%),
                radial-gradient(circle at 90% 72%, #3a4659 0%, #25304a 13%, transparent 14%),
                radial-gradient(circle at 25% 92%, #2d3950 0%, #1a2338 13%, transparent 14%),
                radial-gradient(circle at 50% 96%, #28344b 0%, #172033 14%, transparent 15%),
                radial-gradient(circle at 75% 92%, #2d3950 0%, #1a2338 13%, transparent 14%),
                linear-gradient(155deg, #3e4c62 0%, #26314a 45%, #0f172a 100%);
            box-shadow:
                inset -10px -14px 30px rgba(0,0,0,0.65),
                inset 12px 16px 32px rgba(148,163,184,0.28),
                inset 0 0 0 1px rgba(100,116,139,0.35),
                0 18px 34px rgba(0,0,0,0.6),
                0 0 40px rgba(70,90,130,0.5);
        }
        .robot-thought-bubble.cloud-rain-heavy .cloud-ripple,
        .robot-thought-bubble.cloud-storm .cloud-ripple {
            background: radial-gradient(ellipse at 50% 50%, rgba(40,52,75,0.55) 0%, rgba(30,41,59,0.35) 45%, rgba(15,23,42,0) 75%);
        }
        .robot-thought-bubble.cloud-rain-heavy .cloud-tail,
        .robot-thought-bubble.cloud-storm .cloud-tail {
            background: radial-gradient(circle at 35% 30%, #526079 0%, #3a4659 55%, #172033 100%);
            box-shadow:
                inset -2px -2px 6px rgba(0,0,0,0.7),
                inset 2px 2px 6px rgba(148,163,184,0.25),
                0 4px 10px rgba(0,0,0,0.55);
        }
        .robot-thought-bubble.cloud-rain-heavy .cloud-content,
        .robot-thought-bubble.cloud-storm .cloud-content { color: #f5f7fa; }
        .robot-thought-bubble.cloud-rain-heavy .cloud-content strong,
        .robot-thought-bubble.cloud-storm .cloud-content strong { color: #7dd3fc; }
        .robot-thought-bubble.cloud-rain-heavy .cloud-content em,
        .robot-thought-bubble.cloud-storm .cloud-content em { color: #c4b5fd; }
        .robot-thought-bubble.cloud-rain-heavy .cloud-content .advice,
        .robot-thought-bubble.cloud-storm .cloud-content .advice { color: #cbd5e1; }

        /* Content layer sits above the cloud */
        .robot-thought-bubble .cloud-content {
            position: relative;
            z-index: 3;
            padding: 22px 26px;
            color: #0a1e3f;
            font-size: 0.86rem;
            line-height: 1.55;
            transition: color 0.5s ease;
        }
        .robot-thought-bubble .cloud-content strong { color: #1d4ed8; transition: color 0.5s ease; }
        .robot-thought-bubble .cloud-content em { color: #7c3aed; font-style: normal; transition: color 0.5s ease; }
        .robot-thought-bubble .cloud-content .advice { display: block; margin-top: 6px; color: #475569; font-size: 0.8rem; transition: color 0.5s ease; }

        /* ===== DARK CLOUD VARIANT (for light pages) ===== */
        .robot-thought-bubble.cloud-dark .cloud-ripple {
            background: radial-gradient(ellipse at 50% 50%, rgba(30,41,59,0.4) 0%, rgba(30,41,59,0.25) 45%, rgba(15,23,42,0) 75%);
        }
        .robot-thought-bubble.cloud-dark .cloud-bg {
            background:
                radial-gradient(circle at 18% 22%, #475569 0%, #334155 11%, transparent 12%),
                radial-gradient(circle at 38% 8%,  #475569 0%, #334155 14%, transparent 15%),
                radial-gradient(circle at 62% 6%,  #475569 0%, #334155 14%, transparent 15%),
                radial-gradient(circle at 82% 20%, #475569 0%, #334155 11%, transparent 12%),
                radial-gradient(circle at 5% 48%,  #334155 0%, #1e293b 13%, transparent 14%),
                radial-gradient(circle at 95% 50%, #334155 0%, #1e293b 13%, transparent 14%),
                radial-gradient(circle at 20% 86%, #1e293b 0%, #0f172a 13%, transparent 14%),
                radial-gradient(circle at 50% 94%, #0f172a 0%, #020617 14%, transparent 15%),
                radial-gradient(circle at 80% 86%, #1e293b 0%, #0f172a 13%, transparent 14%),
                linear-gradient(155deg, #334155 0%, #1e293b 45%, #020617 100%);
            box-shadow:
                inset -8px -12px 26px rgba(0,0,0,0.55),
                inset 10px 14px 28px rgba(148,163,184,0.25),
                inset 0 0 0 1px rgba(148,163,184,0.25),
                0 16px 30px rgba(0,0,0,0.55),
                0 0 34px rgba(100,116,139,0.4);
        }
        .robot-thought-bubble.cloud-dark .cloud-content { color: #f5f7fa; }
        .robot-thought-bubble.cloud-dark .cloud-content strong { color: #7dd3fc; }
        .robot-thought-bubble.cloud-dark .cloud-content em { color: #c4b5fd; }
        .robot-thought-bubble.cloud-dark .cloud-content .advice { color: #cbd5e1; }

        /* Thought buttons */
        .robot-thought-bubble .thought-actions { display: flex; gap: 8px; margin-top: 12px; }
        .robot-thought-bubble .thought-btn {
            flex: 1; padding: 8px 12px;
            border-radius: 10px; border: none;
            font-size: 0.78rem; font-weight: 600;
            letter-spacing: 0.3px; cursor: pointer;
            font-family: inherit; color: #ffffff;
            transition: transform 0.15s, box-shadow 0.2s, filter 0.2s;
        }
        .robot-thought-bubble .thought-btn.yes { background: linear-gradient(135deg, #22c55e, #16a34a); box-shadow: 0 2px 8px rgba(34,197,94,0.55); }
        .robot-thought-bubble .thought-btn.no  { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 2px 8px rgba(239,68,68,0.55); }
        .robot-thought-bubble .thought-btn:hover { transform: translateY(-1px); filter: brightness(1.12); box-shadow: 0 4px 14px rgba(0,0,0,0.4); }
        .robot-thought-bubble .thought-btn:active { transform: translateY(0); filter: brightness(0.95); }

        /* Tail dots */
        .robot-thought-bubble .cloud-tail {
            position: absolute;
            border-radius: 50%;
            z-index: 2;
            pointer-events: none;
            background: radial-gradient(circle at 35% 30%, #f8fbff 0%, #d8e8fb 55%, #9dbfe4 100%);
            box-shadow:
                inset -2px -2px 6px rgba(70,110,180,0.35),
                inset 2px 2px 6px rgba(255,255,255,0.9),
                0 4px 10px rgba(20,35,60,0.35);
            transition: background 0.5s ease, box-shadow 0.5s ease;
        }
        .robot-thought-bubble.cloud-dark .cloud-tail {
            background: radial-gradient(circle at 35% 30%, #475569 0%, #334155 55%, #0f172a 100%);
            box-shadow:
                inset -2px -2px 6px rgba(0,0,0,0.6),
                inset 2px 2px 6px rgba(148,163,184,0.25),
                0 4px 10px rgba(0,0,0,0.5);
        }
        .robot-thought-bubble .cloud-tail.tail-1 { width: 16px; height: 16px; }
        .robot-thought-bubble .cloud-tail.tail-2 { width: 9px; height: 9px; }

        .robot-thought-bubble.tail-right .tail-1 { bottom: 2px; right: -22px; }
        .robot-thought-bubble.tail-right .tail-2 { bottom: -10px; right: -38px; }
        .robot-thought-bubble.tail-left .tail-1 { bottom: 2px; left: -22px; }
        .robot-thought-bubble.tail-left .tail-2 { bottom: -10px; left: -38px; }
        .robot-thought-bubble.tail-down .tail-1 { bottom: -20px; left: 50%; transform: translateX(-50%); }
        .robot-thought-bubble.tail-down .tail-2 { bottom: -36px; left: 50%; transform: translateX(-50%); }
        .robot-thought-bubble.tail-up .tail-1 { top: -20px; left: 50%; transform: translateX(-50%); }
        .robot-thought-bubble.tail-up .tail-2 { top: -36px; left: 50%; transform: translateX(-50%); }

        .robot-target-highlight { position: relative; z-index: 1; }
        .robot-target-highlight::after {
            content: ""; position: absolute; inset: -6px;
            border: 2px solid rgba(96,165,250,0.9);
            border-radius: 10px;
            box-shadow: 0 0 18px rgba(96,165,250,0.9), 0 0 34px rgba(59,130,246,0.5), inset 0 0 12px rgba(96,165,250,0.35);
            animation: targetPulse 1.6s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes targetPulse { 0%, 100% { opacity: 0.6; transform: scale(1); } 50% { opacity: 1; transform: scale(1.05); } }

        /* ========== PARTICLE LAYER ========== */
        .robot-particles-layer {
            position: fixed; inset: 0;
            pointer-events: none;
            z-index: 1001;
            overflow: hidden;
        }
        .robot-particle {
            position: absolute;
            pointer-events: none;
            line-height: 1;
            will-change: transform, opacity;
            animation: particleFly 2.6s cubic-bezier(0.22, 0.75, 0.35, 1) forwards;
        }
        @keyframes particleFly {
            0%   { transform: translate(0, 0) scale(0.35) rotate(0deg); opacity: 0; }
            15%  { opacity: 1; }
            100% { transform: translate(var(--px), var(--py)) scale(1.25) rotate(var(--pr)); opacity: 0; }
        }
        .robot-particle.firework {
            animation-duration: 1.7s;
            text-shadow: 0 0 12px currentColor, 0 0 24px currentColor;
        }
        .robot-particle.slow-up { animation: particleFloatUp 3.6s cubic-bezier(0.34, 0.6, 0.4, 1) forwards; }
        @keyframes particleFloatUp {
            0%   { transform: translate(0, 0) scale(0.4); opacity: 0; }
            15%  { opacity: 1; }
            80%  { opacity: 1; }
            100% { transform: translate(var(--px), var(--py)) scale(1.15) rotate(var(--pr)); opacity: 0; }
        }
        .robot-particle.sneeze { animation: particleSneeze 1.4s cubic-bezier(0.22, 0.75, 0.35, 1) forwards; font-weight: 700; }
        @keyframes particleSneeze {
            0%   { transform: translate(0, 0) scale(0.6); opacity: 0; }
            20%  { transform: translate(calc(var(--px) * 0.4), calc(var(--py) * 0.4)) scale(1.15); opacity: 1; }
            100% { transform: translate(var(--px), var(--py)) scale(1.3); opacity: 0; }
        }

        /* ---- Career particles ---- */
        .career-particle {
            position: absolute;
            display: flex; align-items: center; gap: 9px;
            padding: 9px 16px;
            border-radius: 40px;
            background: linear-gradient(135deg, rgba(15,25,45,0.99), rgba(8,15,30,0.99));
            border: 1px solid rgba(96,165,250,0.65);
            color: #f5f7fa;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.25px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.55), 0 0 24px rgba(96,165,250,0.55);
            white-space: nowrap;
            pointer-events: none;
            transform: translate(-50%, -50%) scale(0.3);
            opacity: 0;
            animation: careerFlyLeft 14s cubic-bezier(0.28, 0.6, 0.4, 1) forwards;
            z-index: 1005;
        }
        .career-particle i { color: #7dd3fc; font-size: 1.35rem; filter: drop-shadow(0 0 7px rgba(125,211,252,0.8)); }
        @keyframes careerFlyLeft {
            0% { transform: translate(-50%, -50%) scale(0.3); opacity: 0; }
            8% { transform: translate(-50%, -50%) scale(1.08); opacity: 1; }
            15% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            82% { transform: translate(calc(-50% - var(--career-x, 260px) * 0.7), calc(-50% + var(--career-y, 40px) * 0.7)) scale(1); opacity: 1; }
            100% { transform: translate(calc(-50% - var(--career-x, 260px)), calc(-50% + var(--career-y, 40px))) scale(0.92); opacity: 0; }
        }

        /* ========== RAIN LAYER ========== */
        .robot-rain-layer {
            position: fixed; inset: 0;
            pointer-events: none;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.7s ease;
            overflow: hidden;
        }
        .robot-rain-layer.active { opacity: 1; }
        .rain-drop {
            position: absolute;
            top: -20px;
            width: 2px;
            height: 16px;
            background: linear-gradient(to bottom, rgba(160,205,255,0), rgba(160,205,255,0.85));
            border-radius: 2px;
            animation: rainFall linear infinite;
        }
        @keyframes rainFall {
            0%   { transform: translateY(-30px); opacity: 0; }
            10%  { opacity: 0.85; }
            100% { transform: translateY(105vh); opacity: 0.85; }
        }

        /* ========== AI CHAT ========== */
        .ai-chat-widget {
            position: fixed;
            right: 24px;
            bottom: calc(104px + env(safe-area-inset-bottom, 0px));
            width: 400px;
            max-width: calc(100vw - 32px);
            max-height: calc(100vh - 140px - env(safe-area-inset-bottom, 0px));
            background: rgba(20,30,50,0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            border: 1px solid rgba(96,165,250,0.4);
            z-index: 1051; display: none; flex-direction: column; overflow: hidden;
        }
        .ai-chat-widget.open { display: flex; }
        .ai-chat-header {
            padding: 14px 18px;
            background: rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(96,165,250,0.3);
            display: flex; justify-content: space-between; align-items: center;
            font-weight: bold; color: #7dd3fc; font-size: 1rem;
        }
        .ai-chat-header .header-controls { display: flex; align-items: center; gap: 10px; }
        .ai-chat-header button { background: none; border: none; color: white; font-size: 22px; cursor: pointer; transition: 0.2s; line-height: 1; }
        .ai-chat-header button:hover { color: #7dd3fc; }
        .ai-chat-messages {
            flex: 1 1 auto;
            min-height: 200px; max-height: 450px;
            overflow-y: auto; padding: 16px;
            display: flex; flex-direction: column; gap: 12px;
        }
        .message { max-width: 88%; padding: 10px 14px; border-radius: 20px; font-size: 0.9rem; line-height: 1.45; word-wrap: break-word; white-space: pre-wrap; }
        .message.user { background: #60a5fa; color: #0b1220; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message.ai { background: rgba(96,165,250,0.15); color: white; align-self: flex-start; border-bottom-left-radius: 4px; }
        .ai-typing { background: rgba(255,255,255,0.08); padding: 8px 16px; border-radius: 20px; font-style: italic; display: inline-block; width: auto; }
        .ai-chat-input {
            padding: 12px;
            border-top: 1px solid rgba(96,165,250,0.2);
            display: flex; gap: 8px;
            background: rgba(0,0,0,0.3);
            flex-shrink: 0;
        }
        .ai-chat-input input {
            flex: 1; min-width: 0;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(96,165,250,0.5);
            border-radius: 40px; padding: 10px 16px;
            color: white; outline: none;
        }
        .ai-chat-input input::placeholder { color: rgba(255,255,255,0.5); }
        .ai-chat-input button { background: #60a5fa; border: none; border-radius: 40px; padding: 0 18px; font-weight: bold; color: #0b1220; cursor: pointer; }

        .mic-btn, .speaker-btn {
            background: rgba(96,165,250,0.15);
            border: 1px solid rgba(96,165,250,0.5);
            border-radius: 50%; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            color: #7dd3fc; font-size: 1.4rem; cursor: pointer;
            transition: all 0.3s; flex-shrink: 0; position: relative;
        }
        .mic-btn:hover, .speaker-btn:hover { background: rgba(96,165,250,0.3); transform: scale(1.05); }
        .mic-btn.recording { background: #ef476f; border-color: #ef476f; color: white; }
        .mic-btn.recording::before { content: ''; position: absolute; top: -4px; left: -4px; right: -4px; bottom: -4px; border-radius: 50%; border: 2px solid #ef476f; animation: wavePulse 1.2s ease-out infinite; }
        .mic-btn.recording::after { content: ''; position: absolute; top: -10px; left: -10px; right: -10px; bottom: -10px; border-radius: 50%; border: 2px solid rgba(239,71,111,0.3); animation: wavePulse 1.2s ease-out infinite 0.4s; }
        @keyframes wavePulse { 0% { transform: scale(0.95); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
        .mic-btn:disabled, .speaker-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .speaker-btn.muted { color: #aaa; border-color: #555; }

        @media (max-width: 900px) {
            .robot-thought-anchor { top: 84px; right: 12px; width: 58px; height: 58px; }
            .robot-thought-bubble { max-width: 260px; min-width: 180px; }
            .robot-thought-bubble .cloud-content { padding: 18px 22px; font-size: 0.8rem; }
            .robot-thought-bubble .cloud-sun { width: 80px; height: 80px; top: -30px; right: -22px; }
            .robot-thought-bubble .cloud-rainbow { width: 200px; height: 100px; top: -60px; }
            .robot-stop-btn { width: 34px; height: 34px; font-size: 0.95rem; }
            .robot-stop-btn.show { top: calc(50% + 48px); }
            .career-particle { font-size: 0.75rem; padding: 7px 12px; }
            .career-particle i { font-size: 1.1rem; }
            .lamp-toggle { width: 50px; height: 50px; }
            .lamp-svg { width: 40px; height: 48px; }
        }
        @media (max-width: 768px) {
            .app-header { padding: 0.5rem 1rem; height: 70px; }
            .school-name { display: none; }
            .header-center { padding: 0 5px; }
            .greeting-text { font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .latest-exam { font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .app-nav { gap: 6px; }
            .app-nav__item { padding: 6px 10px; }
            .theme-menu { width: 340px; }
            .theme-grid { grid-template-columns: 1fr; }
            .ai-chat-widget {
                left: 10px; right: 10px; width: auto;
                max-width: none;
                bottom: calc(96px + env(safe-area-inset-bottom, 0px));
                max-height: calc(100vh - 120px - env(safe-area-inset-bottom, 0px) - 70px);
                border-radius: 18px;
            }
            .ai-chat-messages { min-height: 0; max-height: none; padding: 14px; }
            .ai-chat-header { padding: 12px 14px; font-size: 0.92rem; }
            .ai-chat-input { padding: 10px; gap: 6px; }
            .ai-chat-input input { padding: 9px 14px; font-size: 0.9rem; }
            .ai-chat-input button { padding: 0 14px; font-size: 0.85rem; }
            .mic-btn, .speaker-btn { width: 40px; height: 40px; font-size: 1.2rem; }
            .message { font-size: 0.85rem; }
        }
        @media (max-width: 480px) {
            .theme-label { display: none; }
            .app-nav__item { padding: 6px 8px; }
            .ai-chat-widget { bottom: calc(88px + env(safe-area-inset-bottom, 0px)); }
            .lamp-toggle { width: 44px; height: 44px; }
            .lamp-svg { width: 34px; height: 42px; }
            .robot-thought-bubble { min-width: 160px; max-width: 220px; }
            .robot-thought-bubble .cloud-content { padding: 16px 18px; font-size: 0.78rem; }
            .robot-thought-bubble .cloud-sun { width: 68px; height: 68px; top: -24px; right: -18px; }
            .robot-thought-bubble .cloud-rainbow { width: 170px; height: 85px; top: -50px; }
        }

        .ripple { position: relative; overflow: hidden; }
        .ripple:after { content: ""; display: block; position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: none; background-image: radial-gradient(circle, #fff 10%, transparent 10.01%); background-repeat: no-repeat; background-position: 50%; transform: scale(10,10); opacity: 0; transition: transform .3s, opacity .5s; }
        .ripple:active:after { transform: scale(0,0); opacity: .3; transition: 0s; }
    </style>
</head>
<body class="theme-<?php echo h($saved_theme); ?>">

    <!-- SVG filters for cloud ripple effect -->
    <svg width="0" height="0" style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
        <defs>
            <filter id="cloudRipple" x="-15%" y="-15%" width="130%" height="130%">
                <feTurbulence type="fractalNoise" baseFrequency="0.019 0.022" numOctaves="3" seed="9" result="noise"/>
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="11" xChannelSelector="R" yChannelSelector="G" result="disp"/>
                <feGaussianBlur in="disp" stdDeviation="1.1"/>
            </filter>
            <filter id="cloudRippleSoft" x="-30%" y="-30%" width="160%" height="160%">
                <feTurbulence type="fractalNoise" baseFrequency="0.012 0.014" numOctaves="2" seed="17" result="noise"/>
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="22" xChannelSelector="R" yChannelSelector="G" result="disp"/>
                <feGaussianBlur in="disp" stdDeviation="4"/>
            </filter>
        </defs>
    </svg>

    <!-- Theme containers -->
    <div class="theme-container theme-1-container" data-theme="theme-1"></div>
    <div class="theme-container theme-2-container" data-theme="theme-2" style="display:none;"></div>
    <div class="theme-container theme-3-container" data-theme="theme-3" style="display:none;"></div>
    <div class="theme-container theme-4-container" data-theme="theme-4" style="display:none;"></div>
    <div class="theme-container theme-5-container" data-theme="theme-5" style="display:none;"></div>
    <div class="theme-container theme-6-container" data-theme="theme-6" style="display:none;"><canvas id="bubbleCanvasTheme"></canvas></div>
    <div class="theme-container theme-7-container" data-theme="theme-7" style="display:none;"></div>
    <div class="theme-container theme-8-container" data-theme="theme-8" style="display:none;"></div>
    <div class="theme-container theme-9-container" data-theme="theme-9" style="display:none;"></div>
    <div class="theme-container theme-10-container" data-theme="theme-10" style="display:none;"></div>
    <div class="theme-container theme-11-container" data-theme="theme-11" style="display:none;"><div class="leaves-container"></div></div>
    <div class="theme-container theme-12-container" data-theme="theme-12" style="display:none;"><div class="bubble-wonder-container"><div class="main"><div class="d1"></div><div class="d2"></div><div class="d3"></div><div class="d4"></div></div></div></div>
    <div class="theme-container theme-13-container" data-theme="theme-13" style="display:none;"></div>
    <div class="theme-container theme-14-container" data-theme="theme-14" style="display:none;"><div class="background-container"><img src="https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/moon2.png" alt=""><div class="stars"></div><div class="twinkling"></div><div class="clouds"></div></div></div>
    <div class="theme-container theme-15-container" data-theme="theme-15" style="display:none;"><div class="fog-layer"></div></div>
    <div class="theme-container theme-16-container" data-theme="theme-16" style="display:none;"><div class="light-ray"></div></div>
    <div class="theme-container theme-17-container" data-theme="theme-17" style="display:none;"></div>
    <div class="theme-container theme-18-container" data-theme="theme-18" style="display:none;"></div>
    <div class="theme-container theme-19-container" data-theme="theme-19" style="display:none;"></div>
    <div class="theme-container theme-20-container" data-theme="theme-20" style="display:none;"></div>
    <div class="theme-container theme-21-container" data-theme="theme-21" style="display:none;"></div>
    <div class="theme-container theme-22-container" data-theme="theme-22" style="display:none;"></div>
    <div class="theme-container theme-23-container" data-theme="theme-23" style="display:none;"></div>
    <div class="theme-container theme-24-container" data-theme="theme-24" style="display:none;"></div>
    <div class="theme-container theme-25-container" data-theme="theme-25" style="display:none;"></div>
    <div class="theme-container theme-26-container" data-theme="theme-26" style="display:none;"></div>
    <div class="theme-container theme-27-container" data-theme="theme-27" style="display:none;"></div>
    <div class="theme-container theme-28-container" data-theme="theme-28" style="display:none;"></div>
    <div class="theme-container theme-29-container" data-theme="theme-29" style="display:none;"></div>
    <div class="theme-container theme-30-container" data-theme="theme-30" style="display:none;"></div>

    <header class="app-header">
        <div class="header-left">
            <div class="school-name"><?php echo h(substr($school_name, 0, 25)); ?></div>
            <a class="lamp-toggle ripple" href="#" id="sidebarToggle" aria-label="Toggle Sidebar">
                <svg class="lamp-svg" viewBox="0 0 60 70" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="lampBaseGrad" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#475569"/><stop offset="45%" stop-color="#334155"/><stop offset="100%" stop-color="#0f172a"/></linearGradient>
                        <linearGradient id="lampBaseTopGrad" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#94a3b8"/><stop offset="100%" stop-color="#475569"/></linearGradient>
                        <linearGradient id="lampArmGrad" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#64748b"/><stop offset="45%" stop-color="#cbd5e1"/><stop offset="100%" stop-color="#334155"/></linearGradient>
                        <linearGradient id="lampShadeGrad" x1="10%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#93c5fd"/><stop offset="25%" stop-color="#60a5fa"/><stop offset="60%" stop-color="#38bdf8"/><stop offset="100%" stop-color="#0b2161"/></linearGradient>
                        <linearGradient id="lampShadeShine" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="rgba(255,255,255,0.55)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></linearGradient>
                        <linearGradient id="lampShadeInner" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="rgba(0,0,0,0.4)"/><stop offset="100%" stop-color="rgba(0,0,0,0.85)"/></linearGradient>
                        <radialGradient id="lampBulbGrad" cx="35%" cy="30%" r="80%"><stop offset="0%" stop-color="#ffffff"/><stop offset="40%" stop-color="#fef3c7"/><stop offset="75%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></radialGradient>
                        <radialGradient id="lampGlowGrad" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="rgba(254,240,138,0.95)"/><stop offset="50%" stop-color="rgba(250,204,21,0.5)"/><stop offset="100%" stop-color="rgba(250,204,21,0)"/></radialGradient>
                        <linearGradient id="lampBeamGrad" x1="50%" y1="0%" x2="50%" y2="100%"><stop offset="0%" stop-color="rgba(250,204,21,0.85)"/><stop offset="100%" stop-color="rgba(250,204,21,0)"/></linearGradient>
                    </defs>
                    <ellipse cx="30" cy="66" rx="16" ry="2.6" fill="rgba(0,0,0,0.35)"/>
                    <rect x="16" y="61" width="28" height="5" rx="2" fill="url(#lampBaseTopGrad)" stroke="#1e293b" stroke-width="0.6"/>
                    <rect x="18" y="60" width="24" height="2.5" rx="1.2" fill="url(#lampBaseGrad)"/>
                    <ellipse cx="30" cy="60.5" rx="12" ry="1.6" fill="#cbd5e1" opacity="0.9"/>
                    <g class="lamp-swing">
                        <path d="M 30 61 C 30 52, 30 48, 27 40 L 27 32" stroke="url(#lampArmGrad)" stroke-width="3" fill="none" stroke-linecap="round"/>
                        <path d="M 28.2 58 C 28.2 50, 28.5 46, 26.5 40 L 26.5 33" stroke="rgba(255,255,255,0.6)" stroke-width="0.9" fill="none" stroke-linecap="round"/>
                        <circle cx="27" cy="33" r="2" fill="#94a3b8" stroke="#334155" stroke-width="0.6"/>
                        <circle cx="26.4" cy="32.4" r="0.7" fill="rgba(255,255,255,0.75)"/>
                        <path class="lamp-beam" d="M 27 34 L 48 62 L 6 62 Z" fill="url(#lampBeamGrad)" opacity="0.35"/>
                        <path d="M 8 34 L 46 34 L 40 18 L 14 18 Z" fill="url(#lampShadeGrad)" stroke="#0b2161" stroke-width="1" stroke-linejoin="round"/>
                        <path d="M 14 18 L 40 18 L 38 21 L 16 21 Z" fill="url(#lampShadeShine)" opacity="0.5"/>
                        <path d="M 16 21 L 22 21 L 20 34 L 12 34 Z" fill="rgba(255,255,255,0.15)"/>
                        <ellipse cx="27" cy="34" rx="19" ry="2.4" fill="url(#lampShadeInner)" opacity="0.9"/>
                        <line x1="8" y1="34" x2="46" y2="34" stroke="#7dd3fc" stroke-width="1.4" stroke-linecap="round"/>
                        <circle class="lamp-glow" cx="27" cy="39" r="12" fill="url(#lampGlowGrad)"/>
                        <circle cx="27" cy="38" r="4.4" fill="url(#lampBulbGrad)" stroke="#fef3c7" stroke-width="0.5"/>
                        <path class="lamp-bulb-core" d="M 25 38 L 29 38" stroke="#fef3c7" stroke-width="0.9" stroke-linecap="round" fill="none"/>
                        <circle cx="25.4" cy="36.4" r="1.1" fill="rgba(255,255,255,0.85)"/>
                    </g>
                </svg>
            </a>
        </div>
        <div class="header-center">
            <div class="greeting-text">Hello, <span class="greeting-name"><?php echo h($studentFirstName); ?></span></div>
            <?php if (!empty($header_latest_exam)): ?>
                <div class="latest-exam"><i class="bi bi-calendar-check"></i> Latest exam: <?php echo h($header_latest_exam); ?></div>
            <?php endif; ?>
        </div>
        <ul class="app-nav">
            <li class="dropdown theme-dropdown">
                <a class="app-nav__item theme-selector-btn" href="#" data-bs-toggle="dropdown"><i class="bi bi-palette-fill"></i><span class="theme-label" id="themeButtonLabel">Themes</span><i class="bi bi-chevron-down"></i></a>
                <div class="dropdown-menu theme-menu dropdown-menu-end">
                    <div class="dropdown-header"><i class="bi bi-palette"></i> Choose Theme (30)</div>
                    <div class="dropdown-divider"></div>
                    <div class="theme-grid">
                        <div class="theme-option" data-theme="theme-1" data-name="Mystical Forest"><div class="theme-preview preview-1"></div><div class="theme-info"><strong>Mystical Forest</strong><small>Floating lights</small></div></div>
                        <div class="theme-option" data-theme="theme-2" data-name="Glow Particles"><div class="theme-preview preview-2"></div><div class="theme-info"><strong>Glow Particles</strong><small>Rotating orbs</small></div></div>
                        <div class="theme-option" data-theme="theme-3" data-name="Fluid Up"><div class="theme-preview preview-3"></div><div class="theme-info"><strong>Fluid Up</strong><small>Rising bubbles</small></div></div>
                        <div class="theme-option" data-theme="theme-4" data-name="Animated Waves"><div class="theme-preview preview-4"></div><div class="theme-info"><strong>Animated Waves</strong><small>Flowing water</small></div></div>
                        <div class="theme-option" data-theme="theme-5" data-name="Neon Bulbs"><div class="theme-preview preview-5"></div><div class="theme-info"><strong>Neon Bulbs</strong><small>Glowing lights</small></div></div>
                        <div class="theme-option" data-theme="theme-6" data-name="3D Bubbles"><div class="theme-preview preview-6"></div><div class="theme-info"><strong>3D Bubbles</strong><small>Glossy spheres</small></div></div>
                        <div class="theme-option" data-theme="theme-7" data-name="Stars &amp; Shooting Stars"><div class="theme-preview preview-7"></div><div class="theme-info"><strong>Stars &amp; Shooting Stars</strong><small>Twinkling sky</small></div></div>
                        <div class="theme-option" data-theme="theme-8" data-name="Rainbow Gradient"><div class="theme-preview preview-8"></div><div class="theme-info"><strong>Rainbow Gradient</strong><small>Shifting colors</small></div></div>
                        <div class="theme-option" data-theme="theme-9" data-name="Fireflies"><div class="theme-preview preview-9"></div><div class="theme-info"><strong>Fireflies</strong><small>Floating flies</small></div></div>
                        <div class="theme-option" data-theme="theme-10" data-name="Cosmic Stars"><div class="theme-preview preview-10"></div><div class="theme-info"><strong>Cosmic Stars</strong><small>Shooting stars</small></div></div>
                        <div class="theme-option" data-theme="theme-11" data-name="Falling Leaves"><div class="theme-preview preview-11"></div><div class="theme-info"><strong>Falling Leaves</strong><small>Autumn magic</small></div></div>
                        <div class="theme-option" data-theme="theme-12" data-name="Bubble Wonder"><div class="theme-preview preview-12"></div><div class="theme-info"><strong>Bubble Wonder</strong><small>Floating bubbles</small></div></div>
                        <div class="theme-option" data-theme="theme-13" data-name="Ocean Waves"><div class="theme-preview preview-13"></div><div class="theme-info"><strong>Ocean Waves</strong><small>Gentle waves</small></div></div>
                        <div class="theme-option" data-theme="theme-14" data-name="Starry Night"><div class="theme-preview preview-14"></div><div class="theme-info"><strong>Starry Night</strong><small>Moon &amp; clouds</small></div></div>
                        <div class="theme-option" data-theme="theme-15" data-name="Misty Pine Forest"><div class="theme-preview preview-15"></div><div class="theme-info"><strong>Misty Pine Forest</strong><small>Drifting fog &amp; pines</small></div></div>
                        <div class="theme-option" data-theme="theme-16" data-name="Sunlit Redwoods"><div class="theme-preview preview-16"></div><div class="theme-info"><strong>Sunlit Redwoods</strong><small>God rays &amp; giants</small></div></div>
                        <div class="theme-option" data-theme="theme-17" data-name="Autumn Maple Trail"><div class="theme-preview preview-17"></div><div class="theme-info"><strong>Autumn Maple Trail</strong><small>Crisp golden leaves</small></div></div>
                        <div class="theme-option" data-theme="theme-18" data-name="Cherry Blossom Path"><div class="theme-preview preview-18"></div><div class="theme-info"><strong>Cherry Blossom Path</strong><small>Floating pink petals</small></div></div>
                        <div class="theme-option" data-theme="theme-19" data-name="Mossy Ancient Woods"><div class="theme-preview preview-19"></div><div class="theme-info"><strong>Mossy Ancient Woods</strong><small>Deep green tranquility</small></div></div>
                        <div class="theme-option" data-theme="theme-20" data-name="Birch Grove Serenity"><div class="theme-preview preview-20"></div><div class="theme-info"><strong>Birch Grove Serenity</strong><small>Bright &amp; calm</small></div></div>
                        <div class="theme-option" data-theme="theme-21" data-name="Wildflower Meadow"><div class="theme-preview preview-21"></div><div class="theme-info"><strong>Wildflower Meadow</strong><small>Colorful blossoms</small></div></div>
                        <div class="theme-option" data-theme="theme-22" data-name="Snowy Pine Forest"><div class="theme-preview preview-22"></div><div class="theme-info"><strong>Snowy Pine Forest</strong><small>Winter wonderland</small></div></div>
                        <div class="theme-option" data-theme="theme-23" data-name="Riverside Autumn"><div class="theme-preview preview-23"></div><div class="theme-info"><strong>Riverside Autumn</strong><small>Reflections &amp; leaves</small></div></div>
                        <div class="theme-option" data-theme="theme-24" data-name="Bamboo Grove"><div class="theme-preview preview-24"></div><div class="theme-info"><strong>Bamboo Grove</strong><small>Zen &amp; serenity</small></div></div>
                        <div class="theme-option" data-theme="theme-25" data-name="Lavender Dreams"><div class="theme-preview preview-25"></div><div class="theme-info"><strong>Lavender Dreams</strong><small>Purple haze</small></div></div>
                        <div class="theme-option" data-theme="theme-26" data-name="Rose Garden"><div class="theme-preview preview-26"></div><div class="theme-info"><strong>Rose Garden</strong><small>Romantic reds</small></div></div>
                        <div class="theme-option" data-theme="theme-27" data-name="Alpine Meadow"><div class="theme-preview preview-27"></div><div class="theme-info"><strong>Alpine Meadow</strong><small>Mountain flowers</small></div></div>
                        <div class="theme-option" data-theme="theme-28" data-name="Rainforest Canopy"><div class="theme-preview preview-28"></div><div class="theme-info"><strong>Rainforest Canopy</strong><small>Lush &amp; exotic</small></div></div>
                        <div class="theme-option" data-theme="theme-29" data-name="Foggy Valley"><div class="theme-preview preview-29"></div><div class="theme-info"><strong>Foggy Valley</strong><small>Mystical mist</small></div></div>
                        <div class="theme-option" data-theme="theme-30" data-name="Enchanted Forest"><div class="theme-preview preview-30"></div><div class="theme-info"><strong>Enchanted Forest</strong><small>Magical glade</small></div></div>
                    </div>
                </div>
            </li>
            <li class="dropdown notification-dropdown">
                <a class="app-nav__item notification-bell" href="#" data-bs-toggle="dropdown">
                    <div class="bell-wrapper"><i class="bi bi-bell-fill" id="bellIcon"></i><span id="notificationBadge" class="notification-badge <?php echo $notification_count > 0 ? 'pulse' : ''; ?>" style="display: <?php echo $notification_count > 0 ? 'flex' : 'none'; ?>;"><?php echo $notification_count > 0 ? ($notification_count > 99 ? '99+' : $notification_count) : '0'; ?></span></div>
                </a>
                <div class="dropdown-menu notification-dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header"><i class="bi bi-bell-fill"></i> Notifications <span class="badge bg-warning text-dark"><?php echo $notification_count; ?> New</span></div>
                    <div class="dropdown-divider"></div>
                    <?php if (empty($latest_announcements)): ?>
                        <div class="dropdown-item text-center text-muted">No new announcements</div>
                    <?php else: ?>
                        <?php foreach ($latest_announcements as $ann): ?>
                            <a class="dropdown-item" href="student/announcements"><i class="bi bi-megaphone-fill text-primary"></i><div class="notification-content"><strong><?php echo h($ann['title']); ?></strong><small><?php echo h(date('M d, H:i', strtotime($ann['create_date']))); ?></small></div></a>
                        <?php endforeach; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-center" href="student/announcements">View All <i class="bi bi-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
            </li>
            <li class="dropdown user-dropdown">
                <a class="app-nav__item" href="#" data-bs-toggle="dropdown" style="gap: 4px;">
                    <div class="user-avatar">
                        <?php if (!empty($student['display_image']) && $student['display_image'] !== 'DEFAULT' && file_exists('../images/students/' . $student['display_image'])): ?>
                            <img src="images/students/<?php echo h($student['display_image']); ?>" class="user-avatar-img" alt="User image">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                        <?php endif; ?>
                        <div class="online-dot"></div>
                    </div>
                    <i class="bi bi-chevron-down user-chevron"></i>
                </a>
                <ul class="dropdown-menu user-menu dropdown-menu-end">
                    <li class="dropdown-header"><div class="user-info"><strong><?php echo h($student['fname'] . ' ' . ($student['lname'] ?? '')); ?></strong><small><?php echo h($student['id']); ?></small></div></li>
                    <li><a class="dropdown-item" href="student/settings"><i class="bi bi-person-circle"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="student/settings"><i class="bi bi-shield-lock"></i> Security Settings</a></li>
                    <li><a class="dropdown-item" href="student/subjects"><i class="bi bi-book-half"></i> My Subjects</a></li>
                    <li><a class="dropdown-item" href="student/results"><i class="bi bi-bar-chart-steps"></i> Academic Results</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout" id="logoutLink"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </header>

    <!-- Rain layer -->
    <div class="robot-rain-layer" id="robotRainLayer"></div>

    <!-- Particle layer -->
    <div class="robot-particles-layer" id="robotParticlesLayer"></div>

    <!-- Robot anchor: head + stop button -->
    <div class="robot-thought-anchor" id="robotThoughtAnchor">
        <div class="robot-head" id="robotHead" title="Click me to chat. Triple-click to jump between results and dashboard.">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="rhSphere" cx="32%" cy="24%" r="82%">
                        <stop offset="0%"   stop-color="#b8e2ff"/>
                        <stop offset="18%"  stop-color="#7dd3fc"/>
                        <stop offset="42%"  stop-color="#38bdf8"/>
                        <stop offset="70%"  stop-color="#2563eb"/>
                        <stop offset="100%" stop-color="#0b2161"/>
                    </radialGradient>
                    <radialGradient id="rhSpec" cx="30%" cy="22%" r="28%">
                        <stop offset="0%"   stop-color="rgba(255,255,255,0.95)"/>
                        <stop offset="60%"  stop-color="rgba(255,255,255,0.25)"/>
                        <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
                    </radialGradient>
                    <radialGradient id="rhBounce" cx="72%" cy="82%" r="55%">
                        <stop offset="0%"   stop-color="rgba(125,211,252,0.65)"/>
                        <stop offset="100%" stop-color="rgba(125,211,252,0)"/>
                    </radialGradient>
                    <radialGradient id="rhFace" cx="50%" cy="48%" r="65%">
                        <stop offset="0%"   stop-color="#0a1e3f"/>
                        <stop offset="70%"  stop-color="#050e22"/>
                        <stop offset="100%" stop-color="#020713"/>
                    </radialGradient>
                    <radialGradient id="rhEye" cx="42%" cy="38%" r="72%">
                        <stop offset="0%"   stop-color="#ffffff"/>
                        <stop offset="45%"  stop-color="#c9f1ff"/>
                        <stop offset="100%" stop-color="#38bdf8"/>
                    </radialGradient>
                    <linearGradient id="rhRim" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%"   stop-color="rgba(186,230,253,0.95)"/>
                        <stop offset="55%"  stop-color="rgba(56,189,248,0.55)"/>
                        <stop offset="100%" stop-color="rgba(37,99,235,0.9)"/>
                    </linearGradient>
                </defs>

                <line x1="36" y1="22" x2="26" y2="9" stroke="#7dd3fc" stroke-width="1.8" stroke-linecap="round" opacity="0.9"/>
                <circle cx="26" cy="9" r="2.8" fill="#e0f2fe"><animate attributeName="opacity" values="0.6;1;0.6" dur="2.4s" repeatCount="indefinite"/></circle>
                <line x1="64" y1="22" x2="74" y2="9" stroke="#7dd3fc" stroke-width="1.8" stroke-linecap="round" opacity="0.9"/>
                <circle cx="74" cy="9" r="2.8" fill="#e0f2fe"><animate attributeName="opacity" values="0.6;1;0.6" dur="2.4s" repeatCount="indefinite"/></circle>

                <ellipse cx="50" cy="52" rx="40" ry="40" fill="url(#rhSphere)"/>
                <ellipse cx="50" cy="52" rx="40" ry="40" fill="url(#rhSpec)" pointer-events="none"/>
                <ellipse cx="50" cy="52" rx="40" ry="40" fill="url(#rhBounce)" pointer-events="none"/>
                <ellipse cx="50" cy="52" rx="40" ry="40" fill="none" stroke="url(#rhRim)" stroke-width="1.4" opacity="0.9"/>
                <ellipse cx="50" cy="52" rx="39" ry="39" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="0.5"/>
                <ellipse cx="32" cy="32" rx="4" ry="2.4" fill="rgba(255,255,255,0.75)" transform="rotate(-25 32 32)"/>

                <ellipse cx="50" cy="54" rx="30" ry="28" fill="url(#rhFace)" opacity="0.96"/>
                <ellipse cx="50" cy="54" rx="30" ry="28" fill="none" stroke="rgba(96,165,250,0.45)" stroke-width="1"/>

                <path class="rh-eyebrow rh-eyebrow-l" d="M 34 42 Q 40 40 46 42" stroke="#7dd3fc" stroke-width="1.6" fill="none" stroke-linecap="round" opacity="0.85"/>
                <path class="rh-eyebrow rh-eyebrow-r" d="M 54 42 Q 60 40 66 42" stroke="#7dd3fc" stroke-width="1.6" fill="none" stroke-linecap="round" opacity="0.85"/>

                <ellipse class="rh-eye rh-left"  cx="40" cy="52" rx="7" ry="8.5" fill="url(#rhEye)"/>
                <ellipse class="rh-eye rh-right" cx="60" cy="52" rx="7" ry="8.5" fill="url(#rhEye)"/>
                <circle class="rh-pupil rh-pupil-l" cx="40" cy="52" r="3.2" fill="#04141b"/>
                <circle class="rh-pupil rh-pupil-r" cx="60" cy="52" r="3.2" fill="#04141b"/>
                <circle cx="38.6" cy="50" r="1.1" fill="#ffffff" opacity="0.95"/>
                <circle cx="58.6" cy="50" r="1.1" fill="#ffffff" opacity="0.95"/>

                <path class="rh-mouth" d="M 42 68 Q 50 74 58 68" stroke="#7dd3fc" stroke-width="1.8" fill="none" stroke-linecap="round"/>

                <circle cx="24" cy="62" r="2.2" fill="#38bdf8" opacity="0.55"/>
                <circle cx="76" cy="62" r="2.2" fill="#38bdf8" opacity="0.55"/>
            </svg>
        </div>
        <div class="robot-stop-btn" id="robotStopBtn" title="Stop analysis" role="button" aria-label="Stop analysis">
            <i class="bi bi-stop-fill"></i>
        </div>
    </div>

    <!-- 3D cloud thought bubble with per-grade weather effects -->
    <div class="robot-thought-bubble tail-right cloud-light" id="robotThoughtBubble">
        <div class="cloud-ripple"></div>
        <!-- Weather effect layers (only one is visible at a time) -->
        <div class="cloud-sun" aria-hidden="true"></div>
        <div class="cloud-rainbow" aria-hidden="true"></div>
        <div class="cloud-bg"></div>
        <div class="cloud-flash" aria-hidden="true"></div>
        <div class="cloud-tail tail-1"></div>
        <div class="cloud-tail tail-2"></div>
        <div class="cloud-rain" id="cloudRain"></div>
        <svg class="cloud-lightning" viewBox="0 0 32 46" xmlns="http://www.w3.org/2000/svg" id="cloudLightning" aria-hidden="true">
            <path d="M 18 0 L 6 24 L 14 24 L 10 46 L 26 18 L 16 18 L 22 0 Z"
                  fill="#fef9c3" stroke="#fde047" stroke-width="1" stroke-linejoin="round"/>
        </svg>
        <div class="cloud-content" id="cloudContent"></div>
    </div>

    <!-- AI Chat -->
    <div class="ai-chat-widget" id="aiChatWidget">
        <div class="ai-chat-header">
            <span><i class="bi bi-chat-square-text-fill"></i> Your Study Assistant</span>
            <div class="header-controls">
                <button id="speakerToggle" class="speaker-btn muted" title="Unmute voice output" aria-label="Toggle voice output"><i class="bi bi-volume-mute"></i></button>
                <button id="closeChatBtn" aria-label="Close chat">&times;</button>
            </div>
        </div>
        <div class="ai-chat-messages" id="aiChatMessages">
            <div class="message ai">Hey <?php echo h($studentFirstName); ?>. I speak English, Kiswahili, and Chinese. My voice is muted for now — click the speaker icon if you would like me to read my replies aloud.</div>
        </div>
        <div class="ai-chat-input">
            <input type="text" id="aiMessageInput" placeholder="Type or speak your question...">
            <button id="sendAiMessage">Send</button>
            <button id="micBtn" class="mic-btn" title="Click to speak your question" aria-label="Voice input"><i class="bi bi-mic"></i></button>
        </div>
    </div>

    <!-- Scroll-to-top button (fixed bottom-right) -->
    <button type="button" id="scrollTopBtn" class="scroll-top-btn" title="Scroll to top" aria-label="Scroll to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script>
        // ========== SIDEBAR / THEMES / NOTIFICATIONS ==========
        document.querySelectorAll('.app-nav__item').forEach(el => el.classList.add('ripple'));
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.app-header');
            if (window.scrollY > 50) header.classList.add('scrolled');
            else header.classList.remove('scrolled');
        });
        function getSidebarElement() { return document.querySelector('.app-sidebar, .sidebar, #sidebar'); }
        function getSidebarOverlay() { return document.querySelector('.app-sidebar__overlay, .sidebar-overlay'); }
        function syncSidebarDisplay() {
            const sidebar = getSidebarElement();
            const overlay = getSidebarOverlay();
            const isHidden = document.body.classList.contains('sidebar-hidden');
            if (sidebar) sidebar.style.display = isHidden ? 'none' : '';
            if (overlay) overlay.style.display = isHidden ? 'none' : '';
        }
        function setSidebarState(collapsed) {
            if (collapsed) { document.body.classList.add('sidebar-hidden'); localStorage.setItem('sidebarCollapsed', 'true'); }
            else { document.body.classList.remove('sidebar-hidden'); localStorage.setItem('sidebarCollapsed', 'false'); }
            syncSidebarDisplay();
        }
        function initSidebarState() {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) setSidebarState(true);
            else { const saved = localStorage.getItem('sidebarCollapsed'); setSidebarState(saved === 'true'); }
        }
        document.addEventListener('DOMContentLoaded', function() {
            initSidebarState();
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) toggleBtn.addEventListener('click', function(e) { e.preventDefault(); setSidebarState(!document.body.classList.contains('sidebar-hidden')); });
            setTimeout(syncSidebarDisplay, 100);
        });
        const observer = new MutationObserver(syncSidebarDisplay);
        observer.observe(document.body, { attributes: true });

        const bellIcon = document.getElementById('bellIcon');
        if (bellIcon) setInterval(() => { bellIcon.classList.add('shake-bell'); setTimeout(() => bellIcon.classList.remove('shake-bell'), 500); }, 2000);

        const themeNames = {
            'theme-1':'Mystical Forest','theme-2':'Glow Particles','theme-3':'Fluid Up','theme-4':'Animated Waves',
            'theme-5':'Neon Bulbs','theme-6':'3D Bubbles','theme-7':'Stars & Shooting Stars','theme-8':'Rainbow Gradient',
            'theme-9':'Fireflies','theme-10':'Cosmic Stars','theme-11':'Falling Leaves','theme-12':'Bubble Wonder',
            'theme-13':'Ocean Waves','theme-14':'Starry Night','theme-15':'Misty Pine Forest','theme-16':'Sunlit Redwoods',
            'theme-17':'Autumn Maple Trail','theme-18':'Cherry Blossom Path','theme-19':'Mossy Ancient Woods','theme-20':'Birch Grove Serenity',
            'theme-21':'Wildflower Meadow','theme-22':'Snowy Pine Forest','theme-23':'Riverside Autumn','theme-24':'Bamboo Grove',
            'theme-25':'Lavender Dreams','theme-26':'Rose Garden','theme-27':'Alpine Meadow','theme-28':'Rainforest Canopy',
            'theme-29':'Foggy Valley','theme-30':'Enchanted Forest'
        };
        function updateThemeButtonLabel(themeId) {
            const btn = document.getElementById('themeButtonLabel');
            if (btn && themeNames[themeId]) btn.textContent = themeNames[themeId];
            else if (btn) btn.textContent = 'Themes';
        }
        function createFallingLeaves() {
            const container = document.querySelector('.theme-11-container .leaves-container');
            if (!container || container._leavesCreated) return;
            for (let i=0;i<30;i++) {
                const leaf = document.createElement('div');
                leaf.classList.add('leaf');
                leaf.style.left = Math.random()*100+'%';
                leaf.style.animationDuration = 8+Math.random()*6+'s';
                leaf.style.animationDelay = Math.random()*10+'s';
                container.appendChild(leaf);
            }
            container._leavesCreated = true;
        }
        function initBubbleCanvas() {
            let canvas = document.getElementById('bubbleCanvasTheme');
            if (!canvas || canvas._bubbleAnim) return;
            let ctx = canvas.getContext('2d');
            let width = window.innerWidth, height = window.innerHeight;
            let bubbles = [], time = 0;
            function resize() { width = window.innerWidth; height = window.innerHeight; canvas.width = width; canvas.height = height; }
            window.addEventListener('resize', resize); resize();
            class Bubble {
                constructor() { this.x = Math.random()*width; this.y = Math.random()*height; this.r = Math.random()*35+15; this.vx = (Math.random()-0.5)*0.08; this.vy = (Math.random()-0.5)*0.08; this.hue = Math.random()*360; this.op = Math.random()*0.25+0.15; }
                update(t) { this.x += this.vx + Math.sin(t*0.01+this.hue)*0.1; this.y += this.vy + Math.cos(t*0.007+this.hue)*0.1; if(this.x+this.r<0) this.x=width+this.r; if(this.x-this.r>width) this.x=-this.r; if(this.y+this.r<0) this.y=height+this.r; if(this.y-this.r>height) this.y=-this.r; }
                draw() { ctx.save(); ctx.beginPath(); let grad = ctx.createRadialGradient(this.x-this.r*0.35, this.y-this.r*0.35, this.r*0.2, this.x, this.y, this.r); grad.addColorStop(0, `hsla(${this.hue},85%,75%,${this.op})`); grad.addColorStop(0.5, `hsla(${this.hue},80%,65%,${this.op*0.9})`); grad.addColorStop(1, `hsla(${this.hue},70%,45%,${this.op*0.5})`); ctx.fillStyle = grad; ctx.arc(this.x,this.y,this.r,0,Math.PI*2); ctx.fill(); ctx.beginPath(); ctx.arc(this.x-this.r*0.25, this.y-this.r*0.25, this.r*0.25,0,Math.PI*2); ctx.fillStyle = `rgba(255,255,255,${this.op*0.9})`; ctx.fill(); ctx.restore(); }
            }
            function animate() { if(!ctx) return; ctx.clearRect(0,0,width,height); time+=0.02; bubbles.forEach(b=>{b.update(time); b.draw();}); requestAnimationFrame(animate); }
            for(let i=0;i<35;i++) bubbles.push(new Bubble());
            animate(); canvas._bubbleAnim = true;
        }
        function applyTheme(themeId) {
            let themes = []; for(let i=1;i<=30;i++) themes.push('theme-'+i);
            document.body.classList.remove(...themes); document.body.classList.add(themeId);
            document.querySelectorAll('.theme-container').forEach(c=>c.style.display='none');
            let selected = document.querySelector(`.${themeId}-container`); if(selected) selected.style.display='block';
            localStorage.setItem('site_theme', themeId);
            document.querySelectorAll('.theme-option').forEach(opt=>{ if(opt.getAttribute('data-theme')===themeId) opt.classList.add('active'); else opt.classList.remove('active'); });
            updateThemeButtonLabel(themeId);
            if(themeId==='theme-6') initBubbleCanvas();
            if(themeId==='theme-11') createFallingLeaves();
            if (typeof refreshCloudTheme === 'function') setTimeout(refreshCloudTheme, 150);
        }
        (function() {
            let currentTheme = localStorage.getItem('site_theme') || 'theme-26';
            applyTheme(currentTheme);
            document.querySelectorAll('.theme-option').forEach(opt=>{
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    let tid = this.getAttribute('data-theme'); applyTheme(tid);
                    let dd = this.closest('.dropdown-menu');
                    if(dd && dd.previousElementSibling) { let bs = bootstrap.Dropdown.getInstance(dd.previousElementSibling); if(bs) bs.hide(); }
                });
            });
        })();

        function updateNotificationBadge() {
            fetch('student/ajax/get_notification_count', { method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'} })
                .then(r=>r.json()).then(d=>{
                    let badge = document.getElementById('notificationBadge');
                    if(badge && d.count!==undefined) {
                        let oldCount = parseInt(badge.innerText) || 0;
                        if(d.count > oldCount && oldCount > 0) { if(bellIcon) bellIcon.classList.add('shake-bell'); setTimeout(()=>{ if(bellIcon) bellIcon.classList.remove('shake-bell'); },500); }
                        if(d.count>0) { badge.style.display='flex'; badge.innerText = d.count>99?'99+':d.count; badge.classList.add('pulse'); }
                        else { badge.style.display='none'; badge.classList.remove('pulse'); }
                    }
                }).catch(()=>{});
        }
        setInterval(updateNotificationBadge, 30000); updateNotificationBadge();

        // =========================================================
        //  SCROLL TO TOP BUTTON
        // =========================================================
        (function initScrollTopButton() {
            const scrollBtn = document.getElementById('scrollTopBtn');
            if (!scrollBtn) return;

            let ticking = false;
            function updateScrollButton() {
                ticking = false;
                // Hide when the chat widget is open on small screens to avoid overlap
                const chatOpen = document.getElementById('aiChatWidget')?.classList.contains('open');
                const smallScreen = window.innerWidth <= 768;
                if (chatOpen && smallScreen) {
                    scrollBtn.classList.remove('show');
                    return;
                }
                if (window.scrollY > 300) scrollBtn.classList.add('show');
                else scrollBtn.classList.remove('show');
            }

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(updateScrollButton);
                }
            }, { passive: true });
            window.addEventListener('resize', updateScrollButton, { passive: true });

            scrollBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            // Initial state (in case the page loads scrolled)
            updateScrollButton();
        })();

        // =========================================================
        //  ROBOT ENGINE
        // =========================================================
        const robotAnchor   = document.getElementById('robotThoughtAnchor');
        const robotHead     = document.getElementById('robotHead');
        const robotStopBtn  = document.getElementById('robotStopBtn');
        const thoughtEl     = document.getElementById('robotThoughtBubble');
        const cloudContentEl = document.getElementById('cloudContent');
        const cloudRainEl   = document.getElementById('cloudRain');
        const particlesLayer = document.getElementById('robotParticlesLayer');
        const rainLayer     = document.getElementById('robotRainLayer');
        const STUDENT_NAME  = <?php echo json_encode($studentFirstName); ?>;
        const CURRENT_PAGE  = <?php echo json_encode($pageInfo['name']); ?>;
        const IS_RESULTS_PAGE = /result/i.test(CURRENT_PAGE);

        let targetEl = null;
        let anchoredEl = null;

        let analysisRunning = false;
        let analysisStopped = false;
        let stopBtnAutoHideTimer = null;
        let activityRunning = false;

        const ORIGINAL_RIGHT = '22px';

        // ---------- ADAPTIVE CLOUD THEME DETECTION ----------
        function detectSurfaceTheme() {
            const rect = thoughtEl.getBoundingClientRect();
            const points = [
                [rect.left + rect.width * 0.5, rect.top + rect.height * 0.5],
                [rect.left - 20, rect.top + rect.height * 0.5],
                [rect.right + 20, rect.top + rect.height * 0.5],
                [rect.left + rect.width * 0.5, rect.top - 20],
                [rect.left + rect.width * 0.5, rect.bottom + 20]
            ];
            const prevPE = thoughtEl.style.pointerEvents;
            thoughtEl.style.pointerEvents = 'none';
            let totalLum = 0, samples = 0;
            for (const [x, y] of points) {
                if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) continue;
                const stack = document.elementsFromPoint(x, y) || [];
                for (const el of stack) {
                    if (thoughtEl.contains(el) || el === thoughtEl) continue;
                    let cur = el, found = false;
                    while (cur && cur !== document.documentElement) {
                        const bg = getComputedStyle(cur).backgroundColor;
                        const m = bg.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/);
                        if (m) {
                            const r = parseFloat(m[1]), g = parseFloat(m[2]), b = parseFloat(m[3]);
                            const a = m[4] !== undefined ? parseFloat(m[4]) : 1;
                            if (a > 0.2) {
                                const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                                totalLum += lum; samples++; found = true; break;
                            }
                        }
                        cur = cur.parentElement;
                    }
                    if (found) break;
                }
            }
            thoughtEl.style.pointerEvents = prevPE;
            if (samples === 0) {
                const bg = getComputedStyle(document.body).backgroundColor;
                const m = bg.match(/\d+/g);
                if (m) { const lum = 0.2126 * +m[0] + 0.7152 * +m[1] + 0.0722 * +m[2]; return lum > 140 ? 'light' : 'dark'; }
                return 'dark';
            }
            const avgLum = totalLum / samples;
            return avgLum > 150 ? 'light' : 'dark';
        }

        function refreshCloudTheme() {
            if (!thoughtEl) return;
            const hasSun = thoughtEl.classList.contains('cloud-sun');
            const hasRainbow = thoughtEl.classList.contains('cloud-rainbow');
            const hasHeavy = thoughtEl.classList.contains('cloud-rain-heavy');
            const hasStorm = thoughtEl.classList.contains('cloud-storm');

            thoughtEl.classList.remove('cloud-light', 'cloud-dark');
            const surface = detectSurfaceTheme();
            thoughtEl.classList.add(surface === 'light' ? 'cloud-dark' : 'cloud-light');

            if (hasSun) thoughtEl.classList.add('cloud-sun');
            if (hasRainbow) thoughtEl.classList.add('cloud-rainbow');
            if (hasHeavy) thoughtEl.classList.add('cloud-rain-heavy');
            if (hasStorm) thoughtEl.classList.add('cloud-storm');
        }

        // ---------- CLOUD WEATHER EFFECTS ----------
        function clearCloudWeather() {
            if (!thoughtEl) return;
            thoughtEl.classList.remove('cloud-sun','cloud-rainbow','cloud-rain-heavy','cloud-storm');
            if (cloudRainEl) cloudRainEl.innerHTML = '';
        }
        function applyCloudSun() {
            if (!thoughtEl) return;
            clearCloudWeather();
            thoughtEl.classList.add('cloud-sun');
        }
        function applyCloudRainbow() {
            if (!thoughtEl) return;
            clearCloudWeather();
            thoughtEl.classList.add('cloud-rainbow');
        }
        function applyCloudRainHeavy() {
            if (!thoughtEl) return;
            clearCloudWeather();
            thoughtEl.classList.add('cloud-rain-heavy');
            if (cloudRainEl) {
                cloudRainEl.innerHTML = '';
                const vw = window.innerWidth;
                const rainCount = vw < 600 ? 16 : 26;
                for (let i = 0; i < rainCount; i++) {
                    const drop = document.createElement('div');
                    drop.className = 'rain-streak';
                    drop.style.left = (4 + Math.random() * 92) + '%';
                    drop.style.animationDuration = (0.55 + Math.random() * 0.5) + 's';
                    drop.style.animationDelay = (Math.random() * 0.9) + 's';
                    drop.style.height = (18 + Math.random() * 12) + 'px';
                    cloudRainEl.appendChild(drop);
                }
            }
        }
        function applyCloudStorm() {
            if (!thoughtEl) return;
            clearCloudWeather();
            thoughtEl.classList.add('cloud-storm');
            if (cloudRainEl) {
                cloudRainEl.innerHTML = '';
                const vw = window.innerWidth;
                const rainCount = vw < 600 ? 16 : 26;
                for (let i = 0; i < rainCount; i++) {
                    const drop = document.createElement('div');
                    drop.className = 'rain-streak';
                    drop.style.left = (4 + Math.random() * 92) + '%';
                    drop.style.animationDuration = (0.5 + Math.random() * 0.5) + 's';
                    drop.style.animationDelay = (Math.random() * 0.9) + 's';
                    drop.style.height = (16 + Math.random() * 14) + 'px';
                    cloudRainEl.appendChild(drop);
                }
            }
        }
        function applyGradeCloudEffect(grade) {
            if (grade === 'A') applyCloudSun();
            else if (grade === 'B') applyCloudRainbow();
            else if (grade === 'C') { clearCloudWeather(); launchCloudFireworks(); }
            else if (grade === 'D') applyCloudRainHeavy();
            else if (grade === 'F') applyCloudStorm();
            else clearCloudWeather();
        }

        // ---------- CURSOR-TRACKING EYES ----------
        (function initEyeTracking() {
            if (!robotHead) return;
            const pupils = robotHead.querySelectorAll('.rh-pupil');
            if (!pupils.length) return;
            let rafId = null, pendingX = 0, pendingY = 0;
            const MAX_MOVE = 2.6;
            function apply() {
                rafId = null;
                pupils.forEach(p => {
                    p.style.setProperty('--eye-x', pendingX.toFixed(2) + 'px');
                    p.style.setProperty('--eye-y', pendingY.toFixed(2) + 'px');
                });
            }
            function update(e) {
                let cx = 0, cy = 0;
                if (e.touches && e.touches.length) { cx = e.touches[0].clientX; cy = e.touches[0].clientY; }
                else { cx = e.clientX; cy = e.clientY; }
                const r = robotHead.getBoundingClientRect();
                const headCX = r.left + r.width / 2, headCY = r.top + r.height / 2;
                const dx = cx - headCX, dy = cy - headCY;
                const dist = Math.hypot(dx, dy) || 1;
                const magnitude = Math.min(dist / 220, 1) * MAX_MOVE;
                pendingX = (dx / dist) * magnitude;
                pendingY = (dy / dist) * magnitude;
                if (!rafId) rafId = requestAnimationFrame(apply);
            }
            document.addEventListener('mousemove', update, { passive: true });
            document.addEventListener('touchmove', update, { passive: true });
            setInterval(() => {
                pendingX = (Math.random() - 0.5) * 1.2;
                pendingY = (Math.random() - 0.5) * 1.2;
                if (!rafId) rafId = requestAnimationFrame(apply);
            }, 4200);
        })();

        // ---------- STOP BUTTON ----------
        function showStopButton() {
            if (robotStopBtn) robotStopBtn.classList.add('show');
            if (stopBtnAutoHideTimer) { clearTimeout(stopBtnAutoHideTimer); stopBtnAutoHideTimer = null; }
        }
        function showStopButtonWithTimeout(ms) {
            showStopButton();
            if (stopBtnAutoHideTimer) { clearTimeout(stopBtnAutoHideTimer); stopBtnAutoHideTimer = null; }
            stopBtnAutoHideTimer = setTimeout(() => {
                if (!analysisStopped) { if (robotStopBtn) robotStopBtn.classList.remove('show'); }
                stopBtnAutoHideTimer = null;
            }, ms || 5000);
        }
        function hideStopButton() {
            if (robotStopBtn) robotStopBtn.classList.remove('show');
            if (stopBtnAutoHideTimer) { clearTimeout(stopBtnAutoHideTimer); stopBtnAutoHideTimer = null; }
        }

        // ---------- TRIPLE-CLICK ROUTING ----------
        function navigateByTripleClick() {
            if (chatWidget && chatWidget.classList.contains('open')) chatWidget.classList.remove('open');
            hideStopButton();
            hideThought();
            if (analysisRunning) { analysisStopped = true; analysisRunning = false; }
            let destination, label;
            if (IS_RESULTS_PAGE) { destination = 'student'; label = 'Taking you to your dashboard'; }
            else { destination = 'student/results'; label = 'Taking you to your results'; }
            setEmotion('celebrate', 1600);
            showThought(label + ', ' + STUDENT_NAME + '. Hold on.', 'celebrate', 1600, null);
            setTimeout(() => { window.location.href = destination; }, 750);
        }

        // ---------- SINGLE CLICK ----------
        function handleSingleClick() {
            if (analysisRunning) {
                showStopButtonWithTimeout(5000);
                showThought('Here is the stop button. Click it within five seconds if you want me to stop. Otherwise I will keep it out of your way.', 'curious', 4200, null);
                return;
            }
            hideThought();
            if (targetEl) { targetEl.classList.remove('robot-target-highlight'); targetEl = null; }
            toggleChat();
        }

        // ---------- CLICK HANDLER ----------
        let robotClickCount = 0;
        let robotClickResetTimer = null;
        let robotSingleClickTimer = null;

        if (robotHead) {
            robotHead.addEventListener('click', function(e) {
                e.stopPropagation();
                robotClickCount++;
                if (robotClickResetTimer) clearTimeout(robotClickResetTimer);
                if (robotSingleClickTimer) clearTimeout(robotSingleClickTimer);
                if (robotClickCount >= 3) { robotClickCount = 0; navigateByTripleClick(); return; }
                if (robotClickCount === 1) {
                    robotSingleClickTimer = setTimeout(() => { if (robotClickCount === 1) handleSingleClick(); }, 320);
                }
                robotClickResetTimer = setTimeout(() => { robotClickCount = 0; }, 720);
            });
        }

        if (robotStopBtn) {
            robotStopBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!analysisRunning) return;
                if (stopBtnAutoHideTimer) { clearTimeout(stopBtnAutoHideTimer); stopBtnAutoHideTimer = null; }
                analysisStopped = true;
                hideStopButton();
                setEmotion('surprised', 2400);
                showThought('Analysis stopped at your request, ' + STUDENT_NAME + '. You are back in control.', 'surprised', 3400, null);
            });
        }

        // ---------- EMOTIONS ----------
        const ALL_EMOTIONS = [
            'emo-happy','emo-thinking','emo-surprised','emo-proud','emo-curious','emo-celebrate',
            'emo-love','emo-furious','emo-worried','emo-kiss','emo-disappointed','emo-encouraged',
            'emo-sneeze','emo-sleep','emo-chore','emo-sing','emo-typing','emo-sad'
        ];
        let emotionTimer = null;
        function setEmotion(emo, ms) {
            robotAnchor.classList.remove(...ALL_EMOTIONS);
            if (emo) robotAnchor.classList.add('emo-' + emo);
            clearTimeout(emotionTimer);
            emotionTimer = setTimeout(() => {
                robotAnchor.classList.remove(...ALL_EMOTIONS);
            }, ms || 3000);
        }

        // ---------- FREQUENT IDLE EXPRESSIONS ----------
        const IDLE_BURSTS = [
            ['happy'], ['curious'], ['thinking'], ['proud'], ['surprised'],
            ['encouraged'], ['kiss'], ['celebrate'],
            ['happy', 'kiss'], ['curious', 'happy'], ['thinking', 'encouraged'], ['surprised', 'curious']
        ];
        function pickIdleBurst() { return IDLE_BURSTS[Math.floor(Math.random() * IDLE_BURSTS.length)]; }
        function isRobotBusy() {
            return analysisRunning || activityRunning
                || thoughtEl.classList.contains('show')
                || (chatWidget && chatWidget.classList.contains('open'))
                || document.visibilityState !== 'visible';
        }
        async function idleEmotionLoop() {
            const wait = 3200 + Math.random() * 2200;
            await new Promise(r => setTimeout(r, wait));
            if (!isRobotBusy()) {
                const burst = pickIdleBurst();
                for (let i = 0; i < burst.length; i++) {
                    const emo = burst[i];
                    const dur = 1200 + Math.random() * 1200;
                    setEmotion(emo, dur);
                    await new Promise(r => setTimeout(r, dur + 200));
                    if (isRobotBusy()) break;
                }
            }
            idleEmotionLoop();
        }

        // ---------- PARTICLES ----------
        function spawnFrom(containerEl, opts) {
            if (!containerEl || !particlesLayer) return;
            const r = containerEl.getBoundingClientRect();
            const cx = r.left + r.width / 2;
            const cy = r.top + r.height / 2;
            const count = opts.count || 10;
            const symbols = opts.symbols || ['*'];
            const colors = opts.colors || ['#7dd3fc'];
            const spread = opts.spread || Math.PI;
            const baseAngle = opts.baseAngle != null ? opts.baseAngle : -Math.PI / 2;
            const minDist = opts.minDist || 60;
            const maxDist = opts.maxDist || 160;
            const minSize = opts.minSize || 14;
            const maxSize = opts.maxSize || 26;
            const cls = opts.cls || '';
            for (let i = 0; i < count; i++) {
                const p = document.createElement('div');
                p.className = 'robot-particle' + (opts.firework ? ' firework' : '') + (cls ? ' ' + cls : '');
                p.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                p.style.left = cx + 'px';
                p.style.top = cy + 'px';
                p.style.color = colors[Math.floor(Math.random() * colors.length)];
                p.style.fontSize = (minSize + Math.random() * (maxSize - minSize)) + 'px';
                const angle = baseAngle + (Math.random() - 0.5) * spread;
                const dist = minDist + Math.random() * (maxDist - minDist);
                p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                p.style.setProperty('--pr', (Math.random() * 720 - 360) + 'deg');
                p.style.animationDelay = (Math.random() * 0.35) + 's';
                particlesLayer.appendChild(p);
                setTimeout(() => p.remove(), 4000);
            }
        }
        function spawnHearts() { spawnFrom(robotHead, { count: 12, symbols: ['\u2665','\u2764','\u2661'], colors: ['#ef476f','#f472b6','#ec4899','#fb7185'], spread: Math.PI*1.2, baseAngle: -Math.PI/2, minDist: 80, maxDist: 220, minSize: 18, maxSize: 30 }); }
        function spawnStars() { spawnFrom(robotHead, { count: 8, symbols: ['\u2605','\u2726','\u2728','\u2b50'], colors: ['#fbbf24','#facc15','#fde047','#fcd34d'], spread: Math.PI*1.1, baseAngle: -Math.PI/2, minDist: 70, maxDist: 180, minSize: 16, maxSize: 26 }); }
        function spawnAnger() { spawnFrom(robotHead, { count: 8, symbols: ['!','\u26A0','\u2716'], colors: ['#ef4444','#dc2626','#b91c1c'], spread: Math.PI*1.4, baseAngle: -Math.PI/2, minDist: 60, maxDist: 170, minSize: 16, maxSize: 26 }); }
        function spawnWorry() { spawnFrom(robotHead, { count: 6, symbols: ['?','\u2026','\u25CB'], colors: ['#fbbf24','#d97706','#f59e0b'], spread: Math.PI*1.1, baseAngle: -Math.PI/2, minDist: 60, maxDist: 150, minSize: 16, maxSize: 24 }); }
        function launchFireworks() {
            if (!particlesLayer) return;
            const vw = window.innerWidth, vh = window.innerHeight;
            const bursts = 6;
            for (let b = 0; b < bursts; b++) {
                setTimeout(() => {
                    const cx = vw * (0.1 + Math.random() * 0.8);
                    const cy = vh * (0.15 + Math.random() * 0.55);
                    const n = 26 + Math.floor(Math.random() * 18);
                    const hue = Math.floor(Math.random() * 360);
                    for (let i = 0; i < n; i++) {
                        const p = document.createElement('div');
                        p.className = 'robot-particle firework';
                        p.textContent = ['\u2022','\u2726','\u2731','\u25CF'][Math.floor(Math.random() * 4)];
                        p.style.left = cx + 'px'; p.style.top = cy + 'px';
                        p.style.color = `hsl(${(hue + Math.random() * 60) % 360}, 95%, ${65 + Math.random() * 15}%)`;
                        p.style.fontSize = (12 + Math.random() * 8) + 'px';
                        const angle = (i / n) * Math.PI * 2 + Math.random() * 0.25;
                        const dist = 100 + Math.random() * 150;
                        p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                        p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                        p.style.setProperty('--pr', (Math.random() * 360) + 'deg');
                        p.style.animationDuration = '1.7s';
                        particlesLayer.appendChild(p);
                        setTimeout(() => p.remove(), 2100);
                    }
                }, b * 340);
            }
        }
        function launchCloudFireworks() {
            if (!particlesLayer || !thoughtEl) return;
            const rect = thoughtEl.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const bursts = 4;
            for (let b = 0; b < bursts; b++) {
                setTimeout(() => {
                    const hue = Math.floor(Math.random() * 360);
                    const n = 22 + Math.floor(Math.random() * 10);
                    for (let i = 0; i < n; i++) {
                        const p = document.createElement('div');
                        p.className = 'robot-particle firework';
                        p.textContent = ['\u2022','\u2726','\u2731','\u25CF','\u2735'][Math.floor(Math.random() * 5)];
                        p.style.left = cx + 'px'; p.style.top = cy + 'px';
                        p.style.color = `hsl(${(hue + Math.random() * 80) % 360}, 95%, ${65 + Math.random() * 20}%)`;
                        p.style.fontSize = (10 + Math.random() * 8) + 'px';
                        const angle = (i / n) * Math.PI * 2 + Math.random() * 0.3;
                        const dist = 70 + Math.random() * 130;
                        p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                        p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                        p.style.setProperty('--pr', (Math.random() * 360) + 'deg');
                        p.style.animationDuration = '1.6s';
                        particlesLayer.appendChild(p);
                        setTimeout(() => p.remove(), 2000);
                    }
                }, b * 400);
            }
        }

        // ---------- ACTIVITY PARTICLES ----------
        function spawnSneezeCloud() {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cx = r.left + 6, cy = r.top + r.height * 0.55;
            const symbols = ['\u2022','\u25E6','\u25CB','\u2609','a','c','h','o','o'];
            for (let i = 0; i < 22; i++) {
                const p = document.createElement('div');
                p.className = 'robot-particle sneeze';
                p.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                p.style.left = cx + 'px'; p.style.top = cy + 'px';
                p.style.color = ['#e0f2fe','#bae6fd','#7dd3fc','#ffffff'][Math.floor(Math.random() * 4)];
                p.style.fontSize = (8 + Math.random() * 18) + 'px';
                const angle = Math.PI + (Math.random() - 0.5) * 0.9;
                const dist = 60 + Math.random() * 180;
                p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                p.style.setProperty('--py', (Math.sin(angle) * dist - 40 + Math.random() * 40) + 'px');
                p.style.setProperty('--pr', (Math.random() * 720 - 360) + 'deg');
                p.style.animationDelay = (Math.random() * 0.15) + 's';
                particlesLayer.appendChild(p);
                setTimeout(() => p.remove(), 1600);
            }
        }
        function spawnSleepZzzs() {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cx = r.left + r.width - 4, cy = r.top + 10;
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    const p = document.createElement('div');
                    p.className = 'robot-particle slow-up';
                    p.textContent = 'Z';
                    p.style.left = cx + 'px'; p.style.top = cy + 'px';
                    p.style.color = ['#c7d2fe','#a5b4fc','#93c5fd','#ffffff'][Math.floor(Math.random() * 4)];
                    p.style.fontSize = (16 + i * 4 + Math.random() * 6) + 'px';
                    p.style.fontWeight = '700';
                    p.style.textShadow = '0 0 12px rgba(165,180,252,0.9)';
                    const angle = -Math.PI / 2 + (Math.random() - 0.5) * 0.3;
                    const dist = 60 + Math.random() * 60;
                    p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                    p.style.setProperty('--py', (Math.sin(angle) * dist) + 'px');
                    p.style.setProperty('--pr', (Math.random() * 60 - 30) + 'deg');
                    particlesLayer.appendChild(p);
                    setTimeout(() => p.remove(), 4200);
                }, i * 550);
            }
        }
        function spawnChoreSwishes() {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cy = r.top + r.height + 8;
            const sides = [r.left - 30, r.left + r.width + 30];
            sides.forEach((cx, idx) => {
                for (let i = 0; i < 6; i++) {
                    setTimeout(() => {
                        const p = document.createElement('div');
                        p.className = 'robot-particle slow-up';
                        p.textContent = idx === 0 ? '\u2571' : '\u2572';
                        p.style.left = cx + 'px'; p.style.top = cy + 'px';
                        p.style.color = ['#fcd34d','#fbbf24','#fde68a'][Math.floor(Math.random() * 3)];
                        p.style.fontSize = (14 + Math.random() * 8) + 'px';
                        p.style.fontWeight = '700';
                        const angle = -Math.PI / 2 + (idx === 0 ? 0.3 : -0.3);
                        const dist = 50 + Math.random() * 40;
                        p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                        p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                        p.style.setProperty('--pr', (Math.random() * 60 - 30) + 'deg');
                        particlesLayer.appendChild(p);
                        setTimeout(() => p.remove(), 2600);
                    }, i * 130 + idx * 65);
                }
            });
        }
        function spawnSingingNotes() {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cx = r.left + r.width / 2, cy = r.top + 6;
            const notes = ['\u266A','\u266B','\u266C','\u2669'];
            for (let i = 0; i < 7; i++) {
                setTimeout(() => {
                    const p = document.createElement('div');
                    p.className = 'robot-particle slow-up';
                    p.textContent = notes[Math.floor(Math.random() * notes.length)];
                    p.style.left = (cx + (Math.random() - 0.5) * 40) + 'px';
                    p.style.top = cy + 'px';
                    p.style.color = ['#a78bfa','#c4b5fd','#8b5cf6','#f472b6','#fbcfe8'][Math.floor(Math.random() * 5)];
                    p.style.fontSize = (18 + Math.random() * 10) + 'px';
                    p.style.textShadow = '0 0 12px currentColor';
                    const angle = -Math.PI / 2 + (Math.random() - 0.5) * 0.6;
                    const dist = 80 + Math.random() * 80;
                    p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                    p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                    p.style.setProperty('--pr', (Math.random() * 90 - 45) + 'deg');
                    particlesLayer.appendChild(p);
                    setTimeout(() => p.remove(), 4200);
                }, i * 340);
            }
        }
        function spawnTypingKeys() {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cx = r.left + r.width / 2, cy = r.top + r.height + 6;
            const keys = ['\u2318','\u2325','\u21E7','\u21B5','\u2423','A','B','1','2','3'];
            for (let i = 0; i < 10; i++) {
                setTimeout(() => {
                    const p = document.createElement('div');
                    p.className = 'robot-particle';
                    p.textContent = keys[Math.floor(Math.random() * keys.length)];
                    p.style.left = (cx + (Math.random() - 0.5) * 60) + 'px';
                    p.style.top = cy + 'px';
                    p.style.color = ['#7dd3fc','#bae6fd','#e0f2fe','#93c5fd'][Math.floor(Math.random() * 4)];
                    p.style.fontSize = (11 + Math.random() * 6) + 'px';
                    p.style.fontWeight = '700';
                    p.style.animationDuration = '1.6s';
                    const angle = Math.PI / 2 + (Math.random() - 0.5) * 1.4;
                    const dist = 40 + Math.random() * 70;
                    p.style.setProperty('--px', Math.cos(angle) * dist + 'px');
                    p.style.setProperty('--py', Math.sin(angle) * dist + 'px');
                    p.style.setProperty('--pr', (Math.random() * 360 - 180) + 'deg');
                    particlesLayer.appendChild(p);
                    setTimeout(() => p.remove(), 1800);
                }, i * 90);
            }
        }
        function spawnSparkleBurst() {
            spawnFrom(robotHead, {
                count: 18,
                symbols: ['\u2728','\u2726','\u2605','\u2731'],
                colors: ['#fde68a','#fcd34d','#fbbf24','#7dd3fc','#bae6fd','#a7f3d0'],
                spread: Math.PI * 1.8, baseAngle: -Math.PI / 2,
                minDist: 60, maxDist: 200, minSize: 12, maxSize: 22
            });
        }
        function spawnSadDroplets() {
            spawnFrom(robotHead, {
                count: 8,
                symbols: ['\uD83D\uDCA7', '\u25CF'],
                colors: ['#7dd3fc','#bae6fd','#93c5fd','#60a5fa'],
                spread: Math.PI * 0.7, baseAngle: -Math.PI / 2,
                minDist: 30, maxDist: 90, minSize: 8, maxSize: 14
            });
        }

        // ---------- RAIN ----------
        function startRain() {
            if (!rainLayer) return;
            rainLayer.classList.add('active');
            rainLayer.innerHTML = '';
            for (let i = 0; i < 90; i++) {
                const drop = document.createElement('div');
                drop.className = 'rain-drop';
                drop.style.left = (Math.random() * 100) + '%';
                drop.style.animationDuration = (0.6 + Math.random() * 0.7) + 's';
                drop.style.animationDelay = (Math.random() * 0.7) + 's';
                drop.style.height = (10 + Math.random() * 12) + 'px';
                drop.style.opacity = (0.5 + Math.random() * 0.5);
                rainLayer.appendChild(drop);
            }
        }
        function stopRain() {
            if (!rainLayer) return;
            rainLayer.classList.remove('active');
            setTimeout(() => { if (rainLayer) rainLayer.innerHTML = ''; }, 800);
        }

        // ---------- SPECIAL ACTIVITIES ----------
        async function activitySneeze() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('curious', 700);
            await new Promise(r => setTimeout(r, 700));
            setEmotion('sneeze', 1900);
            setTimeout(() => spawnSneezeCloud(), 400);
            await new Promise(r => setTimeout(r, 1900));
            setEmotion('surprised', 900);
            await new Promise(r => setTimeout(r, 900));
            setEmotion('happy', 1500);
            await new Promise(r => setTimeout(r, 1500));
            activityRunning = false;
        }
        async function activitySleep() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('sleep', 6000);
            spawnSleepZzzs();
            await new Promise(r => setTimeout(r, 6000));
            setEmotion('surprised', 900);
            await new Promise(r => setTimeout(r, 900));
            setEmotion('happy', 1200);
            await new Promise(r => setTimeout(r, 1200));
            activityRunning = false;
        }
        async function activityChore() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('chore', 5500);
            spawnChoreSwishes();
            await new Promise(r => setTimeout(r, 5500));
            setEmotion('proud', 1500);
            await new Promise(r => setTimeout(r, 1500));
            activityRunning = false;
        }
        async function activitySing() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('sing', 6500);
            spawnSingingNotes();
            await new Promise(r => setTimeout(r, 6500));
            setEmotion('happy', 1500);
            await new Promise(r => setTimeout(r, 1500));
            activityRunning = false;
        }
        async function activityTyping() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('typing', 4500);
            spawnTypingKeys();
            await new Promise(r => setTimeout(r, 4500));
            setEmotion('proud', 1500);
            await new Promise(r => setTimeout(r, 1500));
            activityRunning = false;
        }
        async function activityRainRun() {
            if (isRobotBusy()) return;
            activityRunning = true;
            setEmotion('surprised', 1000);
            startRain();
            await new Promise(r => setTimeout(r, 800));
            robotAnchor.classList.add('robot-running');
            const vw = window.innerWidth;
            const leftOffset = Math.min(vw * 0.42, vw - 240);
            robotAnchor.style.right = leftOffset + 'px';
            await new Promise(r => setTimeout(r, 3000));
            robotAnchor.style.right = ORIGINAL_RIGHT;
            await new Promise(r => setTimeout(r, 3000));
            stopRain();
            robotAnchor.classList.remove('robot-running');
            robotAnchor.classList.add('robot-shine');
            spawnSparkleBurst();
            await new Promise(r => setTimeout(r, 3000));
            robotAnchor.classList.remove('robot-shine');
            activityRunning = false;
        }

        const ACTIVITIES = [
            { fn: activitySneeze,  weight: 2, minGap: 12000, maxGap: 20000 },
            { fn: activitySleep,   weight: 1, minGap: 25000, maxGap: 45000 },
            { fn: activityChore,   weight: 2, minGap: 16000, maxGap: 28000 },
            { fn: activitySing,    weight: 2, minGap: 14000, maxGap: 26000 },
            { fn: activityTyping,  weight: 2, minGap: 14000, maxGap: 22000 },
            { fn: activityRainRun, weight: 1, minGap: 30000, maxGap: 55000 }
        ];
        async function robotActivityLoop() {
            const baseDelay = 11000 + Math.random() * 9000;
            await new Promise(r => setTimeout(r, baseDelay));
            if (!isRobotBusy()) {
                const totalWeight = ACTIVITIES.reduce((s, a) => s + a.weight, 0);
                let roll = Math.random() * totalWeight;
                let chosen = ACTIVITIES[0];
                for (const a of ACTIVITIES) { roll -= a.weight; if (roll <= 0) { chosen = a; break; } }
                try { await chosen.fn(); } catch (e) { activityRunning = false; }
                const postGap = chosen.minGap + Math.random() * (chosen.maxGap - chosen.minGap);
                await new Promise(r => setTimeout(r, postGap));
            }
            robotActivityLoop();
        }

        // ---------- CAREERS ----------
        function launchCareerIcons(careers) {
            if (!particlesLayer || !robotHead) return;
            const r = robotHead.getBoundingClientRect();
            const cx = r.left + r.width / 2;
            const startY = r.bottom + 60;
            const verticalSpacing = 64;
            const totalHeight = (careers.length - 1) * verticalSpacing;
            const startOffset = -totalHeight / 2;
            careers.forEach((career, i) => {
                setTimeout(() => {
                    const el = document.createElement('div');
                    el.className = 'career-particle';
                    el.innerHTML = '<i class="bi ' + career.icon + '"></i><span>' + career.name + '</span>';
                    el.style.left = cx + 'px';
                    el.style.top = (startY + startOffset + i * verticalSpacing) + 'px';
                    const baseDistLeft = 480 + i * 40 + Math.random() * 70;
                    const baseDistDown = 40 + i * 45 + Math.random() * 25;
                    el.style.setProperty('--career-x', baseDistLeft.toFixed(0) + 'px');
                    el.style.setProperty('--career-y', baseDistDown.toFixed(0) + 'px');
                    particlesLayer.appendChild(el);
                    setTimeout(() => el.remove(), 14600);
                }, i * 900);
            });
        }
        const CAREER_POOLS = {
            excellent: [
                { icon: 'bi-heart-pulse', name: 'Doctor' }, { icon: 'bi-cpu', name: 'Engineer' },
                { icon: 'bi-flask', name: 'Scientist' }, { icon: 'bi-bank', name: 'Lawyer' },
                { icon: 'bi-book', name: 'Professor' }, { icon: 'bi-rulers', name: 'Architect' }
            ],
            good: [
                { icon: 'bi-cpu', name: 'Engineer' }, { icon: 'bi-book', name: 'Teacher' },
                { icon: 'bi-heart-pulse', name: 'Nurse' }, { icon: 'bi-calculator', name: 'Accountant' },
                { icon: 'bi-graph-up', name: 'Analyst' }, { icon: 'bi-briefcase', name: 'Manager' }
            ],
            fair: [
                { icon: 'bi-tools', name: 'Technician' }, { icon: 'bi-book', name: 'Teacher' },
                { icon: 'bi-heart-pulse', name: 'Nurse' }, { icon: 'bi-cart', name: 'Sales Lead' },
                { icon: 'bi-briefcase', name: 'Entrepreneur' }, { icon: 'bi-truck', name: 'Logistics' }
            ],
            needsWork: [
                { icon: 'bi-tools', name: 'Skilled Trade' }, { icon: 'bi-briefcase', name: 'Entrepreneur' },
                { icon: 'bi-truck', name: 'Logistics' }, { icon: 'bi-headset', name: 'Support Specialist' },
                { icon: 'bi-shop', name: 'Small Business' }
            ]
        };
        function pickCareers(avgScore, anyStrong) {
            let pool, count;
            if (avgScore != null && avgScore >= 75) { pool = CAREER_POOLS.excellent; count = 6; }
            else if ((avgScore != null && avgScore >= 65) || anyStrong) { pool = CAREER_POOLS.good; count = 5; }
            else if (avgScore != null && avgScore >= 45) { pool = CAREER_POOLS.fair; count = 5; }
            else { pool = CAREER_POOLS.needsWork; count = 4; }
            const shuffled = [...pool].sort(() => Math.random() - 0.5);
            return shuffled.slice(0, Math.min(count, shuffled.length));
        }

        // ---------- POSITION / SHOW / HIDE ----------
        function positionBubbleNearElement(el, skipHighlight, avoidCollision) {
            if (!thoughtEl) return;
            const padding = 14;
            const w = Math.min(340, window.innerWidth - 40);

            if (!skipHighlight) {
                if (targetEl && targetEl !== el) targetEl.classList.remove('robot-target-highlight');
                targetEl = el;
                if (el) el.classList.add('robot-target-highlight');
            }

            if (!el) {
                const r = robotHead.getBoundingClientRect();
                thoughtEl.style.left = (r.left - w - 14) + 'px';
                thoughtEl.style.top  = (r.top + 4) + 'px';
                thoughtEl.style.maxWidth = w + 'px';
                thoughtEl.classList.remove('tail-left','tail-down','tail-up');
                thoughtEl.classList.add('tail-right');
                return;
            }

            const rect = el.getBoundingClientRect();
            const vw = window.innerWidth, vh = window.innerHeight;

            const prevVisibility = thoughtEl.style.visibility;
            thoughtEl.style.visibility = 'hidden';
            thoughtEl.style.maxWidth = w + 'px';
            const bubbleH = thoughtEl.offsetHeight || 160;
            thoughtEl.style.visibility = prevVisibility;

            if (avoidCollision) {
                const gap = 26;
                let top, tail;
                const spaceAbove = rect.top;
                const spaceBelow = vh - rect.bottom;
                if (spaceAbove >= bubbleH + gap + 60) { top = rect.top - bubbleH - gap; tail = 'tail-down'; }
                else if (spaceBelow >= bubbleH + gap + 60) { top = rect.bottom + gap; tail = 'tail-up'; }
                else { top = Math.max(10, rect.top - bubbleH - gap); tail = 'tail-down'; }
                let left = rect.left + rect.width / 2 - w / 2;
                left = Math.max(12, Math.min(vw - w - 12, left));
                thoughtEl.style.left = left + 'px';
                thoughtEl.style.top  = top + 'px';
                thoughtEl.style.maxWidth = w + 'px';
                thoughtEl.classList.remove('tail-right','tail-left','tail-down','tail-up');
                thoughtEl.classList.add(tail);
                return;
            }

            let left = rect.right + padding + 22;
            let top  = rect.top + rect.height / 2 - 40;
            let tail = 'tail-left';
            if (left + w > vw - 20) { left = rect.left - w - padding - 22; tail = 'tail-right'; }
            if (left < 20) {
                left = Math.max(20, Math.min(vw - w - 20, rect.left + rect.width/2 - w/2));
                top  = rect.top - 90;
                tail = 'tail-down';
            }
            if (top < 70) { top = rect.bottom + 22; tail = 'tail-up'; }
            top = Math.max(78, Math.min(vh - 130, top));

            thoughtEl.style.left = left + 'px';
            thoughtEl.style.top  = top + 'px';
            thoughtEl.style.maxWidth = w + 'px';
            thoughtEl.classList.remove('tail-right','tail-left','tail-down','tail-up');
            thoughtEl.classList.add(tail);
        }

        function showThought(html, emo, ms, anchorEl, options) {
            if (!thoughtEl) return;
            options = options || {};
            const avoidCollision = !!options.avoidCollision;
            anchoredEl = anchorEl || null;
            cloudContentEl.innerHTML = html;
            positionBubbleNearElement(anchorEl || null, false, avoidCollision);
            void thoughtEl.offsetWidth;
            thoughtEl.classList.add('show');
            if (emo) setEmotion(emo, ms || 5000);
            requestAnimationFrame(() => refreshCloudTheme());
            clearTimeout(showThought._t);
            if (!options.persistent) {
                showThought._t = setTimeout(() => hideThought(), ms || 5000);
            }
        }

        function hideThought() {
            if (!thoughtEl) return;
            thoughtEl.classList.remove('show');
            anchoredEl = null;
            if (targetEl) { targetEl.classList.remove('robot-target-highlight'); targetEl = null; }
        }

        // ---------- SCROLL REPOSITIONING ----------
        let scrollTimer = null;
        function repositionOnScroll() {
            if (!thoughtEl.classList.contains('show') || !anchoredEl) return;
            const rect = anchoredEl.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight) { hideThought(); return; }
            thoughtEl.classList.add('no-transition');
            positionBubbleNearElement(anchoredEl, true, false);
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                if (thoughtEl) thoughtEl.classList.remove('no-transition');
                refreshCloudTheme();
            }, 160);
        }
        window.addEventListener('scroll', repositionOnScroll, { passive: true });
        window.addEventListener('resize', function() { repositionOnScroll(); refreshCloudTheme(); }, { passive: true });
        document.addEventListener('scroll', function(e) {
            if (e.target && e.target !== document && e.target !== window) repositionOnScroll();
        }, { passive: true, capture: true });

        // ---------- HELPERS ----------
        const sleep = (ms) => new Promise(r => setTimeout(r, ms));
        function showThoughtAsync(html, emo, ms, anchorEl, options) {
            return new Promise(resolve => {
                showThought(html, emo, ms, anchorEl, options);
                setTimeout(resolve, (ms || 5000) + 200);
            });
        }
        function slowScrollTo(targetY, duration) {
            return new Promise(resolve => {
                const startY = window.scrollY;
                const diff = targetY - startY;
                if (Math.abs(diff) < 2) { resolve(); return; }
                const startTime = performance.now();
                function step(t) {
                    if (analysisStopped) { resolve(); return; }
                    const elapsed = t - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
                    window.scrollTo(0, startY + diff * eased);
                    if (progress < 1) requestAnimationFrame(step);
                    else resolve();
                }
                requestAnimationFrame(step);
            });
        }
        function slowScrollToElement(el, duration) {
            if (!el) return Promise.resolve();
            const rect = el.getBoundingClientRect();
            const targetY = window.scrollY + rect.top - window.innerHeight / 2 + rect.height / 2;
            return slowScrollTo(Math.max(0, targetY), duration);
        }
        function gradeOrder(g) {
            const map = { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'F': 1 };
            return map[(g || '').toUpperCase()] || 0;
        }
        function divisionRank(d) {
            if (d == null) return null;
            const s = String(d).toUpperCase().trim();
            if (s === '' || s === 'N/A') return null;
            if (s === '0' || s === 'FAIL' || s === 'F') return 0;
            const roman = { 'I': 1, 'II': 2, 'III': 3, 'IV': 4, 'V': 5 };
            if (s in roman) return roman[s];
            const n = parseInt(s, 10);
            return isNaN(n) ? null : n;
        }

        // ---------- ADVICE / REACTIONS ----------
        const ADVICE_BY_GRADE = {
            A: 'Advice: Keep this sharp with weekly past-paper practice and by explaining topics to peers.',
            B: 'Advice: Review the exact questions where you lost marks, then drill those types weekly.',
            C: 'Advice: Build a topic-by-topic revision plan and commit to two focused sessions per week.',
            D: 'Advice: Set aside 30 minutes daily on core concepts and one past paper each weekend.',
            F: 'Advice: Start from fundamentals. Attend every remedial class and revisit the syllabus from the top.'
        };
        function reactionForGrade(grade) {
            switch (grade) {
                case 'A': return { emo: 'kiss',          particles: 'hearts' };
                case 'B': return { emo: 'proud',         particles: 'stars' };
                case 'C': return { emo: 'encouraged',    particles: null };
                case 'D': return { emo: 'worried',       particles: 'worry' };
                case 'F': return { emo: 'disappointed',  particles: 'anger' };
                default:  return { emo: 'thinking',      particles: null };
            }
        }
        function fireReactionForGrade(grade) {
            const r = reactionForGrade(grade);
            if (r.particles === 'hearts') spawnHearts();
            else if (r.particles === 'stars') spawnStars();
            else if (r.particles === 'anger') spawnAnger();
            else if (r.particles === 'worry') spawnWorry();
        }

        // =========================================================
        //  RESULT-PAGE-AWARE ANALYSIS HELPERS
        // =========================================================
        function getActiveTermPane() {
            let pane = document.querySelector('.tab-content .tab-pane.active.show');
            if (!pane) pane = document.querySelector('.tab-content .tab-pane.active');
            if (!pane) {
                const panes = document.querySelectorAll('.tab-content .tab-pane');
                for (const p of panes) {
                    if (p.offsetParent !== null) { pane = p; break; }
                }
            }
            return pane;
        }
        function getActiveTermName() {
            const activeLink = document.querySelector('.result-tabs .nav-link.active, .nav-tabs .nav-link.active');
            if (!activeLink) return null;
            const txt = (activeLink.textContent || '').trim();
            return txt || null;
        }
        function getPreviousTermPane() {
            const activeLink = document.querySelector('.result-tabs .nav-link.active, .nav-tabs .nav-link.active');
            if (!activeLink) return null;
            const tabList = activeLink.closest('.nav-tabs');
            if (!tabList) return null;
            const tabs = Array.from(tabList.querySelectorAll('.nav-link'));
            const idx = tabs.indexOf(activeLink);
            if (idx < 0) return null;
            const candidates = [];
            if (tabs[idx + 1]) candidates.push(tabs[idx + 1]);
            if (tabs[idx - 1]) candidates.push(tabs[idx - 1]);
            for (const tab of candidates) {
                const href = tab.getAttribute('data-bs-target') || tab.getAttribute('href');
                if (!href || href.charAt(0) !== '#') continue;
                let pane = null;
                try { pane = document.querySelector(href); } catch (e) {}
                if (pane) return pane;
            }
            return null;
        }
        function parseScoreText(txt) {
            if (!txt) return '';
            const m = String(txt).match(/(\d+(?:\.\d+)?)\s*%?/);
            return m ? m[1] : '';
        }
        function findGradeElementsInScope(scope) {
            const results = [];
            const root = scope || document;
            const tables = root.querySelectorAll('table.result-table');
            tables.forEach(table => {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = Array.from(row.querySelectorAll('td'));
                    if (cells.length < 4) return;
                    const subjectCell = cells[1];
                    let subject = '';
                    const subjectSpan = subjectCell.querySelector('.subject-cell > span:first-child');
                    if (subjectSpan) subject = (subjectSpan.textContent || '').trim();
                    if (!subject) {
                        const anySpan = subjectCell.querySelector('span');
                        if (anySpan) subject = (anySpan.textContent || '').trim();
                    }
                    if (!subject) subject = (subjectCell.textContent || '').trim();
                    const score = parseScoreText(cells[2].textContent);
                    let grade = null;
                    let gradeCell = cells[3];
                    const pill = gradeCell.querySelector('.grade-pill');
                    if (pill) {
                        const m = (pill.className || '').match(/grade-([A-F])/i);
                        if (m) grade = m[1].toUpperCase();
                        else {
                            const t = (pill.textContent || '').trim().toUpperCase();
                            if (/^[A-F]$/.test(t)) grade = t;
                        }
                    }
                    if (!grade) {
                        const t = (gradeCell.textContent || '').trim().toUpperCase();
                        const m = t.match(/\b([A-F])\b/);
                        if (m) grade = m[1];
                    }
                    if (!grade) return;
                    const anchorCell = pill ? pill : row;
                    results.push({ cell: anchorCell, row, subject, score, grade });
                });
            });
            return results;
        }
        function getCurrentTermTotals() {
            const pane = getActiveTermPane();
            if (!pane) return { totals: null, el: null };
            const totals = { total: null, avg: null, grade: null, division: null, points: null, remark: null };
            let anchorEl = null;
            pane.querySelectorAll('.school-summary .summary-item').forEach(item => {
                const labelEl = item.querySelector('span');
                const valueEl = item.querySelector('strong');
                const label = labelEl ? (labelEl.textContent || '').trim().toLowerCase() : '';
                const value = valueEl ? (valueEl.textContent || '').trim() : '';
                if (label.includes('total')) {
                    const n = parseInt(value, 10);
                    if (!isNaN(n)) totals.total = n;
                } else if (label.includes('average')) {
                    const n = parseFloat(value);
                    if (!isNaN(n)) totals.avg = n;
                } else if (label.includes('grade')) {
                    const t = value.toUpperCase();
                    if (/^[A-F]$/.test(t)) totals.grade = t;
                } else if (label.includes('remark')) {
                    totals.remark = value;
                }
            });
            const schoolSection = pane.querySelector('.school-summary');
            if (schoolSection) anchorEl = schoolSection;
            const acseeFinal = pane.querySelector('.result-summary .acsee-final');
            if (acseeFinal) {
                const pointsEl = acseeFinal.querySelector('strong');
                if (pointsEl) {
                    const n = parseInt((pointsEl.textContent || '').trim(), 10);
                    if (!isNaN(n)) totals.points = n;
                }
                const divEl = acseeFinal.querySelector('small');
                if (divEl) {
                    const m = (divEl.textContent || '').match(/Division\s*([IVXivx0-9]+)/i);
                    if (m) totals.division = m[1].toUpperCase();
                }
                if (!anchorEl) anchorEl = acseeFinal;
            }
            if ((totals.points === null || totals.division === null) && pane) {
                const summaryEl = pane.querySelector('.result-summary');
                if (summaryEl) {
                    const text = summaryEl.textContent || '';
                    if (totals.points === null) {
                        const m = text.match(/POINTS?\s*:?\s*(\d+)/i);
                        if (m) totals.points = parseInt(m[1], 10);
                    }
                    if (totals.division === null) {
                        const m = text.match(/Division\s*([IVXivx0-9]+)/i);
                        if (m) totals.division = m[1].toUpperCase();
                    }
                }
            }
            if (!anchorEl) anchorEl = pane.querySelector('.result-summary');
            return { totals, el: anchorEl };
        }
        function getPreviousTermTotals() {
            const prevPane = getPreviousTermPane();
            if (!prevPane) return null;
            const totals = { total: null, avg: null, grade: null, division: null, points: null, remark: null };
            prevPane.querySelectorAll('.school-summary .summary-item').forEach(item => {
                const labelEl = item.querySelector('span');
                const valueEl = item.querySelector('strong');
                const label = labelEl ? (labelEl.textContent || '').trim().toLowerCase() : '';
                const value = valueEl ? (valueEl.textContent || '').trim() : '';
                if (label.includes('total')) {
                    const n = parseInt(value, 10); if (!isNaN(n)) totals.total = n;
                } else if (label.includes('average')) {
                    const n = parseFloat(value); if (!isNaN(n)) totals.avg = n;
                } else if (label.includes('grade')) {
                    const t = value.toUpperCase(); if (/^[A-F]$/.test(t)) totals.grade = t;
                } else if (label.includes('remark')) {
                    totals.remark = value;
                }
            });
            const acseeFinal = prevPane.querySelector('.result-summary .acsee-final');
            if (acseeFinal) {
                const pointsEl = acseeFinal.querySelector('strong');
                if (pointsEl) {
                    const n = parseInt((pointsEl.textContent || '').trim(), 10);
                    if (!isNaN(n)) totals.points = n;
                }
                const divEl = acseeFinal.querySelector('small');
                if (divEl) {
                    const m = (divEl.textContent || '').match(/Division\s*([IVXivx0-9]+)/i);
                    if (m) totals.division = m[1].toUpperCase();
                }
            }
            return totals;
        }

        // ---------- OPINIONS ----------
        function opinionsForFees() { return [{ anchor: null, emo: 'curious', html: 'Checking your fee account. If the balance is above zero, remember you can pay via CRDB or NMB.' },{ anchor: null, emo: 'happy', html: 'You can download a copy of any invoice on this page. Let me know if something looks off.' }]; }
        function opinionsForSubjects() { return [{ anchor: null, emo: 'curious', html: 'These are the subjects you are enrolled in. Interested in any particular one?' },{ anchor: null, emo: 'thinking', html: 'If a subject looks unfamiliar, ask your class teacher to confirm it.' }]; }
        function opinionsForTimetable() { return [{ anchor: null, emo: 'thinking', html: 'Plan your week around the busiest days. It makes a difference.' },{ anchor: null, emo: 'happy', html: 'If a slot looks wrong, talk to the academic office early.' }]; }
        function opinionsForAnnouncements() { return [{ anchor: null, emo: 'curious', html: 'Read the latest announcements first. They often contain deadlines.' },{ anchor: null, emo: 'happy', html: 'Something important usually hides in the third or fourth post.' }]; }
        function opinionsForCalendar() { return [{ anchor: null, emo: 'thinking', html: 'Mark the exam weeks and holidays on your personal planner.' },{ anchor: null, emo: 'happy', html: 'The term is easier when you plan around the calendar.' }]; }
        function opinionsForAssignments() { return [{ anchor: null, emo: 'thinking', html: 'Submit early. Teachers notice consistency.' },{ anchor: null, emo: 'happy', html: 'A short review tonight saves a long night tomorrow.' }]; }
        function opinionsForGrading() { return [{ anchor: null, emo: 'thinking', html: 'Understanding the grading scale helps you aim for the next grade.' },{ anchor: null, emo: 'curious', html: 'Ask me anything about how your grades are calculated.' }]; }
        function opinionsForDivision() { return [{ anchor: null, emo: 'thinking', html: 'Divisions combine your best subjects. Focus on your three strongest.' },{ anchor: null, emo: 'curious', html: 'Every point matters - a single subject can shift your division.' }]; }
        function opinionsForProfile() { return [{ anchor: null, emo: 'curious', html: 'Keep your contact details up to date so the school can reach you.' },{ anchor: null, emo: 'happy', html: 'A clear profile photo helps your teachers recognise you faster.' }]; }
        function opinionsForDashboard() { return [{ anchor: null, emo: 'curious', html: 'Everything important is one click away in the sidebar.' },{ anchor: null, emo: 'thinking', html: 'Start with results, then check fees. That is the usual order.' }]; }
        function opinionsForGeneric() { return [{ anchor: null, emo: 'curious', html: 'Take a moment to look around. I will point things out as you go.' },{ anchor: null, emo: 'thinking', html: 'Click my head any time to chat with me. Triple-click to hop between your results and dashboard.' }]; }
        function getPageOpinions() {
            const p = (CURRENT_PAGE || '').toLowerCase();
            if (p.includes('fee')) return opinionsForFees();
            if (p.includes('subject')) return opinionsForSubjects();
            if (p.includes('timetable')) return opinionsForTimetable();
            if (p.includes('announcement')) return opinionsForAnnouncements();
            if (p.includes('calendar')) return opinionsForCalendar();
            if (p.includes('assignment')) return opinionsForAssignments();
            if (p.includes('grading')) return opinionsForGrading();
            if (p.includes('division')) return opinionsForDivision();
            if (p.includes('profile')) return opinionsForProfile();
            if (p.includes('dashboard')) return opinionsForDashboard();
            return opinionsForGeneric();
        }
        function startOpinionQueue() {
            const list = getPageOpinions();
            let index = 0;
            function scheduleNext() {
                if (index >= list.length) return;
                const delay = index === 0 ? 0 : (7500 + Math.random() * 6000);
                setTimeout(() => {
                    if (analysisRunning || analysisStopped || activityRunning) return;
                    const op = list[index];
                    showThought(op.html, op.emo, 6000, op.anchor);
                    index++;
                    scheduleNext();
                }, delay);
            }
            scheduleNext();
        }

        // ---------- VARIED WELCOMES ----------
        function pickWelcomeMessage() {
            const pool = [
                'Welcome back, <strong>' + STUDENT_NAME + '</strong>.',
                'Good to see you again, <strong>' + STUDENT_NAME + '</strong>.',
                'Hey <strong>' + STUDENT_NAME + '</strong>. Ready when you are.',
                'Glad you are here, <strong>' + STUDENT_NAME + '</strong>.',
                'Hey there.', 'Hello again.', 'Good to have you back.', 'Nice to see you.',
                'What are we working on today, <strong>' + STUDENT_NAME + '</strong>?',
                'Let us pick up where we left off.',
                'Everything is set. Take your time.',
                'Have a look around. I am right here if you need me.',
                'Where would you like to start today?',
                'Take a moment to settle in, <strong>' + STUDENT_NAME + '</strong>.'
            ];
            return pool[Math.floor(Math.random() * pool.length)];
        }
        function pickWelcomeEmotion() { return ['happy', 'curious', 'encouraged', 'proud'][Math.floor(Math.random() * 4)]; }

        // ---------- LOGOUT CONFIRMATION (sad robot) ----------
        function askLogoutConfirmation() {
            if (chatWidget && chatWidget.classList.contains('open')) chatWidget.classList.remove('open');
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                const inst = bootstrap.Dropdown.getInstance(m.previousElementSibling);
                if (inst) inst.hide();
            });
            hideStopButton();
            setEmotion('sad', 22000);
            spawnSadDroplets();
            const html = 'Are you sure you want to log out, <strong>' + STUDENT_NAME + '</strong>? ' +
                '<div class="thought-actions">' +
                    '<button class="thought-btn yes" data-action="logout-yes">Yes, log me out</button>' +
                    '<button class="thought-btn no" data-action="logout-no">No, stay</button>' +
                '</div>';
            showThought(html, 'sad', 22000, null, { persistent: true });
        }
        (function initLogoutConfirmation() {
            const logoutLink = document.getElementById('logoutLink');
            if (!logoutLink) return;
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                askLogoutConfirmation();
            });
        })();

        // ---------- GUIDED ANALYSIS ----------
        async function runResultsAnalysis() {
            if (analysisRunning) return;
            analysisRunning = true;
            analysisStopped = false;
            const stopCheck = () => {
                if (analysisStopped) { analysisRunning = false; hideStopButton(); clearCloudWeather(); return true; }
                return false;
            };

            launchFireworks();
            setEmotion('celebrate', 4000);
            showThought('Please stay back, ' + STUDENT_NAME + '. I will now take control of the screen and walk you through your current-term results.', 'celebrate', 4500, null);
            await sleep(4800); if (stopCheck()) return;

            showThought('Let me bring the stop button forward so you know where it is.', 'curious', 3000, null);
            showStopButton();
            await sleep(3200); if (stopCheck()) return;
            showThought('This is the stop button. I will hold it for now so it does not get in the way. If you need it, just click me and it will come out again for five seconds.', 'happy', 6500, null);
            await sleep(3500); if (stopCheck()) return;
            hideStopButton();
            await sleep(2100); if (stopCheck()) return;
            showThought('Scanning the active term. This will take a few seconds.', 'curious', 3200, null);
            await sleep(3400); if (stopCheck()) return;

            const termName = getActiveTermName() || 'the current term';
            await showThoughtAsync('Focusing on your <strong>' + termName + '</strong>. Other terms are excluded from this walkthrough.', 'thinking', 4200, null);
            if (stopCheck()) return;

            const { totals, el: totalsEl } = getCurrentTermTotals();
            const prev = getPreviousTermTotals();

            if (totalsEl && totals) {
                await slowScrollToElement(totalsEl, 1500);
                await sleep(800); if (stopCheck()) return;

                if (totals.avg != null) {
                    let msg = 'Your current average is <strong>' + totals.avg + '%</strong>.';
                    let emo = 'curious';
                    if (prev && prev.avg != null) {
                        const diff = totals.avg - prev.avg;
                        if (diff > 0.05) { msg += ' Compared to your previous term average of ' + prev.avg + '%, this is an <strong>improvement of ' + diff.toFixed(1) + ' points</strong>. Well done.'; emo = 'proud'; }
                        else if (diff < -0.05) { msg += ' Compared to your previous term average of ' + prev.avg + '%, this is a <strong>decline of ' + Math.abs(diff).toFixed(1) + ' points</strong>. We can reverse this with focused effort.'; emo = 'worried'; }
                        else { msg += ' This matches your previous term average of ' + prev.avg + '%. Consistency is a strength.'; }
                    } else { msg += ' This is a solid baseline for the term.'; }
                    await showThoughtAsync(msg, emo, 6800, totalsEl); if (stopCheck()) return;
                }

                if (totals.grade) {
                    let msg = 'Your overall grade is <strong>' + totals.grade + '</strong>.';
                    let emo = 'curious';
                    if (prev && prev.grade) {
                        const cmp = gradeOrder(totals.grade) - gradeOrder(prev.grade);
                        if (cmp > 0) { msg += ' That is an <strong>improvement</strong> from your previous grade of ' + prev.grade + '.'; emo = 'proud'; }
                        else if (cmp < 0) { msg += ' That is a <strong>decline</strong> from your previous grade of ' + prev.grade + '.'; emo = 'worried'; }
                        else { msg += ' Same as your previous grade of ' + prev.grade + '.'; }
                    }
                    await showThoughtAsync(msg, emo, 6200, totalsEl); if (stopCheck()) return;
                }

                if (totals.division != null) {
                    let msg = 'Your division is <strong>' + totals.division + '</strong>. In the division system, a lower number indicates a stronger overall standing.';
                    let emo = 'curious';
                    if (prev && prev.division != null) {
                        const curRank = divisionRank(totals.division);
                        const prevRank = divisionRank(prev.division);
                        if (curRank != null && prevRank != null) {
                            const cmp = prevRank - curRank;
                            if (cmp > 0) { msg += ' You moved <strong>up</strong> from division ' + prev.division + '. Strong progress.'; emo = 'proud'; }
                            else if (cmp < 0) { msg += ' You moved <strong>down</strong> from division ' + prev.division + '. Let us aim to climb back.'; emo = 'worried'; }
                            else { msg += ' You held the same division as before.'; }
                        }
                    }
                    await showThoughtAsync(msg, emo, 6200, totalsEl); if (stopCheck()) return;
                }

                if (totals.points != null) {
                    let msg = 'You have <strong>' + totals.points + ' points</strong> toward your division placement.';
                    let emo = 'curious';
                    if (prev && prev.points != null) {
                        const diff = totals.points - prev.points;
                        if (diff > 0) { msg += ' That is <strong>up by ' + diff + '</strong> from your previous total of ' + prev.points + '.'; emo = 'proud'; }
                        else if (diff < 0) { msg += ' That is <strong>down by ' + Math.abs(diff) + '</strong> from your previous total of ' + prev.points + '.'; emo = 'worried'; }
                        else { msg += ' Same as your previous total of ' + prev.points + '.'; }
                    }
                    await showThoughtAsync(msg, emo, 6200, totalsEl); if (stopCheck()) return;
                }
            } else {
                await showThoughtAsync('I could not find a totals summary for the active term, ' + STUDENT_NAME + '. I will go straight to the subjects.', 'thinking', 5200, null);
                if (stopCheck()) return;
            }

            await slowScrollTo(0, 1700);
            await sleep(900); if (stopCheck()) return;

            const pane = getActiveTermPane();
            const grades = findGradeElementsInScope(pane);
            if (grades.length === 0) {
                await showThoughtAsync('I could not find any graded subject rows in the active term, ' + STUDENT_NAME + '.', 'thinking', 5200, null);
                hideStopButton(); analysisRunning = false; clearCloudWeather(); return;
            }

            const numericScores = grades.map(g => parseFloat(g.score)).filter(n => !isNaN(n));
            let avgScore = null;
            if (numericScores.length) { avgScore = numericScores.reduce((a, b) => a + b, 0) / numericScores.length; }
            let anyStrong = false;

            for (const g of grades) {
                if (stopCheck()) return;
                const row = g.row || g.cell;
                const subject = g.subject || 'this subject';
                const reaction = reactionForGrade(g.grade);
                if (g.grade === 'A' || g.grade === 'B') anyStrong = true;
                const advice = ADVICE_BY_GRADE[g.grade] || '';
                const html = '<strong>' + subject + '</strong> &mdash; Grade ' + g.grade + (g.score ? ' (' + g.score + '%)' : '') + '.' + '<span class="advice">' + advice + '</span>';

                await slowScrollToElement(row, 1100); if (stopCheck()) return;
                await sleep(500); if (stopCheck()) return;

                fireReactionForGrade(g.grade);
                applyGradeCloudEffect(g.grade);

                await showThoughtAsync(html, reaction.emo, 8500, row, { avoidCollision: true });
            }
            if (stopCheck()) return;
            clearCloudWeather();

            await slowScrollTo(0, 1700);
            await sleep(700); if (stopCheck()) return;

            const isStrong = (avgScore != null && avgScore >= 65) || anyStrong;
            if (isStrong) {
                launchFireworks();
                setEmotion('celebrate', 5000);
                await showThoughtAsync('Overall, ' + STUDENT_NAME + ', this is a solid term. Keep the momentum going.', 'celebrate', 6800, null);
                if (stopCheck()) return;
            } else {
                await showThoughtAsync('That completes the walkthrough, ' + STUDENT_NAME + '. Focus on the weak subjects first.', 'thinking', 6800, null);
                if (stopCheck()) return;
            }

            const careers = pickCareers(avgScore, anyStrong);
            const careerIntro = (avgScore != null && avgScore >= 75)
                ? 'Congratulations ' + STUDENT_NAME + '! With an average of ' + Math.round(avgScore) + '%, these careers are well within reach for you.'
                : (avgScore != null && avgScore >= 65)
                    ? 'Well done ' + STUDENT_NAME + '. Based on this performance, these careers are strong paths for you.'
                    : (avgScore != null && avgScore >= 45)
                        ? 'Good effort ' + STUDENT_NAME + '. These paths fit the potential I see in your results.'
                        : 'Keep going ' + STUDENT_NAME + '. These fields offer a solid start while you strengthen your core subjects.';
            setEmotion('kiss', 6500);
            showThought(careerIntro, 'kiss', 8500, null);
            launchCareerIcons(careers);
            await sleep(15000); if (stopCheck()) return;

            showThought('Click my head any time to chat about any of these paths, ' + STUDENT_NAME + '.', 'encouraged', 4500, null);
            await sleep(4700);

            hideStopButton();
            clearCloudWeather();
            analysisRunning = false;
        }

        // ---------- ANALYSIS PROMPT ----------
        function showAnalysisPrompt() {
            const html = 'Before we begin, would you like me to walk you through your current-term results in detail? ' +
                'I will go through your totals and each subject with tailored advice. ' +
                '<div class="thought-actions">' +
                    '<button class="thought-btn yes" data-action="analysis-yes">Yes, analyse</button>' +
                    '<button class="thought-btn no" data-action="analysis-no">No, just browse</button>' +
                '</div>';
            showThought(html, 'curious', 15000, null, { persistent: true });
        }

        // ---------- THOUGHT BUTTON HANDLER ----------
        if (thoughtEl) {
            thoughtEl.addEventListener('click', function(e) {
                const btn = e.target.closest('.thought-btn');
                if (!btn) return;
                e.stopPropagation();
                const action = btn.getAttribute('data-action');
                if (action === 'analysis-yes') {
                    hideThought();
                    setTimeout(() => { runResultsAnalysis(); }, 400);
                } else if (action === 'analysis-no') {
                    hideThought();
                    setTimeout(() => {
                        showThought('Understood. I will stay out of the way. Have a look around.', 'happy', 3500, null);
                        setTimeout(startOpinionQueue, 4000);
                    }, 250);
                } else if (action === 'logout-yes') {
                    hideThought();
                    setEmotion('sad', 1800);
                    spawnSadDroplets();
                    showThought('Goodbye, <strong>' + STUDENT_NAME + '</strong>. I will miss you.', 'sad', 1800, null);
                    setTimeout(() => { window.location.href = 'logout'; }, 900);
                } else if (action === 'logout-no') {
                    hideThought();
                    setEmotion('encouraged', 2600);
                    showThought('Glad you are staying, <strong>' + STUDENT_NAME + '</strong>. Let us continue.', 'encouraged', 3200, null);
                }
            });
        }

        // ---------- TERM TAB CHANGE ----------
        if (IS_RESULTS_PAGE) {
            document.addEventListener('click', function(e) {
                const link = e.target.closest('.result-tabs .nav-link, .nav-tabs .nav-link');
                if (!link) return;
                if (link.classList.contains('active')) return;
                if (analysisRunning) { analysisStopped = true; hideStopButton(); analysisRunning = false; }
                clearCloudWeather();
                hideThought();
                setTimeout(() => {
                    if (analysisRunning) return;
                    showAnalysisPrompt();
                }, 550);
            });
        }

        // ---------- LIFECYCLE ----------
        window.addEventListener('load', function() {
            setTimeout(async () => {
                const welcomeMsg = pickWelcomeMessage();
                const welcomeEmo = pickWelcomeEmotion();
                showThought(welcomeMsg, welcomeEmo, 3200, null);
                await sleep(3400);
                if (IS_RESULTS_PAGE) {
                    showAnalysisPrompt();
                } else {
                    showThought('Click my head any time to chat with me. Triple-click to jump between results and dashboard.', 'curious', 4600, null);
                    setTimeout(startOpinionQueue, 4000);
                }
            }, 900);
            setTimeout(idleEmotionLoop, 2500);
            setTimeout(robotActivityLoop, 8000);
        });

        // =========================================================
        //  AI CHAT
        // =========================================================
        const chatWidget = document.getElementById('aiChatWidget');
        const closeChatBtn = document.getElementById('closeChatBtn');
        const messagesContainer = document.getElementById('aiChatMessages');
        const msgInput = document.getElementById('aiMessageInput');
        const sendBtn = document.getElementById('sendAiMessage');
        const speakerToggle = document.getElementById('speakerToggle');
        let conversationHistory = [];
        let voiceEnabled = false;

        speakerToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            voiceEnabled = !voiceEnabled;
            const icon = this.querySelector('i');
            if (voiceEnabled) {
                icon.className = 'bi bi-volume-up';
                this.classList.remove('muted');
                this.title = 'Mute voice output';
            } else {
                icon.className = 'bi bi-volume-mute';
                this.classList.add('muted');
                this.title = 'Unmute voice output';
            }
            if (window.speechSynthesis) window.speechSynthesis.cancel();
        });

        function speakText(text) {
            if (!voiceEnabled) return;
            if (!window.speechSynthesis) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            const voices = window.speechSynthesis.getVoices();
            const lang = navigator.language || 'en-US';
            const voice = voices.find(v => v.lang.startsWith(lang.split('-')[0])) || voices[0];
            if (voice) utterance.voice = voice;
            utterance.rate = 0.9; utterance.pitch = 1; utterance.volume = 1;
            window.speechSynthesis.speak(utterance);
        }

        function toggleChat() {
            chatWidget.classList.toggle('open');
            if (chatWidget.classList.contains('open')) {
                msgInput.focus();
                setEmotion('happy', 2500);
            } else {
                if (window.speechSynthesis) window.speechSynthesis.cancel();
            }
            // Refresh the scroll button visibility (chat widget can overlap on small screens)
            const scrollBtn = document.getElementById('scrollTopBtn');
            if (scrollBtn) {
                const chatOpen = chatWidget.classList.contains('open');
                const smallScreen = window.innerWidth <= 768;
                if (chatOpen && smallScreen) scrollBtn.classList.remove('show');
                else if (window.scrollY > 300) scrollBtn.classList.add('show');
            }
        }
        closeChatBtn.addEventListener('click', toggleChat);

        function renderMessage(text) {
            let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
            return formatted;
        }
        function typeMessage(element, text, speed, callback) {
            let i = 0; element.innerHTML = '';
            function type() {
                if (i < text.length) { element.innerHTML += text.charAt(i); i++; setTimeout(type, speed); messagesContainer.scrollTop = messagesContainer.scrollHeight; }
                else if (callback) callback();
            }
            type();
        }
        function addMessageWithTyping(text, sender) {
            if (sender === 'user') {
                const msgDiv = document.createElement('div');
                msgDiv.className = `message ${sender}`;
                msgDiv.innerHTML = renderMessage(text);
                messagesContainer.appendChild(msgDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                conversationHistory.push({ role: sender, content: text });
                if (conversationHistory.length > 10) conversationHistory.shift();
            } else {
                const msgDiv = document.createElement('div');
                msgDiv.className = `message ${sender}`;
                messagesContainer.appendChild(msgDiv);
                conversationHistory.push({ role: sender, content: text });
                if (conversationHistory.length > 10) conversationHistory.shift();
                const plainText = text;
                typeMessage(msgDiv, renderMessage(text), 12, function() { speakText(plainText); });
            }
        }
        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message ai ai-typing';
            typingDiv.id = 'aiTypingIndicator';
            typingDiv.innerHTML = 'Thinking...';
            messagesContainer.appendChild(typingDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        function removeTypingIndicator() { const i = document.getElementById('aiTypingIndicator'); if (i) i.remove(); }

        async function sendMessageToAI(message) {
            const url = window.location.pathname + '?ai_action=chat';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ message: message, history: conversationHistory.slice(-5) })
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                addMessageWithTyping(data.reply, 'ai');
            } catch (err) {
                console.error('Chat error:', err);
                addMessageWithTyping("I could not reach the server. Please try again.", 'ai');
            } finally { removeTypingIndicator(); }
        }

        function handleSendMessage() {
            const message = msgInput.value.trim(); if (!message) return;
            addMessageWithTyping(message, 'user'); msgInput.value = '';
            showTypingIndicator(); sendMessageToAI(message);
        }
        sendBtn.addEventListener('click', handleSendMessage);
        msgInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSendMessage(); });

        // ========== VOICE INPUT ==========
        const micBtn = document.getElementById('micBtn');
        let recognition = null, isRecording = false, silenceTimer = null, finalTranscript = '', interimTranscript = '';
        const SILENCE_TIMEOUT = 2500;
        const STOP_PHRASE = 'stop listening';

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true; recognition.interimResults = true;
            if (navigator.language) recognition.lang = navigator.language;
            recognition.onstart = function() {
                isRecording = true;
                micBtn.classList.add('recording');
                micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                msgInput.placeholder = 'Listening... (click mic to stop)';
                msgInput.disabled = true;
                finalTranscript = ''; interimTranscript = '';
                if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; }
                addMessageWithTyping('Microphone is on. Speak your question. Say "stop listening" or click the mic to turn it off.', 'ai');
            };
            recognition.onresult = function(event) {
                let final = '', interim = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) final += event.results[i][0].transcript;
                    else interim += event.results[i][0].transcript;
                }
                finalTranscript += final; interimTranscript = interim;
                const fullText = (finalTranscript + interim).trim();
                msgInput.value = fullText;
                const lowerFull = fullText.toLowerCase();
                if (lowerFull.includes(STOP_PHRASE)) { addMessageWithTyping('Turning off the microphone.', 'ai'); stopRecording(); msgInput.value = ''; return; }
                if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; }
                if (final && fullText.length > 0) {
                    silenceTimer = setTimeout(() => {
                        const currentText = msgInput.value.trim();
                        if (currentText.length > 2) { handleSendMessage(); msgInput.value = ''; finalTranscript = ''; interimTranscript = ''; }
                        else { finalTranscript = ''; interimTranscript = ''; msgInput.value = ''; }
                        silenceTimer = null;
                    }, SILENCE_TIMEOUT);
                }
            };
            recognition.onerror = function(event) {
                if (event.error === 'not-allowed') { addMessageWithTyping('Please allow microphone access to use voice input.', 'ai'); stopRecording(); }
                else if (event.error === 'audio-capture') { addMessageWithTyping('No microphone found.', 'ai'); stopRecording(); }
                else if (event.error === 'network') { addMessageWithTyping('Network error. Check your connection.', 'ai'); }
                else if (event.error !== 'no-speech' && event.error !== 'aborted') { addMessageWithTyping('Voice error: ' + event.error, 'ai'); }
            };
            recognition.onend = function() {
                if (isRecording) {
                    const text = msgInput.value.trim();
                    if (text.length > 2) { handleSendMessage(); msgInput.value = ''; }
                    try { recognition.start(); } catch (e) { stopRecording(); }
                } else {
                    if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; }
                    msgInput.value = ''; finalTranscript = ''; interimTranscript = '';
                    micBtn.classList.remove('recording'); micBtn.innerHTML = '<i class="bi bi-mic"></i>';
                    msgInput.placeholder = 'Type or speak your question...';
                    msgInput.disabled = false;
                }
            };
            function stopRecording() {
                isRecording = false;
                if (recognition) { try { recognition.stop(); } catch(e) {} }
                if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; }
                micBtn.classList.remove('recording'); micBtn.innerHTML = '<i class="bi bi-mic"></i>';
                msgInput.placeholder = 'Type or speak your question...';
                msgInput.disabled = false;
                finalTranscript = ''; interimTranscript = '';
            }
            micBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!recognition) return;
                if (isRecording) { stopRecording(); addMessageWithTyping('Microphone turned off.', 'ai'); }
                else {
                    try { recognition.start(); }
                    catch (err) {
                        if (err.message && err.message.includes('already started')) { recognition.stop(); setTimeout(() => { try { recognition.start(); } catch(e) {} }, 300); }
                        else { addMessageWithTyping('Could not start voice recognition.', 'ai'); }
                    }
                }
            });
            msgInput.addEventListener('focus', function() { if (isRecording) { stopRecording(); addMessageWithTyping('Microphone off because you started typing.', 'ai'); } });
            msgInput.addEventListener('input', function() { if (isRecording) { stopRecording(); addMessageWithTyping('Microphone off because you started typing.', 'ai'); } });
        } else {
            micBtn.style.opacity = '0.4'; micBtn.title = 'Voice input not supported'; micBtn.disabled = true;
            micBtn.addEventListener('click', function(e) { e.preventDefault(); addMessageWithTyping('Voice input is not supported in this browser. Use Chrome, Edge, or Safari.', 'ai'); });
        }
    </script>
</body>
</html>