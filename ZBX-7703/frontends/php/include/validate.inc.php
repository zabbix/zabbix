<?php
/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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
	function unset_request($key,$requester='unknown'){
		unset($_GET[$key]);
		unset($_POST[$key]);
		unset($_REQUEST[$key]);
	}

	define('ZBX_VALID_OK',		0);
	define('ZBX_VALID_ERROR',	1);
	define('ZBX_VALID_WARNING',	2);

	function is_int_range($value){
		if( !empty($value) ) foreach(explode(',',$value) as $int_range){
			$int_range = explode('-', $int_range);
			if(count($int_range) > 2) return false;
			foreach($int_range as $int_val)
				if( !is_numeric($int_val) )
					return false;
		}
		return true;
	}

	function is_hex_color($value){
//		return eregi('^[0-9,A-F]{6}$', $value);
		return preg_match('/^([0-9,A-F]{6})$/i', $value);
	}

	function BETWEEN($min,$max,$var=NULL){
		return "({".$var."}>=".$min."&&{".$var."}<=".$max.")&&";
	}

	function REGEXP($regexp,$var=NULL){
		return "(preg_match(\"".$regexp."\", {".$var."}))&&";
	}

	function GT($value,$var=''){
		return "({".$var."}>=".$value.")&&";
	}

	function IN($array,$var=''){
		if(is_array($array)) $array = implode(',', $array);

		return "str_in_array({".$var."},array(".$array."))&&";
	}

	function HEX($var=NULL){
		return 'preg_match("/^([a-zA-Z0-9]+)$/",{'.$var.'})&&';
	}

	function KEY_PARAM($var=NULL){
		return 'preg_match("/'.ZBX_PREG_PARAMS.'/",{'.$var.'})&&';
	}

	function validate_ipv4($str,&$arr){
		if( !preg_match('/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/', $str, $arr) )	return false;
		for($i=1; $i<=4; $i++)	if( !is_numeric($arr[$i]) || $arr[$i] > 255 || $arr[$i] < 0 )	return false;
		return true;
	}

	function validate_ipv6($str,&$arr){
		$pattern1 = '([a-f0-9]{1,4}:){7}[a-f0-9]{1,4}';
		$pattern2 = ':(:[a-f0-9]{1,4}){1,7}';
		$pattern3 = '[a-f0-9]{1,4}::([a-f0-9]{1,4}:){0,5}[a-f0-9]{1,4}';
		$pattern4 = '([a-f0-9]{1,4}:){2}:([a-f0-9]{1,4}:){0,4}[a-f0-9]{1,4}';
		$pattern5 = '([a-f0-9]{1,4}:){3}:([a-f0-9]{1,4}:){0,3}[a-f0-9]{1,4}';
		$pattern6 = '([a-f0-9]{1,4}:){4}:([a-f0-9]{1,4}:){0,2}[a-f0-9]{1,4}';
		$pattern7 = '([a-f0-9]{1,4}:){5}:([a-f0-9]{1,4}:){0,1}[a-f0-9]{1,4}';
		$pattern8 = '([a-f0-9]{1,4}:){6}:[a-f0-9]{1,4}';
		$pattern9 = '([a-f0-9]{1,4}:){1,7}:';
		$pattern10 = '::';

		$full = "/^($pattern1)$|^($pattern2)$|^($pattern3)$|^($pattern4)$|^($pattern5)$|^($pattern6)$|^($pattern7)$|^($pattern8)$|^($pattern9)$|^($pattern10)$/i";

		if(!preg_match($full, $str)) return false;

	return true;
	}

	function validate_ip($str,&$arr){
		if(validate_ipv4($str,$arr)) return true;
		if(defined('ZBX_HAVE_IPV6')) return validate_ipv6($str,$arr);

	return false;
	}


	/**
	 * Validate IP mask. IP/bits.
	 * bits range for IPv4: 16 - 32
	 * bits range for IPv6: 112 - 128
	 *
	 * @param string $ip_range
	 *
	 * @return bool
	 */
	function validate_ip_range_mask($ip_range) {
		$parts = explode('/', $ip_range);

		if (count($parts) != 2) {
			return false;
		}
		$ip = $parts[0];
		$bits = $parts[1];

		if (validate_ipv4($ip, $arr)) {
			return preg_match('/^\d{1,2}$/', $bits) && $bits >= 16 && $bits <= 32;
		}
		elseif (defined('ZBX_HAVE_IPV6') && validate_ipv6($ip, $arr)) {
			return preg_match('/^\d{1,3}$/', $bits) && $bits >= 112 && $bits <= 128;
		}
		else {
			return false;
		}
	}

/*
 * Validate IP range. ***.***.***.***[-***]
 */
	function validate_ip_range_range($ip_range){
		$parts = explode('-', $ip_range);

		if( 2 < ($parts_count = count($parts)) )
			return false;

		if( validate_ipv4($parts[0], $arr) ){
			$ip_parts = explode('.', $parts[0]);

			if( $parts_count == 2 ){
//				if( !ereg('^[0-9]{1,3}$', $parts[1]) ) return false;
				if(!preg_match('/^([0-9]{1,3})$/', $parts[1])) return false;

				sscanf($ip_parts[3], "%d", $from_value);
				sscanf($parts[1], "%d", $to_value);

				if(($to_value > 255) || ($from_value > $to_value)) return false;
			}
		}
		else if( defined('ZBX_HAVE_IPV6') && validate_ipv6($parts[0], $arr) ){
			$ip_parts = explode(':', $parts[0]);
			$ip_parts_count = count($ip_parts);

			if( $parts_count == 2 ){
//				if(!ereg('^[A-Fa-f0-9]{1,4}$', $parts[1])) return false;
				if(!preg_match('/^([a-f0-9]{1,4})$/i', $parts[1])) return false;

				sscanf($ip_parts[$ip_parts_count - 1], "%x", $from_value);
				sscanf($parts[1], "%x", $to_value);

				if($from_value > $to_value) return false;
			}
		}
		else{
			return false;
		}

	return true;
	}

	function validate_ip_range($str){
		foreach(explode(',',$str) as $ip_range){
			if(false !== zbx_strpos($ip_range, '/') ) {
				if(false === validate_ip_range_mask($ip_range) )
					return false;
			}
			else{
				if(false === validate_ip_range_range($ip_range) )
					return false;
			}
		}

	return true;
	}

	function validate_port_list($str){
		foreach(explode(',',$str) as $port_range){
			$port_range = explode('-', $port_range);
			if(count($port_range) > 2) return false;

			foreach($port_range as $port){
				if( !is_numeric($port) || $port > 65535 || $port < 0 )
					return false;
			}
		}

	return true;
	}

	function validate_period(&$str){
		$str = trim($str,';');
		$out = "";
		$periods = explode(';',$str);
		foreach($periods as $period){
			// arr[idx]   1       2         3             4            5            6
//			if(!ereg('^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$', $period, $arr)) return false;
			if(!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $arr)) return false;

			if($arr[1] > $arr[2]) // check week day
				return false;
			if($arr[3] > 23 || $arr[3] < 0 || $arr[5] > 24 || $arr[5] < 0) // check hour
				return false;
			if($arr[4] > 59 || $arr[4] < 0 || $arr[6] > 59 || $arr[6] < 0) // check min
				return false;
			if(($arr[5]*100 + $arr[6]) > 2400) // check max time 24:00
				return false;
			if(($arr[3] * 100 + $arr[4]) >= ($arr[5] * 100 + $arr[6])) // check time period
				return false;

			$out .= sprintf('%d-%d,%02d:%02d-%02d:%02d',$arr[1],$arr[2],$arr[3],$arr[4],$arr[5],$arr[6]).';';
		}
		$str = $out;
// parse_period($str);
		return true;
	}

	define('NOT_EMPTY',"({}!='')&&");
	define('DB_ID',"({}>=0&&bccomp('{}',\"10000000000000000000\")<0)&&");
	define('NOT_ZERO',"({}!=0)&&");

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	function calc_exp2($fields,$field,$expression){
		foreach($fields as $f => $checks){
/*
	if an unset variable used in expression, return FALSE
	if(zbx_strstr($expression,'{'.$f.'}')&&!isset($_REQUEST[$f])){
		SDI("Variable [$f] is not set. $expression is FALSE");
		info('Variable ['.$f.'] is not set. '.$expression.' is FALSE');
	return FALSE;
	}
//*/
// echo $f,":",$expression,"<br>";
			$expression = str_replace('{'.$f.'}','$_REQUEST["'.$f.'"]',$expression);
// $debug .= $f." = ".$_REQUEST[$f].SBR;
		}

		$expression = trim($expression,'& ');
		$exec = 'return ('.$expression.')?1:0;';

		$ret = eval($exec);
//echo $debug;
//echo "$field - result: ".$ret." exec: $exec".SBR.SBR;
//SDI("$field - result: ".$ret." exec: $exec");
		return $ret;
	}

	function calc_exp($fields,$field,$expression){
//SDI("$field - expression: ".$expression);

		if(zbx_strstr($expression,'{}') && !isset($_REQUEST[$field]))
			return FALSE;

		if(zbx_strstr($expression,'{}') && !is_array($_REQUEST[$field]))
			$expression = str_replace('{}','$_REQUEST["'.$field.'"]',$expression);

		if(zbx_strstr($expression,'{}') && is_array($_REQUEST[$field])){
			foreach($_REQUEST[$field] as $key => $val){
//				if(!ereg('^[a-zA-Z0-9_]+$',$key)) return FALSE;
				if(!preg_match('/^([a-zA-Z0-9_]+)$/', $key)) return FALSE;

				$expression2 = str_replace('{}','$_REQUEST["'.$field.'"]["'.$key.'"]',$expression);
				if(calc_exp2($fields,$field,$expression2)==FALSE)
					return FALSE;
			}
			return TRUE;
		}

//SDI("$field - expression: ".$expression);
		return calc_exp2($fields,$field,$expression);
	}

	function unset_not_in_list(&$fields){
		foreach($_REQUEST as $key => $val){
			if(!isset($fields[$key])){
				unset_request($key,'unset_not_in_list');
			}
		}
	}

	function unset_if_zero($fields){
		foreach($fields as $field => $checks){
			list($type,$opt,$flags,$validation,$exception)=$checks;

			if(($flags&P_NZERO)&&(isset($_REQUEST[$field]))&&(is_numeric($_REQUEST[$field]))&&($_REQUEST[$field]==0)){
				unset_request($field,'unset_if_zero');
			}
		}
	}


	function unset_action_vars($fields){
		foreach($fields as $field => $checks){
			list($type,$opt,$flags,$validation,$exception)=$checks;

			if(($flags&P_ACT)&&(isset($_REQUEST[$field]))){
				unset_request($field,'unset_action_vars');
			}
		}
	}

	function unset_all(){
		foreach($_REQUEST as $key => $val){
			unset_request($key,'unset_all');
		}
	}

	function check_type(&$field, $flags, &$var, $type){
		if(is_array($var) && $type != T_ZBX_IP){
			$err = ZBX_VALID_OK;
			foreach($var as $el){
				$err |= check_type($field, $flags, $el, $type);
			}
			return $err;
		}

		if($type == T_ZBX_IP){
			if( !validate_ip($var,$arr) ){
				if($flags&P_SYS){
					info('Critical error. Field ['.$field.'] is not IP');
					return ZBX_VALID_ERROR;
				}
				else{
					info('Warning. Field ['.$field.'] is not IP');
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}

		if($type == T_ZBX_IP_RANGE){
			if( !validate_ip_range($var) ){
				if($flags&P_SYS){
					info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_IP_RANGE_SMALL);
					return ZBX_VALID_ERROR;
				}
				else{
					info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_IP_RANGE_SMALL);
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}

		if($type == T_ZBX_PORTS){
			$err = ZBX_VALID_OK;

			$type = ($flags&P_SYS)?ZBX_VALID_ERROR:ZBX_VALID_WARNING;
			foreach(explode(',', $var) as $el)
				foreach(explode('-', $el) as $p){
					$err |= check_type($field, $flags, $p, T_ZBX_INT);
					if(($p > 65535) || ($p < 0))  $err |= $type;
				}

			if($err == ZBX_VALID_ERROR)
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_PORT_RANGE_SMALL);
			else if($err == ZBX_VALID_WARNING)
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_PORT_RANGE_SMALL);

			return $err;
		}

		if($type == T_ZBX_INT_RANGE){
			if( !is_int_range($var) ){
				if($flags&P_SYS){
					info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_INTEGER_RANGE_SMALL);
					return ZBX_VALID_ERROR;
				}
				else{
					info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_INTEGER_RANGE_SMALL);
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}

		if(($type == T_ZBX_INT) && !zbx_is_int($var)){
			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_INTEGER_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_INTEGER_SMALL);
				return ZBX_VALID_WARNING;
			}
		}

		if(($type == T_ZBX_DBL) && !is_numeric($var)) {
			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_DOUBLE_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_DOUBLE_SMALL);
				return ZBX_VALID_WARNING;
			}
		}

		if(($type == T_ZBX_STR) && !is_string($var)) {
			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_STRING_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_STRING_SMALL);
				return ZBX_VALID_WARNING;
			}
		}
//*
		if(($type == T_ZBX_STR) && !defined('ZBX_ALLOW_UNICODE') && (zbx_strlen($var) != zbx_strlen($var))){
			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_CONTAINS_MULTIBYTE_CHARS_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.'] - '.S_MULTIBYTE_CHARS_ARE_RESTRICTED_SMALL);
				return ZBX_VALID_ERROR;
			}
		}
//*/
		if(($type == T_ZBX_CLR) && !is_hex_color($var)) {
			$var = 'FFFFFF';
			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_A_COLOUR_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$field.']'.SPACE.S_IS_NOT_A_COLOUR_SMALL);
				return ZBX_VALID_WARNING;
			}
		}
		return ZBX_VALID_OK;
	}

	function check_trim(&$var){
		if(is_string($var)) {
			$var = trim($var);
		}
		else if(is_array($var)){
			foreach($var as $key => $val){
				check_trim($var[$key]);
			}
		}
	}

	function check_field(&$fields, &$field, $checks){
		if(!isset($checks[5])) $checks[5] = $field;
		list($type,$opt,$flags,$validation,$exception,$caption)=$checks;

		if($flags&P_UNSET_EMPTY && isset($_REQUEST[$field]) && $_REQUEST[$field]==''){
			unset_request($field,'P_UNSET_EMPTY');
		}

		if($exception==NULL)
			$except=FALSE;
		else
			$except=calc_exp($fields,$field,$exception);

		if($opt == O_MAND &&	$except)	$opt = O_NO;
		else if($opt == O_OPT && $except)	$opt = O_MAND;
		else if($opt == O_NO && $except)	$opt = O_MAND;

		if($opt == O_MAND){
			if(!isset($_REQUEST[$field])){
				if($flags&P_SYS){
					info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$caption.']'.SPACE.S_IS_MANDATORY_SMALL);
					return ZBX_VALID_ERROR;
				}
				else{
					info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$caption.']'.SPACE.S_IS_MANDATORY_SMALL);
					return ZBX_VALID_WARNING;
				}
			}
		}
		else if($opt == O_NO){
			if(!isset($_REQUEST[$field]))
				return ZBX_VALID_OK;

			unset_request($field,'O_NO');

			if($flags&P_SYS){
				info(S_CRITICAL_ERROR.'.'.SPACE.S_FIELD.SPACE.'['.$caption.']'.SPACE.S_MUST_BE_MISSING_SMALL);
				return ZBX_VALID_ERROR;
			}
			else{
				info(S_WARNING.'.'.SPACE.S_FIELD.SPACE.'['.$caption.']'.SPACE.S_MUST_BE_MISSING_SMALL);
				return ZBX_VALID_WARNING;
			}
		}
		else if($opt == O_OPT){
			if(!isset($_REQUEST[$field])){
				return ZBX_VALID_OK;
			}
			else if($flags&P_ACT){
				if(!isset($_REQUEST['sid'])){
					info(S_OPERATION_CANNOT_PERFORMED_UNAUTH_REQUEST);
					return ZBX_VALID_ERROR;
				}
				else if(isset($_COOKIE['zbx_sessionid']) && ($_REQUEST['sid'] != substr($_COOKIE['zbx_sessionid'],16,16))){
					info(S_OPERATION_CANNOT_PERFORMED_UNAUTH_REQUEST);
					return ZBX_VALID_ERROR;
				}
			}
		}

		check_trim($_REQUEST[$field]);

		$err = check_type($field, $flags, $_REQUEST[$field], $type);

		if($err != ZBX_VALID_OK)
			return $err;

//sdi($field. '| exception ='.$exception.' | except ='.$except.' | validation= '.$validation);

		if(is_null($exception) || ($except == true)){

			if(!$validation)	$valid = TRUE;
			else			{	$valid = calc_exp($fields,$field,$validation);  }

			if(!$valid){

				if($flags&P_SYS){

					info(S_CRITICAL_ERROR.'.'.SPACE.S_INCORRECT_VALUE_FOR.SPACE.'['.$caption.'] = "'.$_REQUEST[$field].'"');
					return ZBX_VALID_ERROR;
				}
				else{
					info(S_WARNING.'.'.SPACE.S_INCORRECT_VALUE_FOR.SPACE.'['.$caption.']');
					return ZBX_VALID_WARNING;
				}
			}
		}

	return ZBX_VALID_OK;
	}

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$system_fields = array(
		'sid'=>				array(T_ZBX_STR, O_OPT,	P_SYS,	HEX(),		NULL),
//
		'switch_node'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'triggers_hash'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	NOT_EMPTY,	NULL),
		'print'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('1'),	NULL),

// paging
		'start'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	NULL,		NULL),

// table sorting
		'sort'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,		NULL),
		'sortorder'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,		NULL)
	);

	function invalid_url($msg=S_INVALID_URL_PARAMS){
		include_once('include/page_header.php');
		unset_all();
		show_error_message($msg);
		include_once('include/page_footer.php');
	}

	function check_fields(&$fields, $show_messages=true){
		global	$system_fields;

		$err = ZBX_VALID_OK;

		$fields = zbx_array_merge($system_fields, $fields);

		foreach($fields as $field => $checks){
			$err |= check_field($fields, $field,$checks);
		}

		unset_not_in_list($fields);
		unset_if_zero($fields);
		if($err!=ZBX_VALID_OK){
			unset_action_vars($fields);
		}

		$fields = null;
		if($err&ZBX_VALID_ERROR){
			invalid_url();
		}

		if($show_messages && ($err!=ZBX_VALID_OK)){
			show_messages($err==ZBX_VALID_OK, NULL, S_PAGE_RECEIVED_INCORRECT_DATA);
		}

	return ($err==ZBX_VALID_OK ? 1 : 0);
	}

	/*
	* Validate URL against XSS
	*/
	function validateUrl($url) {
		if (stripos($url, "javascript:") !== false) {
			return false;
		}
		// for IE
		if (stripos($url, "vbscript:") !== false) {
			return false;
		}
		// for Netscape
		if (stripos($url, "livescript:") !== false) {
			return false;
		}
		return true;
	}
?>
