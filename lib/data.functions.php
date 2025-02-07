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
   * <uo_reservation/>
   * <uo_movingtime/>
   * <uo_club/>
   * <uo_series>
   *   <uo_team>
   *     <uo_player/>
   *   </uo_team>
   * <uo_scheduling_name/>
   * <uo_pool>
   *   <uo_team_pool/>
   *   <uo_game>
   *     <uo_goal/>
   *     <uo_gameevent/>
   *     <uo_played/>
   *   </uo_game>
   * </uo_pool>
   * <uo_game_pool/>
   * <uo_moveteams/>
   * </uo_series>
   * </uo_season>
   *
   * @param string $eventId
   *          Event to convert into xml-format.
   * @return string event data in xml
   *        
   * @param array $series
   *          An array of series ids to export, or null to export all series
   * @param boolean $template
   *          If true, results are not exported. This includes game results, players and game events (scores).
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

      $ret .= $this->write_clubs($eventId, $series, $template);
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
   *
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

    while ($reader->read() && $reader->name !== "uo_series") {;
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
   * <pre>
   * [
   * 'season_id' => $seasonId,
   * 'season_name' => $season_name,
   * 'series' =>
   * [
   * $seriesId =>
   * [
   * 'name' => $name,
   * 'teams' =>
   * [
   * $teamId =>
   * [
   * 'name' => $name
   * ],
   * ...
   * ]
   * ],
   * ...
   * ]
   * 'reservations' =>
   * [
   * $reservation_id =>
   * [
   * 'location' => $location,
   * 'starttime' => $time,
   * 'reservationgroup' => $group,
   * 'fieldname' => $field
   * ],
   * ...
   * ]
   * ]
   * </pre>
   *
   * @param string $filename
   * @return array
   */
  function XMLStructure($filename, $data = null) {
    // create parser and set callback functions
    $xmlparser = xml_parser_create();
    xml_set_element_handler($xmlparser, array($this, "start_tag_structure"), array($this, "end_tag_structure"));

    $this->structure = array();
    $this->structure['error'] = "";

    if ($data == null) {
      if (!($fp = fopen($filename, "r"))) {
        die("cannot open " . $filename);
      }

      // remove extra spaces
      while ($data = fread($fp, 4096)) {
        // $data=pregi_replace(";>"."[[:space:]]+"."< ;",">< ",$data);
        if (!xml_parse($xmlparser, $data, feof($fp))) {
          $reason = xml_error_string(xml_get_error_code($xmlparser));
          $reason .= xml_get_current_line_number($xmlparser);
          die($reason);
        }
      }
    } else {
      if (!xml_parse($xmlparser, $data, true)) {
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
  function XMLToEvent($filename, $eventId = "", $mode = "new", $replacers, $mock = false, $data = null) {
    switch ($mode) {
      case "new":
      case "insert":
      case "replace":
        $this->mode = $mode;
        break;
      default:
        $this->error = sprintf(_("Invalid mode '%s'"), $mode);
        return;
    }
    $this->eventId = $eventId;
    $this->replacers = $replacers;
    $this->mock = $mock;
    $this->debug = "";
    $this->resetLog();

    if ($this->mock)
      $this->debug .= "Replacers: \n" . print_r($replacers, true) . "\nLog:\n";

    if ((empty($this->eventId) && isSuperAdmin()) || isSeasonAdmin($eventId)) {
      // create parser and set callback functions
      $xmlparser = xml_parser_create();
      xml_set_element_handler($xmlparser, array($this, "start_tag"), array($this, "end_tag"));
      $fp = null;
      if ($filename != null) {
        if (!($fp = fopen($filename, "r"))) {
          die("cannot open " . $filename);
        }
      }
      DBTransaction();
      try {

        // remove extra spaces
        if ($fp != null) {
          while ($data = fread($fp, 4096)) {
            $data = preg_replace(";>" . "[[:space:]]+" . "< ;", ">< ", $data);
            if (!xml_parse($xmlparser, $data, feof($fp))) {
              $reason = xml_error_string(xml_get_error_code($xmlparser));
              $reason .= xml_get_current_line_number($xmlparser);
              $this->error = $reason;
              throw new Exception($this->error);
            }
          }
        } else {
          $data = preg_replace(";>" . "[[:space:]]+" . "< ;", ">< ", $data);
          if (!xml_parse($xmlparser, $data, true)) {
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

  /** event id under processing */
  var $eventId;

  /** event id mapping array */
  var $uo_season = array();

  /** series id mapping array */
  var $uo_series = array();

  /** team id mapping array */
  var $uo_team = array();

  /** club id mapping array */
  var $uo_club = array();

  /** scheduling id mapping array */
  var $uo_scheduling_name = array();

  /** pool id mapping array */
  var $uo_pool = array();

  /** player id mapping array */
  var $uo_player = array();

  /** game id mapping array */
  var $uo_game = array();

  /** reservation id mapping array */
  var $uo_reservation = array();

  /** pools with unresolved followers  */
  var $followers = array();

  /** import mode: 'new', 'insert', or 'replace' */
  var $mode;

  var $replacers;

  /** should we do a dry run? */
  var $mock;


  var $mockId = 10000;

  /**  logging */
  var $debug;

  /** structured logging */
  var $insertLog;

  var $error;

  /**  document structure*/
  var $structure;


  /** current series in structure parse */
  var $structure_seriesId;

  const NULL_MARKER = "%NULL";

  /**
   * Default constructor
   */
  function __construct() {
  }

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

    $locationIds = [];
    while ($reservation = mysqli_fetch_assoc($reservations)) {
      $ret .= $this->RowToXML("uo_reservation", $reservation);
      $locationIds[$reservation['location']] = true;
    }

    if (!empty($locationIds)) {
      $times = DBQuery(
        "SELECT * FROM `uo_movingtime` 
        WHERE `season`='" . mysql_adapt_real_escape_string($eventId) . "' AND (`fromlocation` IN (" .
          implode(',', array_keys($locationIds)) . ") 
          OR `tolocation` IN (" . implode(',', array_keys($locationIds)) . "))"
      );
      while ($time = mysqli_fetch_assoc($times)) {
        $ret .= $this->RowToXML("uo_movingtime", $time);
      }
    }
    return $ret;
  }

  function write_clubs($eventId, $series, $template) {
    $ret = '';

    // TODO select by series
    // uo_club
    $clubs = DBQuery(sprintf("
      SELECT DISTINCT c.*
      FROM `uo_club` c
      JOIN `uo_team` t ON t.club = c.club_id
      JOIN `uo_series` sr on (sr.series_id = t.series)
      WHERE sr.`season` = '%s'", mysql_adapt_real_escape_string($eventId)));
    while ($club = mysqli_fetch_assoc($clubs)) {
      $ret .= $this->RowToXML("uo_club", $club);
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
        "SELECT * FROM `uo_player` WHERE `team`='" . mysql_adapt_real_escape_string($team['team_id']) . "'"
      );
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
                GROUP BY `scheduling_id`"
      );

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
          "SELECT * FROM `uo_team_pool` WHERE `pool`='" . mysql_adapt_real_escape_string($poolRow['pool_id']) . "'"
        );
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
				WHERE p.`series`='$seriesId'"
    );
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
				WHERE p.`series`='$seriesId'"
    );
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
              'name' => $row['name'],
              'club' => $row['club'], // club ID
            );
          }
          break;

        case 'uo_club':
          $this->structure['clubs'][$row['club_id']] = array(
            'name' => $row['name'],
            'city' => $row['city'],
            'country' => $row['country'],
          );
          break;

        case 'uo_reservation':
          $this->structure['reservations'][$row['id']] = array(
            'location' => $row['location'],
            'starttime' => $row['starttime'],
            'reservationgroup' => $row['reservationgroup'],
            'fieldname' => $row['fieldname']
          );
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
  function end_tag_structure($parser, $name) {
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
  function end_tag($parser, $name) {
  }

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
          $row['club'] = $this->replacers['club'][$key] ?? null;
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
            Log1("season", "add", $newId);

            AddEditSeason($_SESSION['uid'], $newId);
            AddSeasonUserRole($_SESSION['uid'], 'seasonadmin:' . $newId, $newId);
          }
          $this->log($this->mode, $tagName, $newId);
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
          $newId = $this->uo_season[$key];
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

      case "uo_club":
        $key  = $row['club_id'];
        unset($row['club_id']);

        $this->replace($row, $key, $tagName);
        $newId = $this->InsertRow($tagName, $row);
        $this->uo_club[$key] = $newId;
        break;

      case "uo_team":
        $key = $row["team_id"];
        unset($row["team_id"]);
        $row["series"] = $this->uo_series[$row["series"]];

        $row['club'] = $this->uo_club[$row['club']] ?? null;

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
        $newId = $this->InsertRow($tagName, $row);
        break;

      case "uo_gameevent":
        $row["game"] = $this->uo_game[$row["game"]];
        $newId = $this->InsertRow($tagName, $row);
        break;

      case "uo_played":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["player"] = $this->uo_player[$row["player"]];
        $newId = $this->InsertRow($tagName, $row);
        break;

      case "uo_team_pool":
        $row["team"] = $this->uo_team[$row["team"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];
        $newId = $this->InsertRow($tagName, $row);
        break;

      case "uo_game_pool":
        $row["game"] = $this->uo_game[$row["game"]];
        $row["pool"] = $this->uo_pool[$row["pool"]];
        $newId = $this->InsertRow($tagName, $row);
        break;

      case "uo_moveteams":
        $row["topool"] = $this->uo_pool[$row["topool"]];
        $row["frompool"] = $this->uo_pool[$row["frompool"]];
        $row["scheduling_id"] = $this->uo_scheduling_name[$row["scheduling_id"]];
        $newId = $this->InsertRow($tagName, $row);
        break;
    }
    $this->log($this->mode, $tagName, $newId);
  }

  function value2sql($value, $type, $key, $table) {
    if ($type === 'int') {
      if (is_null($value)) {
        return "NULL";
      } elseif (is_numeric($value)) {
        return "'" . mysql_adapt_real_escape_string($value) . "'";
      } else {
        throw new Exception(
          $this->debug . "Invalid column value '$value' for column '$key' of type $type in table '$table'."
        );
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
      $values .= $this->value2sql($value, $columns[strtolower($key)], $key, $name) . ",";
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
      $ret = DBQueryInsert($query, $ignore);
      if ($ret > 0) {
        switch ($name) {
          case 'uo_series':
            Log1("series", "add", $ret);
            break;
          case 'uo_club':
            Log1("club", "add", $ret);
            break;
          case 'uo_team':
            Log1("team", "add", $ret);
            break;
          case 'uo_player':
            Log1("player", "add", $ret, $row["team"]);
            break;
          case 'uo_pool':
            Log1("pool", "add", $ret);
            break;
          case 'uo_game':
            Log1("game", "add", $ret);
            break;
        }
      }
      return $ret;
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
        $key = $row['season_id'];

        $cond = "`season_id`='" . $key . "'";
        $query = "SELECT `season_id` FROM `uo_season` WHERE " . $cond;
        $exist = DBQueryRowCount($query);
        if ($exist) {
          if ($this->eventId === $key) {
            $this->debug .= "replace season " . $this->eventId . "\n";

            $this->SetRow($tagName, $row, $cond);
            $this->uo_season[$key] = $key;
          } else {
            throw new Exception(
              sprintf(
                _("Target event %s is not the same as in the file (%s)."),
                $this->eventId,
                utf8entities($key)
              )
            );
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

      case 'uo_club':
        $key = $row["club_id"];
        unset($row["club_id"]);

        $this->replace($row, $key, $tagName);

        $cond = "`club_id`='" . $key . "'";
        $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($tagName, $row, $cond);
          $this->uo_club[$key] = $key;
        } else {
          $row["club"] = $this->uo_club[$row["club"]];
          $newId = $this->InsertRow($tagName, $row);
          $this->uo_club[$key] = $newId;
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
        $key = [$season, $from, $fromfield, $to, $tofield];
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
        $key = [$row['game'], $row['num']];
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
        $key = [$row['game'], $row['num']];
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
        $key = [$row['game'], $row['player']];
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
        $key = [$row['team'], $row['pool']];
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
        $key = [$row['game'], $row['pool']];
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
        $key = [$row['frompool'], $row['fromplacing'], $row['topool'], $row['toplacing']];
        $query = "SELECT * FROM `" . $tagName . "` WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($tagName, $row, $cond);
        } else {
          $this->InsertRow($tagName, $row);
        }
        break;
    }

    $this->log($exist ? "replace" : "replace_insert", $tagName, $key);
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
      $svalue = $this->value2sql($value, $columns[strtolower($key)], $key, $name);

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

  function resetLog() {
    $this->insertLog = [];
  }

  function log($mode, $tag, $id) {
    $this->insertLog[] = ['mode' => $mode, 'tag' => $tag, 'id' => $id];
  }

  function getLog() {
    return $this->insertLog;
  }
}



function do_event_data_import($importmode, $mock, $replacers, $source, $filename, $seasonId, $new_season_name, $import_data, $selected_series, $template, $post) {

  $html = "";
  $mode = null;
  $error_message = "";
  switch ($importmode) {
    case 'new_mode':
      if (isSuperAdmin()) {
        $mode = 'new';
      } else {
        die("Insufficient rights");
      }
      break;
    case 'replace_mode':
      $mode = 'replace';
      break;
    case 'insert_mode':
      $mode = 'insert';
      break;
  }
  $wasImported = [];
  if ($mode === 'new' || $mode === 'replace' || $mode == 'insert') {


    set_time_limit(300);
    $eventdatahandler = new EventDataXMLHandler();

    try {
      if ($source == 'xml') {
        $eventdatahandler->XMLToEvent($filename, $seasonId, $mode, $replacers, $mock);
        // unlink($filename);
      } else if ($source == 'database') {
        $importedSeason = SeriesInfo($selected_series[0])['season'];
        $data = $eventdatahandler->EventToXML($importedSeason, $selected_series, $template);

        $eventdatahandler->XMLToEvent(null, $seasonId, $mode, $replacers, $mock, $data);
      }

      $html .= "<ul>";
      foreach ($replacers['series_name'] as $newSeriesName) {
        $html .= "<li>" . utf8entities($newSeriesName) . "</li>\n";
      }
      $html .= "</ul>";
      if (empty($eventdatahandler->error)) {
        $html .= "<p>" . sprintf(_("Successfully imported into %s."), $new_season_name) . "</p>\n";
        // debug_to_apache($eventdatahandler->getLog());
        $wasImported = array_filter(
          $eventdatahandler->getLog(),
          fn($l) => $l['tag'] == 'uo_series'
        );
      } else {
        $html .= "<p>" . sprintf(_("Error while importing into %s:"), $new_season_name) .
          "<br /><textarea cols='70' rows='10' style='width:100%' readonly>" .
          $eventdatahandler->error . "</textarea></p>\n";
      }
    } catch (Exception $e) {
      $error_message = "<p class='warning'>" . sprintf(_("Error while importing %s:"), $new_season_name) .
        "<br />" . $e->getMessage() . "</p>\n";
    }


    if ($mock) {
      $html .= "<p>" .
        _("Your data was not actually imported. The debug output is shown below. Do you want to import now?") .
        "</p>";

      $html .= "<p>" . _("Debug output:");
      $html .= "<textarea cols='70' rows='10' style='width:100%' readonly>_POST:" . print_r($post, true) . "\n\n" .
        $eventdatahandler->debug . "</textarea></p>\n";
    }
  }
  return [$html, $error_message, $wasImported];
}

function copy_box($reference, $id, $label, $checked, $listener = '') {
  $cid = $id . $reference;
  $checked = $checked ? "checked='true'" : '';
  return "<label for='$cid'>" .
    "<input class='input' type='checkbox' $checked name='{$cid}' id='$cid' onchange='$listener'/>" .
    "$label</label>";
}

function link_script($all_listeners) {
  $script = "<script type=\"text/javascript\">
//<![CDATA[
  window.onload = function() {\n";
  $map = "  var dependent = new Map();\n";
  foreach ($all_listeners as $type => $listeners) {
    $map .= "  dependent.set('{$type}',new Map());\n";
    foreach ($listeners as $location => $rkeys) {
      if (!empty($rkeys['copys'])) {
        $origin = $rkeys['origin'];
        $dependent = '';
        foreach ($rkeys['copys'] as $id) {
          if (empty($dependent))
            $dependent .= '[';
          else
            $dependent .= ',';
          $dependent .= '"' . ($rkeys['getDisplay'])($id) . '"';
          if (empty($rkeys['getChkBox'])) {
            $script .= "    var masterChecked = false;\n";
          } else {
            $script .= "    var masterChecked = document.getElementById (\"" . ($rkeys['getChkBox']) . "\").checked;\n";
          }
          $script .= "    document.getElementById (\"" . ($rkeys['getDisplay'])($id) . "\").disabled = masterChecked;\n";
        }
        $dependent .= ']';
        $map .= "  dependent.get('{$type}').set($origin, $dependent);\n";
        $script .= "    document.getElementById (\"" . ($rkeys['getDisplay'])($origin) .
          "\").addEventListener('change',
        (event) => {
          updateDependent(\"$type\", $origin, event.target.value);
        });\n";
      }
    }
  }
  $script .= "  };\n
$map
  var toggleDependent = function(type, checkbox, rkey) {
    var active = checkbox.checked;
    if (dependent.get(type) != null && dependent.get(type).get(rkey) != null)
      dependent.get(type).get(rkey).forEach((dep) => document.getElementById(dep).disabled = active);
  };
  var updateDependent = function(type, origin, value) {
    dependent.get(type).get(origin).forEach((dep) => { 
      var elem = document.getElementById(dep); 
      if (elem.disabled) elem.value = value; 
    });
  };
//]]>
</script>\n";
  return $script;
}

function get_replacement_form($error_message, $seasonId, $import_data, $modified_import_data, $firstRun) {
  $html = '';
  $scripts = '';

  function locationsSorted(&$reservations) {
    $locationsSorted = [];
    foreach ($reservations as $rkey => $rval) {
      if (isset($locationsSorted[$rval['location']])) {
        $location = &$locationsSorted[$rval['location']];
      } else {
        $location = ['date' => $rval['starttime'], 'reservations' => []];
        $locationsSorted[$rval['location']] = &$location;
      }
      if ($location['date'] > $rval['starttime'])
        $location['date'] = $rval['starttime'];
      $location['reservations'][$rkey] = $rval;
      unset($location);
    }
    uasort($locationsSorted, function ($a, $b) {
      return $a['date'] <=> $b['date'];
    });
    return $locationsSorted;
  }

  function reservationsSorted(&$reservations) {
    uasort(
      $reservations,
      function ($a, $b) {
        $cmp1 = $a['starttime'] <=> $b['starttime'];
        if ($cmp1 != 0)
          return $cmp1;
        return $a['fieldname'] <=> $b['fieldname'];
      }
    );
    return $reservations;
  }

  if (!empty($error_message)) {
    $html .= $error_message;
  }

  $html .= "<p><a href='?view=admin/locations' target='_blank'>" . _("Add a location") . "</a></p>\n";


  if (!empty($seasonId)) {
    $html .= "<fieldset>";
    $html .= "<p><input type='radio' checked='checked' id='insert_mode' name='import_mode' value='insert_mode' />";
    $html .= "<label for='insert_mode'>" .
      _(
        "This operation inserts one or more new divisions into the event. It will only add, not alter any data or change user rights."
      ) .
      "</label></p>\n";
    $html .= "<p><input type='radio' id='replace_mode' name='import_mode' value='replace_mode' />";
    $html .= "<label for='replace_mode'>" .
      _(
        "This operation updates and adds event data in the database. It will not delete any data or change user rights."
      ) .
      "</label></p>\n";
    $html .= "</fieldset>";
  } else {
    $html .= "<fieldset>";
    $html .= "<p><input type='radio' checked='checked' id='new_mode' name='import_mode' value='new_mode' />";
    $html .= "<label for='new_mode'>" .
      _(
        "This operation creates a new season and inserts one or more new divisions. It will only not change user rights."
      ) .
      "</label></p>\n";
    $html .= "</fieldset>";
  }
  $html .= "<br /><table class='formtable'><tr><td colspan='4' class='infocell'>" . _(
    "Confirm or replace event data:"
  ) . "</td></tr>\n";
  if (!empty($seasonId)) {
    $html .= "<td class='infocell'>" . _("Event ID") .
      "</td><td><input type='hidden' name='new_season_id' value='$seasonId'/>$seasonId</td>\n";
    $html .= "<td class='infocell'>" . _("Event Name") . "</td><td><input type='hidden' name='new_season_name' value='" .
      utf8entities(SeasonName($seasonId)) . "'/>" . utf8entities(SeasonName($seasonId)) . "</td></tr>\n";
  } else {
    $html .= "<td>" . _("Event ID") .
      "</td><td><input class='input' size='30' maxlength='30' name='new_season_id' value='" .
      utf8entities($import_data['season_id']) . "'/></td>\n";
    $html .= "<td>" . _("Event Name") .
      "</td><td><input class='input' size='30' maxlength='50' name='new_season_name' value='" .
      utf8entities($import_data['season_name']) . "'/></td></tr>\n";
  }
  $html .= "</table><br />\n";
  $html .= "<table class='formtable'>\n";

  if (!empty($import_data['reservations'])) {
    $html .= "<tr><td colspan='4' class='infocell'>" . _("Change reservations?") . "</td></tr>\n";
    $locations = array();
    $dates = array();
    $dateIds = array();
    $resgroups = array();
    $listeners = array('location' => array());
    foreach (locationsSorted($import_data['reservations']) as $loc => $locres) {
      foreach (reservationsSorted($locres['reservations']) as $rkey => $rval) {
        $id = "rlocation" . utf8entities($rkey);
        $locId0 = $rval['location'];
        $rval2 = $modified_import_data['reservations'][$rkey];
        $locId2 = $rval2['location'];
        $value = utf8entities($locId0);
        $label = "<input type='hidden' id='reservation" . utf8entities($rkey) . "' name='reservations[]' value='" .
          utf8entities($rkey) . "' />" . sprintf(_("Location %s map to"), $value);
        if (isset($locations[$locId0])) {
          $origin = $listeners['location'][$locId0]['origin'];
          $postText = "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of location %s"), $value);
          $listeners['location'][$locId0]['copys'][] = $rkey;
        } else {
          $label2 = sprintf(_("Change all locations %s to this value"), $value);
          $postText = copy_box($rkey, "copyloc", $label2, $firstRun, "toggleDependent(\"location\", this, $rkey)");
          $locations[$locId0] = $id;
          $listeners['location'][$locId0] = array(
            'origin' => $rkey,
            'copys' => array(),
            'getDisplay' => function ($id) {
              return "rlocation" . utf8entities($id) . "Name";
            },
            'getChkBox' => "copyloc" . utf8entities($rkey),
          );
        }

        $html .= "<tr><th>" . sprintf(_("Reservation %d"), $rkey) . "</th><th>" . _("replacement value") .
          "</th><th></th></tr>\n";
        $html .= "<tr><td><label for='{$id}Name'>$label</label></td><td>";
        // FIXME location id refers to old location
        $lname = LocationInfo($locId2)['name'] ?? '';
        $html .= LocationInput2($id, 'rlocations', $lname, $locId2);
        $html .= "</td><td>" . $postText . "</td></tr>\n";

        $scripts .= LocationScript($id);

        $id = "resgroup" . utf8entities($rkey);
        $value = $rval2['reservationgroup'];

        $html .= "<tr><td><label for='$id'>" . _("Reservation Group") . "</label>:</td><td>" .
          "<input type='text' class='input' name='$id' id='$id' value='$value'/></td><td>";

        if (isset($resgroups[$value])) {
          $origin = $listeners['reservationgroup'][$value]['origin'];
          $html .= "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of reservation group %s"), $value) . "</td></tr>\n";
          $listeners['reservationgroup'][$value]['copys'][] = $rkey;
        } else {
          $label = sprintf(_("Change all reservation groups %s to this value"), $value);
          $html .= copy_box($rkey, "copyrgroup", $label, $firstRun, "toggleDependent(\"reservationgroup\", this, $rkey)") .
            "</td></tr>\n";
          $resgroups[$value] = $id;
          $listeners['reservationgroup'][$value] = array(
            'origin' => $rkey,
            'copys' => array(),
            'getDisplay' => function ($id) {
              return "resgroup" . utf8entities($id);
            },
            'getChkBox' => "copyrgroup" . utf8entities($rkey),
          );
        }

        $id = "olddates" . utf8entities($rkey);
        $olddate = ShortDate($rval['starttime']);
        $olddateVal = utf8entities($olddate);
        $newdate = ShortDate($rval2['starttime']);
        $newdateVal = utf8entities($newdate);
        $nid = "newdates" . utf8entities($rkey);
        $html .= "<tr><td><label for='$nid'>" . _("Date") . " (" . _("dd.mm.yyyy") . ")</label>:</td><td>" .
          "<input type='hidden' name='olddates[]' id='$id' value='$olddateVal' />" . getCalendarInput($nid, $newdate) .
          "</td><td>";
        $dateIds[] = $nid;

        if (isset($dates[$olddate])) {
          $origin = $listeners['date'][$olddate]['origin'];
          $html .= "<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of date %s"), $olddate) . "</td></tr>\n";
          $listeners['date'][$olddate]['copys'][] = $rkey;
        } else {
          $label = sprintf(_("Change all dates %s to this value"), $olddate);
          $html .= copy_box($rkey, "copydate", $label, $firstRun, "toggleDependent(\"date\", this, $rkey)") . "</td></tr>\n";
          $dates[$olddate] = $id;
          $listeners['date'][$olddate] = array(
            'origin' => $rkey,
            'copys' => array(),
            'getDisplay' => function ($id) {
              return "newdates" . utf8entities($id);
            },
            'getChkBox' => "copydate" . utf8entities($rkey),
          );
        }
      }
    }
    $scripts .= getCalendarScript($dateIds);

    $scripts .= link_script($listeners);
  }

  $html .= "</table><br />\n";
  $html .= "<table class='formtable'>\n";

  if (!isset($modified_import_data)) {
    $modified_import_data = $import_data;
  }

  $html .= "<tr><td colspan='4' class='infocell'>" . _("Change divisions or teams?") . "</td></tr>\n";
  foreach ($modified_import_data['series'] as $skey => $sval) {
    $id = "seriesnames" . utf8entities($skey);
    $html .= "<tr><td  class='infocell'><label for='$id'>" . _("Division Name") . "</label></td><td>" .
      "<input type='hidden' id='import_series" . utf8entities($skey) . "' name='rename_series[]' value='" .
      utf8entities($skey) . "' />" . "<input class='input' maxlength='50' id='$id' name='seriesnames[]' value='" .
      utf8entities($sval['name']) . "'/></td></tr>\n";
    foreach ($sval['teams'] as $tkey => $tval) {
      $tid = "teamnames" . utf8entities($tkey);
      $cid = "clubnames" . utf8entities($tkey);
      $html .= "<tr><td>" . "<input type='hidden' id='teams" . utf8entities($tkey) . "' name='teams[]' value='" .
        utf8entities($tkey) . "' /><label for='$tid'>" . _("Team / Club") .
        "</label></td><td colspan='2'><input class='input' maxlength='50' id='$tid' name='teamnames[]' value='" .
        utf8entities($tval['name']) . "'/></td>" .
        "<td colspan='2'><input class='input' maxlength='50' id='$cid' name='clubnames[]' value='" .
        utf8entities($tval['club_name']) . "'/></td></tr>\n";
    }
  }
  $html .= "</table>\n";

  $html .= "<input class='button' type='submit' name='mock' value='" . utf8entities(_("Mock (test only)")) . "'/>";
  $html .= " <input class='button' type='submit' name='action_import' value='" . utf8entities(_("Import")) . "'/>";
  $html .= " <input class='button' type='submit' name='action_cancel' value='" . utf8entities(_("Cancel")) . "'/></p>";

  return [$html, $scripts];
}


/**
 * @param array $post
 * @param array $import_data
 * @return array $replacers 
 *   ['season_id'] => new season ID, <br>
 *   ['season_name'] => new season name, <br>
 *   ['location'][$resId] => new reservation ID, <br>
 *   ['reservationgroup'][$resId] => new reservation group, <br>
 *   ['date'][$resId] => new date of reservation, <br>
 *   ['series_name'][$serId] => new series name, <br>
 *   ['team_name'][$teamId] => new team name <br>
 *   ['club_name'][$teamId] => new club name <br>
 */

function get_replacers($post, &$import_data = null) {
  $replacers = array();
  if (!empty($import_data)) {
    $import_data['season_id'] = $replacers['season_id'] = $post['new_season_id'];
    $import_data['season_name'] = $replacers['season_name'] = $post['new_season_name'];
  }
  $help_replacers = array();
  if (!empty($post['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      if (!empty($import_data)) {
        $sres = $import_data['reservations'][$resId];
      }
      // debug_to_apache($resId . " sres: " . print_r($sres, true) . "import_data: " . print_r($import_data, true));

      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $id = $post["copyofrlocation$resId"];
        $loc = $replacers['location'][$id];
      } else {
        $loc = $post['rlocations'][$i];
      }
      $replacers['location'][$resId] = $loc;

      if (!empty($sres) && isset($replacers['location'][$resId]))
        $sres['location'] = $replacers['location'][$resId];

      $origin = null;
      if (isset($post["copyofresgroup$resId"])) // is this a copy?
        $origin = $post["copyofresgroup$resId"];
      if (!empty($origin) && isset($post["copyrgroup$origin"])) { // should we copy
        $replacers['reservationgroup'][$resId] = $replacers['reservationgroup'][$post["copyofresgroup$resId"]];
      } else
        $replacers['reservationgroup'][$resId] = $post["resgroup$resId"];

      if (!empty($sres) && isset($replacers['reservationgroup'][$resId]))
        $sres['reservationgroup'] = $replacers['reservationgroup'][$resId];

      $date0 = $post['olddates'][$i];

      $origin = null;
      if (isset($post["copyofolddates$resId"])) // is this a copy?
        $origin = $post["copyofolddates$resId"];
      if (!empty($origin) && isset($post["copydate$origin"])) { // should we copy
        $help_replacers['newdate'][$resId] = $help_replacers['newdate'][$post["copyofolddates$resId"]];
      } else
        $help_replacers['newdate'][$resId] = $post["newdates$resId"];
      $date1 = $help_replacers['newdate'][$resId];
      // $date1 = $post['newdates'][$i];

      if ($date0 !== $date1) {
        $replacers['date'][$resId] = strtotime($date1) - strtotime($date0);

        if (!empty($sres))
          $sres['starttime'] = EpocToMysql(strtotime($sres['starttime']) + $replacers['date'][$resId]);
      }
      // debug_to_apache([$resId, "sres" =>  $sres, "rep" => $replacers, "help" => $help_replacers]);
      if (!empty($sres))
        $import_data['reservations'][$resId] = $sres;
      // debug_to_apache(["import_data" => $import_data]);
    }
  }
  foreach ($post['rename_series'] as $i => $serId) {
    $replacers['series_name'][$serId] = $post['seriesnames'][$i];
    if (!empty($import_data)) {
      $import_data['series'][$serId]['name'] = $replacers['series_name'][$serId];
    }
  }
  foreach ($post['teams'] as $i => $teamId) {
    $replacers['team_name'][$teamId] = $post['teamnames'][$i];
    $replacers['club_name'][$teamId] = $post['clubnames'][$i];
    if (!empty($import_data)) {
      foreach ($import_data['series'] as $serId => $ser) {
        if (isset($ser['teams'][$teamId])) {
          // debug_to_apache("teamId: $teamId, serId: $serId, old: {$import_data['series'][$serId]['teams'][$teamId]['name']}, new: {$replacers['team_name'][$teamId]}");
          $import_data['series'][$serId]['teams'][$teamId]['name'] = $replacers['team_name'][$teamId];
          $import_data['series'][$serId]['teams'][$teamId]['club_name'] = $replacers['club_name'][$teamId];
          break;
        }
      }
    }
  }
  return $replacers;
}

function check_replacers($post, $replacers) {
  $message = "";
  if (!empty($post['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $id = $post["copyofrlocation$resId"];
        $loc = $replacers['location'][$id];
        $locName = $post["rlocation{$id}Name"];
      } else {
        $loc = $post['rlocations'][$i];
        $locName = $post["rlocation{$resId}Name"];
      }

      $locHere = LocationInfo($loc)['name'] ?? null;
      if (empty($locName) || $locName != $locHere) {
        $message .= utf8entities(
          sprintf(
            _(
              "Reservation %s, Location %s: Your name '%s' does not match our name '%s'. Add a location or confirm existing location."
            ),
            $resId,
            $loc,
            $locName,
            $locHere
          )
        ) . "<br />\n";
      }
    }
  }
  if (!empty($message)) {
    throw new Exception($message);
  }
  return $replacers;
}
