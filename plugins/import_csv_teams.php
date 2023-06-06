<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=import
format=csv
security=superadmin
customization=all

[DESCRIPTION]
title = "Import Teams from CSV file"
description = "CSV file format: team,club,country,series id or name. If series doesn't exist, it is created with name on list."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
  die('Insufficient user rights');
}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";
$title = _("Import Teams from CSV file");

if (isset($_POST['import'])) {
  
  function test_integer($var) { return !(strval($var) !== strval(intval($var))); }

  $utf8 = !empty($_POST['utf8']);
  $season = $_POST['season'];
  $separator = $_POST['separator'];
  $series = SeasonSeries($season);
  $ser = array();
  foreach ($series as $row) {
    $ser[] = array('id' => $row['series_id'], 'name' => $row['name']);
  }

  if (is_uploaded_file($_FILES['file']['tmp_name'])) {
    $row = 1;
    if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 0, $separator)) !== FALSE) {
        $team = $utf8 ? $data[0] : utf8_encode($data[0]);
        $club = $utf8 ? $data[1] : utf8_encode($data[1]);
        $country = $utf8 ? $data[2] : utf8_encode($data[2]);
        $series = $utf8 ? $data[3] : utf8_encode($data[3]);

        // if series is given as name
        if (!test_integer($series)) {
          foreach ($ser as $s) {
            if ($s['name'] == $series) {
              $series = $s['id'];
              break;
            }
          }
          // not found
          if (!test_integer($series)) {
            $sp = array("series_id" => "", "name" => $series, "type" => "", "ordering" => "", "season" => $season,
              "pool_template" => 0, "valid" => "1");

            $id = AddSeries($sp);
            $ser[] = array('id' => $id, 'name' => $series);
            $series = $id;
          }
        }
        $id = AddSeriesEnrolledTeam($series, $_SESSION['uid'], $team, $club, $country);
        ConfirmEnrolledTeam($series, $id);
      }
      fclose($handle);
    }
  } else {
    $html .= "<p>" . ("There was an error uploading the file, please try again!") . "</p>";
  }
}

// season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/import_csv_teams'>\n";
$html .= "<p>" . _("Select event") . ": <select class='dropdown' name='season'>\n";

$seasons = Seasons();

while ($row = mysqli_fetch_assoc($seasons)) {
  $html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) .
    "</option>";
}

$html .= "</select></p>\n";
$html .= "<p>" . _("CSV separator") . ": <input class='input' maxlength='1' size='1' name='separator' value=','/></p>\n";

$html .= "<p>" . _("Select file to import") . ":<br/>\n";
$html .= "<input class='input' type='file' size='100' name='file'/><br/>\n";
$html .= "<input class='input' type='checkbox' name='utf8' /> " . _("File in UTF-8 format") . "</p>";
$html .= "<p><input class='button' type='submit' name='import' value='" . _("Import") . "'/></p>";
$html .= "<div><input type='hidden' name='MAX_FILE_SIZE' value='50000000' /></div>\n";
$html .= "</form>";

showPage($title, $html);
?>
