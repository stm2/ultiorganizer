<?php

function SafeDivide($dividend,$divisor)
	{
	if (!isset($divisor) || is_null($divisor) || $divisor==0)
		$result = 0;
	else			
		$result = $dividend/$divisor;
	
	return $result;
	}
	
function SecToMin($sec)
	{
	$s = intval($sec);
	$str = $s % 60;
	
	if (strlen($str) == 1)
		$str = "0" . $str;
	
	$s = $s/60;
	return (intval($s).".". $str);
	}
	
function WeekdayString($timestamp, $cap)
	{
	$datetime = date_create($timestamp);
	$weekday = date_format($datetime, 'w');
	
	switch($weekday)
		{
		case 0:
			$weekday = $cap ? 'Su' : 'su';
			break;
		case 1:
			$weekday = $cap ? 'Ma' : 'ma';
			break;
		case 2:
			$weekday = $cap ? 'Ti' : 'ti';
			break;
		case 3:
			$weekday = $cap ? 'Ke' : 'ke';
			break;
		case 4:
			$weekday = $cap ? 'To' : 'to';
			break;
		case 5:
			$weekday = $cap ? 'Pe' : 'pe';
			break;
		case 6:
			$weekday = $cap ? 'La' : 'la';
			break;
		default:
			$weekday = '';
			break;
		}
	
	return $weekday;
	}
	
function ShortDate($timestamp)
	{
	$datetime = date_create($timestamp);
	$shortdate = date_format($datetime, 'j.n.Y');

	return $shortdate;
	}

function DefWeekDateFormat($timestamp)
	{
	return WeekdayString($timestamp,true) ." ". ShortDate($timestamp);
	}
	
function DefHourFormat($timestamp)
	{
	$datetime = date_create($timestamp);
	$hours = date_format($datetime, 'H:i');

	return $hours;
	}	

function DefBirthdayFormat($timestamp)
	{
	$datetime = date_create($timestamp);
	$hours = date_format($datetime, 'd.m.Y');

	return $hours;
	}	
	
function TimeToIcal($timestamp)
	{
	$datetime = date_create($timestamp);
	$time = date_format($datetime, 'Ymd\THi00');

	return $time;
	}

function DefTimestamp()
	{
	return date( 'H:i:s', time());
	}
	
function TimeToSec($timestamp)
	{
	$format = substr_count($timestamp, ".");
	$secs = 0;
	//mm.ss
	if($format==1)
		{
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$secs = intval($tmp1)*60+intval($tmp2);
		}
	//hh.mm.ss
	elseif($format==2)
		{
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$tmp3 = strtok(".");
		$secs = intval($tmp1)*3600+intval($tmp2)*60+intval($tmp3);
		}
	return $secs;
	}	
?>