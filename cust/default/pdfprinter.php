<?php
include_once $include_prefix . 'lib/fpdf/fpdf.php';
include_once $include_prefix . 'lib/HSVClass.php';
include_once 'lib/phpqrcode/qrlib.php';

class PDF extends FPDF {

  var $B;

  var $I;

  var $U;

  var $HREF;

  var $game = array("seasonname" => "", "game_id" => "", "hometeamname" => "", "visitorteamname" => "", "poolname" => "",
    "time" => "", "placename" => "");

  var $organization;

  var $logo;

  var $minFontSize = 6;

  function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
    parent::__construct($orientation, $unit, $format);
    $this->organization = U_("Organization");
    $this->logo = "cust/" . CUSTOMIZATIONS . "/logo.png";
  }

  function getScoresheetInstructions() {}

  function getRosterInstructions() {}
  
  function getOrganization() {
    return $this->organization;
  }

  function setOrganization($orga) {
    $this->organization = $orga;
  }

  function getLogo() {
    return $this->logo;
  }

  function setLogo($logo) {
    $this->logo = $logo;
  }

  function PrintScoreSheet($seasonname, $gameId, $hometeamname, $visitorteamname, $poolname, $time, $placename) {
    $this->game['seasonname'] = utf8_decode($seasonname);
    $this->game['game_id'] = $gameId . "" . getChkNum($gameId);
    $this->game['hometeamname'] = utf8_decode($hometeamname);
    $this->game['visitorteamname'] = utf8_decode($visitorteamname);
    $this->game['poolname'] = utf8_decode($poolname);
    $this->game['time'] = $time;
    $this->game['placename'] = utf8_decode($placename);
    
    $this->AddPage();
    
    $data = $this->getOrganization();
    $data .= " - ";
    $data .= _("Game Record");
    $data = utf8_decode($data); // season name already decoded
    $data .= " " . $this->game['seasonname'];
    
    $this->setHeaderStyle(1);
    $this->Cell(0, 9, $data, 1, 1, 'C', true);
    
    $this->SetY(21);
    
    $this->OneCellTable(utf8_decode(_("Game #")), $this->game['game_id']);
    $this->OneCellTable(utf8_decode(_("Division") . ", " . _("Pool")), $this->game['poolname']);
    $this->OneCellTable(utf8_decode(_("Field")), $this->game['placename']);
    $this->OneCellTable(utf8_decode(_("Game official")), "");
    $this->SetFont('Arial', '', 10);
    $this->Ln();
    
    $this->OneCellTable(utf8_decode(_("Scheduled start date and time")), $this->game['time']);
    
    $this->FirstOffence();
    
    $this->Timeouts();
    $this->OneCellTable(utf8_decode(_("Half time ends")), "");
    $this->Ln();
    
    $this->FinalScoreTable();
    
    $this->Signatures();
    $this->Ln();
    
    // print QR-code for result URL
    $filename = UPLOAD_DIR . $this->game['game_id'] . ".png";
    $url = BASEURL . "scorekeeper/?view=result&g=" . $this->game['game_id'];
    
    QRcode::png($url, $filename, 'h', 2, 2);
    $this->Image($filename);
    unlink($filename);
    
    $data = _("After the match has ended, update result:\n\n") . BASEURL . "/scorekeeper/?view=result";
    $data = utf8_decode($data);
    $this->SetFont('Arial', '', 8);
    $this->SetTextColor(0);
    $this->SetFillColor(255);
    $this->MultiCell(0, 2, $data);
    
    if (is_file($this->getLogo())) {
      $this->SetY(-25);
      $this->SetFont('Arial', '', 10);
      $this->setEmptyStyle();
      $this->Image($this->getLogo(), 10, 255, 80);
    }
    
    $this->SetXY(95, 21);
    $this->ScoreGrid();
  }

  function PrintDefenseSheet($seasonname, $gameId, $hometeamname, $visitorteamname, $poolname, $time, $placename) {
    $this->game['seasonname'] = utf8_decode($seasonname);
    $this->game['game_id'] = $gameId . "" . getChkNum($gameId);
    $this->game['hometeamname'] = utf8_decode($hometeamname);
    $this->game['visitorteamname'] = utf8_decode($visitorteamname);
    $this->game['poolname'] = utf8_decode($poolname);
    $this->game['time'] = $time;
    $this->game['placename'] = utf8_decode($placename);
    
    $this->AddPage();
    
    $data = _("Organization");
    $data .= " - ";
    $data .= _("Defenses record");
    $data = utf8_decode($data); // season name already decoded
    $data .= " " . $this->game['seasonname'];
    
    $this->setHeaderStyle(1);
    $this->Cell(0, 9, $data, 1, 1, 'C', true);
    $this->Ln();
    
    $this->SetY(21);
    $this->DefenseGrid();
  }

  // Playerlist array("name"=>name, "accredited"=>accredited, "num"=>number)
  function PrintPlayerList($homeplayers, $visitorplayers) {
    $this->AddPage();
    
    $data = $this->getOrganization();
    $data .= " - ";
    $data .= _("Roster");
    $data .= " " . _("for game") . " #" . $this->game['game_id'];
    $data = utf8_decode($data);
    $this->setHeaderStyle(1);
    $this->Cell(0, 9, $data, 1, 1, 'C', true);
    
    $this->SetY(21);
    
    $this->setHeaderStyle(2);
    
    $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
    $this->Cell(90, 6, $this->game['hometeamname'], 'LRTB', 0, 'C', true);
    
    $this->setEmptyStyle();
    $this->Cell(2, 6, "", 'LR', 0, 'C', true); // separator
    
    $this->setHeaderStyle(2);
    $this->Cell(90, 6, $this->game['visitorteamname'], 'LRTB', 0, 'C', true);
    
    $this->Ln();
    $this->setHeaderStyle(3);
    $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
    $this->Cell(52, 6, utf8_decode(_("Name")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Jersey#")), 'LRTB', 0, 'C', true);
    $this->Cell(23, 6, utf8_decode(_("Info")), 'LRTB', 0, 'C', true);
    
    $this->setEmptyStyle();
    $this->Cell(2, 6, "", 'LR', 0, 'C', true); // separator
    
    $this->setHeaderStyle(3);
    $this->Cell(52, 6, utf8_decode(_("Name")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Jersey#")), 'LRTB', 0, 'C', true);
    $this->Cell(23, 6, utf8_decode(_("Info")), 'LRTB', 0, 'C', true);
    
    $this->Ln();
    $this->setEmptyStyle();
    for ($i = 1; $i < 31; $i++) {
      $hplayer = "";
      $hnumber = "";
      $vplayer = "";
      $vnumber = "";
      
      if (isset($homeplayers[$i - 1]['name'])) {
        $hplayer = utf8_decode($homeplayers[$i - 1]['name']);
        $hnumber = $homeplayers[$i - 1]['num'];
      }
      if (isset($visitorplayers[$i - 1]['name'])) {
        $vplayer = utf8_decode($visitorplayers[$i - 1]['name']);
        $vnumber = $visitorplayers[$i - 1]['num'];
      }
      $this->setEmptyStyle();
      $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);
      
      if (!empty($hplayer) && !($homeplayers[$i - 1]['accredited'])) {
        $this->setEmptyStyle(3, NULL, 'IB');
      }
      
      $this->Cell(52, 6, $hplayer, 'LRTB', 0, 'L', true);
      
      $this->setEmptyStyle();
      $this->Cell(15, 6, $hnumber, 'LRTB', 0, 'C', true);
      $this->Cell(23, 6, "", 'LRTB', 0, 'C', true);
      
      $this->Cell(2, 6, "", 'LR', 0, 'C', true); // separator
      
      if (!empty($vplayer) && !($visitorplayers[$i - 1]['accredited'])) {
        $this->setEmptyStyle(3, NULL, 'IB');
      }
      $this->Cell(52, 6, $vplayer, 'LRTB', 0, 'L', true);
      
      $this->setEmptyStyle();
      $this->Cell(15, 6, $vnumber, 'LRTB', 0, 'C', true);
      $this->Cell(23, 6, "", 'LRTB', 0, 'C', true);
      $this->Ln();
    }
    
    $this->setEmptyStyle(4);
    $data = _("Total number of players:") . " " . count($homeplayers);
    $data = utf8_decode($data);
    $this->Cell(98, 4, $data, 'T', 0, 'L', true);
    $this->Cell(2, 6, "", '', 0, 'C', true); // separator
    $data = _("Total number of players:") . " " . count($visitorplayers);
    $data = utf8_decode($data);
    $this->Cell(90, 4, $data, 'T', 0, 'L', true);
    
    $this->Ln();
    
    // instructions
    $data = utf8_decode($this->getScoresheetInstructions());
    $this->setEmptyStyle(4);
    $this->WriteHTML($data);
  }

  function PrintRoster($teamname, $seriesname, $poolname, $players) {
    $this->AddPage();
    
    $data = $teamname;
    $data .= " - ";
    $data .= _("Roster");
    $data = utf8_decode($data);
    $this->setHeaderStyle(1);
    $this->Cell(0, 9, $data, 1, 1, 'C', true);
    
    $data = U_($seriesname);
    $data .= ", ";
    $data .= U_($poolname);
    $data .= ", ";
    $data .= _("Game") . " #:";
    $data = utf8_decode($data);
    $this->setEmptyStyle(1);
    $this->Cell(0, 6, $data, 1, 1, 'L', true);
    
    $this->setHeaderStyle(4);
    
    $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
    $this->Cell(100, 6, utf8_decode(_("Name")), 'LRTB', 0, 'C', true);
    $this->Cell(10, 6, utf8_decode(_("Play")), 'LRTB', 0, 'C', true);
    $this->Cell(10, 6, utf8_decode(_("Game#")), 'LRTB', 0, 'C', true);
    $this->Cell(62, 6, utf8_decode(_("Info")), 'LRTB', 0, 'C', true);
    $this->Ln();
    $this->setEmptyStyle();
    for ($i = 1; $i < 26; $i++) {
      $player = "";
      
      if (isset($players[$i - 1]['firstname'])) {
        $player .= utf8_decode($players[$i - 1]['firstname']);
      }
      $player .= " ";
      if (isset($players[$i - 1]['lastname'])) {
        $player .= utf8_decode($players[$i - 1]['lastname']);
      }
      
      $this->setEmptyStyle();
      $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);
      
      if (isset($players[$i - 1]['accredited']) && !($players[$i - 1]['accredited'])) {
        $this->setEmptyStyle(3, NULL, 'IB');
      }
      
      $this->Cell(100, 6, $player, 'LRTB', 0, 'L', true);
      $this->setEmptyStyle();
      $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
      if (isset($players[$i - 1]['num']) && $players[$i - 1]['num'] >= 0) {
        $this->Cell(10, 6, $players[$i - 1]['num'], 'LRTB', 0, 'C', true);
      } else {
        $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
      }
      $this->Cell(62, 6, "", 'LRTB', 0, 'C', true);
      
      $this->Ln();
    }
    
    $this->Ln();
    
    $data = utf8_decode($this->getScoresheetInstructions());
    $this->setEmptyStyle(4);
    $this->WriteHTML($data);
  }

  function PrintSchedule($scope, $id, $games, $subset, $lines) {
    $left_margin = 10;
    $top_margin = 10;
    // event title
    $this->SetAutoPageBreak(false, $top_margin);
    $this->SetMargins($left_margin, $top_margin);
    
    $this->AddPage();
    
    switch ($scope) {
    case "season":
      $this->PrintSeasonPools($id, $subset);
      $this->AddPage();
      break;
    
    case "series":
      $this->PrintSeriesPools($id, $subset);
      $this->AddPage();
      break;
    
    case "pool":
    case "team":
      break;
    }
    
    $this->SetAutoPageBreak(true, $top_margin);
    $prevTournament = "";
    $prevPlace = "";
    $prevSeries = "";
    $prevPool = "";
    $prevTeam = "";
    $prevDate = "";
    $prevField = "";
    $isTableOpen = false;
    
    $this->SetTextColor(255);
    $this->SetFillColor(0);
    $this->SetDrawColor(0);
    
    $groups = array('date' => true, 'time' => true, 'field' => true, 'pool' => true, 'result' => true);
    
    foreach ($lines as $line) {
      switch ($line["type"]) {
      case "groups":
        foreach ($line['groups'] as $type => $show)
          $groups[$type] = $show;
        break;
      case "h2":
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0);
        $this->Ln();
        $this->Write(5, $this->getText($line));
        $this->Ln();
        break;
      case "h3":
        $txt = DefWeekDateFormat($game['starttime']);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0);
        $this->Ln();
        $this->Write(5, $this->getText($line));
        $this->Ln();
        break;
      case "th":
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0);
        $this->Ln();
        $this->Cell(0, 5, $this->getText($line), 0, 2, 'L', false);
        break;
      case "game":
        $this->SetFont('Arial', '', 8);
        if (isset($line['game'])) {
          $this->GameRowWithPool($line["game"], $groups['date'], $groups['time'], $groups['field'], $groups['pool']);
          $this->Ln();
        } else {
          $this->cell(0, 5, "error", 0, 2, 'L', false);
          $this->Ln();
        }
        break;
      default:
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0);
        $this->Cell(0, 5, "error " . $line['type'], 0, 2, 'L', false);
        $this->Ln();
      }
    }
  }

  function getText($line) {
    if (isset($line["text"]))
      return utf8_decode($line["text"]);
    $text = "";
    foreach ($line["content"] as $type => $content) {
      if ($type == "text") {
        $text .= $content;
      } else if ($type == "link")
        $text .= $content[1];
    }
    return utf8_decode($text);
  }

  function ufield($game) {
    $txt = U_($game['placename']);
    $txt .= " " . _("Field") . " " . U_($game['fieldname']);
    $txt = utf8_decode($txt);
    return $txt;
  }
  
  function PrintOnePageSchedule($scope, $id, $games, $colors = false) {
    $left_margin = 10;
    $top_margin = 15;
    $xarea = 400;
    $yarea = 270;
    $yfieldtitle = 5;
    $xtimetitle = 20;
    
    // event title
    $this->SetAutoPageBreak(false, $top_margin);
    $this->SetMargins($left_margin, $top_margin);
    
    $timeslots = array();
    $times = array();
    $prevTournament = "";
    $prevPlace = "";
    $prevSeries = "";
    $prevPool = "";
    $prevTeam = "";
    $prevDate = "";
    $prevField = "";
    $fieldstotal = 0;
    
    $isTableOpen = false;
    
    $field = 0;
    $time_offset = $top_margin + $yfieldtitle;
    $field_offset = 0;
    $gridx = 12;
    $gridy = 20;
    $fieldlimit = 15;
    
    $this->SetTextColor(255);
    $this->SetFillColor(0);
    $this->SetDrawColor(0);
    // print all games in order
    while ($game = mysqli_fetch_assoc($games)) {
      
      // one reservation group per page
      if (!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament ||
         $prevDate != JustDate($game['starttime'])) {
        $this->AddPage("L", "A3");
        $times = TimetableTimeslots($game['reservationgroup'], $id);
        $timeslots = array();
        $i = 0;
        foreach ($times as $time) {
          $timeslots[$time['time']] = $i * 20;
          $i++;
        }
        
        $fieldstotal = TimetableFields($game['reservationgroup'], $id);
        $fieldlimit = max($fieldstotal / 2 + 1, 10);
        $gridx = $xarea / $fieldlimit;
        $field = 0;
        $prevField = "";
        $time_offset = $top_margin + $yfieldtitle;
      }
      
      // next field
      if (!empty($game['place_id']) && $game['fieldname'] != $prevField) {
        $field++;
        
        if ($field >= $fieldlimit) {
          $field = 1;
          $time_offset = $yarea / 2 + $top_margin + 2 * $yfieldtitle;
        }
        // write times
        if ($field == 1) {
          $this->SetFont('Arial', 'B', 10);
          $this->SetTextColor(0);
          $this->SetXY($left_margin, $time_offset);
          
          // write times
          foreach ($times as $time) {
            $this->Cell($xtimetitle, $gridy / 4, "", 0, 2, 'L', false);
            $txt = utf8_decode(ShortDate($time['time']));
            $this->Cell($xtimetitle, $gridy / 4, $txt, 0, 2, 'L', false);
            $txt = utf8_decode(DefHourFormat($time['time']));
            $this->Cell($xtimetitle, $gridy / 4, $txt, 0, 2, 'L', false);
            $this->Cell($xtimetitle, $gridy / 4, "", 0, 2, 'L', false);
          }
        }
        
        $field_offset = $left_margin + ($field - 1) * $gridx + $xtimetitle;
        $this->SetXY($field_offset, $time_offset - $yfieldtitle);
        
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0);
        $txt = utf8_decode(_("Field") . " " . $game['fieldname']);
        $this->Cell($gridx, $yfieldtitle, $txt, "LR", 2, 'C', false);
        // write grids
        foreach ($times as $time) {
          $this->Cell($gridx, $gridy, "", 1, 2, 'L', false);
        }
      }
      
      $slot = $game['time'];
      $this->SetXY($field_offset, $time_offset + $timeslots[$slot]);
      
      $this->SetTextColor(0);
      $this->SetFillColor(255);
      $this->SetDrawColor(0);
      $this->SetFont('Arial', '', 8);
      $this->SetTextColor(0);
      $this->Cell($gridx, 1, "", 0, 2, 'L', false);
      if ($game['hometeam'] && $game['visitorteam']) {
        $txt = utf8_decode($game['hometeamname']);
        $this->setTeamFont($txt, $gridx, 8);
        $this->fitCell($gridx, 4, $txt, 0, 2, 'L', false);
        $txt = utf8_decode($game['visitorteamname']);
        $this->setTeamFont($txt, $gridx, 8);
        $this->fitCell($gridx, 4, $txt, 0, 2, 'L', false);
      } else {
        $txt = utf8_decode(U_($game['phometeamname']));
        $this->setTeamFont($txt, $gridx, 8);
        $this->fitCell($gridx, 4, $txt, 0, 2, 'L', false);
        $txt = utf8_decode(U_($game['pvisitorteamname']));
        $this->setTeamFont($txt, $gridx, 8);
        $this->fitCell($gridx, 4, $txt, 0, 2, 'L', false);
      }
      $this->SetFont('Arial', '', 8);
      
      if ($colors) {
        $textcolor = $this->TextColor($game['color']);
        $fillcolor = colorstring2rgb($game['color']);
        
        $this->SetDrawColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
        $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
        $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
      } else {
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->SetDrawColor(0);
      }
      
      $this->Cell($gridx, 1, "", 0, 2, 'L', $colors);
      $txt = utf8_decode($game['seriesname']);
      if (strlen($game['poolname']) < 15) {
        $txt .= ", \n";
      } else {
        $txt .= ", ";
      }
      $txt .= utf8_decode($game['poolname']);
      // $this->DynSetFont($txt,$gridx,8);
      $this->MultiCell($gridx, 3, $txt, "LR", 2, 'L', $colors);
      
      $this->SetXY($field_offset, $time_offset + $timeslots[$slot]);
      $this->Cell($gridx, $gridy, "", "LRBT", 2, 'L', false);
      
      $prevTournament = $game['reservationgroup'];
      $prevPlace = $game['place_id'];
      $prevField = $game['fieldname'];
      $prevSeries = $game['series_id'];
      $prevPool = $game['pool'];
      $prevDate = JustDate($game['starttime']);
      $prevTime = DefHourFormat($game['starttime']);
    }
  }

  function Footer() {
    $this->SetXY(-50, -8);
    $this->SetFont('Arial', '', 6);
    $this->SetTextColor(0);
    $txt = utf8_decode(date('Y-m-d H:i:s P', time()));
    $this->Cell(0, 0, $txt, 0, 2, 'R', false);
  }

  function TextColor($bgcolor) {
    $hsv = new HSVClass();
    $hsv->setRGBString($bgcolor);
    $hsv->changeHue(180);
    $hsvArr = $hsv->getHSV();
    $hsv->setHSV($hsvArr['h'], 1 - $hsvArr['s'], 1 - $hsvArr['v']);
    return $hsv->getRGB();
  }

  function fitFont($text, $maxsize, $width) {
    while ($this->GetStringWidth($text) > $width) {
      $lower = $this->minFontSize;
      $upper = $maxsize;
      $i = 0;
      while ($upper > $lower + .5 && $i++ < 10) {
        $current = floor($upper + $lower) / 2;
        $this->SetFontSize($current);
        if ($this->GetStringWidth($text) > $width) {
          $upper = $current;
        } else {
          $lower = $current;
        }
      }
      if ($this->GetStringWidth($text) > $width)
        $this->SetFontSize($current - .5);
      if ($this->GetStringWidth($text) > $width && mb_strlen($text)>1) {
        $text = mb_substr($text, 0, floor(mb_strlen($text) * 3 / 4));
      }
    }
    return $text;
  }

  function fitCell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
    if ($w > 1) {
      $oldSize = $this->FontSizePt;
      $txt = $this->fitFont($txt, $oldSize, $w - 1);
    }
    $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    if ($w > 0) {
      $this->SetFontSize($oldSize);
    }
  }

  function GameRowWithPool($game, $date = false, $time = true, $field = true, $pool = true, $result = true) {
    $fontsize = 8;
    $this->SetFont('Arial', '', $fontsize);
    $textcolor = $this->TextColor($game['color']);
    $fillcolor = colorstring2rgb($game['color']);
    $this->SetDrawColor(0);
    $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
    $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
    
    $date = (bool) $date;
    $time = (bool) $time;
    $field = (bool) $field;
    $pool = (bool) $pool;
    $result = (bool) $result;
    
    $cols = $date + $time + $field + $pool + $result;
    $extra = ($this->w - $this->lMargin - $this->rMargin - $date * 10 - $time * 10 - $field * 20 - 92 - $pool * 45 -
       $result * 19) / ($cols + 2);
    if ($extra < 0)
      $extra = 0;
    
    if ($date) {
      $txt = utf8_decode(ShortDate($game['time']));
      $this->fitCell(10 + $extra, 5, $txt, 'TB', 0, 'L', true);
    }
    
    if ($time) {
      $txt = utf8_decode(DefHourFormat($game['time']));
      $this->fitCell(10 + $extra, 5, $txt, 'TB', 0, 'L', true);
    }
    
    if ($field) {
      $txt = $this->ufield($game);
      $this->fitCell(20 + $extra, 5, $txt, 'TB', 0, 'L', true);
    }
    
    $o = 0;
    if ($game['gamename']) {
      $this->SetFont('Arial', 'B', $fontsize);
      $txt = utf8_decode(U_($game['gamename']) . ":");
      $this->fitCell(28, 5, $txt, 'TB', 0, 'L', true);
      $o = 14;
      $this->SetFont('Arial', '', $fontsize);
    }
    
    if ($game['hometeam'] && $game['visitorteam']) {
      $txt = utf8_decode($game['hometeamname']);
      $this->fitCell(44 - $o + $extra, 5, $txt, 'TB', 0, 'L', true);
      $txt = " - ";
      $this->Cell(4, 5, $txt, 'TB', 0, 'L', true);
      $txt = utf8_decode($game['visitorteamname']);
      $this->fitCell(44 - $o + $extra, 5, $txt, 'TB', 0, 'L', true);
    } else {
      $this->SetFont('Arial', 'I', $fontsize);
      $txt = utf8_decode($game['phometeamname']);
      $this->fitCell(44 - $o + $extra, 5, $txt, 'TB', 0, 'L', true);
      $txt = " - ";
      $this->Cell(4, 5, $txt, 'TB', 0, 'L', true);
      $txt = utf8_decode($game['pvisitorteamname']);
      $this->fitCell(44 - $o + $extra, 5, $txt, 'TB', 0, 'L', true);
      $this->SetFont('Arial', '', $fontsize);
    }
    if ($pool) {
      $txt = utf8_decode(U_($game['seriesname']));
      $this->fitCell(20 + $extra / 2, 5, $txt, 'TB', 0, 'L', true);
      
      $txt = utf8_decode(U_($game['poolname']));
      $this->fitCell(25 + $extra / 2, 5, $txt, 'TB', 0, 'L', true);
    }
    
    if ($result) {
      if (GameHasStarted($game) && !intval($game['isongoing'])) {
        $txt = intval($game['homescore']);
        $this->fitCell(7 + $extra / 2, 5, $txt, 'TB', 0, 'L', true);
        $txt = " - ";
        $this->Cell(4, 5, $txt, 'TB', 0, 'L', true);
        $txt = intval($game['visitorscore']);
        $this->fitCell(7 + $extra / 2, 5, $txt, 'TB', 0, 'L', true);
      } else {
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->SetDrawColor(0);
        $this->Cell(7 + $extra / 2, 5, "", 'TB', 0, 'L', true);
        $txt = " - ";
        $this->Cell(4, 5, $txt, 'TB', 0, 'L', true);
        $this->Cell(7 + $extra / 2, 5, "", 'TB', 0, 'L', true);
        $this->SetDrawColor(0);
        $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
        $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
      }
    }
  }

  function PrintSeasonPools($id, $subset = null) {
    $title = utf8_decode(SeasonName($id));
    $series = SeasonSeries($id, true);
    
    $this->SetFont('Arial', 'B', 16);
    $this->SetTextColor(255);
    $this->SetFillColor(0);
    $this->Cell(0, 9, $title, 1, 1, 'C', true);
    
    // print all series with color coding
    foreach ($series as $row) {
      $this->PrintSeriesPools($row['series_id'], $subset);
    }
  }

  function PrintSeriesPools($id, $subset = null) {
    $left_margin = 10;
    $top_margin = 10;
    $pools = SeriesPools($id, false);
    if (!($subset === null))
      $pools = array_filter($pools,
        function ($pool) use ($subset) {
          return isset($subset[$pool['pool_id']]) && $subset[$pool['pool_id']] == true;
        });
    if (!$pools)
      return;
    
    if ($this->GetY() + 97 > 297) {
      $this->AddPage();
    }
    $name = utf8_decode(U_(SeriesName($id)));
    $this->SetFont('Arial', 'B', 14);
    $this->SetTextColor(0);
    
    $this->Ln();
    $this->Write(6, $name);
    $this->Ln();
    $max_y = $this->PrintPools($pools, $subset);
    $this->SetXY($left_margin, $max_y);
  }

  function PrintPools($pools, $subset = null) {
    $left_margin = 10;
    $top_margin = 10;
    $pools_x = $left_margin;
    $pools_y = $this->GetY();
    $max_y = $this->GetY();
    $i = 0;
    foreach ($pools as $pool) {
      
      $poolinfo = PoolInfo($pool['pool_id']);
      $teams = PoolTeams($pool['pool_id']);
      $scheduling_teams = false;
      
      if (!count($teams)) {
        $teams = PoolSchedulingTeams($pool['pool_id']);
        $scheduling_teams = true;
      }
      $name = utf8_decode(U_($poolinfo['name']));
      
      if ($i % 6 == 0 && $i <= count($pools)) {
        $this->SetXY($left_margin, $max_y);
        $max_y = $this->GetY();
        $pools_y = $this->GetY();
        $pools_x = $left_margin;
      } else {
        $this->SetXY($pools_x, $pools_y);
      }
      
      // pool header
      $fontsize = 10;
      $this->SetFont('Arial', 'B', $fontsize);
      
      $this->SetTextColor(0);
      $this->SetFillColor(255);
      $this->SetDrawColor(0);
      $this->fitCell(30, 5, $name, 1, 2, 'C', false);
      
      // pool teams
      
      $textcolor = $this->TextColor($poolinfo['color']);
      $fillcolor = colorstring2rgb($poolinfo['color']);
      
      $this->SetDrawColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
      $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
      $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
      
      foreach ($teams as $team) {
        $txt = utf8_decode(U_($team['name']));
        $fontsize = 10;
        if ($scheduling_teams) {
          $this->SetFont('Arial', 'i', $fontsize);
        } else {
          $this->SetFont('Arial', '', $fontsize);
        }
        $this->fitCell(30, 5, $txt, '1', 2, 'L', true);
      }
      
      $pools_x += 31;
      if ($this->GetY() > $max_y) {
        $max_y = $this->GetY() + 1;
      }
      $i++;
    }
    return $max_y;
  }

  function PrintError($text) {
    $this->AddPage();
    
    $this->SetFont('Arial', '', 12);
    $this->SetTextColor(0);
    $this->SetFillColor(255);
    $this->MultiCell(0, 8, $text);
  }

  function Timeouts() {
    // header
    $this->setHeaderStyle(2);
    $this->Cell(80, 6, utf8_decode(_("Time-outs")), 'LRTB', 0, 'C', true);
    $this->Ln();
    
    // home grids
    $this->setEmptyStyle();
    $this->Cell(20, 6, utf8_decode(_("Home")), 'LRTB', 0, 'L', true);
    
    for ($i = 0; $i < 4; $i++) {
      $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
    }
    
    $this->Ln();
    
    // visitor grids
    $this->setEmptyStyle();
    $this->Cell(20, 6, utf8_decode(_("Away")), 'LRTB', 0, 'L', true);
    
    for ($i = 0; $i < 4; $i++) {
      $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
    }
    $this->Ln();
  }

  function FirstOffence() {
    $this->setHeaderStyle();
    $this->Cell(80, 6, utf8_decode(_("First Offence")), 'LRTB', 0, 'C', true);
    $this->Ln();
    $this->setEmptyStyle();
    $this->Cell(20, 6, utf8_decode(_("Team")), 'LRTB', 0, 'C', true);
    $this->Cell(20, 6, "", 'LRTB', 0, 'C', true);
    $this->Cell(20, 6, utf8_decode(_("Time")), 'LRTB', 0, 'C', true);
    $this->Cell(20, 6, "", 'LRTB', 0, 'C', true);
    $this->Ln();
  }

  function SpiritPoints() {
    // header
    $this->setHeaderStyle(2);
    $this->Cell(80, 6, utf8_decode(_("Spirit points")), 'LRTB', 0, 'C', true);
    $this->Ln();
    $this->setEmptyStyle();
    $fontsize = 12;
    $this->SetFont('Arial', 'B', $fontsize);
    $text = $this->game['hometeamname'];
    $this->fitCell(40, 6, $text, 'LRT', 0, 'C', true);
    
    $fontsize = 12;
    $text = $this->game['visitorteamname'];
    $this->SetFont('Arial', 'B', $fontsize);
    $this->fitCell(40, 6, $text, 'LRT', 0, 'C', true);
    
    $this->Ln();
    $this->setEmptyStyle();
    $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
    $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
    $this->Ln();
  }

  function Signatures() {
    $this->setHeaderStyle(2);
    $this->Cell(80, 6, utf8_decode(_("Captains' signatures")), 'LRTB', 0, 'C', true);
    $this->Ln();
    
    // home grids
    $this->setEmptyStyle();
    $this->Cell(15, 8, utf8_decode(_("Home")), 'LRTB', 0, 'L', true);
    $this->Cell(65, 8, "", 'LRTB', 0, 'L', true);
    
    $this->Ln();
    
    // visitor grids
    $this->setEmptyStyle();
    $this->Cell(15, 8, utf8_decode(_("Away")), 'LRTB', 0, 'L', true);
    $this->Cell(65, 8, "", 'LRTB', 0, 'L', true);
    $this->Ln();
  }

  function ScoreGrid() {
    $this->setPreFilledStyle(4);
    $this->SetX(100);
    $this->Cell(20, 4, utf8_decode(_("Scoring team")), 'LRT', 0, 'C', true);
    $this->Cell(30, 4, utf8_decode(_("Jersey numbers")), 'LRT', 0, 'C', true);
    $this->Ln();
    $this->SetX(100);
    $this->setPreFilledStyle(3);
    $this->Cell(10, 6, utf8_decode(_("Home")), 'LRB', 0, 'C', true);
    $this->Cell(10, 6, utf8_decode(_("Away")), 'LRB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Assist")), 'LRB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Goal")), 'LRB', 0, 'C', true);
    $this->Cell(25, 6, utf8_decode(_("Time")), 'LRTB', 0, 'C', true);
    $this->Cell(25, 6, utf8_decode(_("Score")), 'LRTB', 0, 'C', true);
    $this->Ln();
    $this->setEmptyStyle();
    for ($i = 1; $i < 41; $i++) {
      $this->SetX(95);
      $this->SetFont('Arial', '', 8);
      $this->Cell(5, 6, $i, '', 0, 'C', true);
      $this->SetFont('Arial', '', 10);
      $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(25, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(25, 6, "-", 'LRTB', 0, 'C', true);
      $this->Ln();
    }
  }

  function DefenseGrid() {
    $this->setEmptyStyle(4);
    // $this->SetX(100);
    // $this->Cell(24,4,utf8_decode(_("Scoring team")),'LRT',0,'C',true);
    // $this->Cell(30,4,utf8_decode(_("Jersey numbers")),'LRT',0,'C',true);
    // $this->Ln();
    $this->SetX(50);
    $this->setEmptyStyle(3);
    $this->Cell(12, 6, utf8_decode(_("Home")), 'LRTB', 0, 'C', true);
    $this->Cell(12, 6, utf8_decode(_("Away")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Player")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Touched")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Caught")), 'LRTB', 0, 'C', true);
    $this->Cell(15, 6, utf8_decode(_("Callahan")), 'LRTB', 0, 'C', true);
    $this->Cell(25, 6, utf8_decode(_("Time")), 'LRTB', 0, 'C', true);
    $this->Ln();
    $this->setEmptyStyle();
    for ($i = 1; $i < 31; $i++) {
      $this->SetX(45);
      $this->setEmptyStyle(4);
      $this->Cell(5, 6, $i, '', 0, 'C', true);
      $this->setEmptyStyle(3);
      $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
      $this->Cell(25, 6, "", 'LRTB', 0, 'C', true);
      $this->Ln();
    }
  }

  function FinalScoreTable() {
    // header
    $this->setHeaderStyle(2);
    $this->Cell(80, 6, utf8_decode(_("Final score")), 'LRTB', 0, 'C', true);
    $this->Ln();
    
    // data
    $this->setPreFilledStyle(3);
    
    $this->fitCell(38, 6, $this->game['hometeamname'], 'LT', 0, 'C', true);
    $this->Cell(4, 6, "-", 'T', 0, 'C', true);
    $this->fitCell(38, 6, $this->game['visitorteamname'], 'RT', 0, 'C', true);
    
    $this->setEmptyStyle();
    $this->Ln();
    $this->Cell(80, 12, "", 'LRB', 0, 'C', true);
    $this->Ln();
  }

  function setHeaderStyle($size = 2) {
    if ($size == 1)
      $this->SetFont('Arial', 'B', 16);
    elseif ($size == 2)
      $this->SetFont('Arial', 'B', 12);
    elseif ($size == 3)
      $this->SetFont('Arial', 'B', 10);
    else
      $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(255);
    $this->SetFillColor(127, 127, 127);
  }

  function setEmptyStyle($size = 3, $family = NULL, $style = NULL) {
    if ($family == NULL)
      $family = 'Arial';
    if ($style == NULL)
      $style = 'B';
    if ($size == 1)
      $this->SetFont($family, $style, 14);
    elseif ($size == 2)
      $this->SetFont($family, $style, 12);
    elseif ($size == 3)
      $this->SetFont($family, $style, 10);
    else
      $this->SetFont($family, $style, 8);
    $this->SetTextColor(0);
    $this->SetFillColor(255);
  }

  function setPreFilledStyle($size = 3, $family = NULL, $style = NULL) {
    if ($family == NULL)
      $family = 'Arial';
    if ($style == NULL)
      $style = 'B';
    
    if ($size == 1)
      $this->SetFont($family, $style, 14);
    elseif ($size == 2)
      $this->SetFont($family, $style, 12);
    elseif ($size == 3)
      $this->SetFont($family, $style, 10);
    elseif ($size < 1)
      $this->SetFont($family, $style, 8 + $size);
    else
      $this->SetFont($family, $style, 8);
    $this->SetTextColor(0);
    $this->SetFillColor(211, 211, 211);
  }

  function setTeamFont($text, $x, $fontsize) {
    $this->SetFont('Arial', 'B', $fontsize);
  }

  function OneCellTable($header, $data, $mode = NULL) {
    // header
    $this->setHeaderStyle();
    $this->Cell(80, 6, $header, 'LRTB', 0, 'C', true);
    $this->Ln();
    
    // data
    if ($mode == "fixed")
      $this->setPreFilledStyle();
    elseif ($mode == "empty")
      $this->setEmptyStyle();
    elseif ($data == "")
      $this->setEmptyStyle();
    else
      $this->setPreFilledStyle();
    $this->Cell(80, 6, $data, 'LRTB', 0, 'C', true);
    $this->Ln();
  }

  function DoubleCellTable($header, $data) {
    // header
    $this->SetFont('Arial', 'B', 12);
    $this->setHeaderStyle();
    $this->Cell(80, 6, $header, 'LRTB', 0, 'C', true);
    $this->Ln();
    
    // data
    $this->SetFont('Arial', 'B', 12);
    $this->setPreFilledStyle();
    $this->Cell(80, 12, $data, 'LRTB', 0, 'C', true);
    $this->Ln();
  }

  function WriteHTML($html) {
    // HTML parser
    $html = str_replace("\n", ' ', $html);
    $a = preg_split('/<(.*)>/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($a as $i => $e) {
      if ($i % 2 == 0) {
        // Text
        if ($this->HREF)
          $this->PutLink($this->HREF, $e);
        else
          $this->Write(4, $e);
      } else {
        // Tag
        if ($e[0] == '/')
          $this->CloseTag(strtoupper(mb_substr($e, 1)));
        else {
          // Extract attributes
          $a2 = explode(' ', $e);
          $tag = strtoupper(array_shift($a2));
          $attr = array();
          foreach ($a2 as $v) {
            if (preg_match('/([^=]*)=["\']?([^"\']*)/', $v, $a3))
              $attr[strtoupper($a3[1])] = $a3[2];
          }
          $this->OpenTag($tag, $attr);
        }
      }
    }
  }

  function OpenTag($tag, $attr) {
    // Opening tag
    if ($tag == 'B' || $tag == 'I' || $tag == 'U')
      $this->SetStyle($tag, true);
    if ($tag == 'A')
      $this->HREF = $attr['HREF'];
    if ($tag == 'BR')
      $this->Ln(5);
  }

  function CloseTag($tag) {
    // Closing tag
    if ($tag == 'B' || $tag == 'I' || $tag == 'U')
      $this->SetStyle($tag, false);
    if ($tag == 'A')
      $this->HREF = '';
  }

  function SetStyle($tag, $enable) {
    // Modify style and select corresponding font
    $this->$tag += ($enable ? 1 : -1);
    $style = '';
    foreach (array('B', 'I', 'U') as $s) {
      if ($this->$s > 0)
        $style .= $s;
    }
    $this->SetFont('', $style);
  }

  function PutLink($URL, $txt) {
    // Put a hyperlink
    $this->SetTextColor(0, 0, 255);
    $this->SetStyle('U', true);
    $this->Write(4, $txt, $URL);
    $this->SetStyle('U', false);
    $this->SetTextColor(0);
  }
}
?>