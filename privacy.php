<?php
$html = "";
global $include_prefix;

$title = _("Privacy Policy");

$htmlfile = 'locale/'.getSessionLocale().'/LC_MESSAGES/privacy.html';

if (is_file('cust/'.CUSTOMIZATIONS.'/'.$htmlfile)) {
  $html .= file_get_contents('cust/'.CUSTOMIZATIONS.'/'. $htmlfile);
}else{
  $html .= file_get_contents($htmlfile);
}


showPage($title, $html);
?>