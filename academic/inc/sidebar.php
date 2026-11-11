<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/calendar.php';
if (!isAcademicLoggedIn()) { header('Location: login.php'); exit; }
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$events = getEventsByMonth($year, $month, $pdo);
$eventsByDay = [];
foreach ($events as $ev) { $eventsByDay[$ev['event_date']][] = $ev; }
?>
<!DOCTYPE html>
<html>
<head><title>Academic Calendar</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"><link rel="stylesheet" href="../assets/css/calendar.css"><style>body{background:#0f172a;}.admin-calendar .calendar-day{background:rgba(255,255,255,0.05);cursor:pointer;}.admin-calendar .calendar-day:hover{background:rgba(67,97,238,0.3);transform:scale(1.02);}</style></head>
<body><?php include 'inc/sidebar.php'; ?>
<div class="container-fluid"><div class="glass-card p-4 mb-4"><div class="d-flex justify-content-between"><h2>Event Management – <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h2><div><a href="?month=<?= $month-1<1?12:$month-1 ?>&year=<?= $month==1?$year-1:$year ?>" class="btn btn-glass">← Prev</a> <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-glass">Today</a> <a href="?month=<?= $month+1>12?1:$month+1 ?>&year=<?= $month==12?$year+1:$year ?>" class="btn btn-glass">Next →</a> <a href="add-event.php" class="btn btn-success ms-2"><i class="bi bi-plus"></i> New Event</a></div></div></div>
<div class="admin-calendar"><?php echo renderCalendarGrid($year, $month, $eventsByDay, 'add-event.php?date=', true); ?></div>
<div class="mt-4"><h4>Upcoming Events (quick edit)</h4><div class="row"><?php $stmt=$pdo->prepare("SELECT * FROM tbl_events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 5");$stmt->execute();$upcoming=$stmt->fetchAll();foreach($upcoming as $ev):?><div class="col-md-4 mb-3"><div class="glass-card p-3"><strong><?=htmlspecialchars($ev['title'])?></strong><br><small><?=$ev['event_date']?> <?=$ev['start_time']?date('h:i A',strtotime($ev['start_time'])):''?></small><div class="mt-2"><a href="edit-event.php?id=<?=$ev['id']?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a> <a href="delete-event.php?id=<?=$ev['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')"><i class="bi bi-trash"></i> Delete</a></div></div></div><?php endforeach;?></div></div></div><?php include '../includes/footer.php'; ?>