<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if (!($res == "1" && $level == "2")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$term_id = isset($_POST['term']) ? (int)$_POST['term'] : 0;
$class_id = isset($_POST['class']) ? (int)$_POST['class'] : 0;
$subject_comb_id = isset($_POST['subject']) ? (int)$_POST['subject'] : 0;
$is_preview = isset($_POST['preview']);
$is_import = isset($_POST['import']);

if (!$term_id || !$class_id || !$subject_comb_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (term, class, subject).']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset,
        DBUser,
        DBPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verify teacher owns this subject combination and class
    $stmt = $pdo->prepare("SELECT id, class FROM tbl_subject_combinations WHERE id = ? AND teacher = ?");
    $stmt->execute([$subject_comb_id, $account_id]);
    $comb = $stmt->fetch();
    if (!$comb) {
        throw new Exception('Invalid subject combination or not assigned to you.');
    }
    $allowed_classes = unserialize($comb['class']);
    if (!is_array($allowed_classes) || !in_array($class_id, $allowed_classes)) {
        throw new Exception('Selected class is not linked to this subject combination.');
    }

    // Process uploaded file
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed. Please select a valid CSV file.');
    }
    $fileTmp = $_FILES['file']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        throw new Exception('Only CSV files are allowed.');
    }

    $handle = fopen($fileTmp, 'r');
    if (!$handle) {
        throw new Exception('Cannot read the uploaded file.');
    }

    // Remove UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        throw new Exception('Empty file or invalid CSV format.');
    }
    $headers = array_map(function($h) {
        return strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF"));
    }, $headers);
    $expected = ['registration number', 'student name', 'score'];
    if ($headers !== $expected) {
        fclose($handle);
        throw new Exception('Invalid header. Expected: "Registration Number, Student Name, Score"');
    }

    // Parse all rows
    $rows = [];
    $line = 1;
    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $line++;
        if (count($data) < 3) continue;
        $reg_no = trim($data[0]);
        $student_name = trim($data[1]);
        $score_raw = trim($data[2]);
        if ($reg_no === '' || $student_name === '') continue;
        $rows[] = [
            'line'      => $line,
            'reg_no'    => $reg_no,
            'name'      => $student_name,
            'score_raw' => $score_raw
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        throw new Exception('No valid data rows found in the CSV file.');
    }

    // Validate rows
    $validated = [];
    $stats = [
        'total' => count($rows),
        'valid_count' => 0,      // rows with valid score (will be imported)
        'ignored_empty' => 0,    // rows skipped because score empty
        'error_count' => 0,      // rows with real errors
        'errors' => []
    ];

    foreach ($rows as $row) {
        $skip = false;
        $error = null;
        $score_final = null;
        $existing = false;
        $status_text = '';

        if ($row['score_raw'] === '') {
            // Empty score: ignore, not an error
            $skip = true;
            $stats['ignored_empty']++;
            $status_text = 'No score (ignored)';
        } elseif (!is_numeric($row['score_raw'])) {
            $skip = true;
            $error = 'Not a number';
            $stats['error_count']++;
            $stats['errors'][] = "Line {$row['line']}: Not a number";
            $status_text = 'Error: Not a number';
        } else {
            $score_final = round((float)$row['score_raw'], 2);
            if ($score_final < 0 || $score_final > 100) {
                $skip = true;
                $error = 'Score out of range (0-100)';
                $stats['error_count']++;
                $stats['errors'][] = "Line {$row['line']}: Score out of range (0-100)";
                $status_text = 'Error: Out of range';
            } else {
                // Valid score
                $skip = false;
                $stats['valid_count']++;
                // Check if result already exists
                $stmt = $pdo->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
                $stmt->execute([$row['reg_no'], $class_id, $subject_comb_id, $term_id]);
                $existing = (bool)$stmt->fetch();
                $status_text = $existing ? 'Update' : 'Insert';
            }
        }

        $validated[] = [
            'line'       => $row['line'],
            'reg_no'     => $row['reg_no'],
            'name'       => $row['name'],
            'score_raw'  => $row['score_raw'] === '' ? '(empty)' : $row['score_raw'],
            'score_final'=> $score_final,
            'skip'       => $skip,        // true if this row should NOT be imported
            'error'      => $error,
            'existing'   => $existing,
            'status_text'=> $status_text
        ];
    }

    // PREVIEW MODE
    if ($is_preview) {
        echo json_encode([
            'success' => true,
            'data' => [
                'rows'  => $validated,
                'stats' => $stats
            ]
        ]);
        exit;
    }

    // IMPORT MODE
    if ($is_import) {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $import_errors = [];

        foreach ($validated as $v) {
            if ($v['skip']) {
                $skipped++;
                continue;
            }
            try {
                if ($v['existing']) {
                    $stmt = $pdo->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
                    $stmt->execute([$v['score_final'], $v['reg_no'], $class_id, $subject_comb_id, $term_id]);
                    $updated++;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$v['reg_no'], $class_id, $subject_comb_id, $term_id, $v['score_final']]);
                    $inserted++;
                }
            } catch (PDOException $e) {
                $import_errors[] = "Line {$v['line']}: DB error - " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "Import completed. Inserted: $inserted, Updated: $updated, Skipped: $skipped.";
        if (!empty($import_errors)) {
            $error_preview = implode('; ', array_slice($import_errors, 0, 5));
            if (count($import_errors) > 5) $error_preview .= '...';
            $message .= " Errors: " . $error_preview;
            $_SESSION['import_result'] = ['warning', $message];
        } else {
            $_SESSION['import_result'] = ['success', $message];
        }
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>