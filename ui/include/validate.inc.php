<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


function unset_request($key) {
	unset($_GET[$key], $_POST[$key], $_REQUEST[$key]);
}

/**
 * Validation expression for min/max number range.
 *
 * @param int    $min
 * @param int    $max
 * @param string $var
 *
 * @return string
 */
function BETWEEN($min, $max, $var = '') {
	return '({'.$var.'}>='.$min.'&&{'.$var.'}<='.$max.')&&';
}

/**
 * Validation expression for min/max range and max number of digits after the decimal point.
 *
 * @param int    $min
 * @param int    $max
 * @param int    $scale
 * @param string $var
 *
 * @return string
 */
function BETWEEN_DBL($min, $max, $scale, $var = '') {
	return BETWEEN($min, $max, $var).'(round({'.$var.'},'.$scale.')=={'.$var.'})&&';
}

function IN($array, $var = '') {
	if (is_array($array)) {
		$array = implode(',', $array);
	}

	return 'str_in_array({'.$var.'},array('.$array.'))&&';
}

/**
 * @deprecated
 */
function HEX($var = null) {
	return 'preg_match("/^([a-zA-Z0-9]+)$/",{'.$var.'})&&';
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
	if (strpos($expression, '{}') !== false) {
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
	foreach ($fields as $field => $checks) {
		$expression = str_replace('{'.$field.'}', '$_REQUEST["'.$field.'"]', $expression);
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
	if ($caption === null) {
		$caption = $field;
	}

	$is_array_flag = ($flags & P_ONLY_ARRAY);
	$is_td_array_flag = ($flags & P_ONLY_TD_ARRAY);
	$has_array_flag = $is_array_flag || $is_td_array_flag;

	if (is_array($var) && $type != T_ZBX_RANGE_TIME && $has_array_flag) {
		$err = ZBX_VALID_OK;

		if ($flags & P_ONLY_ARRAY) {
			$flags &= ~P_ONLY_ARRAY;
		}

		if ($flags & P_ONLY_TD_ARRAY) {
			$flags &= ~P_ONLY_TD_ARRAY;
			$flags |= P_ONLY_ARRAY;
		}

		if ($flags & P_ONLY_ARRAY || $type !== null) {
			foreach ($var as $v) {
				$err = check_type($field, $flags, $v, $type);

				if ($err != ZBX_VALID_OK) {
					break;
				}
			}
		}

		return $err;
	}

	$error = false;
	$message = '';

	if ($has_array_flag) {
		if (!is_array($var)) {
			error(_s('Field "%1$s" is not correct: %2$s.', $caption, _('an array is expected')));
			return ZBX_VALID_ERROR;
		}
	}
	elseif (is_array($var)) {
		error(_s('Field "%1$s" is not correct: %2$s.', $caption, _('invalid data type')));
		return ZBX_VALID_ERROR;
	}

	if ($type == T_ZBX_INT) {
		if (!zbx_is_int($var)) {
			$error = true;
			$message = _s('Field "%1$s" is not integer.', $caption);
		}
	}
	elseif ($type == T_ZBX_DBL) {
		$number_parser = new CNumberParser();

		if ($number_parser->parse($var) != CParser::PARSE_SUCCESS) {
			$error = true;
			$message = _s('Field "%1$s" is not correct: %2$s', $caption, _('a number is expected'));
		}

		$value = $number_parser->calcValue();

		if (abs($value) > ZBX_FLOAT_MAX) {
			$error = true;
			$message = _s('Field "%1$s" is not correct: %2$s', $caption, _('a number is too large'));
		}
	}
	elseif ($type == T_ZBX_STR) {
		if (!is_string($var)) {
			$error = true;
			$message = _s('Field "%1$s" is not string.', $caption);
		}
		elseif (zbx_mb_check_encoding($var, 'UTF-8') !== true) {
			error(_s('Field "%1$s" is not correct: %2$s.', $caption, _('invalid byte sequence in UTF-8')));

			return ZBX_VALID_ERROR;
		}
	}
	elseif ($type == T_ZBX_TU) {
		$simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => ($flags & P_ALLOW_USER_MACRO),
			'lldmacros' => ($flags & P_ALLOW_LLD_MACRO)
		]);

		if ($simple_interval_parser->parse($var) != CParser::PARSE_SUCCESS) {
			$error = true;
			$message = _s('Field "%1$s" is not correct: %2$s', $caption, _('a time unit is expected'));
		}
	}
	elseif ($type == T_ZBX_RANGE_TIME) {
		$range_time_parser = new CRangeTimeParser();

		if (!is_string($var) || $range_time_parser->parse($var) != CParser::PARSE_SUCCESS) {
			$error = true;
			$message = _s('Field "%1$s" is not correct: %2$s', $caption, _('a time range is expected'));
		}
	}
	elseif ($type == T_ZBX_ABS_TIME) {
		$absolute_time_parser = new CAbsoluteTimeParser();

		if (!is_string($var) || $absolute_time_parser->parse($var) != CParser::PARSE_SUCCESS) {
			$error = true;
			$message = _s('Field "%1$s" is not correct: %2$s', $caption, _('an explicit time is expected'));
		}
	}

	if ($error) {
		if ($flags & P_SYS) {
			error($message);

			return ZBX_VALID_ERROR;
		}
		else {
			info($message);

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
			info(_s('Field "%1$s" is mandatory.', $caption));

			return ($flags & P_SYS) ? ZBX_VALID_ERROR : ZBX_VALID_WARNING;
		}
	}
	elseif ($opt == O_NO) {
		if (!isset($_REQUEST[$field])) {
			return ZBX_VALID_OK;
		}

		unset_request($field);

		info(_s('Field "%1$s" must be missing.', $caption));

		return ($flags & P_SYS) ? ZBX_VALID_ERROR : ZBX_VALID_WARNING;
	}
	elseif ($opt == O_OPT) {
		if (!isset($_REQUEST[$field])) {
			return ZBX_VALID_OK;
		}
		elseif ($flags & P_ACT) {
			$action = APP::Component()->router->getAction();

			$csrf_token_form = getRequest(CSRF_TOKEN_NAME, '');

			if (!isRequestMethod('post') || !is_string($csrf_token_form) || $csrf_token_form === ''
					|| !CCsrfTokenHelper::check($csrf_token_form, $action)) {
				info(_('Operation cannot be performed due to unauthorized request.'));
				return ZBX_VALID_ERROR;
			}
		}
	}

	if ($flags & P_CRLF) {
		$_REQUEST[$field] = CRLFtoLF($_REQUEST[$field]);
	}

	if (!($flags & P_NO_TRIM)) {
		check_trim($_REQUEST[$field]);
	}

	$err = check_type($field, $flags, $_REQUEST[$field], $type, $caption);

	if ($err != ZBX_VALID_OK) {
		return $err;
	}

	if ((is_null($exception) || $except) && $validation && !calc_exp($fields, $field, $validation)) {
		if ($validation == NOT_EMPTY) {
			info(_s('Incorrect value for field "%1$s": %2$s.', $caption, _('cannot be empty')));
		}
		// Check for BETWEEN() or BETWEEN_SCALE function pattern and extract min, max and scale numbers.
		elseif (preg_match('/\(\{\}>=(?<min>\d+)&&\{\}<=(?<max>\d+)\)&&(\(round\(\{\},(?<scale>\d+)\)==\{\}\)&&)?/',
				$validation, $matches)) {
			if (array_key_exists('scale', $matches)) {
				info(_s('Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s, and have no more than %5$s digits after the decimal point.',
					$_REQUEST[$field], $caption, $matches['min'], $matches['max'], $matches['scale']
				));
			}
			else {
				info(_s('Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.',
					$_REQUEST[$field], $caption, $matches['min'], $matches['max']
				));
			}
		}
		elseif (is_scalar($_REQUEST[$field])) {
			info(_s('Incorrect value "%1$s" for "%2$s" field.', $_REQUEST[$field], $caption));
		}
		else {
			info(_s('Incorrect value for "%1$s" field.', $caption));
		}

		return ($flags & P_SYS) ? ZBX_VALID_ERROR : ZBX_VALID_WARNING;
	}

	return ZBX_VALID_OK;
}

function invalid_url($msg = null) {
	if (empty($msg)) {
		$msg = _('Zabbix has received an incorrect request.');
	}

	// required global parameters for correct including page_header.php
	global $DB;

	// backup messages before including page_header.php
	$messages_backup = CMessageHelper::getMessages();
	CMessageHelper::clear();

	require_once dirname(__FILE__).'/page_header.php';

	// Rollback reset messages.
	foreach ($messages_backup as $message) {
		CMessageHelper::addMessage($message);
	}

	unset_all();
	show_error_message($msg);

	(new CHtmlPage())->show();
	require_once dirname(__FILE__).'/page_footer.php';
}

/**
 * Validate request fields and return result flags.
 *
 * @param array $fields field schema together with validation rules
 *
 * @return integer appropriate result flags ZBX_VALID_OK | ZBX_VALID_ERROR | ZBX_VALID_WARNING
 */
function check_fields_raw(&$fields) {
	// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
	$system_fields = [
		'triggers_hash' =>	[T_ZBX_STR, O_OPT, P_SYS, NOT_EMPTY,	null],
		'print' =>			[T_ZBX_INT, O_OPT, P_SYS, IN('1'),		null],
		'page' =>			[T_ZBX_INT, O_OPT, P_SYS, null,		null]	// paging
	];
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

	return $err;
}

/**
 * Validate request fields and return true on success, false on error.
 *
 * @param array $fields field schema together with validation rules
 * @param bool $show_messages do show messages on error
 *
 * @return bool true on success, false on error.
 */
function check_fields(&$fields, $show_messages = true) {
	$err = check_fields_raw($fields);

	if ($err & ZBX_VALID_ERROR) {
		invalid_url();
	}

	if ($show_messages && $err != ZBX_VALID_OK) {
		show_messages(false, null, _('Page received incorrect data'));
	}

	return ($err == ZBX_VALID_OK);
}

/**
 * Validate "from" and "to" parameters for allowed period.
 *
 * @param string|null from
 * @param string|null to
 */
function validateTimeSelectorPeriod($from, $to) {
	if ($from === null || $to === null) {
		return;
	}

	$ts = [];
	$ts['now'] = time();
	$range_time_parser = new CRangeTimeParser();

	foreach (['from' => $from, 'to' => $to] as $field => $value) {
		$range_time_parser->parse($value);
		$ts[$field] = $range_time_parser
			->getDateTime($field === 'from')
			->getTimestamp();
	}

	$period = $ts['to'] - $ts['from'] + 1;
	$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
	$max_period = 1 + $ts['now'] - $range_time_parser
		->getDateTime(true)
		->getTimestamp();

	if ($period < ZBX_MIN_PERIOD) {
		error(_n('Minimum time period to display is %1$s minute.',
			'Minimum time period to display is %1$s minutes.', (int) (ZBX_MIN_PERIOD / SEC_PER_MIN)
		));

		invalid_url();
	}
	elseif ($period > $max_period) {
		error(_n('Maximum time period to display is %1$s day.',
			'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
		));

		invalid_url();
	}
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
	return (new CUserMacroParser())->parse($value) == CParser::PARSE_SUCCESS
		|| (new CUserMacroFunctionParser())->parse($value) == CParser::PARSE_SUCCESS;
}

/**
 * Validate, if unix time in (1970.01.01 00:00:01 - 2038.01.19 00:00:00).
 *
 * @param int $time
 *
 * @return bool
 */
function validateUnixTime($time) {
	return (is_numeric($time) && $time > 0 && $time <= 2147464800);
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
	return !($month < 1 || $month > 12
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
	return !($year < 1970 || $year > 2038 || ($year == 2038 && (($month > 1) || ($month == 1 && $day > 18))));
}

/**
 * Validate a configuration value. Use simple interval parser to parse the string, convert to seconds and check
 * if the value is in between given min and max values. In some cases it's possible to enter 0, or even 0s or 0d.
 * If the value is incorrect, set an error.
 *
 * @param string $value                  Value to parse and validate.
 * @param int    $min                    Lower bound.
 * @param int    $max                    Upper bound.
 * @param bool   $allow_zero             Set to "true" to allow value to be zero.
 * @param string $error
 * @param array  $options
 * @param bool   $options['usermacros']
 * @param bool   $options['lldmacros']
 * @param bool   $options['with_year']
 *
 * @return bool
 */
function validateTimeUnit($value, $min, $max, $allow_zero, &$error, array $options = []) {
	$simple_interval_parser = new CSimpleIntervalParser($options);
	$value = (string) $value;

	if ($simple_interval_parser->parse($value) == CParser::PARSE_SUCCESS) {
		if ($value[0] !== '{') {
			$value = timeUnitToSeconds($value, array_key_exists('with_year', $options) ? $options['with_year'] : false);

			if ($allow_zero && $value == 0) {
				return true;
			}

			if ($value < $min || $value > $max) {
				$error = _s('value must be one of %1$s', $allow_zero ? '0, '.$min.'-'.$max : $min.'-'.$max);

				return false;
			}
		}
	}
	else {
		$error = _('a time unit is expected');

		return false;
	}

	return true;
}
