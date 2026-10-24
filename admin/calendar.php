<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_calendar(PDO $pdo): string {
    $month=isset($_GET['month'])?(int)$_GET['month']:(int)date('n');
    $year=isset($_GET['year'])?(int)$_GET['year']:(int)date('Y');
    if($month<1){
        $month=12;
        $year--;
    }
    if($month>12){
        $month=1;
        $year++;
    }
    $first=strtotime(sprintf('%04d-%02d-01',$year,$month));
    $start=(int)date('N',$first);
    $days=(int)date('t',$first);
    $prevMonth=$month===1?12:$month-1;
    $prevYear=$month===1?$year-1:$year;
    $nextMonth=$month===12?1:$month+1;
    $nextYear=$month===12?$year+1:$year;
    $st=$pdo->prepare("SELECT id,title,start_date,status FROM training_programs WHERE YEAR(start_date)=? AND MONTH(start_date)=? AND status<>'Cancelled'");
    $st->execute([$year,
        $month]);
    $progs=$st->fetchAll();
    $byDay=[];
    foreach($progs as $p)$byDay[(int)date('j',
        strtotime($p['start_date']))][]=$p;
    $html=page_intro('Training Calendar','View upcoming faculty development sessions and scheduled program dates.');
    $html.='<section class="card calendar-card"><div class="calendar-toolbar"><a class="btn btn-secondary" href="?page=Training Calendar&month='
    . $prevMonth.'&year='.$prevYear
    . '" aria-label="Previous month">‹</a><h3>'
    . h(date('F Y',$first))
    . '</h3><a class="btn btn-secondary" href="?page=Training Calendar&month='
    . $nextMonth.'&year='.$nextYear
    . '" aria-label="Next month">›</a><div class="view-switch"><a class="btn btn-ghost" href="?page=Training Calendar">Today / current month</a>'
    .  '</div></div><div class="calendar-grid">';
    foreach(['Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat',
        'Sun'] as $d)$html.='<div class="weekday">'
    . $d
    . '</div>';
    $prevDays=(int)date('t',strtotime($year.'-'.$month.'-01 -1 month'));
    $cellCount=(int)(ceil(($start+$days-1)/7)*7);
    for($i=1;$i<=$cellCount;$i++){
        $day=$i-$start+1;
        $outside=false;
        if($day<1){
            $label=$prevDays+$day;
            $outside=true;
        }
        elseif($day>$days){
            $label=$day-$days;
            $outside=true;
        }
        else{
            $label=$day;
        }
        $html.='<div class="cal-day'.($outside?' outside':'').'">'.(int)$label;
        if(!$outside&&isset($byDay[$day]))foreach($byDay[$day] as $p){
            $color=$p['status']==='Completed'?'green':($p['status']==='Ongoing'?'blue':'');
            $html.='<a class="cal-event '.h($color).'" href="?page=Training Programs" title="'.h($p['title'])
            . '">'.h($p['title'])
            . '</a>';
        }
        $html.='</div>';
    }
    return $html
    . '</div><p class="small-muted" style="margin-top:1rem">'
    . count($progs)
    . ' program(s) scheduled in this month. Create or edit training programs from Training Programs.</p></section>';
}

$page = 'Training Calendar';
$content = render_calendar($pdo);
render_app($pdo, $user, $page, $flash, $content);
