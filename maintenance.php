<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml'>
<head>
        <title>Ultiorganizer - Maintenance mode</title>
</head>
<body>
<?php
if (defined("MAINTENANCE_END")) {
  $timestamp = strtotime(MAINTENANCE_END);
  $maintenance_end = date("r", $timestamp);
}
?>
<h1>Maintenance mode</h1>

<p>This site has been temporarily set to maintenance mode. 
<?php 
if (isset($maintenance_end)) {
  echo " It should be available again before " . $maintenance_end . " if everything goes as planned.";
}
?>
</p>

<p>Diese Seite ist wegen Wartungsarbeiten vorübergehend nicht erreichbar. 
<?php 
if (isset($maintenance_end)) {
  echo " Bis spätestens " . $maintenance_end . " sollte die Seite wieder erreichbar sein, wenn alles nach Plan verläuft.";
}
?>
</p>
</body></html>
