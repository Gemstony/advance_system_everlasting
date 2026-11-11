<?php
chdir('../');
session_start();
require_once('../db/config.php');
require_once('../const/check_session.php');

// Only academic (level 1) can post events
if ($res != "1" || $level != "1") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize inputs
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$event_date = $_POST['event_date'] ?? '';
$event_time = $_POST['event_time'] ?? null;
$location = trim($_POST['location'] ?? '');
$audience = $_POST['audience'] ?? 'both'; // default both
$posted_by = (int)($_POST['posted_by'] ?? 0);

if (empty($title) || empty($event_date) || $posted_by == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Title, date, and poster are required.']);
    exit;
}

// Validate audience value
if (!in_array($audience, ['teachers', 'students', 'both'])) {
    $audience = 'both';
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO tbl_events (title, description, event_date, event_time, location, audience, posted_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description, $event_date, $event_time, $location, $audience, $posted_by]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Event posted successfully.']);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}