<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
 *      get_cookie
 *
 * description:
 *      return cookie value by name,
 *      if cookie is not present return $default_value.
 *
 * author: Eugene Grigorjev
 */
function get_cookie($name, $default_value=null){
	if(isset($_COOKIE[$name]))	return $_COOKIE[$name];
	// else
	return $default_value;
}

/* function:
 *      zbx_setcookie
 *
 * description:
 *      set cookies.
 *
 * author: Eugene Grigorjev
 */
function zbx_setcookie($name, $value, $time=null){
	setcookie($name, $value, isset($time) ? $time : (0));
	$_COOKIE[$name] = $value;
}

/* function:
 *      zbx_unsetcookie
 *
 * description:
 *      unset and clear cookies.
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
 *      zbx_set_post_cookie
 *
 * description:
 *      set cookies after authorisation.
 *      require calling 'zbx_flush_post_cookies' function
 *	Called from:
 *         a) in 'include/page_header.php'
 *         b) from 'redirect()'
 *
 * author: Eugene Grigorjev
 */
function zbx_set_post_cookie($name, $value, $time=null){
	global $ZBX_PAGE_COOKIES;

	$ZBX_PAGE_COOKIES[] = array($name, $value, isset($time) ? $time : (0));
}

/************ END COOKIES ************/

/************* DATE *************/
/* function:
 *      zbx_date2str
 *
 * description:
 *      Convert timestamp to string representation. Retun 'Never' if 0.
 *
 * author: Alexei Vladishev
 */
function zbx_date2str($format, $timestamp){
	return ($timestamp==0)?S_NEVER:date($format,$timestamp);
}

/* function:
 *      zbx_date2age
 *
 * description:
 *      Calculate and convert timestamp to string representation. 
 *
 * author: Aly
 */
function zbx_date2age($start_date,$end_date=0){

	$start_date=date('U',$start_date);
	if($end_date)
		$end_date=date('U',$end_date);
	else
		$end_date = time();

	$time = abs($end_date-$start_date);
	
//SDI($start_date.' - '.$end_date.' = '.$time);
	
	$days = (int) ($time / 86400);
	$hours = (int) (($time - $days*86400) / 3600);
	$minutes = (int) ((($time - $days*86400) - ($hours*3600)) / 60);
	$str = (($days)?$days.'d ':'').(($hours)?$hours.'h ':'').$minutes.'m';
return $str;
}

function getmicrotime(){
	list($usec, $sec) = explode(" ",microtime()); 
	return ((float)$usec + (float)$sec); 
}

/************* END DATE *************/


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
		error('Incorrect type of asort_by_key');
		return array();
	}
	
	$key = htmlspecialchars($key);
	uasort($array, create_function('$a,$b', 'return $a[\''.$key.'\'] - $b[\''.$key.'\'];'));
return $array;
}


/* function:
 *      zbx_rksort
 *
 * description:
 *      Recursively sort an array by key
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

/************* ZBX MISC *************/
function zbx_numeric($value){
	if(is_array($value)) return false;
	
	$value = strval($value);
return ctype_digit($value);
}

function zbx_empty($value){
	if(is_null($value)) return true;		
	if(is_array($value) && empty($value)) return true;
	if(is_string($value) && ($value === '')) return true;
return false;
}
	
function zbx_strlen(&$str){
	if(!$strlen = strlen($str)) return $strlen;
	
	$reallen = 0;
	$fbin= 1 << 7;
	$sbin= 1 << 6;

// check first byte for 11xxxxxx or 0xxxxxxx
	for($i=0; $i < $strlen; $i++){
		if(((ord($str[$i]) & $fbin) && (ord($str[$i]) & $sbin)) || !(ord($str[$i]) & $fbin)) $reallen++;
	}
return $reallen;
}

function zbx_strstr($haystack,$needle){
	$pos = strpos($haystack,$needle);
	if($pos !== FALSE){
		$pos = substr($haystack,$pos);
	}
return $pos;
}

function zbx_stristr($haystack,$needle){
	$haystack_B = strtoupper($haystack);
	$needle = strtoupper($needle);
	
	$pos = strpos($haystack_B,$needle);
	if($pos !== FALSE){
		$pos = substr($haystack,$pos);
	}
return $pos;
}

function uint_in_array($needle,$haystack){
	foreach($haystack as $id => $value)
		if(bccomp($needle,$value) == 0) return true;
return false;
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

function zbx_stripslashes($value){
	if(is_array($value)){
		foreach($value as $id => $data)
			$value[$id] = zbx_stripslashes($data); 
			// $value = array_map('zbx_stripslashes',$value); /* don't use 'array_map' it buggy with indexes */
	} elseif (is_string($value)){
		$value = stripslashes($value);
	}
	return $value;
}
?>