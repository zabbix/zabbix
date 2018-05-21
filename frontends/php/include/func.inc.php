<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Verify that function exists and can be called as a function.
 *
 * @param array		$names
 *
 * @return bool
 */
function zbx_is_callable(array $names) {
	foreach ($names as $name) {
		if (!is_callable($name)) {
			return false;
		}
	}

	return true;
}

/************ REQUEST ************/
function redirect($url) {
	$curl = (new CUrl($url))->removeArgument('sid');
	header('Location: '.$curl->getUrl());
	exit;
}

function jsRedirect($url, $timeout = null) {
	$script = is_numeric($timeout)
		? 'setTimeout(\'window.location="'.$url.'"\', '.($timeout * 1000).')'
		: 'window.location.replace("'.$url.'");';

	insert_js($script);
}

/**
 * Check if request exist.
 *
 * @param string	$name
 *
 * @return bool
 */
function hasRequest($name) {
	return isset($_REQUEST[$name]);
}

/**
 * Check request, if exist request - return request value, else return default value.
 *
 * @param string	$name
 * @param mixed		$def
 *
 * @return mixed
 */
function getRequest($name, $def = null) {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $def;
}

function countRequest($str = null) {
	if (!empty($str)) {
		$count = 0;

		foreach ($_REQUEST as $name => $value) {
			if (strpos($name, $str) !== false) {
				$count++;
			}
		}

		return $count;
	}
	else {
		return count($_REQUEST);
	}
}

/************ COOKIES ************/
function get_cookie($name, $default_value = null) {
	if (isset($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}

	return $default_value;
}

function zbx_setcookie($name, $value, $time = null) {
	setcookie($name, $value, isset($time) ? $time : 0, null, null, HTTPS, true);
	$_COOKIE[$name] = $value;
}

function zbx_unsetcookie($name) {
	zbx_setcookie($name, null, -99999);
	unset($_COOKIE[$name]);
}

/************* DATE *************/
function getMonthCaption($num) {
	switch ($num) {
		case 1: return _('January');
		case 2: return _('February');
		case 3: return _('March');
		case 4: return _('April');
		case 5: return _('May');
		case 6: return _('June');
		case 7: return _('July');
		case 8: return _('August');
		case 9: return _('September');
		case 10: return _('October');
		case 11: return _('November');
		case 12: return _('December');
	}

	return _s('[Wrong value for month: "%s" ]', $num);
}

function getDayOfWeekCaption($num) {
	switch ($num) {
		case 1: return _('Monday');
		case 2: return _('Tuesday');
		case 3: return _('Wednesday');
		case 4: return _('Thursday');
		case 5: return _('Friday');
		case 6: return _('Saturday');
		case 0:
		case 7: return _('Sunday');
	}

	return _s('[Wrong value for day: "%s" ]', $num);
}

// Convert seconds (0..SEC_PER_WEEK) to string representation. For example, 212400 -> 'Tuesday 11:00'
function dowHrMinToStr($value, $display24Hours = false) {
	$dow = $value - $value % SEC_PER_DAY;
	$hr = $value - $dow;
	$hr -= $hr % SEC_PER_HOUR;
	$min = $value - $dow - $hr;
	$min -= $min % SEC_PER_MIN;

	$dow /= SEC_PER_DAY;
	$hr /= SEC_PER_HOUR;
	$min /= SEC_PER_MIN;

	if ($display24Hours && $hr == 0 && $min == 0) {
		$dow--;
		$hr = 24;
	}

	return sprintf('%s %02d:%02d', getDayOfWeekCaption($dow), $hr, $min);
}

// Convert Day Of Week, Hours and Minutes to seconds representation. For example, 2 11:00 -> 212400. false if error occurred
function dowHrMinToSec($dow, $hr, $min) {
	if (zbx_empty($dow) || zbx_empty($hr) || zbx_empty($min) || !zbx_ctype_digit($dow) || !zbx_ctype_digit($hr) || !zbx_ctype_digit($min)) {
		return false;
	}

	if ($dow == 7) {
		$dow = 0;
	}

	if ($dow < 0 || $dow > 6) {
		return false;
	}

	if ($hr < 0 || $hr > 24) {
		return false;
	}

	if ($min < 0 || $min > 59) {
		return false;
	}

	return $dow * SEC_PER_DAY + $hr * SEC_PER_HOUR + $min * SEC_PER_MIN;
}

// Convert timestamp to string representation. Return 'Never' if 0.
function zbx_date2str($format, $value = null) {
	static $weekdaynames, $weekdaynameslong, $months, $monthslong;

	$prefix = '';

	if ($value === null) {
		$value = time();
	}
	elseif ($value > ZBX_MAX_DATE) {
		$prefix = '> ';
		$value = ZBX_MAX_DATE;
	}
	elseif (!$value) {
		return _('Never');
	}

	if (!is_array($weekdaynames)) {
		$weekdaynames = [
			0 => _('Sun'),
			1 => _('Mon'),
			2 => _('Tue'),
			3 => _('Wed'),
			4 => _('Thu'),
			5 => _('Fri'),
			6 => _('Sat')
		];
	}

	if (!is_array($weekdaynameslong)) {
		$weekdaynameslong = [
			0 => _('Sunday'),
			1 => _('Monday'),
			2 => _('Tuesday'),
			3 => _('Wednesday'),
			4 => _('Thursday'),
			5 => _('Friday'),
			6 => _('Saturday')
		];
	}

	if (!is_array($months)) {
		$months = [
			1 => _('Jan'),
			2 => _('Feb'),
			3 => _('Mar'),
			4 => _('Apr'),
			5 => _x('May', 'May short'),
			6 => _('Jun'),
			7 => _('Jul'),
			8 => _('Aug'),
			9 => _('Sep'),
			10 => _('Oct'),
			11 => _('Nov'),
			12 => _('Dec')
		];
	}

	if (!is_array($monthslong)) {
		$monthslong = [
			1 => _('January'),
			2 => _('February'),
			3 => _('March'),
			4 => _('April'),
			5 => _('May'),
			6 => _('June'),
			7 => _('July'),
			8 => _('August'),
			9 => _('September'),
			10 => _('October'),
			11 => _('November'),
			12 => _('December')
		];
	}

	$rplcs = [
		'l' => $weekdaynameslong[date('w', $value)],
		'F' => $monthslong[date('n', $value)],
		'D' => $weekdaynames[date('w', $value)],
		'M' => $months[date('n', $value)]
	];

	$output = $part = '';
	$length = strlen($format);

	for ($i = 0; $i < $length; $i++) {
		$pchar = ($i > 0) ? substr($format, $i - 1, 1) : '';
		$char = substr($format, $i, 1);

		if ($pchar != '\\' && isset($rplcs[$char])) {
			$output .= (strlen($part) ? date($part, $value) : '').$rplcs[$char];
			$part = '';
		}
		else {
			$part .= $char;
		}
	}

	$output .= (strlen($part) > 0) ? date($part, $value) : '';

	return $prefix.$output;
}

/**
 * Calculates and converts timestamp to string represenation.
 *
 * @param int|string $start_date Start date timestamp.
 * @param int|string $end_date   End date timestamp.
 *
 * @return string
 */
function zbx_date2age($start_date, $end_date = 0) {
	$end_date = ($end_date != 0) ? $end_date : time();

	return convertUnitsS($end_date - $start_date);
}

function zbxDateToTime($strdate) {
	if (6 == sscanf($strdate, '%04d%02d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes, $seconds)) {
		return mktime($hours, $minutes, $seconds, $month, $date, $year);
	}
	elseif (5 == sscanf($strdate, '%04d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes)) {
		return mktime($hours, $minutes, 0, $month, $date, $year);
	}
	else {
		return ($strdate && is_numeric($strdate)) ? $strdate : time();
	}
}

/**
 * Correcting adding one unix timestamp to another.
 *
 * @param int		$sec
 * @param mixed		$unixtime	Can accept values:
 *									1) int - unix timestamp,
 *									2) string - date in YmdHis or YmdHi formats,
 *									3) null - current unixtime stamp will be used
 *
 * @return int
 */
function zbxAddSecondsToUnixtime($sec, $unixtime) {
	return strtotime('+'.$sec.' seconds', zbxDateToTime($unixtime));
}

/*************** CONVERTING ******************/
/**
 * Convert the Windows new line (CR+LF) to Linux style line feed (LF).
 *
 * @param string $string  Input string that will be converted.
 *
 * @return string
 */
function CRLFtoLF($string) {
	return str_replace("\r\n", "\n", $string);
}

function rgb2hex($color) {
	$HEX = [
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	];
	foreach ($HEX as $id => $value) {
		if (strlen($value) != 2) {
			$HEX[$id] = '0'.$value;
		}
	}

	return $HEX[0].$HEX[1].$HEX[2];
}

function hex2rgb($color) {
	if ($color[0] == '#') {
		$color = substr($color, 1);
	}

	if (strlen($color) == 6) {
		list($r, $g, $b) = [$color[0].$color[1], $color[2].$color[3], $color[4].$color[5]];
	}
	elseif (strlen($color) == 3) {
		list($r, $g, $b) = [$color[0].$color[0], $color[1].$color[1], $color[2].$color[2]];
	}
	else {
		return false;
	}

	return [hexdec($r), hexdec($g), hexdec($b)];
}

function zbx_num2bitstr($num, $rev = false) {
	if (!is_numeric($num)) {
		return 0;
	}

	$sbin = 0;
	$strbin = '';

	$len = 32;
	if (bccomp($num, ZBX_MAX_INT32) > 0) {
		$len = 64;
	}

	for ($i = 0; $i < $len; $i++) {
		$sbin = 1 << $i;
		$bit = ($sbin & $num) ? '1' : '0';
		if ($rev) {
			$strbin .= $bit;
		}
		else {
			$strbin = $bit.$strbin;
		}
	}

	return $strbin;
}

/**
 * Converts strings like 2M or 5k to bytes
 *
 * @param string $val
 *
 * @return int
 */
function str2mem($val) {
	$val = trim($val);
	$last = strtolower(substr($val, -1));
	$val = (int) $val;

	switch ($last) {
		case 'g':
			$val *= 1024;
			// break; is not missing here
		case 'm':
			$val *= 1024;
			// break; is not missing here
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function mem2str($size) {
	$prefix = 'B';
	if ($size > 1048576) {
		$size = $size / 1048576;
		$prefix = 'M';
	}
	elseif ($size > 1024) {
		$size = $size / 1024;
		$prefix = 'K';
	}

	return round($size, 6).$prefix;
}

function convertUnitsUptime($value) {
	if (($secs = round($value)) < 0) {
		$value = '-';
		$secs = -$secs;
	}
	else {
		$value = '';
	}

	$days = floor($secs / SEC_PER_DAY);
	$secs -= $days * SEC_PER_DAY;

	$hours = floor($secs / SEC_PER_HOUR);
	$secs -= $hours * SEC_PER_HOUR;

	$mins = floor($secs / SEC_PER_MIN);
	$secs -= $mins * SEC_PER_MIN;

	if ($days != 0) {
		$value .= _n('%1$d day', '%1$d days', $days).', ';
	}
	$value .= sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

	return $value;
}

/**
 * Converts a time period to a human-readable format.
 *
 * The following units are used: years, months, days, hours, minutes, seconds and milliseconds.
 *
 * Only the three highest units are displayed: #y #m #d, #m #d #h, #d #h #mm and so on.
 *
 * If some value is equal to zero, it is omitted. For example, if the period is 1y 0m 4d, it will be displayed as
 * 1y 4d, not 1y 0m 4d or 1y 4d #h.
 *
 * @param int $value	time period in seconds
 * @param bool $ignore_millisec	without ms (1s 200 ms = 1.2s)
 *
 * @return string
 */
function convertUnitsS($value, $ignore_millisec = false) {
	$secs = round($value * 1000, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT) / 1000;
	if ($secs < 0) {
		$secs = -$secs;
		$str = '-';
	}
	else {
		$str = '';
	}

	$values = ['y' => null, 'm' => null, 'd' => null, 'h' => null, 'mm' => null, 's' => null, 'ms' => null];

	/*
	 * $n_unit == 4,	(#y #m #d)
	 * $n_unit == 3,	(#m #d #h)
	 * $n_unit == 2,	(#d #h #mm)
	 * $n_unit == 1,	(#h #mm #s)
	 * $n_unit == 0,	(#mm #s) or (#mm #s #ms)
	 */
	$n_unit = 0;

	$n = floor($secs / SEC_PER_YEAR);
	if ($n != 0) {
		$secs -= $n * SEC_PER_YEAR;
		$n_unit = 4;

		$values['y'] = $n;
	}

	$n = floor($secs / SEC_PER_MONTH);
	$secs -= $n * SEC_PER_MONTH;

	if ($n == 12) {
		$values['y']++;
	}
	else {
		if ($n != 0) {
			$values['m'] = $n;
			if ($n_unit == 0) {
				$n_unit = 3;
			}
		}

		$n = floor($secs / SEC_PER_DAY);
		if ($n != 0) {
			$secs -= $n * SEC_PER_DAY;
			$values['d'] = $n;
			if ($n_unit == 0) {
				$n_unit = 2;
			}
		}

		$n = floor($secs / SEC_PER_HOUR);
		if ($n_unit < 4 && $n != 0) {
			$secs -= $n * SEC_PER_HOUR;
			$values['h'] = $n;
			if ($n_unit == 0) {
				$n_unit = 1;
			}
		}

		$n = floor($secs / SEC_PER_MIN);
		if ($n_unit < 3 && $n != 0) {
			$secs -= $n * SEC_PER_MIN;
			$values['mm'] = $n;
		}

		$n = floor($secs);
		if ($n_unit < 2 && $n != 0) {
			$secs -= $n;
			$values['s'] = $n;
		}

		if ($ignore_millisec) {
			$n = round($secs, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT);
			if ($n_unit < 1 && $n != 0) {
				$values['s'] += $n;
			}
		}
		else {
			$n = round($secs * 1000, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT);
			if ($n_unit < 1 && $n != 0) {
				$values['ms'] = $n;
			}
		}
	}

	$str .= isset($values['y']) ? $values['y']._x('y', 'year short').' ' : '';
	$str .= isset($values['m']) ? $values['m']._x('m', 'month short').' ' : '';
	$str .= isset($values['d']) ? $values['d']._x('d', 'day short').' ' : '';
	$str .= isset($values['h']) ? $values['h']._x('h', 'hour short').' ' : '';
	$str .= isset($values['mm']) ? $values['mm']._x('m', 'minute short').' ' : '';
	$str .= isset($values['s']) ? $values['s']._x('s', 'second short').' ' : '';
	$str .= isset($values['ms']) ? $values['ms']._x('ms', 'millisecond short') : '';

	return $str ? rtrim($str) : '0';
}

/**
 * Converts value to actual value.
 * Example:
 * 	6442450944 B convert to 6 GB
 *
 * @param array  $options
 * @param string $options['value']
 * @param string $options['units']
 * @param string $options['convert']
 * @param string $options['byteStep']
 * @param string $options['pow']
 * @param bool   $options['ignoreMillisec']
 * @param string $options['length']
 *
 * @return string
 */
function convert_units($options = []) {
	$defOptions = [
		'value' => null,
		'units' => null,
		'convert' => ITEM_CONVERT_WITH_UNITS,
		'byteStep' => false,
		'pow' => false,
		'ignoreMillisec' => false,
		'length' => false
	];

	$options = zbx_array_merge($defOptions, $options);

	// special processing for unix timestamps
	if ($options['units'] == 'unixtime') {
		return zbx_date2str(DATE_TIME_FORMAT_SECONDS, $options['value']);
	}

	// special processing of uptime
	if ($options['units'] == 'uptime') {
		return convertUnitsUptime($options['value']);
	}

	// special processing for seconds
	if ($options['units'] == 's') {
		return convertUnitsS($options['value'], $options['ignoreMillisec']);
	}

	// black list of units that should have no multiplier prefix (K, M, G etc) applied
	$blackList = ['%', 'ms', 'rpm', 'RPM'];

	// add to the blacklist if unit is prefixed with '!'
	if ($options['units'] !== null && $options['units'] !== '' && $options['units'][0] === '!') {
		$options['units'] = substr($options['units'], 1);
		$blackList[] = $options['units'];
	}

	// any other unit
	if (in_array($options['units'], $blackList) || (zbx_empty($options['units'])
			&& ($options['convert'] == ITEM_CONVERT_WITH_UNITS))) {
		if (preg_match('/\.\d+$/', $options['value'])) {
			$format = (abs($options['value']) >= ZBX_UNITS_ROUNDOFF_THRESHOLD)
				? '%.'.ZBX_UNITS_ROUNDOFF_UPPER_LIMIT.'f'
				: '%.'.ZBX_UNITS_ROUNDOFF_LOWER_LIMIT.'f';
			$options['value'] = sprintf($format, $options['value']);
		}
		$options['value'] = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U', '$1$2$3', $options['value']);
		$options['value'] = rtrim($options['value'], '.');

		return trim($options['value'].' '.$options['units']);
	}

	// if one or more items is B or Bps, then Y-scale use base 8 and calculated in bytes
	if ($options['byteStep']) {
		$step = 1024;
	}
	else {
		switch ($options['units']) {
			case 'Bps':
			case 'B':
				$step = 1024;
				$options['convert'] = $options['convert'] ? $options['convert'] : ITEM_CONVERT_NO_UNITS;
				break;
			case 'b':
			case 'bps':
				$options['convert'] = $options['convert'] ? $options['convert'] : ITEM_CONVERT_NO_UNITS;
			default:
				$step = 1000;
		}
	}

	if ($options['value'] < 0) {
		$abs = bcmul($options['value'], '-1');
	}
	else {
		$abs = $options['value'];
	}

	if (bccomp($abs, 1) == -1) {
		$options['value'] = round($options['value'], ZBX_UNITS_ROUNDOFF_MIDDLE_LIMIT);
		$options['value'] = ($options['length'] && $options['value'] != 0)
			? sprintf('%.'.$options['length'].'f',$options['value']) : $options['value'];

		return trim($options['value'].' '.$options['units']);
	}

	// init intervals
	static $digitUnits;
	if (is_null($digitUnits)) {
		$digitUnits = [];
	}

	if (!isset($digitUnits[$step])) {
		$digitUnits[$step] = [
			['pow' => 0, 'short' => ''],
			['pow' => 1, 'short' => 'K'],
			['pow' => 2, 'short' => 'M'],
			['pow' => 3, 'short' => 'G'],
			['pow' => 4, 'short' => 'T'],
			['pow' => 5, 'short' => 'P'],
			['pow' => 6, 'short' => 'E'],
			['pow' => 7, 'short' => 'Z'],
			['pow' => 8, 'short' => 'Y']
		];

		foreach ($digitUnits[$step] as $dunit => $data) {
			// skip milli & micro for values without units
			$digitUnits[$step][$dunit]['value'] = bcpow($step, $data['pow'], 9);
		}
	}


	$valUnit = ['pow' => 0, 'short' => '', 'value' => $options['value']];

	if ($options['pow'] === false || $options['value'] == 0) {
		foreach ($digitUnits[$step] as $dnum => $data) {
			if (bccomp($abs, $data['value']) > -1) {
				$valUnit = $data;
			}
			else {
				break;
			}
		}
	}
	else {
		foreach ($digitUnits[$step] as $data) {
			if ($options['pow'] == $data['pow']) {
				$valUnit = $data;
				break;
			}
		}
	}

	if (round($valUnit['value'], ZBX_UNITS_ROUNDOFF_MIDDLE_LIMIT) > 0) {
		$valUnit['value'] = bcdiv(sprintf('%.10f',$options['value']), sprintf('%.10f', $valUnit['value'])
			, ZBX_PRECISION_10);
	}
	else {
		$valUnit['value'] = 0;
	}

	switch ($options['convert']) {
		case 0: $options['units'] = trim($options['units']);
		case 1: $desc = $valUnit['short']; break;
	}

	$options['value'] = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U','$1$2$3', round($valUnit['value'],
		ZBX_UNITS_ROUNDOFF_UPPER_LIMIT));

	$options['value'] = rtrim($options['value'], '.');

	// fix negative zero
	if (bccomp($options['value'], 0) == 0) {
		$options['value'] = 0;
	}

	return trim(sprintf('%s %s%s', $options['length']
		? sprintf('%.'.$options['length'].'f',$options['value'])
		: $options['value'], $desc, $options['units']));
}

/**
 * Convert time format with suffixes to seconds.
 * Examples:
 *		10m = 600
 *		3d = 10800
 *
 * @param string $time
 *
 * @return int
 */
function timeUnitToSeconds($time) {
	preg_match('/^((\d)+)(['.ZBX_TIME_SUFFIXES.'])?$/', $time, $matches);

	if (array_key_exists(3, $matches)) {
		$suffix = $matches[3];
		$time = $matches[1];

		switch ($suffix) {
			case 's':
				$sec = $time;
				break;
			case 'm':
				$sec = bcmul($time, SEC_PER_MIN);
				break;
			case 'h':
				$sec = bcmul($time, SEC_PER_HOUR);
				break;
			case 'd':
				$sec = bcmul($time, SEC_PER_DAY);
				break;
			case 'w':
				$sec = bcmul($time, SEC_PER_WEEK);
				break;
		}
	}
	else {
		$sec = $matches[0];
	}

	return $sec;
}

/**
 * Converts value with suffix to actual value.
 * Supported time suffixes: s, m, h, d, w
 * Supported metric suffixes: K, M, G, T
 *
 * @param string $value
 *
 * @return string
 */
function convertFunctionValue($value) {
	$suffix = $value[strlen($value) - 1];
	if (!ctype_digit($suffix)) {
		$value = substr($value, 0, strlen($value) - 1);

		switch ($suffix) {
			case 's':
				break;
			case 'm':
				$value = bcmul($value, '60');
				break;
			case 'h':
				$value = bcmul($value, '3600');
				break;
			case 'd':
				$value = bcmul($value, '86400');
				break;
			case 'w':
				$value = bcmul($value, '604800');
				break;
			case 'K':
				$value = bcmul($value, '1024');
				break;
			case 'M':
				$value = bcmul($value, '1048576');
				break;
			case 'G':
				$value = bcmul($value, '1073741824');
				break;
			case 'T':
				$value = bcmul($value, '1099511627776');
				break;
		}
	}

	return $value;
}

/************* ZBX MISC *************/

/**
 * Swap two values.
 *
 * @param mixed $a first value
 * @param mixed $b second value
 */
function zbx_swap(&$a, &$b) {
	$tmp = $a;
	$a = $b;
	$b = $tmp;
}

function zbx_avg($values) {
	zbx_value2array($values);
	$sum = 0;
	foreach ($values as $value) {
		$sum = bcadd($sum, $value);
	}

	return bcdiv($sum, count($values));
}

// accepts parameter as integer either
function zbx_ctype_digit($x) {
	return ctype_digit(strval($x));
}

/**
 * Returns true if the value is an empty string, empty array or null.
 *
 * @deprecated use strict comparison instead
 *
 * @param $value
 *
 * @return bool
 */
function zbx_empty($value) {
	if ($value === null) {
		return true;
	}
	if (is_array($value) && empty($value)) {
		return true;
	}
	if (is_string($value) && $value === '') {
		return true;
	}

	return false;
}

function zbx_is_int($var) {
	if (is_int($var)) {
		return true;
	}

	if (is_string($var)) {
		if (function_exists('ctype_digit') && ctype_digit($var) || strcmp(intval($var), $var) == 0) {
			return true;
		}
	}
	else {
		if ($var > 0 && zbx_ctype_digit($var)) {
			return true;
		}
	}

	return preg_match("/^\-?\d{1,20}+$/", $var);
}

/**
 * Look for two arrays field value and create 3 array lists, one with arrays where field value exists only in first array
 * second with arrays where field values are only in second array and both where fiel values are in both arrays.
 *
 * @param array  $primary
 * @param array  $secondary
 * @param string $field field that is searched in arrays
 *
 * @return array
 */
function zbx_array_diff(array $primary, array $secondary, $field) {
	$fields1 = zbx_objectValues($primary, $field);
	$fields2 = zbx_objectValues($secondary, $field);

	$first = array_diff($fields1, $fields2);
	$first = zbx_toHash($first);

	$second = array_diff($fields2, $fields1);
	$second = zbx_toHash($second);

	$result = [
		'first' => [],
		'second' => [],
		'both' => []
	];

	foreach ($primary as $array) {
		if (!isset($array[$field])) {
			$result['first'][] = $array;
		}
		elseif (isset($first[$array[$field]])) {
			$result['first'][] = $array;
		}
		else {
			$result['both'][$array[$field]] = $array;
		}
	}

	foreach ($secondary as $array) {
		if (!isset($array[$field])) {
			$result['second'][] = $array;
		}
		elseif (isset($second[$array[$field]])) {
			$result['second'][] = $array;
		}
	}

	return $result;
}

function zbx_array_push(&$array, $add) {
	foreach ($array as $key => $value) {
		foreach ($add as $newKey => $newValue) {
			$array[$key][$newKey] = $newValue;
		}
	}
}

/**
 * Find if array has any duplicate values and return an array with info about them.
 * In case of no duplicates, empty array is returned.
 * Example of usage:
 *     $result = zbx_arrayFindDuplicates(
 *         array('a', 'b', 'c', 'c', 'd', 'd', 'd', 'e')
 *     );
 *     array(
 *         'd' => 3,
 *         'c' => 2,
 *     )
 *
 * @param array $array
 *
 * @return array
 */
function zbx_arrayFindDuplicates(array $array) {
	$countValues = array_count_values($array); // counting occurrences of every value in array
	foreach ($countValues as $value => $count) {
		if ($count <= 1) {
			unset($countValues[$value]);
		}
	}
	arsort($countValues); // sorting, so that the most duplicates would be at the top

	return $countValues;
}

/************* STRING *************/
function zbx_nl2br($str) {
	$str_res = [];
	foreach (explode("\n", $str) as $str_line) {
		array_push($str_res, $str_line, BR());
	}
	array_pop($str_res);

	return $str_res;
}

function zbx_formatDomId($value) {
	return str_replace(['[', ']'], ['_', ''], $value);
}

/**
 * Sort an array of objects so that the objects whose $column value matches $pattern are at the top.
 * Return the first $limit objects.
 *
 * @param array 	$table		array of objects to sort
 * @param string 	$column		name of the $column to search
 * @param string 	$pattern	string to match the value of $column against
 * @param int		$limit		number of objects to return
 *
 * @return array
 */
function selectByPattern(array $table, $column, $pattern, $limit) {
	$chunk_size = $limit;

	$rsTable = [];
	foreach ($table as $num => $row) {
		if (mb_strtolower($row[$column]) === mb_strtolower($pattern)) {
			$rsTable = [$num => $row] + $rsTable;
		}
		elseif ($limit > 0) {
			$rsTable[$num] = $row;
		}
		else {
			continue;
		}
		$limit--;
	}

	if (!empty($rsTable)) {
		$rsTable = array_chunk($rsTable, $chunk_size, true);
		$rsTable = $rsTable[0];
	}

	return $rsTable;
}

/************* SORT *************/
function natksort(&$array) {
	$keys = array_keys($array);
	natcasesort($keys);

	$new_array = [];

	foreach ($keys as $k) {
		$new_array[$k] = $array[$k];
	}

	$array = $new_array;

	return true;
}

// recursively sort an array by key
function zbx_rksort(&$array, $flags = null) {
	if (is_array($array)) {
		foreach ($array as $id => $data) {
			zbx_rksort($array[$id]);
		}
		ksort($array, $flags);
	}

	return $array;
}

/**
 * Sorts the data using a natural sort algorithm.
 *
 * Not suitable for sorting macros, use order_macros() instead.
 *
 * @param $data
 * @param null $sortfield
 * @param string $sortorder
 *
 * @return bool
 *
 * @see order_macros()
 */
function order_result(&$data, $sortfield = null, $sortorder = ZBX_SORT_UP) {
	if (empty($data)) {
		return false;
	}

	if (is_null($sortfield)) {
		natcasesort($data);
		if ($sortorder != ZBX_SORT_UP) {
			$data = array_reverse($data, true);
		}
		return true;
	}

	$sort = [];
	foreach ($data as $key => $arr) {
		if (!isset($arr[$sortfield])) {
			return false;
		}
		$sort[$key] = $arr[$sortfield];
	}
	natcasesort($sort);

	if ($sortorder != ZBX_SORT_UP) {
		$sort = array_reverse($sort, true);
	}

	$tmp = $data;
	$data = [];
	foreach ($sort as $key => $val) {
		$data[$key] = $tmp[$key];
	}

	return true;
}

/**
 * Sorts the macros in the given order. Supports user and LLD macros.
 *
 * order_result() is not suitable for sorting macros, because it treats the "}" as a symbol with a lower priority
 * then any alphanumeric character, and the result will be invalid.
 *
 * E.g: order_result() will sort array('{$DD}', '{$D}', '{$D1}') as
 * array('{$D1}', '{$DD}', '{$D}') while the correct result is array('{$D}', '{$D1}', '{$DD}').
 *
 * @param array $macros
 * @param string $sortfield
 * @param string $order
 *
 * @return array
 */
function order_macros(array $macros, $sortfield, $order = ZBX_SORT_UP) {
	$temp = [];
	foreach ($macros as $key => $macro) {
		$temp[$key] = substr($macro[$sortfield], 2, strlen($macro[$sortfield]) - 3);
	}
	order_result($temp, null, $order);

	$rs = [];
	foreach ($temp as $key => $macroLabel) {
		$rs[$key] = $macros[$key];
	}

	return $rs;
}

// preserve keys
function zbx_array_merge() {
	$args = func_get_args();
	$result = [];
	foreach ($args as &$array) {
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			$result[$key] = $value;
		}
	}
	unset($array);

	return $result;
}

function uint_in_array($needle, $haystack) {
	foreach ($haystack as $value) {
		if (bccomp($needle, $value) == 0) {
			return true;
		}
	}

	return false;
}

function str_in_array($needle, $haystack, $strict = false) {
	if (is_array($needle)) {
		return in_array($needle, $haystack, $strict);
	}
	elseif ($strict) {
		foreach ($haystack as $value) {
			if ($needle === $value) {
				return true;
			}
		}
	}
	else {
		foreach ($haystack as $value) {
			if (strcmp($needle, $value) == 0) {
				return true;
			}
		}
	}

	return false;
}

function zbx_value2array(&$values) {
	if (!is_array($values) && !is_null($values)) {
		$tmp = [];
		if (is_object($values)) {
			$tmp[] = $values;
		}
		else {
			$tmp[$values] = $values;
		}
		$values = $tmp;
	}
}

// creates chain of relation parent -> child, for all chain levels
function createParentToChildRelation(&$chain, $link, $parentField, $childField) {
	if (!isset($chain[$link[$parentField]])) {
		$chain[$link[$parentField]] = [];
	}

	$chain[$link[$parentField]][$link[$childField]] = $link[$childField];
	if (isset($chain[$link[$childField]])) {
		$chain[$link[$parentField]] = zbx_array_merge($chain[$link[$parentField]], $chain[$link[$childField]]);
	}
}

// object or array of objects to hash
function zbx_toHash($value, $field = null) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	if (!is_array($value)) {
		$result = [$value => $value];
	}
	elseif (isset($value[$field])) {
		$result[$value[$field]] = $value;
	}
	else {
		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[$val] = $val;
			}
			elseif (isset($val[$field])) {
				$result[$val[$field]] = $val;
			}
		}
	}

	return $result;
}

/**
 * Transforms a single or an array of values to an array of objects, where the values are stored under the $field
 * key.
 *
 * E.g:
 * zbx_toObject(array(1, 2), 'hostid')  // returns array(array('hostid' => 1), array('hostid' => 2))
 * zbx_toObject(3, 'hostid')            // returns array(array('hostid' => 3))
 *
 * @param $value
 * @param $field
 *
 * @return array
 */
function zbx_toObject($value, $field) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	// Value or Array to Object or Array of objects
	if (!is_array($value)) {
		$result = [[$field => $value]];
	}
	elseif (!isset($value[$field])) {
		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[] = [$field => $val];
			}
		}
	}

	return $result;
}

/**
 * Converts the given value to a numeric array:
 * - a scalar value will be converted to an array and added as the only element;
 * - an array with first element key containing only numeric characters will be converted to plain zero-based numeric array.
 * This is used for reseting nonsequential numeric arrays;
 * - an associative array will be returned in an array as the only element, except if first element key contains only numeric characters.
 *
 * @param mixed $value
 *
 * @return array
 */
function zbx_toArray($value) {
	if ($value === null) {
		return $value;
	}

	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if (zbx_ctype_digit(key($value))) {
			$result = array_values($value);
		}
		elseif (!empty($value)) {
			$result = [$value];
		}
		else {
			$result = [];
		}
	}
	else {
		$result = [$value];
	}

	return $result;
}

// value OR object OR array of objects TO an array
function zbx_objectValues($value, $field) {
	if (is_null($value)) {
		return $value;
	}

	if (!is_array($value)) {
		$result = [$value];
	}
	elseif (isset($value[$field])) {
		$result = [$value[$field]];
	}
	else {
		$result = [];

		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[] = $val;
			}
			elseif (isset($val[$field])) {
				$result[] = $val[$field];
			}
		}
	}

	return $result;
}

function zbx_cleanHashes(&$value) {
	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);
		if (zbx_ctype_digit(key($value))) {
			$value = array_values($value);
		}
	}

	return $value;
}

function zbx_toCSV($values) {
	$csv = '';
	$glue = '","';
	foreach ($values as $row) {
		if (!is_array($row)) {
			$row = [$row];
		}
		foreach ($row as $num => $value) {
			if (is_null($value)) {
				unset($row[$num]);
			}
			else {
				$row[$num] = str_replace('"', '""', $value);
			}
		}
		$csv .= '"'.implode($glue, $row).'"'."\n";
	}

	return $csv;
}

function zbx_array_mintersect($keys, $array) {
	$result = [];

	foreach ($keys as $field) {
		if (is_array($field)) {
			foreach ($field as $sub_field) {
				if (isset($array[$sub_field])) {
					$result[$sub_field] = $array[$sub_field];
					break;
				}
			}
		}
		elseif (isset($array[$field])) {
			$result[$field] = $array[$field];
		}
	}

	return $result;
}

function zbx_str2links($text) {
	$result = [];

	foreach (explode("\n", $text) as $line) {
		$line = rtrim($line, "\r ");

		preg_match_all('#https?://[^\n\t\r ]+#u', $line, $matches, PREG_OFFSET_CAPTURE);

		$start = 0;
		foreach ($matches[0] as $match) {
			$result[] = mb_substr($line, $start, $match[1] - $start);
			$result[] = new CLink($match[0], $match[0]);
			$start = $match[1] + mb_strlen($match[0]);
		}
		$result[] = mb_substr($line, $start);
		$result[] = BR();
	}
	array_pop($result);

	return $result;
}

function zbx_subarray_push(&$mainArray, $sIndex, $element = null, $key = null) {
	if (!isset($mainArray[$sIndex])) {
		$mainArray[$sIndex] = [];
	}
	if ($key) {
		$mainArray[$sIndex][$key] = is_null($element) ? $sIndex : $element;
	}
	else {
		$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
	}
}

/*************** PAGE SORTING ******************/

/**
 * Returns header with sorting options.
 *
 * @param string obj			Header item.
 * @param string $tabfield		Table field.
 * @param string $sortField		Sorting field.
 * @param string $sortOrder		Sorting order.
 * @param string $link			Sorting link.
 *
 * @return CColHeader
 */
function make_sorting_header($obj, $tabfield, $sortField, $sortOrder, $link = null) {
	$sortorder = ($sortField == $tabfield && $sortOrder == ZBX_SORT_UP) ? ZBX_SORT_DOWN : ZBX_SORT_UP;

	$link = CUrlFactory::getContextUrl($link);

	$link->setArgument('sort', $tabfield);
	$link->setArgument('sortorder', $sortorder);

	zbx_value2array($obj);

	$arrow = null;
	if ($tabfield == $sortField) {
		if ($sortorder == ZBX_SORT_UP) {
			$arrow = (new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN);
		}
		else {
			$arrow = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);
		}
	}

	return new CColHeader(new CLink([$obj, $arrow], $link->getUrl()));
}

/**
 * Returns the list page number for the current page.
 *
 * The functions first looks for a page number in the HTTP request. If no number is given, falls back to the profile.
 * Defaults to 1.
 *
 * @return int
 */
function getPageNumber() {
	global $page;

	$pageNumber = getRequest('page');
	if (!$pageNumber) {
		$lastPage = CProfile::get('web.paging.lastpage');
		// For MVC pages $page is not set so we use action instead
		if (isset($page['file']) && $lastPage == $page['file']) {
			$pageNumber = CProfile::get('web.paging.page', 1);
		}
		elseif (isset($_REQUEST['action']) && $lastPage == $_REQUEST['action']) {
			$pageNumber = CProfile::get('web.paging.page', 1);
		}
		else {
			$pageNumber = 1;
		}
	}

	return $pageNumber;
}

/**
 * Returns paging line and recursively slice $items of current page.
 *
 * @param array  $items				list of elements
 * @param string $sortorder			the order in which items are sorted ASC or DESC
 * @param CUrl $url					URL object containing arguments and query
 *
 * @return CDiv
 */
function getPagingLine(&$items, $sortorder, CUrl $url) {
	global $page;

	$rowsPerPage = (int) CWebUser::$data['rows_per_page'];
	$config = select_config();

	$itemsCount = count($items);
	$limit_exceeded = ($config['search_limit'] < $itemsCount);
	$offset = 0;

	if ($limit_exceeded) {
		if ($sortorder == ZBX_SORT_DOWN) {
			$offset = $itemsCount - $config['search_limit'];
		}
		$itemsCount = $config['search_limit'];
	}

	$pagesCount = ($itemsCount > 0) ? ceil($itemsCount / $rowsPerPage) : 1;
	$currentPage = getPageNumber();

	if ($currentPage < 1) {
		$currentPage = 1;
	}
	elseif ($currentPage > $pagesCount) {
		$currentPage = $pagesCount;
	}

	$tags = [];

	if ($pagesCount > 1) {
		// For MVC pages $page is not set
		if (isset($page['file'])) {
			CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
			CProfile::update('web.paging.page', $currentPage, PROFILE_TYPE_INT);
		}
		elseif (isset($_REQUEST['action'])) {
			CProfile::update('web.paging.lastpage', $_REQUEST['action'], PROFILE_TYPE_STR);
			CProfile::update('web.paging.page', $currentPage, PROFILE_TYPE_INT);
		}

		// viewed pages (better to use odd)
		$pagingNavRange = 11;

		$endPage = $currentPage + floor($pagingNavRange / 2);
		if ($endPage < $pagingNavRange) {
			$endPage = $pagingNavRange;
		}
		if ($endPage > $pagesCount) {
			$endPage = $pagesCount;
		}

		$startPage = ($endPage > $pagingNavRange) ? $endPage - $pagingNavRange + 1 : 1;

		if ($startPage > 1) {
			$url->setArgument('page', 1);
			$tags[] = new CLink(_x('First', 'page navigation'), $url->getUrl());
		}

		if ($currentPage > 1) {
			$url->setArgument('page', $currentPage - 1);
			$tags[] = new CLink(
				(new CSpan())->addClass(ZBX_STYLE_ARROW_LEFT), $url->getUrl()
			);
		}

		for ($p = $startPage; $p <= $endPage; $p++) {
			$url->setArgument('page', $p);
			$link = new CLink($p, $url->getUrl());
			if ($p == $currentPage) {
				$link->addClass(ZBX_STYLE_PAGING_SELECTED);
			}

			$tags[] = $link;
		}

		if ($currentPage < $pagesCount) {
			$url->setArgument('page', $currentPage + 1);
			$tags[] = new CLink((new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT), $url->getUrl());
		}

		if ($p < $pagesCount) {
			$url->setArgument('page', $pagesCount);
			$tags[] = new CLink(_x('Last', 'page navigation'), $url->getUrl());
		}
	}

	$total = $limit_exceeded ? $itemsCount.'+' : $itemsCount;
	$start = ($currentPage - 1) * $rowsPerPage;
	$end = $start + $rowsPerPage;

	if ($end > $itemsCount) {
		$end = $itemsCount;
	}

	if ($pagesCount == 1) {
		$table_stats = _s('Displaying %1$s of %2$s found', $itemsCount, $total);
	}
	else {
		$table_stats = _s('Displaying %1$s to %2$s of %3$s found', $start + 1, $end, $total);
	}

	// Trim array with elements to contain elements for current page.
	$items = array_slice($items, $start + $offset, $end - $start, true);

	return (new CDiv())
		->addClass(ZBX_STYLE_TABLE_PAGING)
		->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
				->addItem($tags)
				->addItem(
					(new CDiv())
						->addClass(ZBX_STYLE_TABLE_STATS)
						->addItem($table_stats)
				)
		);
}

/************* MATH *************/
function bcfloor($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number) {
			$number = $tmp;
		}
		elseif ($number[0] != '-') {
			$number = bcadd($number, 0, 0);
		}
		else {
			$number = bcsub($number, 1, 0);
		}
	}

	return $number == '-0' ? '0' : $number;
}

function bcceil($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number) {
			$number = $tmp;
		}
		elseif ($number[0] != '-') {
			$number = bcadd($number, 1, 0);
		}
		else {
			$number = bcsub($number, 0, 0);
		}
	}

	return $number == '-0' ? '0' : $number;
}

/**
 * Converts number to letter representation.
 * From A to Z, then from AA to ZZ etc.
 * Example: 0 => A, 25 => Z, 26 => AA, 27 => AB, 52 => BA, ...
 *
 * Keep in sync with JS num2letter().
 *
 * @param int $number
 *
 * @return string
 */
function num2letter($number) {
	$start = ord('A');
	$base = 26;
	$str = '';
	$level = 0;

	do {
		if ($level++ > 0) {
			$number--;
		}
		$remainder = $number % $base;
		$number = ($number - $remainder) / $base;
		$str = chr($start + $remainder).$str;
	} while (0 != $number);

	return $str;
}

/**
 * Renders an "access denied" message and stops the execution of the script.
 *
 * The $mode parameters controls the layout of the message for logged in users:
 * - ACCESS_DENY_OBJECT     - render the message when denying access to a specific object
 * - ACCESS_DENY_PAGE       - render a complete access denied page
 *
 * If visitor is without any access permission then layout of the message is same as in ACCESS_DENY_PAGE mode.
 *
 * @param int $mode
 */
function access_deny($mode = ACCESS_DENY_OBJECT) {
	// deny access to an object
	if ($mode == ACCESS_DENY_OBJECT && CWebUser::isLoggedIn()) {
		require_once dirname(__FILE__).'/page_header.php';
		show_error_message(_('No permissions to referred object or it does not exist!'));
		require_once dirname(__FILE__).'/page_footer.php';
	}
	// deny access to a page
	else {
		// url to redirect the user to after he logs in
		$url = (new CUrl(!empty($_REQUEST['request']) ? $_REQUEST['request'] : ''))->removeArgument('sid');
		$url = urlencode($url->toString());

		// if the user is logged in - render the access denied message
		if (CWebUser::isLoggedIn()) {
			$data = [
				'header' => _('Access denied'),
				'messages' => [
					_s('You are logged in as "%1$s".', CWebUser::$data['alias']).' '._('You have no permissions to access this page.'),
					_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => []
			];

			// display the login button only for guest users
			if (CWebUser::isGuest()) {
				$data['buttons'][] = (new CButton('login', _('Login')))
					->onClick('javascript: document.location = "index.php?request='.$url.'";');
			}
			$data['buttons'][] = (new CButton('back', _('Go to dashboard')))
				->onClick('javascript: document.location = "zabbix.php?action=dashboard.view"');
		}
		// if the user is not logged in - offer to login
		else {
			$data = [
				'header' => _('You are not logged in'),
				'messages' => [
					_('You must login to view this page.'),
					_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => [
					(new CButton('login', _('Login')))->onClick('javascript: document.location = "index.php?request='.$url.'";')
				]
			];
		}

		$data['theme'] = getUserTheme(CWebUser::$data);

		(new CView('general.warning', $data))->render();
		exit;
	}
}

function detect_page_type($default = PAGE_TYPE_HTML) {
	if (isset($_REQUEST['output'])) {
		switch (strtolower($_REQUEST['output'])) {
			case 'text':
				return PAGE_TYPE_TEXT;
			case 'ajax':
				return PAGE_TYPE_JS;
			case 'json':
				return PAGE_TYPE_JSON;
			case 'json-rpc':
				return PAGE_TYPE_JSON_RPC;
			case 'html':
				return PAGE_TYPE_HTML_BLOCK;
			case 'img':
				return PAGE_TYPE_IMAGE;
			case 'css':
				return PAGE_TYPE_CSS;
		}
	}

	return $default;
}

function makeMessageBox($good, array $messages, $title = null, $show_close_box = true, $show_details = false)
{
	$class = $good ? ZBX_STYLE_MSG_GOOD : ZBX_STYLE_MSG_BAD;
	$msg_details = null;
	$link_details = null;

	if ($messages) {
		if ($title !== null) {
			$link_details = (new CLinkAction())
				->addItem(_('Details'))
				->addItem(' ') // space
				->addItem((new CSpan())
					->setId('details-arrow')
					->addClass($show_details ? ZBX_STYLE_ARROW_UP : ZBX_STYLE_ARROW_DOWN)
				)
				->onClick('javascript: '.
					'showHide(jQuery(this).siblings(\'.'.ZBX_STYLE_MSG_DETAILS.'\')'.
						'.find(\'.'.ZBX_STYLE_MSG_DETAILS_BORDER.'\'));'.
					'jQuery("#details-arrow", $(this)).toggleClass("'.ZBX_STYLE_ARROW_UP.' '.ZBX_STYLE_ARROW_DOWN.'");'
				);
		}

		$list = new CList();
		if ($title !== null) {
			$list->addClass(ZBX_STYLE_MSG_DETAILS_BORDER);

			if (!$show_details) {
				$list->setAttribute('style', 'display: none;');
			}
		}
		foreach ($messages as $message) {
			foreach (explode("\n", $message['message']) as $message_part) {
				$list->addItem($message_part);
			}
		}
		$msg_details = (new CDiv())->addClass(ZBX_STYLE_MSG_DETAILS)->addItem($list);
	}

	// Details link should be in front of title.
	$msg_box = (new CTag('output', true, [$link_details, $title, $msg_details]))->addClass($class);

	if ($show_close_box) {
		$msg_box->addItem((new CSimpleButton())
			->addClass(ZBX_STYLE_OVERLAY_CLOSE_BTN)
			->onClick('jQuery(this).closest(\'.'.$class.'\').remove();')
			->setTitle(_('Close')));
	}

	return $msg_box;
}

/**
 * Filters messages that can be displayed to user based on defines (see ZBX_SHOW_TECHNICAL_ERRORS) and user settings.
 *
 * @param array $messages	List of messages to filter.
 *
 * @return array
 */
function filter_messages(array $messages = []) {
	if (!ZBX_SHOW_TECHNICAL_ERRORS && CWebUser::getType() != USER_TYPE_SUPER_ADMIN && !CWebUser::getDebugMode()) {
		$filtered_messages = [];
		$generic_exists = false;

		foreach ($messages as $message) {
			if (array_key_exists('src', $message) && ($message['src'] === 'sql' || $message['src'] === 'php')) {
				if (!$generic_exists) {
					$message['message'] = _('System error occurred. Please contact Zabbix administrator.');
					$filtered_messages[] = $message;
					$generic_exists = true;
				}
			}
			else {
				$filtered_messages[] = $message;
			}
		}
		$messages = $filtered_messages;
	}

	return $messages;
}

/**
 * Returns the message box when messages are present; null otherwise
 *
 * @param  boolean	$good			Parameter passed to makeMessageBox to specify message box style.
 * @param  string	$title			Message box title.
 * @global array	$ZBX_MESSAGES
 *
 * @return CDiv|null
 */
function getMessages($good = false, $title = null)
{
	global $ZBX_MESSAGES;

	$messages = (isset($ZBX_MESSAGES) && $ZBX_MESSAGES) ? filter_messages($ZBX_MESSAGES) : [];

	$message_box = ($title || $messages)
		? makeMessageBox($good, $messages, $title)
		: null;

	$ZBX_MESSAGES = [];

	return $message_box;
}

function show_messages($good = false, $okmsg = null, $errmsg = null) {
	global $page, $ZBX_MESSAGES;

	if (!defined('PAGE_HEADER_LOADED')) {
//		return null;
	}
	if (defined('ZBX_API_REQUEST')) {
		return null;
	}
	if (!isset($page['type'])) {
		$page['type'] = PAGE_TYPE_HTML;
	}

	$imageMessages = [];

	$title = $good ? $okmsg : $errmsg;
	$messages = isset($ZBX_MESSAGES) ? filter_messages($ZBX_MESSAGES) : [];
	$ZBX_MESSAGES = [];

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			if ($title !== null) {
				$imageMessages[] = [
					'text' => $title,
					'color' => (!$good) ? ['R' => 255, 'G' => 0, 'B' => 0] : ['R' => 34, 'G' => 51, 'B' => 68]
				];
			}

			foreach ($messages as $message) {
				$imageMessages[] = [
					'text' => $message['message'],
					'color' => $message['type'] == 'error'
						? ['R' => 255, 'G' => 55, 'B' => 55]
						: ['R' => 155, 'G' => 155, 'B' => 55]
				];
			}
			break;
		case PAGE_TYPE_XML:
			if ($title !== null) {
				echo htmlspecialchars($title)."\n";
			}

			foreach ($messages as $message) {
				echo '['.$message['type'].'] '.$message['message']."\n";
			}
			break;
		case PAGE_TYPE_HTML:
		default:
			if ($title || $messages) {
				makeMessageBox($good, $messages, $title, true, !$good)->show();
			}
			break;
	}

	// draw an image with the messages
	if ($imageMessages) {
		$imageFontSize = 8;

		// calculate the size of the text
		$imageWidth = 0;
		$imageHeight = 0;
		foreach ($imageMessages as &$msg) {
			$size = imageTextSize($imageFontSize, 0, $msg['text']);
			$msg['height'] = $size['height'] - $size['baseline'];

			// calculate the total size of the image
			$imageWidth = max($imageWidth, $size['width']);
			$imageHeight += $size['height'] + 1;
		}
		unset($msg);

		// additional padding
		$imageWidth += 2;
		$imageHeight += 2;

		// create the image
		$canvas = imagecreate($imageWidth, $imageHeight);
		imagefilledrectangle($canvas, 0, 0, $imageWidth, $imageHeight, imagecolorallocate($canvas, 255, 255, 255));

		// draw each message
		$y = 1;
		foreach ($imageMessages as $msg) {
			$y += $msg['height'];
			imageText($canvas, $imageFontSize, 0, 1, $y,
				imagecolorallocate($canvas, $msg['color']['R'], $msg['color']['G'], $msg['color']['B']),
				$msg['text']
			);
		}
		imageOut($canvas);
		imagedestroy($canvas);
	}
}

function show_message($msg) {
	show_messages(true, $msg, '');
}

function show_error_message($msg) {
	show_messages(false, '', $msg);
}

function info($msgs) {
	global $ZBX_MESSAGES;

	if (!isset($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = [];
	}

	zbx_value2array($msgs);

	foreach ($msgs as $msg) {
		$ZBX_MESSAGES[] = ['type' => 'info', 'message' => $msg];
	}
}

/*
 * Add an error to global message array.
 *
 * @param string | array $msg	Error message text.
 * @param string		 $src	The source of error message.
 */
function error($msgs, $src = '') {
	global $ZBX_MESSAGES;

	if (!isset($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = [];
	}

	$msgs = zbx_toArray($msgs);

	foreach ($msgs as $msg) {
		$ZBX_MESSAGES[] = [
			'type' => 'error',
			'message' => $msg,
			'src' => $src
		];
	}
}

/**
 * Add multiple errors under single header.
 *
 * @param array  $data
 * @param string $data['header']  common header for all error messages
 * @param array  $data['msgs']    array of error messages
 */
function error_group($data) {
	foreach (zbx_toArray($data['msgs']) as $msg) {
		error($data['header'] . ' ' . $msg);
	}
}

function clear_messages($count = null) {
	global $ZBX_MESSAGES;

	if ($count != null) {
		$result = [];

		while ($count-- > 0) {
			array_unshift($result, array_pop($ZBX_MESSAGES));
		}
	}
	else {
		$result = $ZBX_MESSAGES;
		$ZBX_MESSAGES = [];
	}

	return $result ? filter_messages($result) : $result;
}

function fatal_error($msg) {
	require_once dirname(__FILE__).'/page_header.php';
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function parse_period($str) {
	$out = null;
	$time_periods_parser = new CTimePeriodsParser();

	if ($time_periods_parser->parse($str) != CParser::PARSE_SUCCESS) {
		return null;
	}

	foreach ($time_periods_parser->getPeriods() as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $matches)) {
			return null;
		}

		for ($i = $matches[1]; $i <= $matches[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = [];
			}
			array_push($out[$i], [
				'start_h' => $matches[3],
				'start_m' => $matches[4],
				'end_h' => $matches[5],
				'end_m' => $matches[6]
			]);
		}
	}

	return $out;
}

function get_status() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$status = [
		'is_running' => false,
		'has_status' => false
	];

	$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
	$status['is_running'] = $server->isRunning(get_cookie('zbx_sessionid'));

	if ($status['is_running'] === false) {
		return $status;
	}

	$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, 15, ZBX_SOCKET_BYTES_LIMIT);
	$server_status = $server->getStatus(get_cookie('zbx_sessionid'));
	$status['has_status'] = (bool) $server_status;

	if ($server_status === false) {
		error($server->getError());
		return $status;
	}

	$status += [
		'triggers_count_disabled' => 0,
		'triggers_count_off' => 0,
		'triggers_count_on' => 0,
		'items_count_monitored' => 0,
		'items_count_disabled' => 0,
		'items_count_not_supported' => 0,
		'hosts_count_monitored' => 0,
		'hosts_count_not_monitored' => 0,
		'hosts_count_template' => 0,
		'users_count' => 0,
		'users_online' => 0
	];

	// hosts
	foreach ($server_status['template stats'] as $stats) {
		$status['hosts_count_template'] += $stats['count'];
	}

	foreach ($server_status['host stats'] as $stats) {
		if ($stats['attributes']['proxyid'] == 0) {
			switch ($stats['attributes']['status']) {
				case HOST_STATUS_MONITORED:
					$status['hosts_count_monitored'] += $stats['count'];
					break;

				case HOST_STATUS_NOT_MONITORED:
					$status['hosts_count_not_monitored'] += $stats['count'];
					break;
			}
		}
	}
	$status['hosts_count'] = $status['hosts_count_monitored'] + $status['hosts_count_not_monitored']
			+ $status['hosts_count_template'];

	// items
	foreach ($server_status['item stats'] as $stats) {
		if ($stats['attributes']['proxyid'] == 0) {
			switch ($stats['attributes']['status']) {
				case ITEM_STATUS_ACTIVE:
					if (array_key_exists('state', $stats['attributes'])) {
						switch ($stats['attributes']['state']) {
							case ITEM_STATE_NORMAL:
								$status['items_count_monitored'] += $stats['count'];
								break;

							case ITEM_STATE_NOTSUPPORTED:
								$status['items_count_not_supported'] += $stats['count'];
								break;
						}
					}
					break;

				case ITEM_STATUS_DISABLED:
					$status['items_count_disabled'] += $stats['count'];
					break;
			}
		}
	}
	$status['items_count'] = $status['items_count_monitored'] + $status['items_count_disabled']
			+ $status['items_count_not_supported'];

	// triggers
	foreach ($server_status['trigger stats'] as $stats) {
		switch ($stats['attributes']['status']) {
			case TRIGGER_STATUS_ENABLED:
				if (array_key_exists('value', $stats['attributes'])) {
					switch ($stats['attributes']['value']) {
						case TRIGGER_VALUE_FALSE:
							$status['triggers_count_off'] += $stats['count'];
							break;

						case TRIGGER_VALUE_TRUE:
							$status['triggers_count_on'] += $stats['count'];
							break;
					}
				}
				break;

			case TRIGGER_STATUS_DISABLED:
				$status['triggers_count_disabled'] += $stats['count'];
				break;
		}
	}
	$status['triggers_count_enabled'] = $status['triggers_count_off'] + $status['triggers_count_on'];
	$status['triggers_count'] = $status['triggers_count_enabled'] + $status['triggers_count_disabled'];

	// users
	foreach ($server_status['user stats'] as $stats) {
		switch ($stats['attributes']['status']) {
			case ZBX_SESSION_ACTIVE:
				$status['users_online'] += $stats['count'];
				break;

			case ZBX_SESSION_PASSIVE:
				$status['users_count'] += $stats['count'];
				break;
		}
	}
	$status['users_count'] += $status['users_online'];

	// performance
	if (array_key_exists('required performance', $server_status)) {
		$status['vps_total'] = 0;

		foreach ($server_status['required performance'] as $stats) {
			if ($stats['attributes']['proxyid'] == 0) {
				$status['vps_total'] += $stats['count'];
			}
		}
	}

	return $status;
}

function set_image_header() {
	global $IMAGE_FORMAT_DEFAULT;

	switch ($IMAGE_FORMAT_DEFAULT) {
		case IMAGE_FORMAT_JPEG:
			header('Content-type: image/jpeg');
			break;

		case IMAGE_FORMAT_TEXT:
			header('Content-type: text/html');
			break;

		default:
			header('Content-type: image/png');
	}

	header('Expires: Mon, 17 Aug 1998 12:51:50 GMT');
}

function imageOut(&$image, $format = null) {
	global $page, $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	ob_start();

	if (IMAGE_FORMAT_JPEG == $format) {
		imagejpeg($image);
	}
	else {
		imagepng($image);
	}

	$imageSource = ob_get_contents();
	ob_end_clean();

	if ($page['type'] != PAGE_TYPE_IMAGE) {
		$imageId = md5(strlen($imageSource));
		CSession::setValue('image_id', [$imageId => $imageSource]);
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			echo $imageSource;
			break;
		case PAGE_TYPE_JSON:
			$json = new CJson();
			echo $json->encode(['result' => $imageId]);
			break;
		case PAGE_TYPE_TEXT:
		default:
			echo $imageId;
	}
}

/**
 * Check if we have error messages to display.
 *
 * @global array $ZBX_MESSAGES
 *
 * @return bool
 */
function hasErrorMesssages() {
	global $ZBX_MESSAGES;

	if (isset($ZBX_MESSAGES)) {
		foreach ($ZBX_MESSAGES as $message) {
			if ($message['type'] === 'error') {
				return true;
			}
		}
	}

	return false;
}

/**
 * Clears table rows selection's cookies.
 *
 * @param string $cookieId		parent ID, is used as cookie suffix
 */
function uncheckTableRows($cookieId = null) {
	insert_js('cookie.eraseArray("cb_'.basename($_SERVER['SCRIPT_NAME'], '.php').
		($cookieId === null ? '' : '_'.$cookieId).'")'
	);
}

/**
 * Trim each element of the script path. For example, " a / b / c d " => "a/b/c d"
 *
 * @param string $name
 *
 * @return string
 */
function trimPath($name) {
	$path = splitPath($name);
	$path = array_map('trim', $path);
	$path = str_replace(['\\', '/'], ['\\\\', '\\/'], $path);
	return implode('/', $path);
}

/**
 * Splitting string using slashes with escape backslash support and non-pair backslash cleanup.
 *
 * @param string $path
 *
 * @return array
 */
function splitPath($path) {
	$path_items = [];
	$path_item = '';

	for ($i = 0; isset($path[$i]); $i++) {
		switch ($path[$i]) {
			case '/':
				$path_items[] = $path_item;
				$path_item = '';
				break;

			case '\\':
				if (isset($path[++$i])) {
					$path_item .= $path[$i];
				}
				break;

			default:
				$path_item .= $path[$i];
		}
	}

	$path_items[] = $path_item;

	return $path_items;
}

/**
 * Allocate color for an image.
 *
 * @param resource 	$image
 * @param string	$color		a hexadecimal color identifier like "1F2C33"
 * @param int 		$alpha
 */
function get_color($image, $color, $alpha = 0) {
	$red = hexdec('0x'.substr($color, 0, 2));
	$green = hexdec('0x'.substr($color, 2, 2));
	$blue = hexdec('0x'.substr($color, 4, 2));

	if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
			&& @imagecreatetruecolor(1, 1)) {
		return imagecolorexactalpha($image, $red, $green, $blue, $alpha);
	}

	return imagecolorallocate($image, $red, $green, $blue);
}

/**
 * Get graphic theme based on user configuration.
 *
 * @return array
 */
function getUserGraphTheme() {
	$themes = DB::find('graph_theme', [
		'theme' => getUserTheme(CWebUser::$data)
	]);

	if ($themes) {
		return $themes[0];
	}

	return [
		'theme' => 'blue-theme',
		'textcolor' => '1F2C33',
		'highlightcolor' => 'E33734',
		'backgroundcolor' => 'FFFFFF',
		'graphcolor' => 'FFFFFF',
		'gridcolor' => 'CCD5D9',
		'maingridcolor' => 'ACBBC2',
		'gridbordercolor' => 'ACBBC2',
		'nonworktimecolor' => 'EBEBEB',
		'leftpercentilecolor' => '429E47',
		'righttpercentilecolor' => 'E33734',
		'colorpalette' => '1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,5A2B57,'.
			'89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4'
	];
}

/**
 * Custom error handler for PHP errors.
 *
 * @param int     $errno Level of the error raised.
 * @param string  $errstr Error message.
 * @param string  $errfile Filename that the error was raised in.
 * @param int     $errline Line number the error was raised in.
 */
function zbx_err_handler($errno, $errstr, $errfile, $errline) {
	// Necessary to suppress errors when calling with error control operator like @function_name().
	if (error_reporting() === 0) {
		return true;
	}

	// Don't show the call to this handler function.
	error($errstr.' ['.CProfiler::getInstance()->formatCallStack().']', 'php');
}

/**
 * Creates an array with all possible variations of time units.
 * For example: '14d' => ['1209600', '1209600s', '20160m', '336h', '14d', '2w']
 *
 * @param string|array $values
 *
 * @return array
 */
function getTimeUnitFilters($values) {
	if (is_array($values)) {
		$res = [];

		foreach ($values as $value) {
			$res = array_merge($res, getTimeUnitFilters($value));
		}

		return array_unique($res, SORT_STRING);
	}

	$simple_interval_parser = new CSimpleIntervalParser();

	if ($simple_interval_parser->parse($values) != CParser::PARSE_SUCCESS) {
		return [$values];
	}

	$sec = timeUnitToSeconds($values);

	$res = [$sec, $sec.'s'];

	if (bcmod($sec, SEC_PER_MIN) == 0) {
		$res[] = bcdiv($sec, SEC_PER_MIN, 0).'m';
	}

	if (bcmod($sec, SEC_PER_HOUR) == 0) {
		$res[] = bcdiv($sec, SEC_PER_HOUR, 0).'h';
	}

	if (bcmod($sec, SEC_PER_DAY) == 0) {
		$res[] = bcdiv($sec, SEC_PER_DAY, 0).'d';
	}

	if (bcmod($sec, SEC_PER_WEEK) == 0) {
		$res[] = bcdiv($sec, SEC_PER_WEEK, 0).'w';
	}

	return $res;
}

/**
 * Creates SQL filter to search all possible variations of time units.
 *
 * @param string       $field_name
 * @param string|array $values
 *
 * @return string
 */
function makeUpdateIntervalFilter($field_name, $values) {
	$filters = [];

	foreach (getTimeUnitFilters($values) as $filter) {
		$filter = str_replace("!", "!!", $filter);
		$filter = str_replace("%", "!%", $filter);
		$filter = str_replace("_", "!_", $filter);

		$filters[] = $field_name.' LIKE '.zbx_dbstr($filter).' ESCAPE '.zbx_dbstr('!');
		$filters[] = $field_name.' LIKE '.zbx_dbstr($filter.';%').' ESCAPE '.zbx_dbstr('!');
	}

	$res = $filters ? implode(' OR ', $filters) : '';

	if (count($filters) > 1) {
		$res = '('.$res.')';
	}

	return $res;
}

/**
 * Calculate timeline data.
 *
 * @param array		$options
 * @param string	$options['profileIdx']      Profile idx identificator.
 * @param int		$options['profileIdx2']     Profile idx2 identificator.
 * @param boolean	$options['updateProfile']   Update profile or not.
 * @param int		$options['from']            Interval start date, can be in relative format.
 * @param string	$options['to']              Interval end date, can be in relative format.
 *
 * @return array
 */
function calculateTime(array $options = []) {
	$options = array_filter($options) + [
		'profileIdx' => null,
		'profileIdx2' => 0,
		'updateProfile' => false,
		'from' => null,
		'to' => null
	];
	$range_time_parser = new CRangeTimeParser();

	foreach (['from', 'to'] as $type) {
		if ($options[$type] === null) {
			// TODO Sasha: 'profileIdx' can be null?
			$options[$type] = CProfile::get($options['profileIdx'].'.'.$type,
				$type === 'from' ? ZBX_PERIOD_DEFAULT : 'now', $options['profileIdx2']
			);
		}

		$range_time_parser->parse($options[$type]);

		$ts[$type] = $range_time_parser->getDateTime($type === 'from')->getTimestamp();
	}

	$period = $ts['to'] - $ts['from'];

	if ($period < ZBX_MIN_PERIOD) {
		error(_n('Minimum time period to display is %1$s minute.', 'Minimum time period to display is %1$s minutes.',
			(int) ZBX_MIN_PERIOD / SEC_PER_MIN
		));
		$ts['from'] = $ts['to'] - ZBX_MIN_PERIOD;
		$options['from'] = (new DateTime())->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
	}
	elseif ($period > ZBX_MAX_PERIOD) {
		error(_n('Maximum time period to display is %1$s day.', 'Maximum time period to display is %1$s days.',
			(int) ZBX_MAX_PERIOD / SEC_PER_DAY
		));
		$ts['from'] = $ts['to'] - ZBX_MAX_PERIOD;
		$options['from'] = (new DateTime())->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
	}

	// TODO Sasha: 'profileIdx' can be null?
	if ($options['profileIdx'] !== null && $options['updateProfile']) {
		CProfile::update($options['profileIdx'].'.from', $options['from'], PROFILE_TYPE_STR, $options['profileIdx2']);
		CProfile::update($options['profileIdx'].'.to', $options['to'], PROFILE_TYPE_STR, $options['profileIdx2']);
	}

	$time = time();

	return [
		'profileIdx' => $options['profileIdx'],
		'profileIdx2' => $options['profileIdx2'],
		'updateProfile' => $options['updateProfile'],
		'refreshable' => ($ts['from'] <= $time && $time <= $ts['to']),
		'from_ts' => $ts['from'],
		'to_ts' => $ts['to'],
		'from' => $options['from'],
		'to' => $options['to']
	];
}

/**
 * Convert relative date range string to translated string. Function does not check is passed date range correct.
 *
 * @param string $from     Start date of date range.
 * @param string $to       End date of date range.
 *
 * @return string
 */
function relativeDateToText($from, $to) {
	$key = $from.':'.$to;
	$ranges = [
		'now-1d/d:now-1d/d' => _('Yesterday'),
		'now-2d/d:now-2d/d' => _('Day before yesterday'),
		'now-1w/d:now-1w/d' => _('This day last week'),
		'now-1w/w:now-1w/w' => _('Previous week'),
		'now-1M/M:now-1M/M' => _('Previous month'),
		'now-1y/y:now-1y/y' => _('Previous year'),
		'now/d:now/d' => _('Today'),
		'now/d:now' => _('Today so far'),
		'now/w:now/w' => _('This week'),
		'now/w:now' => _('This week so far'),
		'now/M:now/M' => _('This month'),
		'now/M:now' => _('This month so far'),
		'now/y:now/y' => _('This year'),
		'now/y:now' => _('This year so far')
	];

	if (array_key_exists($key, $ranges)) {
		return $ranges[$key];
	}

	if ($to === 'now') {
		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($from) == CParser::PARSE_SUCCESS) {
			$tokens = $relative_time_parser->getTokens();

			if (count($tokens) == 1 && $tokens[0]['type'] == CRelativeTimeParser::ZBX_TOKEN_OFFSET
					&& $tokens[0]['sign'] === '-') {
				$suffix = $tokens[0]['suffix'];
				$value = (int) $tokens[0]['value'];

				switch ($suffix) {
					case 's':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d second', 'Last %1$d seconds', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'm':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d minute', 'Last %1$d minutes', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'h':
						if ($value < 24 || $value % 24 != 0) {
							return _n('Last %1$d hour', 'Last %1$d hours', $value);
						}
						$value /= 24;
						// break; is not missing here.

					case 'd':
						return _n('Last %1$d day', 'Last %1$d days', $value);

					case 'M':
						return _n('Last %1$d month', 'Last %1$d months', $value);

					case 'y':
						return _n('Last %1$d year', 'Last %1$d years', $value);
				}
			}
		}
	}

	$range_time_parser = new CRangeTimeParser();

	$range_time_parser->parse($from);
	$result = $range_time_parser->getDateTime(true)->format(ZBX_DATE_TIME).' - ';

	$range_time_parser->parse($to);
	$result .= $range_time_parser->getDateTime(false)->format(ZBX_DATE_TIME);

	return $result;
}
