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
	return "<a href='http://www.frisbeesportverband.de' class='header_text'>"._("Ultiorganizer")."</a><br/>\n";
}

?>
