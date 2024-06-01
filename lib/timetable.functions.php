<?php
include_once $include_prefix.'lib/configuration.functions.php';
include_once $include_prefix.'lib/game.functions.php';


function TournamentView($games, $grouping=true, &$lines=null, $detail_links = true) {
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();
  
  while($game = mysqli_fetch_assoc($games)){
    $ret .= "\n<!-- res:". $game['reservationgroup'] ." pool:". $game['pool']." date:".JustDate($game['starttime'])."-->\n";
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      if($grouping){
        $ret .= "<h2>".utf8entities(groupHeading($game['reservationgroup']))."</h2>\n";
        if ($lines!==null) $lines[] = array("type" => "h2", "text" => groupHeading($game['reservationgroup']));
      }
      $prevPlace="";
    }

    if(JustDate($game['starttime']) != $prevDate || $game['place_id'] != $prevPlace){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<h3>";
      $ret .= DefWeekDateFormat($game['starttime']);
      $ret .= " ";
      $link = "?view=reservationinfo&amp;reservation=".$game['reservation_id'];
      $ret .= "<a href='$link'>";
      $ret .= utf8entities($linktext=U_($game['placename']));
      $ret .= "</a>";
      $ret .= "</h3>\n";
      if ($lines!==null) $lines[] = array("type" => "h3", 
        "content" => array("text" => DefWeekDateFormat($game['starttime'])." ", "link" => array($link, $linktext)));
      $prevPool="";
    }

    if($game['pool'] != $prevPool){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table class='admintable wide'>\n";
      $isTableOpen = true;
      $ret .= SeriesAndPoolHeaders($game, $lines);
    }
    
    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false,true,true,false,false,true,$rss, $detail_links, $detail_links);
      if ($lines!==null) $lines[] = array("type" => "game", "game" => $game);
    }

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function groupHeading($group){
  if (!empty($group)) {
    return U_($group);
  } else {
    return _("Without grouping");
  }
}

function SeriesView($games, $date=true, $time=false, &$lines=null, $detail_links = true){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['series_id'] != $prevSeries
    || (empty($game['series_id']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      $ret .= "<h2>". utf8entities($text=U_($game['seriesname'])) ."</h2>\n";
      if ($lines!==null) $lines[] = array("type"=>"h2", "text" => $text);
    }

    if($game['pool'] != $prevPool){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table class='admintable wide'>\n";
      $isTableOpen = true;
      $ret .= PoolHeaders($game, $lines);
    }

    //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
    $ret .= GameRow($game, $date, $time, true, false, false, true, $rss, $detail_links, $detail_links);
    if ($lines!==null) $lines[] = array("type"=>"game", "game" => $game);

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['time']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function PlaceView($games, $grouping=true, &$lines=null, $detail_links = true){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      if($grouping){
        $ret .= "<h2>" . utf8entities($text=groupHeading($game['reservationgroup'])) . "</h2>\n";
        if ($lines!==null) $lines[] = array("type"=>"h2", "text" => $text);
      }
      $prevDate = "";
    }

    if(JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $text = DefWeekDateFormat($game['starttime']);
      $ret .= "<h3>$text</h3>\n";
      if ($lines!==null) $lines[] = array("type"=>"h3", "text" => $text);
    }

    if(((empty($game['place_id']) && $prevPlace != "none") || (!empty($game['place_id']) && $game['place_id'] != $prevPlace)) 
        || $game['fieldname'] != $prevField || JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table class='admintable wide'>\n";
      $isTableOpen = true;
      $ret .= PlaceHeaders($game, true, $lines);
    }

    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false, true, false, true, true, true,$rss, $detail_links, $detail_links);
      if ($lines!==null) $lines[] = array("type"=>"game", "game" => $game);
    }

    $prevTournament = $game['reservationgroup'];
    $prevPlace = empty($game['place_id'])?"none":$game['place_id'];
    $prevField = $game['fieldname'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function TimeView($games, $grouping=true, &$lines=null, $detail_links = true){
  $ret = "";
  $prevTournament = "";
  $prevTime = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['time'] != $prevTime) {
      if($isTableOpen){
        $ret .= "</table>\n";
        //$ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      $text = DefWeekDateFormat($game['time']) ." ". DefHourFormat($game['time']);
      $ret .= "<h3>$text</h3>\n";
      if ($lines!==null) $lines[] = array("type" => "h3", "text" => $text);
      $ret .= "<table class='admintable wide'>\n";
      $isTableOpen = true;
    }

    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false, false, true, true, true, true,$rss, $detail_links, $detail_links);
      if ($lines!==null) $lines[] = array("type" => "game", "game" => $game);
    }

    $prevTime = $game['time'];
    $prevTimezone = $game['timezone'];

  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  if ($prevTimezone) $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function ExtTournamentView($games){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $ret .= "<table width='95%'>";
  
  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><h1 class='pk_h1'>". utf8entities(U_($game['reservationgroup'])) ."</h1></td></tr>\n";
    }

    if($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField ||JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td style='width:100%'><table width='100%' class='pk_table'><tr><td class='pk_tournament_td1'>";
      $ret .= utf8entities(U_($game['placename'])) ." "._("Field")." ".utf8entities($game['fieldname'])."</td></tr></table></td></tr>\n";
      $ret .= "<tr><td><table width='100%' class='pk_table'>\n";
      $isTableOpen = true;
    }

    $ret .= "<tr><td style='width:10px' class='pk_tournament_td2'>". DefHourFormat($game['time']) ."</td>";
    if($game['hometeam'] && $game['visitorteam']){
      $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['hometeamname']) ."</td>
			<td style='width:5px' class='pk_tournament_td2'>-</td>
			<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['visitorteamname']) ."</td>";
      	
      if(GameHasStarted($game))
        $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>";
      else
       $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>". intval($game['homescore']) ."</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>". intval($game['visitorscore']) ."</td>";
    }else{
      $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['phometeamname']) ."</td>
			<td style='width:5px' class='pk_tournament_td2'>-</td>
			<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['pvisitorteamname']) ."</td>";
      $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>";
    }
    $ret .= "<td style='width:5px' class='pk_tournament_td2'></td>";
    $ret .= "<td style='width:50px' class='pk_tournament_td2'>". utf8entities($game['seriesname']) ."</td>";
    $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['poolname']) ."</td>";
    $ret .= "</tr>\n";
    	

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevField = $game['fieldname'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table></td></tr>\n";
  }
  $ret .= "</table>\n";
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function ExtGameView($games){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $ret .= "<table style='white-space: nowrap' width='95%'>";

  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><h1 class='pk_h1'>". utf8entities(U_($game['reservationgroup'])) ."</h1></td></tr>\n";
    }

    if($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField || JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><table width='100%' class='pk_table'>";
      $ret .= "<tr><th class='pk_teamgames_th' colspan='12'>";
      $ret .= DefWeekDateFormat($game['starttime']) ." ". utf8entities(U_($game['placename']))." "._("Field")." ".utf8entities($game['fieldname']);
      $ret .= "</th></tr>\n";
      $isTableOpen = true;
    }

    $ret .= "<tr><td style='width:15%' class='pk_teamgames_td'>". DefHourFormat($game['time']) ."</td>";
    if($game['hometeam'] && $game['visitorteam']){
      $ret .= "<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['hometeamname']) ."</td>
			<td style='width:3%' class='pk_teamgames_td'>-</td>
			<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['visitorteamname']) ."</td>";
      if(GameHasStarted($game)){
        $ret .= "<td style='text-align: center;width:4%' class='pk_teamgames_td'>?</td>
					<td style='text-align: center;width:2%' class='pk_teamgames_td'>-</td>
					<td style='text-align: center;width:4%' class='pk_teamgames_td'>?</td>";
      }else{
        $ret .= "<td style='text-align: center;width:4%' class='pk_teamgames_td'>". intval($game['homescore']) ."</td>
					<td style='text-align: center;width:2%' class='pk_teamgames_td'>-</td>
					<td style='text-align: center;width:4%' class='pk_teamgames_td'>". intval($game['visitorscore']) ."</td>";
      }
    }else{
      $ret .= "<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['phometeamname']) ."</td>
			<td style='width:3%' class='pk_teamgames_td'>-</td>
			<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['pvisitorteamname']) ."</td>";
    }
    $ret .= "</tr>\n";
    	

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevField = $game['fieldname'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table></td></tr>\n";
  }
  $ret .= "</table>\n";
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function PlaceHeaders($info, $field = false, &$lines=null) {
  $ret = "<tr>\n";
  $ret .= "<th align='left' colspan='13'>";
  if (empty($info['place_id'])) {
    $text = _("No field defined");
    $ret .= $text;
    if ($lines!==null) $lines[] = array("type"=>"th", "text" => $text);
  } else {
    $link = "?view=reservationinfo&amp;reservation=" . $info['reservation_id'];
    $ret .= "<a class='thlink' href='$link'>";
    $ret .= utf8entities($info['placename']);
    $ret .= "</a>";
    if ($field) {
      $field =  " " . _("Field") . " " . $info['fieldname'];
      $ret .= utf8entities($field);
    }
    if ($lines!==null) $lines[] = array("type"=>"th", "content" => array("link" => (array($link, $info['placename'].($field?$field:"")))));
  }
  $ret .= "</th>\n";
  $ret .= "</tr>\n";

  return $ret;
}

function PoolHeaders($info, &$lines=null){
  $ret = "<tr style='width:100%'>\n";
  $ret .= "<th align='left' colspan='13'>";
  $ret .= utf8entities($text=U_($info['poolname']));
  $ret .= "</th>\n";
  $ret .= "</tr>\n";
  if ($lines!==null) $lines[] = array("type"=>"th", "text" => $text);
  return $ret;
}

function SeriesAndPoolHeaders($info, &$lines=null){
  $ret = "<tr style='width:100%'>\n";
  $ret .= "<th align='left' colspan='12'>";
  $text = U_($info['seriesname']) . " " . U_($info['poolname']);
  $ret .= utf8entities($text);
  $ret .= "</th>\n";
  $ret .= "</tr>\n";
  if ($lines!==null) $lines[] = array("type" => "th", "text" => $text);
  return $ret;
}

function GameRow($game, $date = false, $time = true, $field = true, $series = false, $pool = false, $info = true,
  $rss = false, $media = true, $history = true, $extra = null, $gamename = true, $emphwinner = false) {
  $datew = 'max-width:60px';
  $timew = 'max-width:40px';
  $fieldw = 'max-width:60px';
  $teamw = 'max-width:120px';
  $againstmarkw = 'max-width:5px';
  $seriesw = 'max-width:80px';
  $poolw = 'max-width:130px';
  $scoresw = 'max-width:15px';
  $infow = 'max-width:80px';
  $gamenamew = 'max-width:50px';
  $mediaw='max-width:40px';
  
  $td = $game?"td":"th";

  $ret = "<tr style='width:100%'>\n";
  
  if($date){
    $date = $game?ShortDate($game['time']):_("Date");
    $ret .= "<$td style='$datew'><span>$date</span></$td>\n";
  }

  if($time){
    $time = $game?DefHourFormat($game['time']):_("Time");
    $ret .= "<$td style='$timew'><span>$time</span></$td>\n";
  }

  if($field){
    if (!$game) {
      $ret .= "<$td style='$fieldw'>" . _("Field") . "</$td>\n";
    } elseif (!empty($game['fieldname'])) {
      $ret .= "<td style='$fieldw'><span>" . _("Field") . " " . utf8entities($game['fieldname']) . "</span></td>\n";
    } else {
      $ret .= "<td style='$fieldw'></td>\n";
    }
  }

  $hbye = '';
  $vbye = '';
  if ($game) {
    if (isset($game['homevalid']) && $game['homevalid'] == 2) {
      $hbye = " byename";
    }
    if (isset($game['visitorvalid']) && $game['visitorvalid'] == 2) {
      $vbye = " byename";
    }
  }
  
  $hwinner = $vwinner = "";
  if ($emphwinner && $game && GameIsFinished($game)) {
    $hwinner =  $game ['homescore'] > $game ['visitorscore'] ? " winner" : "";
    $vwinner =  $game ['homescore'] < $game ['visitorscore'] ? " winner" : "";
  }
  
  if (!$game) {
    $ret .= "<$td style='$teamw'><span class='schedulingname'>". utf8entities(_("Home")) ."</span></$td>\n";
  }elseif($game['hometeam']){
    $ret .= "<td style='$teamw'><span class='homename$hbye$hwinner'>". utf8entities($game['hometeamname']) ."</span></td>\n";
  }else {
    $ret .= "<td style='$teamw'><span class='schedulingname'>". utf8entities(U_($game['phometeamname'])) ."</span></td>\n";
  }

  $ret .= "<td style='$againstmarkw'>-</td>\n";

  if (!$game) {
    $ret .= "<$td style='$teamw'><span class='schedulingname'>". utf8entities(_("Away")) ."</span></$td>\n";
  } elseif($game['visitorteam']){
    $ret .= "<td style='$teamw'><span class='visitorname$vbye$vwinner'>". utf8entities($game['visitorteamname']) ."</span></td>\n";
  }else{
    $ret .= "<td style='$teamw'><span class='schedulingname'>". utf8entities(U_($game['pvisitorteamname'])) ."</span></td>\n";
  }

  if($series){
    $series = $game?utf8entities(U_($game['seriesname'])):_("Division");
    $ret .= "<$td style='$seriesw'><span>$series</span></$td>\n";
  }

  if($pool){
    $pool = $game?utf8entities(U_($game['poolname'])):_("Pool");
    $ret .= "<$td style='$poolw'><span>$pool</span></$td>\n";
  }

  if (!$game) {
    $ret .= "<th colspan='3'><span>". _("Result") . "</span></th>\n";
  } elseif(!GameHasStarted($game))	{
    $ret .= "<td style='$scoresw'><span>?</span></td>\n";
    $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
    $ret .= "<td style='$scoresw'><span>?</span></td>\n";
  }else{
    if ($game ['isongoing']) {
      $ret .= "<td style='$scoresw'><span><em>" . intval ( $game ['homescore'] ) . "</em></span></td>\n";
      $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
      $ret .= "<td style='$scoresw'><span><em>" . intval ( $game ['visitorscore'] ) . "</em></span></td>\n";
    } else {
      $ret .= "<td style='$scoresw'><span>" . intval ( $game ['homescore'] ) . "</span></td>\n";
      $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
      $ret .= "<td style='$scoresw'><span>" . intval ( $game ['visitorscore'] ) . "</span></td>\n";
    }
  }

  if ($gamename) {
    if ($game && $game['gamename']) {
      $ret .= "<td style='$gamenamew'><span>" . utf8entities(U_($game['gamename'])) . "</span></td>\n";
    } else {
      $ret .= "<$td style='$gamenamew'></$td>\n";
    }
  }

  if ($media) {
    if ($game) {
      $urls = GetMediaUrlList("game", $game['game_id'], "live");
      $ret .= "<td style='$mediaw;white-space: nowrap;'>";
      if (count($urls) && (intval($game['isongoing']) || !GameHasStarted($game))) {
        foreach ($urls as $url) {
          $title = $url['name'];
          if (empty($title)) {
            $title = _("Live Broadcasting");
          }
          $ret .= "<a href='" . $url['url'] . "'>" . "<img border='0' width='16' height='16' title='" .
            utf8entities($title) . "' src='images/linkicons/" . $url['type'] . ".png' alt='" . $url['type'] . "'/></a>";
        }
      }
      $ret .= "</td>\n";
    } else {
      $ret .= "<th></th>\n";
    }
  }

  if($info){
    if ($game) {
    if(!GameHasStarted($game)){
      if($history && $game['hometeam'] && $game['visitorteam']){
        $t1 = preg_replace('/\s*/m','',$game['hometeamname']);
        $t2 = preg_replace('/\s*/m','',$game['visitorteamname']);

        $xgames = GetAllPlayedGames($t1,$t2, $game['type'], "");
        if(mysqli_num_rows($xgames)>0){
          $ret .= "<td class='right' style='$infow'><span style='white-space: nowrap'>";
          $ret .= "<a href='?view=gamecard&amp;team1=". utf8entities($game['hometeam']) ."&amp;team2=". utf8entities($game['visitorteam']) . "'>";
          $ret .=  _("Game history")."</a></span></td>\n";
        }else{
          $ret .= "<td class='left' style='$infow'></td>\n";
        }
      }else{
        $ret .= "<td class='left' style='$infow'></td>\n";
      }
    }else{
      if(!intval($game['isongoing'])){
        if(isset($game['scoresheet']) && intval($game['scoresheet'])){
          $ret .= "<td class='right' style='$infow'><span>&nbsp;<a href='?view=gameplay&amp;game=". $game['game_id'] ."'>";
          $ret .= _("Game play") ."</a></span></td>\n";
        }else{
          $ret .= "<td class='left' style='$infow'></td>\n";
        }
      }else{
        if(isset($game['scoresheet']) && intval($game['scoresheet'])){
          $ret .= "<td class='right' style='$infow'><span>&nbsp;&nbsp;<a href='?view=gameplay&amp;game=". $game['game_id'] ."'>";
          $ret .= _("Ongoing") ."</a></span></td>\n";
        }else{
          $ret .= "<td class='right' style='$infow'>&nbsp;&nbsp;"._("Ongoing")."</td>\n";
        }
        	
      }
    }
    if($rss){
      $ret .= "<td class='feed-list'><a style='color: #ffffff;' href='ext/rss.php?feed=game&amp;id1=".$game['game_id']."'>";
      $ret .= "<img src='images/feed-icon-14x14.png' width='10' height='10' alt='RSS'/></a></td>";
    }
    } else {
      $ret .= "<th></th>";
      if ($rss) $ret .= "<th></th>";
    }
  }
  
  if ($extra!==null) {
    $ret .= "<$td class='extra-game-column'>$extra</$td>";
  }
  
  $ret .=  "</tr>\n";
  return $ret;
}

function PrintTimeZone($timezone){
  $ret = "<p class='timezone'>"._("Timezone").": ".utf8entities($timezone).". ";
  if(class_exists("DateTime") && !empty($timezone)){
    $dateTime = new DateTime("now", new DateTimeZone($timezone));
    $ret .= _("Local time").": ".DefTimeFormat($dateTime->format("Y-m-d H:i:s"));
  }
  $ret .= "</p>";
  return $ret;
}

function NextGameDay($id, $gamefilter, $order){
  $games = TimetableGames($id, $gamefilter, "coming", "time");
  $game = mysqli_fetch_assoc($games);
  $next = ShortEnDate($game['time']);
  $games = TimetableGames($id, $gamefilter, $next, $order);
  return $games;
}

function PrevGameDay($id, $gamefilter, $order){
  $games = TimetableGames($id, $gamefilter, "past", "timedesc");
  $game = mysqli_fetch_assoc($games);
  $prev = ShortEnDate($game['time']);
  $games = TimetableGames($id, $gamefilter, $prev, $order);
  return $games;
}


function TimetableGames($id, $gamefilter, $timefilter, $order, $groupfilter="", $valid = false){
  //common game query
  $query = "SELECT pp.game_id, pp.time, pp.hometeam, pp.visitorteam, pp.homescore,
			pp.visitorscore, pp.pool AS pool, pool.name AS poolname, pool.timeslot,
			ps.series_id, ps.name AS seriesname, ps.season, ps.type, pr.fieldname, pr.reservationgroup,
			pr.id AS reservation_id, pr.starttime, pr.endtime, pl.id AS place_id, COALESCE(pm.goals,0) AS scoresheet,
			pl.name AS placename, pl.address, pp.isongoing, pp.hasstarted, home.name AS hometeamname, visitor.name AS visitorteamname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename,
			home.abbreviation AS homeshortname, visitor.abbreviation AS visitorshortname, homec.country_id AS homecountryid, 
			homec.name AS homecountry, visitorc.country_id AS visitorcountryid, visitorc.name AS visitorcountry, 
			homec.flagfile AS homeflag, visitorc.flagfile AS visitorflag, s.timezone, home.valid as homevalid, visitor.valid as visitorvalid
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_season s ON (s.season_id=ps.season)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team_pool AS homepool ON (pp.hometeam=homepool.team AND pp.pool=homepool.pool)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_country AS homec ON (homec.country_id=home.country)
			LEFT JOIN uo_country AS visitorc ON (visitorc.country_id=visitor.country)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)";

  switch($gamefilter)
  {
    case "season":
      $query .= " WHERE pp.valid=true AND ps.season='".mysql_adapt_real_escape_string($id)."'";
      break;

    case "series":
      $query .= " WHERE pp.valid=true AND ps.series_id='".(int)$id."'";
      break;

    case "pool":
      $query .= " WHERE pp.valid=true AND pp.pool='".(int)$id."'";
      break;

    case "poolgroup":
      //keep pool filter as it is to give better performance for single pool query
      //extra explode needed to make parameters safe
      $pools = explode(",", mysql_adapt_real_escape_string($id));
      $query .= " WHERE pp.valid=true AND pp.pool IN(".implode(",",$pools).")";
      break;
      	
    case "team":
      $query .= " WHERE pp.valid=true AND (pp.visitorteam='".(int)$id."' OR pp.hometeam='".(int)$id."')";
      break;

    case "game":
      $query .= " WHERE pp.game_id=".(int)$id;
      break;
  }

  switch($timefilter)
  {
    case "coming":
      $query .= " AND ((pp.homescore IS NULL AND pp.visitorscore IS NULL) OR (pp.hasstarted=0) OR pp.isongoing=1)";
      break;

    case "past":
    case "played":
      $query .= " AND ((pp.hasstarted > 0) )";
      break;
      	
    case "ongoing":
      $query .= " AND pp.isongoing=1";
      break;
      	
    case "comingNotToday":
      $query .= " AND pp.time >= Now()";
      break;

    case "pastNotToday":
      $query .= " AND pp.time <= Now()";
      break;
      	
    case "today":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 0 DAY)";
      break;

    case "tomorrow":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;
      	
    case "yesterday":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;

    case "all":
      break;
      	
    default:
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = '".mysql_adapt_real_escape_string($timefilter)."'";
      break;
  }
  
  if (!empty($groupfilter)) {
    if ($groupfilter == "none") {
      $query .= " AND pr.reservationgroup IS NULL";
    } else if ($groupfilter != "all") {
      $query .= " AND pr.reservationgroup='" . mysql_adapt_real_escape_string($groupfilter) . "'";
    }
  }
  
  if ($valid) {
    $query .= " AND ps.valid = true";
  }

  switch($order)
  {
    case "tournaments":
      $query .= " ORDER BY pr.starttime, pr.reservationgroup, pl.id, ps.ordering, pool.ordering, pp.time ASC, pr.fieldname + 0, pp.game_id ASC";
      break;

    case "series":
      $query .= " ORDER BY ps.ordering, pool.ordering, pp.time ASC, pr.starttime, pr.fieldname + 0, pp.game_id ASC";
      break;

    case "places":
      $query .= " ORDER BY pr.starttime, pr.reservationgroup, pl.id, pr.fieldname +0,  pp.time ASC, pp.game_id ASC";
      break;

    case "tournamentsdesc":
      $query .= " ORDER BY pr.starttime DESC, pr.reservationgroup, pl.id, ps.ordering, pool.ordering, pp.time ASC, pp.game_id ASC";
      break;

    case "placesdesc":
      $query .= " ORDER BY pr.starttime DESC, pr.reservationgroup, pl.id, pr.fieldname + 0, pp.time ASC, pp.game_id ASC";
      break;
      	
    case "onepage":
      $query .= " ORDER BY pr.reservationgroup, pr.starttime, pl.id, pr.fieldname +0, pp.time ASC, pp.game_id ASC";
      break;

    case "time":
    case "timeslot":
      $query .= " ORDER BY pp.time ASC, pr.fieldname +0, game_id ASC";
      break;
      	
    case "timedesc":
      $query .= " ORDER BY pp.time DESC, game_id ASC";
      break;
      	
    case "crossmatch":
      $query .= " ORDER BY homepool.`rank` ASC, game_id ASC";
      break;
  }

  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }

  return $result;
}

function TimetableGrouping($id, $gamefilter, $timefilter)
{
  //common game query 
  $query = "SELECT pr.reservationgroup
			FROM uo_game pp 
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool)
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)";

  switch($gamefilter)
  {
    case "season":
      $query .= " WHERE pp.valid=true AND ps.season='".mysql_adapt_real_escape_string($id)."'";
      break;

    case "series":
      $query .= " WHERE pp.valid=true AND ps.series_id='".(int)$id."'";
      break;
      
    case "seriesgroup":
      $series = explode(",", mysql_adapt_real_escape_string($id));
      $query .= " WHERE pp.valid=true AND ps.series_id IN(". implode(",", $series).")";
      break;
      
    case "pool":
      $query .= " WHERE pp.valid=true AND pp.pool='".(int)$id."'";
      break;

    case "poolgroup":
      //keep pool filter as it is to give better performance for single pool query
      //extra explode needed to make parameters safe
      $pools = explode(",", mysql_adapt_real_escape_string($id));
      $query .= " WHERE pp.valid=true AND pp.pool IN(".implode(",",$pools).")";
      break;
      	
    case "team":
      $query .= " WHERE pp.valid=true AND (pp.visitorteam='".(int)$id."' OR pp.hometeam='".(int)$id."')";
      break;
  }

  switch($timefilter)
  {
    case "coming":
      $query .= " AND pp.time IS NOT NULL AND ((pp.homescore IS NULL AND pp.visitorscore IS NULL) OR (pp.hasstarted = 0) OR pp.isongoing=1)";
      break;

    case "past":
      $query .= " AND ((pp.hasstarted >0))";
      break;
      	
    case "played":
      $query .= " AND ((pp.hasstarted >0))";
      break;
      	
    case "ongoing":
      $query .= " AND pp.isongoing=1";
      break;
      	
    case "comingNotToday":
      $query .= " AND pp.time >= Now()";
      break;

    case "pastNotToday":
      $query .= " AND pp.time <= Now()";
      break;
      	
    case "today":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 0 DAY)";
      break;

    case "tomorrow":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;
      	
    case "yesterday":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;

    case "all":
      break;
      	
    default:
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = '".mysql_adapt_real_escape_string($timefilter)."'";
      break;
  }
  $query .= " GROUP BY pr.reservationgroup ORDER BY MIN(ps.ordering) ASC, MIN(pp.time) ASC, pr.reservationgroup";

  return DBQueryToArray($query);
}

function TimetableFields($reservationgroup, $season){
  $query = "SELECT COUNT(*) as games
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)";

  $query .= " WHERE pp.valid=true AND ps.season='".mysql_adapt_real_escape_string($season)."' AND pr.reservationgroup='".mysql_adapt_real_escape_string($reservationgroup)."'";
  $query .= " GROUP BY pr.location, pr.fieldname";
  $result = DBQuery($query);
  return mysqli_num_rows($result);
}

function TimetableTimeslots($reservationgroup, $season){
  $query = "SELECT pp.time
			FROM uo_game pp 
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)";

  $query .= " WHERE pp.valid=true AND ps.season='".mysql_adapt_real_escape_string($season)."' AND pr.reservationgroup='".mysql_adapt_real_escape_string($reservationgroup)."'";
  $query .= " GROUP BY pp.time";
  return DBQueryToArray($query);
}

function TimetableIntraPoolConflicts($season, $series=null) {
  $query = sprintf("SELECT g1.game_id as game1, g2.game_id as game2, g1.pool as pool1, g2.pool as pool2,  
      g1.hometeam as home1, g1.visitorteam as visitor1, g2.hometeam as home2, g2.visitorteam as visitor2, 
      g1.scheduling_name_home as scheduling_home1, g1.scheduling_name_visitor as scheduling_visitor1, 
      g2.scheduling_name_home as scheduling_home2, g2.scheduling_name_visitor as scheduling_visitor2, 
      g1.reservation as reservation1, g2.reservation as reservation2, g1.time as time1, g2.time as time2, 
      p1.timeslot as slot1, p2.timeslot as slot2, 
      res1.location location1, res1.fieldname as field1, res2.location as location2, res2.fieldname as field2  
      FROM uo_game as g1
      LEFT JOIN uo_game as g2 ON ((g1.hometeam=g2.hometeam OR g1.visitorteam = g2.visitorteam OR g1.hometeam=g2.visitorteam OR g1.visitorteam = g2.hometeam) AND g1.game_id != g2.game_id )
      LEFT JOIN uo_pool as p1 ON (p1.pool_id = g1.pool)
      LEFT JOIN uo_pool as p2 ON (p2.pool_id = g2.pool)
      LEFT JOIN uo_reservation as res1 ON (res1.id = g1.reservation)
      LEFT JOIN uo_reservation as res2 ON (res2.id = g2.reservation)
      LEFT JOIN uo_series as ser1 ON (ser1.series_id = p1.series)
      LEFT JOIN uo_series as ser2 ON (ser2.series_id = p2.series)
      WHERE g1.reservation IS NOT NULL AND g2.reservation IS NOT NULL 
      AND ser1.season = '%s' AND ser2.season = '%s'" . 
      (($series != null) ? " AND ser1.series_id = %d AND ser2.series_id = %d " : "") .
      " AND g1.time <= g2.time
      ORDER BY time2 ASC, time1 ASC",
      mysql_adapt_real_escape_string($season), mysql_adapt_real_escape_string($season),
      (int) $series, (int) $series);
  return DBQueryToArray($query);
}

function TimetableInterPoolConflicts($season, $series=null) {
  $query = sprintf("SELECT  g1.game_id as game1, g2.game_id as game2, g1.pool as pool1, g2.pool as pool2,  
      g1.hometeam as home1, g1.visitorteam as visitor1, g2.hometeam as home2, g2.visitorteam as visitor2, 
      g1.scheduling_name_home as scheduling_home1, g1.scheduling_name_visitor as scheduling_visitor1, 
      g2.scheduling_name_home as scheduling_home2, g2.scheduling_name_visitor as scheduling_visitor2, 
      g1.reservation as reservation1, g2.reservation as reservation2, g1.time as time1, g2.time as time2, 
      p1.timeslot as slot1, p2.timeslot as slot2, 
      res1.location location1, res1.fieldname as field1, res2.location as location2, res2.fieldname as field2
      FROM uo_moveteams as mv
      LEFT JOIN uo_game as g1 ON (g1.pool = mv.frompool)
      LEFT JOIN uo_game as g2 ON (g2.pool = mv.topool AND g1.game_id != g2.game_id )
      LEFT JOIN uo_pool as p1 ON (p1.pool_id = g1.pool)
      LEFT JOIN uo_pool as p2 ON (p2.pool_id = g2.pool)
      LEFT JOIN uo_reservation as res1 ON (res1.id = g1.reservation)
      LEFT JOIN uo_reservation as res2 ON (res2.id = g2.reservation)
      LEFT JOIN uo_series as ser1 ON (ser1.series_id = p1.series)
      LEFT JOIN uo_series as ser2 ON (ser2.series_id = p2.series)
      WHERE ser1.season = '%s' AND ser2.season = '%s'" .
      (($series != null) ? " AND ser1.series_id = %d AND ser2.series_id = %d " : "") .
      " AND (g1.hometeam IS NULL OR g1.visitorteam IS NULL OR g2.hometeam IS NULL OR g2.visitorteam IS NULL OR
          (g1.hometeam=g2.hometeam OR g1.visitorteam = g2.visitorteam OR g1.hometeam=g2.visitorteam OR g1.visitorteam = g2.hometeam))
      ORDER BY time2 ASC, time1 ASC",
      mysql_adapt_real_escape_string($season), mysql_adapt_real_escape_string($season),
      (int) $series, (int) $series);
  return DBQueryToArray($query);
}

function TimeTableMoveTimes($season) {
  $query = sprintf("SELECT * FROM uo_movingtime
            WHERE season = '%s'
            ORDER BY fromlocation, fromfield+0, tolocation, tofield+0", $season);
  
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	$ret = array();
	while ($row = mysqli_fetch_assoc($result)){
		$ret[$row['fromlocation']][$row['fromfield']][$row['tolocation']][$row['tofield']] = $row['time'];
	}
	return $ret;
  }

function TimeTableMoveTime($movetimes, $location1, $field1, $location2, $field2) {
  if (!isset($movetimes[$location1][$field1][$location2][$field2]))
    return 0;
  $time = $movetimes[$location1][$field1][$location2][$field2];
  if (empty($time))
    return 0;
  else
    return $time * 60;
}

function TimeTableSetMoveTimes($season, $times) {
  if (isSuperAdmin() || isSeasonAdmin($season)) {
    for ($from = 0; $from < count($times); $from++) {
      for ($to = 0; $to < count($times); $to++) {
        if ((int) $times[$from][$to] == 0) {
          $query = sprintf(
            "DELETE FROM `uo_movingtime` WHERE 
            `season`='%s' AND `fromlocation`=%d AND `fromfield`=%d AND `tolocation`=%d AND `tofield`=%d",
            mysql_adapt_real_escape_string($season), (int) $times[$from]['location'], (int) $times[$from]['field'],
            (int) $times[$to]['location'], (int) $times[$to]['field']);
          DBQuery($query);
        } else {
          $query = sprintf(
            "INSERT INTO `uo_movingtime`
            (`season`, `fromlocation`, `fromfield`, `tolocation`, `tofield`, `time`) 
            VALUES ('%s', %d, %d, %d, %d, %d) ON DUPLICATE KEY UPDATE `time`=%d", mysql_adapt_real_escape_string($season),
            (int) $times[$from]['location'], (int) $times[$from]['field'], (int) $times[$to]['location'],
            (int) $times[$to]['field'], (int) $times[$from][$to], (int) $times[$from][$to]);
          DBQuery($query);
        }
      }
    }
  } else {
    die('Insufficient rights to edit moving times');
  }
}

function IsGamesScheduled($id, $gamefilter, $timefilter)
{
  $result = TimetableGames($id, $gamefilter, $timefilter, "");

  return (mysqli_num_rows($result)>0);
}

function TimetableToCsv($season,$separator){

  $query = sprintf("SELECT pp.time AS Time, phome.name AS HomeSchedulingName, pvisitor.name AS AwaySchedulingName,
			home.name AS HomeTeam, visitor.name AS AwayTeam, pp.homescore AS HomeScores, 
			pp.visitorscore AS VisitorScores, pool.name AS Pool, ps.name AS Division, 
			pr.fieldname AS Field, pr.reservationgroup AS ReservationGroup,
			pl.name AS Place, pp.name AS GameName
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			WHERE pp.valid=true AND ps.season='%s'
			ORDER BY pr.starttime, pr.reservationgroup, pl.id, pr.fieldname +0, pp.time ASC, pp.game_id ASC",
  mysql_adapt_real_escape_string($season));

  // Gets the data from the database
  $result = DBQuery($query);
  return ResultsetToCsv($result, $separator);
}

?>
