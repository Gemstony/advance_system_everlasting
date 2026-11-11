<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "1")) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$term = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$classes = isset($_GET['classes']) ? explode(',', $_GET['classes']) : [];
$subjects = isset($_GET['subjects']) ? explode(',', $_GET['subjects']) : [];

if (!$term || empty($classes) || empty($subjects)) {
    die("Invalid parameters.");
}

// Sanitize inputs
$classes = array_map('intval', $classes);
$subjects = array_map('intval', $subjects);

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get term name
    $stmt = $pdo->prepare("SELECT name FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term]);
    $termName = $stmt->fetchColumn();

    // Get class names (for filename)
    $placeholders = implode(',', array_fill(0, count($classes), '?'));
    $stmt = $pdo->prepare("SELECT name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
    $stmt->execute($classes);
    $classNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $fileNameClass = implode('_', array_map(function($n) { return preg_replace('/[^a-z0-9]/i', '', $n); }, $classNames));

    // Get students from selected classes
    $placeholders = implode(',', array_fill(0, count($classes), '?'));
    $stmt = $pdo->prepare("SELECT id, fname, mname, lname, class FROM tbl_students WHERE class IN ($placeholders) AND status = 1 ORDER BY class, id");
    $stmt->execute($classes);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get subject details for selected combo IDs
    $placeholders = implode(',', array_fill(0, count($subjects), '?'));
    $stmt = $pdo->prepare("SELECT sc.id, s.name FROM tbl_subject_combinations sc
                           LEFT JOIN tbl_subjects s ON sc.subject = s.id
                           WHERE sc.id IN ($placeholders)");
    $stmt->execute($subjects);
    $subjectCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare CSV headers
    $headers = ['Registration Number', 'Student Name', 'Class'];
    foreach ($subjectCombos as $combo) {
        $headers[] = $combo['name'] . ' [' . $combo['id'] . ']';
    }

    // Set filename
    $filename = $fileNameClass . '_' . preg_replace('/[^a-z0-9]/i', '', $termName) . '_template.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    // Output student rows
    foreach ($students as $student) {
        $row = [
            $student['id'],
            trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']),
            $student['class']
        ];
        // Empty scores for each subject
        for ($i = 0; $i < count($subjectCombos); $i++) {
            $row[] = '';
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Error generating template: " . $e->getMessage());
}