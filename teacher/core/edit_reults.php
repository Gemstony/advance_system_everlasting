<?php
chdir('../../');            // go to project root
session_start();
require_once('db/config.php');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("location:../");
    exit;
}

// Get parameters (student ID is string, term is integer)
$student = isset($_GET['std']) ? $_GET['std'] : '';
$term    = isset($_GET['term']) ? (int)$_GET['term'] : 0;

if (empty($student) || $term <= 0) {
    die("Invalid student or term ID.");
}

// Store in session for single_results.php
$_SESSION['student_result']['student'] = $student;
$_SESSION['student_result']['term']    = $term;

// Redirect to the editing form (teacher/single_results.php)
header("Location: ../single_results");
exit;