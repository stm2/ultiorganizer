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
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));

pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

echo "<form action='?view=admin/locations&amp;season=$season' method='post'>\n";
echo _("Name or address") . ": <input class='input' name='search' value=''/>\n";
echo "<input class='button' type='submit' name='searchbutton' value='" . _("Search") . "'/>\n"; // searchByNameandaddress
echo "<input class='button' type='submit' name='addbutton' value='" . _('Add') . "'/>"; // addLocation
echo "</form>\n";
echo "<br/><br/>";

echo $message;

if ($mode == 'search') {
  $result = GetLocations('search', $_POST['search']);
  echo "<form action='?view=admin/locations&amp;season=$season' method='post'>\n";
  echo "<table><tr><th>name</th><th>address</th><th></th></tr>\n";
  $savedId = null;
  while ($row = @mysqli_fetch_assoc($result)){
    if ($row['id'] !== $savedId) {
      echo "<td>" . U_($row['name']) . "</td><td>". $row['address'] . "</td>";
      // FIXME season = ?? 
      echo "<td><a href='?view=admin/locations&amp;location=" . $row['id'] . "'><img class='deletebutton' src='images/settings.png' alt='E' title='"._("edit details")."'/></a></td>";
      echo "</tr>\n";
    }
    $savedId = $row['id'];
  }
  echo "</form>\n";
} else if ($mode == 'add' || $mode == 'edit') {
  if ($mode == 'add') {
    $location['new'] = 1;
    $location['name'] = $new_name;
  } else {
    
  }
  
  echo "<div id='editPlace'>\n";
  echo "<form method='post' action='?view=admin/locations&season=$season'>\n<div>\n";
  if ($location['new'])
    echo "<input type='hidden' name='isnew' id='isnew' value='". $location['new'] . "'/>\n";
  echo "<input type='hidden' name='id' id='place_id' value='$id'/>\n";

  echo "<table><tbody>\n";
  echo "  <tr><th>" . _("Name") . ":</th>";
  echo "  <td>" . TranslatedField("name", $location['name'], 200, 45) . "</td></tr>\n";
  echo "  <tr><th>" . _("Address") . "</th>";
  echo "  <td><input class='input' style='width:100%' name='address' id='address' value='" . $location['address'] . "'/>&nbsp;</tr>\n";

  foreach ($locales as $locale => $name) {
    $locale = str_replace(".", "_", $locale);
    echo "<tr><th>" . _("Info") . " (" . $name . ")";
    echo ":</th><td><textarea rows='3' style='width:100%' name='info_" . $locale . "' id='info_" . $locale . "'>" .
      $location['info'][$locale] . "</textarea></td></tr>";
  }

  echo "<tr><th>" . _("Fields") . ":</th><td><input type='text' style='width:100%;' name='fields' id='fields' value='" . $location['fields'] ."'/></td></tr>\n";
  $is_indoor = intval($location['indoor'])?"checked='checked'":"";
  echo "<tr><th>" . _("Indoor pitch") . ":</th><td><input type='checkbox' name='indoor' id='indoor' $is_indoor/></td></tr>\n";
  echo "<tr><th>" . _("Latitude") .
  ":</th><td><input type='text' style='width:100%;' name='lat' id='lat' value='" . $location['lat'] ."'/></td></tr>\n";
  echo "<tr><th>" . _("Longitude") .
  ":</th><td><input type='text' style='width:100%;' name='lng' id='lng' value='" . $location['lng'] ."'/></td></tr>\n"; 
  echo "</tbody></table>\n";
  echo "<p>";
  echo "<input type='submit' id='save' name='save' value='" . _("Save") . "'/>\n";
  if (!isset($location['new'])) {
    echo "<input type='submit' id='delete' name='delete' value='" . _("Delete") . "'/>\n";
  }
  echo "</p>\n";
  echo "</form>";
  echo "</div>\n";
}

echo TranslationScript("name");
contentEnd();
pageEnd();
?>
