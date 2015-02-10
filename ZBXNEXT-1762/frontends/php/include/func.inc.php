<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	$curl = new CUrl($url);
	$curl->setArgument('sid', null);
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
	setcookie($name, $value, isset($time) ? $time : 0, null, null, HTTPS);
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
		$weekdaynames = array(
			0 => _('Sun'),
			1 => _('Mon'),
			2 => _('Tue'),
			3 => _('Wed'),
			4 => _('Thu'),
			5 => _('Fri'),
			6 => _('Sat')
		);
	}

	if (!is_array($weekdaynameslong)) {
		$weekdaynameslong = array(
			0 => _('Sunday'),
			1 => _('Monday'),
			2 => _('Tuesday'),
			3 => _('Wednesday'),
			4 => _('Thursday'),
			5 => _('Friday'),
			6 => _('Saturday')
		);
	}

	if (!is_array($months)) {
		$months = array(
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
		);
	}

	if (!is_array($monthslong)) {
		$monthslong = array(
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
		);
	}

	$rplcs = array(
		'l' => $weekdaynameslong[date('w', $value)],
		'F' => $monthslong[date('n', $value)],
		'D' => $weekdaynames[date('w', $value)],
		'M' => $months[date('n', $value)]
	);

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

// calculate and convert timestamp to string representation
function zbx_date2age($startDate, $endDate = 0, $utime = false) {
	if (!$utime) {
		$startDate = date('U', $startDate);
		$endDate = $endDate ? date('U', $endDate) : time();
	}

	return convertUnitsS(abs($endDate - $startDate));
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
function rgb2hex($color) {
	$HEX = array(
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	);
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
		list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
	}
	elseif (strlen($color) == 3) {
		list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
	}
	else {
		return false;
	}

	return array(hexdec($r), hexdec($g), hexdec($b));
}

function zbx_num2bitstr($num, $rev = false) {
	if (!is_numeric($num)) {
		return 0;
	}

	$sbin = 0;
	$strbin = '';

	$len = 32;
	if ($num > 2147483647) {
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

	switch ($last) {
		case 'g':
			$val *= 1024;
			/* falls through */
		case 'm':
			$val *= 1024;
			/* falls through */
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
 * @param bool $ignoreMillisec	without ms (1s 200 ms = 1.2s)
 *
 * @return string
 */
function convertUnitsS($value, $ignoreMillisec = false) {
	if (($secs = round($value * 1000, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT) / 1000) < 0) {
		$secs = -$secs;
		$str = '-';
	}
	else {
		$str = '';
	}

	$values = array('y' => null, 'm' => null, 'd' => null, 'h' => null, 'mm' => null, 's' => null, 'ms' => null);
	$n_unit = 0;

	if (($n = floor($secs / SEC_PER_YEAR)) != 0) {
		$secs -= $n * SEC_PER_YEAR;
		if ($n_unit == 0) {
			$n_unit = 4;
		}
		$values['y'] = $n;
	}

	if (($n = floor($secs / SEC_PER_MONTH)) != 0) {
		$secs -= $n * SEC_PER_MONTH;
		// due to imprecise calculations it is possible that the remainder contains 12 whole months but no whole years
		if ($n == 12) {
			$values['y']++;
			$values['m'] = null;
			if ($n_unit == 0) {
				$n_unit = 4;
			}
		}
		else {
			$values['m'] = $n;
			if ($n_unit == 0) {
				$n_unit = 3;
			}
		}
	}

	if (($n = floor($secs / SEC_PER_DAY)) != 0) {
		$secs -= $n * SEC_PER_DAY;
		$values['d'] = $n;
		if ($n_unit == 0) {
			$n_unit = 2;
		}
	}

	if ($n_unit < 4 && ($n = floor($secs / SEC_PER_HOUR)) != 0) {
		$secs -= $n * SEC_PER_HOUR;
		$values['h'] = $n;
		if ($n_unit == 0) {
			$n_unit = 1;
		}
	}

	if ($n_unit < 3 && ($n = floor($secs / SEC_PER_MIN)) != 0) {
		$secs -= $n * SEC_PER_MIN;
		$values['mm'] = $n;
	}

	if ($n_unit < 2 && ($n = floor($secs)) != 0) {
		$secs -= $n;
		$values['s'] = $n;
	}

	if ($ignoreMillisec) {
		if ($n_unit < 1 && ($n = round($secs, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT)) != 0) {
			$values['s'] += $n;
		}
	}
	else {
		if ($n_unit < 1 && ($n = round($secs * 1000, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT)) != 0) {
			$values['ms'] = $n;
		}
	}

	$str .= isset($values['y']) ? $values['y']._x('y', 'year short').' ' : '';
	$str .= isset($values['m']) ? $values['m']._x('m', 'month short').' ' : '';
	$str .= isset($values['d']) ? $values['d']._x('d', 'day short').' ' : '';
	$str .= isset($values['h']) ? $values['h']._x('h', 'hour short').' ' : '';
	$str .= isset($values['mm']) ? $values['mm']._x('m', 'minute short').' ' : '';
	$str .= isset($values['s']) ? $values['s']._x('s', 'second short').' ' : '';
	$str .= isset($values['ms']) ? $values['ms']._x('ms', 'millisecond short') : '';

	return $str ? rtrim($str) : 0;
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
function convert_units($options = array()) {
	$defOptions = array(
		'value' => null,
		'units' => null,
		'convert' => ITEM_CONVERT_WITH_UNITS,
		'byteStep' => false,
		'pow' => false,
		'ignoreMillisec' => false,
		'length' => false
	);

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

	// any other unit
	// black list of units that should have no multiplier prefix (K, M, G etc) applied
	$blackList = array('%', 'ms', 'rpm', 'RPM');

	if (in_array($options['units'], $blackList) || (zbx_empty($options['units'])
			&& ($options['convert'] == ITEM_CONVERT_WITH_UNITS))) {
		if (abs($options['value']) >= ZBX_UNITS_ROUNDOFF_THRESHOLD) {
			$options['value'] = round($options['value'], ZBX_UNITS_ROUNDOFF_UPPER_LIMIT);
		}
		$options['value'] = sprintf('%.'.ZBX_UNITS_ROUNDOFF_LOWER_LIMIT.'f', $options['value']);
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
		$digitUnits = array();
	}

	if (!isset($digitUnits[$step])) {
		$digitUnits[$step] = array(
			array('pow' => 0, 'short' => ''),
			array('pow' => 1, 'short' => 'K'),
			array('pow' => 2, 'short' => 'M'),
			array('pow' => 3, 'short' => 'G'),
			array('pow' => 4, 'short' => 'T'),
			array('pow' => 5, 'short' => 'P'),
			array('pow' => 6, 'short' => 'E'),
			array('pow' => 7, 'short' => 'Z'),
			array('pow' => 8, 'short' => 'Y')
		);

		foreach ($digitUnits[$step] as $dunit => $data) {
			// skip milli & micro for values without units
			$digitUnits[$step][$dunit]['value'] = bcpow($step, $data['pow'], 9);
		}
	}


	$valUnit = array('pow' => 0, 'short' => '', 'value' => $options['value']);

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

	$result = array(
		'first' => array(),
		'second' => array(),
		'both' => array()
	);

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
	$str_res = array();
	$str_arr = explode("\n", $str);
	foreach ($str_arr as $id => $str_line) {
		array_push($str_res, $str_line, BR());
	}

	return $str_res;
}

function zbx_formatDomId($value) {
	return str_replace(array('[', ']'), array('_', ''), $value);
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

	$rsTable = array();
	foreach ($table as $num => $row) {
		if (mb_strtolower($row[$column]) === mb_strtolower($pattern)) {
			$rsTable = array($num => $row) + $rsTable;
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

	$new_array = array();

	foreach ($keys as $k) {
		$new_array[$k] = $array[$k];
	}

	$array = $new_array;

	return true;
}

function asort_by_key(&$array, $key) {
	if (!is_array($array)) {
		error(_('Incorrect type of asort_by_key.'));
		return array();
	}
	$key = htmlspecialchars($key);
	uasort($array, create_function('$a,$b', 'return $a[\''.$key.'\'] - $b[\''.$key.'\'];'));

	return $array;
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

	$sort = array();
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
	$data = array();
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
	$temp = array();
	foreach ($macros as $key => $macro) {
		$temp[$key] = substr($macro[$sortfield], 2, strlen($macro[$sortfield]) - 3);
	}
	order_result($temp, null, $order);

	$rs = array();
	foreach ($temp as $key => $macroLabel) {
		$rs[$key] = $macros[$key];
	}

	return $rs;
}

// preserve keys
function zbx_array_merge() {
	$args = func_get_args();
	$result = array();
	foreach ($args as &$array) {
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			$result[$key] = $value;
		}
	}

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
		$tmp = array();
		if (is_object($values)) {
			$tmp[] = $values;
		}
		else {
			$tmp[$values] = $values;
		}
		$values = $tmp;
	}
}

// creates chain of relation parent -> childs, for all chain levels
function createParentToChildRelation(&$chain, $link, $parentField, $childField) {
	if (!isset($chain[$link[$parentField]])) {
		$chain[$link[$parentField]] = array();
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
	$result = array();

	if (!is_array($value)) {
		$result = array($value => $value);
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
	$result = array();

	// Value or Array to Object or Array of objects
	if (!is_array($value)) {
		$result = array(array($field => $value));
	}
	elseif (!isset($value[$field])) {
		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[] = array($field => $val);
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

	$result = array();
	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if (zbx_ctype_digit(key($value))) {
			$result = array_values($value);
		}
		elseif (!empty($value)) {
			$result = array($value);
		}
	}
	else {
		$result = array($value);
	}

	return $result;
}

// value OR object OR array of objects TO an array
function zbx_objectValues($value, $field) {
	if (is_null($value)) {
		return $value;
	}
	$result = array();

	if (!is_array($value)) {
		$result = array($value);
	}
	elseif (isset($value[$field])) {
		$result = array($value[$field]);
	}
	else {
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
			$row = array($row);
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
	$result = array();

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
	$result = array();
	if (zbx_empty($text)) {
		return $result;
	}
	preg_match_all('#https?://[^\n\t\r ]+#u', $text, $matches, PREG_OFFSET_CAPTURE);

	$start = 0;
	foreach ($matches[0] as $match) {
		$result[] = mb_substr($text, $start, $match[1] - $start);
		$result[] = new CLink($match[0], $match[0], null, null, true);
		$start = $match[1] + mb_strlen($match[0]);
	}
	$result[] = mb_substr($text, $start, mb_strlen($text));

	return $result;
}

function zbx_subarray_push(&$mainArray, $sIndex, $element = null, $key = null) {
	if (!isset($mainArray[$sIndex])) {
		$mainArray[$sIndex] = array();
	}
	if ($key) {
		$mainArray[$sIndex][$key] = is_null($element) ? $sIndex : $element;
	}
	else {
		$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
	}
}

/*************** PAGE SORTING ******************/

// creates header col for sorting in table header
function make_sorting_header($obj, $tabfield, $sortField, $sortOrder) {
	global $page;

	$sortorder = ($sortField == $tabfield && $sortOrder == ZBX_SORT_UP) ? ZBX_SORT_DOWN : ZBX_SORT_UP;

	$link = CUrlFactory::getContextUrl();

	$link->setArgument('sort', $tabfield);
	$link->setArgument('sortorder', $sortorder);

	$url = $link->getUrl();

	if ($page['type'] != PAGE_TYPE_HTML && defined('ZBX_PAGE_MAIN_HAT')) {
		$script = "javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."', '".$url."');";
	}
	else {
		$script = 'javascript: redirect("'.$url.'");';
	}

	zbx_value2array($obj);
	$cont = new CSpan();

	foreach ($obj as $el) {
		if (is_object($el) || $el === SPACE) {
			$cont->addItem($el);
		}
		else {
			$cont->addItem(new CSpan($el, 'underline'));
		}
	}
	$cont->addItem(SPACE);

	$img = null;
	if ($tabfield == $sortField) {
		if ($sortorder == ZBX_SORT_UP) {
			$img = new CSpan(SPACE, 'icon_sortdown');
		}
		else {
			$img = new CSpan(SPACE, 'icon_sortup');
		}
	}
	$col = new CCol(array($cont, $img), 'nowrap hover_grey');
	$col->setAttribute('onclick', $script);

	return $col;
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
		$pageNumber = ($lastPage == $page['file']) ? CProfile::get('web.paging.page', 1) : 1;
	}

	return $pageNumber;
}

/**
 * Returns paging line.
 *
 * @param array $items				list of items
 *
 * @return CTable
 */
function getPagingLine(&$items) {
	global $page;

	$config = select_config();

	$searchLimit = '';
	if ($config['search_limit'] < count($items)) {
		array_pop($items);
		$searchLimit = '+';
	}

	$rowsPerPage = CWebUser::$data['rows_per_page'];
	$itemsCount = count($items);
	$pagesCount = ($itemsCount > 0) ? ceil($itemsCount / $rowsPerPage) : 1;

	$currentPage = getPageNumber();
	if ($currentPage < 1) {
		$currentPage = 1;
	}

	if ($currentPage > $pagesCount) {
		$currentPage = $pagesCount;
	}

	$start = ($currentPage - 1) * $rowsPerPage;

	CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
	CProfile::update('web.paging.page', $currentPage, PROFILE_TYPE_INT);

	// trim array with items to contain items for current page
	$items = array_slice($items, $start, $rowsPerPage, true);

	// viewed pages (better to use not odd)
	$pagingNavRange = 11;

	$endPage = $currentPage + floor($pagingNavRange / 2);
	if ($endPage < $pagingNavRange) {
		$endPage = $pagingNavRange;
	}
	if ($endPage > $pagesCount) {
		$endPage = $pagesCount;
	}

	$startPage = ($endPage > $pagingNavRange) ? $endPage - $pagingNavRange + 1 : 1;

	$pageLine = array();

	$table = null;

	if ($pagesCount > 1) {
		$url = CUrlFactory::getContextUrl();

		if ($startPage > 1) {
			$url->setArgument('page', 1);
			$pageLine[] = new CLink('<< '._x('First', 'page navigation'), $url->getUrl(), null, null, true);
			$pageLine[] = '&nbsp;&nbsp;';
		}

		if ($currentPage > 1) {
			$url->setArgument('page', $currentPage - 1);
			$pageLine[] = new CLink('< '._x('Previous', 'page navigation'), $url->getUrl(), null, null, true);
			$pageLine[] = ' | ';
		}

		for ($p = $startPage; $p <= $pagesCount; $p++) {
			if ($p > $endPage) {
				break;
			}

			if ($p == $currentPage) {
				$pagespan = new CSpan($p, 'bold textcolorstyles');
			}
			else {
				$url->setArgument('page', $p);
				$pagespan = new CLink($p, $url->getUrl(), null, null, true);
			}

			$pageLine[] = $pagespan;
			$pageLine[] = ' | ';
		}

		array_pop($pageLine);

		if ($currentPage < $pagesCount) {
			$pageLine[] = ' | ';

			$url->setArgument('page', $currentPage + 1);
			$pageLine[] = new CLink(_x('Next', 'page navigation').' >', $url->getUrl(), null, null, true);
		}

		if ($p < $pagesCount) {
			$pageLine[] = '&nbsp;&nbsp;';

			$url->setArgument('page', $pagesCount);
			$pageLine[] = new CLink(_x('Last', 'page navigation').' >>', $url->getUrl(), null, null, true);
		}

		$table = new CTable(null, 'paging');
		$table->addRow(new CCol($pageLine));
	}

	$viewFromPage = ($currentPage - 1) * $rowsPerPage + 1;

	$viewTillPage = $currentPage * $rowsPerPage;
	if ($viewTillPage > $itemsCount) {
		$viewTillPage = $itemsCount;
	}

	$pageView = array();
	$pageView[] = _('Displaying').SPACE;
	if ($itemsCount > 0) {
		$pageView[] = new CSpan($viewFromPage, 'info');
		$pageView[] = SPACE._('to').SPACE;
	}

	$pageView[] = new CSpan($viewTillPage, 'info');
	$pageView[] = SPACE._('of').SPACE;
	$pageView[] = new CSpan($itemsCount, 'info');
	$pageView[] = $searchLimit;
	$pageView[] = SPACE._('found');

	$pageView = new CSpan($pageView);

	zbx_add_post_js('insertInElement("numrows", '.zbx_jsvalue($pageView->toString()).', "div");');

	return $table;
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
 * The $mode parameters controls the layout of the message:
 * - ACCESS_DENY_OBJECT     - render the message when denying access to a specific object
 * - ACCESS_DENY_PAGE       - render a complete access denied page
 *
 * @param int $mode
 */
function access_deny($mode = ACCESS_DENY_OBJECT) {
	// deny access to an object
	if ($mode == ACCESS_DENY_OBJECT) {
		require_once dirname(__FILE__).'/page_header.php';
		show_error_message(_('No permissions to referred object or it does not exist!'));
		require_once dirname(__FILE__).'/page_footer.php';
	}
	// deny access to a page
	else {
		// url to redirect the user to after he loggs in
		$url = new CUrl(!empty($_REQUEST['request']) ? $_REQUEST['request'] : '');
		$url->setArgument('sid', null);
		$url = urlencode($url->toString());

		// if the user is logged in - render the access denied message
		if (CWebUser::isLoggedIn()) {
			$header = _('Access denied.');
			$message = array(
				_('Your are logged in as'),
				' ',
				bold(CWebUser::$data['alias']),
				'. ',
				_('You have no permissions to access this page.'),
				BR(),
				_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
			);

			$buttons = array();
			// display the login button only for guest users
			if (CWebUser::isGuest()) {
				$buttons[] = new CButton('login', _('Login'),
					'javascript: document.location = "index.php?request='.$url.'";', 'button'
				);
			}
			$buttons[] = new CButton('back', _('Go to dashboard'),
				'javascript: document.location = "dashboard.php"', 'button'
			);
		}
		// if the user is not logged in - offer to login
		else {
			$header = _('You are not logged in.');
			$message = array(
				_('You must login to view this page.'),
				BR(),
				_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
			);
			$buttons = array(
				new CButton('login', _('Login'), 'javascript: document.location = "index.php?request='.$url.'";', 'button')
			);
		}

		$warning = new CWarning($header, $message);
		$warning->setButtons($buttons);

		$warningView = new CView('general.warning', array(
			'warning' => $warning
		));
		$warningView->render();
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

function show_messages($bool = true, $okmsg = null, $errmsg = null) {
	global $page, $ZBX_MESSAGES;

	if (!defined('PAGE_HEADER_LOADED')) {
		return null;
	}
	if (defined('ZBX_API_REQUEST')) {
		return null;
	}
	if (!isset($page['type'])) {
		$page['type'] = PAGE_TYPE_HTML;
	}

	$imageMessages = array();

	if (!$bool && !is_null($errmsg)) {
		$msg = _('ERROR').': '.$errmsg;
	}
	elseif ($bool && !is_null($okmsg)) {
		$msg = $okmsg;
	}

	if (isset($msg)) {
		switch ($page['type']) {
			case PAGE_TYPE_IMAGE:
				// save all of the messages in an array to display them later in an image
				$imageMessages[] = array(
					'text' => $msg,
					'color' => (!$bool) ? array('R' => 255, 'G' => 0, 'B' => 0) : array('R' => 34, 'G' => 51, 'B' => 68)
				);
				break;
			case PAGE_TYPE_XML:
				echo htmlspecialchars($msg)."\n";
				break;
			case PAGE_TYPE_HTML:
			default:
				$msg_tab = new CTable($msg, ($bool ? 'msgok' : 'msgerr'));
				$msg_tab->setCellPadding(0);
				$msg_tab->setCellSpacing(0);

				$row = array();

				$msg_col = new CCol(bold($msg), 'msg_main msg');
				$msg_col->setAttribute('id', 'page_msg');
				$row[] = $msg_col;

				if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
					$msg_details = new CDiv(_('Details'), 'blacklink');
					$msg_details->setAttribute('onclick', 'javascript: showHide("msg_messages", IE ? "block" : "table");');
					$msg_details->setAttribute('title', _('Maximize').'/'._('Minimize'));
					array_unshift($row, new CCol($msg_details, 'clr'));
				}
				$msg_tab->addRow($row);
				$msg_tab->show();
				break;
		}
	}

	if (isset($ZBX_MESSAGES) && !empty($ZBX_MESSAGES)) {
		if ($page['type'] == PAGE_TYPE_IMAGE) {
			foreach ($ZBX_MESSAGES as $msg) {
				// save all of the messages in an array to display them later in an image
				if ($msg['type'] == 'error') {
					$imageMessages[] = array(
						'text' => $msg['message'],
						'color' => array('R' => 255, 'G' => 55, 'B' => 55)
					);
				}
				else {
					$imageMessages[] = array(
						'text' => $msg['message'],
						'color' => array('R' => 155, 'G' => 155, 'B' => 55)
					);
				}
			}
		}
		elseif ($page['type'] == PAGE_TYPE_XML) {
			foreach ($ZBX_MESSAGES as $msg) {
				echo '['.$msg['type'].'] '.$msg['message']."\n";
			}
		}
		else {
			$lst_error = new CList(null,'messages');
			foreach ($ZBX_MESSAGES as $msg) {
				$lst_error->addItem($msg['message'], $msg['type']);
				$bool = ($bool && 'error' !== strtolower($msg['type']));
			}
			$msg_show = 6;
			$msg_count = count($ZBX_MESSAGES);
			if ($msg_count > $msg_show) {
				$msg_count = $msg_show * 16;
				$lst_error->setAttribute('style', 'height: '.$msg_count.'px;');
			}
			$tab = new CTable(null, ($bool ? 'msgok' : 'msgerr'));
			$tab->setCellPadding(0);
			$tab->setCellSpacing(0);
			$tab->setAttribute('id', 'msg_messages');
			$tab->setAttribute('style', 'width: 100%;');
			if (isset($msg_tab) && $bool) {
				$tab->setAttribute('style', 'display: none;');
			}
			$tab->addRow(new CCol($lst_error, 'msg'));
			$tab->show();
		}
		$ZBX_MESSAGES = null;
	}

	// draw an image with the messages
	if ($page['type'] == PAGE_TYPE_IMAGE && count($imageMessages) > 0) {
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

	zbx_value2array($msgs);
	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}
	foreach ($msgs as $msg) {
		array_push($ZBX_MESSAGES, array('type' => 'info', 'message' => $msg));
	}
}

function error($msgs) {
	global $ZBX_MESSAGES;

	if (is_null($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = array();
	}

	$msgs = zbx_toArray($msgs);
	foreach ($msgs as $msg) {
		if (isset(CWebUser::$data['debug_mode']) && !is_object($msg) && !CWebUser::$data['debug_mode']) {
			$msg = preg_replace('/^\[.+?::.+?\]/', '', $msg);
		}
		array_push($ZBX_MESSAGES, array('type' => 'error', 'message' => $msg));
	}
}

function clear_messages($count = null) {
	global $ZBX_MESSAGES;

	$result = array();
	if (!is_null($count)) {
		while ($count-- > 0) {
			array_unshift($result, array_pop($ZBX_MESSAGES));
		}
	}
	else {
		$result = $ZBX_MESSAGES;
		$ZBX_MESSAGES = null;
	}
	return $result;
}

function fatal_error($msg) {
	require_once dirname(__FILE__).'/page_header.php';
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function parse_period($str) {
	$out = null;
	$str = trim($str, ';');
	$periods = explode(';', $str);
	foreach ($periods as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $arr)) {
			return null;
		}

		for ($i = $arr[1]; $i <= $arr[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = array();
			}
			array_push($out[$i], array(
				'start_h' => $arr[3],
				'start_m' => $arr[4],
				'end_h' => $arr[5],
				'end_m' => $arr[6]
			));
		}
	}
	return $out;
}

function get_status() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$status = array(
		'triggers_count' => 0,
		'triggers_count_enabled' => 0,
		'triggers_count_disabled' => 0,
		'triggers_count_off' => 0,
		'triggers_count_on' => 0,
		'items_count' => 0,
		'items_count_monitored' => 0,
		'items_count_disabled' => 0,
		'items_count_not_supported' => 0,
		'hosts_count' => 0,
		'hosts_count_monitored' => 0,
		'hosts_count_not_monitored' => 0,
		'hosts_count_template' => 0,
		'users_online' => 0,
		'qps_total' => 0
	);

	// server
	$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, 0);
	$status['zabbix_server'] = $zabbixServer->isRunning() ? _('Yes') : _('No');

	// triggers
	$dbTriggers = DBselect(
		'SELECT COUNT(DISTINCT t.triggerid) AS cnt,t.status,t.value'.
			' FROM triggers t'.
			' WHERE NOT EXISTS ('.
				'SELECT f.functionid FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN hosts h ON i.hostid=h.hostid'.
					' WHERE f.triggerid=t.triggerid AND (i.status<>'.ITEM_STATUS_ACTIVE.' OR h.status<>'.HOST_STATUS_MONITORED.')'.
				')'.
			' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
			' GROUP BY t.status,t.value'
		);
	while ($dbTrigger = DBfetch($dbTriggers)) {
		switch ($dbTrigger['status']) {
			case TRIGGER_STATUS_ENABLED:
				switch ($dbTrigger['value']) {
					case TRIGGER_VALUE_FALSE:
						$status['triggers_count_off'] = $dbTrigger['cnt'];
						break;
					case TRIGGER_VALUE_TRUE:
						$status['triggers_count_on'] = $dbTrigger['cnt'];
						break;
				}
				break;
			case TRIGGER_STATUS_DISABLED:
				$status['triggers_count_disabled'] += $dbTrigger['cnt'];
				break;
		}
	}
	$status['triggers_count_enabled'] = $status['triggers_count_off'] + $status['triggers_count_on'];
	$status['triggers_count'] = $status['triggers_count_enabled'] + $status['triggers_count_disabled'];

	// items
	$dbItems = DBselect(
		'SELECT COUNT(i.itemid) AS cnt,i.status,i.state'.
				' FROM items i'.
				' INNER JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					' AND i.type<>'.ITEM_TYPE_HTTPTEST.
				' GROUP BY i.status,i.state');
	while ($dbItem = DBfetch($dbItems)) {
		if ($dbItem['status'] == ITEM_STATUS_ACTIVE) {
			if ($dbItem['state'] == ITEM_STATE_NORMAL) {
				$status['items_count_monitored'] = $dbItem['cnt'];
			}
			else {
				$status['items_count_not_supported'] = $dbItem['cnt'];
			}
		}
		elseif ($dbItem['status'] == ITEM_STATUS_DISABLED) {
			$status['items_count_disabled'] += $dbItem['cnt'];
		}
	}
	$status['items_count'] = $status['items_count_monitored'] + $status['items_count_disabled']
			+ $status['items_count_not_supported'];

	// hosts
	$dbHosts = DBselect(
		'SELECT COUNT(*) AS cnt,h.status'.
		' FROM hosts h'.
		' WHERE '.dbConditionInt('h.status', array(
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE
			)).
			' AND '.dbConditionInt('h.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' GROUP BY h.status');
	while ($dbHost = DBfetch($dbHosts)) {
		switch ($dbHost['status']) {
			case HOST_STATUS_MONITORED:
				$status['hosts_count_monitored'] = $dbHost['cnt'];
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status['hosts_count_not_monitored'] = $dbHost['cnt'];
				break;
			case HOST_STATUS_TEMPLATE:
				$status['hosts_count_template'] = $dbHost['cnt'];
				break;
		}
	}
	$status['hosts_count'] = $status['hosts_count_monitored'] + $status['hosts_count_not_monitored']
			+ $status['hosts_count_template'];

	// users
	$row = DBfetch(DBselect('SELECT COUNT(*) AS usr_cnt FROM users u'));

	$status['users_count'] = $row['usr_cnt'];
	$status['users_online'] = 0;

	$db_sessions = DBselect(
		'SELECT s.userid,s.status,MAX(s.lastaccess) AS lastaccess'.
		' FROM sessions s'.
		' WHERE s.status='.ZBX_SESSION_ACTIVE.
		' GROUP BY s.userid,s.status'
	);
	while ($session = DBfetch($db_sessions)) {
		if (($session['lastaccess'] + ZBX_USER_ONLINE_TIME) >= time()) {
			$status['users_online']++;
		}
	}

	// comments: !!! Don't forget sync code with C !!!
	$row = DBfetch(DBselect(
		'SELECT SUM(CAST(1.0/i.delay AS DECIMAL(20,10))) AS qps'.
		' FROM items i,hosts h'.
		' WHERE i.status='.ITEM_STATUS_ACTIVE.
		' AND i.hostid=h.hostid'.
		' AND h.status='.HOST_STATUS_MONITORED.
		' AND i.delay<>0'.
		' AND i.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE
	));
	$status['qps_total'] = round($row['qps'], 2);

	return $status;
}

function set_image_header($format = null) {
	global $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	if (IMAGE_FORMAT_JPEG == $format) {
		header('Content-type:  image/jpeg');
	}
	if (IMAGE_FORMAT_TEXT == $format) {
		header('Content-type:  text/html');
	}
	else {
		header('Content-type:  image/png');
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
		session_start();
		$imageId = md5(strlen($imageSource));
		$_SESSION['image_id'] = array();
		$_SESSION['image_id'][$imageId] = $imageSource;
		session_write_close();
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			echo $imageSource;
			break;
		case PAGE_TYPE_JSON:
			$json = new CJson();
			echo $json->encode(array('result' => $imageId));
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

	if ($ZBX_MESSAGES !== null) {
		foreach ($ZBX_MESSAGES as $message) {
			if ($message['type'] === 'error') {
				return true;
			}
		}
	}

	return false;
}

/**
 * Check if all keys from $keys exist in $array.
 * If some keys are missing return array of missing keys, true otherwise.
 *
 * @param array $array
 * @param array $keys
 *
 * @return array|bool
 */
function checkRequiredKeys(array $array, array $keys) {
	return array_diff($keys, array_keys($array));
}

/**
 * Clears table rows selection's cookies.
 *
 * @param string $id	parent id, is used as cookie suffix
 */
function uncheckTableRows($cookieId = null) {
	insert_js('cookie.eraseArray("cb_'.basename($_SERVER['SCRIPT_NAME'], '.php').($cookieId ? '_'.$cookieId : '').'")');
}

/**
 * Splitting string using slashes with escape backslash support and non-pair backslash cleanup.
 *
 * @param string $path					String path to parse.
 * @param bool   $stripSlashes			Remove escaped slashes from the path pieces.
 * @param bool   $cleanupBackslashes	Cleanup invalid backslash combinations.
 *
 * @return array
 */
function splitPath($path, $stripSlashes = true, $cleanupBackslashes = false) {
	$position = 0;
	$escapeCharacters = '';
	$pathItemsArray = array();
	$pathItemString = '';

	for ($stringLength = strlen($path); $position < $stringLength; ++$position) {
		// Determine how many escape characters we already have in the backlog.
		$escapeCharacterCount = strlen($escapeCharacters);

		if ($path[$position] === '/') {
			// If we have no escape chars previously - save item into the array and move on.
			if ($escapeCharacterCount == 0) {
				$pathItemsArray[] = $pathItemString;
				$escapeCharacters = '';
				$pathItemString = '';
				continue;
			}

			// We have a backslash before the / - keep it as part of the item and clean escape char buffer.
			$pathItemString .= $escapeCharacters.$path[$position];
			$escapeCharacters = '';
		}
		elseif ($cleanupBackslashes && $path[$position] === '\\') {

			/*
			 * If we had a backslash before - this is an escaped backslash, keep it and empty char backlog. This way
			 * we save only paired backslashes.
			 */
			if ($escapeCharacterCount == 1) {
				$pathItemString .= $escapeCharacters.$path[$position];
				$escapeCharacters = '';
			}
			else {
				// It is a first backslash - add it to the backlog.
				$escapeCharacters .= $path[$position];
			}
		}
		else {
			// A regular character - save it and move on. If previous char was a backslash - it is dropped.
			$pathItemString .= $path[$position];
			$escapeCharacters = '';
		}
	}

	// Save the path tail.
	if (strlen($pathItemString) != 0) {
		$pathItemsArray[] = $pathItemString;
	}

	if ($stripSlashes) {
		$pathItemsArray = array_map('stripslashes', $pathItemsArray);
	}

	return $pathItemsArray;
}
