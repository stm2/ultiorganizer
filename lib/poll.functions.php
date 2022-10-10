<?php
include_once $include_prefix . 'lib/debug.functions.php';

function PollStatuses() {
  return array(1 => _("visible"), 2 => _("option_entry"), 4 => _("voting"), 8 => _("results"));
}

function PollStatusName($statusId) {
  switch ($statusId) {
  case 1:
    return _("Visible");
  case 2:
    return _("Option suggestion");
  case 4:
    return _("Voting");
  case 8:
    return _("Results");
  }
}

function PollInfo($pollId) {
  $query = sprintf("SELECT * FROM uo_poll
                    WHERE poll_id=%d", (int) $pollId);
  $info = DBQueryToRow($query);
  return poll_status_to_tags($info);
}

function poll_tags_to_flags($params) {
  $flags = 0;
  foreach (PollStatuses() as $flag => $name) {
    if ($params[$name])
      $flags |= $flag;
  }
  return $flags;
}

function poll_status_to_tags(&$params) {
  if (empty($params))
    return $params;
  $status = $params['status'];
  foreach (PollStatuses() as $flag => $name) {
    if ($status & $flag)
      $params[$name] = 1;
    else
      $params[$name] = 0;
  }
  unset($params['status']);
  return $params;
}

function poll_is_status_flag($pollId, $flag) {
  $info = PollInfo($pollId);

  return $info[$flag] > 0;
}

function IsVisible($pollId) {
  return poll_is_status_flag($pollId, 'visible');
}

function CanSuggest($user, $name, $pollId) {
  return poll_is_status_flag($pollId, 'option_entry');
}

function CanVote($user, $name, $pollId) {
  return poll_is_status_flag($pollId, 'voting');
}

function HasResults($pollId) {
  return poll_is_status_flag($pollId, 'results');
}

/**
 * Returns all seasons having at least one poll.
 */
function PollSeasons() {
  $query = sprintf(
    "SELECT sn.season_id, sn.name
    FROM uo_season sn, uo_series sr, uo_poll pl
    WHERE sn.season_id = sr.season AND pl.series_id = sr.series_id AND (pl.status & 1) = 1
    GROUP BY sn.season_id
    ORDER BY starttime DESC");
  return DBQueryToArray($query);
}

function SeriesPolls($seriesId) {
  $query = sprintf("
		SELECT *
		FROM uo_poll
		WHERE series_id=%d", (int) $seriesId);

  $polls = DBQueryToArray($query);

  foreach ($polls as &$poll) {
    poll_status_to_tags($poll);
  }
  return $polls;
}

function AddPoll($seriesId, $params) {
  if (hasEditSeriesRight($seriesId)) {

    $query = sprintf(
      "
               INSERT INTO uo_poll
               (series_id, name, password, description, status)
               VALUES (%d, '%s', '%s', '%s', '%d')", (int) $seriesId, mysql_adapt_real_escape_string($params['name']),
      mysql_adapt_real_escape_string($params['password']), mysql_adapt_real_escape_string($params['description']),
      (int) poll_tags_to_flags($params));
    return DBQueryInsert($query);
  } else
    die("Insufficient rights to edit series");
}

function DeletePoll($pollId) {
  $poll = PollInfo($pollId);
  if (hasEditSeriesRight($poll['series_id'])) {
    $query = sprintf("DELETE FROM uo_poll
     WHERE poll_id='%d'", (int) ($pollId));
    $ret = DBQuery($query);
    if ($ret !== -1) {
      $query = sprintf("DELETE FROM uo_poll_option
     WHERE poll_id='%d'", (int) ($pollId));
      $ret = DBQuery($query);
      if ($ret !== -1) {
        $query = sprintf("DELETE FROM uo_poll_vote
     WHERE option_id='%d'", (int) ($pollId));
        return DBQuery($query);
      }
    }
  } else
    die("Insufficient rights delete poll");
}

function SetPoll($id, $seriesId, $params) {
  if (hasEditSeriesRight($seriesId)) {
    $query = sprintf("
               UPDATE uo_poll SET name='%s', password='%s', description='%s', status = %d
               WHERE poll_id = %d", mysql_adapt_real_escape_string($params['name']),
      mysql_adapt_real_escape_string($params['password']), mysql_adapt_real_escape_string($params['description']),
      (int) poll_tags_to_flags($params), (int) $id);
    return DBQuery($query);
  } else
    die("Insufficient rights to edit series");
}

function PollOptions($pollId) {
  $query = sprintf("
		SELECT *
		FROM uo_poll_option
		WHERE poll_id=%d", (int) $pollId);

  return DBQueryToArray($query);
}

function PollOption($optionId) {
  $query = sprintf("
		SELECT *
		FROM uo_poll_option
		WHERE option_id=%d", (int) $optionId);

  return DBQueryToRow($query);
}

function AddPollOption($params) {
  // FIXME rights?
  $query = sprintf(
    "INSERT INTO uo_poll_option
      (poll_id, user_id, name, mentor, description, status )
      VALUES (%d, %d, '%s', '%s', '%s', %d)", (int) $params['poll_id'], (int) $params['user_id'],
    mysql_adapt_real_escape_string($params['name']), mysql_adapt_real_escape_string($params['mentor']),
    mysql_adapt_real_escape_string($params['description']), (int) $params['status']);
  return DBQueryInsert($query);
}

function SetPollOption($ptId, $params) {
  // FIXME rights?
  $query = sprintf(
    "UPDATE uo_poll_option SET
      user_id='%d', name='%s', mentor='%s', description='%s'
      WHERE option_id=%d", (int) $params['user_id'], mysql_adapt_real_escape_string($params['name']),
    mysql_adapt_real_escape_string($params['mentor']), mysql_adapt_real_escape_string($params['description']),
    (int) $ptId);
  return DBQuery($query);
}

function DeletePollOption($optionId) {
  $option = PollOption($optionId);
  if (!empty($option)) {
    $pollSeries = PollInfo($option['poll_id'])['series_id'];
    if (hasEditSeriesRight($pollSeries)) {
      $query = sprintf("DELETE FROM uo_poll_option
     WHERE option_id='%d'", (int) ($optionId));
      $ret = DBQuery($query);
      if ($ret !== -1) {
        $query = sprintf("DELETE FROM uo_poll_vote
     WHERE option_id='%d'", (int) ($optionId));
        return DBQuery($query);
      }
    }
  }
  return -1;
}

function HasPollOption($pollId, $optionName) {
  $query = sprintf("SELECT count(*)
    FROM uo_poll_option
    WHERE poll_id='%d' AND name='%s'", (int) $pollId, mysql_adapt_real_escape_string($optionName));

  return DBQueryToValue($query) > 0;
}

function VotePassword($poll, $name) {
  $query = sprintf("SELECT password
    FROM uo_poll_vote
    WHERE poll_id='%d' AND name='%s'", (int) $poll, mysql_adapt_real_escape_string($name));

  $res = DBQueryToValue($query);
  if ($res === -1)
    return null;
  return $res;
}

function VoteName($poll, $userId) {
  if ($userId <= 0)
    return null;
  $query = sprintf("SELECT name
    FROM uo_poll_vote
    WHERE poll_id='%d' AND user_id='%d'", (int) $poll, (int) $userId);

  $val = DBQueryToValue($query);
  if ($val === -1)
    return null;
  return $val;
}

function PollVoters($poll) {
  $query = sprintf("SELECT count(distinct name) as voters FROM uo_poll_vote WHERE poll_id='%d'", (int) $poll);

  return DBQueryToValue($query);
}

function PollRanks($poll, $name, $options) {
  $query = sprintf("SELECT option_id, score
    FROM uo_poll_vote
    WHERE poll_id='%d' AND name='%s'", (int) $poll, mysql_adapt_real_escape_string($name));

  $result = mysql_adapt_query($query);
  $votes = array();
  while ($row = mysqli_fetch_assoc($result)) {
    $votes[$row['option_id']] = $row['score'];
  }

  $ranks = array();
  foreach ($options as $option) {
    $ranks[$option['option_id']] = $votes[$option['option_id']];
  }

  return $ranks;
}

function InsertVote($poll, $user, $name, $password, $ranks) {
  $res = null;

  $query = sprintf("DELETE FROM uo_poll_vote 
    WHERE poll_id='%d' AND name='%s'", intval($poll), mysql_adapt_real_escape_string($name));

  DBQuery($query);

  foreach ($ranks as $option => $rank) {
    $query = sprintf(
      "INSERT INTO uo_poll_vote
        (poll_id, user_id, name, password, option_id, score)
        VALUES (%d, %d, '%s', '%s', %d, %d)", intval($poll), intval($user), mysql_adapt_real_escape_string($name),
      mysql_adapt_real_escape_string($password), (int) ($option), (int) $rank, (int) $rank);
    $res = DBQueryInsert($query);
  }
  return $res;
}

function DeleteVote($pollId, $userId, $name, $votePassword) {
  $query = sprintf("DELETE FROM uo_poll_vote 
     WHERE poll_id='%d' AND name='%s' AND user_id='%d'", (int) ($pollId), mysql_adapt_real_escape_string($name),
    (int) $userId);
  return DBQuery($query);
}

/**
 * Converts an array into a map by option id.
 */
function mapByOption($array, $column = '') {
  $map = array();
  foreach ($array as $row) {
    $map[$row['option_id']] = (float) (empty($column) ? $row : $row[$column]);
  }
  return $map;
}

/**
 * Returns the number of voters for each option.
 *
 * @param int $pollId
 * @return number[]
 */
function PollVotesRanking($pollId) {
  $query = sprintf(
    "SELECT v1.option_id, COUNT(*) AS `score` FROM uo_poll_vote v1
     WHERE `v1`.`poll_id` = %d
     GROUP BY v1.option_id", (int) $pollId);

  return mapByOption(DBQueryToArray($query), 'score');
}

/**
 * Ranks options by sum of (unmodified) scores.
 *
 * @param int $pollId
 * @return
 */
function PollSumRanking($pollId) {
  $query = sprintf(
    "SELECT `option_id`, SUM(`vote`.`score`) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = '%d'
    GROUP BY `option_id`
    ORDER BY `score` DESC", (int) $pollId);
  return mapByOption(DBQueryToArray($query), 'score');
}

/**
 * Ranks options by average of scores.
 *
 * @param int $pollId
 * @return
 */
function PollRangeRanking($pollId) {
  $query = sprintf(
    "SELECT `option_id`, AVG(`vote`.`score`) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = '%d' AND `vote`.`score` > 0
    GROUP BY `option_id`
    ORDER BY `score` DESC", (int) $pollId);
  return mapByOption(DBQueryToArray($query, true), 'score');
}

/**
 * Ranks options by average of scores.
 *
 * @param int $pollId
 * @return
 */
function pollArithmeticRanking($pollId, $normalize = false, $ignoreZero = false, $average = false) {
  $votes = PollVotes($pollId, array('voter'));
  // $options = PollOptions($pollId);

  $count = 0;
  $voter = '';
  $stats = array();

  foreach ($votes as $vote) {
    if ($vote['name'] != $voter) {
      if ($count > 0) {
        if (!isset($max))
          die('internal logic error');
        $stats[$voter] = array('max' => $max, 'count' => $count);
      }
      $voter = $vote['name'];
      $min = PHP_INT_MAX;
      $max = PHP_INT_MIN;
      $count = 0;
    }
    $voter = $vote['name'];
    $score = $vote['score'];
    // $scores[$vote['option_id']] = $score;
    if (!$ignoreZero || $score != 0) {
      if ($score < $min)
        $min = $score;
      if ($score > $max)
        $max = $score;
      ++$count;
    }
  }
  if ($count > 0) {
    $stats[$voter] = array('max' => $max, 'count' => $count);
  }

  $votes = PollVotes($pollId, array('option'));
  $result = array();
  $count = 0;
  $option = null;
  foreach ($votes as $vote) {
    if ($option != $vote['option_id']) {
      if ($count > 0) {
        if ($average)
          $sum /= $count;
        $result[$option] = $sum;
      }
      $sum = 0;
      $count = 0;
      $option = $vote['option_id'];
    }
    $score = $vote['score'];
    if ($normalize) {
      $max = $stats[$vote['name']]['max'];
      if ($max > 0) {
        $score /= $max;
      }
    }
    $sum += $score;
    if (!$ignoreZero || $score > 0)
      ++$count;
  }
  if ($count > 0) {
    if ($average)
      $sum /= $count;
    $result[$option] = $sum;
  }
  return $result;
}

/**
 * Ranks options by number of votes who had this option as (a) first preference (highest score).
 *
 * @param int $pollId
 * @return
 */
function PollFirstPreferenceRanking($pollId) {
  $query = sprintf(
    "SELECT v1.option_id, COUNT(*) AS `score` FROM uo_poll_vote v1 INNER JOIN
  (SELECT vote.name,  MAX(`vote`.score) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = %d
    GROUP BY vote.name
    ORDER BY `score` DESC) max
    ON max.name = v1.name AND max.score = v1.score
    WHERE v1.`poll_id` = %d
    GROUP BY v1.option_id", (int) $pollId, (int) $pollId);

  return mapByOption(DBQueryToArray($query), 'score');
}

/**
 * Ranks options by number of votes who had this option as (a) first preference (highest score).
 *
 * @param int $pollId
 * @return
 */
function PollApproveRanking($pollId) {
  $query = sprintf(
    "SELECT v1.option_id, COUNT(*) AS `score` FROM uo_poll_vote v1 
     WHERE `v1`.`poll_id` = %d AND v1.score > 0
     GROUP BY v1.option_id", (int) $pollId);

  return mapByOption(DBQueryToArray($query), 'score');
}

function getSort($selector) {
  switch ($selector) {
  case 'option':
    return "option_id";
  case 'voter':
    return "name";
  case 'score':
    return "score DESC";
  default:
    return $selector;
  }
}

/**
 * Returns all votes of a poll.
 */
function PollVotes($pollId, $sort = array('voter', 'score DESC')) {
  $sortclause = '';
  foreach ($sort as $selector) {
    if (!empty($sortclause))
      $sortclause .= ", ";
    $sortclause .= getSort($selector);
  }

  if (!empty($sortclause))
    $sortclause = "ORDER BY " . $sortclause;

  $query = sprintf(
    "SELECT vote.option_id, vote.name, score FROM uo_poll_vote vote
    WHERE `vote`.`poll_id` = %d
    $sortclause", (int) $pollId);

  return DBQueryToArray($query);
}

/**
 * Returns the number of voters having a option among the first $position preferences.
 *
 * @param int $pollId
 * @return
 */
function PollAllPreferences($pollId, $position) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $options = PollOptions($pollId);
  $count = count($options);
  $option = '';
  $voter = '';
  $score = 0;
  $pref = array();
  foreach ($votes as $vote) {
    if ($vote['name'] != $voter) {
      $voter = $vote['name'];
      $score = 1;
    }
    $option = $vote['option_id'];
    if (!isset($pref[$option])) {
      $pref[$option] = 0;
    }

    // for ($s = $score; $s <= $count; ++$s) {
    if ($position >= $score && $position <= $count) {
      ++$pref[$option];
    }
    ++$score;
  }
  return $pref;
}

function copeEvaluate(&$games, $scores, $options) {
  if (!empty($scores)) {
    foreach ($options as $t1) {
      foreach ($options as $t2) {
        $c1 = isset($scores[$t1]) ? $scores[$t1] : 0;
        $c2 = isset($scores[$t2]) ? $scores[$t2] : 0;
        if ($c1 == 0 || $c2 == 0)
          $games[$t1][$t2] += 0;
        else {
          $cc = $c1 - $c2;
          if ($cc > 0)
            $games[$t1][$t2] += 1;
          else if ($cc == 0)
            $games[$t1][$t2] += 0;
        }
      }
    }
  }
}

/**
 * Ranks the options according to their copeland score, which simulates the results of a round-robin tournament where
 * a option wins if the majority of voters prefers it to the opposing option.
 *
 * @param int $pollId
 * @return
 */
function PollCopelandRanking($pollId) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $tt = PollOptions($pollId);
  $options = array();
  foreach ($tt as $t1) {
    $options[] = $t1['option_id'];
  }
  $games = array();
  foreach ($options as $t1) {

    $games[$t1] = array();
    foreach ($options as $t2) {
      $games[$t1][$t2] = 0;
    }
  }

  $voter = '';
  $scores = array();
  foreach ($votes as $vote) {
    if ($vote['name'] != $voter) {
      copeEvaluate($games, $scores, $options);
      $voter = $vote['name'];
      $scores = array();
    }
    $scores[$vote['option_id']] = $vote['score'];
  }

  copeEvaluate($games, $scores, $options);

  $scores = array();
  foreach ($options as $t1) {
    $score = 0;
    foreach ($options as $t2) {
      $score += $games[$t1][$t2];
    }
    $scores[$t1] = $score;
  }
  asort($scores);

  return $scores;
}

class BordaDistribution {

  var $count;

  var $buffer;

  var $points;

  var $lastScore;

  function init($votes, $options) {
    $this->count = count($options);
  }

  function points($score, $pos) {
    if ($pos == 0) {
      $this->points = $this->count;
      $this->buffer = 1;
    } else if ($score < $this->lastScore) {
      $this->points -= $this->buffer;
      $this->buffer = 1;
    } else {
      ++$this->buffer;
    }
    $this->lastScore = $score;
    if ($score == 0)
      return 0;

    return $this->points;
  }
}

class GeometricDistribution {

  var $points;

  var $buffer;

  var $lastScore;

  function init($votes, $options) {
    //
  }

  function points($score, $pos) {
    if ($pos == 0) {
      $this->points = 1;
      $this->buffer = 1;
    } else if ($score < $this->lastScore) {
      $this->points += $this->buffer;
      $this->buffer = 1;
    } else {
      ++$this->buffer;
    }
    $this->lastScore = $score;

    if ($score == 0)
      return 0;

    return 1 / 2 ** $this->points;
  }
}

class HarmonicDistribution {

  var $points;

  var $buffer;

  var $lastScore;

  function init($votes, $options) {
    //
  }

  function points($score, $pos) {
    if ($pos == 0) {
      $this->points = 1;
      $this->buffer = 1;
    } else if ($score < $this->lastScore) {
      $this->points += $this->buffer;
      $this->buffer = 1;
    } else {
      ++$this->buffer;
    }
    $this->lastScore = $score;
    if ($score == 0)
      return 0;

    return $this->points > 0 ? 1 / $this->points : 0;
  }
}

class PluralityDistribution {

  var $hiScore;

  function init($votes, $options) {
    //
  }

  function points($score, $pos) {
    if ($pos == 0) {
      $this->hiScore = $score;
      return 1;
    } else if ($score == $this->hiScore)
      return 1;
    else
      return 0;
  }
}

function positionalRanking($pollId, $distribution, $average = false) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $options = PollOptions($pollId);

  $distribution->init($votes, $options);

  $lastVoter = '';
  $scores = array();
  foreach ($options as $option) {
    $scores[$option['option_id']] = 0;
    $counts[$option['option_id']] = 0;
  }
  foreach ($votes as $vote) {
    if ($vote['name'] != $lastVoter) {
      $lastVoter = $vote['name'];
      $pos = 0;
    }

    $points = $distribution->points($vote['score'], $pos++);
    if ($points > 0) {
      $scores[$vote['option_id']] += $points;
      ++$counts[$vote['option_id']];
    }
  }

  if ($average) {
    foreach ($options as $option) {
      if ($scores[$option['option_id']] > 0)
        $scores[$option['option_id']] /= $counts[$option['option_id']] / count($options);
    }
  }

  asort($scores);

  return $scores;
}

/**
 * Ranks the options according to their copeland score, which simulates the results of a round-robin tournament where
 * a option wins if the majority of voters prefers it to the opposing option.
 *
 * @param int $pollId
 * @return
 */
function PollBordaRanking($pollId, $average = false) {
  return positionalRanking($pollId, new BordaDistribution(), $average);
}

function PollPluralityRanking($pollId) {
  return positionalRanking($pollId, new PluralityDistribution());
}

function PollHarmonicRanking($pollId) {
  return positionalRanking($pollId, new HarmonicDistribution());
}

function PollGeometricRanking($pollId) {
  return positionalRanking($pollId, new GeometricDistribution());
}

?>
