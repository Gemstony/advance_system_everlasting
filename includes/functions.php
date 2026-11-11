<?php
if (!function_exists('getEventsByMonth')) {
    function getEventsByMonth($year, $month, $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_events 
            WHERE YEAR(event_date) = ? AND MONTH(event_date) = ? 
            ORDER BY event_date ASC, start_time ASC");
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll();
    }
}
if (!function_exists('getEventsByDate')) {
    function getEventsByDate($date, $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_events WHERE event_date = ? ORDER BY start_time ASC");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
}
if (!function_exists('getYearEventStats')) {
    function getYearEventStats($year, $pdo) {
        $stmt = $pdo->prepare("SELECT MONTH(event_date) as month, COUNT(*) as count 
            FROM tbl_events WHERE YEAR(event_date) = ? GROUP BY MONTH(event_date)");
        $stmt->execute([$year]);
        $result = [];
        while ($row = $stmt->fetch()) $result[$row['month']] = $row['count'];
        return $result;
    }
}
if (!function_exists('getEventById')) {
    function getEventById($id, $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_events WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
if (!function_exists('getEventCategories')) {
    function getEventCategories($pdo) {
        $stmt = $pdo->query("SELECT * FROM event_categories ORDER BY name");
        return $stmt->fetchAll();
    }
}
?>