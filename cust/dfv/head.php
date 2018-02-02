<?php
function logo() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	return "<img class='logo' src='".$styles_prefix."cust/dfv/DFV-Logo2018.jpg' alt='"._("DFV Logo")."'/>";
}

function pageHeader() {
  global $include_prefix;
  return "<div><a style='float:left;' href='http://www.frisbeesportverband.de' class='header_text'><img class='header_logo' src='".$include_prefix."cust/dfv/DFV-Logo2018.jpg' alt='"._("DFV Logo")."' /></a><div style='float:left;padding:16px 20px' class='header_text' style='align:right'>" . _("Ultiorganizer") . "</span></div>\n";
}

?>
