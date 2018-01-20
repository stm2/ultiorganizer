<?php
include_once $include_prefix.'lib/season.functions.php';

$title = _("Contacts");
$html = "";

if (empty($_GET['season'])) {
  die(_("Event mandatory"));
}
$season = $_GET['season'];
$links = getEditSeasonLinks();
if (!isset($links[$season]['?view=user/contacts&amp;season='.$season])) {
  die(_("Inadequate user rights"));
}

$html .=  "<h2>"._("Contacts")."</h2>";

function mailtoencode($user) {
  return utf8entities($user['name']."<".$user['email'].">");
}

$html .=  "<div><a href='mailto:";
$resp = SeasonTeamAdmins($season,true);
foreach($resp as $user){
  $html .=  mailtoencode($user) .";";
}
$html .=  "'>"._("Mail to everyone registered for the event")."</a></div>";

$html .=  "<h3>"._("Mail to Event Organizers")."</h3>";
$admins = SeasonAdmins($season);
$html .=  "<ul>";
foreach($admins as $user){
  if(!empty($user['email'])){
    $html .=  "<li> <a href='mailto:".mailtoencode($user)."'>".mailtoencode($user)."</a></li>\n";
    $all .= mailtoencode($user).";";
  }
}
$html .= "<li><a href='mailto:".$all."'>". _("All organizers") ."</a></li>\n";
$html .=  "</ul>\n";

$html .=  "<h3>"._("Mail to Division Organizers")."</h3>";
$series = SeasonSeries($season);
$html .=  "<ul>";
foreach($series as $row){
  $html .=  "<li><p><b>".utf8entities(U_($row['name']))."</b></p>";
  $admins = SeriesAdmins($row['series_id'], true);
  $html .=  "<ul>";
  $all = "";
  foreach($admins as $user){
    if(!empty($user['email'])){
      $html .=  "<li><a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>";
      $html .=  " (".utf8entities($user['name']) . ")</li>\n";;
    }
  }
  $html .= "<li><a href='mailto:" . $all . "'>" . _("All admins") . "</a></li>\n";
  $html .= "</ul></li>\n";
}
$admins = SeasonSeriesAdmins($season);
$all = "";
foreach($admins as $user){
  if(!empty($user['email'])){
    $all .= mailtoencode($user).";";
  }
}

$html .= "<li><a href='mailto:".$all."'>" . _("All season admins") ."</a></li>\n";
$html .=  "</ul>\n";

$html .=  "<h3>"._("Mail to Teams")."</h3>";

$series = SeasonSeries($season);
foreach($series as $row){

  $html .=  "<p><b>".utf8entities(U_($row['name']))."</b></p>";
  $resp = SeriesTeamResponsibles($row['series_id']);
  $html .=  "<div><a href='mailto:";
  foreach($resp as $user){
    $html .=  utf8entities($user['email']).";";
  }
  $html .=  "'>"._("Mail to teams in")." ".U_($row['name'])." "._("division")."</a></div>";

  $teams = SeriesTeams($row['series_id']);
  $html .=  "<ul>";
  foreach($teams as $team){
    $html .=  "<li>".utf8entities($team['name']).":";
    $admins = GetTeamAdmins($team['team_id']);
    foreach($admins as $user){
      if(!empty($user['email'])){
        $html .=  " <a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>";
        $html .=  " (".utf8entities($user['name']).")";
      }
    }
    $html .=  "</li>\n";
  }
  $html .=  "</ul>\n";
}

showPage($title, $html);
?>