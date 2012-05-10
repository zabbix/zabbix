<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

/************ REQUEST ************/
function redirect($url){
	zbx_flush_post_cookies();

	$curl = new Curl($url);
	$curl->setArgument('sid', null);
	header('Location: '.$curl->getUrl());
	exit();
}

function jsRedirect($url,$timeout=null){
	zbx_flush_post_cookies();

	$script = '';
	if( is_numeric($timeout) ) {
		$script.='setTimeout(\'window.location="'.$url.'"\','.($timeout*1000).')';
	}
	else {
		$script.='window.location.replace("'.$url.'");';
	}
	insert_js($script);
}

function get_request($name, $def=NULL){
	if(isset($_REQUEST[$name]))
		return $_REQUEST[$name];
	else
		return $def;
}


function inarr_isset($keys, $array=null){
	if(is_null($array)) $array =& $_REQUEST;

	if(is_array($keys)){
		foreach($keys as $id => $key){
			if( !isset($array[$key]) )
				return false;
		}
		return true;
	}

	return isset($array[$keys]);
}
/************ END REQUEST ************/

/************ COOKIES ************/

function get_cookie($name, $default_value=null){
	if(isset($_COOKIE[$name]))	return $_COOKIE[$name];
return $default_value;
}

function zbx_setcookie($name, $value, $time=null){
	setcookie($name, $value, isset($time) ? $time : (0));
	$_COOKIE[$name] = $value;
}

function zbx_unsetcookie($name){
	zbx_setcookie($name, null, -99999);
	unset($_COOKIE[$name]);
}

function zbx_flush_post_cookies($unset=false){
	global $ZBX_PAGE_COOKIES;

	if(isset($ZBX_PAGE_COOKIES)){
		foreach($ZBX_PAGE_COOKIES as $cookie){
			if($unset)
				zbx_unsetcookie($cookie[0]);
			else
				zbx_setcookie($cookie[0], $cookie[1], $cookie[2]);
		}
		unset($ZBX_PAGE_COOKIES);
	}
}

function zbx_set_post_cookie($name, $value, $time=null){
	global $ZBX_PAGE_COOKIES;

	$ZBX_PAGE_COOKIES[] = array($name, $value, isset($time)?$time:0);
}

/************ END COOKIES ************/

/************* DATE *************/
function getMonthCaption($num){
	switch($num){
		case 1: $month = S_JANUARY; break;
		case 2: $month = S_FEBRUARY; break;
		case 3: $month = S_MARCH; break;
		case 4: $month = S_APRIL; break;
		case 5: $month = S_MAY; break;
		case 6: $month = S_JUNE; break;
		case 7: $month = S_JULY; break;
		case 8: $month = S_AUGUST; break;
		case 9: $month = S_SEPTEMBER; break;
		case 10: $month = S_OCTOBER; break;
		case 11: $month = S_NOVEMBER; break;
		case 12: $month = S_DECEMBER; break;
		default: $month = S_CONFIG_WARNING_WRONG_MONTH_PART1.SPACE.$num.SPACE.S_CONFIG_WARNING_WRONG_MONTH_PART2;
	}

return $month;
}

function getDayOfWeekCaption($num){
	switch($num){
		case 1: $day = S_MONDAY; break;
		case 2: $day = S_TUESDAY; break;
		case 3: $day = S_WEDNESDAY; break;
		case 4: $day = S_THURSDAY; break;
		case 5: $day = S_FRIDAY; break;
		case 6: $day = S_SATURDAY; break;
		case 7: $day = S_SUNDAY; break;
		default: $day = S_CONFIG_WARNING_WRONG_DOW_PART1.SPACE.$num.SPACE.S_CONFIG_WARNING_WRONG_DOW_PART2;
	}

return $day;
}
/* function:
 *	zbx_date2str
 *
 * description:
 *	Convert timestamp to string representation. Retun 'Never' if 0.
 *
 * author: Alexei Vladishev
 */
function zbx_date2str($format, $value = null){
	static $weekdaynames, $weekdaynameslong, $months, $monthslong;

	if(is_null($value)) $value = time();
	else if(!$value) return S_NEVER;

	if(!is_array($weekdaynames)) {
		$weekdaynames = array(
					0 => S_WEEKDAY_SUNDAY_SHORT,
					1 => S_WEEKDAY_MONDAY_SHORT,
					2 => S_WEEKDAY_TUESDAY_SHORT,
					3 => S_WEEKDAY_WEDNESDAY_SHORT,
					4 => S_WEEKDAY_THURSDAY_SHORT,
					5 => S_WEEKDAY_FRIDAY_SHORT,
					6 => S_WEEKDAY_SATURDAY_SHORT);
	}

	if(!is_array($weekdaynameslong)) {
		$weekdaynameslong = array(
					0 => S_WEEKDAY_SUNDAY_LONG,
					1 => S_WEEKDAY_MONDAY_LONG,
					2 => S_WEEKDAY_TUESDAY_LONG,
					3 => S_WEEKDAY_WEDNESDAY_LONG,
					4 => S_WEEKDAY_THURSDAY_LONG,
					5 => S_WEEKDAY_FRIDAY_LONG,
					6 => S_WEEKDAY_SATURDAY_LONG);
	}

	if(!is_array($months)) {
		$months = array(
				1 => S_MONTH_JANUARY_SHORT,
				2 => S_MONTH_FEBRUARY_SHORT,
				3 => S_MONTH_MARCH_SHORT,
				4 => S_MONTH_APRIL_SHORT,
				5 => S_MONTH_MAY_SHORT,
				6 => S_MONTH_JUNE_SHORT,
				7 => S_MONTH_JULY_SHORT,
				8 => S_MONTH_AUGUST_SHORT,
				9 => S_MONTH_SEPTEMBER_SHORT,
				10 => S_MONTH_OCTOBER_SHORT,
				11 => S_MONTH_NOVEMBER_SHORT,
				12 => S_MONTH_DECEMBER_SHORT);
	}

	if(!is_array($monthslong)) {
		$monthslong = array(
					1 => S_MONTH_JANUARY_LONG,
					2 => S_MONTH_FEBRUARY_LONG,
					3 => S_MONTH_MARCH_LONG,
					4 => S_MONTH_APRIL_LONG,
					5 => S_MONTH_MAY_LONG,
					6 => S_MONTH_JUNE_LONG,
					7 => S_MONTH_JULY_LONG,
					8 => S_MONTH_AUGUST_LONG,
					9 => S_MONTH_SEPTEMBER_LONG,
					10 => S_MONTH_OCTOBER_LONG,
					11 => S_MONTH_NOVEMBER_LONG,
					12 => S_MONTH_DECEMBER_LONG);
	}

	$rplcs = array(
		'l' => $weekdaynameslong[date('w',$value)],
		'F' => $monthslong[date('n',$value)],
		'D' => $weekdaynames[date('w',$value)],
		'M' => $months[date('n',$value)]
	);

	$output = '';
	$part = '';
	$length = zbx_strlen($format);
	for($i = 0; $i < $length; $i++) {
		$pchar = $i > 0 ? zbx_substr($format, $i-1, 1) : '';
		$char = zbx_substr($format, $i, 1);

		if(($pchar != '\\') && isset($rplcs[$char])) {
			$output .= (zbx_strlen($part) ? date($part, $value) : '').$rplcs[$char];
			$part = '';
		}
		else{
			$part .= $char;
		}
	}

	$output .= (zbx_strlen($part) > 0) ? date($part, $value) : '';

return $output;
}

/* function:
 *	zbx_date2age
 *
 * description:
 *	Calculate and convert timestamp to string representation.
 *
 * author: Aly
 */
function zbx_date2age($start_date, $end_date = null, $utime = false) {
	if (!$utime) {
		$start_date = date('U', $start_date);
		$end_date = $end_date ? date('U', $end_date) : time();
	}

	return convertUnitsS(abs($end_date - $start_date));
}

function getmicrotime(){
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}

function zbxDateToTime($strdate) {
	if (6 == sscanf($strdate, '%04d%02d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes, $seconds)) {
		return mktime($hours, $minutes, $seconds, $month, $date, $year);
	}
	elseif (5 == sscanf($strdate, '%04d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes)) {
		return mktime($hours, $minutes, 0, $month, $date, $year);
	}
	else {
		return time();
	}
}
/************* END DATE *************/

/*************** CONVERTING ******************/
function rgb2hex($color){
	$HEX = array(
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	);

	foreach($HEX as $id => $value){
		if(zbx_strlen($value) != 2) $HEX[$id] = '0'.$value;
	}

return $HEX[0].$HEX[1].$HEX[2];
}

function hex2rgb($color){
	if($color[0] == '#') $color = substr($color, 1);

	if(zbx_strlen($color) == 6){
		list($r, $g, $b) = array($color[0].$color[1],
								 $color[2].$color[3],
								 $color[4].$color[5]);
	}
	else if(zbx_strlen($color) == 3){
		list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
	}
	else{
		return false;
	}

	$r = hexdec($r);
	$g = hexdec($g);
	$b = hexdec($b);

return array($r, $g, $b);
}

function zbx_num2bitstr($num,$rev=false){
	if(!is_numeric($num)) return 0;

	$sbin = 0;
	$strbin = '';

	$len = 32;
	if($num > 2147483647) $len = 64;

	for($i=0;$i<$len;$i++){
		$sbin= 1 << $i;
		$bit = ($sbin & $num)? '1':'0';
		if($rev){
			$strbin.=$bit;
		}
		else{
			$strbin = $bit.$strbin;
		}
	}

return $strbin;
}

function empty2null($var){
	return ($var == "") ? null : $var;
}

function str2mem($val){
	$val = trim($val);
	$last = zbx_strtolower(zbx_substr($val, -1, 1));

	switch($last){
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function mem2str($size){
	$prefix = S_B;
	if($size > 1048576) {	$size = $size/1048576;	$prefix = S_M; }
	elseif($size > 1024) {	$size = $size/1024;	$prefix = S_K; }
	return round($size, 6).$prefix;
}

function convertUnitsUptime($value){
	if(($secs = round($value)) < 0){
		$value = '-';
		$secs = -$secs;
	}
	else
		$value = '';

	$days = floor($secs / SEC_PER_DAY);
	$secs -= $days * SEC_PER_DAY;

	$hours = floor($secs / SEC_PER_HOUR);
	$secs -= $hours * SEC_PER_HOUR;

	$mins = floor($secs / SEC_PER_MIN);
	$secs -= $mins * SEC_PER_MIN;

	if($days != 0)
		$value .= $days.' '.S_DAYS_SMALL.', ';
	$value .= sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

	return $value;
}

function convertUnitsS($value){
	if(floor(abs($value) * 1000) == 0){
		$value = ($value == 0 ? '0'.S_SECOND_SHORT : '< 1'.S_MILLISECOND_SHORT);
		return $value;
	}

	if(($secs = round($value * 1000) / 1000) < 0){
		$value = '-';
		$secs = -$secs;
	}
	else
		$value = '';
	$n_unit = 0;

	if(($n = floor($secs / SEC_PER_YEAR)) != 0){
		$value .= $n.S_YEAR_SHORT.' ';
		$secs -= $n * SEC_PER_YEAR;
		if (0 == $n_unit)
			$n_unit = 4;
	}

	if(($n = floor($secs / SEC_PER_MONTH)) != 0){
		$value .= $n.S_MONTH_SHORT.' ';
		$secs -= $n * SEC_PER_MONTH;
		if (0 == $n_unit)
			$n_unit = 3;
	}

	if(($n = floor($secs / SEC_PER_DAY)) != 0){
		$value .= $n.S_DAY_SHORT.' ';
		$secs -= $n * SEC_PER_DAY;
		if (0 == $n_unit)
			$n_unit = 2;
	}

	if($n_unit < 4 && ($n = floor($secs / SEC_PER_HOUR)) != 0){
		$value .= $n.S_HOUR_SHORT.' ';
		$secs -= $n * SEC_PER_HOUR;
		if (0 == $n_unit)
			$n_unit = 1;
	}

	if($n_unit < 3 && ($n = floor($secs / SEC_PER_MIN)) != 0){
		$value .= $n.S_MINUTE_SHORT.' ';
		$secs -= $n * SEC_PER_MIN;
	}

	if($n_unit < 2 && ($n = floor($secs)) != 0){
		$value .= $n.S_SECOND_SHORT.' ';
		$secs -= $n;
	}

	if($n_unit < 1 && ($n = round($secs * 1000)) != 0)
		$value .= $n.S_MILLISECOND_SHORT;

	return rtrim($value);
}

// convert:
function convert_units($value, $units, $convert=ITEM_CONVERT_WITH_UNITS){

// Special processing for unix timestamps
	if($units == 'unixtime'){
		$ret=zbx_date2str(S_FUNCT_UNIXTIMESTAMP_DATE_FORMAT,$value);
		return $ret;
	}
//Special processing of uptime
	if($units == 'uptime'){
		return convertUnitsUptime($value);
	}
// Special processing for seconds
	if($units == 's'){
		return convertUnitsS($value);
	}

	$u='';

// Any other unit
//-------------------
// black list wich do not require units metrics..
	$blackList = array('%','ms','rpm','RPM');

	if(in_array($units, $blackList) || (zbx_empty($units) && (($convert == ITEM_CONVERT_WITH_UNITS) || ($value < 1)))){
		if(abs($value) >= ZBX_UNITS_ROUNDOFF_THRESHOLD)
			$value = round($value, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT);
		$value = sprintf('%.'.ZBX_UNITS_ROUNDOFF_LOWER_LIMIT.'f', $value);

		$value = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U','$1$2$3', $value);
		$value = rtrim($value, '.');

		if(zbx_empty($units)) return $value;
		else return $value.' '.$units;
	}

	switch($units){
		case 'Bps':
		case 'B':
			$step=1024;
			$convert = $convert?$convert:ITEM_CONVERT_NO_UNITS;
			break;
		case 'b':
		case 'bps':
			$convert = $convert?$convert:ITEM_CONVERT_NO_UNITS;
		default:
			$step = 1000;
	}

// INIT intervals
	static $digitUnits;
	if(is_null($digitUnits)) $digitUnits = array();

	if(!isset($digitUnits[$step])){
		$digitUnits[$step] = array(
//				array('pow'=>-3, 'short'=>S_N_SMALL, 'long'=>S_NANO),
				array('pow'=>-2, 'short'=>S_U_MICRO, 'long'=>S_MICRO),
				array('pow'=>-1, 'short'=>S_M_SMALL, 'long'=>S_MILLI),
				array('pow'=>0, 'short'=>'', 'long'=>''),
				array('pow'=>1, 'short'=>S_K, 'long'=>S_KILO),
				array('pow'=>2, 'short'=>S_M, 'long'=>S_MEGA),
				array('pow'=>3, 'short'=>S_G, 'long'=>S_GIGA),
				array('pow'=>4, 'short'=>S_T, 'long'=>S_TERA),
				array('pow'=>5, 'short'=>S_P, 'long'=>S_PETA),
				array('pow'=>6, 'short'=>S_E, 'long'=>S_EXA),
				array('pow'=>7, 'short'=>S_Z, 'long'=>S_ZETTA),
				array('pow'=>8, 'short'=>S_Y, 'long'=>S_YOTTA)
			);

		foreach($digitUnits[$step] as $dunit => $data){
// skip mili & micro for values without units
			$digitUnits[$step][$dunit]['value'] = bcpow($step, $data['pow'], 9);
		}
	}
//---

	if($value < 0) $abs = bcmul($value, '-1');
	else $abs = $value;

	$valUnit = array('pow'=>0, 'short'=>'', 'long'=>'', 'value'=>$value);
	if(($abs > 999) || ($abs < 0.001)){
		foreach($digitUnits[$step] as $dnum => $data){
			if(bccomp($abs, $data['value']) > -1) $valUnit = $data;
			else break;
		}

		if(round($valUnit['value'], 6) > 0){
			$valUnit['value'] = bcdiv(sprintf('%.6f',$value), sprintf('%.6f', $valUnit['value']), 6);
		}
		else
			$valUnit['value'] = 0;
	}

//------
	switch($convert){
		case 0: $units = trim($units);
		case 1: $desc = $valUnit['short']; break;
		case 2: $desc = $valUnit['long']; break;
	}

	$value = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U','$1$2$3', round($valUnit['value'], ZBX_UNITS_ROUNDOFF_UPPER_LIMIT));
	$value = rtrim($value, '.');

return sprintf('%s %s%s', $value, $desc, $units);
}

/*************** END CONVERTING ******************/


/************* ZBX MISC *************/
function zbx_avg($values){
	zbx_value2array($values);

	$sum = 0;
	foreach($values as $num => $value){
		$sum = bcadd($sum, $value);
	}

return bcdiv($sum, count($values));
}

// accepts parametr as integer either
function zbx_ctype_digit($x){
	return ctype_digit(strval($x));
}

function zbx_empty($value){
	if(is_null($value)) return true;
	if(is_array($value) && empty($value)) return true;
	if(is_string($value) && ($value === '')) return true;
return false;
}

function zbx_is_int($var){
	if(is_int($var)) return true;

	if(is_string($var))
		if(ctype_digit($var) || (strcmp(intval($var), $var) == 0)) return true;
	else
		if(($var>0) && zbx_ctype_digit($var)) return true;

return preg_match("/^\-?[0-9]+$/", $var);
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
 * @param array $array
 * @return array
 */
function zbx_arrayFindDuplicates(array $array){
	$countValues = array_count_values($array); // counting occurrences of every value in array
	foreach($countValues as $value => $count){
		if($count <= 1){
			unset($countValues[$value]);
		}
	}
	arsort($countValues); // sorting, so that the most duplicates would be at the top
	return $countValues;
}

// STRING FUNCTIONS {{{
if(!function_exists('zbx_stripslashes')){
function zbx_stripslashes($value){
	if(is_array($value)){
		foreach($value as $id => $data){
			$value[$id] = zbx_stripslashes($data);
		}
	}
	else if(is_string($value)){
		$value = stripslashes($value);
	}

return $value;
}
}

function zbx_nl2br($str){
	$str_res = array();
	$str_arr = explode("\n",$str);
	foreach($str_arr as $id => $str_line){
		array_push($str_res,$str_line,BR());
	}
return $str_res;
}

function zbx_htmlstr($str){
	return str_replace(array('<','>','"'),array('&lt;','&gt;','&quot;'), $str);
}

function zbx_strlen($str){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strlen($str);
	}
	else{
		return strlen($str);
	}

/*
	$zbx_strlen = strlen($zbx_strlen);

	$reallen = 0;
	$fbin= 1 << 7;
	$sbin= 1 << 6;

// check first byte for 11xxxxxx or 0xxxxxxx
	for($i=0; $i < $zbx_strlen; $i++){
		if(((ord($str[$i]) & $fbin) && (ord($str[$i]) & $sbin)) || !(ord($str[$i]) & $fbin)) $reallen++;
	}

return $reallen;
//*/
}

function zbx_strstr($haystack, $needle){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		$pos = mb_strpos($haystack, $needle);
		if($pos !== false){
			return mb_substr($haystack, $pos);
		}
		else return false;
	}
	else{
		return strstr($haystack, $needle);
	}
}

function zbx_stristr($haystack, $needle){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		$haystack_B = mb_strtoupper($haystack);
		$needle = mb_strtoupper($needle);

		$pos = mb_strpos($haystack_B, $needle);
		if($pos !== false){
			$pos = mb_substr($haystack, $pos);
		}
		return $pos;
	}
	else{
		return stristr($haystack, $needle);
	}
}

function zbx_substring($haystack, $start, $end=null){
	if(!is_null($end) && ($end < $start)) return '';

	if(defined('ZBX_MBSTRINGS_ENABLED')){
		if(is_null($end))
			$result = mb_substr($haystack, $start);
		else
			$result = mb_substr($haystack, $start, ($end - $start));
	}
	else{
		if(is_null($end))
			$result = substr($haystack, $start);
		else
			$result = substr($haystack, $start, ($end - $start));
	}

	return $result;
}

function zbx_substr($string, $start, $length=null){

	if(defined('ZBX_MBSTRINGS_ENABLED')){
		if(is_null($length))
			$result = mb_substr($string, $start);
		else
			$result = mb_substr($string, $start, $length);
	}
	else{
		if(is_null($length))
			$result = substr($string, $start);
		else
			$result = substr($string, $start, $length);
	}

	return $result;
}

function zbx_str_revert($str){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		$result = '';

		$stop = mb_strlen($str);
		for($idx=0; $idx<$stop; $idx++){
			$result = mb_substr($str, $idx, 1) . $result;
		}
	}
	else{
		$result = strrev($str);
	}

	return $result;
}

function zbx_strtoupper($str){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strtoupper($str);
	}
	else{
		return strtoupper($str);
	}
}

function zbx_strtolower($str){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strtolower($str);
	}
	else{
		return strtolower($str);
	}
}

function zbx_strpos($haystack, $needle, $offset=0){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strpos($haystack, $needle, $offset);
	}
	else{
		return strpos($haystack, $needle, $offset);
	}
}

function zbx_strrpos($haystack, $needle){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strrpos($haystack, $needle);
	}
	else{
		return strrpos($haystack, $needle);
	}
}

// }}} STRING FUNCTIONS


// {{{ ARRAY FUNCTIONS
/************* SELECT *************/
function selectByPattern(&$table, $column, $pattern, $limit){
	$chunk_size = $limit;

	$rsTable = array();
	foreach($table as $num => $row){
		if(zbx_strtoupper($row[$column]) == zbx_strtoupper($pattern))
			$rsTable = array($num=>$row) + $rsTable;
		else if($limit > 0)
			$rsTable[$num] = $row;
		else
			continue;

		$limit--;
	}

	if(!empty($rsTable)){
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

function asort_by_key(&$array, $key){
	if(!is_array($array)) {
		error(S_INCORRECT_TYPE_OF_ASORT_BY_KEY);
		return array();
	}

	$key = htmlspecialchars($key);
	uasort($array, create_function('$a,$b', 'return $a[\''.$key.'\'] - $b[\''.$key.'\'];'));
return $array;
}


/* function:
 *	zbx_rksort
 *
 * description:
 *	Recursively sort an array by key
 *
 * author: Eugene Grigorjev
 */
function zbx_rksort(&$array, $flags=NULL){
	if(is_array($array)){
		foreach($array as $id => $data)
			zbx_rksort($array[$id]);

		ksort($array,$flags);
	}
	return $array;
}


/**
 * Class for sorting array by multiple fields.
 * When PHP 5.3+ arraives to Zabbix should be changed to function with closure.
 */
class ArraySorter {
	protected static $fields;

	private function __construct() {}

	/**
	 * Sort array by multiple fields
	 * @static
	 * @param array $array array to sort passed by reference
	 * @param array $fields fields to sort, can be either string with field name or array with 'field' and 'order' keys
	 */
	public static function sort(array &$array, array $fields) {
		foreach ($fields as $fid => $field) {
			if (!is_array($field)) {
				$fields[$fid] = array('field' => $field, 'order' => ZBX_SORT_UP);
			}
		}
		self::$fields = $fields;

		uasort($array, array('self', 'compare'));
	}

	/**
	 * Method to be used as callback for uasort function in sort method.
	 *
	 * @static
	 * @param $a
	 * @param $b
	 * @return int
	 */
	protected static function compare($a, $b) {
		foreach (self::$fields as $field) {
			if (!(isset($a[$field['field']]) && isset($b[$field['field']]))) {
				return 0;
			}

			if ($a[$field['field']] != $b[$field['field']]) {
				if ($field['order'] == ZBX_SORT_UP) {
					return strnatcasecmp($a[$field['field']], $b[$field['field']]);
				}
				else {
					return strnatcasecmp($b[$field['field']], $a[$field['field']]);
				}
			}
		}

		return 0;
	}
}

function order_result(&$data, $sortfield=null, $sortorder=ZBX_SORT_UP){
	if(empty($data)) return false;

	if(is_null($sortfield)){
		natcasesort($data);
		if($sortorder != ZBX_SORT_UP)
			$data = array_reverse($data, true);
		return true;
	}

	$sort = array();
	foreach($data as $key => $arr){
		if(!isset($arr[$sortfield])) return false;
		$sort[$key] = $arr[$sortfield];
	}
	natcasesort($sort);

	if($sortorder != ZBX_SORT_UP)
		$sort = array_reverse($sort, true);

	$tmp = $data;
	$data = array();
	foreach($sort as $key => $val){
		$data[$key] = $tmp[$key];
	}

	return true;
}

function order_by($def, $allways='') {
	global $page;

	$orderString = '';

	$sortField = get_request('sort', CProfile::get('web.'.$page['file'].'.sort', null));
	$sortable = explode(',', $def);
	if (!str_in_array($sortField, $sortable)) {
		$sortField = null;
	}

	if ($sortField !== null) {
		$sortOrder = get_request('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));
		$orderString .= $sortField.' '.$sortOrder;
	}

	if (!empty($allways)) {
		$orderString .= ($sortField === null) ? '' : ',';
		$orderString .= $allways;
	}

	$orderString = empty($orderString) ? '' : ' ORDER BY '.$orderString;
	return $orderString;
}
/************* END SORT *************/

function zbx_implodeHash($glue1, $glue2, $hash){
	if(is_null($glue2)) $glue2 = $glue1;
	$str = '';

	foreach($hash as $key => $value){
		if(!empty($str)) $str.= $glue1;
		$str.= $key.$glue2.$value;
	}

return $str;
}

// This function will preserve keys!!!
// author: Aly
function zbx_array_merge(){
	$args = func_get_args();

	$result = array();
	foreach($args as &$array){
		if(!is_array($array)) return false;

		foreach($array as $key => $value){
			$result[$key] = $value;
		}
	}

return $result;
}

function uint_in_array($needle,$haystack){
	foreach($haystack as $id => $value)
		if(bccomp($needle,$value) == 0) return true;

return false;
}

function zbx_uint_array_intersect(&$array1, &$array2){
	$result = array();
	foreach($array1 as $key => $value)
		if(uint_in_array($value, $array2)) $result[$key] = $value;
return $result;
}

function str_in_array($needle,$haystack,$strict=false){
	if(is_array($needle)){
		return in_array($needle,$haystack,$strict);
	}
	else if($strict){
		foreach($haystack as $id => $value)
			if($needle === $value) return true;
	}
	else{
		foreach($haystack as $id => $value)
			if(strcmp($needle,$value) == 0) return true;
	}
return false;
}

function zbx_value2array(&$values){
	if(!is_array($values) && !is_null($values)){
		$tmp = array();

		if(is_object($values))
			$tmp[] = $values;
		else
			$tmp[$values] = $values;

		$values = $tmp;
	}
}

// fuunction: zbx_toHash
// object or array of objects to hash
// author: Aly
function zbx_toHash(&$value, $field=null){
	if(is_null($value)) return $value;
	$result = array();

	if(!is_array($value)){
		$result = array($value => $value);
	}
	else if(isset($value[$field])){
		$result[$value[$field]] = $value;
	}
	else{
		foreach($value as $key => $val){
			if(!is_array($val)){
				$result[$val] = $val;
			}
			else if(isset($val[$field])){
				$result[$val[$field]] = $val;
			}
		}
	}

return $result;
}

// fuunction: zbx_toObject
// Value or Array to Object or Array of objects
// author: Aly
function zbx_toObject(&$value, $field){
	if(is_null($value)) return $value;
	$result = array();

// Value or Array to Object or Array of objects
	if(!is_array($value)){
		$result = array(array($field => $value));
	}
	else if(!isset($value[$field])){
		foreach($value as $key => $val){
			if(!is_array($val)){
				$result[] = array($field => $val);
			}
		}
	}

return $result;
}

// function: zbx_toArray
// author: Aly
function zbx_toArray($value){
	if(is_null($value)) return $value;
	$result = array();

	if(!is_array($value)){
		$result = array($value);
	}
	else{
// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if(zbx_ctype_digit(key($value)))
			$result = array_values($value);
		else if(!empty($value))
			$result = array($value);
	}

return $result;
}

// fuunction: zbx_objectFields
// value OR object OR array of objects TO an array
// author: Aly
function zbx_objectValues(&$value, $field){
	if(is_null($value)) return $value;
	$result = array();

	if(!is_array($value)){
		$result = array($value);
	}
	else if(isset($value[$field])){
		$result = array($value[$field]);
	}
	else{
		foreach($value as $key => $val){
			if(!is_array($val)){
				$result[] = $val;
			}
			else if(isset($val[$field])){
				$result[] = $val[$field];
			}
		}
	}

return $result;
}

function zbx_cleanHashes(&$value){
	if(is_array($value)){
// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);
		if(zbx_ctype_digit(key($value)))
			$value = array_values($value);
	}

return $value;
}
// }}} ARRAY FUNCTION
function zbx_array_mintersect($keys, $array){
	$result = array();

	foreach($keys as $field){
		if(is_array($field)){
			foreach($field as $sub_field){
				if(isset($array[$sub_field])){
					$result[$sub_field] = $array[$sub_field];
					break;
				}
			}
		}
		else if(isset($array[$field])){
			$result[$field] = $array[$field];
		}
	}
	return $result;
}

function zbx_str2links($text){
// $value = preg_replace('#(https?|ftp|file)://[^\n\t\r ]+#u', '<a href="$0">$0</a>', $value);
	$result = array();
	if(empty($text)) return $result;

	preg_match_all('#https?://[^\n\t\r ]+#u', $text, $matches, PREG_OFFSET_CAPTURE);

	$start = 0;
	foreach($matches[0] as $match){
		$result[] = zbx_substr($text, $start, $match[1]-$start);
		$result[] = new CLink($match[0], $match[0], null, null, true);
		$start = $match[1] + zbx_strlen($match[0]);
	}

	$result[] = zbx_substr($text, $start, zbx_strlen($text));
	return $result;
}

function zbx_subarray_push(&$mainArray, $sIndex, $element = null) {
	if(!isset($mainArray[$sIndex])) $mainArray[$sIndex] = array();
	$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
}

/************* END ZBX MISC *************/

/*************** PAGE SORTING ******************/
	/* function:
	 *      validate_sort_and_sortorder
	 *
	 * description:
	 *      Checking,setting AND saving sort params
	 *
	 * author: Aly
	 */
	function validate_sort_and_sortorder($sort=NULL,$sortorder=ZBX_SORT_UP){
		global $page;

		$_REQUEST['sort'] = get_request('sort',CProfile::get('web.'.$page['file'].'.sort',$sort));
		$_REQUEST['sortorder'] = get_request('sortorder',CProfile::get('web.'.$page['file'].'.sortorder',$sortorder));

		if(!is_null($_REQUEST['sort'])){
			$_REQUEST['sort'] = preg_replace('/[^a-z\.\_]/i','',$_REQUEST['sort']);
			CProfile::update('web.'.$page['file'].'.sort', $_REQUEST['sort'], PROFILE_TYPE_STR);
		}

		if(!str_in_array($_REQUEST['sortorder'],array(ZBX_SORT_DOWN,ZBX_SORT_UP)))
			$_REQUEST['sortorder'] = ZBX_SORT_UP;

		CProfile::update('web.'.$page['file'].'.sortorder', $_REQUEST['sortorder'], PROFILE_TYPE_STR);
	}

/* function:
 *      make_sorting_header
 *
 * description:
 *      Creates header col for sorting in table header
 *
 * author: Aly
 */
	function make_sorting_header($obj,$tabfield,$url=''){
		global $page;

		$sortorder = (($_REQUEST['sort'] == $tabfield) && ($_REQUEST['sortorder'] == ZBX_SORT_UP))?ZBX_SORT_DOWN:ZBX_SORT_UP;

		$link = new Curl($url);
		if(empty($url)) $link->formatGetArguments();
		$link->setArgument('sort', $tabfield);
		$link->setArgument('sortorder', $sortorder);

		$url = $link->getUrl();

		if(($page['type'] != PAGE_TYPE_HTML) && defined('ZBX_PAGE_MAIN_HAT')){
			$script = "javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');";
		}
		else{
			$script = "javascript: redirect('".$url."');";
		}

		zbx_value2array($obj);
		$div = new CDiv();
		$div->setAttribute('style', 'float:left;');

		foreach($obj as $enum => $el){
			if(is_object($el) || ($el === SPACE)) $div->addItem($el);
			else $div->addItem(new CSpan($el, 'underline'));
		}
		$div->addItem(SPACE);

		$img = null;
		if(isset($_REQUEST['sort']) && ($tabfield == $_REQUEST['sort'])){
			if($sortorder == ZBX_SORT_UP) $img = new CDiv(SPACE,'icon_sortdown');
			else $img = new CDiv(SPACE,'icon_sortup');

			$img->setAttribute('style','float: left;');
		}

		$col = new CCol(array($div, $img), 'nowrap hover_grey');
		$col->setAttribute('onclick', $script);

	return $col;
	}

	function getPageSortField($def){
		global $page;
		$tabfield = get_request('sort',CProfile::get('web.'.$page['file'].'.sort',$def));

	return $tabfield;
	}

	function getPageSortOrder($def=ZBX_SORT_UP){
		global $page;
		$sortorder = get_request('sortorder',CProfile::get('web.'.$page['file'].'.sortorder',$def));

	return $sortorder;
	}
/*************** END PAGE SORTING ******************/

/************* PAGING *************/
function getPagingLine(&$items, $autotrim=true){
	global $USER_DETAILS, $page;
	$config = select_config();

	$search_limit = '';
	if($config['search_limit'] < count($items)){
		array_pop($items);
		$search_limit = '+';
	}

	$start = get_request('start', null);

	if(is_null($start)){
		$last_page = CProfile::get('web.paging.lastpage');
		$start = ($last_page == $page['file']) ? CProfile::get('web.paging.start', 0) : 0;
	}

	$rows_per_page = $USER_DETAILS['rows_per_page'];

	$cnt_items = count($items);
	$cnt_pages = ceil($cnt_items / $rows_per_page);

	if($cnt_items < $start) $start = 0;
	CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
	CProfile::update('web.paging.start', $start, PROFILE_TYPE_INT);

	if($cnt_pages < 1) $cnt_pages = 1;

	$crnt_page = floor($start / $rows_per_page) + 1;

	if($autotrim){
		$items = array_slice($items, $start, $rows_per_page, true);
	}

// Viewed pages (better to use not odd)
	$view_pages = 11;

	$endPage = $crnt_page + floor($view_pages/2);
	if($endPage < $view_pages) $endPage = $view_pages;
	if($endPage > $cnt_pages) $endPage = $cnt_pages;

	$startPage = ($endPage > $view_pages)?($endPage - $view_pages + 1):1;

// Page line
	$pageline = array();

	$table = BR();
	if($cnt_pages > 1){
		if($startPage > 1){
			$pagespan = new CSpan('<< '.S_FIRST_PAGE, 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage(0);');

			$pageline[] = $pagespan;
			$pageline[] = '&nbsp;&nbsp;';
		}

		if($crnt_page > 1){
			$pagespan = new CSpan('< '.S_PREVIOUS_PAGE, 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.(($crnt_page-2) * $rows_per_page).');');

			$pageline[] = $pagespan;
			$pageline[] = ' | ';
		}

		for($p=$startPage; $p <= $cnt_pages; $p++){
			if($p > $endPage)	break;

			if($p == $crnt_page){
				$pagespan = new CSpan($p, 'bold textcolorstyles');
			}
			else{
				$pagespan = new CSpan($p, 'darklink');
				$pagespan->setAttribute('onclick', 'javascript: openPage('.(($p-1) * $rows_per_page).');');
			}

			$pageline[] = $pagespan;
			$pageline[] = ' | ';
		}

		array_pop($pageline);

		if($crnt_page <  $cnt_pages){
			$pagespan = new CSpan(S_NEXT_PAGE.' >', 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.($crnt_page * $rows_per_page).');');

			$pageline[] = ' | ';
			$pageline[] = $pagespan;
		}

		if($p < $cnt_pages){
			$pagespan = new CSpan(S_LAST_PAGE.' >>', 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.(($cnt_pages-1) * $rows_per_page).');');

			$pageline[] = '&nbsp;&nbsp;';
			$pageline[] = $pagespan;
		}

		$table = new CTable(null, 'paging');
		$table ->addRow(new CCol($pageline));
	}
// Table view

	$view_from_page = ($crnt_page-1) * $rows_per_page + 1;

	$view_till_page = $crnt_page * $rows_per_page;
	if($view_till_page > $cnt_items) $view_till_page = $cnt_items;

	$page_view = array();
	$page_view[] = S_DISPLAYING.SPACE;
	if($cnt_items > 0){
		$page_view[] = new CSpan($view_from_page,'info');
		$page_view[] = SPACE.S_TO_SMALL.SPACE;
	}

	$page_view[] = new CSpan($view_till_page,'info');
	$page_view[] = SPACE.S_OF_SMALL.SPACE;
	$page_view[] = new CSpan($cnt_items,'info');
	$page_view[] = $search_limit;
	$page_view[] = SPACE.S_FOUND_SMALL;

	$page_view = new CSpan($page_view);

	zbx_add_post_js('insertInElement("numrows",'.zbx_jsvalue($page_view->toString()).',"div");');

return $table;
}

/************* DYNAMIC REFRESH *************/
function add_doll_objects($ref_tab, $pmid='mainpage'){
	$upd_script = array();
	foreach($ref_tab as $id => $doll){
		$upd_script[$doll['id']] = format_doll_init($doll);
	}

	zbx_add_post_js('initPMaster('.zbx_jsvalue($pmid).','.zbx_jsvalue($upd_script).');');
}

function format_doll_init($doll){
	global $USER_DETAILS;

	$args = array('frequency' => 60,
					'url' => '',
					'counter' => 0,
					'darken' => 0,
					'params' => array()
				);

	foreach($args as $key => $def){
		if(isset($doll[$key])) $obj[$key] = $doll[$key];
		else $obj[$key] = $def;
	}

	$obj['url'].= (zbx_empty($obj['url'])? '?':'&').'output=html';

	$obj['params']['favobj'] = 'hat';
	$obj['params']['favref'] = $doll['id'];
	$obj['params']['action'] = 'refresh';

return $obj;
}

function get_update_doll_script($pmasterid, $dollid, $key, $value=''){
	$script = 'PMasters['.zbx_jsvalue($pmasterid).'].dolls['.zbx_jsvalue($dollid).'].'.$key.'('.zbx_jsvalue($value).');';
return $script;
}

function make_refresh_menu($pmid,$dollid,$cur_interval,$params=null,&$menu,&$submenu, $menu_type=1){

	if($menu_type == 1){
		$intervals = array('10'=>10, '30'=>30, '60'=>60, '120'=>120, '600'=>600, '900'=>900);
		$title = S_REFRESH_TIME_IN_SECONDS;
	}
	else if($menu_type == 2){
		$intervals = array('x0.25'=>0.25, 'x0.5'=>0.5, 'x1'=>1, 'x1.5'=>1.5, 'x2'=>2, 'x3'=>3, 'x4'=>4, 'x5'=>5);
		$title = S_REFRESH_TIME_MULTIPLIER;
	}

	$menu['menu_'.$dollid][] = array($title, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));

	foreach($intervals as $key => $value){
		$menu['menu_'.$dollid][] = array(
			$key,
			'javascript: setRefreshRate('.zbx_jsvalue($pmid).','.zbx_jsvalue($dollid).','.$value.','.zbx_jsvalue($params).');'.
			'void(0);',
			null,
			array('outer' => ($value == $cur_interval)? 'pum_b_submenu':'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	}
	$submenu['menu_'.$dollid][] = array();
}

/************* END REFRESH *************/


/************* MATH *************/

function bcfloor($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number)
			$number = $tmp;
		else if ($number[0] != '-')
			$number = bcadd($number, 0, 0);
		else
			$number = bcsub($number, 1, 0);
	}
	return $number == '-0' ? '0' : $number;
}

function bcceil($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number)
			$number = $tmp;
		else if ($number[0] != '-')
			$number = bcadd($number, 1, 0);
		else
			$number = bcsub($number, 0, 0);
	}
	return $number == '-0' ? '0' : $number;
}

function bcround($number, $precision = 0) {
	if (strpos($number, '.') !== false) {
		if ($number[0] != '-')
			$number = bcadd($number, '0.' . str_repeat('0', $precision) . '5', $precision);
		else
			$number = bcsub($number, '0.' . str_repeat('0', $precision) . '5', $precision);
	}
	else if ($precision != 0) {
		$number .= '.' . str_repeat('0', $precision);
	}
	// According to bccomp(), '-0.0' does not equal '-0'. However, '0.0' and '0' are equal.
	$zero = ($number[0] != '-' ? bccomp($number, '0') == 0 : bccomp(substr($number, 1), '0') == 0);
	return $zero ? ($precision == 0 ? '0' : '0.' . str_repeat('0', $precision)) : $number;
}

/************* END MATH *************/

?>
