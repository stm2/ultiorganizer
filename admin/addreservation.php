<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';

$addmore = false;
$html = "";
$allfields = "";
$reservationId = 0;
$season = "";

if (isset($_GET['reservation'])) {
  $reservationId = $_GET['reservation'];
}
if (!empty($_GET['season'])) {
  $season = $_GET['season'];
}

//reservation parameters
$res = array(
	"id"=>$reservationId,
	"location"=>"",
	"fieldname"=>"",
	"reservationgroup"=>"",
	"date"=>"",
	"starttime"=>"",
	"endtime"=>"",
	"season"=>$season,
	"timeslots"=>"");

function time_fix($post, $key, $date) {
  if (isset($post[$key])) {
    $time = preg_replace("/^([0-9]+)[.]([0-9]+)$/", "$1:$2", $post[$key]);
    $time = preg_replace("/^[0-9]+$/", "$0:00", $post[$key]);
  } else {
    $time = "08:00";
  }
  return ToInternalTimeFormat("$date $time");
}

function check_input($post, $season, &$res) {
  $error = "";
  if (empty($post['date'])) {
    $error = "<p>" . _("Date required.") . "</p>";
  }

  $res['id'] = isset($post['id']) ? $post['id'] : 0;
  $res['location'] = isset($post['location'][0]) ? $post['location'][0] : 0;
  $res['fieldname'] = isset($post['fieldname']) ? $post['fieldname'] : "";
  $res['reservationgroup'] = isset($post['reservationgroup']) ? $post['reservationgroup'] : "";
  $res['date'] = isset($post['date']) ? $post['date'] : date('d.m.Y', time());
  $res['starttime'] = time_fix($post, 'starttime', $res['date']);
  $res['endtime'] = time_fix($post, 'endtime', $res['date']);
  $res['date'] = ToInternalTimeFormat($res['date']);
  $res['timeslots'] = isset($post['timeslots']) ? $post['timeslots'] : "";
  $res['season'] = isset($post['resseason']) ? $post['resseason'] : $season;

  if (empty($res['season'])) {
    $error .= "<p>" . _("Season required.") . "</p>";
  }
  if (empty($res['starttime']) || empty($res['endtime']) ||
    strtotime($res['endtime']) - strtotime($res['starttime']) < 60) {
    $error .= "<p>" . sprintf(_("Error: Duration must be at least %d minutes"), 1) . "</p>";
  }
  return $error;
}

if (isset($_POST['save']) || isset($_POST['add'])) {
  $error = check_input($_POST, $season, $res);
  if (empty($error)) {
    if ($res['id'] > 0) {
      SetReservation($res['id'], $res);
    } else {
      // check if adding more than 1 field
      $fields = array();
      $tmpfields = explode(",", $res['fieldname']);
      foreach ($tmpfields as $field) {
        $morefields = explode("-", $field);
        if (count($morefields) > 1) {
          for ($i = $morefields[0]; $i <= $morefields[1]; $i++) {
            $fields[] = $i;
          }
        } else {
          $fields[] = $morefields[0];
        }
      }
      if (count($fields) == 0) {
        $fields[] = $res['fieldname'];
      }
      $i = 0;
      $html .= "<p>" . _("Reservations added:") . "</p>";
      $html .= "<ul>";
      $locinfo = LocationInfo($res['location']);
      $loc_name = $locinfo != null ? $locinfo['name'] : '---';
      $allfields = $res['fieldname'];
      foreach ($fields as $field) {
        $res['fieldname'] = $field;
        $reservationId = AddReservation($res);
        $html .= "<li>" . $res['reservationgroup'] . ": " . DefWeekDateFormat($res['date']) . " ";
        if (!empty($res['timeslots'])) {
          $html .= $res['timeslots'] . " ";
        } else {
          $html .= DefHourFormat($res['starttime']) . "-" . DefHourFormat($res['endtime']) . " ";
        }
        $html .= $loc_name . " " . _("field") . " " . $field;
        $html .= "</li>";
      }
      $html .= "</ul><hr/>";
    }
  } else {
    $html .= "<p>" . _("No reservations added.") . "</p>\n" . $error . "<hr />\n";
  }
  $addmore = $res['id'] <= 0;
}

$title = _("Add field reservation");
include_once 'lib/yui.functions.php';

addHeaderCallback(
  function () {
    echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));
    
    echo getCalendarScript(['date']);
  });

if ($reservationId > 0) {
  $reservationInfo = ReservationInfo($reservationId);
  $res['id']=$reservationId;
  $res['location']=$reservationInfo['location'];
  $res['fieldname']=$reservationInfo['fieldname'];
  $res['reservationgroup']=$reservationInfo['reservationgroup'];
  $res['date']=ShortDate($reservationInfo['date']);
  $res['starttime']=DefHourFormat($reservationInfo['starttime']);
  $res['endtime']=DefHourFormat($reservationInfo['endtime']);
  $res['season']=$reservationInfo['season'];
  $res['timeslots']=$reservationInfo['timeslots'];
  if(!empty($allfields)){
     $res['fieldname']=$allfields;
  }
}

$html .= "<form method='post' action='?view=admin/addreservation&amp;season=".$season."&amp;reservation=".$res['id']."'>\n";
$html .= "<table class='formtable'>\n";

$html .= "<tr><td>"._("Date")." ("._("dd.mm.yyyy")."):</td><td>";

$value = utf8entities(ShortDate($res['date']));
$html .= getCalendarInput('date', $value);

// $html .= "<input type='text' class='input' name='date' id='date' value='$value'/>&nbsp;\n";
// $html .= "<button type='button' class='button' id='showcal1'>
// 		<img width='12' height='10' src='images/calendar.gif' alt='cal'/></button></td></tr>\n";
// $html .= "<tr><td></td><td><div id='calContainer1'></div>";
$html .= "</td></tr>\n";

$html .= "<tr><td>"._("Start time")." ("._("hh:mm")."):</td><td>";
$html .= "<input type='text' class='input' name='starttime' value='".utf8entities(DefHourFormat($res['starttime']))."'/>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td>"._("End time")." ("._("hh:mm")."):</td><td>";
$html .= "<input type='text' class='input' name='endtime' value='".utf8entities(DefHourFormat($res['endtime']))."'/>\n";
$html .= "</td></tr>\n";

/* Not yet supported
$html .= "<tr><td>"._("Timeslots")." ("._("hh:mm,hh:mm")."):</td><td>";
$html .= "<input type='text' class='input' size='32' maxlength='100' name='timeslots' value='".utf8entities($res['timeslots'])."'/>\n";
$html .= "</td></tr>\n";
*/

$html .= "<tr><td>"._("Grouping name").":</td>";
$html .= "<td>".TranslatedField("reservationgroup", $res['reservationgroup'])."</td></tr>\n";
$html .= "<tr><td>"._("Fields").":</td><td>";

$html .= TranslatedField("fieldname", $res['fieldname']);

if(!$addmore){
  $html .= "</td></tr><tr><td></td><td>". _("Enter separate field numbers (1,2,3) or multiple fields (1-30)");
}
$html .= "</td></tr>\n";

$location_info=null;
if($res['location']>0){
  $location_info = LocationInfo($res['location']);
  if ($location_info != null)
    $location_info = $location_info['name'];
}
$html .= LocationInput('location', 'location', $location_info, _("Location"), $res['location']); 

if(isSuperAdmin()){
  $html .= "<tr><td>"._("Season").":</td><td>";
  $html .= "<select class='dropdown' name='resseason'>\n";
  $html .= "<option class='dropdown' value=''></option>";
  $seasons = Seasons();

  while($row = mysqli_fetch_assoc($seasons)){
    if($res['season'] == $row['season_id'] || $season == $row['season_id']){
      $html .= "<option class='dropdown' selected='selected' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
    }else{
      $html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
    }
  }
  $html .= "</select>\n";
  $html .= "</td></tr>\n";
}

$html .= "<tr><td>";


if (!$addmore) {
  $html .= "<br /><input type='hidden' name='id' value='".utf8entities($res['id'])."'/>";
  $html .= "<input type='submit' class='button' name='save' value='"._("Save")."'/>";
} else {
  $html .= "<input type='submit' class='button' name='add' value='"._("Add")."'/>";
}
$html .= "<input class='button' type='button' name='back'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/reservations&amp;season=".$season."'\"/>";
$html .= "</td><td>&nbsp;</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>\n";
$html .= LocationScript('location');
$html .= TranslationScript("reservationgroup");
$html .= TranslationScript("fieldname");

showPage($title, $html);
?>
