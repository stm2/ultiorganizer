<?php
if (IsRegistered($_SESSION['uid'])) {
  header("location:?view=frontpage");
}

$title = _("Login");
$userId = "";
if (isset($_POST['user']) && $_POST['user'])
  $userId = $_POST['user'];
if (!$userId && isset($_GET['user']) && $_GET['user'])
  $userId = $_GET['user'];

$html = "";

// if (isset($_POST['login'])) {
if (!empty($_GET['query']))
  $query_string = $_GET['query'];
else
  $query_string = '';

$html .= loginForm($query_string);

showPage($title, $html);
?>