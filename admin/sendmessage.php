<?php
$title = _("Send message");
$html = "";

ensureLogin();

$recipients = json_decode(iget('recipients'), true);
$subject = iget('subject');
$message = iget('message');

if (isset($_POST['send'])) {
  $message = $_POST['message'] ?? "";
  $subject = $_POST['subject'] ?? "";
  if (empty($message) && empty($subject)) {
    $html .= "<p class='warning'>" . _("You must provide a message or a subject.") . "</p>\n";
  } else {
    $html .= "<p>Sending message from " . utf8entities(UserInfo($_SESSION['uid'])['name']) . " to " .
      utf8entities(iget('recipients')) . "):</p><div style='white-space: pre-wrap;'>" .
      utf8entities(print_r($_POST, true)) . "</div>";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";

    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

    Log2("mail", "send", count($recipients) . " recipients: $subject");
    foreach ($recipients as $row) {
      $mail = $row['email'];
      $who = $row['name'];
      if (mail($mail, $subject, $message, $headers)) {
        $html .= "<p>" . sprintf(_("Message sent to %s"), utf8entities($who)) . "</p>\n";
        $message = $subject = "";
      } else {
        $html .= "<p class='warning'>" . sprintf(_("Message could not be sent to %s"), utf8entities($who)) . "!</p>\n";
      }
    }
  }
}

$subject = utf8entities($subject);
$message = utf8entities($message);

$html .= "<form method='post' action='?view=admin/sendmessage&amp;recipients=" . utf8entities(iget('recipients')) . "'>";

$html .= "<h3>" . _("Recipients") . "</h3>\n";
$html .= "<div id='recipients'>";
$r = 0;
foreach ($recipients as $row) {
  $mail = $row['email'];
  $who = $row['name'];
  if (++$r > 1)
    $html .= ", ";
  $html .= utf8entities($who) . " (" . utf8entities($mail) . ")";
}
$html .= "</div>\n";

$html .= "<h3>" . _("Subject") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='100' name='subject' value='$subject'/><br />";

$html .= "<h3>" . _("Message") . "</h3>\n";
$html .= "<div><textarea  class='input borderbox' rows='20' id='message' name='message'>$message</textarea></div>\n";
$html .= "<input class='button' name='send' type='submit' value='" . utf8entities(_("Send")) . "'/>";

$html .= "</form>";

showPage($title, $html);

