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
	function unset_request($key,$requester='unknown')
	{
		unset($_REQUEST[$key]);
	}

	define('ZBX_VALID_OK',		0);
	define('ZBX_VALID_ERROR',	1);
	define('ZBX_VALID_WARNING',	2);

	function	is_int_range($value)
	{
		if( !empty($value) ) foreach(explode(',',$value) as $int_range)
		{
			$int_range = explode('-', $int_range);
			if(count($int_range) > 2) return false;
			foreach($int_range as $int_val)
				if( !is_numeric($int_val) )
					return false;
		}
		return true;
	}
	
	function	is_hex_color($value)
	{
		return eregi('^[0-9,A-F]{6}$', $value);
	}
	
	function	BETWEEN($min,$max,$var=NULL)
	{
		return "({".$var."}>=".$min."&&{".$var."}<=".$max.")&&";
	}

	function	GT($value,$var='')
	{
		return "({".$var."}>=".$value.")&&";
	}

	function	IN($array,$var='')
	{
		if(is_array($array)) $array = implode(',', $array);

		return "in_array({".$var."},array(".$array."))&&";
	}
	function	HEX($var=NULL)
	{
		return "ereg(\"^[a-zA-Z0-9]{1,}$\",{".$var."})&&";
	}
	function	KEY_PARAM($var=NULL)
	{
		return 'ereg(\'^([0-9a-zA-Z\_\.[.-.]\$ ]+)$\',{'.$var.'})&&';
	}
	function	validate_ipv4($str,&$arr)
	{
		if( !ereg('^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$', $str, $arr) )	return false;
		for($i=1; $i<=4; $i++)	if( !is_numeric($arr[$i]) || $arr[$i] > 255 || $arr[$i] < 0 )	return false;
		return true;
	}
	function	validate_ipv6($str,&$arr)
	{
		$pattern1 = '([A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}';
		$pattern2 = ':(:[A-Fa-f0-9]{1,4}){1,7}';
		$pattern3 = '[A-Fa-f0-9]{1,4}::([A-Fa-f0-9]{1,4}:){0,5}[A-Fa-f0-9]{1,4}';
		$pattern4 = '([A-Fa-f0-9]{1,4}:){2}:([A-Fa-f0-9]{1,4}:){0,4}[A-Fa-f0-9]{1,4}';
		$pattern5 = '([A-Fa-f0-9]{1,4}:){3}:([A-Fa-f0-9]{1,4}:){0,3}[A-Fa-f0-9]{1,4}';
		$pattern6 = '([A-Fa-f0-9]{1,4}:){4}:([A-Fa-f0-9]{1,4}:){0,2}[A-Fa-f0-9]{1,4}';
		$pattern7 = '([A-Fa-f0-9]{1,4}:){5}:([A-Fa-f0-9]{1,4}:){0,1}[A-Fa-f0-9]{1,4}';
		$pattern8 = '([A-Fa-f0-9]{1,4}:){6}:[A-Fa-f0-9]{1,4}';

		$full = "/^($pattern1)$|^($pattern2)$|^($pattern3)$|^($pattern4)$|^($pattern5)$|^($pattern6)$|^($pattern7)$|^($pattern8)$/";

		if( !ereg($full, $str, $arr) )	return false;
		return true;
	}

	function	validate_ip($str,&$arr)
	{
		if(validate_ipv4($str,$arr))
			return true;
		if(defined('ZBX_HAVE_IPV6'))
		{
			return validate_ipv6($str,$arr);
		}
		return false;
	}

/*	function	validate_ip_range($str)
	{
		foreach(explode(',',$str) as $ip_range)
		{
			$ip_parts = explode('.', $ip_range);
			if(count($ip_parts) != 4) return false;

			if( !is_numeric($ip_parts[0]) || $ip_parts[0] < 0 || $ip_parts[0] > 255 ) return false;
			if( !is_numeric($ip_parts[1]) || $ip_parts[1] < 0 || $ip_parts[1] > 255 ) return false;
			if( !is_numeric($ip_parts[2]) || $ip_parts[2] < 0 || $ip_parts[2] > 255 ) return false;

			$last_part = explode('-', $ip_parts[3]);
			if(count($last_part) > 2) return false;
			foreach($last_part as $ip_p)
			{
				if( !is_numeric($ip_p) || $ip_p < 0 || $ip_p > 255 ) return false;
			}
			if(count($last_part) == 2 && $last_part[0] > $last_part[1]) return false;

		}
		return true;
	}
*/
	function	validate_ip_range($str)
	{
		foreach(explode(',',$str) as $ip_range)
		{
			$parts = explode('-', $ip_range);
			$parts_count = count($parts);
			if($parts_count > 2) return false;

			if(validate_ipv4($parts[0], $arr))
			{
				$ip_parts = explode('.', $parts[0]);

				if( $parts_count == 2 )
				{
					if( !ereg('^[0-9]{1,3}$', $parts[1]) ) return false;

					sscanf($ip_parts[3], "%d", $from_value);
					sscanf($parts[1], "%d", $to_value);
					if($to_value > 255 || $from_value > $to_value) return false;
				}
			}
			else if( defined('ZBX_HAVE_IPV6') && validate_ipv6($parts[0], $arr) )
			{
				$ip_parts = explode(':', $parts[0]);
				$ip_parts_count = count($ip_parts);

				if( $parts_count == 2 )
				{
					if( !ereg('^[A-Fa-f0-9]{1,4}$', $parts[1]) ) return false;

					sscanf($ip_parts[$ip_parts_count - 1], "%x", $from_value);
					sscanf($parts[1], "%x", $to_value);
					if($from_value > $to_value) return false;
				}
			}
			else
			{
				return false;
			}

		}
		return true;
	}

/*	function	validate_ip_range($str)
	{
		if(defined('ZBX_HAVE_IPV6'))
		{
			return validate_ipv4_ipv6_range($str);
		}
		else
		{
			return validate_ipv4_range($str);
		}
		return false;
	}
*/
	function	validate_port_list($str)
	{
		foreach(explode(',',$str) as $port_range)
		{
			$port_range = explode('-', $port_range);
			if(count($port_range) > 2) return false;
			foreach($port_range as $port)
				if( !is_numeric($port) || $port > 65535 || $port < 0 )
					return false;
		}
		return true;
	}


	define("NOT_EMPTY","({}!='')&&");
	define("DB_ID","({}>=0&&bccomp('{}',\"10000000000000000000\")<0)&&");

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	function	calc_exp2($fields,$field,$expression)
	{
		foreach($fields as $f => $checks)
		{
/*
			// If an unset variable used in expression, return FALSE
			if(strstr($expression,'{'.$f.'}')&&!isset($_REQUEST[$f]))
			{
//SDI("Variable [$f] is not set. $expression is FALSE");
//info("Variable [$f] is not set. $expression is FALSE");
//				return FALSE;
			}
//*/
//echo $f,":",$expression,"<br>";
			$expression = str_replace('{'.$f.'}','$_REQUEST["'.$f.'"]',$expression);
//$debug .= $f." = ".$_REQUEST[$f].BR;
		}
		$expression = trim($expression,"& ");
		$exec = "return (".$expression.") ? 1 : 0;";

		$ret = eval($exec);
//echo $debug;
//echo "$field - result: ".$ret." exec: $exec".BR.BR;
//SDI("$field - result: ".$ret." exec: $exec");
		return $ret;
	}

	function	calc_exp($fields,$field,$expression)
	{
		global $_REQUEST;

//SDI("$field - expression: ".$expression);

		if(strstr($expression,"{}") && !isset($_REQUEST[$field]))
			return FALSE;

		if(strstr($expression,"{}") && !is_array($_REQUEST[$field]))
			$expression = str_replace("{}",'$_REQUEST["'.$field.'"]',$expression);

		if(strstr($expression,"{}") && is_array($_REQUEST[$field]))
		{
			foreach($_REQUEST[$field] as $key => $val)
			{
				$expression2 = str_replace("{}",'$_REQUEST["'.$field.'"]["'.$key.'"]',$expression);
				if(calc_exp2($fields,$field,$expression2)==FALSE)
					return FALSE;
			}	
			return TRUE;
		}
//SDI("$field - expression: ".$expression);
		return calc_exp2($fields,$field,$expression);
	}

	function	unset_not_in_list(&$fields)
	{
		foreach($_REQUEST as $key => $val)
		{
			if(!isset($fields[$key]))
			{
				unset_request($key,'unset_not_in_list');
			}
		}
	}

	function	unset_if_zero($fields)
	{
		foreach($fields as $field => $checks)
		{
			list($type,$opt,$flags,$validation,$exception)=$checks;

			if(($flags&P_NZERO)&&(isset($_REQUEST[$field]))&&(is_numeric($_REQUEST[$field]))&&($_REQUEST[$field]==0))
			{
				unset_request($field,'unset_if_zero');
			}
		}
	}


	function	unset_action_vars($fields)
	{
		foreach($fields as $field => $checks)
		{
			list($type,$opt,$flags,$validation,$exception)=$checks;
			
			if(($flags&P_ACT)&&(isset($_REQUEST[$field])))
			{
				unset_request($field,'unset_action_vars');
			}
		}
	}

	function	unset_all()
	{
		foreach($_REQUEST as $key => $val)
		{
			unset_request($key,'unset_all');
		}
	}

	function 	check_type(&$field, $flags, &$var, $type)
	{
		if(is_array($var) && $type != T_ZBX_IP)
		{
			$err = ZBX_VALID_OK;
			foreach($var as $el)
			{
				$err |= check_type($field, $flags, $el, $type);
			}
			return $err;
		}
 
		if($type == T_ZBX_IP)
		{
			if( !validate_ip($var,$arr) )
			{
				if($flags&P_SYS)
				{
					info("Critical error. Field [".$field."] is not IP");
					return ZBX_VALID_ERROR;
				}
				else
				{
					info("Warning. Field [".$field."] is not IP");
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}

		if($type == T_ZBX_IP_RANGE)
		{
			if( !validate_ip_range($var) )
			{
				if($flags&P_SYS)
				{
					info("Critical error. Field [".$field."] is not IP range");
					return ZBX_VALID_ERROR;
				}
				else
				{
					info("Warning. Field [".$field."] is not IP range");
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}

		if($type == T_ZBX_PORTS)
		{
			$err = ZBX_VALID_OK;
			foreach(explode(',', $var) as $el)
				foreach(explode('-', $el) as $p)
					$err |= check_type($field, $flags, $p, T_ZBX_INT);
			return $err;
		}

		if($type == T_ZBX_INT_RANGE)
		{
			if( !is_int_range($var) )
			{
				if($flags&P_SYS)
				{
					info("Critical error. Field [".$field."] is not integer range");
					return ZBX_VALID_ERROR;
				}
				else
				{
					info("Warning. Field [".$field."] is not integer range");
					return ZBX_VALID_WARNING;
				}
			}
			return ZBX_VALID_OK;
		}
		
		if(($type == T_ZBX_INT) && !is_numeric($var)) {
			if($flags&P_SYS)
			{
				info("Critical error. Field [".$field."] is not integer");
				return ZBX_VALID_ERROR;
			}
			else
			{
				info("Warning. Field [".$field."] is not integer");
				return ZBX_VALID_WARNING;
			}
		}

		if(($type == T_ZBX_DBL) && !is_numeric($var)) {
			if($flags&P_SYS)
			{
				info("Critical error. Field [".$field."] is not double");
				return ZBX_VALID_ERROR;
			}
			else
			{
				info("Warning. Field [".$field."] is not double");
				return ZBX_VALID_WARNING;
			}
		}

		if(($type == T_ZBX_STR) && !is_string($var)) {
			if($flags&P_SYS)
			{
				info("Critical error. Field [".$field."] is not string");
				return ZBX_VALID_ERROR;
			}
			else
			{
				info("Warning. Field [".$field."] is not string");
				return ZBX_VALID_WARNING;
			}
		}

		if(($type == T_ZBX_CLR) && !is_hex_color($var)) {
			$var = 'FFFFFF';
			if($flags&P_SYS)
			{
				info("Critical error. Field [".$field."] is not color");
				return ZBX_VALID_ERROR;
			}
			else
			{
				info("Warning. Field [".$field."] is not color");
				return ZBX_VALID_WARNING;
			}
		}
		return ZBX_VALID_OK;
	}

	function	check_trim(&$var)
	{
		if(is_string($var)) 
		{
			$var = trim($var);
		}
		elseif(is_array($var))
		{
			foreach($var as $key => $val)
			{
				check_trim($var[$key]);
			}
		}
	}

	function	check_field(&$fields, &$field, $checks)
	{
		list($type,$opt,$flags,$validation,$exception)=$checks;

		if($flags&P_UNSET_EMPTY && isset($_REQUEST[$field]) && $_REQUEST[$field]=='')
		{
			unset_request($field,'P_UNSET_EMPTY');
		}

//echo "Field: $field<br>";

		if($exception==NULL)	$except=FALSE;
		else			$except=calc_exp($fields,$field,$exception);

		if($opt == O_MAND &&	$except)	$opt = O_NO;
		else if($opt == O_OPT && $except)	$opt = O_MAND;
		else if($opt == O_NO && $except)	$opt = O_MAND;

		if($opt == O_MAND)
		{
			if(!isset($_REQUEST[$field]))
			{
				if($flags&P_SYS)
				{
					info("Critical error. Field [".$field."] is mandatory");
					return ZBX_VALID_ERROR;
				}
				else
				{
					info("Warning. Field [".$field."] is mandatory");
					return ZBX_VALID_WARNING;
				}
			}
		}
		elseif($opt == O_NO)
		{
			if(!isset($_REQUEST[$field]))
				return ZBX_VALID_OK;

			unset_request($field,'O_NO');

			if($flags&P_SYS)
			{
				info("Critical error. Field [".$field."] must be missing");
				return ZBX_VALID_ERROR;
			}
			else
			{
				info("Warning. Field [".$field."] must be missing");
				return ZBX_VALID_WARNING;
			}
		}
		elseif($opt == O_OPT)
		{
			if(!isset($_REQUEST[$field]))
				return ZBX_VALID_OK;
		}

		check_trim($_REQUEST[$field]);

		$err = check_type($field, $flags, $_REQUEST[$field], $type);
		if($err != ZBX_VALID_OK)
			return $err;

		if(($exception==NULL)||($except==TRUE))
		{
			if(!$validation)	$valid=TRUE;
			else			$valid=calc_exp($fields,$field,$validation);

			if(!$valid)
			{
				if($flags&P_SYS)
				{
					info("Critical error. Incorrect value for [".$field."] = '".$_REQUEST[$field]."'");
					return ZBX_VALID_ERROR;
				}
				else
				{
					info("Warning. Incorrect value for [".$field."]");
					return ZBX_VALID_WARNING;
				}
			}
		}

		
		return ZBX_VALID_OK;
	}

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$system_fields=array(
		"sessionid"=>		array(T_ZBX_STR, O_OPT,	 P_SYS,	HEX(),NULL),
		"switch_node"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"triggers_hash"=>	array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,NULL)
	);

	function	invalid_url()
	{
		include_once "include/page_header.php";
		unset_all();
		show_error_message(S_INVALID_URL);
		include_once "include/page_footer.php";
	}
	
	function	check_fields(&$fields, $show_messages=true)
	{

		global	$_REQUEST;
		global	$system_fields;

		$err = ZBX_VALID_OK;

		$fields = array_merge($fields, $system_fields);

		foreach($fields as $field => $checks)
		{
			$err |= check_field($fields, $field,$checks);
		}

		unset_not_in_list($fields);
		unset_if_zero($fields);
		if($err!=ZBX_VALID_OK)
		{
			unset_action_vars($fields);
		}

		$fields = null;
		
		if($err&ZBX_VALID_ERROR)
		{
			invalid_url();
		}

		if($show_messages) show_messages();

		return ($err==ZBX_VALID_OK ? 1 : 0);
	}
?>
