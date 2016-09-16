<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Condition');
$page['file'] = 'popup_trexpr.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

$metrics = [
	PARAM_TYPE_TIME => _('Time'),
	PARAM_TYPE_COUNTS => _('Count')
];
$param1SecCount = [
	[
		'C' => _('Last of').' (T)',	// caption
		'T' => T_ZBX_INT,			// type
		'M' => $metrics				// metrics
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	]
];
$param1Sec = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT
	]
];
$param1Str = [
	[
		'C' => 'T',
		'T' => T_ZBX_STR
	]
];
$param2SecCount = [
	[
		'C' => 'V',
		'T' => T_ZBX_STR
	],
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	]
];
$param3SecVal = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	],
	[
		'C' => 'V',
		'T' => T_ZBX_STR
	],
	[
		'C' => 'O',
		'T' => T_ZBX_STR
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	]
];
$param3SecPercent = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	],
	[
		'C' => _('Percentage').' (P)',
		'T' => T_ZBX_DBL
	]
];
$paramSecIntCount = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	],
	[
		'C' => _('Mask'),
		'T' => T_ZBX_STR
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	]
];
$paramForecast = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	],
	[
		'C' => _('Time').' (t)',
		'T' => T_ZBX_INT
	],
	[
		'C' => _('Fit'),
		'T' => T_ZBX_STR
	],
	[
		'C' => _('Mode'),
		'T' => T_ZBX_STR
	]
];
$paramTimeleft = [
	[
		'C' => _('Last of').' (T)',
		'T' => T_ZBX_INT,
		'M' => $metrics
	],
	[
		'C' => _('Time shift'),
		'T' => T_ZBX_INT
	],
	[
		'C' => _('Threshold'),
		'T' => T_ZBX_DBL
	],
	[
		'C' => _('Fit'),
		'T' => T_ZBX_STR
	]
];
$allowedTypesAny = [
	ITEM_VALUE_TYPE_FLOAT => 1,
	ITEM_VALUE_TYPE_STR => 1,
	ITEM_VALUE_TYPE_LOG => 1,
	ITEM_VALUE_TYPE_UINT64 => 1,
	ITEM_VALUE_TYPE_TEXT => 1
];
$allowedTypesNumeric = [
	ITEM_VALUE_TYPE_FLOAT => 1,
	ITEM_VALUE_TYPE_UINT64 => 1
];
$allowedTypesStr = [
	ITEM_VALUE_TYPE_STR => 1,
	ITEM_VALUE_TYPE_LOG => 1,
	ITEM_VALUE_TYPE_TEXT => 1
];
$allowedTypesLog = [
	ITEM_VALUE_TYPE_LOG => 1
];
$allowedTypesInt = [
	ITEM_VALUE_TYPE_UINT64 => 1
];

$functions = [
	'abschange[<]' => [
		'description' =>  _('Absolute difference between last and previous value is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'abschange[>]' => [
		'description' =>  _('Absolute difference between last and previous value is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'abschange[=]' => [
		'description' =>  _('Absolute difference between last and previous value is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'abschange[<>]' => [
		'description' =>  _('Absolute difference between last and previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'avg[<]' => [
		'description' =>  _('Average value of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'avg[>]' => [
		'description' =>  _('Average value of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'avg[=]' => [
		'description' =>  _('Average value of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'avg[<>]' => [
		'description' =>  _('Average value of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'delta[<]' => [
		'description' =>  _('Difference between MAX and MIN value of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'delta[>]' => [
		'description' =>  _('Difference between MAX and MIN value of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'delta[=]' => [
		'description' =>  _('Difference between MAX and MIN value of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'delta[<>]' => [
		'description' =>  _('Difference between MAX and MIN value of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'change[<]' => [
		'description' =>  _('Difference between last and previous value is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'change[>]' => [
		'description' =>  _('Difference between last and previous value is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'change[=]' => [
		'description' =>  _('Difference between last and previous value is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'change[<>]' => [
		'description' =>  _('Difference between last and previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'count[<]' => [
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is < N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	],
	'count[>]' => [
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is > N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	],
	'count[=]' => [
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is = N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	],
	'count[<>]' => [
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is NOT N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	],
	'diff[=]' => [
		'description' =>  _('Difference between last and preceding values, then N = 1, 0 - otherwise'),
		'allowed_types' => $allowedTypesAny
	],
	'diff[<>]' => [
		'description' =>  _('Difference between last and preceding values, then N NOT 1, 0 - otherwise'),
		'allowed_types' => $allowedTypesAny
	],
	'last[<]' => [
		'description' =>  _('Last (most recent) T value is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'last[>]' => [
		'description' =>  _('Last (most recent) T value is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'last[=]' => [
		'description' =>  _('Last (most recent) T value is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'last[<>]' => [
		'description' =>  _('Last (most recent) T value is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'max[<]' => [
		'description' =>  _('Maximum value for period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'max[>]' => [
		'description' =>  _('Maximum value for period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'max[=]' => [
		'description' =>  _('Maximum value for period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'max[<>]' => [
		'description' =>  _('Maximum value for period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'min[<]' => [
		'description' =>  _('Minimum value for period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		],
	'min[>]' => [
		'description' =>  _('Minimum value for period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		],
	'min[=]' => [
		'description' =>  _('Minimum value for period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		],
	'min[<>]' => [
		'description' =>  _('Minimum value for period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		],
	'percentile[<]' => [
		'description' =>  _('Percentile P of a period T is < N'),
		'params' => $param3SecPercent,
		'allowed_types' => $allowedTypesNumeric
	],
	'percentile[>]' => [
		'description' =>  _('Percentile P of a period T is > N'),
		'params' => $param3SecPercent,
		'allowed_types' => $allowedTypesNumeric
	],
	'percentile[=]' => [
		'description' =>  _('Percentile P of a period T is = N'),
		'params' => $param3SecPercent,
		'allowed_types' => $allowedTypesNumeric
	],
	'percentile[<>]' => [
		'description' =>  _('Percentile P of a period T is NOT N'),
		'params' => $param3SecPercent,
		'allowed_types' => $allowedTypesNumeric
	],
	'prev[<]' => [
		'description' =>  _('Previous value is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'prev[>]' => [
		'description' =>  _('Previous value is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'prev[=]' => [
		'description' =>  _('Previous value is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'prev[<>]' => [
		'description' =>  _('Previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'str[=]' => [
		'description' =>  _('Find string V in last (most recent) value. N = 1 - if found, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'str[<>]' => [
		'description' =>  _('Find string V in last (most recent) value. N NOT 1 - if found, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'strlen[<]' => [
		'description' =>  _('Length of last (most recent) T value in characters is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	],
	'strlen[>]' => [
		'description' =>  _('Length of last (most recent) T value in characters is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	],
	'strlen[=]' => [
		'description' =>  _('Length of last (most recent) T value in characters is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	],
	'strlen[<>]' => [
		'description' =>  _('Length of last (most recent) T value in characters is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	],
	'sum[<]' => [
		'description' =>  _('Sum of values of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'sum[>]' => [
		'description' =>  _('Sum of values of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'sum[=]' => [
		'description' =>  _('Sum of values of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'sum[<>]' => [
		'description' =>  _('Sum of values of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	],
	'date[<]' => [
		'description' =>  _('Current date is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'date[>]' => [
		'description' =>  _('Current date is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'date[=]' => [
		'description' =>  _('Current date is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'date[<>]' => [
		'description' =>  _('Current date is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofweek[<]' => [
		'description' =>  _('Day of week is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofweek[>]' => [
		'description' =>  _('Day of week is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofweek[=]' => [
		'description' =>  _('Day of week is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofweek[<>]' => [
		'description' =>  _('Day of week is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofmonth[<]' => [
		'description' =>  _('Day of month is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofmonth[>]' => [
		'description' =>  _('Day of month is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofmonth[=]' => [
		'description' =>  _('Day of month is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'dayofmonth[<>]' => [
		'description' =>  _('Day of month is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'fuzzytime[=]' => [
		'description' =>  _('Difference between item timestamp value and Zabbix server timestamp is over T seconds, then N = 0, 1 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	],
	'fuzzytime[<>]' => [
		'description' =>  _('Difference between item timestamp value and Zabbix server timestamp is over T seconds, then N NOT 0, 1 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	],
	'regexp[=]' => [
		'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'regexp[<>]' => [
		'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'iregexp[=]' => [
		'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise (non case-sensitive)'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'iregexp[<>]' => [
		'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise (non case-sensitive)'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	],
	'logeventid[=]' => [
		'description' =>  _('Event ID of last log entry matching regular expression T, then N = 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	],
	'logeventid[<>]' => [
		'description' =>  _('Event ID of last log entry matching regular expression T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	],
	'logseverity[<]' => [
		'description' =>  _('Log severity of the last log entry is < N'),
		'allowed_types' => $allowedTypesLog
	],
	'logseverity[>]' => [
		'description' =>  _('Log severity of the last log entry is > N'),
		'allowed_types' => $allowedTypesLog
	],
	'logseverity[=]' => [
		'description' =>  _('Log severity of the last log entry is = N'),
		'allowed_types' => $allowedTypesLog
	],
	'logseverity[<>]' => [
		'description' =>  _('Log severity of the last log entry is NOT N'),
		'allowed_types' => $allowedTypesLog
	],
	'logsource[=]' => [
		'description' =>  _('Log source of the last log entry matching parameter T, then N = 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	],
	'logsource[<>]' => [
		'description' =>  _('Log source of the last log entry matching parameter T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	],
	'now[<]' => [
		'description' =>  _('Number of seconds since the Epoch is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'now[>]' => [
		'description' =>  _('Number of seconds since the Epoch is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'now[=]' => [
		'description' =>  _('Number of seconds since the Epoch is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'now[<>]' => [
		'description' =>  _('Number of seconds since the Epoch is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'time[<]' => [
		'description' =>  _('Current time is < N'),
		'allowed_types' => $allowedTypesAny
	],
	'time[>]' => [
		'description' =>  _('Current time is > N'),
		'allowed_types' => $allowedTypesAny
	],
	'time[=]' => [
		'description' =>  _('Current time is = N'),
		'allowed_types' => $allowedTypesAny
	],
	'time[<>]' => [
		'description' =>  _('Current time is NOT N'),
		'allowed_types' => $allowedTypesAny
	],
	'nodata[=]' => [
		'description' =>  _('No data received during period of time T, then N = 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	],
	'nodata[<>]' => [
		'description' =>  _('No data received during period of time T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	],
	'band[=]' => [
		'description' =>  _('Bitwise AND of last (most recent) T value and mask is = N'),
		'params' => $paramSecIntCount,
		'allowed_types' => $allowedTypesInt
	],
	'band[<>]' => [
		'description' =>  _('Bitwise AND of last (most recent) T value and mask is NOT N'),
		'params' => $paramSecIntCount,
		'allowed_types' => $allowedTypesInt
	],
	'forecast[<]' => [
		'description' => _('Forecast for next t seconds based on period T is < N'),
		'params' => $paramForecast,
		'allowed_types' => $allowedTypesNumeric
	],
	'forecast[>]' => [
		'description' => _('Forecast for next t seconds based on period T is > N'),
		'params' => $paramForecast,
		'allowed_types' => $allowedTypesNumeric
	],
	'forecast[=]' => [
		'description' => _('Forecast for next t seconds based on period T is = N'),
		'params' => $paramForecast,
		'allowed_types' => $allowedTypesNumeric
	],
	'forecast[<>]' => [
		'description' => _('Forecast for next t seconds based on period T is NOT N'),
		'params' => $paramForecast,
		'allowed_types' => $allowedTypesNumeric
	],
	'timeleft[<]' => [
		'description' => _('Time to reach threshold estimated based on period T is < N'),
		'params' => $paramTimeleft,
		'allowed_types' => $allowedTypesNumeric
	],
	'timeleft[>]' => [
		'description' => _('Time to reach threshold estimated based on period T is > N'),
		'params' => $paramTimeleft,
		'allowed_types' => $allowedTypesNumeric
	],
	'timeleft[=]' => [
		'description' => _('Time to reach threshold estimated based on period T is = N'),
		'params' => $paramTimeleft,
		'allowed_types' => $allowedTypesNumeric
	],
	'timeleft[<>]' => [
		'description' => _('Time to reach threshold estimated based on period T is NOT N'),
		'params' => $paramTimeleft,
		'allowed_types' => $allowedTypesNumeric
	]
];
order_result($functions, 'description');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'dstfrm' =>				[T_ZBX_STR, O_MAND, P_SYS, NOT_EMPTY,	null],
	'dstfld1' =>			[T_ZBX_STR, O_MAND, P_SYS, NOT_EMPTY,	null],
	'expression' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'itemid' =>				[T_ZBX_INT, O_OPT, null,	null,		'isset({insert})'],
	'parent_discoveryid' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'expr_type'=>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({insert})'],
	'params' =>				[T_ZBX_STR, O_OPT, null,	0,			null],
	'paramtype' =>			[T_ZBX_INT, O_OPT, null,	IN(PARAM_TYPE_TIME.','.PARAM_TYPE_COUNTS), 'isset({insert})'],
	'value' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({insert})'],
	// action
	'insert' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null]
];
check_fields($fields);

$dstfrm = getRequest('dstfrm', 0);
$dstfld1 = getRequest('dstfld1', '');
$itemId = getRequest('itemid', 0);
$value = getRequest('value', 0);
$params = getRequest('params', []);
$paramType = getRequest('paramtype');
$exprType = getRequest('expr_type', 'last[=]');

// opening the popup when editing an expression in the trigger constructor
if (isset($_REQUEST['expression']) && $_REQUEST['dstfld1'] == 'expr_temp') {
	$_REQUEST['expression'] = utf8RawUrlDecode($_REQUEST['expression']);

	$expressionData = new CTriggerExpression();
	$result = $expressionData->parse(getRequest('expression'));

	if ($result) {
		// only one item function macro is supported in an expression
		$functionMacroTokens = $result->getTokensByType(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO);
		if (count($functionMacroTokens) == 1) {
			$functionMacroToken = $functionMacroTokens[0];

			// function
			$function = $functionMacroToken['data']['functionName'];

			// determine param type
			$params = $functionMacroToken['data']['functionParams'];
			$paramNumber = in_array($function, ['regexp', 'iregexp', 'str']) ? 1 : 0;
			if (isset($params[$paramNumber][0]) && $params[$paramNumber][0] == '#') {
				$paramType = PARAM_TYPE_COUNTS;
				$params[$paramNumber] = substr($params[$paramNumber], 1);
			}
			else {
				$paramType = PARAM_TYPE_TIME;
			}

			// default operator
			$operator = '=';

			// try to find an operator and a numeric value
			// the value and operator can be extracted only if the immediately follow the item function macro
			$tokens = $result->getTokens();
			foreach ($tokens as $key => $token) {
				if ($token['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO) {
					if (isset($tokens[$key + 2])
							&& $tokens[$key + 1]['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR
							&& isset($functions[$function.'['.$tokens[$key + 1]['value'].']'])
							&& $tokens[$key + 2]['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_NUMBER) {

						$operator = $tokens[$key + 1]['value'];
						$value = $tokens[$key + 2]['value'];
					}
					else {
						break;
					}
				}
			}

			$exprType = $function.'['.$operator.']';

			// find the item
			$item = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
				'selectHosts' => ['name'],
				'webitems' => true,
				'filter' => [
					'host' => $functionMacroToken['data']['host'],
					'key_' => $functionMacroToken['data']['item'],
					'flags' => null
				]
			]);
			$item = reset($item);

			if ($item) {
				$itemId = $item['itemid'];
			}
			else {
				error(_('Unknown host item, no such item in selected host'));
			}
		}
	}
}
// opening an empty form or switching a function
else {
	if (preg_match('/^([a-z]+)\[([=><]{1,2})\]$/i', $exprType, $matches)) {
		$function = $matches[1];
		$operator = $matches[2];

		if (!isset($functions[$exprType])) {
			unset($function);
		}
	}

	// fetch item
	$item = API::Item()->get([
		'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
		'selectHosts' => ['host', 'name'],
		'itemids' => $itemId,
		'webitems' => true,
		'filter' => ['flags' => null]
	]);
	$item = reset($item);
}

if ($itemId) {
	$items = CMacrosResolverHelper::resolveItemNames([$item]);
	$item = $items[0];

	$itemValueType = $item['value_type'];
	$itemKey = $item['key_'];
	$itemHostData = reset($item['hosts']);
	$description = $itemHostData['name'].NAME_DELIMITER.$item['name_expanded'];
}
else {
	$itemKey = '';
	$description = '';
	$itemValueType = null;
}

if (is_null($paramType) && isset($functions[$exprType]['params']['M'])) {
	$paramType = is_array($functions[$exprType]['params']['M']) ? reset($functions[$exprType]['params']['M']) : $functions[$exprType]['params']['M'];
}
elseif (is_null($paramType)) {
	$paramType = PARAM_TYPE_TIME;
}

/*
 * Display
 */
$data = [
	'parent_discoveryid' => getRequest('parent_discoveryid'),
	'dstfrm' => $dstfrm,
	'dstfld1' => $dstfld1,
	'itemid' => $itemId,
	'value' => $value,
	'params' => $params,
	'paramtype' => $paramType,
	'description' => $description,
	'functions' => $functions,
	'item_key' => $itemKey,
	'itemValueType' => $itemValueType,
	'selectedFunction' => null,
	'expr_type' => $exprType,
	'insert' => getRequest('insert'),
	'cancel' => getRequest('cancel')
];

// check if submitted function is usable with selected item
foreach ($data['functions'] as $id => $f) {
	if ((!$data['itemValueType'] || isset($f['allowed_types'][$data['itemValueType']])) && $id == $exprType) {
		$data['selectedFunction'] = $id;
		break;
	}
}

if ($data['selectedFunction'] === null) {
	error(_s('Function "%1$s" cannot be used with selected item "%2$s"',
		$data['functions'][$exprType]['description'],
		$data['description']
	));
}

// remove functions that not correspond to chosen item
foreach ($data['functions'] as $id => $f) {
	if ($data['itemValueType'] && !isset($f['allowed_types'][$data['itemValueType']])) {
		unset($data['functions'][$id]);
	}
}

// create and validate trigger expression
if (isset($data['insert'])) {
	try {
		if ($data['description']) {
			if ($data['paramtype'] == PARAM_TYPE_COUNTS) {
				$paramNumber = in_array($function, ['regexp', 'iregexp', 'str']) ? 1 : 0;
				$data['params'][$paramNumber] = '#'.$data['params'][$paramNumber];
			}

			if ($data['paramtype'] == PARAM_TYPE_TIME && in_array($function, ['last', 'band', 'strlen'])) {
				$data['params'][0] = '';
			}

			// quote function param
			$quotedParams = [];
			foreach ($data['params'] as $param) {
				$quotedParams[] = quoteFunctionParam($param);
			}

			$data['expression'] = sprintf('{%s:%s.%s(%s)}%s%s',
				$itemHostData['host'],
				$data['item_key'],
				$function,
				rtrim(implode(',', $quotedParams), ','),
				$operator,
				$data['value']
			);

			// validate trigger expression
			$triggerExpression = new CTriggerExpression();

			if ($triggerExpression->parse($data['expression'])) {
				$expressionData = reset($triggerExpression->expressions);

				// validate trigger function
				$triggerFunctionValidator = new CFunctionValidator();
				$isValid = $triggerFunctionValidator->validate([
					'function' => $expressionData['function'],
					'functionName' => $expressionData['functionName'],
					'functionParamList' => $expressionData['functionParamList'],
					'valueType' => $data['itemValueType']
				]);
				if (!$isValid) {
					unset($data['insert']);
					throw new Exception($triggerFunctionValidator->getError());
				}
			}
			else {
				unset($data['insert']);
				throw new Exception($triggerExpression->error);
			}

			// quote function param
			if (isset($data['insert'])) {
				foreach ($data['params'] as $pnum => $param) {
					$data['params'][$pnum] = quoteFunctionParam($param);
				}
			}
		}
		else {
			unset($data['insert']);
			throw new Exception(_('Item not selected'));
		}
	}
	catch (Exception $e) {
		error($e->getMessage());
		show_error_message(_('Cannot insert trigger expression'));
	}
}
elseif (hasErrorMesssages()) {
	show_messages();
}

// render view
$expressionView = new CView('configuration.triggers.expression', $data);
$expressionView->render();
$expressionView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
