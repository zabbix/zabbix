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

$operators = array(
	'<' => '<',
	'>' => '>',
	'=' => '=',
	'#' => 'NOT'
);
$limitedOperators = array(
	'=' => '=',
	'#' => 'NOT'
);
$metrics = array(
	PARAM_TYPE_TIME => _('Time'),
	PARAM_TYPE_COUNTS => _('Count')
);
$param1SecCount = array(
	array(
		'C' => _('Last of').' (T)',// caption
		'T' => T_ZBX_INT, // type
		'M' => $metrics // metrcis
	),
	array(
		'C' => _('Time shift').' ', // caption
		'T' => T_ZBX_INT // type
	)
);
$param1Sec = array(
	array(
		'C' => _('Last of').' (T)', // caption
		'T' => T_ZBX_INT // type
	)
);
$param1Str = array(
	array(
		'C' => 'T', // caption
		'T' => T_ZBX_STR
	)
);
$param2SecCount = array(
	array(
		'C' => 'V', // caption
		'T' => T_ZBX_STR
	),
	array(
		'C' => _('Last of').' (T)', // caption
		'T' => T_ZBX_INT, // type
		'M' => $metrics // metrcis
	)
);
$param3SecVal = array(
	array(
		'C' => _('Last of').' (T)', // caption
		'T' => T_ZBX_INT, // type
		'M' => $metrics // metrcis
	),
	array(
		'C' => 'V', // caption
		'T' => T_ZBX_STR
	),
	array(
		'C' => 'O', // caption
		'T' => T_ZBX_STR
	),
	array(
		'C' => _('Time shift').' ', // caption
		'T' => T_ZBX_INT // type
	)
);
$paramSecIntCount = array(
	array(
		'C' => _('Last of').' (T)', // caption
		'T' => T_ZBX_INT, // type
		'M' => $metrics // metrics
	),
	array(
		'C' => _('Mask'), // caption
		'T' => T_ZBX_STR
	),
	array(
		'C' => _('Time shift').' ', // caption
		'T' => T_ZBX_INT // type
	)
);
$allowedTypesAny = array(
	ITEM_VALUE_TYPE_FLOAT => 1,
	ITEM_VALUE_TYPE_STR => 1,
	ITEM_VALUE_TYPE_LOG => 1,
	ITEM_VALUE_TYPE_UINT64 => 1,
	ITEM_VALUE_TYPE_TEXT => 1
);
$allowedTypesNumeric = array(
	ITEM_VALUE_TYPE_FLOAT => 1,
	ITEM_VALUE_TYPE_UINT64 => 1
);
$allowedTypesStr = array(
	ITEM_VALUE_TYPE_STR => 1,
	ITEM_VALUE_TYPE_LOG => 1,
	ITEM_VALUE_TYPE_TEXT => 1
);
$allowedTypesLog = array(
	ITEM_VALUE_TYPE_LOG => 1
);
$allowedTypesInt = array(
	ITEM_VALUE_TYPE_UINT64 => 1
);

$functions = array(
	'abschange[<]' => array(
		'description' =>  _('Absolute difference between last and previous value is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'abschange[>]' => array(
		'description' =>  _('Absolute difference between last and previous value is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'abschange[=]' => array(
		'description' =>  _('Absolute difference between last and previous value is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'abschange[#]' => array(
		'description' =>  _('Absolute difference between last and previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'avg[<]' => array(
		'description' =>  _('Average value of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'avg[>]' => array(
		'description' =>  _('Average value of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'avg[=]' => array(
		'description' =>  _('Average value of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'avg[#]' => array(
		'description' =>  _('Average value of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'delta[<]' => array(
		'description' =>  _('Difference between MAX and MIN value of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'delta[>]' => array(
		'description' =>  _('Difference between MAX and MIN value of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'delta[=]' => array(
		'description' =>  _('Difference between MAX and MIN value of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'delta[#]' => array(
		'description' =>  _('Difference between MAX and MIN value of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'change[<]' => array(
		'description' =>  _('Difference between last and previous value is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'change[>]' => array(
		'description' =>  _('Difference between last and previous value is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'change[=]' => array(
		'description' =>  _('Difference between last and previous value is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'change[#]' => array(
		'description' =>  _('Difference between last and previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'count[<]' => array(
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is < N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	),
	'count[>]' => array(
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is > N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	),
	'count[=]' => array(
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is = N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	),
	'count[#]' => array(
		'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is NOT N'),
		'params' => $param3SecVal,
		'allowed_types' => $allowedTypesAny
	),
	'diff[=]' => array(
		'description' =>  _('Difference between last and preceding values, then N = 1, 0 - otherwise'),
		'allowed_types' => $allowedTypesAny
	),
	'diff[#]' => array(
		'description' =>  _('Difference between last and preceding values, then N NOT 1, 0 - otherwise'),
		'allowed_types' => $allowedTypesAny
	),
	'last[<]' => array(
		'description' =>  _('Last (most recent) T value is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'last[>]' => array(
		'description' =>  _('Last (most recent) T value is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'last[=]' => array(
		'description' =>  _('Last (most recent) T value is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'last[#]' => array(
		'description' =>  _('Last (most recent) T value is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'max[<]' => array(
		'description' =>  _('Maximum value for period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'max[>]' => array(
		'description' =>  _('Maximum value for period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'max[=]' => array(
		'description' =>  _('Maximum value for period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'max[#]' => array(
		'description' =>  _('Maximum value for period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'min[<]' => array(
		'description' =>  _('Minimum value for period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		),
	'min[>]' => array(
		'description' =>  _('Minimum value for period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		),
	'min[=]' => array(
		'description' =>  _('Minimum value for period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		),
	'min[#]' => array(
		'description' =>  _('Minimum value for period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
		),
	'prev[<]' => array(
		'description' =>  _('Previous value is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'prev[>]' => array(
		'description' =>  _('Previous value is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'prev[=]' => array(
		'description' =>  _('Previous value is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'prev[#]' => array(
		'description' =>  _('Previous value is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'str[=]' => array(
		'description' =>  _('Find string V in last (most recent) value. N = 1 - if found, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'str[#]' => array(
		'description' =>  _('Find string V in last (most recent) value. N NOT 1 - if found, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'strlen[<]' => array(
		'description' =>  _('Length of last (most recent) T value in characters is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	),
	'strlen[>]' => array(
		'description' =>  _('Length of last (most recent) T value in characters is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	),
	'strlen[=]' => array(
		'description' =>  _('Length of last (most recent) T value in characters is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	),
	'strlen[#]' => array(
		'description' =>  _('Length of last (most recent) T value in characters is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesStr
	),
	'sum[<]' => array(
		'description' =>  _('Sum of values of a period T is < N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'sum[>]' => array(
		'description' =>  _('Sum of values of a period T is > N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'sum[=]' => array(
		'description' =>  _('Sum of values of a period T is = N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'sum[#]' => array(
		'description' =>  _('Sum of values of a period T is NOT N'),
		'params' => $param1SecCount,
		'allowed_types' => $allowedTypesNumeric
	),
	'date[<]' => array(
		'description' =>  _('Current date is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'date[>]' => array(
		'description' =>  _('Current date is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'date[=]' => array(
		'description' =>  _('Current date is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'date[#]' => array(
		'description' =>  _('Current date is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofweek[<]' => array(
		'description' =>  _('Day of week is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofweek[>]' => array(
		'description' =>  _('Day of week is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofweek[=]' => array(
		'description' =>  _('Day of week is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofweek[#]' => array(
		'description' =>  _('Day of week is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofmonth[<]' => array(
		'description' =>  _('Day of month is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofmonth[>]' => array(
		'description' =>  _('Day of month is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofmonth[=]' => array(
		'description' =>  _('Day of month is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'dayofmonth[#]' => array(
		'description' =>  _('Day of month is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'fuzzytime[=]' => array(
		'description' =>  _('Timestamp not different from Zabbix server time for more than T seconds, then N = 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	),
	'fuzzytime[#]' => array(
		'description' =>  _('Timestamp not different from Zabbix server time for more than T seconds, then N NOT 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	),
	'regexp[=]' => array(
		'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'regexp[#]' => array(
		'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'iregexp[=]' => array(
		'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise (non case-sensitive)'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'iregexp[#]' => array(
		'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise (non case-sensitive)'),
		'params' => $param2SecCount,
		'allowed_types' => $allowedTypesAny
	),
	'logeventid[=]' => array(
		'description' =>  _('Event ID of last log entry matching regular expression T, then N = 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	),
	'logeventid[#]' => array(
		'description' =>  _('Event ID of last log entry matching regular expression T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	),
	'logseverity[<]' => array(
		'description' =>  _('Log severity of the last log entry is < N'),
		'allowed_types' => $allowedTypesLog
	),
	'logseverity[>]' => array(
		'description' =>  _('Log severity of the last log entry is > N'),
		'allowed_types' => $allowedTypesLog
	),
	'logseverity[=]' => array(
		'description' =>  _('Log severity of the last log entry is = N'),
		'allowed_types' => $allowedTypesLog
	),
	'logseverity[#]' => array(
		'description' =>  _('Log severity of the last log entry is NOT N'),
		'allowed_types' => $allowedTypesLog
	),
	'logsource[=]' => array(
		'description' =>  _('Log source of the last log entry matching parameter T, then N = 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	),
	'logsource[#]' => array(
		'description' =>  _('Log source of the last log entry matching parameter T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Str,
		'allowed_types' => $allowedTypesLog
	),
	'now[<]' => array(
		'description' =>  _('Number of seconds since the Epoch is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'now[>]' => array(
		'description' =>  _('Number of seconds since the Epoch is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'now[=]' => array(
		'description' =>  _('Number of seconds since the Epoch is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'now[#]' => array(
		'description' =>  _('Number of seconds since the Epoch is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'time[<]' => array(
		'description' =>  _('Current time is < N'),
		'allowed_types' => $allowedTypesAny
	),
	'time[>]' => array(
		'description' =>  _('Current time is > N'),
		'allowed_types' => $allowedTypesAny
	),
	'time[=]' => array(
		'description' =>  _('Current time is = N'),
		'allowed_types' => $allowedTypesAny
	),
	'time[#]' => array(
		'description' =>  _('Current time is NOT N'),
		'allowed_types' => $allowedTypesAny
	),
	'nodata[=]' => array(
		'description' =>  _('No data received during period of time T, then N = 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	),
	'nodata[#]' => array(
		'description' =>  _('No data received during period of time T, then N NOT 1, 0 - otherwise'),
		'params' => $param1Sec,
		'allowed_types' => $allowedTypesAny
	),
	'band[=]' => array(
		'description' =>  _('Bitwise AND of last (most recent) T value and mask is = N'),
		'params' => $paramSecIntCount,
		'allowed_types' => $allowedTypesInt
	),
	'band[#]' => array(
		'description' =>  _('Bitwise AND of last (most recent) T value and mask is NOT N'),
		'params' => $paramSecIntCount,
		'allowed_types' => $allowedTypesInt
	)
);
order_result($functions, 'description');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>				array(T_ZBX_STR, O_MAND, P_SYS, NOT_EMPTY,	null),
	'dstfld1' =>			array(T_ZBX_STR, O_MAND, P_SYS, NOT_EMPTY,	null),
	'expression' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'itemid' =>				array(T_ZBX_INT, O_OPT, null,	null,		'isset({insert})'),
	'parent_discoveryid' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'expr_type'=>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({insert})'),
	'param' =>				array(T_ZBX_STR, O_OPT, null,	0,			'isset({insert})'),
	'paramtype' =>			array(T_ZBX_INT, O_OPT, null,	IN(PARAM_TYPE_TIME.','.PARAM_TYPE_COUNTS), 'isset({insert})'),
	'value' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({insert})'),
	// action
	'insert' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null)
);
check_fields($fields);

if (isset($_REQUEST['expression']) && $_REQUEST['dstfld1'] == 'expr_temp') {
	$_REQUEST['expression'] = utf8RawUrlDecode($_REQUEST['expression']);

	$expressionData = new CTriggerExpression();

	if ($expressionData->parse($_REQUEST['expression']) && count($expressionData->expressions) == 1) {
		$exprPart = reset($expressionData->expressions);

		preg_match('/\}([=><#]{1})([0-9]+['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)$/', $_REQUEST['expression'],
			$exprSymbols
		);

		if (isset($exprSymbols[1])) {
			$_REQUEST['expr_type'] = $exprPart['functionName'].'['.$exprSymbols[1].']';
		}

		$_REQUEST['description'] = $exprPart['host'].':'.$exprPart['item'];
		$_REQUEST['param'] = $exprPart['functionParamList'];

		$paramNumber = in_array($exprPart['functionName'], array('regexp', 'iregexp', 'str')) ? 1 : 0;

		if (isset($_REQUEST['param'][$paramNumber][0]) && $_REQUEST['param'][$paramNumber][0] == '#') {
			$_REQUEST['paramtype'] = PARAM_TYPE_COUNTS;
			$_REQUEST['param'][$paramNumber] = substr($_REQUEST['param'][$paramNumber], 1);
		}
		else {
			$_REQUEST['paramtype'] = PARAM_TYPE_TIME;
		}

		if (isset($exprSymbols[2])) {
			$_REQUEST['value'] = $exprSymbols[2];
		}

		$myItem = API::Item()->get(array(
			'filter' => array('host' => $exprPart['host'], 'key_' => $exprPart['item'], 'flags' => null),
			'output' => array('itemid'),
			'webitems' => true
		));
		$myItem = reset($myItem);

		if (isset($myItem['itemid'])) {
			$_REQUEST['itemid'] = $myItem['itemid'];
		}
		else {
			error(_('Unknown host item, no such item in selected host'));
		}
	}
}

$exprType = get_request('expr_type', 'last[=]');
$exprType = array_key_exists($exprType, $functions) ? $exprType : 'last[=]';

if (preg_match('/^([a-z]+)\[(['.implode('', array_keys($operators)).'])\]$/i', $exprType, $exprRes)) {
	$function = $exprRes[1];
	$operator = $exprRes[2];
}

$dstfrm = get_request('dstfrm', 0);
$dstfld1 = get_request('dstfld1', '');
$itemId = get_request('itemid', 0);
$value = get_request('value', 0);
$param = get_request('param', 0);
$paramType = get_request('paramtype');

if ($itemId) {
	$items = API::Item()->get(array(
		'output' => array('itemid', 'hostid', 'name', 'key_'),
		'itemids' => $itemId,
		'webitems' => true,
		'selectHosts' => array('host'),
		'filter' => array('flags' => null)
	));

	$items = CMacrosResolverHelper::resolveItemNames($items);

	$item = reset($items);
	$itemKey = $item['key_'];
	$itemHost = reset($item['hosts']);
	$itemHost = $itemHost['host'];
	$description = $itemHost.NAME_DELIMITER.$item['name_expanded'];
}
else {
	$itemKey = $itemHost = $description = '';
}

if (is_null($paramType) && isset($functions[$exprType]['params']['M'])) {
	$paramType = is_array($functions[$exprType]['params']['M']) ? reset($functions[$exprType]['params']['M']) : $functions[$exprType]['params']['M'];
}
elseif (is_null($paramType)) {
	$paramType = PARAM_TYPE_TIME;
}

if (!is_array($param)) {
	if (isset($functions[$exprType]['params'])) {
		$param = explode(',', $param, count($functions[$exprType]['params']));
	}
	else {
		$param = array($param);
	}
}

/*
 * Display
 */
$data = array(
	'parent_discoveryid' => get_request('parent_discoveryid', null),
	'dstfrm' => $dstfrm,
	'dstfld1' => $dstfld1,
	'itemid' => $itemId,
	'value' => $value,
	'param' => $param,
	'paramtype' => $paramType,
	'description' => $description,
	'functions' => $functions,
	'function' => $function,
	'operator' => $operator,
	'item_host' => $itemHost,
	'item_key' => $itemKey,
	'itemValueType' => null,
	'expr_type' => $exprType,
	'insert' => get_request('insert', null),
	'cancel' => get_request('cancel', null)
);

// if user has already selected an item
if ($itemId) {
	// get item value type
	$selectedItems = API::Item()->get(array(
		'itemids' => array($itemId),
		'output' => array('value_type'),
		'filter' => array('flags' => null),
		'webitems' => true
	));

	if ($selectedItem = reset($selectedItems)) {
		$data['itemValueType'] = $selectedItem['value_type'];
	}
}

$submittedFunction = $data['function'].'['.$data['operator'].']';
$data['selectedFunction'] = null;

// check if submitted function is usable with selected item
foreach ($data['functions'] as $id => $f) {
	if ((!$data['itemValueType'] || isset($f['allowed_types'][$data['itemValueType']])) && $id == $submittedFunction) {
		$data['selectedFunction'] = $id;
		break;
	}
}

if ($data['selectedFunction'] === null) {
	error(_s('Function "%1$s" cannot be used with selected item "%2$s"',
		$data['functions'][$submittedFunction]['description'],
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
				$paramNumber = in_array($data['function'], array('regexp', 'iregexp', 'str')) ? 1 : 0;
				$data['param'][$paramNumber] = '#'.$data['param'][$paramNumber];
			}

			if ($data['paramtype'] == PARAM_TYPE_TIME && in_array($data['function'], array('last', 'band', 'strlen'))) {
				$data['param'][0] = '';
			}

			// quote function param
			$params = array();
			foreach ($data['param'] as $param) {
				$params[] = quoteFunctionParam($param);
			}

			$data['expression'] = sprintf('{%s:%s.%s(%s)}%s%s',
				$data['item_host'],
				$data['item_key'],
				$data['function'],
				rtrim(implode(',', $params), ','),
				$data['operator'],
				$data['value']
			);

			// validate trigger expression
			$triggerExpression = new CTriggerExpression();

			if ($triggerExpression->parse($data['expression'])) {
				$expressionData = reset($triggerExpression->expressions);

				// validate trigger function
				$triggerFunctionValidator = new CTriggerFunctionValidator();
				$isValid = $triggerFunctionValidator->validate(array(
					'function' => $expressionData['function'],
					'functionName' => $expressionData['functionName'],
					'functionParamList' => $expressionData['functionParamList'],
					'valueType' => $data['itemValueType']
				));
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
				foreach ($data['param'] as $pnum => $param) {
					$data['param'][$pnum] = quoteFunctionParam($param);
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
