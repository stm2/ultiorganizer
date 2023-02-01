<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/yui.functions.php';

$title = _("Select user roles");
$html = "";

//common page

addHeaderCallback(
  function () {
//     echo yuiLoad(array("calendar"));
    echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));
    
    echo getCalendarScript(['searchstart', 'searchend']);
  });


if (!empty($_GET['user'])) {
	$target = "view=user/userinfo&amp;user=".urlencode($_GET['user']);
} else {
	$target = "view=user/userinfo";
}
//content
$html .= "<h2>".$title."</h2>";
if ($_GET['userrole'] == 'superadmin') {
	$html .= "<h3>"._("Administrator")."</h3>\n";
	$html .= "<form method='post' action='?".$target."'>\n";
	$html .= "<p>";
	$html .= "<input type='hidden' name='userrole' value='superadmin'/>\n";
	$html .= "<input type='submit' name='selectuserrole' value='"._("Select")."'/>\n";
	$html .= "<input type='submit' name='cancel' value='"._("Cancel")."'/>\n";
	$html .= "</p>";
	$html .= "</form>\n";	
} elseif ($_GET['userrole'] == 'translationadmin') {
	$html .= "<h3>"._("Translation administrator")."</h3>\n";
	$html .= "<form method='post' action='?".$target."'>\n";
	$html .= "<p>";
	$html .= "<input type='hidden' name='userrole' value='translationadmin'/>\n";
	$html .= "<input type='submit' name='selectuserrole' value='"._("Select")."'/>\n";
	$html .= "<input type='submit' name='cancel' value='"._("cancel")."'/>\n";
	$html .= "</p>";
	$html .= "</form>\n";	
} elseif ($_GET['userrole'] == 'useradmin') {
	$html .= "<h3>"._("User administrator")."</h3>\n";
	$html .= "<form method='post' action='?".$target."'>\n";
	$html .= "<p>";
	$html .= "<input type='hidden' name='userrole' value='useradmin'/>\n";
	$html .= "<input type='submit' name='selectuserrole' value='"._("Select")."'/>\n";
	$html .= "<input type='submit' name='cancel' value='"._("cancel")."'/>\n";
	$html .= "</p>";
	$html .= "</form>\n";
} elseif ($_GET['userrole'] == 'teamadmin') {
	$html .= "<h3>"._("Team contact person")."</h3>";
	$html .= SearchTeam($target, array('userrole' => 'teamadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'accradmin') {
	$html .= "<h3>"._("Accreditation official")."</h3>";
	$html .= SearchTeam($target, array('userrole' => 'accradmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'seasonadmin') {
	$html .= "<h3>"._("Event responsible")."</h3>";
	$html .= SearchSeason($target, array('userrole' => 'seasonadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'seriesadmin') {
	$html .= "<h3>"._("Division organizer")."</h3>\n";
	$html .= SearchSeries($target, array('userrole' => 'seriesadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'resadmin') {
	$html .= "<h3>"._("Scheduling right")."</h3>\n";
	$html .= SearchReservation($target, array('userrole' => 'resadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'resgameadmin') {
	$html .= "<h3>"._("Reservation game input responsible")."</h3>\n";
	$html .= SearchReservation($target, array('userrole' => 'resgameadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'gameadmin') {
	$html .= "<h3>"._("Reservation game input responsible")."</h3>\n";
	$html .= SearchGame($target, array('userrole' => 'gameadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'playeradmin') {
	$html .= "<h3>"._("Player profile administrator")."</h3>\n";
	$html .= SearchPlayer($target, array('userrole' => 'playeradmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
}

showPage($title, $html);
?>
