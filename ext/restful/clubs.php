<?php

class Clubs extends Restful {

  function Clubs() {
    $this->listsql = "SELECT club_id, club.name
		FROM uo_club club";
    $this->itemsql = "SELECT club.name, club.city, club.country, country.name AS countryname, country.flagfile, club.founded
		FROM uo_club club
		LEFT JOIN uo_country country ON (club.country=country.country_id)
		WHERE club.club_id = '%s'";

    $this->tables = array("uo_club" => "club", "uo_country" => "country");
    $this->defaultOrdering = array("club.name" => "ASC", "club.city" => "ASC", "club.country" => "ASC");

    $this->localizename = false;
  }
}
?>