<?php
function logo() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	return "<img class='header_logo' src='".$styles_prefix."cust/dfv/logo.gif' alt='"._("DFV Logo")."'/>";
}

function pageHeader() {
  global $include_prefix;
	return "<div><a href='http://www.frisbeesportverband.de' class='header_text'><img class='header_logo' src='".$include_prefix."cust/dfv/logo.gif' alt='"._("DFV Logo")."' style='height:45px;'/>"._("DFV - Ultiorganizer")."</a></div>\n";
}

?>
