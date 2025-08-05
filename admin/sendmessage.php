<?php
include_once 'lib/mail.interface.php';

$title = _("Send message");
$html = "";

ensureLogin();

$method = "get";
$recipients = json_decode(iget('recipients'), true);

// Parse a recipient string into ['name'=>..., 'email'=>...].
function parseRecipient($input) {
  $input = trim($input);
  // Name <email@example.com>
  if (preg_match('/^(.+?)\s*<([^>]+)>$/', $input, $matches)) {
    $name = trim($matches[1]);
    $email = trim($matches[2]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ['error' => sprintf(_("Invalid email address: %s"), utf8entities($email))];
    }
    return ['name' => $name, 'email' => $email];
  }
  // Plain email
  if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
    return ['name' => $input, 'email' => $input];
  }
  // Username: look up in UserInfo
  $user = UserInfo($input);
  if (!$user || empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
    return ['error' => sprintf(_("User %s not found or has invalid email."), utf8entities($input))];
  }
  $name = isset($user['name']) ? $user['name'] : $input;
  return ['name' => $name, 'email' => $user['email']];
}

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
  // Collect all recipients from recipients, to, cc, bcc fields
  $all_recipient_strings = [];
  // Recipients from selection
  foreach ($recipients as $row) {
    if (is_array($row) && isset($row['email']) && isset($row['name'])) {
      $all_recipient_strings[] = $row['name'] . ' <' . $row['email'] . '>';
    } else if (is_string($row)) {
      $all_recipient_strings[] = $row;
    }
  }
  // To/CC/BCC fields, pre-fill from posted values
  $to_raw = $_POST['to'] ?? '';
  $cc_raw = $_POST['cc'] ?? '';
  $bcc_raw = $_POST['bcc'] ?? '';
  $to_list = array_filter(array_map('trim', explode(',', $to_raw)));
  $all_recipient_strings = array_merge($all_recipient_strings, $to_list);
  // Do not add cc/bcc to recipients; only use for headers

  // Parse and deduplicate recipients (to, cc, bcc), collect all errors
  $final_recipients = [];
  $seen_emails = [];
  $recipient_errors = [];
  // To
  foreach ($all_recipient_strings as $recstr) {
    $parsed = parseRecipient($recstr);
    if (isset($parsed['error'])) {
      $recipient_errors[] = $parsed['error'];
      continue;
    }
    $email = strtolower($parsed['email']);
    if (!isset($seen_emails[$email])) {
      $final_recipients[] = $parsed;
      $seen_emails[$email] = true;
    }
  }
  // CC
  $cc_list = array_filter(array_map('trim', explode(',', $cc_raw)));
  $cc_header = [];
  foreach ($cc_list as $cc_entry) {
    $parsed = parseRecipient($cc_entry);
    if (isset($parsed['error'])) {
      $recipient_errors[] = $parsed['error'];
      continue;
    }
    if (isset($parsed['email']) && filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
      if (!empty($parsed['name']) && $parsed['name'] !== $parsed['email']) {
        $cc_header[] = sprintf('"%s" <%s>', addslashes($parsed['name']), $parsed['email']);
      } else {
        $cc_header[] = $parsed['email'];
      }
    }
  }
  // BCC
  $bcc_list = array_filter(array_map('trim', explode(',', $bcc_raw)));
  $bcc_header = [];
  foreach ($bcc_list as $bcc_entry) {
    $parsed = parseRecipient($bcc_entry);
    if (isset($parsed['error'])) {
      $recipient_errors[] = $parsed['error'];
      continue;
    }
    if (isset($parsed['email']) && filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
      if (!empty($parsed['name']) && $parsed['name'] !== $parsed['email']) {
        $bcc_header[] = sprintf('"%s" <%s>', addslashes($parsed['name']), $parsed['email']);
      } else {
        $bcc_header[] = $parsed['email'];
      }
    }
  }

  // Sanitize subject: remove all newlines and carriage returns
  $subject = str_replace(["\r", "\n"], " ", $subject);
  // Optionally trim and limit subject length
  $subject = trim($subject);
  $subject = mb_substr($subject, 0, 200);
  // Sanitize message: allow newlines, but remove any \r
  $message = str_replace(["\r"], "", $message);
  $send_with_errors = isset($_POST['send_with_errors']);
  foreach ($recipient_errors as $err) {
    $html .= "<p class='warning'>" . $err . "</p>\n";
  }
  if (empty($message) || empty($subject)) {
    $html .= "<p class='warning'>" . _("You must provide a message and a subject.") . "</p>\n";
    $html .= "<p class='warning'>" . _("Message not sent! Please correct the errors and try again.") . "</p>\n";
  } elseif (!empty($recipient_errors) && !$send_with_errors) {
    $show_send_buttons = true;
    $html .= "<p class='warning'>" . _("Message not sent! Please correct the errors and try again.") . "</p>\n";
  } elseif (empty($final_recipients)) {
    $html .= "<p class='warning'>" . _("No valid recipients found.") . "</p>\n";
    $html .= "<p class='warning'>" . _("Message not sent! Please correct the errors and try again.") . "</p>\n";
  } else {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";


    if (!empty($cc_header)) {
      $headers .= "Cc: " . implode(', ', $cc_header) . "\r\n";
    }
    if (!empty($bcc_header)) {
      $headers .= "Bcc: " . implode(', ', $bcc_header) . "\r\n";
    }

    Log2("mail", "send", count($final_recipients) . " recipients: $subject");
    $clean = true;
    foreach ($final_recipients as $row) {
      $mail = $row['email'];
      $who = $row['name'];
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
  $_post_filtered = $_POST;
  unset($_post_filtered['send_with_errors']);
  $html .= getHiddenInput($_post_filtered);


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

$html .= "<h3>" . _("To") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='200' name='to' value='" . htmlspecialchars($to_raw, ENT_QUOTES, 'UTF-8') . "'/><br />";
$html .= "<p class='info'>" . _("Separate multiple addresses with commas.") . "</p>\n";

$html .= "<h3>" . _("CC") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='200' name='cc' value='" . htmlspecialchars($cc_raw, ENT_QUOTES, 'UTF-8') . "'/><br />";
$html .= "<p class='info'>" . _("Separate multiple addresses with commas.") . "</p>\n";

$html .= "<h3>" . _("BCC") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='200' name='bcc' value='" . htmlspecialchars($bcc_raw, ENT_QUOTES, 'UTF-8') . "'/><br />";
$html .= "<p class='info'>" . _("Separate multiple addresses with commas.") . "</p>\n";

$html .= "<h3>" . _("Subject") . "</h3>\n";
$html .= "<input class='input' size='50' maxlength='100' name='subject' value='$subject'/><br />";

$html .= "<h3>" . _("Message") . "</h3>\n";
$html .= "<div><textarea  class='input borderbox' rows='20' maxlength='50000' id='message' name='message'>$message</textarea></div>\n";


if (isset($show_send_buttons) && $show_send_buttons) {
  $html .=
    ("<input class='button' name='send_with_errors' type='submit' value='" . utf8entities(_("Send with errors")) . "'/>&nbsp;"
      . "<input class='button' name='check_again' type='submit' value='" . utf8entities(_("Check again")) . "'/>");
} else {
  $html .= ("<input class='button' name='send' type='submit' value='" . utf8entities(_("Send")) . "'/>");
}

$html .= "</form>";

showPage($title, $html);
