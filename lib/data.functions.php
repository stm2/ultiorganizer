<?php

/**
 * @file
 * This file contains Event Data XML Handler data handling functions for xml<->sql.
 *
 */

/**
 *
 * @class EventDataXMLHandler
 *
 */
class EventDataXMLHandler {
  
  // FIXME include defense, userproperties (?)
  /**
   * Converts element data into xml-format.
   *
   * xml-structure
   * <uo_season>
   * <uo_reservation></uo_reservation>
   * <uo_movingtime></uo_movingtime>
   * <uo_series>
   * <uo_team>
   * <uo_player></uo_player>
   * </uo_team>
   * <uo_scheduling_name></uo_scheduling_name>
   * <uo_pool>
   * <uo_team_pool></uo_team_pool>
   * <uo_game>
   * <uo_goal></uo_goal>
   * <uo_gameevent></uo_gameevent>
   * <uo_played></uo_played>
   * </uo_game>
   * </uo_pool>
   * <uo_game_pool></uo_game_pool>
   * <uo_moveteams></uo_moveteams>
   * </uo_series>
   * </uo_season>
   *
   * @param string $eventId
   *          Event to convert into xml-format.
   * @return string event data in xml
   *
   * @param array $series An array of series ids to export, or null to export all series
   * @param boolean $template If true, results are not exported. This includes game results, players and game events (scores). 
   * @throws Exception
   */
  
  function EventToXML($eventId, $series = null, $template = false) {
    if (isSeasonAdmin($eventId)) {
      $ret = "";
      $ret .= "<?xml version='1.0' encoding='UTF-8'?>\n";
      // uo_season
      $ret .= $this->write_season($eventId, $template);
      
      // uo_reservation
      // uo_movingtime
      $ret .= $this->write_reservations($eventId, $template, $series);
      
      // uo_series
      $seriesResult = $this->selectSeries($eventId, $series);
      while ($ser = mysqli_fetch_assoc($seriesResult)) {
        $ret .= $this->write_series($ser, $template);
      }
      
      $ret .= "</uo_season>\n";
      
      return $ret;
    } else {
      throw new Exception(_('Insufficient rights to export data.'));
    }
  }
  
  /**
   * @deprecated Status unknown
   */
  function XMLGetSeason($filename) {
    $reader = new XMLReader();
    $reader->open($filename);
    $reader->read();
    
    $content = array();
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== "uo_season") {
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
      } while ($reader->next());
    }
    $reader->close();
    return $content;
  }
  
  /**
   * Reads an XML file into an array:
   * 
   * <code>
   * [ 
   * 'season_id' => $seasonId, 
   * 'season_name' => $season_name,
   * 'series' => 
   *   [
   *   $seriesId => 
   *     [
   *     'name' => $name,
   *     'teams' => 
   *       [
   *       $teamId => 
   *         [
   *         'name' => $name
   *         ],
   *       ...
   *       ]
   *     ],
   *   ...
   *   ]
   * 'reservations' => 
   *   [
   *   $reservation_id => 
   *     [
   *     'location' => $location,
   *     'starttime' => $time,
   *     'reservationgroup' => $group,
   *     'fieldname' => $field
   *     ],
   *   ...
   *   ]
   * ]
   *   
   * 
   * @param string $filename
   * @return array
   */
  function XMLStructure($filename) {
    // create parser and set callback functions
    $xmlparser = xml_parser_create();
    xml_set_element_handler($xmlparser, array($this, "start_tag_structure"), array($this, "end_tag_structure"));
    if (!($fp = fopen($filename, "r"))) {
      die("cannot open " . $filename);
    }
    
    $this->structure = array();
    $this->structure['error'] = "";
    
    // remove extra spaces
    while ($data = fread($fp, 4096)) {
      // $data=pregi_replace(";>"."[[:space:]]+"."< ;",">< ",$data);
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
   * Creates or updates event in the database from given xml-file.
   *
   * @param string $filename
   *          Name of XML-file uploaded
   * @param string $eventId
   *          Event to Update or empty if new event created
   * @param string $mode
   *          new - for new event, replace - to update existing event
   *
   * @return
   */
  function XMLToEvent($filename, $eventId = "", $mode = "new", $replacers, $mock = false) {
    $this->mode = $mode;
    $this->eventId = $eventId;
    $this->replacers = $replacers;
    $this->mock = $mock;
    $this->debug = "";
    
    if ($this->mock)
      $this->debug .= print_r($replacers, true);
      
      if ((empty($this->eventId) && isSuperAdmin()) || isSeasonAdmin($eventId)) {
        // create parser and set callback functions
        $xmlparser = xml_parser_create();
        xml_set_element_handler($xmlparser, array($this, "start_tag"), array($this, "end_tag"));
        
        if (!($fp = fopen($filename, "r"))) {
          die("cannot open " . $filename);
        }
        DBTransaction();
        try {
          
          // remove extra spaces
          while ($data = fread($fp, 4096)) {
            $data = preg_replace(";>" . "[[:space:]]+" . "< ;", ">< ", $data);
            if (!xml_parse($xmlparser, $data, feof($fp))) {
              $reason = xml_error_string(xml_get_error_code($xmlparser));
              $reason .= xml_get_current_line_number($xmlparser);
              $this->error = $reason;
              throw new Exception($this->error);
            }
          }
          
          foreach ($this->followers as $pool => $follow) {
            $query = "UPDATE `uo_pool` SET `follower`='" . ((int) $this->uo_pool[$follow]) . "' WHERE `pool_id`='$pool'";
            if ($this->mock) {
              $this->debug .= $query . "\n";
            } else {
              DBQuery($query);
            }
          }
          DBCommit();
        } catch (Exception $e) {
          DBRollback();
          $this->error = $e->getMessage();
        }
        xml_parser_free($xmlparser);
      } else {
        $this->error = _('Insufficient rights to import data.');
      }
  }
  
  

  var $eventId;

  // event id under processing
  var $uo_season = array();

  // event id mapping array
  var $uo_series = array();

  // series id mapping array
  var $uo_team = array();

  // team id mapping array
  var $uo_scheduling_name = array();

  // scheduling id mapping array
  var $uo_pool = array();

  // pool id mapping array
  var $uo_player = array();

  // player id mapping array
  var $uo_game = array();

  // game id mapping array
  var $uo_reservation = array();

  // reservation id mapping array
  var $followers = array();

  // pools with unresolved followers
  var $mode;

  // import mode: 'new', 'insert', or 'replace'
  var $mock;

  // should we do a dry run?
  var $mockId = 10000;

  var $debug;

  // logging
  var $error;

  var $structure;

  // document structure
  var $structure_seriesId;

  // current series in structure parse
  const NULL_MARKER = "%NULL";

  /**
   * Default construction
   */
  function __construct() {}

  // //////////////// WRITER ///////////////////////////

  function write_season($eventId, $template) {
    $seasons = DBQuery("SELECT * FROM `uo_season` WHERE `season_id`='" . mysql_adapt_real_escape_string($eventId) . "'");
    $row = mysqli_fetch_assoc($seasons);

    if ($template) {
      $row["iscurrent"] = 0;
    }

    return $this->RowToXML("uo_season", $row, false);
  }

  function selectSeries($eventId, $series) {
    $query = sprintf("SELECT * FROM `uo_series` WHERE `season`='%s'", mysql_adapt_real_escape_string($eventId));
    if (!empty($series)) {
      $query .= "AND series_id IN (" . mysql_adapt_real_escape_string(implode(",", $series)) . ")";
    }

    return DBQuery($query);
  }

  function write_reservations($eventId, $template, $series = null) {
    $ret = "";

    $select = sprintf("rr.`season`='%s'", mysql_adapt_real_escape_string($eventId));
    if (!empty($series))
      $select .= sprintf(" AND `series` IN (%s)", mysql_adapt_real_escape_string(implode(",", $series)));

    $query = "SELECT `rr`.* FROM `uo_reservation` `rr` 
        LEFT JOIN `uo_game` `gg` ON (`gg`.`reservation` = `rr`.`id`)
        LEFT JOIN `uo_pool` `pp` on (`pp`.`pool_id` = `gg`.`pool`)
        
    WHERE $select GROUP BY `rr`.`id`";
    $reservations = DBQuery($query);

    while ($reservation = mysqli_fetch_assoc($reservations)) {
      $ret .= $this->RowToXML("uo_reservation", $reservation);
    }

    $times = DBQuery("SELECT * FROM `uo_movingtime` WHERE `season`='" . mysql_adapt_real_escape_string($eventId) . "'");
    while ($time = mysqli_fetch_assoc($times)) {
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

    $seriesId = (int) $ser['series_id'];

    // uo_team
    $teams = DBQuery("SELECT * FROM `uo_team` WHERE `series`='$seriesId' ORDER BY `rank`");
    while ($team = mysqli_fetch_assoc($teams)) {
      if ($template) {
        $team['activerank'] = null;
      }
      $ret .= $this->RowToXML("uo_team", $team, false);

      // uo_player
      $players = DBQuery(
        "SELECT * FROM `uo_player` WHERE `team`='" . mysql_adapt_real_escape_string($team['team_id']) . "'");
      while ($player = mysqli_fetch_assoc($players)) {
        $ret .= $this->RowToXML("uo_player", $player);
      }
      $ret .= "</uo_team>\n";
    }

    // uo_scheduling_name, referenced by either games or moves
    $schedulings = 
    // DBQuery("SELECT sched.* FROM uo_scheduling_name sched
    // LEFT JOIN uo_game game ON (sched.scheduling_id = game.scheduling_name_home OR sched.scheduling_id = game.scheduling_name_visitor)
    // LEFT JOIN uo_pool pool ON (game.pool = pool.pool_id)
    // LEFT JOIN uo_moveteams mv ON (sched.scheduling_id = mv.scheduling_id)
    // LEFT JOIN uo_pool pool2 ON (mv.frompool = pool2.pool_id OR mv.topool = pool2.pool_id)
    // WHERE pool2.series = $seriesId OR pool.series = $seriesId
    // GROUP BY scheduling_id");

    // this is faster
    DBQuery(
      "SELECT `scheduling_id`, MIN(`name`) as `name` FROM (
                (SELECT `sched`.*
                    FROM `uo_scheduling_name` `sched`
                    LEFT JOIN `uo_game` `game` ON (`sched`.`scheduling_id` = `game`.`scheduling_name_home` OR `sched`.`scheduling_id` = `game`.`scheduling_name_visitor`)
                    LEFT JOIN `uo_pool` `pool` ON (`game`.`pool` = `pool`.`pool_id`)
                    WHERE `pool`.`series` = $seriesId)
                UNION
                (SELECT `sched`.*
                    FROM `uo_scheduling_name` `sched`
                    LEFT JOIN `uo_moveteams` `mv` ON (`sched`.`scheduling_id` = `mv`.`scheduling_id`)
                    LEFT JOIN `uo_pool` `pool` ON (`mv`.`frompool` = `pool`.`pool_id` OR `mv`.`topool` = `pool`.`pool_id`)
                    WHERE `pool`.`series` = $seriesId )
                ) as `unioned`
                GROUP BY `scheduling_id`");

    while ($row = mysqli_fetch_assoc($schedulings)) {
      $ret .= $this->RowToXML("uo_scheduling_name", $row);
    }

    // uo_pool
    $ret .= $this->write_pools($seriesId, $template);

    // uo_moveteams
    $ret .= $this->write_moveteams($seriesId, $template);

    // uo_game_pool
    $ret .= $this->write_gamepools($seriesId, $template);
    $ret .= "</uo_series>\n";
    return $ret;
  }

  function is_continuing($pool) {
    if ($pool['continuingpool'] != 0)
      return true;
    if (PoolPlayoffRoot($pool['pool_id']) != $pool['pool_id'])
      return true;
    return false;
  }

  function write_pools($seriesId, $template) {
    $ret = "";
    $pools = DBQuery("SELECT * FROM `uo_pool` WHERE `series`='$seriesId'");
    while ($poolRow = mysqli_fetch_assoc($pools)) {
      if ($template) {
        $poolRow['played'] = 0;
      }

      $ret .= $this->RowToXML("uo_pool", $poolRow, false);

      if (!$template || !$this->is_continuing($poolRow)) { /* FIXME playoff pools may have continuing == FALSE */
        // uo_team_pool
        $teampools = DBQuery(
          "SELECT * FROM `uo_team_pool` WHERE `pool`='" . mysql_adapt_real_escape_string($poolRow['pool_id']) . "'");
        while ($teampool = mysqli_fetch_assoc($teampools)) {
          $ret .= $this->RowToXML("uo_team_pool", $teampool);
        }
      }

      // uo_game
      $ret .= $this->write_games($poolRow['pool_id'], $template);
      $ret .= "</uo_pool>\n";
    }
    return $ret;
  }

  function write_games($poolId, $template) {
    $ret = "";
    $games = DBQuery("SELECT * FROM `uo_game` WHERE `pool`='" . mysql_adapt_real_escape_string($poolId) . "'");
    while ($gameRow = mysqli_fetch_assoc($games)) {
      if ($template) {
        if (!empty($gameRow['scheduling_name_home'])) {
          // must be continuing pool
          $gameRow['hometeam'] = NULL;
          $gameRow['visitorteam'] = NULL;
        }
        if ($gameRow['hometeam'] != $gameRow['respteam'] && $gameRow['visitorteam'] != $gameRow['respteam'])
          $gameRow['respteam'] = NULL; // TODO can the scheduling team be reconstructed?
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
        // uo_goal
        $goals = DBQuery("SELECT * FROM `uo_goal` WHERE `game`='" . $gameId . "'");
        while ($goal = mysqli_fetch_assoc($goals)) {
          $ret .= $this->RowToXML("uo_goal", $goal);
        }
        // uo_gameevent
        $gameevents = DBQuery("SELECT * FROM `uo_gameevent` WHERE `game`='" . $gameId . "'");
        while ($gameevent = mysqli_fetch_assoc($gameevents)) {
          $ret .= $this->RowToXML("uo_gameevent", $gameevent);
        }
        // uo_played
        $playedplayers = DBQuery("SELECT * FROM `uo_played` WHERE `game`='" . $gameId . "'");
        while ($playedplayer = mysqli_fetch_assoc($playedplayers)) {
          $ret .= $this->RowToXML("uo_played", $playedplayer);
        }
      }
      $ret .= "</uo_game>\n";
    }
    return $ret;
  }

  function write_moveteams($seriesId, $template) {
    $ret = "";
    $moveteams = DBQuery(
      "SELECT m.* FROM `uo_moveteams` m
				LEFT JOIN `uo_pool` p ON(m.`frompool`=p.`pool_id`)
				WHERE p.`series`='$seriesId'");
    while ($moveteam = mysqli_fetch_assoc($moveteams)) {
      if ($template) {
        $moveteam['ismoved'] = 0;
      }
      $ret .= $this->RowToXML("uo_moveteams", $moveteam);
    }
    return $ret;
  }

  function write_gamepools($seriesId, $template) {
    $ret = "";
    $gamepools = DBQuery(
      "SELECT g.* FROM `uo_game_pool` g
				LEFT JOIN `uo_pool` p ON(g.`pool`=p.`pool_id`)
				WHERE p.`series`='$seriesId'");
    while ($gamepool = mysqli_fetch_assoc($gamepools)) {
      if (!$template || $gamepool['timetable'] == 1) {
        $ret .= $this->RowToXML("uo_game_pool", $gamepool);
      }
    }
    return $ret;
  }


  /**
   * Converts database row to xml.
   *
   * @param string $elementName
   *          - name of xml-element (table name)
   * @param array $row
   *          - element attributes (table row)
   * @param boolean $endtag
   *          - true if element closed
   *          
   * @return string XML-data
   */
  function RowToXML($elementName, $row, $endtag = true) {
    $columns = array_keys($row);
    $values = array_values($row);
    $total = count($row);
    $ret = "<" . $elementName . " ";

    for ($i = 0; $i < $total; $i++) {
      $ret .= $this->do_attribute($columns[$i], $values[$i]);
    }

    if ($endtag) {
      $ret .= "/>\n";
    } else {
      $ret .= ">\n";
    }

    return $ret;
  }

  function do_attribute($name, $value) {
    if ($value === NULL) {
      $value = self::NULL_MARKER;
    } else if (is_string($value)) {
      if (substr($value, 0, 1) === '%') {
        $value = "%" . $value;
      }
    }
    if ($name === htmlspecialchars($name)) {
      return $name . "='" . htmlspecialchars($value, ENT_QUOTES, "UTF-8") . "' ";
    } else {
      die('invalid attribute name "' . $name . '"');
    }
  }

  // //////////////// READER ///////////////////////////

  function get_attribute(&$row, $name, $value) {
    if ($value === self::NULL_MARKER) {
      $row[strtolower($name)] = NULL;
    } else if (mb_substr($value, 0, 2) === '%%') {
      $row[strtolower($name)] = mb_substr($value, 1);
    } else {
      $row[strtolower($name)] = $value;
    }
  }

  /**
   * Callback function for element start.
   *
   * @param xmlparser $parser
   *          a reference to the XML parser calling the handler.
   * @param string $name
   *          a element name
   * @param array $attribs
   *          element's attributes
   */
  function start_tag_structure($parser, $name, $attribs) {
    if (is_array($attribs)) {
      $row = array();
      foreach ($attribs as $key => $val) {
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
        $this->structure['series'][$row['series_id']] = array('name' => $row['name'], 'teams' => array());
        $this->structure_seriesId = $row['series_id'];
        break;

      case 'uo_team':
        if (empty($this->structure_seriesId)) {
          $this->structure['error'] .= sprintf(_("uo_team without uo_series (%s)"), $row['team_id']);
        } else {
          $this->structure['series'][$this->structure_seriesId]['teams'][$row['team_id']] = array(
            'name' => $row['name']);
        }
        break;

      case 'uo_reservation':
        $this->structure['reservations'][$row['id']] = array('location' => $row['location'],
          'starttime' => $row['starttime'], 'reservationgroup' => $row['reservationgroup'],
          'fieldname' => $row['fieldname']);
        break;
      }
    }
  }

  /**
   * Callback function for element end.
   *
   * @param xmlparser $parser
   *          a reference to the XML parser calling the handler.
   * @param string $name
   *          a element name
   */
  function end_tag_structure($parser, $name) {}


  /**
   * Callback function for element start.
   *
   * @param xmlparser $parser
   *          a reference to the XML parser calling the handler.
   * @param string $name
   *          a element name
   * @param array $attribs
   *          element's attributes
   */
  function start_tag($parser, $name, $attribs) {
    if (is_array($attribs)) {
      $row = array();
      foreach ($attribs as $key => $val) {
        $this->get_attribute($row, $key, $val);
      }
      switch ($this->mode) {
      case "new":
      case "insert":
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
   *
   * @param xmlparser $parser
   *          a reference to the XML parser calling the handler.
   * @param string $name
   *          a element name
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
      if (!empty($this->replacers['reservationgroup'][$key])) {
        $row['reservationgroup'] = $this->replacers['reservationgroup'][$key];
      }
      if (!empty($this->replacers['date'][$key])) {
        $this->debug .= "(" . $row['starttime'] . "->" . $this->shiftTime($row['starttime'], $key) . ")\n";
        $row['starttime'] = $this->shiftTime($row['starttime'], $key);
        $row['endtime'] = $this->shiftTime($row['endtime'], $key);
        $row['date'] = $this->shiftTime($row['date'], $key);
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
        $this->debug .= "(" . $row['time'] . "->" . $this->shiftTime($row['time'], $key) . ")";
        $row['time'] = $this->shiftTime($row['time'], $key);
      }
      break;
    }
  }

  /**
   * Does id mappings before inserts data into database as new data.
   *
   * @param string $tagName
   *          Name of the table to insert
   * @param array $row
   *          Data to insert: key=>field, value=>data
   *          
   * @see EventDataXMLHandler::InsertRow()
   */
  function InsertToDatabase($tagName, $row) {
    switch ($tagName) {
    case "uo_season":
      if ($this->mode == "new") {
        $seasonId = $row["season_id"];
        $newId = empty($this->replacers['season_id']) ? $seasonId : $this->replacers['season_id'];
        $newName = empty($this->replacers['season_name']) ? $row["name"] : $this->replacers['season_name'];

        $max = 1;
        while (SeasonExists($newId) || SeasonNameExists($newName)) {
          $modifier = rand(1, ++$max);
          $newId = mb_substr($seasonId, 0, 7) . "_$modifier";
          $newName = $row["name"] . " ($modifier)";
        }
        $this->debug .= "new season " . $newName . " (" . $seasonId . " -> " . $newId . ")\n";
        $row["name"] = $newName;
        $this->uo_season[$row["season_id"]] = $newId;
        unset($row["season_id"]);

        $values = "'" . implode("','", array_values($row)) . "'";
        $fields = "`" . implode("`,`", array_keys($row)) . "`";

        $query = "INSERT INTO `" . mysql_adapt_real_escape_string($tagName) . "` (";
        $query .= "`season_id`,";
        $query .= mysql_adapt_real_escape_string($fields);
        $query .= ") VALUES (";
        $query .= "'" . mysql_adapt_real_escape_string($newId) . "',";
        $query .= $values;
        $query .= ")";
        if ($this->mock) {
          $this->debug .= $query . "\n";
        } else {
          DBQueryInsert($query);

          AddEditSeason($_SESSION['uid'], $newId);
          AddSeasonUserRole($_SESSION['uid'], 'seasonadmin:' . $newId, $newId);
        }
      } else if ($this->mode == "insert") {
        $key = $row['season_id'];
        unset($row['season_id']);
        $this->uo_season[$key] = empty($this->replacers['season_id']) ? $this->eventId : $this->replacers['season_id'];

        $cond = "`season_id`='" . $this->uo_season[$key] . "'";
        $query = "SELECT `season_id` FROM `uo_season` WHERE $cond";
        $this->debug .= "insert season " . $key . " -> " . $this->uo_season[$key] . "\n";
        $exist = DBQueryRowCount($query);
        if ($exist) {
          // don't update
        } else {
          throw new Exception(sprintf(_("Event to insert (%s) doesn't exist."), utf8entities($this->uo_season[$key])));
        }
        break;
      }
      break;

    case "uo_series":
      $key = $row["series_id"];
      unset($row["series_id"]);
      $row["season"] = $this->uo_season[$row["season"]];
      $this->debug .= "replace series " . $key . "\n";
      $this->replace($row, $key, $tagName);

      $newId = $this->InsertRow($tagName, $row);
      $this->uo_series[$key] = $newId;
      break;

    case "uo_scheduling_name":
      $key = $row["scheduling_id"];
      unset($row["scheduling_id"]);

      $newId = $this->InsertRow($tagName, $row);
      $this->uo_scheduling_name[$key] = $newId;
      break;

    case "uo_team":
      $key = $row["team_id"];
      unset($row["team_id"]);
      $row["series"] = $this->uo_series[$row["series"]];

      $this->replace($row, $key, $tagName);

      $newId = $this->InsertRow($tagName, $row);
      $this->uo_team[$key] = $newId;
      break;

    case "uo_player":
      $key = $row["player_id"];
      unset($row["player_id"]);
      $row["team"] = $this->uo_team[$row["team"]];

      $newId = $this->InsertRow($tagName, $row);
      $this->uo_player[$key] = $newId;
      break;

    case "uo_pool":
      $key = $row["pool_id"];
      unset($row["pool_id"]);
      $row["series"] = $this->uo_series[$row["series"]];

      $newId = $this->InsertRow($tagName, $row);
      $this->uo_pool[$key] = $newId;

      if (!empty($row["follower"]) && !is_null($row["follower"])) {
        $this->followers[$newId] = (int) $row["follower"];
      }

      break;

    case "uo_reservation":
      $key = $row["id"];
      unset($row["id"]);
      $row["season"] = $this->uo_season[$row["season"]];

      $this->replace($row, $key, $tagName);
      $newId = $this->InsertRow($tagName, $row);
      $this->uo_reservation[$key] = $newId;
      break;

    case "uo_movingtime":
      $row["season"] = $this->uo_season[$row["season"]];

      $newId = $this->InsertRow($tagName, $row, true);
      break;

    case "uo_game":
      $key = $row["game_id"];
      $reservationKey = $row["reservation"];
      unset($row["game_id"]);

      if (!empty($row["hometeam"]) && !is_null($row["hometeam"]) && $row["hometeam"] > 0) {
        $row["hometeam"] = $this->uo_team[$row["hometeam"]];
      }
      if (!empty($row["visitorteam"]) && !is_null($row["visitorteam"]) && $row["visitorteam"] > 0) {
        $row["visitorteam"] = $this->uo_team[$row["visitorteam"]];
      }
      if (!empty($row["respteam"]) && !is_null($row["respteam"]) && $row["respteam"] > 0) {
        if (is_null($row["hometeam"])) {
          if (!isset($this->uo_scheduling_name[$row["respteam"]])) {
            $this->error .= sprintf("no match for responsible team %d in game %d<br />", $row['respteam'], $key);
          } else
            $row["respteam"] = $this->uo_scheduling_name[$row["respteam"]];
        } else {
          $row["respteam"] = $this->uo_team[$row["respteam"]];
        }
      }
      if (!empty($row["reservation"]) && isset($this->uo_reservation[$row["reservation"]])) {
        $row["reservation"] = $this->uo_reservation[$row["reservation"]];
      }
      if (!empty($row["pool"])) {
        $row["pool"] = $this->uo_pool[$row["pool"]];
      }
      if (!empty($row["scheduling_name_home"]) && isset($this->uo_scheduling_name[$row["scheduling_name_home"]])) {
        $row["scheduling_name_home"] = $this->uo_scheduling_name[$row["scheduling_name_home"]];
      }
      if (!empty($row["scheduling_name_visitor"]) && isset($this->uo_scheduling_name[$row["scheduling_name_visitor"]])) {
        $row["scheduling_name_visitor"] = $this->uo_scheduling_name[$row["scheduling_name_visitor"]];
      }

      $this->replace($row, $reservationKey, $tagName);

      $newId = $this->InsertRow($tagName, $row);

      $this->uo_game[$key] = $newId;
      break;

    case "uo_goal":
      $row["game"] = $this->uo_game[$row["game"]];
      if ($row["assist"] >= 0) {
        $row["assist"] = $this->uo_player[$row["assist"]];
      }
      if ($row["scorer"] >= 0) {
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

  function value2sql($value, $type) {
    if ($type === 'int') {
      if (is_null($value)) {
        return "NULL";
      } elseif (is_numeric($value)) {
        return "'" . mysql_adapt_real_escape_string($value) . "'";
      } else {
        throw new Exception(
          $this->debug . "Invalid column value '$value' for column $key of table $name. (" . json_encode($row) . ").");
      }
    } else {
      if ($type === 'datetime' && is_null($value)) {
        return "NULL";
      } else {
        return "'" . mysql_adapt_real_escape_string($value) . "'";
      }
    }
  }

  /**
   * Inserts data into database as new data.
   *
   * @param string $name
   *          Name of the table to insert
   * @param array $row
   *          Data to insert: key=>field, value=>data
   * @param boolean $ignore
   *          If true, DB errors will be ignored
   */
  function InsertRow($name, $row, $ignore = false) {
    $columns = GetTableColumns($name);
    $fields = '`' . implode("`,`", array_keys($row)) . '`';

    $values = "";
    foreach ($row as $key => $value) {
      $values .= $this->value2sql($value, $columns[strtolower($key)]) . ",";
    }

    $values = mb_substr($values, 0, -1);

    if ($ignore)
      $query = "INSERT IGNORE INTO ";
    else
      $query = "INSERT INTO ";
    $query .= mysql_adapt_real_escape_string($name) . " (";
    $query .= mysql_adapt_real_escape_string($fields);
    $query .= ") VALUES (";
    $query .= $values;
    $query .= ")";
    if ($this->mock) {
      $this->debug .= $query . ":" . (++$this->mockId) . "\n";
      return $this->mockId;
    } else {
      return DBQueryInsert($query, $ignore);
    }
  }

  /**
   * Does id mappings before updating data into database.
   * If primary key doesn't exist in database, then data is inserted into database.
   *
   * @param string $tagName
   *          Name of the table to update
   * @param array $row
   *          Data to insert: key=>field, value=>data
   *          
   * @see EventDataXMLHandler::InsertRow()
   * @see EventDataXMLHandler::SetRow()
   */
  function ReplaceInDatabase($tagName, $row) {
    switch ($tagName) {
    case "uo_season":
      // no replace

      $cond = "`season_id`='" . $row["season_id"] . "'";
      $query = "SELECT `season_id` FROM `uo_season` WHERE " . $cond;
      $exist = DBQueryRowCount($query);
      if ($exist) {
        if ($this->eventId === $row["season_id"]) {
          $this->debug .= "replace season " . $this->eventId . "\n";

          $this->SetRow($tagName, $row, $cond);
          $this->uo_season[$row["season_id"]] = $row["season_id"];
        } else {
          throw new Exception(
            sprintf(_("Target event %s is not the same as in the file (%s)."), $this->eventId,
              utf8entities($row["season_id"])));
        }
      } else {
        throw new Exception(sprintf(_("Event to replace (%s) doesn't exist."), utf8entities($row['season_id'])));
      }
      break;

    case "uo_series":
      $key = $row["series_id"];
      unset($row["series_id"]);

      $this->replace($row, $key, $tagName);

      $cond = "`series_id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->debug .= "replace series " . $key . "\n";
        $this->SetRow($tagName, $row, $cond);
        $this->uo_series[$key] = $key;
      } else {
        $this->debug .= "insert series " . $key . "\n";
        $row["season"] = $this->uo_season[$row["season"]];
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_series[$key] = $newId;
      }
      break;

    case "uo_scheduling_name":
      $key = $row["scheduling_id"];
      unset($row["scheduling_id"]);

      $cond = "`scheduling_id`='$key'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_scheduling_name[$key] = $newId;
      }
      break;

    case "uo_team":
      $key = $row["team_id"];
      unset($row["team_id"]);

      $this->replace($row, $key, $tagName);

      $cond = "`team_id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
        $this->uo_team[$key] = $key;
      } else {
        $row["series"] = $this->uo_series[$row["series"]];
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_team[$key] = $newId;
      }
      break;

    case "uo_player":
      $key = $row["player_id"];
      unset($row["player_id"]);
      $row["team"] = $this->uo_team[$row["team"]];

      $cond = "`player_id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
        $this->uo_player[$key] = $key;
      } else {
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_player[$key] = $newId;
      }
      break;

    case "uo_pool":
      $key = $row["pool_id"];
      unset($row["pool_id"]);
      $row["series"] = $this->uo_series[$row["series"]];

      $cond = "`pool_id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
        $this->uo_pool[$key] = $key;
      } else {
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_pool[$key] = $newId;
      }
      break;

    case "uo_reservation":
      $key = $row["id"];
      unset($row["id"]);
      $row["season"] = $this->uo_season[$row["season"]];

      $this->replace($row, $key, $tagName);

      $cond = "`id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
        $this->uo_reservation[$key] = $key;
      } else {
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_reservation[$key] = $newId;
      }
      break;

    case "uo_movingtime":
      $row["season"] = $this->uo_season[$row["season"]];

      $season = $row["season"];
      $from = $row["fromlocation"];
      $fromfield = $row["fromfield"];
      $to = $row["tolocation"];
      $tofield = $row["tofield"];
      $cond = "`season`='$season' AND `fromlocation`='$from' AND `fromfield`='$fromfield' AND `tolocation`='$to' AND `tofield`='$tofield'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $newId = $this->InsertRow($tagName, $row);
      }
      break;

    case "uo_game":
      $key = $row["game_id"];
      $reservationKey = $row["reservation"];
      unset($row["game_id"]);

      if (!empty($row["hometeam"]) && !is_null($row["hometeam"]) && $row["hometeam"] > 0) {
        $row["hometeam"] = $this->uo_team[$row["hometeam"]];
      }
      if (!empty($row["visitorteam"]) && !is_null($row["visitorteam"]) && $row["visitorteam"] > 0) {
        $row["visitorteam"] = $this->uo_team[$row["visitorteam"]];
      }
      if (!empty($row["respteam"]) && !is_null($row["respteam"]) && $row["respteam"] > 0) {
        $row["respteam"] = $this->uo_team[$row["respteam"]];
      }
      if (!empty($row["reservation"]) && isset($this->uo_reservation[$row["reservation"]])) {
        $row["reservation"] = $this->uo_reservation[$row["reservation"]];
      }
      if (!empty($row["pool"])) {
        $row["pool"] = $this->uo_pool[$row["pool"]];
      }
      if (!empty($row["scheduling_name_home"]) && isset($this->uo_scheduling_name[$row["scheduling_name_home"]])) {
        $row["scheduling_name_home"] = $this->uo_scheduling_name[$row["scheduling_name_home"]];
      }
      if (!empty($row["scheduling_name_visitor"]) && isset($this->uo_scheduling_name[$row["scheduling_name_visitor"]])) {
        $row["scheduling_name_visitor"] = $this->uo_scheduling_name[$row["scheduling_name_visitor"]];
      }

      $cond = "`game_id`='" . $key . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
        $this->uo_game[$key] = $key;
      } else {
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_game[$key] = $newId;
      }
      break;

    case "uo_goal":
      $row["game"] = $this->uo_game[$row["game"]];
      if ($row["assist"] >= 0) {
        $row["assist"] = $this->uo_player[$row["assist"]];
      }
      if ($row["scorer"] >= 0) {
        $row["scorer"] = $this->uo_player[$row["scorer"]];
      }

      $cond = "`game`='" . $row["game"] . "' AND `num`='" . $row["num"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }
      break;

    case "uo_gameevent":
      $row["game"] = $this->uo_game[$row["game"]];

      $cond = "`game`='" . $row["game"] . "' AND `num`='" . $row["num"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }

      break;

    case "uo_played":
      $row["game"] = $this->uo_game[$row["game"]];
      $row["player"] = $this->uo_player[$row["player"]];

      $cond = "`game`='" . $row["game"] . "' AND `player`='" . $row["player"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }
      break;

    case "uo_team_pool":
      $row["team"] = $this->uo_team[$row["team"]];
      $row["pool"] = $this->uo_pool[$row["pool"]];

      $cond = "`team`='" . $row["team"] . "' AND `pool`='" . $row["pool"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }
      break;

    case "uo_game_pool":
      $row["game"] = $this->uo_game[$row["game"]];
      $row["pool"] = $this->uo_pool[$row["pool"]];

      $cond = "`game`='" . $row["game"] . "' AND `pool`='" . $row["pool"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }

      break;

    case "uo_moveteams":
      $row["topool"] = $this->uo_pool[$row["topool"]];
      $row["frompool"] = $this->uo_pool[$row["frompool"]];

      $cond = "`topool`='" . $row["topool"] . "' AND `fromplacing`='" . $row["fromplacing"] . "'";
      $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
      $exist = DBQueryRowCount($query);

      if ($exist) {
        $this->SetRow($tagName, $row, $cond);
      } else {
        $this->InsertRow($tagName, $row);
      }
      break;
    }
  }

  /**
   * Set data into database by updating existing row.
   *
   * @param string $name
   *          Name of the table to update
   * @param array $row
   *          Data to insert: key=>field, value=>data
   */
  function SetRow($name, $row, $cond) {
    $columns = GetTableColumns($name);
    $query = "UPDATE `" . mysql_adapt_real_escape_string($name) . "` SET ";

    foreach ($row as $key => $value) {
      $svalue = $this->value2sql($value, $columns[strtolower($key)]);

      $query .= '`' . mysql_adapt_real_escape_string($key) . "`=$svalue, ";
    }

    $query = rtrim($query, ', ');
    $query .= " WHERE ";
    $query .= $cond;

    if ($this->mock) {
      $this->debug .= $query . "\n";
      return FALSE;
    } else {
      return DBQueryInsert($query);
    }
  }
}
?>
