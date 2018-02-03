<?php
include_once 'bootstrap.php';
/**
 * On-the-fly CSS Compression
 * Copyright (c) 2009 and onwards, Manas Tungare.
 * Creative Commons Attribution, Share-Alike.
 *
 * In order to minimize the number and size of HTTP requests for CSS content,
 * this script combines multiple CSS files into a single file and compresses
 * it on-the-fly.
 *
 * To use this in your HTML, link to it in the usual way:
 * <link rel="stylesheet" type="text/css" media="screen, print, projection" href="/css/compressed.css.php" />
 */

global $styles_prefix;
global $include_prefix;
if (!isset($styles_prefix)) {
  $styles_prefix = $include_prefix;
}

$cssFiles = array();
$cssFiles[] = "cust/default/colors.css";
if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/colors.css')) {
  $cssFiles[] = "cust/".CUSTOMIZATIONS."/colors.css";
}

$cssFiles[] = "cust/default/default.css";
if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/default.css')) {
  $cssFiles[] = "cust/".CUSTOMIZATIONS."/default.css";
}

$cssFiles[] = "cust/default/layout.css";
if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/layout.css')) {
  $cssFiles[] = "cust/".CUSTOMIZATIONS."/layout.css";
}

$cssFiles[] = "cust/default/font.css";
if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/font.css')) {
  $cssFiles[] = "cust/".CUSTOMIZATIONS."/font.css";
}

$buffer = "";
foreach ($cssFiles as $cssFile) {
  $buffer .= file_get_contents($include_prefix.$cssFile);
}
// Remove comments
$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
// Remove space after colons
$buffer = str_replace(': ', ':', $buffer);
// Remove whitespace
$buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);
// Enable GZip encoding.
ob_start("ob_gzhandler");
// Enable caching
header('Cache-Control: public');
// Expire in one day
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
// Set the correct MIME type, because Apache won't set it for us
header("Content-type: text/css");
// Write everything out
echo($buffer);
?>