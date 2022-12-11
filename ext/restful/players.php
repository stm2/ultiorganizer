<?php 
class Players extends Restful {
  function __construct() {
		$this->listsql = "SELECT player_id, CONCAT(player.firstname, ' ', player.lastname) as name, num, 
		player.firstname, player.lastname, player.accredited, player.accreditation_id
		FROM uo_player player
		LEFT JOIN uo_team team ON (player.team=team.team_id)
		LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id)
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)";
		
		// TODO $publicfields = explode("|", $profile['public']);
		// email, gender, birthdate
		
		$this->itemsql = "SELECT p.player_id, p.firstname, 
		p.lastname, p.num, p.team, t.name AS teamname, p.team, t.series, ser.type, ser.name AS seriesname 
          
		FROM uo_player p
		LEFT JOIN uo_team t ON (p.team=t.team_id) 
		LEFT JOIN uo_series ser ON (ser.series_id=t.series)
		LEFT JOIN uo_player_profile pp ON (p.profile_id=pp.profile_id)
		WHERE player_id='%s'";
		$this->tables = array("uo_player" => "player", "uo_team" => "team", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
		$this->defaultOrdering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC");
		
		$this->localizename = false;
		$this->linkfields["accreditation_id"] = "playerprofiles";
		$this->linkfields["team"] = "teams";
		$this->children["games"] = array("field" => "game.game_id", "operator" => "subselect", "value" =>
			array("table" => "played", "field" => "played.game", "join" => "and", "criteria" =>
				array(array("field" => "played.player", "operator" => "=", "value" => array("variable" => "id")))));

		//$this->children["goals"];
		//$this->children["passes"];
	}	
}
?>