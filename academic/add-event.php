<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
if (!isAcademicLoggedIn()) { header('Location: login.php'); exit; }
$date = $_GET['date'] ?? date('Y-m-d');
$categories = getEventCategories($pdo);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("INSERT INTO tbl_events (title, description, event_date, start_time, end_time, venue, category, color, posted_by, created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW())");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['event_date'], $_POST['start_time']?:null, $_POST['end_time']?:null, $_POST['venue'], $_POST['category'], $_POST['color'], $_SESSION['academic_id']]);
    header('Location: dashboard.php?msg=added');
    exit;
}
?>
<!DOCTYPE html>
<html><head><title>Add Event</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'inc/sidebar.php'; ?><div class="container"><div class="glass-card p-4"><h3>Add New Event</h3><form method="POST"><div class="mb-3"><label>Title</label><input type="text" name="title" class="form-control" required></div><div class="mb-3"><label>Description</label><textarea name="description" rows="3" class="form-control"></textarea></div><div class="mb-3"><label>Date</label><input type="date" name="event_date" value="<?=$date?>" class="form-control" required></div><div class="row"><div class="col-md-6"><label>Start Time</label><input type="time" name="start_time" class="form-control"></div><div class="col-md-6"><label>End Time</label><input type="time" name="end_time" class="form-control"></div></div><div class="mb-3"><label>Venue</label><input type="text" name="venue" class="form-control"></div><div class="mb-3"><label>Category</label><select name="category" class="form-control"><?php foreach($categories as $cat):?><option value="<?=$cat['name']?>" style="color:<?=$cat['color']?>"><?=$cat['name']?></option><?php endforeach;?></select></div><div class="mb-3"><label>Color</label><input type="color" name="color" class="form-control w-25" value="#4f46e5"></div><button class="btn btn-primary">Save Event</button> <a href="dashboard.php" class="btn btn-secondary">Cancel</a></form></div></div></body></html>