<?php
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/club.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/country.functions.php';
include_once $include_prefix . 'lib/url.functions.php';
include_once $include_prefix . 'lib/image.functions.php';

$title = _("Club information");

$max_file_size = 5 * 1024 * 1024; // 5 MB
$max_new_links = 3;
$html = "";
$teamId = 0;
$clubId = 0;

if (!empty($_GET["team"])) {
  $teamId = intval($_GET["team"]);
  $teaminfo = TeamInfo($teamId);
  $clubId = $teaminfo['club'];
}
if (!empty($_GET["club"])) {
  $clubId = intval($_GET["club"]);
}

ensureEditClubRight($teamId, $clubId, $title);

if (isset($_SERVER['HTTP_REFERER']))
  $backurl = utf8entities($_SERVER['HTTP_REFERER']);
elseif ($teamId > 0)
  $backurl = utf8entities("?view=user/teamplayers&team=$teamId");
elseif (isSuperAdmin())
  $backurl = utf8entities("?view=admin/clubs");
else
  $backurl = '';

if ($clubId <= 0) {
  $title = _("Club");
  $html .= "<p>" . _("This team has no club") . "</p>";

  showPage($title, $html);
  exit();
}

// club profile
$op = array("name" => "", "club_id" => $clubId, "valid" => 1, "founded" => "", "contacts" => "", "country" => "",
  "city" => "", "story" => "", "achievements" => "", "profile_image" => "");

if (isset($_POST['save'])) {
  $backurl = utf8entities($_POST['backurl']);
  if (!empty($_POST['name']))
    $op['name'] = $_POST['name'];
  else
    $op['name'] = ClubName($clubId);

  $op['founded'] = empty($_POST['founded'])?null:intval($_POST['founded']);
  $op['contacts'] = $_POST['contacts'];
  $op['country'] = $_POST['country'];
  $op['city'] = $_POST['city'];
  $op['story'] = $_POST['story'];
  $op['achievements'] = $_POST['achievements'];

  if (!empty($_POST['valid']))
    $op['valid'] = 1;
  else
    $op['valid'] = 0;
  SetClubProfile($teamId, $op);

  for ($i = 0; $i < $max_new_links; $i++) {

    if (!empty($_POST["url$i"])) {
      $name = "";
      if (!empty($_POST["urlname$i"])) {
        $name = $_POST["urlname$i"];
      }
      AddClubProfileUrl($teamId, $clubId, $_POST["urltype$i"], $_POST["url$i"], $name);
    }
  }

  if (is_uploaded_file($_FILES['picture']['tmp_name'])) {
    $html .= UploadClubImage($teamId, $clubId);
  }
} elseif (isset($_POST['remove'])) {
  RemoveClubProfileImage($teamId, $clubId);
} elseif (isset($_POST['removeurl_x'])) {
  $id = $_POST['hiddenDeleteId'];
  RemoveClubProfileUrl($teamId, $clubId, $id);
}

$club = ClubInfo($clubId);
if ($club) {
  $op['name'] = $club['name'];
  $op['profile_image'] = $club['profile_image'];
  $op['club_id'] = $club['club_id'];
  $op['founded'] = $club['founded'];
  $op['valid'] = $club['valid'];
  $op['country'] = $club['country'];
  $op['city'] = $club['city'];
  $op['contacts'] = $club['contacts'];
  $op['story'] = $club['story'];
  $op['achievements'] = $club['achievements'];
}

$title = _("Club information") . ": " . ($club['name']);
$html .= file_get_contents('script/disable_enter.js.inc');

$menutabs = [];
if ($teamId > 0) {
  $menutabs[_("Roster")] = "?view=user/teamplayers&team=$teamId";
  $menutabs[_("Team Profile")] = "?view=user/teamprofile&team=$teamId";
  $menutabs[_("Club Profile")] = "?view=user/clubprofile&team=$teamId";
}
$html .= pageMenu($menutabs, "", false);

$html .= "<form method='post' enctype='multipart/form-data' action='?view=user/clubprofile&amp;team=$teamId&amp;club=$clubId'>\n";
if (isSuperAdmin() || hasEditTeamsRight($teaminfo['series'] ?? 0)) {

  if (intval($club['valid']))
    $html .= "<p><input class='input' type='checkbox' id='valid' name='valid' checked='checked'/>";
  else
    $html .= "<p><input class='input' type='checkbox' id='valid' name='valid'/>";
  $html .= " " . _("Show on club list") . "</p>\n";
} elseif (intval($club['valid'])) {
  $html .= "<div><input type='hidden' id='valid' name='valid' value='" . utf8entities($club['valid']) . "'/></div>";
}
$html .= "<h1>" . utf8entities($club['name']) . "</h1>";

$html .= "<table style='width: 100%'>";

$html .= "<tr><td class='infocell'>" . _("Name") . ":</td>";
if (isSuperAdmin() || hasEditTeamsRight($teaminfo['series'] ?? 0)) {
  $html .= "<td><input class='input' maxlength='50' size='40' name='name' value='" . utf8entities($op['name']) .
    "'/></td></tr>\n";
} else {
  $html .= "<td><input class='input' maxlength='50' size='40' disabled='disabled' name='name' value='" .
    utf8entities($op['name']) . "'/></td></tr>\n";
}

$html .= "<tr><td class='infocell'>" . _("Country") . ":</td>";
$html .= "<td>" . CountryDropListWithValues("country", "country", $op['country']) . "</td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("City") . ":</td>";
$html .= "<td><input class='input' maxlength='100' size='40' name='city' value='" . utf8entities($op['city']) .
  "'/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Founded on year") . ":</td>";
$html .= "<td><input class='input' maxlength='4' size='5' name='founded' value='" . utf8entities($op['founded']) .
  "'/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Contacts") . ":</td>";
$html .= "<td><textarea class='input borderbox' rows='10' maxlength='5000' name='contacts'>" .
  utf8entities($op['contacts']) . "</textarea> </td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Description") . ":</td>";
$html .= "<td><textarea class='input borderbox' rows='10' maxlength='5000' name='story'>" . utf8entities($op['story']) .
  "</textarea> </td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Achievements") . ":</td>";
$html .= "<td><textarea class='input borderbox' rows='10' maxlength='5000' name='achievements'>" .
  utf8entities($op['achievements']) . "</textarea> </td></tr>\n";

$html .= "<tr><td class='infocell' colspan='2'>" . _("Web pages (homepage, blogs, images, videos)") . ":</td></tr>";
$html .= "<tr><td colspan='2'>";

$urls = GetUrlList("club", $clubId);

$html .= UrlTable($urls);

$html .= "<br />\n";

$html .= "<table border='0'>";

$html .= "<tr>";
$html .= "<td>" . _("Type") . "</td>";
$html .= "<td>" . _("URL") . "</td>";
$html .= "<td>" . _("Name") . " (" . _("optional") . ")</td>";
$html .= "</tr>";

$urltypes = GetUrlTypes();
for ($i = 0; $i < $max_new_links; $i++) {
  $html .= "<tr>";
  $html .= "<td><select class='dropdown' name='urltype$i'>\n";
  foreach ($urltypes as $type) {
    $html .= "<option value='" . utf8entities($type['type']) . "'>" . utf8entities($type['name']) . "</option>\n";
  }
  $html .= "</select></td>";
  $html .= "<td class='tbox'><input class='input' maxlength='500' name='url$i' value=''/></td>";
  $html .= "<td class='tbox'><input class='input' maxlength='500' name='urlname$i' value=''/></td>";
  $html .= "</tr>";
}

$html .= "</table>";
$html .= "</td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Current image") . ":</td>";
if (!empty($club['profile_image'])) {
  $html .= "<td><a href='" . UPLOAD_DIR . "clubs/$clubId/" . $club['profile_image'] . "'>";
  $html .= "<img src='" . UPLOAD_DIR . "clubs/$clubId/thumbs/" . $club['profile_image'] . "' alt='" . _("Profile image") .
    "'/></a></td>";
  $html .= "</tr>\n";
  $html .= "<tr><td class='infocell'></td>";
  $html .= "<td><input class='button' type='submit' name='remove' value='" . _("Delete image") . "' /></td></tr>\n";
} else {
  $html .= "<td>" . _("No image") . "</td>";
}

$html .= "<tr><td class='infocell'>" . _("New image") . ":</td>";
$html .= "<td><input class='input' type='file' size='50' name='picture'/></td></tr>\n";

$html .= "<tr><td colspan = '2' align='right'>";
if (isSuperAdmin() || (hasEditPlayersRight($teamId))) {

  $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>\n";
}
$html .= "<input class='button' type='button' name='takaisin'  value='" . _("Return") .
  "' onclick=\"window.location.href='$backurl'\"/>
	  <input type='hidden' name='backurl' value='$backurl'/>
	  <input type='hidden' name='MAX_FILE_SIZE' value='$max_file_size'/>
	  </td></tr>\n";
$html .= "</table>\n";
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";
$html .= "<p><a href='?view=clubcard&amp;club=" . $clubId . "'>" . _("Check Club card") . "</a></p>";

showPage($title, $html);

?>
