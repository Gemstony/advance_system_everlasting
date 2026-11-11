<?php
// ai_handler.php - Dedicated AJAX endpoint for AI Assistant
chdir('../');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ai_error.log');

require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

header('Content-Type: application/json');

try {
    // Verify student is logged in and level = 3
    if (!($res == "1" && $level == "3")) {
        throw new Exception("Unauthorized access.");
    }

    $student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
    if (!$student_id) throw new Exception("Student session expired.");

    // Database connection
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Get student details
    $stmt = $pdo->prepare("SELECT s.*, CAST(s.class AS UNSIGNED) AS class_int, c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) throw new Exception("Student record not found.");
    $class_id = (int) $student['class_int'];

    // School contacts
    $phone = '+255 794 457 583';
    $email = 'it@peverlastingacademy.co.tz';
    $whatsapp = '+255 794 457 583';
    $stmt = $pdo->query("SELECT phone1, phone2, email FROM tbl_school_contacts LIMIT 1");
    if ($row = $stmt->fetch()) {
        if (!empty($row['phone1'])) $phone = $row['phone1'];
        if (!empty($row['email'])) $email = $row['email'];
        $whatsapp = $row['phone1'] ?? $whatsapp;
    }

    // Grading system
    $grading = $pdo->query("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();

    // Conversation history
    if (!isset($_SESSION['ai_conversation'])) {
        $_SESSION['ai_conversation'] = [];
    }

    $question = trim($_POST['question'] ?? '');
    if (empty($question)) {
        throw new Exception("Please ask me something – I'm here to help! 😊");
    }

    // Store user question
    $_SESSION['ai_conversation'][] = ['role' => 'user', 'text' => $question];
    if (count($_SESSION['ai_conversation']) > 20) array_shift($_SESSION['ai_conversation']);

    // ---------- Helper functions (copied from your original) ----------
    function getStudentResults($pdo, $student_id, $grading) {
        $stmt = $pdo->prepare("SELECT DISTINCT t.id, t.name FROM tbl_exam_results r JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY t.id DESC LIMIT 1");
        $stmt->execute([$student_id]);
        $latest_term = $stmt->fetch();
        if (!$latest_term) return null;
        
        $stmt = $pdo->prepare("SELECT r.score, s.name AS subject_name 
                               FROM tbl_exam_results r 
                               JOIN tbl_subject_combinations sc ON r.subject_combination = sc.id 
                               JOIN tbl_subjects s ON sc.subject = s.id 
                               WHERE r.student = ? AND r.term = ?");
        $stmt->execute([$student_id, $latest_term['id']]);
        $scores = $stmt->fetchAll();
        if (empty($scores)) return null;
        
        $total = array_sum(array_column($scores, 'score'));
        $avg = round($total / count($scores));
        $grade = '';
        foreach ($grading as $g) {
            if ($avg >= $g['min'] && $avg <= $g['max']) { $grade = $g['name']; break; }
        }
        $subjects = [];
        foreach ($scores as $s) {
            $subj_grade = '';
            foreach ($grading as $g) {
                if ($s['score'] >= $g['min'] && $s['score'] <= $g['max']) { $subj_grade = $g['name']; break; }
            }
            $subjects[] = ['name' => $s['subject_name'], 'score' => $s['score'], 'grade' => $subj_grade];
        }
        return [
            'term' => $latest_term['name'],
            'total' => $total,
            'average' => $avg,
            'grade' => $grade,
            'subjects' => $subjects
        ];
    }

    function getUpcomingEvents($pdo) {
        $stmt = $pdo->prepare("SELECT title, event_date, venue FROM tbl_events WHERE audience IN ('students','both') AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    function getRecentAnnouncements($pdo) {
        $stmt = $pdo->query("SELECT title, announcement FROM tbl_announcements WHERE level IN (1,2) ORDER BY create_date DESC LIMIT 5");
        return $stmt->fetchAll();
    }

    function getSubjectTeachers($pdo, $class_id) {
        $stmt = $pdo->query("SELECT sc.class, s.name AS subject_name, sf.fname, sf.lname 
                             FROM tbl_subject_combinations sc 
                             JOIN tbl_subjects s ON sc.subject = s.id 
                             JOIN tbl_staff sf ON sc.teacher = sf.id 
                             WHERE sc.status = 1");
        $teachers = [];
        while ($row = $stmt->fetch()) {
            $classes = null;
            if (!empty($row['class'])) {
                $classes = @unserialize($row['class']);
                if ($classes === false) {
                    preg_match_all('/s:\d+:"(\d+)"/', $row['class'], $matches);
                    if (!empty($matches[1])) {
                        $classes = array_map('intval', $matches[1]);
                    }
                }
            }
            if (is_array($classes) && in_array($class_id, $classes)) {
                $teachers[] = [
                    'subject' => $row['subject_name'],
                    'fname' => $row['fname'],
                    'lname' => $row['lname']
                ];
            }
        }
        return $teachers;
    }

    function getActiveTerms($pdo) {
        $stmt = $pdo->query("SELECT name FROM tbl_terms WHERE status = 1 ORDER BY id");
        return $stmt->fetchAll();
    }

    function getLocalAIResponse($question, $student, $class_id, $grading, $pdo, $phone, $email, $whatsapp) {
        $q = strtolower($question);
        
        // Predefined conversations
        try {
            $stmt = $pdo->prepare("SELECT response FROM tbl_ai_conversations WHERE MATCH(keywords) AGAINST(? IN NATURAL LANGUAGE MODE) ORDER BY priority DESC, RAND() LIMIT 1");
            $stmt->execute([$q]);
            $conv = $stmt->fetch();
            if ($conv) return $conv['response'];
        } catch (Exception $e) {
            $stmt = $pdo->query("SELECT response, keywords FROM tbl_ai_conversations");
            $all = $stmt->fetchAll();
            $best = null; $best_score = 0;
            foreach ($all as $row) {
                $kw = explode(' ', $row['keywords']);
                $score = 0;
                foreach ($kw as $k) {
                    if (strlen($k) > 2 && strpos($q, $k) !== false) $score++;
                }
                if ($score > $best_score) { $best_score = $score; $best = $row['response']; }
            }
            if ($best) return $best;
        }
        
        // Results
        if (preg_match('/result|score|grade|performance|average|how did i do|my marks/i', $q)) {
            $results = getStudentResults($pdo, $student['id'], $grading);
            if ($results) {
                $subject_names = array_map(function($s) { return preg_quote(strtolower($s['name']), '/'); }, $results['subjects']);
                if (!empty($subject_names) && preg_match('/\b(' . implode('|', $subject_names) . ')\b/i', $q, $match)) {
                    $subj_name = ucfirst($match[1]);
                    foreach ($results['subjects'] as $subj) {
                        if (strtolower($subj['name']) == strtolower($subj_name)) {
                            return "In {$results['term']}, your score in **{$subj['name']}** is **{$subj['score']}%** (Grade {$subj['grade']}). " . ($subj['score'] >= 50 ? "Well done! 👍" : "Keep practicing, you can improve! 💪");
                        }
                    }
                }
                $subject_lines = [];
                foreach ($results['subjects'] as $subj) {
                    $subject_lines[] = "• {$subj['name']}: {$subj['score']}% (Grade {$subj['grade']})";
                }
                $advice = $results['average'] >= 75 ? "Excellent performance! 🎉" : ($results['average'] >= 50 ? "Good job! Keep it up! 👍" : "You can do better! Focus on weak areas. 💪");
                return "Here are your results for **{$results['term']}**:\n" . implode("\n", $subject_lines) . 
                       "\n\n📊 **Summary:** Total = {$results['total']}, Average = {$results['average']}%, Grade = {$results['grade']}. $advice";
            } else {
                return "You don't have any exam results yet. Once your teachers publish results, I'll show them here. 😊";
            }
        }
        
        // Upcoming events
        if (preg_match('/event|activity|happening|coming up|calendar/i', $q)) {
            $events = getUpcomingEvents($pdo);
            if (count($events)) {
                $list = [];
                foreach ($events as $e) {
                    $list[] = "• {$e['title']} on " . date('d M Y', strtotime($e['event_date'])) . " at {$e['venue']}";
                }
                return "Here are upcoming school events:\n" . implode("\n", $list) . "\nExciting stuff! 🎉";
            } else {
                return "No upcoming events scheduled at the moment. Check back later!";
            }
        }
        
        // Announcements
        if (preg_match('/announcement|news|notice|update/i', $q)) {
            $anns = getRecentAnnouncements($pdo);
            if (count($anns)) {
                $list = [];
                foreach ($anns as $a) {
                    $list[] = "📢 " . htmlspecialchars($a['title']);
                }
                return "Recent announcements:\n" . implode("\n", $list) . "\nCheck the portal for full details!";
            } else {
                return "No recent announcements. The school will post updates soon.";
            }
        }
        
        // Teachers
        if (preg_match('/teacher|who teaches|instructor/i', $q)) {
            $teachers = getSubjectTeachers($pdo, $class_id);
            if (count($teachers)) {
                $list = [];
                foreach ($teachers as $t) {
                    $list[] = "{$t['subject']}: {$t['fname']} {$t['lname']}";
                }
                return "Your subject teachers:\n" . implode("\n", $list) . "\nReach out to them during school hours!";
            } else {
                return "I couldn't find teacher assignments for your class. Please contact the academic office.";
            }
        }
        
        // Contacts
        if (preg_match('/contact|phone|email|support|reach|call/i', $q)) {
            return "You can reach the school IT support at:\n📞 Phone: {$phone}\n📧 Email: {$email}\n💬 WhatsApp: {$whatsapp}\nSupport hours: Monday–Friday, 8am–5pm.";
        }
        
        // Term dates
        if (preg_match('/term date|start of term|academic calendar|when does term/i', $q)) {
            $terms = getActiveTerms($pdo);
            if (count($terms)) {
                $term_names = array_column($terms, 'name');
                return "The active terms are: " . implode(", ", $term_names) . ". For exact start and end dates, please check the school calendar or ask the academic office.";
            } else {
                return "Term dates are managed by the academic office. Please contact them for the latest calendar.";
            }
        }
        
        // Library
        if (preg_match('/library|book|borrow|librarian/i', $q)) {
            return "📚 The school library is open from 8:00 AM to 4:30 PM on school days. You can borrow up to 3 books for two weeks. Ask the librarian for recommendations!";
        }
        
        // Fees
        if (preg_match('/fee|tuition|cost|payment|school fee/i', $q)) {
            return "For detailed fee structure, please contact the accounts office via email {$email} or phone {$phone}. Estimated ranges: Day students TZS 450,000–650,000/term, Boarding TZS 850,000–1,200,000/term.";
        }
        
        // Timetable
        if (preg_match('/timetable|schedule|period|class time/i', $q)) {
            return "Your class timetable is available on the notice board and on the student portal under 'My Subjects'. For detailed period schedule, please ask your class teacher.";
        }
        
        // Grading system
        if (preg_match('/grading system|grade scale|how are grades calculated/i', $q)) {
            $grades = [];
            foreach ($grading as $g) {
                $grades[] = "{$g['name']}: {$g['min']}–{$g['max']}% ({$g['remark']})";
            }
            return "Everlasting Academy grading system:\n" . implode("\n", $grades) . "\nKeep aiming high! 💯";
        }
        
        // Default fallback
        $history = $_SESSION['ai_conversation'];
        $last_user = '';
        for ($i = count($history)-1; $i >= 0; $i--) {
            if ($history[$i]['role'] == 'user') {
                $last_user = $history[$i]['text'];
                break;
            }
        }
        if ($last_user && strlen($last_user) > 3 && strpos($q, 'more') === false && strpos($q, 'again') === false) {
            return "Hmm, I see you asked about \"$last_user\" earlier. Could you rephrase your question? I can help with results, events, teachers, contacts, fees, timetable, library, and grading. 😊";
        } else {
            return "I'm your Everlasting Academy AI assistant. You can ask me about your exam results, upcoming events, announcements, subject teachers, school contacts, term dates, timetable, library hours, fees, or the grading system. What would you like to know today? 🌟";
        }
    }

    $answer = getLocalAIResponse($question, $student, $class_id, $grading, $pdo, $phone, $email, $whatsapp);
    $_SESSION['ai_conversation'][] = ['role' => 'ai', 'text' => $answer];
    
    echo json_encode(['answer' => nl2br(htmlspecialchars($answer))]);

} catch (Exception $e) {
    error_log("AI Handler Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['answer' => "⚠️ Error: " . htmlspecialchars($e->getMessage())]);
}