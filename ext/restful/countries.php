<?php

class Countries extends Restful {

  function Countries() {
    $this->listsql = "SELECT country_id, name
		FROM uo_country country";
    $this->itemsql = "SELECT name, abbreviation, flagfile
		FROM uo_country country
		WHERE country.country_id = '%s'";

    $this->tables = array("uo_country" => "country");
    $this->defaultOrdering = array("country.name" => "ASC");

    $this->localizename = false;
  }

  function getItemName() {
    return "country";
  }
}
?>