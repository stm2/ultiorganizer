<?php

$title = _("User Guide");
$html = "";
$print = iget("print");

$htmlfile = 'locale/'.getSessionLocale().'/LC_MESSAGES/user_guide.html';

if (is_file('cust/'.CUSTOMIZATIONS.'/'.$htmlfile)) {
  $html .= file_get_contents('cust/'.CUSTOMIZATIONS.'/'. $htmlfile);
}else if (is_file($htmlfile)){
  $html .= file_get_contents($htmlfile);
} else {
  $html .= "<p>" . _("Sorry, user guide not available for your language.") . "</p>";
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/","",$querystring);
if($print){
  $html .= "<hr/><div style='text-align:right'><a href='?".utf8entities($querystring)."'>"._("Return")."</a></div>";
}else{
  $html .= "<hr/><div style='text-align:right'><a href='?".utf8entities($querystring)."&amp;print=1'>"._("Printable version")."</a></div>";
}
if($print){
  showPrintablePage($title, $html);
}else{
  showPage($title, $html);
}

?>