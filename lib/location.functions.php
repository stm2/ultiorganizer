<?php

function GetLocations($mode, $search, $locale = null) {
  if ($locale == null)
    $locale = str_replace(".", "_", getSessionLocale());
  if ($mode == 'search') {
    $query1 = sprintf("SELECT loc.*,
		    inf1.locale as locale, inf1.info as locale_info,
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
		    WHERE (name like '%%%s%%' OR address like '%%%s%%') ORDER BY name",
      mysql_adapt_real_escape_string($locale), mysql_adapt_real_escape_string($search), mysql_adapt_real_escape_string($search));
    
  } else if ($mode == 'id') {
    $query1 = sprintf("SELECT loc.*,
		    inf1.locale as locale, inf1.info as locale_info,
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE id=%d ORDER BY name",
      mysql_adapt_real_escape_string($locale),
      (int)$search);
  } else if ($mode == 'all') {
    $query1 = sprintf("SELECT loc.*,
		    inf1.locale as locale, inf1.info as locale_info,
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE 1 ORDER BY name",
      mysql_adapt_real_escape_string($locale));
  }
  $result1 = mysql_adapt_query($query1);
  
  if (!$result1) { die('Invalid query: ' . mysql_adapt_error()); }
  return $result1;
}

function GetSearchLocations() {
	$locale = str_replace(".", "_", getSessionLocale());
	if (isset($_GET['search']) || isset($_GET['query']) || isset($_GET['q'])) {
		if (isset($_GET['search']))
			$search = $_GET['search'];
		elseif (isset($_GET['query']))
			$search = $_GET['query'];
		else
			$search = $_GET['q'];
		return GetLocations('search', $search, $locale);
		
	} elseif (isset($_GET['id'])) {
	  return GetLocations('id', (int) $_GET['id'], $locale);
	} else {
	  return GetLocations('all', null, $locale);
	  
	}
	die('Invalid call');
}

function LocationDescription($id, $locale = null) {
  if (empty($locale))
    $locale = str_replace(".", "_", getSessionLocale());
  $query = sprintf("SELECT info
	    FROM uo_location_info
	    WHERE location_id=%d AND `locale`='%s'", (int)$id, mysql_adapt_real_escape_string($locale));
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
  
  $result = mysqli_fetch_assoc($result);
  if (empty($result))
    return null;
  return $result['info'];
}


function LocationInfo($id) {
	$locale = str_replace(".", "_", getSessionLocale());
	$query = sprintf("SELECT id, name, fields, indoor, address, inf.info as info, lat, lng 
	    FROM uo_location loc LEFT JOIN uo_location_info inf ON ( loc.id = inf.location_id and inf.locale='%s' )
	    WHERE id=%d", mysql_adapt_real_escape_string($locale), (int)$id);
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	return mysqli_fetch_assoc($result);
}

function SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
		$query = sprintf("UPDATE uo_location SET name='%s', address='%s', fields=%d, indoor=%d, lat='%s', lng='%s'  WHERE id=%d", 
			mysql_adapt_real_escape_string($name),
			mysql_adapt_real_escape_string($address),
			(int)$fields,
			(int)$indoor,
			mysql_adapt_real_escape_string($lat),
			mysql_adapt_real_escape_string($lng),
		    (int)$id);
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
		
		updateInfos($id, $info);
	} else { die('Insufficient rights to change location'); }	
}

function updateInfos($id, $info) {
  foreach ($info as $locale => $infostr) {
    if (empty($infostr)) {
      $query = sprintf("DELETE FROM uo_location_info WHERE location_id=%d AND locale='%s'",
          (int)$id, mysql_adapt_real_escape_string($locale));
    } else {
      $query = sprintf("INSERT INTO uo_location_info (location_id, locale, info) VALUE (%d, '%s', '%s')
		    ON DUPLICATE KEY UPDATE info='%s'",
          (int)$id,
          mysql_adapt_real_escape_string($locale),
          mysql_adapt_real_escape_string($infostr),
          mysql_adapt_real_escape_string($infostr));
    }
    $result = mysql_adapt_query($query);
    if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
  }
}

function AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
	   $query = sprintf("INSERT INTO uo_location (name, address, fields, indoor, lat, lng)
	       VALUES ('%s', '%s', %d, %d, '%s', '%s')",
	       mysql_adapt_real_escape_string($name),
	       mysql_adapt_real_escape_string($address),
	       (int)$fields,
	       (int)$indoor,
	       mysql_adapt_real_escape_string($lat),
	       mysql_adapt_real_escape_string($lng));
	       
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }

		$locationId = mysql_adapt_insert_id();

		updateInfos($locationId, $info);
		
		return $locationId;
	} else { die('Insufficient rights to add location'); }		
}

function RemoveLocation($id) {
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_location WHERE id=%d", (int)$id);
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
		
		$query = sprintf("DELETE FROM uo_location_info WHERE location_id=%d", (int)$id);
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
		
	} else { die('Insufficient rights to remove location'); }	
}

function LocationInput($id, $group, $value, $label, $location) {
  $html = "<tr>";
  $html .= "<td>$label</td><td>";
  $html .= LocationInput2($id, $group, $value, $location);
  $html .= "</td>";
  $html .= "</tr>\n";

  return $html;
}

function LocationInput2($id, $group, $value, $location) {
  $html = "<input type='hidden'  name='${group}[]' id='${id}' value='" . utf8entities($location) . "'/>";
  $html .= "<div id='${id}Autocomplete' class='yui-skin-sam'>";
  $html .= "<input class='input' id='${id}Name' style='position:relative;' size='30' type='text' name='${id}Name' value='";
  $html .= utf8entities($value);
  $html .= "'/><div id='${id}NameContainer'></div></div>\n";

  return $html;
}

function LocationScript($id) {
  return "<script type=\"text/javascript\">
//<![CDATA[
  var ${id}SelectHandler = function(sType, aArgs) {
    var oData = aArgs[2];
    document.getElementById(\"${id}\").value = oData[2];
    var x = document.getElementById(\"${id}Name\").className; 
    document.getElementById(\"${id}Name\").className = x.replaceAll(' highlight','');
  };

  var ${id}SelectHandler2 = function() {
    var x = document.getElementById(\"${id}Name\").className;
    if (!x || !x.includes(' highlight'))
      document.getElementById(\"${id}Name\").className+=' highlight';
  };

  Fetch${id} = function(){        
    var locationSource = new YAHOO.util.XHRDataSource(\"ext/locationtxt.php\");
    locationSource.responseSchema = {
      recordDelim: \"\\n\",
      fieldDelim: \"\\t\"
    };
    locationSource.responseType = YAHOO.util.XHRDataSource.TYPE_TEXT;
    locationSource.maxCacheEntries = 60;

    // First AutoComplete
    var locationAutoComp = new YAHOO.widget.AutoComplete(\"${id}Name\",\"${id}NameContainer\",locationSource);
    locationAutoComp.formatResult = function(oResultData, sQuery, sResultMatch) { 

      // some other piece of data defined by schema 
      var moreData1 = oResultData[1];  

      return `<div class='myCustomResult'>
  <span style='font-weight:bold'>\${sResultMatch}</span> / \${moreData1}</div>`; 
    }; 
    locationAutoComp.itemSelectEvent.subscribe(${id}SelectHandler);
    locationAutoComp.textboxFocusEvent.subscribe(${id}SelectHandler2);
    return {
      oDS: locationSource,
      oAC: locationAutoComp
    }
  }();
//]]>
</script>\n";
}

function MapScript(string $elementId, float $lat, float $lng, string $latElem = null, string $lngElem = null) {
  $key = GetGoogleMapsAPIKey();
  if (!empty($latElem) && !empty($lngElem)) {
    $drag = 'true';
  } else {
    $drag = 'false';
  }
  return <<<HEREDOC
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false&key=$key"></script>

<script type="text/javascript">
//<![CDATA[

var simpleGoogleMapsApiExample = simpleGoogleMapsApiExample || {};

simpleGoogleMapsApiExample.map = function (mapDiv, latitude, longitude) {
  "use strict";
  
  var createMap = function (mapDiv, coordinates) {
    var mapOptions = {
      center: coordinates,
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      zoom: 10
    };
    
    return new google.maps.Map(mapDiv, mapOptions);
  };
  
  var initialize = function (mapDiv, latitude, longitude) {
    var coordinates = new google.maps.LatLng(latitude, longitude);
    var map = createMap(mapDiv, coordinates);
    var marker = addMarker(map, coordinates);
  };
  
  var addMarker = function (map, coordinates) {
    var markerOptions = {
      clickable: false,
      map: map,
      draggable: $drag,
      position: coordinates
    };

    var marker = new google.maps.Marker(markerOptions);

    if ($drag) {
      google.maps.event.addListener(marker, 'dragend', function(marker) {
          var latLng = marker.latLng;
          document.getElementById("$latElem").value = latLng.lat();
          document.getElementById("$lngElem").value = latLng.lng();
       });
    }
    return marker;
  }

  initialize(mapDiv, latitude, longitude);
};


google.maps.event.addDomListener(window, 'load', function () {
  // $(document).ready(function () {
  //  "use strict";
  
  simpleGoogleMapsApiExample.map(document.getElementById("$elementId"), $lat, $lng);
});

//]]>
</script>
HEREDOC;
}

?>
