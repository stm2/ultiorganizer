<?php
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'localization.php';

$html = "";
$title = _("Front page");

function pool_list($pre, $pools, $post, $limit) {
  $upcoming = "";
  $last_series = null;
  $pool_list = '';
  $pool_list0 = '';
  $count = 0;
  $pool_limit = 80;
  foreach ($pools as $pool) {
    if ($last_series != $pool['series_id']) {
      if (++$count > $limit)
        break;

      $seriesId = $pool['series_id'];
      if ($last_series != null) {
        $upcoming .= " ($pool_list)</li>\n";
        $pool_list = '';
        $pool_list0 = '';
      }

      $upcoming .= "<li><a href='?view=seriesstatus&amp;series=$seriesId'>" . $pool['series_name'] . "</a>";
      $last_series = $pool['series_id'];
    }

    if (strlen($pool_list0) < $pool_limit) {
      if (!empty($pool_list)) {
        $pool_list .= ", ";
        $pool_list0 .= ", ";
      }
      $pool_list .= "<a href='?view=poolstatus&amp;pool=" . $pool['pool_id'] . "'>" . $pool['pool_name'] . "</a>";
      $pool_list0 .= $pool['pool_name'];
    } else if (mb_substr($pool_list0, -5) != ", ...") {
      $pool_list .= ", ...";
      $pool_list0 .= ", ...";
    }
  }

  $html = "";
  if (!empty($pool_list)) {
    $html .= $pre;
    $html .= "<ul>";
    $html .= $upcoming;
    $html .= " ($pool_list)</li>\n";
    if ($count > $limit)
      $html .= "<li>...</li>\n";
    $html .= "</ul>\n";
    $html .= $post;
  }

  return $html;
}

function recent_and_upcoming() {
  $html = pool_list("<h2>" . _("Most recent events") . "</h2>", NextPools('recent'), "<br />", 10);
  $html .= pool_list("<h2>" . _("Some Upcoming events") . "</h2>", NextPools('future'), "<br />", 10);
  return $html;
}

function replace_templates($html) {
  preg_match("/{{([^}]*)}}/", $html, $matches);

  foreach ($matches as $match) {
    switch (trim($match)) {
    case "{{ recent_and_upcoming }}":
      $replacement = recent_and_upcoming();
      break;
    default:
      $replacement = "<!-- could not find replacement for ' " . utf8entities($match) . "'. -->\n";
    }

    $html = str_replace($match, $replacement, $html);
  }

  return $html;
}

if (iget("hideseason")) {
  $propId = getPropId($user, 'editseason', iget("hideseason"));
  RemoveEditSeason($user, $propId);
  header("location:?view=frontpage");
  exit();
}

$htmlfile = 'locale/' . getSessionLocale() . '/LC_MESSAGES/welcome.html';

if (is_file('cust/' . CUSTOMIZATIONS . '/' . $htmlfile)) {
  $html .= file_get_contents('cust/' . CUSTOMIZATIONS . '/' . $htmlfile);
} else if (is_file($htmlfile)) {
  $html .= file_get_contents($htmlfile);
} else {
  $html .= "<h2>" . _("Welcome to Ultiorganizer") . "</h2>\n" . "{{ recent_and_upcoming }}";
}

$html = replace_templates($html);

$urls = GetUrlListByTypeArray(array("admin"), 0);
if (!empty($urls)) {
  $html .= "<p>";
  $html .= _("In case of feedback, improvement ideas or any other questions, please contact:");
  foreach ($urls as $url) {
    $html .= "<br/><a href='mailto:" . $url['url'] . "'>" . U_($url['name']) . "</a>\n";
  }
  $html .= "</p>";
}

showPage($title, $html);
?>
