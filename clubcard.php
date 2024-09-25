<?php
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';
include_once 'lib/url.functions.php';

$html = "";
$clubId = iget("club");
$profile = ClubInfo($clubId);

$title = _("Club Card").": ". ($profile['name']);

$html .= "<h1>".utf8entities($profile['name'])."</h1>";

if(!empty($profile['profile_image'])){
  $html .= "<div class='profile_image'><a href='".UPLOAD_DIR."clubs/$clubId/".$profile['profile_image']."'>";
  $html .= "<img style='width:165px' src='".UPLOAD_DIR."clubs/$clubId/thumbs/".$profile['profile_image']."' alt='"._("Profile image")."'/></a></div>";
}
$html .= "<div class='club_details'><table border='0'>";
$html .= "<tr><td></td></tr>";
if($profile['country']>0){
  $country_info = CountryInfo($profile['country']);
  $html .= "<tr><td class='profileheader'>"._("Country").":</td>";
  $html .= "<td style='white-space: nowrap;'><div style='float: left; clear: left;'>";
  $html .= "<a href='?view=countrycard&amp;country=". $country_info['country_id']."'>".utf8entities($country_info['name'])."</a>";
  $html .= "</div><div>&nbsp;<img src='images/flags/tiny/".$country_info['flagfile']."' alt=''/></div>";
  $html .= "</td></tr>\n";
}
if(!empty($profile['city'])){
  $html .= "<tr><td class='profileheader'>"._("City").":</td>";
  $html .= "<td>".utf8entities($profile['city'])."</td></tr>\n";

}

if(!empty($profile['founded'])){
  $html .= "<tr><td class='profileheader'>"._("Founded").":</td>";
  $html .= "<td>".utf8entities($profile['founded'])."</td></tr>\n";
}

if(!empty($profile['homepage'])){
  $html .= "<tr><td class='profileheader'>"._("Homepage").":</td>";
  if(mb_substr(strtolower($profile['homepage']),0,4)=="http"){
    $html .= "<td><a href='". utf8entities($profile['homepage']) ."'>".utf8entities($profile['homepage'])."</a></td></tr>\n";
  }else{
    $html .= "<td><a href='http://".utf8entities($profile['homepage'])."'>".utf8entities($profile['homepage'])."</a></td></tr>\n";
  }
}
if(!empty($profile['contacts'])){
  $html .= "<tr><td class='profileheader'>"._("Contacts").":</td>";
  $contacts = utf8entities($profile['contacts']);
  $contacts = str_replace("\n",'<br/>',$contacts);
  $html .= "<tr><td class='profileheader' style='vertical-align:top'>"._("Contacts").":</td>";
  $html .= "<td>".$contacts."</td></tr>\n";
}

$html .= "</table>";
$html .= "</div>";

if(!empty($profile['story'])){
  $html .= "<div class='profileheader' colspan='2'>"._("Description").":</div>\n";
  $story = utf8entities($profile['story']);
  $story = str_replace("\n",'<br/>',$story);
  $html .= "<div class='club_story'>".$story."</div>\n";
}
if(!empty($profile['achievements'])){
  $html .= "<br />\n";
  $html .= "<div class='profileheader' colspan='2'>"._("Achievements").":</div>\n";
  $achievements = utf8entities($profile['achievements']);
  $achievements = str_replace("\n",'<br/>',$achievements);
  $html .= "<div class='club_achievements'>".$achievements."</div>\n";
}
$urls = GetUrlList("club", $clubId);

if(count($urls)){
  $html .= "<div class='profileheader'>"._("Club pages").":</div>";
  
  $html .= UrlTable($urls, null, false);
}

$urls = GetMediaUrlList("club", $clubId);

if(count($urls)){
  $html .= "<div class='profileheader'>"._("Photos and Videos").":</div>";

  $html .= UrlTable($urls,
    ['type', 'url', 'mediaowner'], false);
}

$teams = ClubTeams($clubId, CurrentSeason());
if(mysqli_num_rows($teams)){
  $html .= "<h2>". utf8entities(U_(CurrentSeasonName())).":</h2>\n";
  $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
  $html .= "<tr><th>"._("Team")."</th><th>"._("Division")."</th><th colspan='3'></th></tr>\n";

  while($team = mysqli_fetch_assoc($teams)){
    $html .= "<tr>\n";
    $html .= "<td style='width:30%'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
    $html .=  "<td  style='width:30%'><a href='?view=poolstatus&amp;series=". $team['series_id'] ."'>".utf8entities(U_($team['seriesname']))."</a></td>";
    if(IsStatsDataAvailable()){
      $html .=  "<td class='right' style='width:15%'><a href='?view=playerlist&amp;team=".$team['team_id']."'>"._("Roster")."</a></td>";
      $html .=  "<td class='right' style='width:15%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Scoreboard")."</a></td>";
    }else{
      $html .=  "<td class='right' style='width:30%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Players")."</a></td>";
    }
    $html .=  "<td class='right' style='width:10%'><a href='?view=games&amp;team=".$team['team_id']."'>"._("Games")."</a></td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>\n";
}

$teams = ClubTeamsHistory($clubId);
if(mysqli_num_rows($teams)){
  $html .= "<h2>"._("History").":</h2>\n";
  $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
  $html .= "<tr><th>"._("Event")."</th><th>"._("Team")."</th><th>"._("Division")."</th><th colspan='3'></th></tr>\n";

  while($team = mysqli_fetch_assoc($teams)){
    $html .= "<tr>\n";
    $html .= "<td style='width:20%'>".utf8entities(U_(SeasonName($team['season'])))."</td>";
    $html .= "<td style='width:30%'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
    $html .=  "<td style='width:20%'><a href='?view=poolstatus&amp;series=". $team['series_id'] ."'>".utf8entities(U_($team['seriesname']))."</a></td>";

    if(IsStatsDataAvailable()){
      $html .=  "<td style='width:15%'><a href='?view=playerlist&amp;team=".$team['team_id']."'>"._("Roster")."</a></td>";
      $html .=  "<td style='width:15%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Scoreboard")."</a></td>";
    }else{
      $html .=  "<td style='width:30%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Players")."</a></td>";
    }
    $html .=  "<td style='width:10%'><a href='?view=games&amp;team=".$team['team_id']."'>"._("Games")."</a></td>";

    $html .= "</tr>\n";
  }
  $html .= "</table>\n";
}

if ($_SESSION['uid'] != 'anonymous') {
  $html .= "<div style='float:left;'><hr/><a href='?view=user/addmedialink&amp;club=$clubId'>"._("Add media")."</a></div>";
}

showPage($title, $html);

?>
