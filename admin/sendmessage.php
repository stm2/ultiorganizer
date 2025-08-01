<?php

include_once 'lib/mail.interface.php';

$title = _("Send message");
$html = "";

ensureLogin();

$method = "get";
$recipients = json_decode(iget('recipients'), true);

if (empty($recipients) && isset($_POST['recipients'])) {
  $method = "post";
  $rpar = $_POST['recipients'];
  $recipients = [];
  foreach ($rpar as $key => $value) {
    $ids = array_map('trim', explode(',', $value));
    foreach ($ids as $id) {
      if (!in_array($id, $recipients)) {
        $recipients[] = $id;
      }
    }
  }
}

$subject = iget('subject');
$message = iget('message');

if (isset($_POST['send'])) {
  $message = $_POST['message'] ?? "";
  $subject = $_POST['subject'] ?? "";
  // Sanitize subject: remove all newlines and carriage returns
  $subject = str_replace(["\r", "\n"], " ", $subject);
  // Optionally trim and limit subject length
  $subject = trim($subject);
  $subject = mb_substr($subject, 0, 200);
  // Sanitize message: allow newlines, but remove any \r
  $message = str_replace(["\r"], "", $message);
  if (empty($message) || empty($subject)) {
    $html .= "<p class='warning'>" . _("You must provide a message and a subject.") . "</p>\n";
  } else {
    // $html .= "<p>Sending message from " . utf8entities(UserInfo($_SESSION['uid'])['name']) . " to " .
    //   utf8entities(iget('recipients')) . "):</p><div style='white-space: pre-wrap;'>" .
    //   utf8entities(print_r($_POST, true)) . "</div>";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

    // Sanitize CC and BCC inputs: split by comma, trim, and filter valid emails
    $cc_raw = $_POST['cc'] ?? '';
    $bcc_raw = $_POST['bcc'] ?? '';
    $cc_list = array_filter(array_map('trim', explode(',', $cc_raw)), function ($email) {
      return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    $bcc_list = array_filter(array_map('trim', explode(',', $bcc_raw)), function ($email) {
      return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    if (!empty($cc_list)) {
      $headers .= "Cc: " . implode(', ', $cc_list) . "\r\n";
    }
    if (!empty($bcc_list)) {
      $headers .= "Bcc: " . implode(', ', $bcc_list) . "\r\n";
    }

    Log2("mail", "send", count($recipients) . " recipients: $subject");
    $clean = true;
    foreach ($recipients as $row) {
      $warning = "";
      if (is_array($row)) {
        $mail = $row['email'];
        $who = $row['name'];
      } else {
        $user = UserInfo($row);
        if (!$user) {
          $warning .= "<p class='warning'>" . sprintf(_("User %s not found."), utf8entities($row)) . "</p>\n";
        }
        // Sanitize and validate user info
        $mail = isset($user['email']) ? str_replace(["\r", "\n"], "", $user['email']) : "";
        if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
          $warning .= "<p class='warning'>" . sprintf(_("Invalid email for user %s: %s"), utf8entities($row), utf8entities($mail)) . "</p>\n";
          $mail = "";
        }
        $who = isset($user['name']) ? str_replace(["\r", "\n"], " ", $user['name']) : $row;
      }
      $html .= $warning;

      if (sendMail($mail, $subject, $message, $headers)) {
        $html .= "<p>" . sprintf(_("Message sent to %s."), utf8entities($who)) . "</p>\n";
      } else {
        $html .= "<p class='warning'>" . sprintf(_("Message could not be sent to %s."), utf8entities($who)) . "</p>\n";
        $clean = false;
      }
    }
    if ($clean)
      $message = $subject = "";
  }
}

if (empty($recipients)) {
  $html .= "<p class='warning'>" . _("No recipients selected.") . "</p>\n";
  showPage($title, $html);


  exit;
}

$subject = utf8entities($subject);
$message = utf8entities($message);

if ($method == "get") {
  $html .= "<form method='post' action='?view=admin/sendmessage&amp;recipients=" . utf8entities(iget('recipients')) . "'>";
} else {
  $html .= "<form method='post' action='?view=admin/sendmessage'>";
  $html .= getHiddenInput($_POST);


  // foreach ($_POST as $key => $value) {
  //   if (is_array($value)) {
  //     foreach ($value as $k => $v) {
  //       $k = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
  //       $v = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
  //       $key_escaped = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
  //       $html .= "<input type='hidden' name='{$key_escaped}[$k]' value='$v'/>\n";
  //     }
  //   } else {
  //     $key_escaped = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
  //     $value_escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  //     $html .= "<input type='hidden' name='$key_escaped' value='$value_escaped'/>\n";
  //   }
  // }
}

$html .= "<h3>" . _("Recipients") . "</h3>\n";
$html .= "<div id='recipients'>";
$warning = "";
$r = 0;
foreach ($recipients as $row) {
  if (is_array($row)) {
    $mail = $row['email'];
    $who = $row['name'];
  } else {
    $user = UserInfo($row);
    if (!$user) {
      $warning .= "<p class='warning'>" . sprintf(_("User %s not found."), utf8entities($row)) . "</p>\n";
    }
    $mail = $user['email'] ?? "";
    $who = $user['name'] ?? $row;
  }
  if (++$r > 1)
    $html .= ", ";
  if (!hasViewUsersRight()) {
    $mail = obfuscateEmail($mail);
  }
  $html .= utf8entities($who) . " (" . utf8entities($mail) . ")";
}
$html .= "</div>\n";
$html .= $warning;

$html .= "<h3>" . _("CC") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='200' name='cc' value=''/><br />";
$html .= "<p class='info'>" . _("Separate multiple addresses with commas.") . "</p>\n";

$html .= "<h3>" . _("BCC") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='200' name='bcc' value=''/><br />";
$html .= "<p class='info'>" . _("Separate multiple addresses with commas.") . "</p>\n";

$html .= "<h3>" . _("Subject") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='100' name='subject' value='$subject'/><br />";

$html .= "<h3>" . _("Message") . "</h3>\n";
$html .= "<div><textarea  class='input borderbox' rows='20' maxlength='50000' id='message' name='message'>$message</textarea></div>\n";
$html .= "<input class='button' name='send' type='submit' value='" . utf8entities(_("Send")) . "'/>";

$html .= "</form>";

showPage($title, $html);
