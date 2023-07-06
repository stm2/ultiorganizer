<?php

include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix . 'lib/yui.functions.php';

function SearchSeason($resultTarget, $hiddenProperties, $submitbuttons) {
  $ret = "<form method='post' action='?".$resultTarget."'>\n";
  $ret .= "<table><tr><td>"._("Event").":</td><td>";
  $ret .= SeasonControl();
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "<p>";
  $ret .= getHiddenInput($hiddenProperties);
  $ret .= getSubmitButtons($submitbuttons);
  $ret .= "</p>";
  $ret .= "</form>";
  return $ret;
}

function SearchSeries($resultTarget, $hiddenProperties, $submitbuttons, $season = NULL) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "";
  if (empty($season)) {
    $ret = "<form method='post' action='?" . utf8entities($querystring) . "'>\n";
    $ret .= "<table><tr><td>" . _("Event") . ":</td><td>";
    $ret .= SeasonControl();
    $ret .= "</td></tr>\n";
    $ret .= "<tr><td>";
    $ret .= _("Division") . "</td><td>";
    $ret .= "<input type='text' name='seriesname' value='";
    if (isset($_POST['seriesname']))
      $ret .= $_POST['seriesname'];
    $ret .= "'/>\n";
    $ret .= "</td></tr>\n";
    $ret .= "<tr><td>";
    $ret .= "<input type='submit' name='searchser' value='" . _("Search") . "'/>";
    $ret .= "</td></tr>\n";
    $ret .= "</table>\n";
    $ret .= "</form>";
  }
  $ret .= "<form method='post' id='series' action='?" . $resultTarget . "'>\n";
  $ret .= "<p>";
  $results = SeriesResults($season);
  $ret .= $results;
  $ret .= getHiddenInput($hiddenProperties);
  
  $submit = true;
  if (empty($results)) {
    $submit = false;
    if (!empty($_POST['searchser'])) {
      $ret .= "<p>" . utf8entities(_("No results found")) . "</p>\n";
    } else {
      $ret .= "<br />";
    }
  }
  foreach ($submitbuttons as $name => $value) {
    if ($submit || $name == 'cancel')
      $ret .= getSubmitButtons([$name => $value]);
  }
  $ret .= "</p>";
  $ret .= "</form>";
  return $ret;
}

function SearchPool($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table><tr><td>"._("Event").":</td><td>";
  $ret .= SeasonControl();
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Division")."</td><td>";
  $ret .= "<input type='text' name='seriesname' value='";
  if (isset($_POST['seriesname'])) $ret .= $_POST['seriesname'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Pool")."</td><td>";
  $ret .= "<input type='text' name='poolname' value='";
  if (isset($_POST['poolname'])) $ret .= $_POST['poolname'];
  $ret .="'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchpool' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  $ret .= "<form method='post' id='pools' action='?".$resultTarget."'>\n";
  $results = PoolResults();
  if (empty($results)) {
    $ret .= "<p>" . _("No results") . "</p>";
  } else {
    $ret .= "<p>";
    $ret .= $results;
    $ret .= getHiddenInput($hiddenProperties);
    $ret .= getSubmitButtons($submitbuttons);
    $ret .= "</p>";
    $ret .= "</form>";
  }
  return $ret;
}

function SearchTeam($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table><tr><td>"._("Event").":</td><td>";
  $ret .= SeasonControl();
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Division")."</td><td>";
  $ret .= "<input type='text' name='seriesname' value='";
  if (isset($_POST['seriesname'])) $ret .= $_POST['seriesname'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Team")."</td><td>";
  $ret .= "<input type='text' name='teamname' value='";
  if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchteam' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  $ret .= "<form method='post' id='teams' action='?".$resultTarget."'>\n";
  $ret .= "<p>";
  $ret .= TeamResults();
  $ret .= getHiddenInput($hiddenProperties);
  $ret .= getSubmitButtons($submitbuttons);
  $ret .= "</p>";
  $ret .= "</form>";
  return $ret;
}

function SearchUser($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table><tr><td>"._("Event").":</td><td>";
  $ret .= "<input type='checkbox'";
  if (!empty($_POST['useseasons'])) {
    $ret .= " checked='checked'";
  }
  $ret .= " name='useseasons' value='true' />";
  $ret .= SeasonControl();
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Name")."</td><td>";
  $ret .= "<input type='text' name='username' value='";
  if (isset($_POST['username'])) $ret .= $_POST['username'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Team")."</td><td>";
  $ret .= "<input type='text' name='teamname' value='";
  if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Email")."</td><td>";
  $ret .= "<input type='text' name='email' value='";
  if (isset($_POST['email'])) $ret .= $_POST['email'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Unconfirmed").":</td><td>";
  $ret .= "<input type='checkbox'";
  if (!empty($_POST['registerrequest'])) {
    $ret .= " checked='checked'";
  }
  $ret .= " name='registerrequest' value='true' />";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchuser' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  $ret .= "<form method='post' id='users' action='?".$resultTarget."'>\n";
  $ret .= "<div>";
  $ret .= UserResults();
  if (!empty($_POST['registerrequest'])) {
    $ret .= getHiddenInput('registerrequest', 'registerrequest');
  }
  $ret .= getHiddenInput($hiddenProperties);
  $ret .= getSubmitButtons($submitbuttons);
  $ret .= "</div>";
  $ret .= "</form>";
  return $ret;
}

function SearchPlayer($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table><tr><td>"._("Event").":</td><td>";
  $ret .= "<input type='checkbox'";
  if (!empty($_POST['useseasons'])) {
    $ret .= " checked='checked'";
  }
  $ret .= " name='useseasons' value='true' />";
  $ret .= SeasonControl();
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Name")."</td><td>";
  $ret .= "<input type='text' name='username' value='";
  if (isset($_POST['username'])) $ret .= $_POST['username'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Team")."</td><td>";
  $ret .= "<input type='text' name='teamname' value='";
  if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= _("Email")."</td><td>";
  $ret .= "<input type='text' name='email' value='";
  if (isset($_POST['email'])) $ret .= $_POST['email'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchplayer' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  $ret .= "<form method='post' id='users' action='?".$resultTarget."'>\n";
  $ret .= "<div>";
  $ret .= PlayerResults();
  if (!empty($_POST['registerrequest'])) {
    $ret .= getHiddenInput('registerrequest', 'registerrequest');
  }
  $ret .= getHiddenInput($hiddenProperties);
  $ret .= getSubmitButtons($submitbuttons);
  $ret .= "</div>";
  $ret .= "</form>";
  return $ret;
}

function SearchReservation($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table style='width:100%'>";
  $ret .= "<tr><td>"._("Start time")." ("._("dd.mm.yyyy")."):</td><td>";
  
  $value = isset($_POST['searchstart']) ? $_POST['searchstart'] : date('d.m.Y');
  $ret .= getCalendarInput('searchstart', $value);
  
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("End time")." ("._("dd.mm.yyyy")."):</td><td>";
  
  $value = isset($_POST['searchend'])?$_POST['searchend']:'';
  
  $ret .= getCalendarInput('searchend', $value);
  
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Grouping name").":</td><td>";
  $ret .= "<input type='text' name='searchgroup' value='";
  if (isset($_POST['searchgroup'])) $ret .= $_POST['searchgroup'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Field").":</td><td>";
  $ret .= "<input type='text' name='searchfield' value='";
  if (isset($_POST['searchfield'])) $ret .= $_POST['searchfield'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Location").":</td><td>";
  $ret .= "<input type='text' name='searchlocation' value='";
  if (isset($_POST['searchlocation'])) $ret .= $_POST['searchlocation'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchreservation' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  
  $results = ReservationResults($_POST, isset($_GET['season'])?$_GET['season']:null);
  if (!empty($results)) {
    $ret .= "<form method='post' id='reservations' action='?" . $resultTarget . "'>\n";
    $ret .= $results;
    $ret .= "<p>";
    $ret .= getHiddenInput();
    $ret .= getHiddenInput($hiddenProperties);
    if (!empty($_POST['searchreservation']) || !empty($_GET['season'])) {
      $ret .= getSubmitButtons($submitbuttons);
    }
    $ret .= "</p>";
    $ret .= "</form>";
  } else if (!empty($_POST)) {
    $ret .= "<p>" . _("No reservations.") . "</p>";
  }
  
  return $ret;
}

function SearchGame($resultTarget, $hiddenProperties, $submitbuttons) {
  $querystring = $_SERVER['QUERY_STRING'];
  //leads to styles included on middle of page
  $ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
  $ret .= "<table>";
  $ret .= "<tr><td>"._("Start time")." ("._("dd.mm.yyyy")."):</td><td>";
  $value = isset($_POST['searchstart']) ? $_POST['searchstart'] : date('d.m.Y');
  $ret .= getCalendarInput('searchstart', $value);
  
  $ret .= "<tr><td>" . _("End time") . " (" . _("dd.mm.yyyy") . "):</td><td>";

  $value = isset($_POST['searchend']) ? $_POST['searchend'] : "";
  $ret .= getCalendarInput('searchend', $value);

  $ret .= "<tr><td>"._("Tournament").":</td><td>";
  $ret .= "<input type='text' name='searchgroup' value='";
  if (isset($_POST['searchgroup'])) $ret .= $_POST['searchgroup'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Field").":</td><td>";
  $ret .= "<input type='text' name='searchfield' value='";
  if (isset($_POST['searchfield'])) $ret .= $_POST['searchfield'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Location").":</td><td>";
  $ret .= "<input type='text' name='searchlocation' value='";
  if (isset($_POST['searchlocation'])) $ret .= $_POST['searchlocation'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>"._("Team")." ("._("separate with a comma")."):</td><td>";
  $ret .= "<input type='text' name='searchteams' value='";
  if (isset($_POST['searchteams'])) $ret .= $_POST['searchteams'];
  $ret .= "'/>\n";
  $ret .= "</td></tr>\n";
  $ret .= "<tr><td>";
  $ret .= "<input type='submit' name='searchgame' value='"._("Search")."'/>";
  $ret .= "</td></tr>\n";
  $ret .= "</table>\n";
  $ret .= "</form>";
  
  $ret .= "<form method='post' id='games' action='?".$resultTarget."'>\n";
  $ret .= GameResults();
  $ret .= "<p>";
  $ret .= getHiddenInput($hiddenProperties);
  $ret .= getSubmitButtons($submitbuttons);
  $ret .= "</p>";
  $ret .= "</form>";
  
  return $ret;
}

function SeasonControl() {
  if (!empty($_POST['searchseasons'])) {
    $selected = array_flip($_POST['searchseasons']);
  } elseif (!empty($GET['Season'])) {
    $selected = array($GET['Season'] => 'selected');
  } elseif(!empty($_SESSION['userproperties']['editseason'])) {
    $selected = $_SESSION['userproperties']['editseason'];
  }else{
    $selected = array();
  }

  $ret = "<select multiple='multiple' name='searchseasons[]' id='searchseasons' style='height:200px'>\n";
  
  $seasons = Seasons();
  while($season = mysqli_fetch_assoc($seasons)){
    $ret .= "<option value=\"".  utf8entities($season['season_id'])."\"";
    if (isset($selected[$season['season_id']])) {
      $ret .=  " selected='selected'";
    }
    $ret .= ">".utf8entities($season['name'])."</option>\n";
  }
  $ret .= "</select>\n";
  return $ret;
}

function SeriesResults($season = NULL) {
  if (empty($_POST['searchser']) && empty($season)) {
    return "";
  } else {
    if (!empty($season)) {
      $selected = array($season => 'selected');
    } else if (!empty($_POST['searchseasons'])) {
      $selected = array_flip($_POST['searchseasons']);
    } elseif (!empty($GET['Season'])) {
      $selected = array($GET['Season'] => 'selected');
    } else {
      $selected = $_SESSION['userproperties']['editseason'];
    }

    $result = SeasonSeriesMult($selected, $_POST['seriesname'] ?? null);

    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    if ($result->num_rows > 0) {
      $ret = "<table class='results'><tr><th>" . checkAllCheckbox('series') . "</th>";
      $ret .= "<th>" . _("Division") . "</th><th>" . _("Event") . "</th></tr>\n";
      while ($row = mysqli_fetch_assoc($result)) {
        $ret .= "<tr><td><input type='checkbox' name='series[]' value='" . utf8entities($row['series']) . "' /></td>";
        $ret .= "<td>" . utf8entities($row['series_name']) . "</td>";
        $ret .= "<td>" . utf8entities($row['season_name']) . "</td>";
        $ret .= "</tr>\n";
      }
      $ret .= "</table>\n";
      return $ret;
    } else
      return "";
  }
}

function PoolResults() {
  if (empty($_POST['searchpool'])) {
    return "";
  } else {
    $query = "SELECT seas.name as season_name, ser.name as series_name, pool.pool_id as pool, pool.name as pool_name ";
    $query .= "FROM uo_pool as pool left join uo_series as ser on (pool.series = ser.series_id) left join uo_season as seas on (ser.season = seas.season_id) ";
    $query .= "WHERE ser.season IN (";
    if (!empty($_POST['searchseasons'])) {
      $selected = array_flip($_POST['searchseasons']);
    } elseif (!empty($GET['Season'])) {
      $selected = array($GET['Season'] => 'selected');
    } else {
      $selected = $_SESSION['userproperties']['editseason'];
    }
    $terms = "";
    foreach ($selected as $seasonid => $value) {
      if (!empty($terms))
        $terms .= ",";
      $terms .= "'".mysql_adapt_real_escape_string($seasonid)."'";
    }
    $query .= $terms;
    $query .= ")";
    if (!empty($_POST['seriesname']) && strlen(trim($_POST['seriesname'])) > 0) {
      $query .= " AND ser.name like '%".mysql_adapt_real_escape_string(trim($_POST['seriesname']))."%'";
    } 
    if (!empty($_POST['poolname']) && strlen(trim($_POST['poolname'])) > 0) {
      $query .= " AND pool.name like '%".mysql_adapt_real_escape_string(trim($_POST['poolname']))."%'";
    } 

    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
    $ret = "";
    if (mysqli_num_rows($result) > 0) {
      $ret = "<table><tr><th>" . checkAllCheckbox('pools') . "</th>";
      $ret .= "<th>" . _("Pool") . "</th><th>" . _("Division") . "</th><th>" . _("Event") . "</th></tr>\n";
      while ($row = mysqli_fetch_assoc($result)) {
        $ret .= "<tr><td><input type='checkbox' name='pools[]' value='" . utf8entities($row['pool']) . "' /></td>";
        $ret .= "<td>" . utf8entities($row['pool_name']) . "</td>";
        $ret .= "<td>" . utf8entities($row['series_name']) . "</td>";
        $ret .= "<td>" . utf8entities($row['season_name']) . "</td>";
        $ret .= "</tr>\n";
      }
      $ret .= "</table>\n";
    }
    return $ret;
  }
}

function TeamResults() {
  if (empty($_POST['searchteam'])) {
    return "";
  } else {
    $query = "SELECT seas.name as season_name, ser.name as series_name, team.team_id as team, team.name as team_name ";
    $query .= "FROM uo_team as team left join uo_series as ser on (team.series = ser.series_id) left join uo_season as seas on (ser.season = seas.season_id) ";
    $query .= "WHERE ser.season IN (";
    if (!empty($_POST['searchseasons'])) {
      $selected = array_flip($_POST['searchseasons']);
    } elseif (!empty($GET['Season'])) {
      $selected = array($GET['Season'] => 'selected');
    } else {
      $selected = $_SESSION['userproperties']['editseason'];
    }
    foreach ($selected as $seasonid => $value) {
      $query .= "'".mysql_adapt_real_escape_string($seasonid)."', ";
    }
    $query = substr($query, 0, strlen($query) - 2);
    $query .= ")";
    if (!empty($_POST['seriesname']) && strlen(trim($_POST['seriesname'])) > 0) {
      $query .= " AND ser.name like '%".mysql_adapt_real_escape_string(trim($_POST['seriesname']))."%'";
    } 
    if (!empty($_POST['teamname']) && strlen(trim($_POST['teamname'])) > 0) {
      $query .= " AND team.name like '%".mysql_adapt_real_escape_string(trim($_POST['teamname']))."%'";
    } 

    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
    $ret = "<table><tr><th>" . checkAllCheckbox('teams') . "</th>";
    $ret .= "<th>"._("Team")."</th><th>"._("Division")."</th><th>"._("Event")."</th></tr>\n";
    while ($row = mysqli_fetch_assoc($result)) {
      $ret .= "<tr><td><input type='checkbox' name='teams[]' value='".utf8entities($row['team'])."' /></td>";
      $ret .= "<td>".utf8entities($row['team_name'])."</td>";
      $ret .= "<td>".utf8entities($row['series_name'])."</td>";
      $ret .= "<td>".utf8entities($row['season_name'])."</td>";
      $ret .= "</tr>\n";
    }
    $ret .= "</table>\n";
    return $ret;
  }
}

function UserResults() {
  if (empty($_POST['searchuser'])) {
    return "";
  } else {
    if (!empty($_POST['registerrequest'])) {
      $query = "SELECT name as user_name, userid, last_login, email FROM uo_registerrequest ";
    }else{
      $query = "SELECT name as user_name, userid, last_login, email FROM uo_users ";
    }
    
    $selected = array();
    if (!empty($_POST['searchseasons'])) {
      $selected = array_flip($_POST['searchseasons']);
    } elseif (!empty($GET['Season'])) {
      $selected = array($GET['Season'] => 'selected');
    } else if (isset($_SESSION['userproperties']['editseason'])){
      $selected = $_SESSION['userproperties']['editseason'];
    }
    $criteria = "";
    if (!empty($_POST['useseasons']) && !empty($selected)) {
      $criteria = "(userid in (select userid from uo_userproperties where name='editseason' and value in (";
      foreach ($selected as $seasonid => $prop) {
        $criteria .= "'".mysql_adapt_real_escape_string($seasonid)."', ";
      }
      $criteria = substr($criteria, 0, strlen($criteria) - 2);
      $criteria .= ")))";
    }
    
    if (!empty($_POST['teamname'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " and ";
      }
      $criteria .= "(userid in (select userid from uo_userproperties where name='userrole' ";
      $criteria .= "and value like 'teamadmin:%' and substring_index(value, ':', -1) in ";
      $criteria .= "(select team_id from uo_team where series in ";
      
      $seasonclause = "";
      if (!empty($selected)) {
        $seasonclause = " season in (";
        foreach ($selected as $seasonid => $value) {
          $seasonclause .= "'" . mysql_adapt_real_escape_string($seasonid) . "', ";
        }
      }
      $criteria .= "(select series_id from uo_series $seasonclause where ";
      
      $criteria = substr($criteria, 0, strlen($criteria) - 2);
      $criteria .= ")) and name like '%".mysql_adapt_real_escape_string($_POST['teamname'])."%')))";
    }
    if (!empty($_POST['username'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " and ";
      }
      $criteria .= "(name like '%".mysql_adapt_real_escape_string($_POST['username'])."%')";
    }
    
    if (!empty($_POST['email'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " and ";
      }
      $criteria .= "(email like '%".mysql_adapt_real_escape_string($_POST['email'])."%')";
    }
    
    if (strlen($criteria) > 0) {
      $query .= " WHERE ".$criteria;  
    }
    $query .= " ORDER BY userid, name";

    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
    
    $ret = "<table style='white-space: nowrap;'><tr><th>" . checkAllCheckbox('users') . "</th>";
    $ret .= "<th>"._("Name")."</th><th>"._("Username")."</th><th>"._("Email")."</th><th>"._("Rights")."</th><th>"._("Last login")."</th></tr>\n";
    while ($row = mysqli_fetch_assoc($result)) {
      $ret .= "<tr><td style='vertical-align:text-top;'>";
      if ($row['userid'] != 'anonymous') {
        $ret .= "<input type='checkbox' name='users[]' value='".utf8entities($row['userid'])."'/>";
      } else {
        $ret .= "&nbsp;";
      }
      $ret .= "</td>";
      $ret .= "<td style='vertical-align:text-top;'><a href='?view=user/userinfo&amp;user=".utf8entities($row['userid'])."'>".utf8entities($row['user_name'])."</a></td>";
      $ret .= "<td style='vertical-align:text-top;'>".utf8entities($row['userid'])."</td>";
      $ret .= "<td style='vertical-align:text-top;'>".utf8entities($row['email'])."</td>";
      
      $ret .= "<td style='vertical-align:text-top;'>".UserListRightsHtml($row['userid'])."</td>";
      
      $ret .= "<td style='vertical-align:text-top;'>".LongTimeFormat($row['last_login'])."</td>";      
      $ret .= "</tr>\n";
    }
    $ret .= "</table>\n";
    return $ret;
  }
}

function PlayerResults() {
  if (empty($_POST['searchplayer'])) {
    return "";
  } else {
    $query = "SELECT MAX(player_id) as player_id, pp.profile_id, CONCAT(pp.firstname, ' ', pp.lastname) as user_name, 
                pp.accreditation_id, GROUP_CONCAT(DISTINCT email SEPARATOR ', ') as email,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.team_id DESC SEPARATOR ', ') as teamname
              FROM uo_player p 
              JOIN uo_player_profile pp ON (pp.profile_id = p.profile_id)
              JOIN uo_team t ON (p.team = t.team_id)";
    
    if (!empty($_POST['searchseasons'])) {
      $selected = array_flip($_POST['searchseasons']);
    } elseif (!empty($GET['Season'])) {
      $selected = array($GET['Season'] => 'selected');
    } else {
      $selected = $_SESSION['userproperties']['editseason'];
    }
    $criteria = "";
    if (!empty($_POST['useseasons'])) {
      $criteria = "(team in ";
      $criteria .= "(SELECT team_id FROM uo_team WHERE series in ";
      $criteria .= "(SELECT series_id FROM uo_series WHERE season in (";
      foreach ($selected as $seasonid => $prop) {
        $criteria .= "'" . mysql_adapt_real_escape_string($seasonid) . "', ";
      }
      $criteria = substr($criteria, 0, strlen($criteria) - 2);
      $criteria .= "))))";
    }
    
    if (!empty($_POST['teamname'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " AND ";
      }
      $criteria .= "(team in ";
      $criteria .= "(SELECT team_id FROM uo_team WHERE series in ";
      $criteria .= "(SELECT series_id FROM uo_series WHERE season in ("; 
      foreach ($selected as $seasonid => $value) {
        $criteria .= "'".mysql_adapt_real_escape_string($seasonid)."', ";
      }
      $criteria = substr($criteria, 0, strlen($criteria) - 2);
      $criteria .= ")) AND name LIKE '%".mysql_adapt_real_escape_string($_POST['teamname'])."%'))";
    }
    if (!empty($_POST['username'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " AND ";
      }
      $criteria .= "(p.firstname LIKE '%".mysql_adapt_real_escape_string($_POST['username'])."%'";
      $criteria .= " OR p.lastname LIKE '%".mysql_adapt_real_escape_string($_POST['username'])."%'";
      $criteria .= " OR CONCAT(p.firstname, ' ', p.lastname) LIKE '%".mysql_adapt_real_escape_string($_POST['username'])."%')";
    }
    
    if (!empty($_POST['email'])) {
      if (strlen($criteria) > 0) {
        $criteria .= " AND ";
      }
      $criteria .= "(pp.email like '%".mysql_adapt_real_escape_string($_POST['email'])."%')";
    }
    
    if (strlen($criteria) > 0) {
      $query .= " WHERE ".$criteria;  
    }
    $query .= " GROUP BY profile_id, accreditation_id";
    $query .= " ORDER BY CONCAT(pp.firstname,pp.lastname), teamname";
    $result = mysql_adapt_query($query);
    if (!$result) { die("Invalid query: " . mysql_adapt_error()); }
    
    
    $ret = "<table class='infotable widetable'><tr><th>" . checkAllCheckbox('players') . "</th>";
    $ret .= "<th>"._("Name")."</th><th>"._("Team")."</th><th>"._("Email")."</th></tr>\n";
    while ($row = mysqli_fetch_assoc($result)) {
      $ret .= "<tr><td>";
      $ret .= "<input type='checkbox' name='players[]' value='".utf8entities($row['profile_id'])."'/>";
      $ret .= "</td>";
      $ret .= "<td><a href='?view=playercard&amp;player=".utf8entities($row['player_id'])."'>".utf8entities($row['user_name'])."</a></td>";
      $ret .= "<td>".utf8entities($row['teamname'])."</td>";
      $ret .= "<td>".utf8entities($row['email'])."</td>";
      $ret .= "</tr>\n";
    }
    $ret .= "</table>\n";
    return $ret;
  }
}

function ReservationResults($post, $season = null) {
  if (empty($post['searchreservation']) && empty($season)) {
    return "";
  } else {
    $query = "SELECT res.id as reservation_id, res.season, res.location, res.fieldname, res.reservationgroup, res.starttime, res.endtime, loc.name, loc.fields, loc.indoor, loc.address, count(game_id) as games ";
    $query .= "FROM uo_reservation res left join uo_location as loc on (res.location = loc.id) left join uo_game as game on (res.id = game.reservation) ";

    $start = "";
    if (isset($post['searchstart'])) {
      $start = $post['searchstart'];
    }
    
    //else {
    //  $start = date('d.m.Y');
    //}
    if (isset($post['searchend'])) {
      $end = $post['searchend'];
    } 
    
    $query .= "WHERE 1";
    
    if(!empty($start)){
      $query .= " AND res.starttime >= '".ToInternalTimeFormat($start." 00:00")."'";
    }
    if(!empty($end)){
      $query .= " AND res.endtime <= '".ToInternalTimeFormat($end." 23:59")."'";
    }
    if (isset($post['searchgroup']) && strlen($post['searchgroup']) > 0) {
      $query .= " AND res.reservationgroup like '%".mysql_adapt_real_escape_string($post['searchgroup'])."%'";
    }
    if (isset($post['searchfield']) && strlen($post['searchfield']) > 0) {
      $query .= " AND res.fieldname like '".mysql_adapt_real_escape_string($post['searchfield'])."'";
    }
    if (isset($post['searchlocation']) && strlen($post['searchlocation']) > 0) {
      $query .= " AND (loc.name like '%".mysql_adapt_real_escape_string($post['searchlocation'])."%' OR ";
      $query .= "loc.address like '%".mysql_adapt_real_escape_string($post['searchlocation'])."%')";
    }
    
    if (!empty($season)) {
      $query .= " AND res.season='".mysql_adapt_real_escape_string($season)."'";
    }
    $query .= " GROUP BY res.starttime, res.id, res.location, res.fieldname, res.reservationgroup, res.endtime, loc.name, loc.fields, loc.indoor, loc.address";

    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
    
    if (mysqli_num_rows($result) > 0) {
    $ret = "<table class='admintable'><tr><th>" . checkAllCheckbox('reservations') . "</th>";
    $ret .= "<th>"._("Reservation Group")."</th><th>"._("Location")."</th><th>"._("Date")."</th>";
    $ret .= "<th>"._("Starts")."</th><th>"._("Ends")."</th><th>"._("Games")."</th>";
    $ret .= "<th>"._("Scoresheets")."</th><th></th></tr>\n";
    while ($row = mysqli_fetch_assoc($result)) {
      $ret .= "<tr class='admintablerow'><td><input type='checkbox' name='reservations[]' value='".utf8entities($row['reservation_id'])."'/></td>";
      $ret .= "<td>".utf8entities(U_($row['reservationgroup']))."</td>";
      $ret .= "<td><a href='?view=admin/addreservation&amp;reservation=".$row['reservation_id']."&amp;season=".$row['season']."'>".utf8entities(U_($row['name']))." "._("Field")." ".utf8entities(U_($row['fieldname']))."</a></td>";
      $ret .= "<td>".DefWeekDateFormat($row['starttime'])."</td>";
      $ret .= "<td>".DefHourFormat($row['starttime'])."</td>";
      $ret .= "<td>".DefHourFormat($row['endtime'])."</td>";
      $ret .= "<td class='center'>".$row['games']."</td>";
      $ret .= "<td class='center'><a href='?view=user/pdfscoresheet&amp;reservation=".$row['reservation_id']."'>"._("PDF")."</a></td>";
      if(intval($row['games'])==0){
        $ret .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' name='remove' alt='"._("X")."' onclick=\"setId(".$row['reservation_id'].");\"/></td>";
      }
      
      $ret .= "</tr>\n";
    }
    $ret .= "</table>\n";
    } else {
      return "";
    }
    return $ret;
    
  }
}

function GameResults() {
  
  if (empty($_POST['searchgame'])) {
    return "";
  } else {
    $query = "SELECT game_id, hometeam, kj.name as hometeamname, visitorteam, vj.name as visitorteamname, pp.pool as pool,
      time, homescore, visitorscore, pool.timecap, pool.timeslot, pool.series,
      CONCAT(loc.name, ' "._("Field")." ', res.fieldname) AS locationname,
      res.reservationgroup,phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
    FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
      left join uo_pool pool on (pp.pool=pool.pool_id)
      left join uo_team kj on (pp.hometeam=kj.team_id)
      left join uo_team vj on (pp.visitorteam=vj.team_id)
      LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
      LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
      left join uo_location loc on (res.location=loc.id)";

    if (isset($_POST['searchstart'])) {
      $start = $_POST['searchstart'];
    } else {
      $start = date('d.m.Y');
    }
    
    if (isset($_POST['searchend'])) {
      $end = $_POST['searchend'];
    } 
    
    if(empty($end)){
      $query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."'";
    }else{
      $query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."' AND ";
      $query .= "res.endtime<'".ToInternalTimeFormat($end." 23:59")."' ";
    }
    
    if (isset($_POST['searchgroup']) && strlen($_POST['searchgroup']) > 0) {
      $query .= "AND res.reservationgroup like '%".mysql_adapt_real_escape_string($_POST['searchgroup'])."%' ";
    }
    if (isset($_POST['searchfield']) && strlen($_POST['searchfield']) > 0) {
      $query .= "AND res.fieldname like '".mysql_adapt_real_escape_string($_POST['searchfield'])."' ";
    }
    if (isset($_POST['searchlocation']) && strlen($_POST['searchlocation']) > 0) {
      $query .= "AND (loc.name like '%".mysql_adapt_real_escape_string($_POST['searchlocation'])."%' OR ";
      $query .= "loc.address like '%".mysql_adapt_real_escape_string($_POST['searchlocation'])."%') ";
    }
    if (isset($_POST['searchteams']) && strlen($_POST['searchteams'])) {
      foreach (explode(',',$_POST['searchteams']) as $team) {
        $query .= "AND (vj.name LIKE '%".mysql_adapt_real_escape_string($team)."%' OR kj.name LIKE '%".mysql_adapt_real_escape_string($team)."%') ";
      }
    }
    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
    
    $ret = "<table><tr><th>" . checkAllCheckbox('games') . "</th>";
    $ret .= "<th>"._("Game")."</th><th>"._("Reservation Group")."</th><th>"._("Location")."</th></tr>\n";
    while ($row = mysqli_fetch_assoc($result)) {
      $ret .= "<tr><td><input type='checkbox' name='games[]' value='".utf8entities($row['game_id'])."'/></td>";
      $ret .= "<td>".utf8entities(GameName($row))."</td>";
      $ret .= "<td>".utf8entities($row['reservationgroup'])."</td>";
      $ret .= "<td>".utf8entities($row['locationname'])."</td>";
      $ret .= "</tr>\n";
    }
    $ret .= "</table>\n";
    return $ret;
  }
}
?>
