<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('includes/functions.php');

if ($res != "1" || $level != "1") die("Unauthorized");

$id = $_GET['id'];
$event = getEventById($id, $pdo);
if (!$event) die("Event not found");
$categories = getEventCategories($pdo);

$pending_requests = 0;
$current_page = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE tbl_events SET title=?, description=?, event_date=?, start_time=?, end_time=?, venue=?, category=?, color=? WHERE id=?");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['event_date'], $_POST['start_time']?:null, $_POST['end_time']?:null, $_POST['venue'], $_POST['category'], $_POST['color'], $id]);
    header("Location: academic/event-dashboard.php?msg=updated");
    exit;
}
?>
<!DOCTYPE html>
<html><head><title>Edit Event</title><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="#">SRMS</a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li></ul></li></ul></header>
<?php require 'inc/sidebar_academic.php'; ?>
<main class="app-content"><div class="app-title"><div><h1><i class="bi bi-pencil-square"></i> Edit Event</h1></div></div>
<div class="row"><div class="col-md-8"><div class="tile"><form method="POST"><div class="mb-3"><label>Title</label><input type="text" name="title" value="<?=htmlspecialchars($event['title'])?>" class="form-control" required></div><div class="mb-3"><label>Description</label><textarea name="description" rows="3" class="form-control"><?=htmlspecialchars($event['description'])?></textarea></div><div class="mb-3"><label>Date</label><input type="date" name="event_date" value="<?=$event['event_date']?>" class="form-control" required></div><div class="row"><div class="col-md-6"><label>Start Time</label><input type="time" name="start_time" value="<?=$event['start_time']?>" class="form-control"></div><div class="col-md-6"><label>End Time</label><input type="time" name="end_time" value="<?=$event['end_time']?>" class="form-control"></div></div><div class="mb-3"><label>Venue</label><input type="text" name="venue" value="<?=htmlspecialchars($event['venue'])?>" class="form-control"></div><div class="mb-3"><label>Category</label><select name="category" class="form-control"><?php foreach($categories as $cat):?><option value="<?=$cat['name']?>" <?=$event['category']==$cat['name']?'selected':''?>><?=$cat['name']?></option><?php endforeach;?></select></div><div class="mb-3"><label>Color</label><input type="color" name="color" value="<?=$event['color']?>" class="form-control w-25"></div><button class="btn btn-primary">Update Event</button> <a href="academic/event-dashboard.php" class="btn btn-secondary">Cancel</a></form></div></div></div></main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body>
</html>