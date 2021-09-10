<?php
include_once 'menufunctions.php';
include_once 'lib/location.functions.php';
include_once 'lib/configuration.functions.php';

$season = -1;
if (!empty($_GET["season"])) {
  $season = $_GET["season"];
}

global $locales;

$location = array(
  'name' => '',
  'address' => '',
  'fields' => 1,
  'indoor' => 0,
  'lat' => '0',
  'lng' => '0'
);

$location['info'] = array();
foreach ($locales as $locale => $name) {
  $locale = str_replace(".", "_", $locale);
  $location['info'][$locale] = '';
}

if (isset($_POST['save'])) {
  if (isset($_POST['name']))
    $location['name'] = $_POST['name'];
  if (isset($_POST['address']))
    $location['address'] = $_POST['address'];
  foreach ($locales as $locale => $name) {
    $locale = str_replace(".", "_", $locale);
    if (isset($_POST['info_' . $locale]))
      $location['info'][$locale] = $_POST['info_' . $locale];
  }
  if (isset($_POST['fields']))
    $location['fields'] = $_POST['fields'];
  if (isset($_POST['indoor']))
    $location['indoor'] = "1";
  if (isset($_POST['lat']))
    $location['lat'] = $_POST['lat'];
  if (isset($_POST['lng']))
    $location['lng'] = $_POST['lng'];
  
  if (isset($_POST['id'])) {
    $id = $_POST['id'];
    if (empty(LocationInfo($id))) {
      $message = "<p>" ._("Invalid location.") . "</p>\n";
      $mode = '';
    } else if (!empty($location['name'])) {
      SetLocation($id, $location['name'], $location['address'], $location['info'], $location['fields'], $location['indoor'], 
        $location['lat'], $location['lng'], $season);
      $mode = 'edit';
    } else {
      $message = "<p>" . _("Name cannot be empty. Please edit and save.") . "</p>\n";
      $mode = 'edit';
    }
  } else if (isset($_POST['isnew'])) {
    if (!empty($name)) {
      $id = AddLocation($location['name'], $location['address'], $location['info'], $location['fields'], $location['indoor'],
        $location['lat'], $location['lng'], $season);
      $mode = 'edit';
      $message = "<p>" . _("New location has been created.") . "</p>\n";
    } else {
      $mode = 'add';
      $message = "<p>" . _("Name cannot be empty. Please edit and save.") . "</p>\n";
    }
  }
} else if (isset($_POST['delete']) && isset($_POST['id'])) {
  $id = $_POST['id'];
  if (empty(LocationInfo($id))) {
    $message = "<p>" ._("Invalid location.") . "</p>\n";
    $mode = '';
  } else {
    header('location: ?view=admin/locations');
    exit();
  }
} else if (isset($_POST['searchbutton'])) {
  $mode = 'search';
} else if (isset($_POST['id']) || isset($_GET['location'])) {
  $mode = 'edit';
  if (isset($_POST['id'])) {
    $id = $_POST['id'];
  } else {
    $id = $_GET['location'];
  }
  $location = LocationInfo($id);
  if (empty($location)) {
    $message = "<p>" ._("Invalid location.") . "</p>\n";
    $mode = '';
  } else {
    $location['info'] = array();

    foreach ($locales as $locale => $name) {
      $locale = str_replace(".", "_", $locale);
      $location['info'][$locale] = utf8entities(LocationDescription($id, $locale));
    }
  }
} else if (isset($_POST['addbutton'])) {
  $new_name =  $_POST['search'];
  $mode = 'add';
  $message = "<p>" . _("New location not saved. Plese edit the details and save.") . "</p>\n";
}

// common page
$title = _("Game locations");
$LAYOUT_ID = LOCATIONS;
$html = '';

include_once 'lib/yui.functions.php';

addHeaderCallback(
  function () {
    global $location;
    echo yuiLoad(array("utilities", "datasource", "autocomplete"));

    echo MapScript('map', $location['lat'], $location['lng'], 'lat', 'lng');
  });

$html .= "<form action='?view=admin/locations&amp;season=$season' method='post'>\n";
$html .= _("Name or address") . ": <input class='input' name='search' value=''/>\n";
$html .= "<input class='button' type='submit' name='searchbutton' value='" . _("Search") . "'/>\n"; // searchByNameandaddress
$html .= "<input class='button' type='submit' name='addbutton' value='" . _('Add') . "'/>"; // addLocation
$html .= "</form>\n";
$html .= "<br/><br/>";

$html .= $message;

if ($mode == 'search') {
  $result = GetLocations('search', $_POST['search']);
  $html .= "<form action='?view=admin/locations&amp;season=$season' method='post'>\n";
  $html .= "<table><tr><th>name</th><th>address</th><th></th></tr>\n";
  $savedId = null;
  while ($row = @mysqli_fetch_assoc($result)){
    if ($row['id'] !== $savedId) {
      $html .= "<td>" . U_($row['name']) . "</td><td>". $row['address'] . "</td>";
      // FIXME season = ?? 
      $html .= "<td><a href='?view=admin/locations&amp;location=" . $row['id'] . "'><img class='deletebutton' src='images/settings.png' alt='E' title='"._("edit details")."'/></a></td>";
      $html .= "</tr>\n";
    }
    $savedId = $row['id'];
  }
  $html .= "</form>\n";
} else if ($mode == 'add' || $mode == 'edit') {
  if ($mode == 'add') {
    $location['new'] = 1;
    $location['name'] = $new_name;
  } else {
    $html .= "<div id='map'></div>\n";    
  }
  
  $html .= "<div id='editPlace'>\n";
  $html .= "<form method='post' action='?view=admin/locations&season=$season'>\n<div>\n";
  if ($location['new'])
    $html .= "<input type='hidden' name='isnew' id='isnew' value='". $location['new'] . "'/>\n";
  if (isset($id))
    $html .= "<input type='hidden' name='id' id='place_id' value='$id'/>\n";

  $html .= "<table><tbody>\n";
  $html .= "  <tr><th>" . _("Name") . ":</th>";
  $html .= "  <td>" . TranslatedField("name", $location['name'], 200, 45) . "</td></tr>\n";
  $html .= "  <tr><th>" . _("Address") . "</th>";
  $html .= "  <td><input class='controls' type='text' style='width:100%' name='address' id='address' value='" . $location['address'] . "'/>&nbsp;</tr>\n";

  foreach ($locales as $locale => $name) {
    $locale = str_replace(".", "_", $locale);
    $html .= "<tr><th>" . _("Info") . " (" . $name . ")";
    $html .= ":</th><td><textarea rows='3' style='width:100%' name='info_" . $locale . "' id='info_" . $locale . "'>" .
      $location['info'][$locale] . "</textarea></td></tr>";
  }

  $html .= "<tr><th>" . _("Fields") . ":</th><td><input type='text' style='width:100%;' name='fields' id='fields' value='" . $location['fields'] ."'/></td></tr>\n";
  $is_indoor = intval($location['indoor'])?"checked='checked'":"";
  $html .= "<tr><th>" . _("Indoor pitch") . ":</th><td><input type='checkbox' name='indoor' id='indoor' $is_indoor/></td></tr>\n";
  $html .= "<tr><th>" . _("Latitude") .
  ":</th><td><input type='text' style='width:100%;' name='lat' id='lat' value='" . $location['lat'] ."'/></td></tr>\n";
  $html .= "<tr><th>" . _("Longitude") .
  ":</th><td><input type='text' style='width:100%;' name='lng' id='lng' value='" . $location['lng'] ."'/></td></tr>\n"; 
  $html .= "</tbody></table>\n";
  $html .= "<p>";
  $html .= "<input type='submit' id='save' name='save' value='" . _("Save") . "'/>\n";
  if (!isset($location['new'])) {
    $html .= "<input type='submit' id='delete' name='delete' value='" . _("Delete") . "'/>\n";
  }
  $html .= "</p>\n";
  $html .= "</form>";
  $html .= "</div>\n";
}

$html .= TranslationScript("name");

showPage($title, $html);
?>
