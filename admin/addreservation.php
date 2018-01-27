<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';

$LAYOUT_ID = ADDRESERVATION;
$addmore = false;
$html = "";
$allfields = "";
$reservationId = 0;
$season="";

if (isset($_GET['reservation'])) {
  $reservationId = $_GET['reservation'];
}
if(!empty($_GET['season'])) {
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
    $res['starttime'] = isset($post['starttime']) ? ToInternalTimeFormat($res['date'] . " " . $post['starttime']) : ToInternalTimeFormat(
        "00:00");
    $res['endtime'] = isset($post['endtime']) ? ToInternalTimeFormat($res['date'] . " " . $post['endtime']) : ToInternalTimeFormat(
        "00:00");
    $res['date'] = ToInternalTimeFormat($res['date']);
    $res['timeslots'] = isset($post['timeslots']) ? $post['timeslots'] : "";
    $res['season'] = isset($post['resseason']) ? $post['resseason'] : $season;
    
    if (empty($res['season'])) {
      $error .= "<p>" . _("Season required.") . "</p>";
    }
    if (empty ($res['starttime']) || empty ($res['endtime']) || strtotime($res['endtime']) - strtotime($res['starttime']) < 60) {
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
      $fields = array ();
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
        $html .= $locinfo['name'] . " " . _("field") . " " . $field;
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
//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

?>
<link
	rel="stylesheet" type="text/css"
	href="script/yui/calendar/calendar.css" />

<script type="text/javascript">
<!--

YAHOO.namespace("calendar");

YAHOO.calendar.init = function() {
	
	YAHOO.calendar.cal1 = new YAHOO.widget.Calendar("cal1","calContainer1");
	YAHOO.calendar.cal1.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal1.render();

	function handleCal1Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer1"); 
		
		if(containerDiv.style.display == "none"){
			updateCal("date",YAHOO.calendar.cal1);
			YAHOO.calendar.cal1.show();
		}else{
			YAHOO.calendar.cal1.hide();
		}
	}
	
	// Listener to show the Calendar when the button is clicked
	YAHOO.util.Event.addListener("showcal1", "click", handleCal1Button);
	YAHOO.calendar.cal1.hide();
	
	function handleSelect1(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("date");
			txtDate1.value = day + "." + month + "." + year;
		}

	function updateCal(input,obj) {
            var txtDate1 = document.getElementById(input);
            if (txtDate1.value != "") {
				var date = txtDate1.value.split(".");
				obj.select(date[1] + "/" + date[0] + "/" + date[2]);
				obj.cfg.setProperty("pagedate", date[1] + "/" + date[2]);
				obj.render();
            }
        }
	YAHOO.calendar.cal1.selectEvent.subscribe(handleSelect1, YAHOO.calendar.cal1, true);
}
YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
//-->
</script>

<?php
$setFocus = "OnLoad=\"document.getElementById('date').focus();\"";
pageTopHeadClose($title,false,$setFocus);
leftMenu($LAYOUT_ID);
contentStart();
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

echo $html;

$html = "<form method='post' action='?view=admin/addreservation&amp;season=".$season."&amp;reservation=".$res['id']."'>\n";
$html .= "<table>\n";

$html .= "<tr><td>"._("Date")." ("._("dd.mm.yyyy")."):</td><td>";
$html .= "<input type='text' class='input' name='date' id='date' value='".utf8entities(ShortDate($res['date']))."'/>&nbsp;\n";
$html .= "<button type='button' class='button' id='showcal1'>
		<img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>\n";
$html .= "<tr><td></td><td><div id='calContainer1'></div></td></tr>\n";

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
  $location_info = $location_info['name'];
}
$html .= LocationInput('location', 'location', $location_info, _("Location"), $res['location']); 

$html .= "<tr><td></td><td>&nbsp;</td></tr>\n";
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
  $html .= "</select></p>\n";
  $html .= "</td></tr>\n";
}

$html .= "<tr><td>";


if (!$addmore) {
  $html .= "<input type='hidden' name='id' value='".utf8entities($res['id'])."'/>";
  $html .= "<input type='submit' class='button' name='save' value='"._("Save")."'/>";
} else {
  $html .= "<input type='submit' class='button' name='add' value='"._("Add")."'/>";
}
$html .= "<input class='button' type='button' name='back'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/reservations&amp;season=".$season."'\"/>";
$html .= "</td><td>&nbsp;</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>";
echo $html;
echo LocationScript('location');
?>

<?php
echo TranslationScript("reservationgroup");
echo TranslationScript("fieldname");
contentEnd();
pageEnd();
?>
