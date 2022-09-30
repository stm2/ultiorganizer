<?php
include_once $include_prefix . 'lib/debug.functions.php';

function PollStatuses() {
  return array(0 => _("no poll"), 2 => _("team entry"), 4 => _("voting"), 6 => _("closed"));
}

function HasPolls($seasonId) {
  $series = SeasonSeries($seasonId);

  foreach ($series as $seriesRow) {
    $poll = SeriesPoll($seriesRow['series_id']);
    return !empty($poll) && $poll['status'] > 0;
  }
  return false;
}

function PollInfo($pollId) {
  $query = sprintf("SELECT * FROM uo_team_poll
                    WHERE poll_id=%d", (int) $pollId);
  return DBQueryToRow($query);
}

function CanVote($user, $name, $pollId) {
  $info = PollInfo($pollId);
  $status = 0;
  if (!empty($info['status']))
    $status = $info['status'];

  return $status == 4; // || ($status > 0 && isSuperAdmin());
}

function HasResults($pollId) {
  $info = PollInfo($pollId);
  $status = 0;
  if (!empty($info['status']))
    $status = $info['status'];

  return $status == 6; // || ($status > 0 && isSuperAdmin());
}

/**
 * Returns all seasons having at least one poll.
 */
function PollSeasons() {
  $query = sprintf(
    "SELECT sn.season_id, sn.name
    FROM uo_season sn, uo_series sr, uo_team_poll pl
    WHERE sn.season_id = sr.season AND pl.series_id = sr.series_id AND pl.status > 0
    GROUP BY sn.season_id
    ORDER BY starttime DESC");
  return DBQueryToArray($query);
}

function SeriesPoll($seriesId) {
  $query = sprintf("
		SELECT *
		FROM uo_team_poll
		WHERE series_id=%d", (int) $seriesId);

  return DBQueryToRow($query);
}

function AddPoll($seriesId, $seasonId, $params) {
  if (hasEditSeasonSeriesRight($seasonId)) {
    $query = sprintf("
               INSERT INTO uo_team_poll
               (series_id, password, description, status)
               VALUES (%d, '%s', '%s', 2)", (int) $seriesId, mysql_adapt_real_escape_string($params['password']),
      mysql_adapt_real_escape_string($params['description']));
    return DBQueryInsert($query);
  } else
    die("Insufficient rights to edit series");
}

function SetPoll($id, $seriesId, $seasonId, $params) {
  if (hasEditSeasonSeriesRight($seasonId)) {
    $query = sprintf("
               UPDATE uo_team_poll SET password='%s', description='%s', status = %d
               WHERE poll_id = %d", mysql_adapt_real_escape_string($params['password']),
      mysql_adapt_real_escape_string($params['description']), (int) $params['status'], (int) $id);
    return DBQuery($query);
  } else
    die("Insufficient rights to edit series");
}

function PollTeams($teamPollId) {
  $query = sprintf("
		SELECT *
		FROM uo_poll_team
		WHERE poll_id=%d", (int) $teamPollId);

  return DBQueryToArray($query);
}

function PollTeam($teamId) {
  $query = sprintf("
		SELECT *
		FROM uo_poll_team
		WHERE pt_id=%d", (int) $teamId);

  return DBQueryToRow($query);
}

function AddPollTeam($params) {
  $query = sprintf(
    "INSERT INTO uo_poll_team
      (poll_id, user_id, name, mentor, description, status )
      VALUES (%d, %d, '%s', '%s', '%s', %d)", (int) $params['poll_id'], (int) $params['user_id'],
    mysql_adapt_real_escape_string($params['name']), mysql_adapt_real_escape_string($params['mentor']),
    mysql_adapt_real_escape_string($params['description']), (int) $params['status']);
  return DBQueryInsert($query);
}

function SetPollTeam($ptId, $params) {
  $query = sprintf("UPDATE uo_poll_team SET
      user_id='%d', name='%s', mentor='%s', description='%s'
      WHERE pt_id=%d", (int) $params['user_id'], mysql_adapt_real_escape_string($params['name']),
    mysql_adapt_real_escape_string($params['mentor']), mysql_adapt_real_escape_string($params['description']),
    (int) $ptId);
  return DBQuery($query);
}

function HasPollTeam($teamName) {
  $query = sprintf("SELECT count(*)
    FROM uo_poll_team
    WHERE name='%s'", mysql_adapt_real_escape_string($teamName));

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

function PollRanks($poll, $name, $teams) {
  $query = sprintf("SELECT team_id, score
    FROM uo_poll_vote
    WHERE poll_id='%d' AND name='%s'", (int) $poll, mysql_adapt_real_escape_string($name));

  $result = mysql_adapt_query($query);
  $votes = array();
  while ($row = mysqli_fetch_assoc($result)) {
    $votes[$row['team_id']] = $row['score'];
  }

  $ranks = array();
  foreach ($teams as $team) {
    $ranks[$team['pt_id']] = $votes[$team['pt_id']];
  }

  return $ranks;
}

function InsertVote($poll, $user, $name, $password, $ranks) {
  $res = null;

  $query = sprintf("DELETE FROM uo_poll_vote 
    WHERE poll_id='%d' AND name='%s'", intval($poll), mysql_adapt_real_escape_string($name));

  DBQuery($query);

  foreach ($ranks as $team => $rank) {
    $query = sprintf(
      "INSERT INTO uo_poll_vote
        (poll_id, user_id, name, password, team_id, score)
        VALUES (%d, %d, '%s', '%s', %d, %d)", intval($poll), intval($user), mysql_adapt_real_escape_string($name),
      mysql_adapt_real_escape_string($password), (int) ($team), (int) $rank, (int) $rank);
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
 * Converts an array into a map by team id.
 */
function mapByTeam($array, $column = '') {
  $map = array();
  foreach ($array as $row) {
    $map[$row['team_id']] = (float) (empty($column) ? $row : $row[$column]);
  }
  return $map;
}

/**
 * Ranks teams by sum of (unmodified) scores.
 *
 * @param int $pollId
 * @return
 */
function PollSumRanking($pollId) {
  $query = sprintf(
    "SELECT `team_id`, SUM(`vote`.`score`) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = '%d'
    GROUP BY `team_id`
    ORDER BY `score` DESC", (int) $pollId);
  return mapByTeam(DBQueryToArray($query), 'score');
}

/**
 * Ranks teams by average of scores.
 *
 * @param int $pollId
 * @return
 */
function PollRangeRanking($pollId) {
  $query = sprintf(
    "SELECT `team_id`, AVG(`vote`.`score`) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = '%d' AND `vote`.`score` > 0
    GROUP BY `team_id`
    ORDER BY `score` DESC", (int) $pollId);
  return mapByTeam(DBQueryToArray($query, true), 'score');
}

/**
 * Ranks teams by average of scores.
 *
 * @param int $pollId
 * @return
 */
function pollArithmeticRanking($pollId, $normalize = false, $ignoreZero = false, $average = false) {
  $votes = PollVotes($pollId, array('voter'));
  // $teams = PollTeams($pollId);

  $count = 0;
  $voter = '';
  $stats = array();

  foreach ($votes as $vote) {
    $result[vote['team_id']] += 1;
    if ($vote['name'] != $voter) {
      if ($count > 0) {
        $stats[$voter] = array('max' => $max, 'count' => $count);
      }
      $voter = $vote['name'];
      $min = PHP_INT_MAX;
      $max = PHP_INT_MIN;
      $count = 0;
    }
    $voter = $vote['name'];
    $score = $vote['score'];
    // $scores[$vote['team_id']] = $score;
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

  // var_dump($stats);

  $votes = PollVotes($pollId, array('team'));
  $result = array();
  $count = 0;
  $team = null;
  foreach ($votes as $vote) {
    if ($team != $vote['team_id']) {
      if ($count > 0) {
        if ($average)
          $sum /= $count;
        $result[$team] = $sum;
      }
      $sum = 0;
      $count = 0;
      $team = $vote['team_id'];
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
    $result[$team] = $sum;
  }
  return $result;
}

/**
 * Ranks teams by number of votes who had this team as (a) first preference (highest score).
 *
 * @param int $pollId
 * @return
 */
function PollFirstPreferenceRanking($pollId) {
  $query = sprintf(
    "SELECT v1.team_id, COUNT(*) AS `score` FROM uo_poll_vote v1 INNER JOIN
  (SELECT vote.name,  MAX(`vote`.score) AS `score`
    FROM `uo_poll_vote` `vote`
    WHERE `vote`.`poll_id` = %d
    GROUP BY vote.name
    ORDER BY `score` DESC) max
    ON max.name = v1.name AND max.score = v1.score
    WHERE v1.`poll_id` = %d
    GROUP BY v1.team_id", (int) $pollId, (int) $pollId);

  return mapByTeam(DBQueryToArray($query), 'score');
}

/**
 * Ranks teams by number of votes who had this team as (a) first preference (highest score).
 *
 * @param int $pollId
 * @return
 */
function PollApproveRanking($pollId) {
  $query = sprintf(
    "SELECT v1.team_id, COUNT(*) AS `score` FROM uo_poll_vote v1 
     WHERE `v1`.`poll_id` = %d AND v1.score > 0
     GROUP BY v1.team_id", (int) $pollId);

  return mapByTeam(DBQueryToArray($query), 'score');
}

function getSort($selector) {
  switch ($selector) {
  case 'team':
    return "team_id";
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
    "SELECT vote.team_id, vote.name, score FROM uo_poll_vote vote
    WHERE `vote`.`poll_id` = %d
    $sortclause", (int) $pollId);

  return DBQueryToArray($query);
}

/**
 * Returns the number of voters having a team among the first $position preferences.
 *
 * @param int $pollId
 * @return
 */
function PollAllPreferences($pollId, $position) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $teams = PollTeams($pollId);
  $count = count($teams);
  $team = '';
  $voter = '';
  $score = 0;
  $pref = array();
  foreach ($votes as $vote) {
    if ($vote['name'] != $voter) {
      $voter = $vote['name'];
      $score = 1;
    }
    $team = $vote['team_id'];
    if (!isset($pref[$team])) {
      $pref[$team] = 0;
    }

    // for ($s = $score; $s <= $count; ++$s) {
    if ($position >= $score && $position <= $count) {
      ++$pref[$team];
    }
    ++$score;
  }
  return $pref;
}

/**
 * Ranks the teams according to their copeland score, which simulates the results of a round-robin tournament where
 * a team wins if the majority of voters prefers it to the opposing team.
 *
 * @param int $pollId
 * @return
 */
function PollCopelandRanking($pollId) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $tt = PollTeams($pollId);
  $teams = array();
  foreach ($tt as $t1) {
    $teams[] = $t1['pt_id'];
  }
  $count = count($teams);
  $games = array();
  foreach ($teams as $t1) {

    $games[$t1] = array();
    foreach ($teams as $t2) {
      $games[$t1][$t2] = 0;
    }
  }

  $voter = '';
  foreach ($votes as $vote) {
    if ($vote['name'] != $voter) {
      $voter = $vote['name'];
      $scores = array();
    }
    $scores[$vote['team_id']] = $vote['score'];
    if (count($scores) == $count) {
      foreach ($teams as $t1) {
        foreach ($teams as $t2) {
          $cc = $scores[$t1] - $scores[$t2];
          if ($cc > 0)
            $games[$t1][$t2] += 2;
          else if ($cc == 0)
            $games[$t1][$t2] += 1;
        }
      }
    }
  }
  $scores = array();
  foreach ($teams as $t1) {
    $score = 0;
    foreach ($teams as $t2) {
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

  function init($votes, $teams) {
    $this->count = count($teams);
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

    return $this->points;
  }
}

class GeometricDistribution {

  var $points;

  var $buffer;

  var $lastScore;

  function init($votes, $teams) {
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

    return 1 / 2 ** $this->points;
  }
}

class HarmonicDistribution {

  var $points;

  var $buffer;

  var $lastScore;

  function init($votes, $teams) {
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

    return 1 / $this->points;
  }
}

class PluralityDistribution {

  var $hiScore;

  function init($votes, $teams) {
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

function positionalRanking($pollId, $distribution) {
  $votes = PollVotes($pollId, array('voter', 'score'));
  $teams = PollTeams($pollId);

  $distribution->init($votes, $teams);

  $lastVoter = '';
  $scores = array();
  foreach ($teams as $team) {
    $scores[$team['pt_id']] = 0;
  }
  foreach ($votes as $vote) {
    if ($vote['name'] != $lastVoter) {
      $lastVoter = $vote['name'];
      $pos = 0;
    }

    $points = $distribution->points($vote['score'], $pos++);
    $scores[$vote['team_id']] += $points;
  }

  asort($scores);

  return $scores;
}

/**
 * Ranks the teams according to their copeland score, which simulates the results of a round-robin tournament where
 * a team wins if the majority of voters prefers it to the opposing team.
 *
 * @param int $pollId
 * @return
 */
function PollBordaRanking($pollId) {
  return positionalRanking($pollId, new BordaDistribution());
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
