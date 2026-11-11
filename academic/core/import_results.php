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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$term = $_POST['term'] ?? 0;
if (!$term) {
    echo json_encode(['success' => false, 'message' => 'Term is required.']);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error.']);
    exit;
}

// Validate CSV extension
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (strtolower($ext) !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed.']);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify term exists
    $stmt = $pdo->prepare("SELECT id FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term]);
    if (!$stmt->fetch()) throw new Exception("Invalid term ID.");

    // ---- Detect delimiter ----
    $firstLine = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0];
    $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) throw new Exception("Cannot read file.");

    // ---- Read and clean headers ----
    $rawHeaders = fgetcsv($handle, 0, $delimiter);
    if (!$rawHeaders) throw new Exception("Empty CSV file.");

    // Remove BOM from first header
    $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]);

    // Trim all headers
    $headers = array_map('trim', $rawHeaders);
    $headersLower = array_map('strtolower', $headers);

    // ---- Flexible mapping for required columns ----
    $requiredPatterns = [
        'reg'   => ['registration number', 'reg no', 'reg number', 'registration no', 'student id', 'regno'],
        'name'  => ['student name', 'full name', 'name'],
        'class' => ['class', 'class name']
    ];

    $colMap = [];
    foreach ($requiredPatterns as $key => $patterns) {
        $colMap[$key] = false;
        foreach ($patterns as $pattern) {
            $idx = array_search($pattern, $headersLower);
            if ($idx !== false) {
                $colMap[$key] = $idx;
                break;
            }
        }
        if ($colMap[$key] === false) {
            throw new Exception("Invalid CSV format: missing required column. Must include one of: " . implode(', ', $patterns));
        }
    }

    // ---- Parse subject columns (remaining columns) ----
    $subjectColumns = [];
    for ($i = 0; $i < count($headers); $i++) {
        if (!in_array($i, $colMap)) {
            // Expect format: "Subject Name [combo_id]"
            if (preg_match('/\[(\d+)\]$/', $headers[$i], $matches)) {
                $subjectColumns[$i] = (int)$matches[1];
            } else {
                throw new Exception("Invalid subject header at column " . ($i+1) . ": " . $headers[$i] . " (must contain [combo_id] at the end)");
            }
        }
    }

    if (empty($subjectColumns)) {
        throw new Exception("No subject columns found in CSV.");
    }

    // ---- Prepare statements ----
    $checkStmt = $pdo->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
    $insertStmt = $pdo->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?, ?, ?, ?, ?)");
    $updateStmt = $pdo->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");

    $rowNumber = 1; // header row counted
    $imported = 0;
    $updated = 0;
    $errors = [];

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNumber++;
        if (count($data) < 3) {
            $errors[] = "Row $rowNumber: insufficient columns.";
            continue;
        }

        $reg_no = trim($data[$colMap['reg']]);
        $studentName = trim($data[$colMap['name']]); // not used for validation, but we keep for logging if needed
        $classIdRaw = trim($data[$colMap['class']]);

        if (!is_numeric($classIdRaw)) {
            $errors[] = "Row $rowNumber: Class must be a number (class ID).";
            continue;
        }
        $class_id = (int)$classIdRaw;

        // Verify student exists in this class
        $stmt = $pdo->prepare("SELECT id FROM tbl_students WHERE id = ? AND class = ?");
        $stmt->execute([$reg_no, $class_id]);
        if (!$stmt->fetch()) {
            $errors[] = "Row $rowNumber: student $reg_no not found in class $class_id.";
            continue;
        }

        // Process each subject column
        foreach ($subjectColumns as $colIndex => $comboId) {
            $score = isset($data[$colIndex]) ? trim($data[$colIndex]) : '';
            if ($score === '') {
                // Skip empty scores (do nothing)
                continue;
            }
            if (!is_numeric($score) || $score < 0 || $score > 100) {
                $errors[] = "Row $rowNumber, column " . ($colIndex+1) . ": score must be a number between 0 and 100.";
                continue;
            }

            // Check if record exists
            $checkStmt->execute([$reg_no, $class_id, $comboId, $term]);
            if ($checkStmt->fetch()) {
                // Update
                $updateStmt->execute([$score, $reg_no, $class_id, $comboId, $term]);
                $updated++;
            } else {
                // Insert
                $insertStmt->execute([$reg_no, $class_id, $comboId, $term, $score]);
                $imported++;
            }
        }
    }

    fclose($handle);

    $message = "Import completed. $imported new rows inserted, $updated updated.";
    if (!empty($errors)) {
        $message .= " Errors: " . implode('; ', array_slice($errors, 0, 10));
        if (count($errors) > 10) $message .= " (and " . (count($errors)-10) . " more)";
        // Since there were errors, we still consider the import partially successful but return success false? Original returns false with message.
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        echo json_encode(['success' => true, 'message' => $message]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}