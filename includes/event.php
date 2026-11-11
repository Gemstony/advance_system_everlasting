<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$events = getEventsByDate($date, $pdo);
include '../includes/header.php';
?>
<div class="glass-card p-4">
    <h2><i class="bi bi-calendar-event"></i> Events on <?= date('F j, Y', strtotime($date)) ?></h2>
    <?php if (empty($events)): ?>
        <div class="alert alert-info">No events scheduled for this day.</div>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <div class="card bg-dark text-white mb-3 border-0 shadow-sm" style="border-left: 5px solid <?= $event['color'] ?>;">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-tag-fill" style="color: <?= $event['color'] ?>"></i> <?= htmlspecialchars($event['title']) ?></h5>
                    <p class="card-text"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-clock"></i> Time: <?= $event['start_time'] ? date('h:i A', strtotime($event['start_time'])) : 'All day' ?> 
                            <?= $event['end_time'] ? ' - ' . date('h:i A', strtotime($event['end_time'])) : '' ?></li>
                        <li><i class="bi bi-geo-alt"></i> Venue: <?= htmlspecialchars($event['venue'] ?: 'Not specified') ?></li>
                        <li><i class="bi bi-folder"></i> Category: <?= htmlspecialchars($event['category']) ?></li>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="month.php?month=<?= date('n', strtotime($date)) ?>&year=<?= date('Y', strtotime($date)) ?>" class="btn btn-glass mt-3">
        <i class="bi bi-arrow-left"></i> Back to Calendar
    </a>
</div>
<?php include '../includes/footer.php'; ?>