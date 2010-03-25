<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

/************* PAGING *************/
function getPagingLine(&$items, $autotrim=true){
	global $USER_DETAILS;
	$config = select_config();

	$search_limit = '';
	if($config['search_limit'] < count($items)){
		array_pop($items);
		$search_limit = '+';
	}

	$start = get_request('start',0);
	$rows_per_page = $USER_DETAILS['rows_per_page'];

	$cnt_items = count($items);
	$cnt_pages = ceil($cnt_items / $rows_per_page);

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
			$page = new CSpan('<< '.S_FIRST, 'darklink');
			$page->setAttribute('onclick', 'javascript: openPage(0);');

			$pageline[] = $page;
			$pageline[] = '&nbsp;&nbsp;';
		}

		if($crnt_page > 1){
			$page = new CSpan('< '.S_PREVIOUS, 'darklink');
			$page->setAttribute('onclick', 'javascript: openPage('.(($crnt_page-2) * $rows_per_page).');');

			$pageline[] = $page;
			$pageline[] = ' | ';
		}

		for($p=$startPage; $p <= $cnt_pages; $p++){
			if($p > $endPage)	break;

			if($p == $crnt_page){
				$page = new CSpan($p, 'bold textcolorstyles');
			}
			else{
				$page = new CSpan($p, 'darklink');
				$page->setAttribute('onclick', 'javascript: openPage('.(($p-1) * $rows_per_page).');');
			}

			$pageline[] = $page;
			$pageline[] = ' | ';
		}

		array_pop($pageline);

		if($crnt_page <  $cnt_pages){
			$page = new CSpan(S_NEXT.' >', 'darklink');
			$page->setAttribute('onclick', 'javascript: openPage('.($crnt_page * $rows_per_page).');');

			$pageline[] = ' | ';
			$pageline[] = $page;
		}

		if($p < $cnt_pages){
			$page = new CSpan(S_LAST.' >>', 'darklink');
			$page->setAttribute('onclick', 'javascript: openPage('.(($cnt_pages-1) * $rows_per_page).');');

			$pageline[] = '&nbsp;&nbsp;';
			$pageline[] = $page;
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

	$page_view = new CJSscript($page_view);

	zbx_add_post_js('insert_in_element("numrows",'.zbx_jsvalue($page_view->toString()).');');

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

	$obj['url'].= (zbx_empty($obj['url'])?'?':'&').'output=html';

	$obj['params']['favobj'] = 'refresh';
	$obj['params']['favid'] = $doll['id'];

return $obj;
}

function get_update_doll_script($pmasterid, $dollid, $key, $value=''){
	$script = 'PMasters['.zbx_jsvalue($pmasterid).'].dolls['.zbx_jsvalue($dollid).'].'.$key.'('.zbx_jsvalue($value).');';
return $script;
}

function make_refresh_menu($pmid,$dollid,$cur_interval,$params=null,&$menu,&$submenu){

	$menu['menu_'.$dollid][] = array(S_REFRESH, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$intervals = array('10','30','60','120','600','900');

	foreach($intervals as $key => $value){
		$menu['menu_'.$dollid][] = array(
					S_EVERY.SPACE.$value.SPACE.S_SECONDS_SMALL,
					'javascript: setRefreshRate('.zbx_jsvalue($pmid).','.zbx_jsvalue($dollid).','.$value.','.zbx_jsvalue($params).');'.
					'void(0);',
					null,
					array('outer' => ($value == $cur_interval)?'pum_b_submenu':'pum_o_submenu', 'inner'=>array('pum_i_submenu')
			));
	}
	$submenu['menu_'.$dollid][] = array();
}

/************* END REFRESH *************/

/************ REQUEST ************/
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
/* function:
 *	get_cookie
 *
 * description:
 *	return cookie value by name,
 *	if cookie is not present return $default_value.
 *
 * author: Eugene Grigorjev
 */
function get_cookie($name, $default_value=null){
	if(isset($_COOKIE[$name]))	return $_COOKIE[$name];
	// else
	return $default_value;
}

/* function:
 *	zbx_setcookie
 *
 * description:
 *	set cookies.
 *
 * author: Eugene Grigorjev
 */
function zbx_setcookie($name, $value, $time=null){
	setcookie($name, $value, isset($time) ? $time : (0));
	$_COOKIE[$name] = $value;
}

/* function:
 *	zbx_unsetcookie
 *
 * description:
 *	unset and clear cookies.
 *
 * author: Aly
 */
function zbx_unsetcookie($name){
	zbx_setcookie($name, null, -99999);
	unset($_COOKIE[$name]);
}

/* function:
 *     zbx_flush_post_cookies
 *
 * description:
 *     set posted cookies.
 *
 * author: Eugene Grigorjev
 */
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

/* function:
 *	zbx_set_post_cookie
 *
 * description:
 *	set cookies after authorisation.
 *	require calling 'zbx_flush_post_cookies' function
 *	Called from:
 *	   a) in 'include/page_header.php'
 *	   b) from 'redirect()'
 *
 * author: Eugene Grigorjev
 */
function zbx_set_post_cookie($name, $value, $time=null){
	global $ZBX_PAGE_COOKIES;

	$ZBX_PAGE_COOKIES[] = array($name, $value, isset($time)?$time:0);
}

/************ END COOKIES ************/

/************* DATE *************/
/* function:
 *	zbx_date2str
 *
 * description:
 *	Convert timestamp to string representation. Retun 'Never' if 0.
 *
 * author: Alexei Vladishev
 */
function zbx_date2str($format, $timestamp){
	return ($timestamp==0)?S_NEVER:date($format,$timestamp);
}

/* function:
 *	zbx_date2age
 *
 * description:
 *	Calculate and convert timestamp to string representation.
 *
 * author: Aly
 */
function zbx_date2age($start_date,$end_date=0,$utime = false){

	if(!$utime){
		$start_date=date('U',$start_date);
		if($end_date)
			$end_date=date('U',$end_date);
		else
			$end_date = time();
	}

	$original_time = $time = abs($end_date-$start_date);
//SDI($start_date.' - '.$end_date.' = '.$time);

	$years = (int) ($time / (365*86400));
	$time -= $years*365*86400;

	$months = 0;
	$months = (int ) ($time / (30*86400));
	$time -= $months*30*86400;

	$weeks = (int ) ($time / (7*86400));
	$time -= $weeks*7*86400;

	$days = (int) ($time / 86400);
	$time -= $days*86400;

	$hours = (int) ($time / 3600);
	$time -= $hours*3600;

	$minutes = (int) ($time / 60);
	$time -= $minutes*60;

	if($time >= 1){
		$seconds = round($time,2);
		$ms = 0;
	}
	else{
		$seconds = 0;
		$ms = round($time,3) * 1000;
	}

	$str =  (($years)?$years.S_YEAR_SHORT.' ':'').
			(($months)?$months.S_MONTH_SHORT.' ':'').
			(($weeks)?$weeks.S_WEEK_SHORT.' ':'').
			(($days && !$years)?$days.S_DAY_SHORT.' ':'').
			(($hours && !$years && !$months)?$hours.S_HOUR_SHORT.' ':'').
			(($minutes && !$years && !$months && !$weeks)?$minutes.S_MINUTE_SHORT.' ':'').
			((!$years && !$months && !$weeks && !$days && ($ms || $seconds))?$seconds.S_SECOND_SHORT.' ':'').
			(($ms && !$years && !$months && !$weeks && !$days && !$hours)?$ms.S_MILLISECOND_SHORT:'').
         (!$ms && $original_time < 0.001 ? '< 1'.S_MILLISECOND_SHORT:'');

return trim($str,' ');
}

function getmicrotime(){
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}

function getDateStringByType($type, $timestamp){
	$str = S_WRONG_TYPE;
	switch($type){
		case TIMEPERIOD_TYPE_HOURLY:
			$str = date('H:i', $timestamp);
			break;
		case TIMEPERIOD_TYPE_DAILY:
			$str = date('D H:i', $timestamp);
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			$str = S_WEEK.' '.date('W', $timestamp);
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			$str = date('M', $timestamp);
			break;
		case TIMEPERIOD_TYPE_YEARLY:
			$str = date('Y', $timestamp);
			break;
	}
return $str;
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
	if($color[0] == '#')
		$color = substr($color, 1);

	if(zbx_strlen($color) == 6)
		list($r, $g, $b) = array($color[0].$color[1],
								 $color[2].$color[3],
								 $color[4].$color[5]);
	else if(zbx_strlen($color) == 3)
		list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
	else
		return false;

	$r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

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
		$bit = ($sbin & $num)?'1':'0';
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

// convert:
function convert_units($value, $units, $convert=ITEM_CONVERT_WITH_UNITS){
// Special processing for unix timestamps
	if($units=='unixtime'){
		$ret=date('Y.m.d H:i:s',$value);
		return $ret;
	}
//Special processing of uptime
	if($units=='uptime'){
		$ret='';
		$days=floor($value/(24*3600));
		if($days>0){
			$value=$value-$days*(24*3600);
		}
		$hours=floor($value/(3600));
		if($hours>0){
			$value=$value-$hours*3600;
		}
		$min=floor($value/(60));
		if($min>0){
			$value=$value-$min*(60);
		}
		if($days==0){
			$ret = sprintf("%02d:%02d:%02d", $hours, $min, $value);
		}
		else{
			$ret = sprintf("%d ".S_DAYS_SMALL.", %02d:%02d:%02d", $days, $hours, $min, $value);
		}
		return $ret;
	}
// Special processing for seconds
	if($units=='s'){
		return zbx_date2age(0,$value,true);
	}

	$u='';

// Any other unit
//-------------------

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

	if(zbx_empty($units) && ($convert == ITEM_CONVERT_WITH_UNITS)){
		if(round($value,2) == round($value,0)) $format = '%.0f %s';
		else $format = '%.2f %s';

		return sprintf($format, $value, $units);
	}

// INIT intervals
	static $digitUnits;
	if(is_null($digitUnits)) $digitUnits = array();

	if(!isset($digitUnits[$step])){
		$digitUnits[$step] = array(
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
			$digitUnits[$step][$dunit]['value'] = bcpow($step, $data['pow']);
		}
	}
//---
	if($value < 0) $abs = bcmul($value, '-1');
	else $abs = $value;

	$valUnit = array('pow'=>0, 'short'=>'', 'long'=>'', 'value'=>$value);
	if($abs >= $step){	
		foreach($digitUnits[$step] as $dnum => $data){
			if(bccomp($abs, $data['value']) > -1) $valUnit = $data;
			else break;
		}

		$valUnit['value'] = bcdiv($value, $valUnit['value'], 4);
	}

//------
	if(round($valUnit['value'],2) == round($valUnit['value'],0)) $format = '%.0f %s%s';
	else $format = '%.2f %s%s';

	switch($convert){
		case 0: $units = trim($units); 
		case 1: $desc = $valUnit['short']; break;
		case 2: $desc = $valUnit['long']; break;
	}

return sprintf($format, $valUnit['value'], $desc, $units);
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
	return preg_match('/^\\d+$/',$x);
}

function zbx_numeric($value){
	if(is_array($value)) return false;
	if(zbx_empty($value)) return false;

	$value = strval($value);

return preg_match('/^[-|+]?\\d+$/',$value);
}

function zbx_empty($value){
	if(is_null($value)) return true;
	if(is_array($value) && empty($value)) return true;
	if(is_string($value) && ($value === '')) return true;
return false;
}


// STRING FUNCTIONS {{{

function zbx_nl2br(&$str){
	$str_res = array();
	$str_arr = explode("\n",$str);
	foreach($str_arr as $id => $str_line){
		array_push($str_res,$str_line,BR());
	}
return $str_res;
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

function zbx_strpos($haystack, $needle){
	if(defined('ZBX_MBSTRINGS_ENABLED')){
		return mb_strpos($haystack, $needle);
	}
	else{
		return strpos($haystack, $needle);
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


// {{{ ARRAY UNCTIONS
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
//TODO: REMOVE
	if(!empty($haystack) && !is_numeric(key($haystack))){
//		info('uint_in_array: possible pasted associated array');
	}
//----

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
	else if(zbx_ctype_digit(key($value))){
		$result = array_values($value);
	}
	else if(!empty($value)){
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
	if(is_array($value) && ctype_digit((string) key($value))){
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

/************* END ZBX MISC *************/
?>
