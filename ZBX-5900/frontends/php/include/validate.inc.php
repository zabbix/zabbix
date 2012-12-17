<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


function unset_request($key) {
	unset($_GET[$key], $_POST[$key], $_REQUEST[$key]);
}

function is_int_range($value) {
	if (!empty($value)) {
		foreach (explode(',', $value) as $int_range) {
			$int_range = explode('-', $int_range);
			if (count($int_range) > 2) {
				return false;
			}
			foreach ($int_range as $int_val) {
				if (!is_numeric($int_val)) {
					return false;
				}
			}
		}
	}
	return true;
}

function is_hex_color($value) {
	return preg_match('/^([0-9,A-F]{6})$/i', $value);
}

function BETWEEN($min, $max, $var = null) {
	return '({'.$var.'}>='.$min.'&&{'.$var.'}<='.$max.')&&';
}

function REGEXP($regexp, $var = null) {
	return "(preg_match(\"".$regexp."\", {".$var."}))&&";
}

function GT($value, $var = '') {
	return '({'.$var.'}>='.$value.')&&';
}

function IN($array, $var = '') {
	if (is_array($array)) {
		$array = implode(',', $array);
	}
	return 'str_in_array({'.$var.'},array('.$array.'))&&';
}

function HEX($var = null) {
	return 'preg_match("/^([a-zA-Z0-9]+)$/",{'.$var.'})&&';
}

function KEY_PARAM($var = null) {
	return 'preg_match("/'.ZBX_PREG_PARAMS.'/",{'.$var.'})&&';
}

function validate_ipv4($str, &$arr) {
	if (!preg_match('/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/', $str, $arr)) {
		return false;
	}
	for ($i = 1; $i <= 4; $i++) {
		if (!is_numeric($arr[$i]) || $arr[$i] > 255 || $arr[$i] < 0 ) {
			return false;
		}
	}
	return true;
}

function validate_ipv6($str) {
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

	if (!preg_match($full, $str)) {
		return false;
	}
	return true;
}

function validate_ip($str, &$arr) {
	if (validate_ipv4($str, $arr)) {
		return true;
	}
	if (defined('ZBX_HAVE_IPV6')) {
		return validate_ipv6($str);
	}
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
function validate_ip_range_range($ip_range) {
	$parts = explode('-', $ip_range);
	if (($parts_count = count($parts)) > 2) {
		return false;
	}

	if (validate_ipv4($parts[0], $arr)) {
		$ip_parts = explode('.', $parts[0]);

		if ($parts_count == 2) {
			if (!preg_match('/^([0-9]{1,3})$/', $parts[1])) {
				return false;
			}
			sscanf($ip_parts[3], "%d", $from_value);
			sscanf($parts[1], "%d", $to_value);

			if (($to_value > 255) || ($from_value > $to_value)) {
				return false;
			}
		}
	}
	elseif (defined('ZBX_HAVE_IPV6') && validate_ipv6($parts[0])) {
		$ip_parts = explode(':', $parts[0]);
		$ip_parts_count = count($ip_parts);

		if ($parts_count == 2) {
			if (!preg_match('/^([a-f0-9]{1,4})$/i', $parts[1])) {
				return false;
			}
			sscanf($ip_parts[$ip_parts_count - 1], "%x", $from_value);
			sscanf($parts[1], "%x", $to_value);

			if ($from_value > $to_value) {
				return false;
			}
		}
	}
	else {
		return false;
	}
	return true;
}

function validate_ip_range($str) {
	foreach (explode(',', $str) as $ip_range) {
		if (zbx_strpos($ip_range, '/') !== false) {
			if (!validate_ip_range_mask($ip_range)) {
				return false;
			}
		}
		else {
			if (!validate_ip_range_range($ip_range)) {
				return false;
			}
		}
	}
	return true;
}

function validate_port_list($str) {
	foreach (explode(',', $str) as $port_range) {
		$port_range = explode('-', $port_range);
		if (count($port_range) > 2) {
			return false;
		}
		foreach ($port_range as $port) {
			if (!validatePortNumber($port)) {
				return false;
			}
		}
	}
	return true;
}

function calc_exp($fields, $field, $expression) {
	if (zbx_strstr($expression, '{}')) {
		if (!isset($_REQUEST[$field])) {
			return false;
		}
		if (!is_array($_REQUEST[$field])) {
			$expression = str_replace('{}', '$_REQUEST["'.$field.'"]', $expression);
		}
		if (is_array($_REQUEST[$field])) {
			foreach ($_REQUEST[$field] as $key => $val) {
				if (!preg_match('/^([a-zA-Z0-9_]+)$/', $key)) {
					return false;
				}
				if (!calc_exp2($fields, str_replace('{}', '$_REQUEST["'.$field.'"]["'.$key.'"]', $expression))) {
					return false;
				}
			}
			return true;
		}
	}
	return calc_exp2($fields, $expression);
}

function calc_exp2($fields, $expression) {
	foreach ($fields as $f => $checks) {
		$expression = str_replace('{'.$f.'}', '$_REQUEST["'.$f.'"]', $expression);
	}
	return eval('return ('.trim($expression, '& ').') ? 1 : 0;');
}

function unset_not_in_list(&$fields) {
	foreach ($_REQUEST as $key => $val) {
		if (!isset($fields[$key])) {
			unset_request($key);
		}
	}
}

function unset_if_zero($fields) {
	foreach ($fields as $field => $checks) {
		list($type, $opt, $flags, $validation, $exception) = $checks;

		if ($flags&P_NZERO && isset($_REQUEST[$field]) && is_numeric($_REQUEST[$field]) && $_REQUEST[$field] == 0) {
			unset_request($field);
		}
	}
}

function unset_action_vars($fields) {
	foreach ($fields as $field => $checks) {
		list($type, $opt, $flags, $validation, $exception) = $checks;

		if ($flags&P_ACT && isset($_REQUEST[$field])) {
			unset_request($field);
		}
	}
}

function unset_all() {
	foreach ($_REQUEST as $key => $val) {
		unset_request($key);
	}
}

function check_type(&$field, $flags, &$var, $type, $caption = null) {
	if (is_null($caption)) {
		$caption = $field;
	}
	if (is_array($var) && $type != T_ZBX_IP) {
		$err = ZBX_VALID_OK;
		foreach ($var as $el) {
			$err |= check_type($field, $flags, $el, $type);
		}
		return $err;
	}

	if ($type == T_ZBX_IP) {
		if (!validate_ip($var, $arr)) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Field "%1$s" is not IP.', $field));
				return ZBX_VALID_ERROR;
			}
			else {
				info(_s('Warning. Field "%1$s" is not IP.', $field));
				return ZBX_VALID_WARNING;
			}
		}
		return ZBX_VALID_OK;
	}

	if ($type == T_ZBX_IP_RANGE) {
		if (!validate_ip_range($var)) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Field "%1$s" is not IP range.', $field));
				return ZBX_VALID_ERROR;
			}
			else{
				info(_s('Warning. Field "%1$s" is not IP range.', $field));
				return ZBX_VALID_WARNING;
			}
		}
		return ZBX_VALID_OK;
	}

	if ($type == T_ZBX_INT_RANGE) {
		if (!is_int_range($var)) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Field "%1$s" is not integer list or range.', $field));
				return ZBX_VALID_ERROR;
			}
			else {
				info(_s('Warning. Field "%1$s" is not integer list or range.', $field));
				return ZBX_VALID_WARNING;
			}
		}
		return ZBX_VALID_OK;
	}

	if ($type == T_ZBX_INT && !zbx_is_int($var)) {
		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" is not integer.', $field));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" is not integer.', $field));
			return ZBX_VALID_WARNING;
		}
	}

	if ($type == T_ZBX_DBL && !is_numeric($var)) {
		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" is not decimal number.', $field));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" is not decimal number.', $field));
			return ZBX_VALID_WARNING;
		}
	}

	if ($type == T_ZBX_STR && !is_string($var)) {
		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" is not string.', $field));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" is not string.', $field));
			return ZBX_VALID_WARNING;
		}
	}

	if ($type == T_ZBX_STR && !defined('ZBX_ALLOW_UNICODE') && zbx_strlen($var) != zbx_strlen($var)) {
		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" contains Multibyte chars.', $field));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" multibyte chars are restricted.', $field));
			return ZBX_VALID_ERROR;
		}
	}

	if ($type == T_ZBX_CLR && !is_hex_color($var)) {
		$var = 'FFFFFF';
		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" is not a colour.', $field));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" is not a colour.', $caption));
			return ZBX_VALID_WARNING;
		}
	}
	return ZBX_VALID_OK;
}

function check_trim(&$var) {
	if (is_string($var)) {
		$var = trim($var);
	}
	elseif (is_array($var)) {
		foreach ($var as $key => $val) {
			check_trim($var[$key]);
		}
	}
}

function check_field(&$fields, &$field, $checks) {
	if (!isset($checks[5])) {
		$checks[5] = $field;
	}
	list($type, $opt, $flags, $validation, $exception, $caption) = $checks;

	if ($flags&P_UNSET_EMPTY && isset($_REQUEST[$field]) && $_REQUEST[$field] == '') {
		unset_request($field);
	}

	$except = !is_null($exception) ? calc_exp($fields, $field, $exception) : false;

	if ($except) {
		if ($opt == O_MAND) {
			$opt = O_NO;
		}
		elseif ($opt == O_OPT) {
			$opt = O_MAND;
		}
		elseif ($opt == O_NO) {
			$opt = O_MAND;
		}
	}

	if ($opt == O_MAND) {
		if (!isset($_REQUEST[$field])) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Field "%1$s" is mandatory.', $caption));
				return ZBX_VALID_ERROR;
			}
			else {
				info(_s('Warning. Field "%1$s" is mandatory.', $caption));
				return ZBX_VALID_WARNING;
			}
		}
	}
	elseif ($opt == O_NO) {
		if (!isset($_REQUEST[$field])) {
			return ZBX_VALID_OK;
		}

		unset_request($field);

		if ($flags&P_SYS) {
			info(_s('Critical error. Field "%1$s" must be missing.', $caption));
			return ZBX_VALID_ERROR;
		}
		else {
			info(_s('Warning. Field "%1$s" must be missing.', $caption));
			return ZBX_VALID_WARNING;
		}
	}
	elseif ($opt == O_OPT) {
		if (!isset($_REQUEST[$field])) {
			return ZBX_VALID_OK;
		}
		elseif ($flags&P_ACT) {
			if (!isset($_REQUEST['sid'])
					|| (isset($_COOKIE['zbx_sessionid'])
					&& ($_REQUEST['sid'] != substr($_COOKIE['zbx_sessionid'], 16, 16)))) {
				info(_('Operation cannot be performed due to unauthorized request.'));
				return ZBX_VALID_ERROR;
			}
		}
	}

	if (!($flags & NO_TRIM)) {
		check_trim($_REQUEST[$field]);
	}

	$err = check_type($field, $flags, $_REQUEST[$field], $type, $caption);

	if ($err != ZBX_VALID_OK) {
		return $err;
	}

	if ((is_null($exception) || $except) && $validation && !calc_exp($fields, $field, $validation)){
		if ($validation == NOT_EMPTY) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Incorrect value for field "%1$s": cannot be empty.', $caption));
			}
			else {
				info(_s('Warning. Incorrect value for field "%1$s": cannot be empty.', $caption));
			}
		}
		// check for BETWEEN() function pattern and extract numbers e.g. ({}>=0&&{}<=999)&&
		elseif (preg_match('/\(\{\}\>=([0-9]*)\&\&\{\}\<=([0-9]*)\)\&\&/', $validation, $result)) {
			if ($flags&P_SYS) {
				info(_s('Critical error. Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.',
					$_REQUEST[$field], $caption, $result[1], $result[2]));
			}
			else {
				info(_s('Warning. Incorrect value for field "%1$s": must be between %2$s and %3$s.',
					$caption, $result[1], $result[2]));
			}
		}
		else {
			if ($flags&P_SYS) {
				info(_s('Critical error. Incorrect value "%1$s" for "%2$s" field.', $_REQUEST[$field], $caption));
			}
			else {
				info(_s('Warning. Incorrect value for field "%1$s".', $caption));
			}
		}

		return ($flags&P_SYS) ? ZBX_VALID_ERROR : ZBX_VALID_WARNING;
	}
	return ZBX_VALID_OK;
}

function invalid_url($msg = null) {
	if (empty($msg)) {
		$msg = _('Zabbix has received an incorrect request.');
	}
	require_once dirname(__FILE__).'/page_header.php';
	unset_all();
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function check_fields(&$fields, $show_messages = true) {
	// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
	$system_fields = array(
		'sid' =>		array(T_ZBX_STR, O_OPT, P_SYS, HEX(),		null),
		'switch_node' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
		'triggers_hash' =>	array(T_ZBX_STR, O_OPT, P_SYS, NOT_EMPTY,	null),
		'print' =>		array(T_ZBX_INT, O_OPT, P_SYS, IN('1'),		null),
		'sort' =>		array(T_ZBX_STR, O_OPT, P_SYS, null,		null),
		'sortorder' =>		array(T_ZBX_STR, O_OPT, P_SYS, null,		null),
		'page' =>		array(T_ZBX_INT, O_OPT, P_SYS, null,		null), // paging
		'ddreset' =>		array(T_ZBX_INT, O_OPT, P_SYS, null,		null)
	);
	$fields = zbx_array_merge($system_fields, $fields);

	$err = ZBX_VALID_OK;
	foreach ($fields as $field => $checks) {
		$err |= check_field($fields, $field, $checks);
	}

	unset_not_in_list($fields);
	unset_if_zero($fields);
	if ($err != ZBX_VALID_OK) {
		unset_action_vars($fields);
	}

	$fields = null;

	if ($err&ZBX_VALID_ERROR) {
		invalid_url();
	}

	if ($show_messages && $err != ZBX_VALID_OK) {
		show_messages($err == ZBX_VALID_OK, null, _('Page received incorrect data'));
	}

	return $err == ZBX_VALID_OK;
}

function validatePortNumberOrMacro($port) {
	return (validatePortNumber($port) || validateUserMacro($port));
}

function validatePortNumber($port) {
	return validateNumber($port, ZBX_MIN_PORT_NUMBER, ZBX_MAX_PORT_NUMBER);
}

function validateNumber($value, $min = null, $max = null) {
	if (!zbx_is_int($value)) {
		return false;
	}

	if ($min !== null && $value < $min) {
		return false;
	}

	if ($max !== null && $value > $max) {
		return false;
	}

	return true;
}

function validateUserMacro($value) {
	return preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $value);
}

/**
 * Validate, if time <= 2147464800 (2038.01.19 00:00).
 *
 * @param int $time
 *
 * @return bool
 */
function validateMaxTime($time) {
	return (is_numeric($time) && $time <= 2147464800);
}

/**
 * Validate if date and time are in correct range, e.g. month is not greater than 12 etc.
 *
 * @param int $year
 * @param int $month
 * @param int $day
 * @param int $minutes
 * @param int $seconds
 *
 * @return bool
 */
function validateDateTime($year, $month, $day, $hours, $minutes, $seconds = null) {
	return ($month < 1 || $month > 12
			|| $day < 1  || $day > 31 || (($month == 4 || $month == 6 || $month == 9 || $month == 11) && $day > 30)
			|| ($month == 2 && ((($year % 4) == 0 && $day > 29) || (($year % 4) != 0 && $day > 28)))
			|| $hours < 0 || $hours > 23
			|| $minutes < 0 || $minutes > 59
			|| (!is_null($seconds) && ($seconds < 0 || $seconds > 59)));
}

/**
 * Validate allowed date interval (1970.01.01-2038.01.18).
 *
 * @param int $year
 * @param int $month
 * @param int $day
 *
 * @return bool
 */
function validateDateInterval($year, $month, $day) {
	return ($year < 1970 || $year > 2038 || ($year == 2038 && (($month > 1) || ($month == 1 && $day > 18))));
}
