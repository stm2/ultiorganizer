<?php
include_once 'cust/default/pdfprinter.php';

class CustomPDF {

  var UltiPDF $pdf;

  function __construct(UltiPDF $pdf) {
    $this->pdf = $pdf;
  }

  function init() {
    // implement to add your own customizations
  }

  function getScoresheetInstructions() {
    $data = "<br><b>" . _("Scoresheet filling instructions:") . "</b><br>";
    $data .= "1. " . _("Officials fill in their names.") . "<br>";
    $data .= "2. " .
      _("Captains confirm roster by crossing out injured players, and adjusting jersey numbers if necessary.") . "<br>";
    $data .= "3. " . _("After the toss, officials check the team that will start on offence.") . "<br>";
    $data .= "4. " . _("When half time starts, fill in time it ends (the second half start time).") . "<br>";
    $data .= "5. " .
      _(
        "During the game, fill in which team has scored, the jersey numbers of the player who threw the goal (Assist) and the player who caught the goal (Goal), the time that the goal was scored, and the scoreline after the goal. If a player scores an intercept goal (Callahan), then mark XX as assist.") .
      "<br>";
    $data .= "6. " . _("When a team takes a time-out, mark the time in the \"Time-outs\" section.") . "<br>";
    $data .= "7. " . _("After the game, each captain signs the scoresheet to confirm the final score.") . "<br>";
    $data .= "8. " . _("Officials return the completed scoresheet to the results headquarters.");
    return $data;
  }

  function getShortScoresheetInstructions() {
    $data = "1. " . _("Fill in team names if missing.") . "<br>";
    $data .= "2. " . _("Mark first offence and gender ratio of first point for mixed games.") . "<br>";
    $data .= "4. " . _("Mark goals scored.") . "<br>";
    $data .= "5. " . _("When a team takes a time-out, check appropriate box.") . "<br>";
    $data .= "6. " .
      _(
        "For the current ABBA gender ratio, look up the sum of the current scores in the table at the bottom of the page.") .
      "<br>";
    $data .= "7. " . _("After the game, fill in the final score and get the captains' signature.") . "<br>";
    $data .= "8. " . _("Report the game result using the QR code and the game # at the top right of the game record.");
    return $data;
  }
}
