<?php

class Results extends Restful {

  var $listfields;

  function __construct() {
    $this->listsql = "SELECT game.game_id AS id, '' AS name, game.game_id AS game, game.time AS 'time', 
               game.hometeam AS hometeam, hteam.name as hname,  
               game.visitorteam visitorteam, vteam.name as vname,
			homescore AS 'homescore', visitorscore AS 'visitorscore',
               game.pool AS pool,
               pool.name AS poolname,
               pool.series AS series,
               series.name AS seriesname,
			series.season AS season,
               season.name AS seasonname 
            FROM uo_game game
               LEFT JOIN uo_pool pool on (game.pool=pool.pool_id)
			LEFT JOIN uo_series series on (series.series_id=pool.series)
               LEFT JOIN uo_season season on (season.season_id=series.season)
			LEFT JOIN uo_team hteam on (game.hometeam=hteam.team_id)
			LEFT JOIN uo_team vteam on (game.visitorteam=vteam.team_id)";
    
    $this->itemsql = "SELECT game.game_id AS id, game.game_id AS game, '' AS name, game.time AS 'time',
               game.hometeam AS hometeam, hteam.name as hname,
               game.visitorteam visitorteam, vteam.name as vname,
			homescore AS 'homescore', visitorscore AS 'visitorscore',
               game.pool AS pool,
               pool.name AS poolname,
               pool.series AS series,
               series.name AS seriesname,
			series.season AS season,
               season.name AS seasonname
            FROM uo_game game
               LEFT JOIN uo_pool pool on (game.pool=pool.pool_id)
			LEFT JOIN uo_series series on (series.series_id=pool.series)
               LEFT JOIN uo_season season on (season.season_id=series.season)
			LEFT JOIN uo_team hteam on (game.hometeam=hteam.team_id)
			LEFT JOIN uo_team vteam on (game.visitorteam=vteam.team_id)
            WHERE game.game_id = '%s'";
    
    $this->tables = array("uo_game" => "game", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season",
      "uo_team" => "team");

    $this->defaultOrdering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC",
      "game.time" => "ASC");

    $this->localizename = false;

    $this->filters["active"] = array("join" => "and",
      "criteria" => array(array("field" => "game.hasstarted", "operator" => ">", "value" => 0),
        array("field" => "game.isongoing", "operator" => "=", "value" => 0),
        array("field" => "game.valid", "operator" => "=", "value" => 1),
        array("field" => "season.iscurrent", "operator" => "=", "value" => 1),
        array("field" => "series.valid", "operator" => "=", "value" => 1)));

    $this->linkfields["game"] = "games";
    $this->linkfields["hometeam"] = "teams";
    $this->linkfields["visitorteam"] = "teams";
    $this->linkfields["pool"] = "pools";
    $this->linkfields["series"] = "series";
    $this->linkfields["season"] = "seasons";

//     $this->listfields = array('time', 'hometeam' => 'name', 'visitorteam', 'homescore', 'visitorscore', 'pool', 'series', 'season');
    $this->listfields = 
    array('game' => [ 'id' => "game", 'link' => 'games'],
      'time', 'hometeam' => array("id" => "hometeam", "name" => 'hname', 'link' => "teams"),
      'visitorteam' => array("id" => "visitorteam", "name" => 'vname', 'link' => "teams"), 'homescore', 'visitorscore',
      'pool' => array("id" => "pool", "name" => 'poolname', 'link' => "pools"),
      'series' => array("id" => "series", "name" => 'seriesname', 'link' => "series"),
      'season' => array("id" => "season", "name" => 'seasonname', 'link' => "seasons"));
  }

  function getListData($row) {
    $ret = parent::getListData($row);

    foreach ($this->listfields as $name => $field) {
      if (is_array($field)) {
        $value = array();
        foreach ($field as $fname => $column) {
          if ($fname == 'link') {
            $value['link'] = $this->getLink($row[$name], $column);
          } else {
            $value[$fname] = $row[$column];
          }
        }
        $ret[$name] = $value;
      } else {
        $ret[$field] = $row[$field];
      }
    }
    unset($ret['link']);
    
//     $this->convertLinkFields($ret, false);
    return $ret;
  }

  function getIdField() {
    return "id";
  }

  function getList($filter = null, $ordering = null, $items = null) {
    if ($filter === null) {
      $filter = $this->getFilter("active");
    }
    return parent::getList($filter, $ordering, $items);
  }
}

?>
