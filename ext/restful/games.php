<?php

class Games extends Restful {

  function Games() {
    $this->listsql = "SELECT time, home.name AS hometeamname, visitor.name AS visitorteamname, game.*,scheduling_name.name AS gamename
		FROM uo_game AS game 
		LEFT JOIN uo_team AS home ON (game.hometeam=home.team_id) 
		LEFT JOIN uo_team AS visitor ON (game.visitorteam=visitor.team_id)
		LEFT JOIN uo_scheduling_name AS scheduling_name ON (scheduling_name.scheduling_id=game.name)
          LEFT JOIN uo_pool pool ON (game.pool=pool.pool_id)
          LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
          LEFT JOIN uo_season season ON (ser.season=season.season_id)";
    
    $this->itemsql = "SELECT home.name AS hometeamname, visitor.name AS visitorteamname, 
                game.hometeam, game.visitorteam, game.pool,  
                game.homescore, game.visitorscore, 
                game.time, game.halftime, pool.timeslot, game.reservation, 
                game.official, game.respteam, game.resppers, 
                game.homedefenses, game.visitordefenses,
                game.valid, game.isongoing, game.hasstarted,
                scheduling_name.name AS gamename, hsname.name AS scheduling_home, vsname.name AS scheduling_visitor,
                ser.series_id AS series, ser.name AS seriesname, ser.season
		FROM uo_game AS game
		LEFT JOIN uo_team AS home ON (game.hometeam=home.team_id) 
		LEFT JOIN uo_team AS visitor ON (game.visitorteam=visitor.team_id)
		LEFT JOIN uo_scheduling_name AS scheduling_name ON (scheduling_name.scheduling_id=game.name)
          LEFT JOIN uo_scheduling_name AS hsname ON (hsname.scheduling_id=game.scheduling_name_home)
          LEFT JOIN uo_scheduling_name AS vsname ON (vsname.scheduling_id=game.scheduling_name_visitor)
          LEFT JOIN uo_pool pool ON (game.pool=pool.pool_id)
          LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
          WHERE game.game_id = %d";

    $this->tables = array("uo_game" => "game", "uo_played" => "played", "uo_game_pool" => "game_pool", "uo_pool" => "pool",
      "uo_scheduling_name" => "scheduling_name", "uo_series" => "ser", "uo_season" => "season");

    $this->defaultOrdering = array("season.starttime" => "ASC", "ser.ordering" => "ASC", "pool.ordering" => "ASC",
      "game.time" => "ASC");

//     $this->children["teams"] = array("field" => "team.team_id", "operator" => "=",
//       "value" => array("variable" => "hometeam"));
//     $this->children["visitorsteam"] = array("field" => "team.team_id", "operator" => "=",
//       "value" => array("variable" => "visitorteam"));

    $this->localizename = false;
    global $active_seasons, $editable_teams, $editing_teams;
    $this->filters["active"] = $active_seasons;

    $this->linkfields["hometeam"] = "teams";
    $this->linkfields["visitorteam"] = "teams";
    $this->linkfields["respteam"] = "teams";
    $this->linkfields["pool"] = "pools";
    $this->linkfields["series"] = "series";
    $this->linkfields["season"] = "seasons";
  }
}

?>