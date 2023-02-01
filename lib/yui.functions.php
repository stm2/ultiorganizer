<?php 
include_once $include_prefix.'lib/yuiloader/phploader/loader.php';

function yuiLoad($libs) {
	$loader = new YAHOO_util_Loader("2.8.0r4");
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	$loader->base = $styles_prefix."script/yui/";
	foreach ($libs as $lib) {
		$loader->loadSingle($lib);
	}
	return $loader->tags();
}

function getCalendarScript($fieldIds) {
  $html = '
<link rel="stylesheet" type="text/css" href="script/yui/calendar/calendar.css" />

<script type="text/javascript">
<!--
YAHOO.namespace("calendar");
    
YAHOO.calendar.init = function() {

  var handleCal = function(id, cal) {
    var containerDiv = YAHOO.util.Dom.get(id + "Container");

    if(containerDiv.style.display == "none") {
      updateCal(id, cal);
      cal.show();
    } else {
      cal.hide();
    }
  };

';
  foreach ($fieldIds as $i => $fieldId) {
    $html .= "
  YAHOO.calendar.cal{$i} = new YAHOO.widget.Calendar('cal{$i}','${fieldId}Container');
  YAHOO.calendar.cal{$i}.cfg.setProperty('START_WEEKDAY', '1');
  YAHOO.calendar.cal{$i}.render();
    
  function handleCal{$i}Button(e) {
    handleCal('{$fieldId}', YAHOO.calendar.cal{$i});
  }
    
  // Listener to show the Calendar when the button is clicked
  YAHOO.util.Event.addListener('show{$fieldId}', 'click', handleCal{$i}Button);
  YAHOO.calendar.cal{$i}.hide();
    
  function handleSelect{$i}(type, args, obj) {
    var dates = args[0];
    var date = dates[0];
    var year = date[0], month = date[1], day = date[2];
    
    var txtDate = document.getElementById('${fieldId}');
    txtDate.value = day + '.' + month + '.' + year;
  }
    
  function updateCal(input, obj) {
    var txtDate = document.getElementById(input);
    if (txtDate.value != '') {
      var date = txtDate.value.split('.');
      obj.select(date[1] + '/' + date[0] + '/' + date[2]);
      obj.cfg.setProperty('pagedate', date[1] + '/' + date[2]);
      obj.render();
    }
  }
    
  YAHOO.calendar.cal{$i}.selectEvent.subscribe(handleSelect{$i}, YAHOO.calendar.cal{$i}, true);

";
  }

  $html .= '
}

YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
//-->
</script>';
  
  return $html;
}

function getCalendarInput($id, $val) {
  $val = utf8entities($val);
  $html = "<input class='input' size='12' maxlength='10' id='$id' name='$id' value='$val'/>&nbsp;&nbsp;";
  $html .= "<button type='button' class='button' id='show$id'><img width='12' height='10' src='images/calendar.gif' alt='cal'/></button><br />";
  $html .= "<div id='${id}Container'></div>\n";
  return $html;
}
?>