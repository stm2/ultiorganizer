<?php
$title = _("User Guide");
$html = "";

$htmlfile = 'locale/' . getSessionLocale() . '/LC_MESSAGES/user_guide.html';

if (is_file('cust/' . CUSTOMIZATIONS . '/' . $htmlfile)) {
  $html .= file_get_contents('cust/' . CUSTOMIZATIONS . '/' . $htmlfile);
} else if (is_file($htmlfile)) {
  $html .= file_get_contents($htmlfile);
} else {
  $html .= "<p>" . _("Sorry, user guide not available for your language.") . "</p>";
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/", "", $querystring);

showPage($title, $html);

?>