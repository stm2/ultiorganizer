<?php
/**
 * @file
 * This file contains Event Data XML Handler data handling functions for xml<->sql.
 *
 */

/**
 * @class EventDataXMLHandler
 *
 */
class EventDataXMLHandler{
  var $eventId; //event id under processing
  var $uo_season=array(); //event id mapping array
  var $uo_series=array(); //series id mapping array
  var $uo_team=array(); //team id mapping array
  var $uo_scheduling_name=array(); //scheduling id mapping array
  var $uo_pool=array(); //pool id mapping array
  var $uo_player=array(); //player id mapping array
  var $uo_game=array(); //game id mapping array
  var $uo_reservation=array(); //reservation id mapping array
  var $followers = array(); // pools with unresolved followers
  var $mode; //import mode: 'add' or 'replace'
  var $mock; // should we do a dry run? 
  var $debug; // logging
  var $structure; // document structure
  var $structure_seriesId; // current series in structure parse
  
  const NULL_MARKER = "%NULL";
  
  /**
   * Default construction
   */
  function __construct(){}

  // FIXME include defense, gameevent, played (?)
  /**
   * Converts element data into xml-format.
   *
   * xml-structure
   * <uo_season>
   *  <uo_reservation></uo_reservation>
   *  <uo_movingtime></uo_movingtime>
   *  <uo_series>
   *    <uo_team>
   *      <uo_player></uo_player>
   *    </uo_team>
   *    <uo_scheduling_name></uo_scheduling_name>
   *    <uo_pool>
   *	    <uo_team_pool></uo_team_pool>
   *      <uo_game>
   *        <uo_goal></uo_goal>
   *        <uo_gameevent></uo_gameevent>
   *        <uo_played></uo_played>
   *      </uo_game>
   *    </uo_pool>
   *    <uo_game_pool></uo_game_pool>
   *    <uo_moveteams></uo_moveteams>
   *  </uo_series>
   *</uo_season>
   *
   * @param string $eventId Event to conver into xml-format.
   * @return string event data in xml
   */

  function selectSeries($eventId, $series) {
    $query = sprintf("SELECT * FROM uo_series WHERE season='%s'",
      mysql_adapt_real_escape_string($eventId));
    if (!empty($series)) {
      $query .= "AND series_id IN (";
      $num = 0;
      foreach ($series as $num => $seriesId) {
        if ($num> 0)
          $query .= ", ";
          $query .= $seriesId;
      }
      $query .= ")";
    }
    
    return DBQuery($query);
  }
  
  
  function write_season($eventId, $template) {
    $seasons = DBQuery("SELECT * FROM uo_season WHERE season_id='".mysql_adapt_real_escape_string($eventId)."'");
    $row = mysqli_fetch_assoc($seasons);
    
    if($template) {
      $row["iscurrent"] = 0;
    }
        
    return $this->RowToXML("uo_season", $row, false);
  }
  
  function write_reservations($eventId, $template) {
    $ret = "";
    $reservations = DBQuery("SELECT * FROM uo_reservation WHERE season='".mysql_adapt_real_escape_string($eventId)."'");
    while($reservation = mysqli_fetch_assoc($reservations)){
      $ret .= $this->RowToXML("uo_reservation", $reservation);
    }
    
    $times = DBQuery("SELECT * FROM uo_movingtime WHERE season='".mysql_adapt_real_escape_string($eventId)."'");
    while($time = mysqli_fetch_assoc($times)){
      $ret .= $this->RowToXML("uo_movingtime", $time);
    }
    return $ret;
  }

  function write_series($ser, $template) {
    $ret = "";
    if ($template) {
      $ser["valid"] = 0;
    }
    
    $ret .= $this->RowToXML("uo_series", $ser, false);
    
    $seriesId = (int)$ser['series_id'];
    
    //uo_team
    $teams = DBQuery("SELECT * FROM uo_team WHERE series='$seriesId' ORDER BY rank");
    while($team = mysqli_fetch_assoc($teams)){
      if ($template) {
        $team['activerank'] = null;
      }
      $ret .= $this->RowToXML("uo_team", $team, false);
      
      //uo_player
      $players = DBQuery("SELECT * FROM uo_player WHERE team='".mysql_adapt_real_escape_string($team['team_id'])."'");
      while($player = mysqli_fetch_assoc($players)){
        $ret .= $this->RowToXML("uo_player", $player);
      }
      $ret .= "</uo_team>\n";
    }
    
    //uo_scheduling_name, referenced by either games or moves
    $schedulings =
    //         DBQuery("SELECT sched.* FROM uo_scheduling_name sched
      //             LEFT JOIN uo_game game ON (sched.scheduling_id = game.scheduling_name_home OR sched.scheduling_id = game.scheduling_name_visitor)
      //             LEFT JOIN uo_pool pool ON (game.pool = pool.pool_id)
      //             LEFT JOIN uo_moveteams mv ON (sched.scheduling_id = mv.scheduling_id)
      //             LEFT JOIN uo_pool pool2 ON (mv.frompool = pool2.pool_id OR mv.topool = pool2.pool_id)
      //             WHERE pool2.series = $seriesId  OR pool.series = $seriesId
      //             GROUP BY scheduling_id");
    
    // this is faster
    DBQuery("SELECT scheduling_id, MIN(name) as name FROM (
                (SELECT sched.*
                    FROM uo_scheduling_name sched
                    LEFT JOIN uo_game game ON (sched.scheduling_id = game.scheduling_name_home OR sched.scheduling_id = game.scheduling_name_visitor)
                    LEFT JOIN uo_pool pool ON (game.pool = pool.pool_id)
                    WHERE pool.series = $seriesId)
                UNION
                (SELECT sched.*
                    FROM uo_scheduling_name sched
                    LEFT JOIN uo_moveteams mv ON (sched.scheduling_id = mv.scheduling_id)
                    LEFT JOIN uo_pool pool ON (mv.frompool = pool.pool_id OR mv.topool = pool.pool_id)
                    WHERE pool.series = $seriesId )
                ) as unioned
                GROUP BY scheduling_id");
    
    while ($row = mysqli_fetch_assoc($schedulings)) {
      $ret .= $this->RowToXML("uo_scheduling_name", $row);
    }
    
    //uo_pool
    $ret .= $this->write_pools($seriesId, $template);
    
    //uo_moveteams
    $ret .= $this->write_moveteams($seriesId, $template);
    
    //uo_game_pool
    $ret .= $this->write_gamepools($seriesId, $template);
    $ret .= "</uo_series>\n";
    return $ret;
  }
  
  function write_pools($seriesId, $template) {
    $ret = "";
    $pools = DBQuery("SELECT * FROM uo_pool WHERE series='$seriesId'");
    while($poolRow = mysqli_fetch_assoc($pools)){
      if ($template) {
        $poolRow['played'] = 0;
      }
      
      $ret .= $this->RowToXML("uo_pool", $poolRow, false);

      if (!$template || $poolRow['continuingpool'] == 0) {
        //uo_team_pool
        $teampools = DBQuery("SELECT * FROM uo_team_pool WHERE pool='".mysql_adapt_real_escape_string($poolRow['pool_id'])."'");
        while($teampool = mysqli_fetch_assoc($teampools)){
          $ret .= $this->RowToXML("uo_team_pool", $teampool);
        }
      }
      
      //uo_game
      $ret .= $this->write_games($poolRow['pool_id'], $template);
      $ret .= "</uo_pool>\n";
    }
    return $ret;
  }

  function write_games($poolId, $template) {
    $ret = "";
    $games = DBQuery("SELECT * FROM uo_game WHERE pool='".mysql_adapt_real_escape_string($poolId)."'");
    while($gameRow = mysqli_fetch_assoc($games)){
      if($template) {
        $gameRow['hometeam'] = NULL;
        $gameRow['visitorteam'] = NULL;
        $gameRow['homescore'] = NULL;
        $gameRow['visitorscore'] = NULL;
        $gameRow['homedefenses'] = 0;
        $gameRow['visitordefenses'] = 0;
        $gameRow['hasstarted'] = 0;
        $gameRow['isongoing'] = 0;
      }
      
      $ret .= $this->RowToXML("uo_game", $gameRow, false);
      $gameId = mysql_adapt_real_escape_string($gameRow['game_id']);

      if (!$template) {
        //uo_goal
        $goals = DBQuery("SELECT * FROM uo_goal WHERE game='". $gameId ."'");
        while($goal = mysqli_fetch_assoc($goals)){
          $ret .= $this->RowToXML("uo_goal", $goal);
        }
        //uo_gameevent
        $gameevents = DBQuery("SELECT * FROM uo_gameevent WHERE game='".$gameId."'");
        while($gameevent = mysqli_fetch_assoc($gameevents)){
          $ret .= $this->RowToXML("uo_gameevent", $gameevent);
        }
        //uo_played
        $playedplayers = DBQuery("SELECT * FROM uo_played WHERE game='".$gameId."'");
        while($playedplayer = mysqli_fetch_assoc($playedplayers)){
          $ret .= $this->RowToXML("uo_played", $playedplayer);
        }
      }
      $ret .= "</uo_game>\n";
    }
    return $ret;
  }
  
  function write_moveteams($seriesId, $template) {
    $ret = "";
    $moveteams = DBQuery("SELECT m.* FROM uo_moveteams m
				LEFT JOIN uo_pool p ON(m.frompool=p.pool_id)
				WHERE p.series='$seriesId'");
    while($moveteam = mysqli_fetch_assoc($moveteams)) {
      if ($template) {
        $moveteam['ismoved'] = 0;
      }
      $ret .= $this->RowToXML("uo_moveteams", $moveteam);
    }
    return $ret;    
  }
  
  function write_gamepools($seriesId, $template) {
    $ret = "";
    $gamepools = DBQuery("SELECT g.* FROM uo_game_pool g
				LEFT JOIN uo_pool p ON(g.pool=p.pool_id)
				WHERE p.series='$seriesId'");
    while($gamepool = mysqli_fetch_assoc($gamepools)){
      if (!$template || $gamepool['timetable'] == 1) {
        $ret .= $this->RowToXML("uo_game_pool", $gamepool);
      }
    }
    return $ret;
  }
  
  function EventToXML($eventId, $series = null, $template = false){
     
    if (isSeasonAdmin($eventId)) {
      $ret = "";
      $ret .= "<?xml version='1.0' encoding='UTF-8'?>\n";
      //uo_season
      $ret .= $this->write_season($eventId, $template);

      //uo_reservation
      //uo_movingtime
      $ret .= $this->write_reservations($eventId, $template);

      //uo_series
      $seriesResult = $this->selectSeries($eventId, $series);
      while($ser = mysqli_fetch_assoc($seriesResult)){
        $ret .= $this->write_series($ser, $template); 
      }

      $ret .= "</uo_season>\n";

      return $ret;
    } else { die('Insufficient rights to export data'); }
  }

  /**
   * Converts database row to xml.
   * @param string $elementName - name of xml-element (table name)
   * @param array $row - element attributes (table row) 
   * @param boolean $endtag - true if element closed
   *
   * @return string XML-data
   */
  function RowToXML($elementName, $row, $endtag=true){
    $columns = array_keys($row);
    $values = array_values($row);
    $total = count($row);
    $ret = "<".$elementName." ";
     
    for ($i=0; $i < $total; $i++) {
      $ret .= $this->do_attribute($columns[$i], $values[$i]);
    }

    if($endtag){
      $ret .= "/>\n";
    }else{
      $ret .= ">\n";
    }

    return $ret;
  }
  
  function do_attribute($name, $value) {
    if($value === NULL) {
      $value = self::NULL_MARKER;
    } else if (is_string($value)) {
      if (substr($value, 0, 1) === '%') {
        $value = "%" . $value;
      }
    }
    if ($name === htmlspecialchars($name)) {
      return $name . "='" . htmlspecialchars($value, ENT_QUOTES,"UTF-8") . "' ";
    } else {
      die ('invalid attribute name "' . $name .'"');
    }
  }
  
  function get_attribute(&$row, $name, $value) {
    if ($value === self::NULL_MARKER) {
      $row[strtolower($name)] = NULL;
    } else if (substr($value, 0, 2) === '%%') {
      $row[strtolower($name)] = substr($value, 1, strlen($value)-1);
    } else {
      $row[strtolower($name)] = $value;
    }
  }

  function XMLGetSeason($filename) {
    $reader = new XMLReader();
    $reader->open($filename);
    $reader->read();
    
    $content = array();
    if($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== "uo_season") {
      $content['error'] = _("Invalid XML file (no 'uo_season' element).");
      return $content;
    }
    $content['season_id'] = $reader->getAttribute("season_id");
    $content['name'] = $reader->getAttribute("name");
    
    while ($reader->read() && $reader->name !== "uo_series") {
      ;
    }
    if ($reader->name == "uo_series") {
      $content['series'] = array();
      do {
        if ($reader->name === "uo_series")
          $content['series'][$reader->getAttribute('series_id')] = $reader->getAttribute('name');   
      } while($reader->next());
    }
    $reader->close();
    return $content;
  }
  
  function XMLStructure($filename) {
    //create parser and set callback functions
    $xmlparser = xml_parser_create();
    xml_set_element_handler($xmlparser, array($this, "start_tag_structure"), array($this, "end_tag_structure"));
    if (!($fp = fopen($filename, "r"))) { die("cannot open ".$filename); }

    $this->structure = array();
    $this->structure['error'] = "";
    
    //remove extra spaces
    while ($data = fread($fp, 4096)){
      // $data=eregi_replace(">"."[[:space:]]+"."< ",">< ",$data);
      if (!xml_parse($xmlparser, $data, feof($fp))) {
        $reason = xml_error_string(xml_get_error_code($xmlparser));
        $reason .= xml_get_current_line_number($xmlparser);
        die($reason);
      }
    }
    xml_parser_free($xmlparser);
    return $this->structure;
  }
  
  /**
   * Callback function for element start.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   * @param array $attribs element's attributes
   */
  function start_tag_structure($parser, $name, $attribs) {
    if (is_array($attribs)) {
      $row = array();
      while(list($key,$val) = each($attribs)) {
        $this->get_attribute($row, strtolower($key), $val);
      }
      switch (strtolower($name)) {
        case 'uo_season': 
          $this->structure['season_id'] = $row['season_id'];
          $this->structure['season_name'] = $row['name'];
          $this->structure['series'] = array();
          $this->structure['reservations'] = array();
          break;
          
        case 'uo_series':
          $this->structure['series'][$row['series_id']] = array( 'name' => $row['name'], 'teams' => array());
          $this->structure_seriesId = $row['series_id'];
          break;
          
        case 'uo_team':
          if(empty($this->structure_seriesId)){
            $this->structure['error'] .= sprintf(_("uo_team without uo_series (%s)"), $row['team_id']);
          } else {
            $this->structure['series'][$this->structure_seriesId]['teams'][$row['team_id']] = array('name' => $row['name']);
          }
          break;
          
        case 'uo_reservation':
          $this->structure['reservations'][$row['id']] = array('location' => $row['location'], 'starttime' => $row['starttime']);
          break;
      }
    }
  }
  
  /**
   * Callback function for element end.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   */
  function end_tag_structure($parser, $name) {}
  
  
  /**
   * Creates or updates event from given xml-file.
   * @param string $filename Name of XML-file uploaded
   * @param string $eventId Event to Update or empty if new event created
   * @param string $mode new - for new event, replace - to update existing event
   */
  function XMLToEvent($filename, $eventId="", $mode="new", $replacers, $mock = false){
    $this->mode = $mode;
    $this->eventId = $eventId;
    $this->replacers = $replacers;
    $this->mock = $mock;
    $this->debug = "";
    
    if ($this->mock)
      $this->debug .= print_r($replacers, true);
    

    if((empty($this->eventId) && isSuperAdmin()) || isSeasonAdmin($eventId)){
      //create parser and set callback functions
      $xmlparser = xml_parser_create();
      xml_set_element_handler($xmlparser, array($this, "start_tag"), array($this, "end_tag"));

      if (!($fp = fopen($filename, "r"))) { die("cannot open ".$filename); }

      //remove extra spaces
      while ($data = fread($fp, 4096)){
        $data=eregi_replace(">"."[[:space:]]+"."< ",">< ",$data);
        if (!xml_parse($xmlparser, $data, feof($fp))) {
          $reason = xml_error_string(xml_get_error_code($xmlparser));
          $reason .= xml_get_current_line_number($xmlparser);
          die($reason);
        }
      }

      foreach ($this->followers as $pool => $follow) {
        $query = "UPDATE uo_pool SET follower='" . ((int) $this->uo_pool[$follow]) . "' WHERE pool_id='$pool'";
        if ($this->mock) {
          $this->debug .= $query . "\n";
        } else {
          DBQuery($query);
        }
      }
       
      xml_parser_free($xmlparser);
    } else { die('Insufficient rights to import data'); }
  }

  /**
   * Callback function for element start.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   * @param array $attribs element's attributes
   */
  function start_tag($parser, $name, $attribs) {
    if (is_array($attribs)) {
      $row = array();
      while(list($key,$val) = each($attribs)) {
        $this->get_attribute($row, $key, $val);
      }
      switch($this->mode){
        case "new":
          $this->InsertToDatabase(strtolower($name), $row);
          break;
           
        case "replace":
          $this->ReplaceInDatabase(strtolower($name), $row);
          break;
      }
    }
  }
  
  /**
   * Callback function for element end.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   */
  function end_tag($parser, $name) {}
  
  function shiftTime($date, $resId) {
    if (empty($this->replacers['date'][$resId]))
      return $date;
    
  
    return EpocToMysql(strtotime($date) + $this->replacers['date'][$resId]);
  }
   
  
  function replace(&$row, $key, $name) {
    switch ($name) {
      case "uo_reservation":
        if (!empty($this->replacers['location'][$key])) {
          $row['location'] = $this->replacers['location'][$key];
        }
        if (!empty($this->replacers['date'][$key])) {
          $this->debug .= "(".$row['starttime']."->".$this->shiftTime($row['starttime'], $key).")";
          $row['starttime'] = $this->shiftTime($row['starttime'], $key);
          $row['endtime'] = $this->shiftTime($row['endtime'], $key);
          $row['date'] = $this->shiftTime($row['end'], $key);
        }
        break;
        
      case "uo_series":
        if (!empty($this->replacers['series_name'][$key])) {
          $row['name'] = $this->replacers['series_name'][$key];
        }
        break;
        
      case "uo_team":
        if (!empty($this->replacers['team_name'][$key])) {
          $row['name'] = $this->replacers['team_name'][$key];
        }
        break;

        
      case "uo_game":
        if (!empty($this->replacers['date'][$key])) {
          $this->debug .= "(".$row['time']."->".$this->shiftTime($row['time'], $key).")";
          $row['time'] = $this->shiftTime($row['time'], $key);
        }
        break;
    }
    
  }
  
  /**
   * Does id mappings before inserts data into database as new data.
   * @param string $tagName Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   */
  function InsertToDatabase($tagName, $row){

    switch($tagName){
      case "uo_season":

        $seasonId = $row["season_id"];
        $newId = empty($this->replacers['season_id'])?$seasonId:$this->replacers['season_id'];
        $newName = empty($this->replacers['season_name'])?$row["name"]:$this->replacers['season_name'];
        
        $max = 1;
        while (SeasonExists($newId) || SeasonNameExists($newName)) {
          $modifier = rand(1,++$max);
          $newId = substr($seasonId,0,7) ."_$modifier";
          $newName = $row["name"]." ($modifier)";
        }
        $row["name"] = $newName; 
        $this->uo_season[$row["season_id"]]=$newId;
        unset($row["season_id"]);

        $values = "'".implode("','",array_values($row))."'";
        $fields = implode(",",array_keys($row));

        $query = "INSERT INTO ".mysql_adapt_real_escape_string($tagName)." (";
        $query .= "season_id,";
        $query .= mysql_adapt_real_escape_string($fields);
        $query .= ") VALUES (";
        $query .= "'".mysql_adapt_real_escape_string($newId)."',";
        $query .= $values;
        $query .= ")";
        if ($this->mock) {
          $this->debug .= $query . "\n";
        } else {
          DBQueryInsert($query);

          AddEditSeason($_SESSION['uid'],$newId);
          AddUserRole($_SESSION['uid'], 'seasonadmin:'.$newId);
        }
        break;

      case "uo_series":
        $key = $row["series_id"];
        unset($row["series_id"]);
        $row["season"] = $this->uo_season[$row["season"]];
        $this->replace($row, $key, $tagName);

        $newId = $this->InsertRow($tagName, $row);
        $this->uo_series[$key]=$newId;
        break;

      case "uo_scheduling_name":
        $key = $row["scheduling_id"];
        unset($row["scheduling_id"]);
        
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_scheduling_name[$key]=$newId;
        break;
        
      case "uo_team":
        $key = $row["team_id"];
        unset($row["team_id"]);
        $row["series"] = $this->uo_series[$row["series"]];
        
        $this->replace($row, $key, $tagName);
        
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_team[$key]=$newId;
        break;
         
      case "uo_player":
        $key = $row["player_id"];
        unset($row["player_id"]);
        $row["team"] = $this->uo_team[$row["team"]];
         
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_player[$key]=$newId;
        break;

      case "uo_pool":
        $key = $row["pool_id"];
        unset($row["pool_id"]);
        $row["series"] = $this->uo_series[$row["series"]];
        
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_pool[$key]=$newId;

        if(!empty($row["follower"]) && !is_null($row["follower"])){
          $this->followers[$newId] = (int)$row["follower"];
        }
        
        break;
         
      case "uo_reservation":
        $key = $row["id"];
        unset($row["id"]);
        $row["season"] = $this->uo_season[$row["season"]];
        
        $this->replace($row, $key, $tagName);
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_reservation[$key]=$newId;
        break;
        
      case "uo_movingtime":
        $row["season"] = $this->uo_season[$row["season"]];
        
        $newId = $this->InsertRow($tagName, $row);
        break;
        
      case "uo_game":
        $key = $row["game_id"];
        $reservationKey = $row["reservation"];
        unset($row["game_id"]);
        
        if(!empty($row["hometeam"]) && !is_null($row["hometeam"]) && $row["hometeam"]>0){
          $row["hometeam"] = $this->uo_team[$row["hometeam"]];
        }
        if(!empty($row["visitorteam"]) && !is_null($row["visitorteam"]) && $row["visitorteam"]>0){
          $row["visitorteam"] = $this->uo_team[$row["visitorteam"]];
        }
        if (!empty($row["respteam"]) && !is_null($row["respteam"]) && $row["respteam"] > 0) {
          $oldresp = $row["respteam"];
          if (is_null($row["hometeam"])) {
            $row["respteam"] = $this->uo_scheduling_name[$row["respteam"]];
          } else {
            $row["respteam"] = $this->uo_team[$row["respteam"]];
          }
        }
        if(!empty($row["reservation"]) && isset($this->uo_reservation[$row["reservation"]])){
          $row["reservation"] = $this->uo_reservation[$row["reservation"]];
        }
        if(!empty($row["pool"])){
          $row["pool"] = $this->uo_pool[$row["pool"]];
        }
        if(!empty($row["scheduling_name_home"]) && isset($this->uo_scheduling_name[$row["scheduling_name_home"]])){
          $row["scheduling_name_home"] = $this->uo_scheduling_name[$row["scheduling_name_home"]];
        } 
        if(!empty($row["scheduling_name_visitor"]) && isset($this->uo_scheduling_name[$row["scheduling_name_visitor"]])){
          $row["scheduling_name_visitor"] = $this->uo_scheduling_name[$row["scheduling_name_visitor"]];
        }
        
        $this->replace($row, $reservationKey, $tagName);
                
        $newId = $this->InsertRow($tagName, $row);
        
        $this->uo_game[$key]=$newId;
        break;

      case "uo_goal":
        $row["game"] = $this->uo_game[$row["game"]];
        if($row["assist"]>=0){
          $row["assist"] = $this->uo_player[$row["assist"]];
        }
        if($row["scorer"]>=0){
          $row["scorer"] = $this->uo_player[$row["scorer"]];
        }
        $this->InsertRow($tagName, $row);
        break;

      case "uo_gameevent":
        $row["game"] = $this->uo_game[$row["game"]];
        $this->InsertRow($tagName, $row);
        break;
         
      case "uo_played":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["player"] = $this->uo_player[$row["player"]];
        $this->InsertRow($tagName, $row);
        break;

      case "uo_team_pool":
        $row["team"] = $this->uo_team[$row["team"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];
        $this->InsertRow($tagName, $row);
        break;

      case "uo_game_pool":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];
        $this->InsertRow($tagName, $row);
        break;

      case "uo_moveteams":
        $row["topool"] = $this->uo_pool[$row["topool"]];
        $row["frompool"] = $this->uo_pool[$row["frompool"]];
        $row["scheduling_id"] = $this->uo_scheduling_name[$row["scheduling_id"]];
        $this->InsertRow($tagName, $row);
        break;
    }
  }

  /**
   * Inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   */
  function InsertRow($name, $row){
    $columns = GetTableColumns($name);
    $fields = implode(",",array_keys($row));
    
    
    $values = "";
    foreach ($row as $key => $value) {
      if ($columns[strtolower($key)]==='int') {
        if (is_null($value)){
          $values .= "NULL,";
        } elseif (is_numeric($value))
          $values .= "'".mysql_adapt_real_escape_string($value)."',";
        else
          die("Invalid column value '$value' for column $key of table $name. (".json_encode($row).").");
      } else {
        $values .= "'".mysql_adapt_real_escape_string($value)."',";
      }
    }
    
    $values = substr($values, 0, -1);

    $query = "INSERT INTO ".mysql_adapt_real_escape_string($name)." (";
    $query .= mysql_adapt_real_escape_string($fields);
    $query .= ") VALUES (";
    $query .= $values;
    $query .= ")";
    if ($this->mock) {
      $this->debug .= $query ."\n";
      return FALSE;
    } else {
      return DBQueryInsert($query);
    }
  }

  /**
   * Does id mappings before updating data into database.
   * If primary key doesn't exist in database, then data is inserted into database.
   * @param string $tagName Name of the table to update
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   * @see EventDataXMLHandler::SetRow()
   */
  function ReplaceInDatabase($tagName, $row){

    switch($tagName){
      case "uo_season":
        // no replace
        
        $cond = "season_id='".$row["season_id"]."'";
        $query = "SELECT season_id FROM uo_season WHERE ". $cond;
        $exist = DBQueryRowCount($query);
        if($exist){
          if($this->eventId === $row["season_id"]){
            $this->SetRow($tagName, $row, $cond);
            $this->uo_season[$row["season_id"]]=$row["season_id"];
          }else{
            die(sprintf(_("Target event %s is not the same as in the file (%s)."), $this->eventId, utf8entities($row["season_id"])));
          }
        }else{
          die(sprintf(_("Event to replace (%s) doesn't exist."), utf8entities($row['season_id'])));
        }
        break;

      case "uo_series":
        $key = $row["series_id"];
        unset($row["series_id"]);

        $this->replace($row, $key, $tagName);
        
        $cond = "series_id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_series[$key]=$key;
        }else{
          $row["season"] = $this->uo_season[$row["season"]];
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_series[$key]=$newId;
        }
        break;

      case "uo_scheduling_name":
        $key = $row["scheduling_id"];
        unset($row["scheduling_id"]);
      
        $cond = "scheduling_id='$key'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);
         
        if($exist){
          $this->SetRow($tagName, $row, $cond);
        }else{
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_scheduling_name[$key]=$newId;
        }
        break;
        
        
      case "uo_team":
        $key = $row["team_id"];
        unset($row["team_id"]);
        
        $this->replace($row, $key, $tagName);
        
        $cond = "team_id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_team[$key]=$key;
        }else{
          $row["series"] = $this->uo_series[$row["series"]];
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_team[$key]=$newId;
        }
        break;
         
      case "uo_player":
        $key = $row["player_id"];
        unset($row["player_id"]);
        $row["team"] = $this->uo_team[$row["team"]];

        $cond = "player_id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_player[$key]=$key;
        }else{
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_player[$key]=$newId;
        }
        break;

      case "uo_pool":
        $key = $row["pool_id"];
        unset($row["pool_id"]);
        $row["series"] = $this->uo_series[$row["series"]];

        $cond = "pool_id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_pool[$key]=$key;
        }else{
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_pool[$key]=$newId;
        }
        break;
         
      case "uo_reservation":
        $key = $row["id"];
        unset($row["id"]);
        $row["season"] = $this->uo_season[$row["season"]];
        
        $this->replace($row, $key, $tagName);
         
        $cond = "id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_reservation[$key]=$key;
        }else{
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_reservation[$key]=$newId;
        }
        break;
         
      case "uo_movingtime":
        $row["season"] = $this->uo_season[$row["season"]];

        $season=$row["season"];
        $from=$row["fromlocation"];
        $fromfield=$row["fromfield"];
        $to=$row["tolocation"];
        $tofield=$row["tofield"];
        $cond = "season='$season' AND fromlocation='$from' AND fromfield='$fromfield' AND tolocation='$to' AND tofield='$tofield'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);
        
        if($exist) {
          $this->SetRow($tagName, $row, $cond);
        } else {
          $newId = $this->InsertRow($tagName, $row);
        }
        break;
        
      case "uo_game":
        $key = $row["game_id"];
        $reservationKey = $row["reservation"];
        unset($row["game_id"]);
        
        if(!empty($row["hometeam"]) && !is_null($row["hometeam"]) && $row["hometeam"]>0){
          $row["hometeam"] = $this->uo_team[$row["hometeam"]];
        }
        if(!empty($row["visitorteam"]) && !is_null($row["visitorteam"]) && $row["visitorteam"]>0){
          $row["visitorteam"] = $this->uo_team[$row["visitorteam"]];
        }
        if(!empty($row["respteam"]) && !is_null($row["respteam"]) && $row["respteam"]>0){
          $row["respteam"] = $this->uo_team[$row["respteam"]];
        }
        if(!empty($row["reservation"]) && isset($this->uo_reservation[$row["reservation"]])){
          $row["reservation"] = $this->uo_reservation[$row["reservation"]];
        }
        if(!empty($row["pool"])){
          $row["pool"] = $this->uo_pool[$row["pool"]];
        }
        if(!empty($row["scheduling_name_home"]) && isset($this->uo_scheduling_name[$row["scheduling_name_home"]])){
          $row["scheduling_name_home"] = $this->uo_scheduling_name[$row["scheduling_name_home"]];
        }
        if(!empty($row["scheduling_name_visitor"]) && isset($this->uo_scheduling_name[$row["scheduling_name_visitor"]])){
          $row["scheduling_name_visitor"] = $this->uo_scheduling_name[$row["scheduling_name_visitor"]];
        }
        
        $this->replace($row, $reservationKey, $tagName);
        
        $newId = $this->InsertRow($tagName, $row);
        
        $this->uo_game[$key]=$newId;
        
        $cond = "game_id='".$key."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
          $this->uo_game[$key]=$key;
        }else{
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_game[$key]=$newId;
        }
        break;

      case "uo_goal":
        $row["game"] = $this->uo_game[$row["game"]];
        if($row["assist"]>=0){
          $row["assist"] = $this->uo_player[$row["assist"]];
        }
        if($row["scorer"]>=0){
          $row["scorer"] = $this->uo_player[$row["scorer"]];
        }

        $cond = "game='".$row["game"]."' AND num='".$row["num"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }
        break;

      case "uo_gameevent":
        $row["game"] = $this->uo_game[$row["game"]];

        $cond = "game='".$row["game"]."' AND num='".$row["num"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }

        break;
         
      case "uo_played":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["player"] = $this->uo_player[$row["player"]];

        $cond = "game='".$row["game"]."' AND player='".$row["player"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }
        break;

      case "uo_team_pool":
        $row["team"] = $this->uo_team[$row["team"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];

        $cond = "team='".$row["team"]."' AND pool='".$row["pool"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }
        break;

      case "uo_game_pool":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];

        $cond = "game='".$row["game"]."' AND pool='".$row["pool"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }

        break;

      case "uo_moveteams":
        $row["topool"] = $this->uo_pool[$row["topool"]];
        $row["frompool"] = $this->uo_pool[$row["frompool"]];

        $cond = "topool='".$row["topool"]."' AND fromplacing='".$row["fromplacing"]."'";
        $query = "SELECT * FROM ".$tagName." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($tagName,$row,$cond);
        }else{
          $this->InsertRow($tagName, $row);
        }
        break;
    }
  }

  /**
   * Set data into database by updating existing row.
   * @param string $name Name of the table to update
   * @param array $row Data to insert: key=>field, value=>data
   */
  function SetRow($name, $row, $cond){

    $values = array_values($row);
    $fields = array_keys($row);

    $query = "UPDATE ".mysql_adapt_real_escape_string($name)." SET ";
    
    for($i=0;$i<count($fields);$i++){
      $query .= mysql_adapt_real_escape_string($fields[$i]) ."='". mysql_adapt_real_escape_string($values[$i])."', ";
    }
    $query = rtrim($query,', ');
    $query .= " WHERE ";
    $query .= $cond;
    
    if ($this->mock) {
      $this->debug .= $query ."\n";
      return FALSE;
    } else {
      return DBQueryInsert($query);
    }
  }
}
?>
