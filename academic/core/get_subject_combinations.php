<?php
chdir('../../');
session_start();
require_once('../db/config.php');
require_once('../const/check_session.php');

if ($res != "1" || $level != "1") {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
if (!$subject_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid subject']);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all combinations for this subject, including teacher info
    $stmt = $pdo->prepare("
        SELECT sc.id, sc.class, s.name AS subject_name, st.fname, st.lname, st.id AS teacher_id
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        LEFT JOIN tbl_staff st ON sc.teacher = st.id
        WHERE sc.subject = ?
    ");
    $stmt->execute([$subject_id]);
    $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helper to parse class list
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
        foreach ($parts as $p) if (ctype_digit($p)) $ints[] = (int)$p;
        return $ints;
    }

    // Collect teacher IDs and class IDs
    $teacher_ids = [];
    $class_ids = [];
    $result = [];

    foreach ($combinations as $comb) {
        $class_list = parse_class_list($comb['class']);
        $class_ids = array_merge($class_ids, $class_list);
        $teacher_ids[] = $comb['teacher_id'];

        // Get class names
        $class_names = [];
        if (!empty($class_list)) {
            $placeholders = implode(',', array_fill(0, count($class_list), '?'));
            $stmt2 = $pdo->prepare("SELECT name FROM tbl_classes WHERE id IN ($placeholders)");
            $stmt2->execute($class_list);
            $class_names = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        }

        $result[] = [
            'subject_name' => $comb['subject_name'],
            'teacher' => trim(($comb['fname'] ?? '') . ' ' . ($comb['lname'] ?? '')),
            'classes' => implode(', ', $class_names)
        ];
    }

    // Count unique teachers
    $teacher_count = count(array_unique(array_filter($teacher_ids)));

    // Count total students in all these classes
    $student_count = 0;
    if (!empty($class_ids)) {
        $class_ids = array_unique($class_ids);
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders) AND status = 1");
        $stmt3->execute($class_ids);
        $student_count = $stmt3->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'teacher_count' => $teacher_count,
        'student_count' => (int)$student_count,
        'data' => $result
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}