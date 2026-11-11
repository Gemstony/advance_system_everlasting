<?php
function renderCalendarGrid($year, $month, $eventsByDay, $baseUrl, $isAdmin = false) {
    $firstDayOfMonth = mktime(0,0,0,$month,1,$year);
    $daysInMonth = date('t',$firstDayOfMonth);
    $startWeekday = date('w',$firstDayOfMonth);
    if($startWeekday==0) $startWeekday=7;
    $calendar=[]; $week=array_fill(1,7,''); $currentDay=1; $startOffset=$startWeekday-1;
    for($i=1;$i<=$startOffset;$i++) $week[$i]='';
    $cell=$startOffset+1;
    while($currentDay<=$daysInMonth){
        $week[$cell]=$currentDay;
        if($cell==7||$currentDay==$daysInMonth){ $calendar[]=$week; $week=array_fill(1,7,''); $cell=0; }
        $currentDay++; $cell++;
    }
    $html='<div class="calendar-grid"><div class="calendar-weekdays">';
    foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd) $html.="<div class='calendar-weekday'>$wd</div>";
    $html.='</div><div class="calendar-days">';
    foreach($calendar as $week){
        for($i=1;$i<=7;$i++){
            $day=$week[$i];
            if(empty($day)) $html.='<div class="calendar-day empty"></div>';
            else{
                $dateKey=sprintf('%04d-%02d-%02d',$year,$month,$day);
                $hasEvents=isset($eventsByDay[$dateKey]) && count($eventsByDay[$dateKey])>0;
                $eventColors=$hasEvents?array_unique(array_column($eventsByDay[$dateKey],'color')):[];
                $markers=''; foreach($eventColors as $col) $markers.="<span class='event-dot' style='background-color:$col;'></span>";
                $eventCount=$hasEvents?count($eventsByDay[$dateKey]):0;
                $badge=$eventCount>0?"<span class='event-count'>$eventCount</span>":'';
                $link=$isAdmin?"$baseUrl$dateKey":"$baseUrl?date=$dateKey";
                $html.="<div class='calendar-day'><div class='day-number'><a href='$link'>$day</a>$badge</div><div class='event-markers'>$markers</div></div>";
            }
        }
    }
    $html.='</div></div>';
    return $html;
}
?>