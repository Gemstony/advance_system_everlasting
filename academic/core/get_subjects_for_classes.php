<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "1")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$class_ids = isset($_GET['classes']) ? explode(',', $_GET['classes']) : [];
if (empty($class_ids)) {
    echo json_encode(['success' => false, 'message' => 'No classes selected']);
    exit;
}
$class_ids = array_map('intval', $class_ids);

// Helper to parse class list (copied from main page)
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

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT sc.id AS sc_id, sc.class, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $all_sc = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectMap = []; // id => name
    foreach ($all_sc as $r) {
        $class_list = parse_class_list($r['class']);
        foreach ($class_ids as $cid) {
            if (in_array($cid, $class_list, true)) {
                $subjectMap[(int)$r['sc_id']] = $r['subject_name'] ?? 'Unknown';
                break;
            }
        }
    }

    $subjects = [];
    foreach ($subjectMap as $id => $name) {
        $subjects[] = ['id' => $id, 'name' => $name];
    }

    // Sort by name
    usort($subjects, fn($a, $b) => strcmp($a['name'], $b['name']));

    echo json_encode(['success' => true, 'subjects' => $subjects]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}