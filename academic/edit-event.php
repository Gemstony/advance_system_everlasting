<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
if (!isAcademicLoggedIn()) die("Unauthorized");
$id = $_GET['id'];
$event = getEventById($id, $pdo);
if (!$event) die("Event not found");
$categories = getEventCategories($pdo);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE tbl_events SET title=?, description=?, event_date=?, start_time=?, end_time=?, venue=?, category=?, color=? WHERE id=?");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['event_date'], $_POST['start_time']?:null, $_POST['end_time']?:null, $_POST['venue'], $_POST['category'], $_POST['color'], $id]);
    header("Location: dashboard.php?msg=updated");
    exit;
}
?>
<!DOCTYPE html>
<html><head><title>Edit Event</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'inc/sidebar.php'; ?><div class="container"><div class="glass-card p-4"><h3>Edit Event</h3><form method="POST"><div class="mb-3"><label>Title</label><input type="text" name="title" value="<?=htmlspecialchars($event['title'])?>" class="form-control" required></div><div class="mb-3"><label>Description</label><textarea name="description" rows="3" class="form-control"><?=htmlspecialchars($event['description'])?></textarea></div><div class="mb-3"><label>Date</label><input type="date" name="event_date" value="<?=$event['event_date']?>" class="form-control" required></div><div class="row"><div class="col-md-6"><label>Start Time</label><input type="time" name="start_time" value="<?=$event['start_time']?>" class="form-control"></div><div class="col-md-6"><label>End Time</label><input type="time" name="end_time" value="<?=$event['end_time']?>" class="form-control"></div></div><div class="mb-3"><label>Venue</label><input type="text" name="venue" value="<?=htmlspecialchars($event['venue'])?>" class="form-control"></div><div class="mb-3"><label>Category</label><select name="category" class="form-control"><?php foreach($categories as $cat):?><option value="<?=$cat['name']?>" <?=$event['category']==$cat['name']?'selected':''?>><?=$cat['name']?></option><?php endforeach;?></select></div><div class="mb-3"><label>Color</label><input type="color" name="color" value="<?=$event['color']?>" class="form-control w-25"></div><button class="btn btn-primary">Update Event</button> <a href="dashboard.php" class="btn btn-secondary">Cancel</a></form></div></div></body></html>