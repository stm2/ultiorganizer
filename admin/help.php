<?php
$html = "";
$print = 0;
if (!empty($_GET["print"])) {
  $print = intval($_GET["print"]);
}
// common page
$title = _("Helps");
pageTop($title, $print);
leftMenu(0, true, $print);
contentStart();

$html .= file_get_contents('locale/' . getSessionLocale() . '/LC_MESSAGES/help.html');

echo $html;
contentEnd();
pageEnd();
?>