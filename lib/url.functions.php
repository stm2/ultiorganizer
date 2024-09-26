<?php 

function GetUrlById($urlId) {
	$query = sprintf("SELECT * FROM uo_urls WHERE url_id=%d",
		(int)$urlId);
	return DBQueryToRow($query);
}

function GetUrl($owner, $ownerId, $type) {
	$query = sprintf("SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND type='%s'",
		mysql_adapt_real_escape_string($owner),
		mysql_adapt_real_escape_string($ownerId),
		mysql_adapt_real_escape_string($type));
	return DBQueryToRow($query);
}

function GetUrlList($owner, $ownerId, $medialinks=false) {
	if($medialinks){
		$query = sprintf("SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND ismedialink=1",
			mysql_adapt_real_escape_string($owner),
			mysql_adapt_real_escape_string($ownerId));
	}else{
		$query = sprintf("SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND ismedialink=0",
			mysql_adapt_real_escape_string($owner),
			mysql_adapt_real_escape_string($ownerId));
	}
	$query .= " ORDER BY ordering, type, name";
	return DBQueryToArray($query);
}

function GetUrlListByTypeArray($typearray, $ownerId) {
	foreach($typearray as $type){
		$list[] = "'".mysql_adapt_real_escape_string($type)."'";
	}
	$liststring = implode(",", $list);
	$query = "SELECT * FROM uo_urls WHERE type IN($liststring) AND owner_id='".mysql_adapt_real_escape_string($ownerId)."' ORDER BY ordering,type, name";
	return DBQueryToArray($query);
}

function GetMediaUrlList($owner, $ownerId, $type="") {
	
	if($owner=="game"){
		$query = sprintf("SELECT urls.*, u.name AS publisher, e.time
			FROM uo_urls urls 
			LEFT JOIN uo_users u ON (u.id=urls.publisher_id)
			LEFT JOIN uo_gameevent e ON(e.info=urls.url_id)
			WHERE urls.owner='%s' AND urls.owner_id='%s' AND urls.ismedialink=1",
			mysql_adapt_real_escape_string($owner),
			mysql_adapt_real_escape_string($ownerId));
	}else{
		$query = sprintf("SELECT urls.*, u.name AS publisher FROM uo_urls urls 
			LEFT JOIN uo_users u ON (u.id=urls.publisher_id)
			WHERE urls.owner='%s' AND urls.owner_id='%s' AND urls.ismedialink=1",
			mysql_adapt_real_escape_string($owner),
			mysql_adapt_real_escape_string($ownerId));
	}
	if(!empty($type)){
	  $query.= sprintf(" AND urls.type='%s'",mysql_adapt_real_escape_string($type));
	}
	
	return DBQueryToArray($query);
}

function GetUrlTypes() {
	$types = array();
	$dbtype = array("homepage", "forum", "twitter", "blogger", "facebook", "flickr", "picasa", "other");
	$translation = array(_("Homepage"), _("Forum"), _("Twitter"), _("Blogger"), _("Facebook"), _("Flickr"), _("Picasa"), _("Other"));
	$icon = array("homepage.png", "forum.png", "twitter.png", "blogger.png", "facebook.png", "flickr.png", "picasa.png", "other.png");
	
	for($i=0;$i<count($dbtype);$i++){
		$types[] = array('type'=> $dbtype[$i], 'name'=> $translation[$i], 'icon'=> $icon[$i]);
	}
	return $types;
}

function UrlTable(array $urls, array $columns = null, callable|false $can_delete = null, $tablespec = '',
  callable $is_shown = null) {
  if (empty($urls))
    return '';

  if (empty($columns)) {
    $columns = ['type', 'url'];
  }

  if ($can_delete === false)
    $can_delete = function ($url) {
      return false;
    };

  $count = 0;

  $html = "<table $tablespec>";
  $heading = '';
  foreach ($columns as $head => $key) {
    if (gettype($head) !== 'integer')
      $heading .= "<th class='urltable_header'>" . utf8entities($head) . "</th>";
  }
  if (!empty($heading))
    $html .= "<tr>$heading</tr>\n";

  foreach ($urls as $url) {
    if ($is_shown == null || $is_shown($url)) {
      ++$count;
      $html .= "<tr style='border-bottom-style:solid; border-bottom-width:1px;'>";
      foreach ($columns as $key) {
        if (gettype($key) === 'string') {
          $html .= "<td class='urltable_$key'>";
          if ($key === 'type') {
            $html .= "<img width='16' height='16' src='images/linkicons/" . $url['type'] . ".png' alt='" . $url['type'] .
              "'/></td>";
          } elseif ($key === 'url') {
            if (!empty($url['name'])) {
              $html .= "<a href='" . utf8entities($url['url']) . "'>" . utf8entities($url['name']) . "</a> (" .
                utf8entities($url['url']) . ")";
            } else {
              $html .= "<a href='" . utf8entities($url['url']) . "'>" . utf8entities($url['url']) . "</a>";
            }
          } else {
            $html .= utf8entities($url[$key]);
          }
        } else {
          $col = $key($url);
          debug_to_apache([$url, $col]);
          $html .= "<td class='urltable_${col['type']}'>";
          $html .= utf8entities($col['value']);
        }
        $html .= "</td>";
      }

      if (empty($can_delete) || $can_delete($url))
        $html .= "<td class='urltable_delete right'><input class='deletebutton' type='image' src='images/remove.png' name='removeurl' value='X' alt='X' onclick='setId(" .
          $url['url_id'] . ");'/></td>";
      else
        $html .= "<td class='urltable_delete empty'></td>";
      $html .= "</tr>\n";
    }
  }
  $html .= "</table>\n";
  if ($count)
    return $html;
  else
    return "";
}

function UrlLinks(array $urls) {
  if (empty($urls))
    return '';
  foreach ($urls as $url) {
    if ($url['type'] == "menulink") {
      echo "<a class='subnav' href='" . utf8entities($url['url']) . "'>&raquo; " . utf8entities(U_($url['name'])) .
        "</a>\n";
    } elseif ($url['type'] == "menumail") {
      echo "<a class='subnav' href='mailto:" . utf8entities($url['url']) . "'>@ " . utf8entities(U_($url['name'])) .
        "</a>\n";
    }
  }
}

function GetMediaUrlTypes() {
	$types = array();
	$dbtype = array("image", "video", "live");
	$translation = array(_("Image"), _("Video"),_("Live video"));
	$icon = array("image.png", "video.png", "live.png");
	
	for($i=0;$i<count($dbtype);$i++){
		$types[] = array('type'=> $dbtype[$i], 'name'=> $translation[$i], 'icon'=> $icon[$i]);
	}
	return $types;
}

function AddUrl($urlparams) {
	if (isSuperAdmin()){
		$url = SafeUrl($urlparams['url']);
	
		$query = sprintf("INSERT INTO uo_urls (owner,owner_id,type,name,url,ordering)
				VALUES('%s','%s','%s','%s','%s','%s')",
			mysql_adapt_real_escape_string($urlparams['owner']),
			mysql_adapt_real_escape_string($urlparams['owner_id']),
			mysql_adapt_real_escape_string($urlparams['type']),
			mysql_adapt_real_escape_string($urlparams['name']),
			mysql_adapt_real_escape_string($url),
		    mysql_adapt_real_escape_string($urlparams['ordering']));
			
		return DBQuery($query);
	} else { die('Insufficient rights to add url'); }	
}

function AddMail($urlparams) {
	if (isSuperAdmin()){
		$query = sprintf("INSERT INTO uo_urls (owner,owner_id,type,name,url,ordering)
				VALUES('%s','%s','%s','%s','%s','%s')",
			mysql_adapt_real_escape_string($urlparams['owner']),
			mysql_adapt_real_escape_string($urlparams['owner_id']),
			mysql_adapt_real_escape_string($urlparams['type']),
			mysql_adapt_real_escape_string($urlparams['name']),
			mysql_adapt_real_escape_string($urlparams['url']),
			mysql_adapt_real_escape_string($urlparams['ordering']));
		return DBQuery($query);
	} else { die('Insufficient rights to add url'); }	
}

function SetUrl($urlparams) {
	if (isSuperAdmin()){
		$url = SafeUrl($urlparams['url']);
	
		$query = sprintf("UPDATE uo_urls SET owner='%s',owner_id='%s',type='%s',name='%s',url='%s', ordering='%s'
			WHERE url_id=%d",
			mysql_adapt_real_escape_string($urlparams['owner']),
			mysql_adapt_real_escape_string($urlparams['owner_id']),
			mysql_adapt_real_escape_string($urlparams['type']),
			mysql_adapt_real_escape_string($urlparams['name']),
			mysql_adapt_real_escape_string($url),
			mysql_adapt_real_escape_string($urlparams['ordering']),
			(int)$urlparams['url_id']);
		return DBQuery($query);
	} else { die('Insufficient rights to add url'); }	
}

function SetMail($urlparams) {
	if (isSuperAdmin()){
		$query = sprintf("UPDATE uo_urls SET owner='%s',owner_id='%s',type='%s',name='%s',url='%s', ordering='%s'
			WHERE url_id=%d",
			mysql_adapt_real_escape_string($urlparams['owner']),
			mysql_adapt_real_escape_string($urlparams['owner_id']),
			mysql_adapt_real_escape_string($urlparams['type']),
			mysql_adapt_real_escape_string($urlparams['name']),
			mysql_adapt_real_escape_string($urlparams['url']),
			mysql_adapt_real_escape_string($urlparams['ordering']),
			(int)$urlparams['url_id']);
		return DBQuery($query);
	} else { die('Insufficient rights to add url'); }	
}

function RemoveUrl($urlId) {
	if (isSuperAdmin()){
		$query = sprintf("DELETE FROM uo_urls WHERE url_id=%d",
			(int)$urlId);
		return DBQuery($query);
	} else { die('Insufficient rights to remove url'); }
}

function AddMediaUrl($urlparams) {
	if (hasAddMediaRight()){
	
		$url = SafeUrl($urlparams['url']);
		
		$query = sprintf("INSERT INTO uo_urls (owner,owner_id,type,name,url,ismedialink,mediaowner,publisher_id)
				VALUES('%s','%s','%s','%s','%s',1,'%s','%s')",
			mysql_adapt_real_escape_string($urlparams['owner']),
			mysql_adapt_real_escape_string($urlparams['owner_id']),
			mysql_adapt_real_escape_string($urlparams['type']),
			mysql_adapt_real_escape_string($urlparams['name']),
			mysql_adapt_real_escape_string($url),
			mysql_adapt_real_escape_string($urlparams['mediaowner']),
			mysql_adapt_real_escape_string($urlparams['publisher_id']));
		Log2("Media","Add",$urlparams['url']);
		DBQuery($query);
		return mysql_adapt_insert_id();
	} else { die('Insufficient rights to add media'); }	
}

function RemoveMediaUrl($urlId) {
	if (hasAddMediaRight()){
		$query = sprintf("DELETE FROM uo_urls WHERE url_id=%d",
			(int)$urlId);
		Log2("Media","Remove",$urlId);
		return DBQuery($query);
	} else { die('Insufficient rights to remove url'); }
}
?>
