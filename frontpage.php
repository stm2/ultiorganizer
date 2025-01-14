<?php
$html = "";
$title = _("Frontpage");

if(iget("hideseason")) {
  $propId = getPropId($user, 'editseason', iget("hideseason"));
  RemoveEditSeason($user, $propId);
  header("location:?view=frontpage");
  exit;
}

$html .= "<h1>". _("Welcome to the Ultiorganizer") ."</h1>";

$htmlfile = 'locale/'.getSessionLocale().'/LC_MESSAGES/welcome.html';

if (is_file('cust/'.CUSTOMIZATIONS.'/'.$htmlfile)) {
  $html .= file_get_contents('cust/'.CUSTOMIZATIONS.'/'. $htmlfile);
}else{
  $html .= file_get_contents($htmlfile);
}

$html .= "<p>";
$html .= "<a href='?view=user_guide'>"._("User Guide")."</a>\n";
$html .= "</p>";

$html .= "<p>";
$html .= _("In case of feedback, improvement ideas or any other questions contact to:");
$urls = GetUrlListByTypeArray(array("admin"),0);
foreach($urls as $url){
  $html .= "<br/><a href='mailto:".$url['url']."'>".U_($url['name'])."</a>\n";
}
$html .= "</p>";

showPage($title, $html);
?>
