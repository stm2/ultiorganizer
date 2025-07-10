<?php

/**
 * Mail interface module.
 * Supports real sending and simulation (write to file).
 */

global $serverConf;
if (!isset($serverConf['MailSimulate'])) {
  $serverConf['MailSimulate'] = "false";
}
if (!isset($serverConf['MailSimulateFile'])) {
  $serverConf['MailSimulateFile'] = UPLOAD_DIR . 'tmp/mailsim.log';
}

/**
 * Send an email, or simulate by writing to a file.
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param string $headers
 * @return bool
 */
function sendMail($to, $subject, $message, $headers = "") {
  global $serverConf;
  if (empty($to) || empty($subject)) {
    Log2("mail", "error", "sendMail called with empty parameters: to=$to, subject=$subject, message=$message");
    return false;
  }
  if (empty($headers)) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";
  }

  // handled by caller
  // Log2("mail", "send", "Sending mail to $to with subject: $subject");

  if ($serverConf['MailSimulate'] == "true") {
    $log = "DATE: " . date('Y-m-d H:i:s') . "\nTO: $to\nSUBJECT: $subject\nHEADERS: $headers\nMESSAGE:\n$message\n----\n";
    return empty(file_put_contents($serverConf['MailSimulateFile'], $log, FILE_APPEND)) ? false : true;
  } else {
    return mail($to, $subject, $message, $headers);
  }
}
