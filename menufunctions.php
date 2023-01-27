<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/statistical.functions.php';

if (is_file('cust/' . CUSTOMIZATIONS . '/head.php')) {
  include_once 'cust/' . CUSTOMIZATIONS . '/head.php';
} else {
  include_once 'cust/default/head.php';
}

/**
 * Shows html content with ultiorganizer menus and layout.
 *
 * @param string $title
 *          page's title
 * @param string $html
 *          page's content
 */
function showPage($title, $html) {
  preContent($title);
  echo $html;
  postContent();
}

function showPageMobile($title, $html) {
  mobilePageTop($title);
  echo $html;
  mobilePageEnd();
}

$headerScripts = array();

function addHeaderScript($name) {
  global $headerScripts;
  $headerScripts[] = $name;
}

$headerFunctions = array();

function addHeaderCallback($callback) {
  global $headerFunctions;
  $headerFunctions[] = $callback;
}

function addHeaderText($text) {
  global $headerTexts;
  $headerTexts[] = $text;
}

function preContent($title) {
  global $headerScripts;
  global $headerFunctions;
  global $headerTexts;

  pageTopHeadOpen($title);
  if (!empty($headerScripts)) {
    foreach ($headerScripts as $script) {
      include_once ($script);
    }
  }
  if (!empty($headerFunctions)) {
    foreach ($headerFunctions as $function) {
      $function();
    }
  }
  if (!empty($headerTexts)) {
    foreach ($headerTexts as $text) {
      echo $text;
    }
  }
  pageTopHeadClose($title);
  leftMenu($title);
  contentStart();
}

function postContent() {
  contentEnd();
  pageEnd();
}

function getPrintMode() {
  if (isset($_SESSION['print']) && $_SESSION['print'] == 1)
    return 1;
  return 0;
}

/**
 * Produce html code for page top.
 *
 * @param string $title
 *          - title of the page
 * @param boolean $printable
 *          - if true then no header produced.
 */
function pageTop($title, $printable = false) {
  pageTopHeadOpen($title);
  pageTopHeadClose($title, $printable);
}

/**
 * HTML code with page meta information.
 * Leaves <head> tag open.
 *
 * @param string $title
 *          - the page title
 */
function pageTopHeadOpen($title) {
  global $include_prefix;
  $lang = explode("_", getSessionLocale());
  $lang = $lang[0];
  $icon = $include_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png";

  echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
		<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='" . $lang . "' lang='" . $lang . "'";
  global $serverConf;
  if (IsFacebookEnabled()) {
    echo "\n		xmlns:fb=\"http://www.facebook.com/2008/fbml\"";
  }
  echo ">\n<head>
		<meta http-equiv=\"Content-Style-Type\" content=\"text/css\"/>
		<meta http-equiv=\"Content-Script-Type\" content=\"text/javascript\"/>";
  // no cache
  echo "<meta http-equiv=\"Pragma\" content=\"no-cache\"/>";
  echo "<meta http-equiv=\"Expires\" content=\"-1\"/>";

  echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";

  echo "<link rel='icon' type='image/png' href='$icon' />
		<title>" . GetPageTitle() . "" . $title . "</title>\n";
  echo styles();

  include $include_prefix . 'script/common.js.inc';
  global $include_prefix;
  include_once $include_prefix . 'script/help.js.inc';
}

/**
 * HTML code for header and navigation bar.
 *
 * @param string $title
 *          - title of the page
 * @param boolean $printable
 *          - if true then no header produced.
 * @param string $bodyfunctions
 *          - insert additional attributes/functions in body tag
 */
function pageTopHeadClose($title, $printable = false, $bodyfunctions = "") {
  if (isset($_SESSION['uid'])) {
    $user = $_SESSION['uid'];
  } else {
    $user = "anonymous";
  }

  $printable |= getPrintMode();

  if ((!empty($_GET['view']) && $_GET['view'] == 'logout') || !isset($_SERVER['QUERY_STRING'])) {
    $query_string = "view=frontpage";
  } else {
    $query_string = $_SERVER['QUERY_STRING'];
  }

  global $serverConf;
  global $styles_prefix;
  global $include_prefix;

  if (!isset($styles_prefix)) {
    $styles_prefix = $include_prefix;
  }
  $printclass = $printable ? "class='print'" : "";

  echo "</head><body style='overflow-y:scroll;' $printclass " . $bodyfunctions . ">\n";
  echo "<div class='page'>\n";

  if (!$printable) {
    echo "<header class='page_top'>\n";

    // top header left part can be customized
    echo "<div class='topheader_left'>\n";
    echo pageHeader();
    echo "</div><!--topheader_left-->";

    // top header right part contains common elements
    echo "<div class='topheader_right'>";
    echo "<table border='0' cellpadding='0' cellspacing='0' style='width:95%;white-space: nowrap;'>\n";

    // 1st row: Locale selection
    echo "<tr>";
    echo "<td colspan='3' class='right' style='vertical-align:top;'>" . localeSelection() . "</td>";
    echo "</tr>";

    // 2nd row: User Log in
    echo "<tr>\n";
    echo "<td class='left' style='padding-top:5px'>";

    if (IsFacebookEnabled() && $user == 'anonymous') {
      echo "<div id='fb-root'></div>\n";
      echo "<fb:login-button perms='email,publish_stream,offline_access'/>\n";
    }

    if ($user == 'anonymous') {
      echo "</td><td class='right'><span class='topheadertext'>" . "<a class='topheaderlink' href='?view=login&amp;query=" .
        urlencode($query_string) . "'>" . utf8entities(_("Login")) . "</a></span>";
    } else {
      $userinfo = UserInfo($user);
      echo "</td><td class='right'><span class='topheadertext'>" . utf8entities(_("User")) .
        ": <a class='topheaderlink' href='?view=user/userinfo'>" . utf8entities($userinfo['name']) . "</a></span>";
      echo "<span class='topheadertext'><a class='topheaderlink' href='?view=logout'>&raquo; " .
        utf8entities(_("Logout")) . "</a></span>";
    }
    echo "</td></tr>\n";
    echo "</table>";
    echo "</div><!--topheader_right-->";
    echo "</header><!--page_top-->\n";
  }

  $showUrl = MakeUrl($_GET, array('show_menu' => 1));
  $hideUrl = MakeUrl($_GET, array('show_menu' => -1));
  $hideText = utf8entities(_("Hide Menu"));
  $showText = utf8entities(_("Show Menu"));

  $showShow = '';
  $showHide = '';
  if (getShowNav() != 0) {
    $showShow = getShowNav() == 1 ? ' hidden' : 'shown_block';
    $showHide = getShowNav() == 1 ? 'shown_block' : ' hidden';
  }

  leftMenuScript();
  
  echo "<div id='show_menu_link' class='$showShow'><a href='$showUrl' onclick='LeftMenu.toggleMenu(this);'>$showText</a></div>\n";
  echo "<div id='hide_menu_link' class='$showHide'><a href='$hideUrl' onclick='LeftMenu.toggleMenu(this);'>$hideText</a></div>\n";
  echo "<button id='menu_toggle' class='page_menu' title='Toggle menu' onclick='return LeftMenu.toggleMenu(this);'>";
  echo hamburgerMenu();
  echo "</button>";

  // navigation bar
  echo "<div class='breadcrumbs'><p class='breadcrumbs_text'>";
  echo navigationBar($title) . "</p></div>";
}

/**
 * Start of page content.
 */
function contentStart() {
  if (getPrintMode())
    contentStartWide();
  else
    echo "\n<main class='content'>\n";
}

function contentStartWide() {
  echo "\n<main class='content nomenu'>\n";
}

$footers = array();

/**
 * Add a footer printed at bottom of page.
 *
 * @param string $link
 *          HTML entities must already be encoded!
 * @param string $caption
 *          HTML entities must already be encoded!
 *          
 */
function addFooter($link, $caption) {
  global $footers;
  $footers[$link] = $caption;
}

$backurl = null;

function setBackurl($newback) {
  global $backurl;
  $backurl = $newback;
}

/**
 * End of page content.
 */
function contentEnd() {
  global $footers;

  $querystring = $_SERVER['QUERY_STRING'];
  $querystring = preg_replace("/&print=[^&]/", "", $querystring);

  echo "</main><!--content-->";
  echo "<footer>\n";
  echo "<hr />";
  global $backurl;
  if (!$backurl)
    $backurl = isset($_SERVER['HTTP_REFERER']) ? ($_SERVER['HTTP_REFERER']) : "";
  if ($backurl) {
    echo "<div class='backlink'><a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a></div>\n";
  }

  echo "<div class='printlink'>";
  foreach ($footers as $link => $caption) {
    echo " <a href='$link'>$caption</a> |";
  }
  if (getPrintMode() == 0) {
    echo " <a href='?" . utf8entities($querystring) . "&amp;print=1'>" . _("Printable version") . "</a></div>\n";
  } else {
    echo " <a href='?" . utf8entities($querystring) . "'>" . _("Screen version") . "</a></div>\n";
  }
  echo "</footer>";

  // echo "</td></tr></table>";
  // echo "</div>";
  // echo "</div><!--page_middle-->\n";
}

/**
 * End of the page.
 */
function pageEnd() {
  global $serverConf;
  if (IsFacebookEnabled()) {
    echo "<script src='http://connect.facebook.net/en_US/all.js'></script>
    <script>
      FB.init({appId: '";
    echo $serverConf['FacebookAppId'];
    echo "', status: true,
               cookie: true, xfbml: true});
      FB.Event.subscribe('auth.login', function(response) {
        window.location.reload();
      });
    </script>";
  }
  // echo "<div class='page_bottom'></div>";
  echo "</div><!--page-->";
  echo "</body></html>";
}

/**
 * Adds on page help.
 *
 * @param string $html
 *          - html-text shown when help button pressed.
 */
function onPageHelpAvailable($html) {
  return "<div style='float:right;'>
	<input type='image' alt='" . utf8entities(_("Help")) .
    "' class='helpbutton' id='helpbutton' src='images/help-icon.png'/></div>\n
	<div id='helptext' class='yui-pe-content'>$html<hr/></div>";
}

/**
 * Top of Mobile page.
 *
 * @param String $title
 *          - page title
 */
function mobilePageTop($title) {
  pageTopHeadOpen($title);

  echo "</head><body style='overflow-y:scroll;'>\n";
  leftMenu(0, false);

  echo "<div class='mobile_page'>\n";
}

function mobilePageEnd($query = "") {
  if ($query == "")
    $query = $_SERVER['QUERY_STRING'];
  if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {

    $html = "<form action='?" . utf8entities($query) . "' method='post'>\n";
    $html .= "<table cellpadding='2'>\n";
    $html .= "<tr><td>\n";
    $html .= utf8entities(_("Username")) . ":";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='input' type='text' id='myusername' name='myusername' size='15'/> ";
    $html .= "</td></tr><tr><td>\n";
    $html .= utf8entities(_("Password")) . ":";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='input' type='password' id='mypassword' name='mypassword' size='15'/> ";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='button' type='submit' name='login' value='" . utf8entities(_("Login")) . "'/>";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<hr/>\n";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<a href='?view=frontpage'>" . utf8entities(_("Back to the Ultiorganizer")) . "</a>";
    $html .= "</td></tr>\n";
    $html .= "</table>\n";
    $html .= "</form>";
  } else {
    if ($query != "") {
      header($query);
    }
    // $user = $_SESSION['uid'];
    // $userinfo = UserInfo($user);
    $html = "<table cellpadding='2'>\n";
    $html .= "<tr><td></td></tr>\n";
    $html .= "<tr><td><hr /></td></tr><tr><td>\n";
    $html .= "<a href='?view=frontpage'>" . utf8entities(_("Back to the Ultiorganizer")) . "</a>";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<a href='?view=mobile/logout'>" . utf8entities(_("Logout")) . "</a></td></tr></table>";
  }

  global $serverConf;
  if (IsFacebookEnabled()) {
    $html .= "<script src='http://connect.facebook.net/en_US/all.js'></script>
    <script>
      FB.init({appId: '";
    $html .= $serverConf['FacebookAppId'];
    $html .= "', status: true,
               cookie: true, xfbml: true});
      FB.Event.subscribe('auth.login', function(response) {
        window.location.reload();
      });
    </script>";
  }
  $html .= "<div class='page_bottom'></div>";
  $html .= "</div></body></html>";
  echo $html;
}

/**
 * Creates locale selection html-code.
 */
function localeSelection() {
  global $locales;

  $ret = "";

  foreach ($locales as $localestr => $localename) {
    $query_string = StripFromQueryString($_SERVER['QUERY_STRING'], "locale");
    $query_string = StripFromQueryString($query_string, "goindex");
    $ret .= "<a href='?" . utf8entities($query_string) . "&amp;";
    $ret .= "locale=" . $localestr . "'><img class='localeselection' src='locale/" . $localestr . "/flag.png' alt='" .
      utf8entities($localename) . "'/></a>\n";
  }

  return $ret;
}

function nav_clear() {
  unset($_SESSION['navigation']);
}

function nav_initialize() {
  nav_clear();
  nav_add("view=frontpage", _("Homepage"));
}

function nav_size() {
  if (isset($_SESSION['navigation']))
    return count($_SESSION['navigation']);
  return 0;
}

function nav_add($query, $title) {
  if (!isset($_SESSION['navigation']))
    $_SESSION['navigation'] = array();

  $n = nav_size();
  if ($n > 0 && $_SESSION['navigation'][$n]['title'] == $title) {
    --$n;
  }

  $_SESSION['navigation'][$n + 1]['query'] = $query;
  $_SESSION['navigation'][$n + 1]['title'] = $title;
}

function nav_goto(int $index) {
  for ($i = nav_size(); $i > $index; --$i)
    unset($_SESSION['navigation'][$i]);
}

function nav_check_index($index) {
  if (!isset($_SESSION['navigation']) || !isset($_SESSION['navigation'][$index]))
    throw new Exception("Invalid navigation index");
}

function nav_query(int $index) {
  nav_check_index($index);
  return $_SESSION['navigation'][$index]['query'];
}

function nav_title(int $index) {
  nav_check_index($index);
  return $_SESSION['navigation'][$index]['title'];
}

function nav_current() {
  if (!isset($_SESSION['navigation']) || empty($_SESSION['navigation']))
    nav_initialize();
  return $_SESSION['navigation'][nav_size()];
}

/**
 * Navigation bar functionality and html-code.
 *
 * @param string $title
 *          - page title
 */
function navigationBar($title) {
  $max_len = 300;
  $max_size = 4;

  if (isset($_SERVER['QUERY_STRING']))
    $query_string = $_SERVER['QUERY_STRING'];
  else
    $query_string = "";

  $goindex = isset($_GET['goindex']) ? $_GET['goindex'] : 0;
  unset($_GET['goindex']);
  if ($goindex >= 1) {
    nav_goto($goindex);
  } else if ($goindex < 0) {
    nav_initialize();
  } else if (isset($_GET['view']) && $_GET['view'] == 'logout') {
    nav_initialize();
  } else if (strlen($query_string) == 0) {
    nav_add("view=frontpage", _("Homepage"));
  } else if (!empty($title)) {
    nav_add($query_string, $title);
  }

  $n = nav_size();
  $ret = '';
  $ellips = '';
  for ($i = $n; $i >= 1; --$i) {
    $ptitle = nav_title($i);
    $query = nav_query($i);
    if (empty($ret))
      $ret = $ptitle;
    else if ($i > 1 && $i < $n &&
      ($i < $n - $max_size || strlen($ptitle) + strlen($ret) + strlen(nav_title(1)) > $max_len)) {
      $ellips = "&hellip;&nbsp;&raquo;";
    } else {
      $current = "<a href='?" . utf8entities($query) . "&amp;goindex=" . $i . "'>" . $ptitle . "</a> &raquo; ";
      if ($i == 1)
        $current .= $ellips;
      $ret = $current . $ret;
    }
  }

  return $ret;
}

/**
 * Season selection html-code.
 */
function seasonSelection() {
  $seasons = CurrentSeasons();
  if (mysqli_num_rows($seasons) > 1) {
    echo "<table class='leftmenulinks'><tr><td>";
    echo "<form action='?view=frontpage' method='get' id='seasonsels'>";
    echo "<div><label for='selseason'>" . _("Select division") .
      "<select class='seasondropdown' name='selseason' id='selseason'
			onchange='var selseason=document.getElementById(\"selseason\"); changeseason(selseason.options[selseason.options.selectedIndex].value);'>\n";
    while ($row = mysqli_fetch_assoc($seasons)) {
      $selected = "";
      if (isset($_SESSION['userproperties']['selseason']) &&
        $_SESSION['userproperties']['selseason'] == $row['season_id']) {
        $selected = "selected='selected'";
      }
      echo "<option class='dropdown' $selected value='" . utf8entities($row['season_id']) . "'>" .
        SeasonName($row['season_id']) . "</option>\n";
    }
    echo "</select></label>\n";
    foreach ($_GET as $name => $value) {
      if ($name != 'selseason')
        echo "<input type='hidden' name='" . utf8entities($name) . "' value='" . utf8entities($value) . "' />\n";
    }
    echo "<noscript><input type='submit' value='" . utf8entities(_("Go")) .
      "' name='selectseason'/></noscript>\n";
    echo "</div></form>";
    echo "</td></tr></table>\n";
  }
}

function pageMainStart($printable = false) {
  if ($printable) {
    // echo "<table class='main_page print'><tr>\n";
    // echo "<div class='main_page print'>\n";
  } else {
    // echo "<table class='main_page'><tr>\n";
    // echo "<div class='main_page'>\n";
  }
}

function startTable(&$status, $heading = '') {
  if (!$status) {
    echo "<table class='leftmenulinks'>\n";
    if (!empty($heading)) {
      echo "<tr class='level1'><th class='menuseasonlevel'>" . utf8entities($heading) . "</th></tr>\n";
    }
    echo "<tr class='level2'><td>\n";
  }
  $status = true;
}

function endTable($status) {
  if ($status)
    echo "</td></tr></table>\n";
  $status = false;
}

$currentSeries = 0;

function SetCurrentSeries($seriesId) {
  global $currentSeries;
  $currentSeries = (int) $seriesId;
}

function leftMenuScript() {
  global $currentSeries;
  echo <<<EOG
<script type="text/javascript">
<!--
class LeftMenu {
  static hide2(parent, className, collapse) {
    var elements = parent.getElementsByClassName(className);
    
    for (var i = 0; i < elements.length; i++) {
       elements[i].style.display = collapse?"none":"";
    }
  }

  static hide() {
    LeftMenu.hide2(document, "menulevel2", true);
    LeftMenu.hide2(document, "menulevel3", true);
    LeftMenu.hide2(document, "collapse", true);
    LeftMenu.hide2(document, "uncollapse", false);
  }
  
  static toggle(id) {
     var element = document.getElementById(id);
     if (element != null) {
       var parent = element.parentNode;
       element = parent.nextElementSibling;
       var collapsed = element !=null && (element.className === "menulevel2" || element.clasName === "menulevel3") && element.style.display === "none";
       for (; element != null && (element.className == "menulevel2" || element.className == "menulevel3");) {
         element.style.display = collapsed?"":"none";
         
         element = element.nextElementSibling;
       }
       LeftMenu.hide2(parent, "collapse", !collapsed);
       LeftMenu.hide2(parent, "uncollapse", collapsed);
     }
  }
 
  static toggleMenu(elem) {
     var menu = document.getElementById('left_menu');
     if (menu == null) return; 
     var hidden=menu.style.display === "none";
     hidden = menu.offsetParent === null;
     if (!hidden) {
        menu.style.display = "none";
     } else { 
        menu.style.display = "block";
     }
     return false;
  }

  static init() {
    LeftMenu.hide(); 
    LeftMenu.toggle("seriesNav$currentSeries"); 
    var menu = document.getElementById('left_menu');
    var show = document.getElementById('show_menu_link');
    var hide = document.getElementById('hide_menu_link');
    var toggle = document.getElementById('menu_toggle');
    show.style.display = "none";
    hide.style.display = "none";
    toggle.style.display = "block";
  }
}

YAHOO.util.Event.onDOMReady(function() { LeftMenu.init(); });
//-->
</script>
EOG;
}

function hamburgerMenu($id = 'hamburger') {
  return "<div class='$id'><div class='{$id}_inner'></div></div>";
}

function getShowNav() {
  $showNav = 0;
  if (isset($_GET['show_menu'])) {
    $showNav = $_GET['show_menu'];
    $_SESSION['show_menu'] = $showNav;
  } else if (isset($_SESSION['show_menu'])) {
    $showNav = $_SESSION['show_menu'];
  }
  return $showNav;
}

/**
 * Creates menus on left side of page.
 *
 * @param int $id
 *          - page id (not used now days)
 * @param boolean $printable
 *          - if true, menu is not drawn.
 */
function leftMenu($id = 0, $pagestart = true, $printable = false) {
  $printable |= getPrintMode();
  if ($pagestart) {
    pageMainStart($printable);
  }

  if ($printable) {
    return;
  }

  $hidden = getShowNav() == -1 ? ' hidden' : '';

  // echo "<td id='left_menu' class='menu_left'>";
  echo "<aside id='left_menu' class='menu_left$hidden'>\n";

  // Administration menu
  if (hasScheduleRights() || isSuperAdmin() || hasTranslationRight()) {}
  $leftmenu = 0;
  $heading = _("Administration");
  if (isSuperAdmin()) {
    startTable($leftmenu, $heading);

    echo "<a class='subnav' href='?view=admin/seasons'>&raquo; " . utf8entities(_("Events")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/serieformats'>&raquo; " . utf8entities(_("Rule templates")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/clubs'>&raquo; " . utf8entities(_("Clubs & Countries")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/locations'>&raquo; " . utf8entities(_("Field locations")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/reservations'>&raquo; " . utf8entities(_("Field reservations")) . "</a>\n";
  }
  if (hasScheduleRights()) {
    startTable($leftmenu, $heading);
    echo "<a class='subnav' href='?view=admin/schedule'>&raquo; " . utf8entities(_("Scheduling")) . "</a>";
  }

  if (hasTranslationRight()) {
    startTable($leftmenu, $heading);
    echo "<a class='subnav' href='?view=admin/translations'>&raquo; " . utf8entities(_("Translations")) . "</a>\n";
  }
  if (isSuperAdmin()) {
    startTable($leftmenu, $heading);
    echo "<a class='subnav' href='?view=admin/users'>&raquo; " . utf8entities(_("Users")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/eventviewer'>&raquo; " . utf8entities(_("Logs")) . "</a>\n";
    // echo "<a class='subnav' href='?view=admin/sms'>&raquo; ".utf8entities(_("SMS"))."</a>\n";
    echo "<a class='subnav' href='?view=admin/dbadmin'>&raquo; " . utf8entities(_("Database")) . "</a>\n";
    echo "<a class='subnav' href='?view=admin/serverconf'>&raquo; " . utf8entities(_("Settings")) . "</a>\n";
  }

  endTable($leftmenu);

  if ($_SESSION['uid'] != 'anonymous') {
    $leftmenu = 0;
    startTable($leftmenu, '');
    echo "<a class='subnav' href='?view=admin/help'>&raquo; " . utf8entities(_("Helps")) . "</a>\n";
    echo "<a class='subnav' href='?view=user/userinfo'>&raquo; " . utf8entities(_("Missing menu?")) . "</a>\n";
    endTable($leftmenu);
  }

  // Event administration menu
  $editlinks = getEditSeasonLinks();
  if (count($editlinks)) {
    foreach ($editlinks as $season => $links) {
      echo "<table class='leftmenulinks'>\n";
      echo "<tr><th class='menuseasonlevel'>" . utf8entities(SeasonName($season)) . " " .
        utf8entities(_("Administration")) . "</th>";
      echo "<td class='menuseasonlevel'><a class='hideseason' style='text-decoration: none;' href='?view=frontpage&amp;hideseason=$season'>x</a></td>";
      echo "</tr><tr><td colspan='2'>\n";
      foreach ($links as $href => $name) {
        if (empty($href))
          echo "<hr />";
        else
          echo "<a class='subnav' href='" . $href . "'>&raquo; " . utf8entities($name) . "</a>\n";
      }
      echo "</td></tr>\n";
      echo "</table>\n";
    }
  }

  // Create new event menu
  if (isSuperAdmin()) {
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td>\n";
    echo "<a class='subnav' href='?view=admin/addseasons'>&raquo; " . utf8entities(_("Create new event")) . "</a>\n";
    echo "</td></tr>\n";
    echo "</table>\n";
  }

  // Team registration
  if ($_SESSION['uid'] != 'anonymous') {
    $enrollSeasons = EnrollSeasons();
    if (count($enrollSeasons) > 0) {
      echo "<table class='leftmenulinks'>\n";
      echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Team registration")) . "</th></tr>\n";
      echo "<tr><td>\n";
      foreach ($enrollSeasons as $seasonId => $seasonName) {
        echo "<a class='subnav' href='?view=user/enrollteam&amp;season=" . $seasonId . "'>&raquo; " .
          utf8entities(U_($seasonName)) . "</a>\n";
      }
      echo "</td></tr>\n";
      echo "</table>\n";
    }
  }

  if (true) {
    $pollSeasons = PollSeasons();
    if (count($pollSeasons) > 0) {
      echo "<table class='leftmenulinks'>\n";
      echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Polls")) . "</th></tr>\n";
      echo "<tr><td>\n";
      foreach ($pollSeasons as $spoll) {
        echo "<a class='subnav' href='?view=user/polls&amp;season=" . $spoll['season_id'] . "'>&raquo; " .
          utf8entities(U_($spoll['name'])) . "</a>\n";
      }
      echo "</td></tr>\n";
      echo "</table>\n";
    }
  }

  // Player profiles
  if (hasPlayerAdminRights()) {
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Player profiles")) . "</th></tr>\n";
    echo "<tr><td>\n";
    foreach ($_SESSION['userproperties']['userrole']['playeradmin'] as $profile_id => $propid) {
      $playerInfo = PlayerProfile($profile_id);
      echo "<a class='subnav' href='?view=user/playerprofile&amp;profile=" . $playerInfo['profile_id'] . "'>&raquo; " .
        $playerInfo['firstname'] . " " . $playerInfo['lastname'] . "</a>\n";
    }
    echo "</td></tr>";
    echo "</table>\n";
  }

  // event public part: schedule, played games, teams, divisions, pools...
  seasonSelection();
  $curseason = CurrentSeason();

  echo "<table class='leftmenulinks'>\n";
  $colspan = " colspan='3'";
  if ($curseason < 0) {
    echo "<tr class='menulevel0'><th class='menuseasonlevel'>" . utf8entities(_("No seasons created")) . "</th></tr>\n";
  } else {
    $pools = getViewPools($curseason);
    if ($pools && mysqli_num_rows($pools)) {
      $lastseason = "";
      $lastseries = "";
      while ($row = mysqli_fetch_assoc($pools)) {
        $season = $row['season'];
        $series = $row['series'];
        if (SeriesInfo($series)['valid'] == 0 && !hasEditSeriesRight($series)) {
          continue;
        }
        if ($lastseason != $season) {
          $lastseason = $season;
          echo "<tr class='menulevel0'><th $colspan class='menuseasonlevel'><a class='seasonnav' href='?view=teams&amp;season=" .
            urlencode($season) . "&amp;list=bystandings'>";
          echo utf8entities(U_($row['season_name'])) . "</a></th></tr>\n";
          echo "<tr class='menulevel1'><td $colspan><a class='nav' href='?view=teams&amp;season=" . urlencode($season) .
            "&amp;list=bystandings'>" . utf8entities(_("Final ranking")) . "</a></td></tr>\n";
          echo "<tr class='menulevel1'><td $colspan><a class='nav' href='?view=teams&amp;season=" . urlencode($season) .
            "&amp;list=allteams'>" . utf8entities(_("Teams")) . "</a></td></tr>\n";
          echo "<tr class='menulevel1'><td $colspan class='menuseparator'></td></tr>\n";
        }

        if ($lastseries != $series) {
          $lastseries = $series;
          $onclick = "onclick='LeftMenu.toggle(\"seriesNav" . $series . "\");'";
          echo "<tr class='menulevel1'><td class='menuserieslevel' id='seriesNav" . $series . "'>";
          echo "<a href='#$series' class='subnav' $onclick>";
          // echo "<a class='subnav' href='?view=poolstatus&amp;series=" . $series . "'>";
          echo utf8entities(U_($row['series_name']));
          echo "</a>";
          echo "</td><td class='uncollapse' style='display:none;'><a $onclick tabindex='0'>&raquo;</a></td><td class='collapse' style='display:none;'><a $onclick>v</a></td></tr>\n";
          echo "<tr class='menulevel2'><td $colspan class='navpoollink'>\n";
          echo "<a class='subnav' href='?view=seriesstatus&amp;series=" . $series . "'>&raquo; " .
            utf8entities(_("Statistics")) . "</a></td></tr>\n";
          echo "<tr class='menulevel2'><td $colspan class='navpoollink'>\n";
          echo "<a class='subnav' href='?view=games&amp;series=" . $series . "'>&raquo; " . utf8entities(_("Games")) .
            "</a></td></tr>\n";
          echo "<tr class='menulevel2'><td $colspan class='navpoollink'>\n";
          echo "<a class='subnav' href='?view=poolstatus&amp;series=" . $series . "'>&raquo; " .
            utf8entities(_("Show all pools")) . "</a></td></tr>\n";
        }
        echo "<tr class='menulevel3'><td $colspan class='menupoollevel'>\n";
        echo "<a class='navpoollink' href='?view=poolstatus&amp;pool=" . $row['pool'] . "'>&raquo; " .
          utf8entities(U_($row['pool_name'])) . "</a>\n";
        echo "</td></tr>\n";
      }
      echo "<tr class='menulevel1'><td $colspan class='menuseparator'></td></tr>\n";
      echo "<tr class='menulevel1'><td $colspan><a class='nav' href='?view=games&amp;season=" . urlencode($season) .
        "&amp;filter=series&amp;group=all'>" . utf8entities(_("All Games")) . "</a></td></tr>\n";
      // echo "<tr><td $colspan><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>".utf8entities(_("Played games"))."</a></td></tr>\n";
    } else {
      $season = CurrentSeason();
      echo "<tr class='menulevel0'><th class='menuseasonlevel'><a class='seasonnav' href='?view=teams&amp;season=" .
        urlencode($season) . "&amp;list=bystandings'>" . utf8entities(U_(CurrentSeasonName())) . "</a></th></tr>\n";
      echo "<tr class='menulevel1'><td><a class='nav' href='?view=timetables&amp;season=" . urlencode($season) .
        "&amp;filter=tournaments&amp;group=all'>" . utf8entities(_("Games")) . "</a></td></tr>\n";
      // echo "<tr><td><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>".utf8entities(_("Played games"))."</a></td></tr>\n";
      echo "<tr class='menulevel1'><td><a class='nav' href='?view=teams&amp;season=" . urlencode($season) . "'>" .
        utf8entities(_("Teams")) . "</a></td></tr>\n";
      echo "<tr class='menulevel1'><td class='menuseparator'></td></tr>\n";

      $tmpseries = SeasonSeries($season, true);
      foreach ($tmpseries as $row) {
        echo "<tr class='menulevel1'><td class='menuserieslevel'>" . utf8entities(U_($row['name'])) . "</td></tr>\n";
        echo "<tr class='menulevel2'><td class='menupoollevel'>\n";
        echo utf8entities(_("Pools not yet created"));
        echo "</td></tr>\n";
      }
    }
  }
  echo "</table>\n";

  // event links
  echo "<table class='leftmenulinks'>\n";
  echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Event Links")) . "</th></tr>\n";
  echo "<tr><td>";

  $urls = GetUrlListByTypeArray(array("menulink", "menumail"), $curseason);
  foreach ($urls as $url) {
    if ($url['type'] == "menulink") {
      echo "<a class='subnav' href='" . $url['url'] . "'>&raquo; " . U_($url['name']) . "</a>\n";
    } elseif ($url['type'] == "menumail") {
      echo "<a class='subnav' href='mailto:" . $url['url'] . "'>@ " . U_($url['name']) . "</a>\n";
    }
  }
  echo "</td></tr>\n";
  echo "<tr><td>";
  echo "<a class='subnav' style='background: url(./images/linkicons/feed_14x14.png) no-repeat 0 50%; padding: 0 0 0 19px;' href='./ext/rss.php?feed=all'>" .
    utf8entities(_("Result Feed")) . "</a>\n";
  echo "</td></tr>\n";
  if (IsTwitterEnabled()) {
    $savedurl = GetUrl("season", $season, "result_twitter");
    if (!empty($savedurl['url'])) {
      echo "<tr><td>";
      echo "<a class='subnav' style='background: url(./images/linkicons/twitter_14x14.png) no-repeat 0 50%; padding: 0 0 0 19px;' href='" .
        $savedurl['url'] . "'>" . utf8entities(_("Result Twitter")) . "</a>\n";
      echo "</td></tr>\n";
    }
  }
  echo "</table>\n";

  // event history
  if (IsStatsDataAvailable()) {
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Statistics")) . "</th></tr>\n";
    echo "<tr><td>";
    echo "<a class='subnav' href=\"?view=seasonlist\">&raquo; " . utf8entities(_("Events")) . "</a>\n";
    echo "<a class='subnav' href=\"?view=allplayers\">&raquo; " . utf8entities(_("Players")) . "</a>\n";
    echo "<a class='subnav' href=\"?view=allteams\">&raquo; " . utf8entities(_("Teams")) . "</a>\n";
    echo "<a class='subnav' href=\"?view=allclubs\">&raquo; " . utf8entities(_("Clubs")) . "</a>\n";
    $countries = CountryList(true, true);
    if (count($countries)) {
      echo "<a class='subnav' href=\"?view=allcountries\">&raquo; " . utf8entities(_("Countries")) . "</a>\n";
    }
    echo "<a class='subnav' href=\"?view=statistics&amp;list=teamstandings\">&raquo; " . utf8entities(_("All time")) .
      "</a></td></tr>\n";
    echo "</table>";
  }

  // External access
  echo "<table class='leftmenulinks'>\n";
  echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Client access")) . "</th></tr>\n";
  echo "<tr><td>";
  echo "<a class='subnav' href='?view=ext/index'>&raquo; " . utf8entities(_("Ultiorganizer links")) . "</a>\n";
  echo "<a class='subnav' href='?view=ext/export'>&raquo; " . utf8entities(_("Data export")) . "</a>\n";
  echo "<a class='subnav' href='?view=mobile/index'>&raquo; " . utf8entities(_("Mobile Administration")) . "</a>\n";
  echo "<a class='subnav' href='./scorekeeper/'>&raquo; " . utf8entities(_("Scorekeeper")) . "</a>\n";
  echo "</td></tr>\n";
  echo "</table>";

  echo "<table class='leftmenulinks'>\n";
  echo "<tr><th class='menuseasonlevel'>" . utf8entities(_("Links")) . "</th></tr>\n";
  echo "<tr><td>";
  $urls = GetUrlListByTypeArray(array("menulink", "menumail"), 0);
  foreach ($urls as $url) {
    if ($url['type'] == "menulink") {
      echo "<a class='subnav' href='" . $url['url'] . "'>&raquo; " . U_($url['name']) . "</a>\n";
    } elseif ($url['type'] == "menumail") {
      echo "<a class='subnav' href='mailto:" . $url['url'] . "'>@ " . U_($url['name']) . "</a>\n";
    }
  }
  echo "</td></tr>\n";
  echo "</table>";

  // draw customizable logo if any
  echo "<div class='leftmenulogo'>" . logo() . "</div>\n";

  echo "<table class='leftmenulinks'>\n";
  echo "<tr><td class='guides'>";
  echo "<a href='?view=user_guide'>" . utf8entities(_("User Guide")) . "</a> | \n";
  echo "<a href='?view=admin/help'>" . utf8entities(_("Admin Help")) . "</a> | \n";
  echo "<a href='?view=privacy'>" . utf8entities(_("Privacy Policy")) . "</a>\n";
  echo "</td></tr>";
  echo "</table>";

  // echo "</td>\n";
  // echo "</div>\n";
  echo "</aside>\n";
}

/**
 * Get event administration links.
 */
function getEditSeasonLinks() {
  $ret = array();
  if (isset($_SESSION['userproperties']['editseason'])) {
    $editSeasons = getEditSeasons($_SESSION['uid']);
    foreach ($editSeasons as $season => $propid) {
      $ret[$season] = array();
    }
    $respgamesset = array();
    $deleteset = array();
    foreach ($ret as $season => $links) {
      if (isSeasonAdmin($season)) {
        $links['?view=admin/seasonadmin&amp;season=' . $season] = _("Event");
        $links['?view=admin/seasonseries&amp;season=' . $season] = _("Divisions");
        $links['?view=admin/seasonteams&amp;season=' . $season] = _("Teams");
        $links['?view=admin/seasonpools&amp;season=' . $season] = _("Pools");
        $links['?view=admin/reservations&amp;season=' . $season] = _("Scheduling");
        $links['?view=admin/seasongames&amp;season=' . $season] = _("Games");
        $links['?view=admin/seasonstandings&amp;season=' . $season] = _("Rankings");
        $links['?view=admin/seasonpolls&amp;season=' . $season] = _("Polls");
        $links['?view=admin/accreditation&amp;season=' . $season] = _("Accreditation");
        $respgamesset[$season] = "set";
        $deleteset[$season] = "set";
      }
      $ret[$season] = $links;
    }
    if (isset($_SESSION['userproperties']['userrole']['seriesadmin'])) {
      foreach ($_SESSION['userproperties']['userrole']['seriesadmin'] as $series => $param) {
        $seriesseason = SeriesSeasonId($series);
        // Links already added if superadmin or seasonadmin
        if (isset($ret[$seriesseason]) && !isSeasonAdmin($seriesseason)) {
          $links = $ret[$seriesseason];
          $seriesname = U_(getSeriesName($series));
          $links['?view=admin/seasonteams&amp;season=' . $season . '&amp;series=' . $series] = $seriesname . " " .
            _("Teams");
          $links['?view=admin/seasongames&amp;season=' . $season . '&amp;series=' . $series] = $seriesname . " " .
            _("Games");
          $links['?view=admin/seasonstandings&amp;season=' . $season . '&amp;series=' . $series] = $seriesname . " " .
            _("Pool rankings");
          $links['?view=admin/accreditation&amp;season=' . $seriesseason] = _("Accreditation");
          $ret[$seriesseason] = $links;
          $respgamesset[$seriesseason] = "set";
          $deleteset[$season] = "set";
        }
      }
    }

    $teamPlayersSet = array();
    if (isset($_SESSION['userproperties']['userrole']['teamadmin'])) {

      foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $team => $param) {
        $teamseason = getTeamSeason($team);
        $teamresps = TeamResponsibilities($_SESSION['uid'], $teamseason);
        if (isset($ret[$teamseason])) {
          if (count($teamresps) < 2) {
            $teamname = getTeamName($team);
            $links = $ret[$teamseason];
            $links['?view=user/teamplayers&amp;team=' . $team] = _("Team") . ": " . $teamname;
            $respgamesset[$teamseason] = "set";
            $teamPlayersSet["" . $team] = "set";
            $ret[$teamseason] = $links;
          } else {
            $links = $ret[$teamseason];
            $links['?view=user/respteams&amp;season=' . $teamseason] = _("Team responsibilities");
            $respgamesset[$teamseason] = "set";
            $ret[$teamseason] = $links;
          }
        }
      }
    }
    if (isset($_SESSION['userproperties']['userrole']['accradmin'])) {
      if (count($_SESSION['userproperties']['userrole']['teamadmin']) <= 4) {
        foreach ($_SESSION['userproperties']['userrole']['accradmin'] as $team => $param) {
          if (!isset($teamPlayersSet[$team])) {
            $teamseason = getTeamSeason($team);
            if (isset($ret[$teamseason])) {
              $teamname = getTeamName($team);
              $links = $ret[$teamseason];
              $links['?view=user/teamplayers&amp;team=' . $team] = _("Team") . ": " . $teamname;
              $links['?view=admin/accreditation&amp;season=' . $teamseason] = _("Accreditation");
              $teamPlayersSet["" . $team] = "set";
              $ret[$teamseason] = $links;
            }
          }
        }
      } else {
        $links = $ret[$season];
        $links['?view=user/respteams&amp;season=' . $season] = _("Team responsibilities");
        $links['?view=admin/accreditation&amp;season=' . $season] = _("Accreditation");
        $ret[$season] = $links;
      }
    }
    if (isset($_SESSION['userproperties']['userrole']['gameadmin'])) {
      foreach ($_SESSION['userproperties']['userrole']['gameadmin'] as $game => $param) {
        $gameseason = GameSeason($game);
        if (isset($ret[$gameseason])) {
          $respgamesset[$gameseason] = "set";
        }
      }
    }
    if (isset($_SESSION['userproperties']['userrole']['resgameadmin'])) {
      foreach ($_SESSION['userproperties']['userrole']['resgameadmin'] as $resId => $param) {
        foreach (ReservationSeasons($resId) as $resSeason) {
          if (isset($ret[$resSeason])) {
            $respgamesset[$resSeason] = "set";
          }
        }
      }
    }
    foreach ($respgamesset as $season => $set) {
      $links = $ret[$season];
      $links['?view=user/respgames&amp;season=' . $season] = _("Game responsibilities");
      $links['?view=user/contacts&amp;season=' . $season] = _("Contacts");
      $ret[$season] = $links;
    }

    foreach ($ret as $season => $links) {
      if (isSeasonAdmin($season)) {
        $links['?view=admin/addseasonusers&amp;season=' . $season] = _("Event users");
      }
      $ret[$season] = $links;
    }

    foreach ($respgamesset as $season => $set) {
      $links = $ret[$season];
      if (!empty($deleteset[$season])) {
        $links[''] = "";
        $links['?view=admin/delete&amp;season=' . $season] = _("Delete");
        $ret[$season] = $links;
      }
    }
  }

  foreach ($ret as $season => $links) {
    if (!isset($links) || empty($links) || count($links) == 0) {
      unset($ret[$season]);
    }
  }
  return $ret;
}

function make_array($var) {
  if (is_array($var)) return $var;
  return [$var];
}

/**
 * Creates on page menu.
 * Typically top of the page.
 *
 * @param array $menuitems
 *          - key is link name, value is url (not html encoded), or array of urls if there are duplicate names.
 * @param string $current
 *          - links to this url obtain the class 'current'
 * @param boolean $echoed
 *          if true (the default), the menu is echoed
 * @return string the menu (html)
 *        
 */
function pageMenu($menuitems, $current = "", $echoed = true) {
  $html = "\n<!-- on page menu -->\n";
  $html .= "<div class='pagemenu_container'>\n";
  $line = "";
  foreach ($menuitems as $name => $url) {
    foreach(make_array($url) as $url2) {
      $line .= utf8entities($name);
      $line .= " - ";
    }
  }
  if (strlen($line) < 100) {
    $html .= "<table class='pagemenu'><tr>\n";
    $first = true;
    foreach ($menuitems as $name => $url) {
      foreach(make_array($url) as $url2) {
      if (!$first)
        $html .= "<td> - </td>";
      $first = false;
      if ($url2 == $current || strrpos($_SERVER["REQUEST_URI"], $url2)) {
        $html .= "<th><a class='current' href='" . htmlentities($url2) . "'>" . utf8entities($name) . "</a></th>\n";
      } else {
        $html .= "<th><a href='" . htmlentities($url2) . "'>" . utf8entities($name) . "</a></th>\n";
      }
      }
    }
    $html .= "</tr></table>";
  } else {
    $html .= "<ul class='pagemenu'>\n";

    foreach ($menuitems as $name => $url) {
      foreach(make_array($url) as $url2) {
      if ($url2 == $current) {
        $html .= "<li><a class='current' href='" . htmlentities($url2) . "'>" . utf8entities($name) . "</a></li>\n";
      } elseif (strrpos($_SERVER["REQUEST_URI"], $url2)) {
        $html .= "<li><a class='current' href='" . htmlentities($url2) . "'>" . utf8entities($name) . "</a></li>\n";
      } else {
        $html .= "<li><a href='" . htmlentities($url2) . "'>" . utf8entities($name) . "</a></li>\n";
      }
      }
    }
    $html .= "</ul>\n";
  }
  $html .= "</div>\n";
  $html .= "<p style='clear:both'></p>\n";

  if ($echoed) {
    echo $html;
  }
  return $html;
}

function loginForm($query_string, $userId = '', $subId = '') {
  if (empty($query_string))
    $query_string = 'view=login';
  $userId = utf8entities($userId);
  $html = '';

  $html .= "<form action='?" . utf8entities($query_string) . "' method='post'>\n";
  $html .= "<table class='formtable'>\n";
  $html .= "<tr><td class='infocell'><label for='myusername$subId'>" . _("Username") . "</label>:</td>";
  $html .= "<td><input class='input' type='text' id='myusername$subId' name='myusername' size='40' value='$userId'/>&nbsp;</td>";
  $html .= "<td><span class='topheadertext'><a class='topheaderlink' href='?view=register'>" .
    utf8entities(_("New user?")) . "</a></span></td>\n";
  $html .= "</tr>\n";
  $html .= "<tr><td class='infocell'><label for='mypassword$subId'>" . _("Password") . "</label>:</td>";
  $html .= "<td><input type='password' class='input' size='40' maxlength='20' id='mypassword$subId' name='mypassword' value=''/></td>";
  $html .= "<td><span class='topheadertext'><a class='topheaderlink' href='?view=login&amp;recover=1'>" .
    utf8entities(_("Recover lost password?")) . "</a></span></td>\n";
  $html .= "</tr>";
  $html .= "</table>\n";
  $html .= "<input type='hidden' name='query' value='" . urlencode($query_string) . "' />";
  if ($subId)
    $html .= "<p><input type='button' name='login' id='myloginbutton$subId' value='" . utf8entities(_("Login")) . "' /></p>";
  else
    $html .= "<p><input class='button' type='submit' name='login' id='myloginbutton' value='" . utf8entities(_("Login")) . "' /></p>";
  $html .= "</form>";

  return $html;
}
?>