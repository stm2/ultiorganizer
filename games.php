<?php
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

if (is_file('cust/'.CUSTOMIZATIONS.'/pdfprinter.php')) {
  include_once 'cust/'.CUSTOMIZATIONS.'/pdfprinter.php';
} else {
  include_once 'cust/default/pdfprinter.php';
}

$html = "";
$filter = 'tournaments';
$baseurl = "?view=games";
$id = 0;
$print = 0;
$gamefilter = "season";
$format = "html";
$group = "";
$groupheader = true;
$games;

if(iget("series")) {
  $id = iget("series");
  $baseurl .= "&series=$id";
  $gamefilter="series";
  $title = _("Schedule")." ".utf8entities(U_(SeriesName($id)));
} elseif(iget("pool")) {
  $id = iget("pool");
  $baseurl .= "&pool=$id";
  $gamefilter="pool";
  $title = _("Schedule")." ".utf8entities(U_(PoolSeriesName($id)).", ".U_(PoolName($id)));
} elseif(iget("pools")) {
  $id = iget("pools");
  $baseurl .= "&pools=$id";
  $gamefilter="poolgroup";
  $title = _("Schedule")." ".utf8entities(U_(PoolSeriesName($id)).", ".U_(PoolName($id)));
} elseif(iget("team")) {
  $id = iget("team");
  $baseurl .= "&team=$id";
  $gamefilter="team";
  $filter = 'places';
  $title = _("Schedule")." ".utf8entities(TeamName($id));
} elseif(iget("season")) {
  $id = iget("season");
  $baseurl .= "&season=$id";
  $gamefilter="season";
  $title = _("Schedule")." ".utf8entities(U_(SeasonName($id)));
  $comment = CommentHTML(1, $id);
} else {
  $id = CurrentSeason();
  $baseurl .= "&season=$id";
  $gamefilter="season";
  $title = _("Schedule")." ".utf8entities(U_(SeasonName($id)));
}

$filter  = iget("filter");
if(empty($filter)){
  $filter = 'tournaments';
}

$group  = iget("group");
if(empty($group)){
  $group="all";
}

if(iget("print")) {
  $print = intval(iget("print"));
  $format = "paper";
}

$singleview = 0;

if(iget("singleview")) {
  $singleview = intval(iget("singleview"));
}

$timefilter="all";
$order="tournaments";

switch($filter){
  case "today":
    $timefilter="today";
    $order="series";
    break;

  case "tomorrow":
    $timefilter="tomorrow";
    $order="series";
    break;
    
  case "yesterday":
    $timefilter="yesterday";
    $order="series";
    break;    

  case "next":
    $order="tournaments";
    $order="series";
    break;

  case "tournaments":
    $timefilter="all";
    $order="tournaments";
    break;

  case "series":
    $timefilter="all";
    $order="series";
    break;

  case "places":
    $timefilter="all";
    $order="places";
    break;

  case "season":
    $timefilter="all";
    $order="places";
    $format = "pdf";
    break;

  case "onepage":
    $timefilter="all";
    $order="onepage";
    $format = "pdf";
    break;

  case "timeslot":
    $timefilter="all";
    $order="time";
    break;

  default:
    $timefilter="all";
    $order="tournaments";
    break;
}

$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
$groups = TimetableGrouping($id, $gamefilter, $timefilter);

if($format=="pdf"){
  $pdf = new PDF();
  if($filter=="onepage"){
    $pdf->PrintOnePageSchedule($gamefilter, $id, $games);
  }else{
    $pdf->PrintSchedule($gamefilter, $id, $games);
  }
  $pdf->Output();
}

if(!$print && !$singleview){
  $menutabs[_("By grouping")]= ($baseurl)."&filter=tournaments&group=$group";
  $menutabs[_("By timeslot")]= ($baseurl)."&filter=timeslot&group=$group";
  $menutabs[_("By division")]= ($baseurl)."&filter=series&group=$group";
  $menutabs[_("By location")]= ($baseurl)."&filter=places&group=$group";
  $menutabs[_("Today")]= ($baseurl)."&filter=today&group=$group";
  $menutabs[_("Tomorrow")]= ($baseurl)."&filter=tomorrow&group=$group";
  $menutabs[_("Yesterday")]= ($baseurl)."&filter=yesterday&group=$group";

  $html .= pageMenu($menutabs,"",false);
  
  if (count($groups) > 1) {
    $html .= "<p>\n";
    foreach ($groups as $grouptmp) {
      if (empty($grouptmp['reservationgroup'])) {
        $html .= grouplink($baseurl, $filter, "none", _("Without grouping"));
      } else {
        $html .= grouplink($baseurl, $filter, $grouptmp['reservationgroup'], U_($grouptmp['reservationgroup']), 
            $group == $grouptmp['reservationgroup']);
      }
      $html .= " ";
    }
    $html .= grouplink($baseurl, $filter, "all", _("All groupings"), $group == "all");
    $html .= "</p>\n";
    $html .= "<p style='clear:both'></p>\n";
  }
}

if(!empty($group) && $group!="all"){
  $groupheader=false;
}

if(mysql_num_rows($games)==0){
  $html .= "\n<p>"._("No games").".</p>\n";
}elseif($filter == 'tournaments'){
  $html .= TournamentView($games,$groupheader);
}elseif($filter == 'series'){
  $html .= SeriesView($games, true, true);
}elseif($filter == 'next'){
  $html .= TournamentView($games,$groupheader);
}elseif($filter == 'places'){
  $html .= PlaceView($games,$groupheader);
}elseif($filter == 'timeslot'){
  $html .= TimeView($games);
}elseif(in_array($filter, array('today', 'tomorrow', 'yesterday'))){
  $html .= SeriesView($games, false, true);
}else { // ($filter == 'all')
  $html .= SeriesView($games);
}


$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/","",$querystring);
if($print){
  $html .= "<hr/><div style='text-align:right'><a href='?".utf8entities($querystring)."'>"._("Return")."</a></div>";
}elseif(mysql_num_rows($games)){
  $html .= "<hr/>\n";
  $html .= "<p>";
  $html .= "<a href='?view=ical&amp;$gamefilter=$id&amp;time=$timefilter&amp;order=$order'>"._("iCalendar (.ical)")."</a> | ";
  $html .= "<a href='".utf8entities($baseurl)."&amp;filter=onepage&group=$group'>"._("Grid (PDF)")."</a> | ";
  $html .= "<a href='".utf8entities($baseurl)."&amp;filter=season&group=$group'>"._("List (PDF)")."</a> | ";
  $html .= "<a href='?".utf8entities($querystring)."&amp;print=1'>"._("Printable version")."</a>";
  $html .= "</p>\n";
}
if($print){
  showPrintablePage($title, $html);
}else{
  showPage($title, $html);
}

function grouplink($baseurl, $filter, $group, $name, $selected = false) {
  if ($selected)
    return "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=" .
         utf8entities($group) . "'><span class='selgroupinglink'>" . utf8entities($name) . "</span></a>";
  else
    return "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=" .
         utf8entities($group) . "'>" . utf8entities($name) . "</a>";
}


?>
