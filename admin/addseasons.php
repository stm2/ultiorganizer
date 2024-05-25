<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/common.functions.php';

$seasonId = "";
$html = "";
    
//season parameters
$sp = array(
  "season_id"=>"",
  "name"=>"",
  "type"=>"",
  "starttime"=>"",
  "istournament"=>0,
  "isinternational"=>0,
  "organizer"=>"",
  "category"=>"",
  "isnationalteams"=>0,
  "endtime"=>"",
  "spiritmode"=>0,
  "showspiritpoints"=>0,
  "iscurrent"=>0,
  "enrollopen"=>0,
  "enroll_deadline"=>"",
  "timezone"=>GetDefTimeZone()
  );

if (!empty($_GET["season"]))
  $seasonId = $_GET["season"];

$backurl = utf8entities($_SERVER['HTTP_REFERER']??"");
if (empty($backurl))
  if (empty($seasonId))
    $backurl = "?view=admin/seasons";
  else 
    $backurl = "?view=admin/seasonadmin&amp;season=" . $seasonId;

// process itself on submit
if (!empty($_POST['add'])) {
  $backurl = utf8entities($_POST['backurl'] ?? '');
  $sp['season_id'] = $_POST['added_id'];
  $sp['name'] = $_POST['seasonname'];
  $sp['type'] = $_POST['type'];
  $sp['istournament'] = !empty($_POST['istournament']);
  $sp['isinternational'] = !empty($_POST['isinternational']);
  $sp['organizer'] = $_POST['organizer'];
  $sp['category'] = $_POST['category'];
  $sp['isnationalteams'] = !empty($_POST['isnationalteams']);
  $sp['timezone'] = $_POST['timezone'];
  $sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
  $sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
  $sp['enrollopen'] = !empty($_POST['enrollopen']);
  $sp['enroll_deadline'] = isset($_POST['enrollendtime']) ? ToInternalTimeFormat($_POST['enrollendtime']) : ToInternalTimeFormat($_POST['seasonstarttime']);
  $sp['iscurrent'] = !empty($_POST['iscurrent']);
  $sp['spiritmode'] = $_POST['spiritmode'];
  $sp['showspiritpoints'] = !empty($_POST['showspiritpoints']);
  $comment=$_POST['comment'];

  if(empty($_POST['added_id'])){
    $html .= "<p class='warning'>"._("Event id can not be empty").".</p>";
  }else if(preg_match('/[ \'"]/', $_POST['added_id']) || !preg_match('/[\w_~$@-]{1,10}/iu', $_POST['added_id'])){
    $html .= "<p class='warning'>"._("Event id may not contain spaces or special characters and may only be 10 characters long").".</p>";
  }else if(empty($_POST['seasonname'])){
    $html .= "<p class='warning'>"._("Name can not be empty").".</p>";
  }else if(empty($_POST['type'])){
    $html .= "<p class='warning'>"._("Type can not be empty").".</p>";
  }else{
    AddSeason($sp['season_id'], $sp, $comment);
    $seasonId = $sp['season_id'];

    // add rights for season creator
    AddEditSeason($_SESSION['uid'], $sp['season_id']);
    AddUserRole($_SESSION['uid'], 'seasonadmin:'.$sp['season_id']);
    
    if($sp['istournament']){
      $_SESSION['title'] = _("New tournament added") .":";
    }else{
      $_SESSION['title'] = _("New season added") .":";
    }
    
    session_write_close();
    header("location:?view=admin/seasonadmin&season=". urlencode($seasonId));
  }
}else if(!empty($_POST['save'])){
  $backurl = utf8entities($_POST['backurl']??'');
  if(empty($_POST['seasonname'])){
    $html .= "<p class='warning'>"._("Name can not be empty").".</p>";
  }else{
    $sp['season_id'] = $seasonId;
    $sp['name'] = $_POST['seasonname'];
    $sp['type'] = $_POST['type'];
    $sp['istournament'] = !empty($_POST['istournament']);
    $sp['isinternational'] = !empty($_POST['isinternational']);
    $sp['isnationalteams'] = !empty($_POST['isnationalteams']);
    $sp['organizer'] = $_POST['organizer'];
    $sp['category'] = $_POST['category'];
    $sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
    $sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
    $sp['enrollopen'] = !empty($_POST['enrollopen']);
    $sp['enroll_deadline'] = ToInternalTimeFormat($_POST['enrollendtime']);
    $sp['iscurrent'] = !empty($_POST['iscurrent']);
    $sp['spiritmode'] = $_POST['spiritmode'];
    $sp['showspiritpoints'] = !empty($_POST['showspiritpoints']);
    $sp['timezone'] = $_POST['timezone'];
    $comment=$_POST['comment'];
    SetSeason($sp['season_id'], $sp, $comment);
  }
}

$title = _("Edit event");
if (strlen($sp['name']) > 0) {
  $title .= ": ".$sp['name'];
}

if($seasonId){
  $info = SeasonInfo($seasonId);
  
  $sp['season_id'] = $info['season_id'];
  $sp['name'] = $info['name'];
  $sp['type'] = $info['type'];
  $sp['starttime'] = $info['starttime'];
  $sp['endtime'] = $info['endtime'];
  $sp['iscurrent'] = $info['iscurrent'];
  $sp['enrollopen']  = $info['enrollopen'];
  $sp['enroll_deadline'] = $info['enroll_deadline'];
  $sp['istournament'] = $info['istournament'];
  $sp['isinternational'] = $info['isinternational'];
  $sp['organizer'] = $info['organizer'];
  $sp['category'] = $info['category'];
  $sp['isnationalteams'] = $info['isnationalteams'];
  $sp['spiritmode'] = $info['spiritmode'];
  $sp['showspiritpoints'] = $info['showspiritpoints'];
  $sp['timezone'] = $info['timezone'];
  $comment = CommentRaw(1, $info['season_id']);
} else {
  $comment = "";
}

//common page
include_once $include_prefix . 'lib/yui.functions.php';

addHeaderCallback(
  function () {
    echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));

    echo getCalendarScript(['seasonstarttime', 'seasonendtime', 'enrollendtime']);
  });


if(empty($seasonId)){
  $sValue = utf8entities($_POST['added_id'] ?? "");
  ensureSuperAdmin($title);
  $html .= "<h2>"._("Add new season/tournament")."</h2>\n";
  $html .= "<form method='post' action='?view=admin/addseasons'>";
  $disabled="";
}else{
  ensureSeasonAdmin($seasonId, $title);
  $sValue = utf8entities($seasonId);
  $html .= "<h2>"._("Edit season/tournament")."</h2>\n";
  $html .= "<form method='post' action='?view=admin/addseasons&amp;season=" . urlencode($seasonId) . "'>";
  $disabled="disabled='disabled'";
}

$html .= "<table class='formtable'>";
$html .= "<tr><td class='infocell'>"._("Event id").": </td>\n";
$html .= "<td><input class='input' size='30' name='added_id' $disabled value='$sValue'></input></td></tr>\n";
$html .= "<tr><td class='infocell'>"._("Name").": </td>
      <td>".TranslatedField2("seasonname", $sp['name'], '')."</td>
    </tr>\n";
$html .= "<tr><td class='infocell'>"._("Type").": </td><td><select class='dropdown' name='type'>\n";

$types = SeasonTypes();

foreach ($types as $type) {
  if ($sp['type'] == $type)
    $html .= "<option class='dropdown' selected='selected' value='$type'>" . U_($type) . "</option>\n";
  else
    $html .= "<option class='dropdown' value='$type'>" . U_($type) . "</option>\n";
}

$html .= "</select></td></tr>\n";

$html .= "<tr><td class='infocell'>"._("Tournament").": </td><td><input class='input' type='checkbox' name='istournament' ";
if ($sp['istournament']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("International").": </td><td><input class='input' type='checkbox' name='isinternational' ";
if ($sp['isinternational']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("For national teams").": </td><td><input class='input' type='checkbox' name='isnationalteams' ";
if ($sp['isnationalteams']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("Spirit mode").": </td><td>";
$spiritmodes = SpiritModes();
$html .= "<select class='dropdown' id='spiritmode' name='spiritmode'>\n";
$html .= "<option value='0'></option>\n";
foreach($spiritmodes as $mode) {
  $selected =  ($sp['spiritmode']==$mode['mode'])?" selected='selected'":"";
  $html .= "<option $selected value='". utf8entities($mode['mode']) . "'>".utf8entities(_($mode['name'])) . "</option>\n";
}
$html .= "</select>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td class='infocell'>"._("Spirit points visible").": </td><td><input class='input' type='checkbox' name='showspiritpoints' ";
if ($sp['showspiritpoints']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("Organizer").": </td><td><input class='input' size='50' maxlength='50' name='organizer' value='".utf8entities($sp['organizer'])."'/></td></tr>";
$html .= "<tr><td class='infocell'>"._("Category").": </td><td><input class='input' size='50' maxlength='50' name='category' value='".utf8entities($sp['category'])."'/></td></tr>";

$html .= "<tr><td class='infocell'>".utf8entities(_("Comment (you can use <b>, <em>, and <br /> tags)")).":</td>
    <td><textarea class='input' rows='10' cols='70' name='comment'>".utf8entities($comment)."</textarea></td></tr>";

$html .= "<tr><td class='infocell'>"._("Timezone").": </td><td>";
$dateTimeZone = GetTimeZoneArray();
$html .= "<select class='dropdown' id='timezone' name='timezone'>\n";
$html .= "<option value=''></option>\n";
foreach($dateTimeZone as $tz){
  if($sp['timezone']==$tz){
    $html .= "<option selected='selected' value='$tz'>".utf8entities($tz)."</option>\n";
  }else{
    $html .= "<option value='$tz'>".utf8entities($tz)."</option>\n";
  }
}
$html .= "</select>\n";
//$dateTime = new DateTime("now", new DateTimeZone($sp['timezone']));
//$html .= DefTimeFormat($dateTime->format("Y-m-d H:i:s"));
$html .= "</td></tr>";

$html .= "<tr><td class='infocell'>"._("Starts")." ("._("dd.mm.yyyy")."): </td><td>". 
  getCalendarInput('seasonstarttime', ShortDate($sp['starttime'])) . "</td></tr>";
$html .= "<tr><td class='infocell'>"._("Ends")." ("._("dd.mm.yyyy")."): </td><td>" .
  getCalendarInput('seasonendtime', ShortDate($sp['endtime'])).  "</td></tr>";
$html .= "<tr><td class='infocell'>"._("Open for enrollment").": </td><td><input class='input' type='checkbox' name='enrollopen' ";
if ($sp['enrollopen']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";
$html .= "<tr><td class='infocell'>"._("Enrolling ends")."<br/>("._("only informational")."): </td>";
$html .= "<td>" . getCalendarInput('enrollendtime', ShortDate($sp['enroll_deadline'])) . "</td></tr>";

$html .= "<tr><td class='infocell'>"._("Shown in main menu").": </td><td><input class='input' type='checkbox' name='iscurrent' ";
if ($sp['iscurrent']) {
  $html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "</table>\n";
if(empty($seasonId)){
  $html .= "<p><input class='button' type='submit' name='add' value='"._("Add")."' />";
}else{
  $html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."' />";
}
if ($backurl) {
  $html .= "<input type='hidden' name='backurl' value='$backurl'/>";
  $html .= "<input class='button' type='button' value='"._("Return")."' onclick=\"window.location.href='$backurl'\" /></p>";
}
$html .= "</form>\n";

$html .= TranslationScript("seasonname");

showPage($title, $html);
?>
