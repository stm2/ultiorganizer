<?php
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$title = _("Clubs and Countries");

$html = "";

if (isset($_POST['removeclub_x']) && isset($_POST['hiddenDeleteId'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveClub($id);
}elseif (isset($_POST['addclub']) && !empty($_POST['clubname'])){
	AddClub(0,$_POST['clubname']);
}elseif (isset($_POST['saveclub']) && !empty($_POST['valid'])){
	//invalidate all valid clubs
	$clubs = ClubList(true); 
	while($row = mysqli_fetch_assoc($clubs)){
		SetClubValidity($row['club_id'], false);
	}
	//revalidate
	foreach($_POST["valid"] as $clubId){
		SetClubValidity($clubId, true);
	}
}elseif (isset($_POST['removecountry_x']) && isset($_POST['hiddenDeleteId'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveCountry($id);
}elseif (isset($_POST['addcountry']) && !empty($_POST['countryname']) && !empty($_POST['abbreviation']) && !empty($_POST['flag'])){
	AddCountry($_POST['countryname'], $_POST['abbreviation'], $_POST['flag']);
}elseif (isset($_POST['savecountry']) && !empty($_POST['valid'])){
	//invalidate all valid countries
	$countries = CountryList(true); 
	foreach($countries as $row){
		SetCountryValidity($row['country_id'], false);
	}
	//revalidate
	foreach($_POST["valid"] as $countryId){
		SetCountryValidity($countryId, true);
	}
}

//common page

$html .= "<form method='post' action='?view=admin/clubs'>";
$html .= "<h2>"._("All Clubs")."</h2>";
$html .= "<p>"._("Add new").": ";
$html .= "<input class='input' maxlength='50' size='40' name='clubname'/> ";
$html .= "<input class='button' type='submit' name='addclub' value='"._("Add")."'/></p>";

$html .= "<table class='admintable'>\n";
$html .= "<tr><th>"._("Id")."</th> <th>"._("Name")."</th><th>"._("Teams")."</th><th>"._("Valid")."</th><th></th></tr>\n";

$i=0;
$clubs = ClubList();

while($row = mysqli_fetch_assoc($clubs)){
  
	$html .= "<tr>";
	$html .= "<td>".$row['club_id']."&#160;</td>";
	$html .=  "<td><a href='?view=user/clubprofile&amp;club=".$row['club_id']."'>".utf8entities($row['name'])."</a></td>";

	$html .= "<td class='center'>".ClubNumOfTeams($row['club_id'])."</td>";
	if(intval($row['valid'])){
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['club_id'])."' checked='checked'/></td>";
	}else{
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['club_id'])."'/></td>";
	}
		
	if(CanDeleteClub($row['club_id'])){
	  $html .=  "<td class='center'>". getDeleteButton("removeclub", $row['club_id']) . "</td>";
	}
	$html .= "</tr>\n";
	$i++;
}

$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."'/></p>";

$html .= "<h2>"._("All Countries")."</h2>";
$html .= "<h3>"._("Add new")."</h3>";
$html .= "<table class='formtable'>";
$html .= "<tr><td>" . _("Name") ."</td><td><input class='input' maxlength='50' size='40' name='countryname'/></td></tr>\n";
$html .= "<tr><td>" . _("Abbreviation") ."</td><td><input class='input' maxlength='50' size='40' name='abbreviation'/></td></tr>\n";
$html .= "<tr><td>" . _("Flag filename") ."</td><td><input class='input' maxlength='50' size='40' name='flag'/></td></tr>\n";
$html .= "</table>\n";
$html .= "<p><input class='button' type='submit' name='addcountry' value='"._("Add")."'/></p>";

$html .= "<table class='admintable'>\n";
$html .= "<tr><th>"._("Id")."</th> <th>"._("Name")."</th><th>"._("Abbreviation")."</th><th>"._("Teams")."</th><th>"._("Valid")."</th><th></th></tr>\n";

$i=0;
$countries = CountryList(false); 
foreach($countries as $row){

	$html .= "<tr>";
	$html .= "<td>".$row['country_id']."&#160;</td>";
	$html .=  "<td>".utf8entities($row['name'])."</td>";
	$html .=  "<td class='center'>".utf8entities($row['abbreviation'])."</td>";

	$html .= "<td class='center'>".CountryNumOfTeams($row['country_id'])."</td>";
	if(intval($row['valid'])){
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['country_id'])."' checked='checked'/></td>";
	}else{
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['country_id'])."'/></td>";
	}

	if(CanDeleteCountry($row['country_id'])){
	  $html .=  "<td class='center'>" . getDeleteButton("removecountry_x", $row['country_id']) . "</td>";
	}
	
	$html .= "</tr>\n";
	$i++;
}

$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='savecountry' value='"._("Save")."'/></p>";

$html .= "<p>" . getHiddenInput(null, 'hiddenDeleteId', 'hiddenDeleteId') . "</p>";
$html .= "</form>\n";

showPage($title, $html);

?>