<?php
// teacher/export_results_process.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "2") {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function parse_class_field($raw) {
    $out = [];
    if ($raw === null) return $out;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) {
        foreach ($un as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
        foreach ($j as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    preg_match_all('/\d+/', (string)$raw, $m);
    if (!empty($m[0])) foreach ($m[0] as $v) $out[] = (int)$v;
    return array_values(array_unique($out));
}

function safe_filename($s) {
    $s = preg_replace('/[^\w\-_\. ]+/', '', $s);
    $s = trim(str_replace(' ', '_', $s));
    return $s === '' ? 'export' : $s;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Read params
$mode = isset($_POST['mode']) && $_POST['mode'] === 'all' ? 'all' : 'filled';
$preview = isset($_POST['preview']) && $_POST['preview'] === '1';
$missing = isset($_POST['missing']) && $_POST['missing'] === 'blank' ? 'blank' : 'zero';
$only_with_scores = isset($_POST['only_with_scores']) && $_POST['only_with_scores'] == '1';
$include_meta = isset($_POST['include_meta']) && $_POST['include_meta'] == '1';

// Ensure session result__data exists
if (!isset($_SESSION['result__data']) || empty($_SESSION['result__data']['term'])) {
    if ($preview) {
        echo json_encode(['success'=>false, 'message'=>'No result context in session']);
        exit;
    }
    $_SESSION['error'] = 'No result context (term/subject) found in session.';
    header('Location: teacher/manage_results');
    exit;
}
$term = (int) $_SESSION['result__data']['term'];
$subject_combination = (int) $_SESSION['result__data']['subject'];

try {
    $dsn = 'mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset;
    $pdo = new PDO($dsn, DBUser, DBPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($mode === 'filled') {
        $class = (int) $_SESSION['result__data']['class'];
        $stmt = $pdo->prepare("SELECT c.name AS class_name, s.name AS subject_name
                               FROM tbl_classes c
                               LEFT JOIN tbl_subject_combinations sc ON sc.class = c.id
                               LEFT JOIN tbl_subjects s ON sc.subject = s.id
                               WHERE c.id = ? LIMIT 1");
        $stmt->execute([$class]);
        $meta = $stmt->fetch();
        $fileLabel = ($meta['class_name'] ?? 'class') . '_' . ($meta['subject_name'] ?? 'subject') . '_term' . $term;
        $fileName = safe_filename($fileLabel) . '.csv';

        $stmt = $pdo->prepare("SELECT er.student AS registration, s.fname, s.mname, s.lname, er.score
                               FROM tbl_exam_results er
                               LEFT JOIN tbl_students s ON er.student = s.id
                               WHERE er.class = ? AND er.subject_combination = ? AND er.term = ?
                               ORDER BY s.fname ASC, s.lname ASC");
        $stmt->execute([$class, $subject_combination, $term]);
        $rows = $stmt->fetchAll();

        if ($preview) {
            $total = count($rows);
            $with_scores = 0;
            foreach ($rows as $r) if ($r['score'] !== null) $with_scores++;
            echo json_encode(['success'=>true, 'total_students'=>$total, 'with_scores'=>$with_scores, 'missing_scores'=> $total - $with_scores]);
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE']);
        foreach ($rows as $r) {
            $name = trim($r['fname'].' '.$r['mname'].' '.$r['lname']);
            $val = $r['score'];
            if ($val === null) $val = ($missing === 'blank' ? '' : 0);
            fputcsv($out, [$r['registration'], $name, $val]);
        }
        fclose($out);
        exit;
    }

    // mode == all
    $selected = isset($_POST['classes']) && is_array($_POST['classes']) ? array_values(array_filter($_POST['classes'], function($v){ return ctype_digit((string)$v); })) : [];
    if (count($selected) === 0) {
        if ($preview) {
            echo json_encode(['success'=>false, 'message'=>'No classes selected']);
            exit;
        }
        $_SESSION['error'] = 'No classes selected.';
        header("Location: teacher/export_results.php");
        exit;
    }
    $classIds = array_map('intval', array_values(array_unique($selected)));

    // fetch class names
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($ph) ORDER BY name ASC");
    $stmt->execute($classIds);
    $clRows = $stmt->fetchAll();
    $classMap = [];
    foreach ($clRows as $c) $classMap[(int)$c['id']] = $c['name'];

    // prepare statements
    $stmtStudents = $pdo->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY fname ASC, lname ASC");
    $stmtScore = $pdo->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");

    $rowsOut = [];
    $totalStudents = 0;
    $withScores = 0;

    foreach ($classIds as $cid) {
        $stmtStudents->execute([$cid]);
        $students = $stmtStudents->fetchAll();
        foreach ($students as $st) {
            $totalStudents++;
            $stmtScore->execute([$st['id'], $cid, $subject_combination, $term]);
            $sr = $stmtScore->fetch();
            if ($sr && isset($sr['score'])) {
                $scoreVal = $sr['score'];
                $withScores++;
            } else {
                $scoreVal = null;
            }
            if ($only_with_scores && $scoreVal === null) continue;
            $rowsOut[] = [
                'class_id' => $cid,
                'class_name' => $classMap[$cid] ?? 'Class '.$cid,
                'registration' => $st['id'],
                'student_name' => trim($st['fname'].' '.$st['mname'].' '.$st['lname']),
                'score' => $scoreVal
            ];
        }
    }

    if ($preview) {
        echo json_encode(['success'=>true, 'total_students'=>$totalStudents, 'with_scores'=>$withScores, 'missing_scores'=>$totalStudents - $withScores]);
        exit;
    }

    // CSV filename
    $labelParts = [];
    foreach ($classIds as $cid) {
        if (isset($classMap[$cid])) $labelParts[] = preg_replace('/\s+/', '', substr($classMap[$cid], 0, 20));
    }
    $fileLabel = (count($labelParts) ? implode('-', $labelParts) . '_' : '') . 'term' . $term;
    $fileName = safe_filename($fileLabel) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fileName.'"');
    $out = fopen('php://output', 'w');

    // headers: include meta columns if requested
    if (count($classIds) > 1 || $include_meta) {
        if ($include_meta) {
            fputcsv($out, ['CLASS', 'REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE', 'SUBJECT', 'TEACHER']);
        } else {
            fputcsv($out, ['CLASS', 'REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE']);
        }
    } else {
        fputcsv($out, ['REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE']);
    }

    // optionally fetch subject/teacher for meta column (single lookup per class is fine)
    $scStmt = $pdo->prepare("SELECT sc.class AS class_raw, s.name AS subject_name, st.fname AS tfname, st.lname AS tlname
                             FROM tbl_subject_combinations sc
                             LEFT JOIN tbl_subjects s ON sc.subject = s.id
                             LEFT JOIN tbl_staff st ON sc.teacher = st.id
                             WHERE sc.id = ?");
    $subjectName = '';
    $teacherLabel = '';
    // For include_meta we can fetch the sc record and parse classes to match class id
    if ($include_meta) {
        $scStmt2 = $pdo->prepare("SELECT sc.class AS class_raw, s.name AS subject_name, st.fname AS tfname, st.lname AS tlname
                                  FROM tbl_subject_combinations sc
                                  LEFT JOIN tbl_subjects s ON sc.subject = s.id
                                  LEFT JOIN tbl_staff st ON sc.teacher = st.id
                                  WHERE sc.id = ?");
        $scStmt2->execute([$subject_combination]);
        $scInfo = $scStmt2->fetch();
        $scClassMap = [];
        if ($scInfo) {
            $classesForSc = parse_class_field($scInfo['class_raw']);
            foreach ($classesForSc as $cid) {
                $scClassMap[$cid] = [
                    'subject' => $scInfo['subject_name'] ?? '',
                    'teacher' => trim(($scInfo['tfname'] ?? '') . ' ' . ($scInfo['tlname'] ?? ''))
                ];
            }
        }
    }

    foreach ($rowsOut as $r) {
        $val = $r['score'];
        if ($val === null) $val = ($missing === 'blank' ? '' : 0);
        if (count($classIds) > 1 || $include_meta) {
            if ($include_meta) {
                $metaSubject = $scClassMap[$r['class_id']]['subject'] ?? '';
                $metaTeacher = $scClassMap[$r['class_id']]['teacher'] ?? '';
                fputcsv($out, [$r['class_name'], $r['registration'], $r['student_name'], $val, $metaSubject, $metaTeacher]);
            } else {
                fputcsv($out, [$r['class_name'], $r['registration'], $r['student_name'], $val]);
            }
        } else {
            fputcsv($out, [$r['registration'], $r['student_name'], $val]);
        }
    }

    fclose($out);
    exit;

} catch (PDOException $e) {
    if ($preview) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        exit;
    }
    echo "DB error: " . htmlspecialchars($e->getMessage());
    exit;
}