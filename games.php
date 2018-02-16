<?php
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

if (is_file('cust/' . CUSTOMIZATIONS . '/pdfprinter.php')) {
  include_once 'cust/' . CUSTOMIZATIONS . '/pdfprinter.php';
} else {
  include_once 'cust/default/pdfprinter.php';
}

$html = "";

$filters = $_GET;

$orderdefault = 'tournaments';

$id = 0;
$print = getPrintMode();

function grouplink($filters, $newfilters, $name, $selected = false) {
  if ($selected) {
    $filters['selected'] = 'selected';
    return "<a class='groupinglink' href='" . utf8entities(MakeUrl($filters, $newfilters)) .
       "'><span class='selgroupinglink'>" . utf8entities($name) . "</span></a>";
  } else {
    return "<a class='groupinglink' href='" . utf8entities(MakeUrl($filters, $newfilters)) . "'>" . utf8entities($name) .
       "</a>";
  }
}

function findPools($games, &$html) {
  $subset = array();
  while ($game = mysqli_fetch_assoc($games)) {
    $subset[$game['pool']] = true;
  }
  return $subset;
}

$groupheader = true;

if (iget("series")) {
  $id = iget("series");
  SetCurrentSeries($id);
  $filters['gamefilter'] = "series";
  $title = _("Schedule") . " " . utf8entities(U_(SeriesName($id)));
} elseif (iget("pool")) {
  $id = iget("pool");
  $filters['gamefilter'] = "pool";
  $title = _("Schedule") . " " . utf8entities(U_(PoolSeriesName($id)) . ", " . U_(PoolName($id)));
} elseif (iget("pools")) {
  $id = iget("pools");
  $filters['gamefilter'] = "poolgroup";
  $title = _("Schedule") . " " . utf8entities(U_(PoolSeriesName($id)) . ", " . U_(PoolName($id)));
} elseif (iget("team")) {
  $id = iget("team");
  $filters['gamefilter'] = "team";
  $orderdefault = 'places';
  $title = _("Schedule") . " " . utf8entities(TeamName($id));
} elseif (iget("season")) {
  $id = iget("season");
  $filters['gamefilter'] = "season";
  $title = _("Schedule") . " " . utf8entities(U_(SeasonName($id)));
  $comment = CommentHTML(1, $id);
} else {
  $id = CurrentSeason();
  $filters['gamefilter'] = "season";
  $title = _("Schedule") . " " . utf8entities(U_(SeasonName($id)));
}

if (!isset($filters['gamefilter']))
  $filters['gamefilter'] = 'season';

$singleview = 0;

if (iget("singleview")) {
  $singleview = intval(iget("singleview"));
}

$format = iget("format");
if (empty($format)) {
  $format = 'html';
}

if (!isset($filters['order']))
  $filters['order'] = $orderdefault;

if (!isset($filters['time']))
  $filters['time'] = "all";

if (!isset($filters['group']))
  $filters['group'] = "all";

$games = TimetableGames($id, $filters['gamefilter'], $filters['time'], $filters['order'], $filters['group']);
$groups = TimetableGrouping($id, $filters['gamefilter'], $filters['time']);

if (!$print && !$singleview) {
  
  $menutabs[_("By grouping")] = MakeUrl($filters, array('order' => 'tournaments'));
  $menutabs[_("By timeslot")] = MakeUrl($filters, array('order' => 'timeslot'));
  $menutabs[_("By division")] = MakeUrl($filters, array('order' => 'series'));
  $menutabs[_("By location")] = MakeUrl($filters, array('order' => 'places'));
  
  $menutabs[_("Today")] = MakeUrl($filters, array('time' => 'today'));
  $menutabs[_("Tomorrow")] = MakeUrl($filters, array('time' => 'tomorrow'));
  $menutabs[_("Past")] = MakeUrl($filters, array('time' => 'past'));
  $menutabs[_("Future")] = MakeUrl($filters, array('time' => 'coming'));
  $menutabs[_("All")] = MakeUrl($filters, array('time' => 'all'));
  
  $html .= pageMenu($menutabs, MakeUrl($filters), false);
  
  if (count($groups) > 1) {
    $html .= "<p>\n";
    foreach ($groups as $grouptmp) {
      if (empty($grouptmp['reservationgroup'])) {
        $html .= grouplink($filters, array('group' => 'none'), _("Without grouping"));
      } else {
        $html .= grouplink($filters, array('group' => $grouptmp['reservationgroup']), U_($grouptmp['reservationgroup']),
          $filters['group'] == $grouptmp['reservationgroup']);
      }
      $html .= " ";
    }
    $html .= grouplink($filters, array('group' => "all"), _("All groupings"), $filters['group'] == "all");
    $html .= "</p>\n";
    $html .= "<p style='clear:both'></p>\n";
  }
}

if (!empty($filters['group']) && $filters['group'] != "all") {
  $groupheader = false;
}

$lines = array();

// $groups = array('date' => true, 'time' => true, 'field' => true, 'pool' => true, 'result' => true);

if (mysqli_num_rows($games) == 0) {
  $html .= "\n<p>" . _("No games") . ".</p>\n";
} elseif ($filters['order'] == 'tournaments') {
  $lines[] = array('type' => 'groups', 'groups' => array('date' => false, 'pool' => false));
  $html .= TournamentView($games, $groupheader, $lines);
} elseif ($filters['order'] == 'series') {
  $lines[] = array('type' => 'groups', 'groups' => array('pool' => false));
  $html .= SeriesView($games, true, true, $lines);
} elseif ($filters['order'] == 'places') {
  $lines[] = array('type' => 'groups', 'groups' => array('date' => false, 'field' => false));
  $html .= PlaceView($games, $groupheader, $lines);
} elseif ($filters['order'] == 'timeslot') {
  $lines[] = array('type' => 'groups', 'groups' => array('date' => false, 'time' => false));
  $html .= TimeView($games, true, $lines);
} else { // ($filter == 'all')
  $lines[] = array('type' => 'groups', 'groups' => array('date' => true, 'time' => false, 'pool' => false));
  $html .= SeriesView($games, true, false, $lines);
}

mysqli_data_seek($games, 0);
$subset = findPools($games, $html);

if ($format == "grid" || $format == "list") {
  $pdf = new PDF();
  if ($format == "grid") {
    mysqli_data_seek($games, 0);
    $pdf->PrintOnePageSchedule($filters['gamefilter'], $id, $games);
  } else {
    mysqli_data_seek($games, 0);
    $pdf->PrintSchedule($filters['gamefilter'], $id, $games, $subset, $lines);
  }
  $pdf->Output();
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/", "", $querystring);

if (!$print) {
  addFooter(
    MakeUrl(
      array('view' => 'ical', $filters['gamefilter'] => $id, 'time' => $filters['time'], 'order' => $filters['order'])),
    _("iCalendar (.ical)"));
  addFooter(utf8entities(MakeUrl($filters, array('format' => 'grid'))), _("Grid (PDF)"));
  addFooter(utf8entities(MakeUrl($filters, array('format' => 'list'))), _("List (PDF)"));
}

showPage($title, $html);
?>
