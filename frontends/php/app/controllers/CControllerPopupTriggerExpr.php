<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerPopupTriggerExpr extends CController {
	private $metrics = [];
	private $param1SecCount = [];
	private $param1Sec = [];
	private $param1Str = [];
	private $param2SecCount = [];
	private $param3SecVal = [];
	private $param3SecPercent = [];
	private $paramSecIntCount = [];
	private $paramForecast = [];
	private $paramTimeleft = [];
	private $allowedTypesAny = [];
	private $allowedTypesNumeric = [];
	private $allowedTypesStr = [];
	private $allowedTypesLog = [];
	private $allowedTypesInt = [];
	private $functions = [];

	protected function init() {
		$this->disableSIDvalidation();

		$this->metrics = [
			PARAM_TYPE_TIME => _('Time'),
			PARAM_TYPE_COUNTS => _('Count')
		];

		$this->param1SecCount = [
			[
				'C' => _('Last of').' (T)',	// caption
				'T' => T_ZBX_INT,			// type
				'M' => $this->metrics		// metrics
			],
			[
				'C' => _('Time shift'),
				'T' => T_ZBX_INT
			]
		];

		$this->param1Sec = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT
			]
		];

		$this->param1Str = [
			[
				'C' => 'T',
				'T' => T_ZBX_STR
			]
		];

		$this->param2SecCount = [
			[
				'C' => 'V',
				'T' => T_ZBX_STR
			],
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
			]
		];

		$this->param3SecVal = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
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

		$this->param3SecPercent = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
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

		$this->paramSecIntCount = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
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

		$this->paramForecast = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
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

		$this->paramTimeleft = [
			[
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics
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

		$this->allowedTypesAny = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_UINT64 => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesNumeric = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->allowedTypesStr = [
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesLog = [
			ITEM_VALUE_TYPE_LOG => 1
		];

		$this->allowedTypesInt = [
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->functions = [
			'abschange[<]' => [
				'description' =>  _('Absolute difference between last and previous value is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'abschange[>]' => [
				'description' =>  _('Absolute difference between last and previous value is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'abschange[=]' => [
				'description' =>  _('Absolute difference between last and previous value is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'abschange[<>]' => [
				'description' =>  _('Absolute difference between last and previous value is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'avg[<]' => [
				'description' =>  _('Average value of a period T is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'avg[>]' => [
				'description' =>  _('Average value of a period T is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'avg[=]' => [
				'description' =>  _('Average value of a period T is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'avg[<>]' => [
				'description' =>  _('Average value of a period T is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'delta[<]' => [
				'description' =>  _('Difference between MAX and MIN value of a period T is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'delta[>]' => [
				'description' =>  _('Difference between MAX and MIN value of a period T is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'delta[=]' => [
				'description' =>  _('Difference between MAX and MIN value of a period T is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'delta[<>]' => [
				'description' =>  _('Difference between MAX and MIN value of a period T is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'change[<]' => [
				'description' =>  _('Difference between last and previous value is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'change[>]' => [
				'description' =>  _('Difference between last and previous value is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'change[=]' => [
				'description' =>  _('Difference between last and previous value is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'change[<>]' => [
				'description' =>  _('Difference between last and previous value is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'count[<]' => [
				'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is < N'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny
			],
			'count[>]' => [
				'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is > N'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny
			],
			'count[=]' => [
				'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is = N'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny
			],
			'count[<>]' => [
				'description' =>  _('Number of successfully retrieved values V (which fulfill operator O) for period T is NOT N'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny
			],
			'diff[=]' => [
				'description' =>  _('Difference between last and preceding values, then N = 1, 0 - otherwise'),
				'allowed_types' => $this->allowedTypesAny
			],
			'diff[<>]' => [
				'description' =>  _('Difference between last and preceding values, then N NOT 1, 0 - otherwise'),
				'allowed_types' => $this->allowedTypesAny
			],
			'last[<]' => [
				'description' =>  _('Last (most recent) T value is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'last[>]' => [
				'description' =>  _('Last (most recent) T value is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'last[=]' => [
				'description' =>  _('Last (most recent) T value is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'last[<>]' => [
				'description' =>  _('Last (most recent) T value is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'max[<]' => [
				'description' =>  _('Maximum value for period T is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'max[>]' => [
				'description' =>  _('Maximum value for period T is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'max[=]' => [
				'description' =>  _('Maximum value for period T is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'max[<>]' => [
				'description' =>  _('Maximum value for period T is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'min[<]' => [
				'description' =>  _('Minimum value for period T is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
				],
			'min[>]' => [
				'description' =>  _('Minimum value for period T is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
				],
			'min[=]' => [
				'description' =>  _('Minimum value for period T is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
				],
			'min[<>]' => [
				'description' =>  _('Minimum value for period T is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
				],
			'percentile[<]' => [
				'description' =>  _('Percentile P of a period T is < N'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'percentile[>]' => [
				'description' =>  _('Percentile P of a period T is > N'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'percentile[=]' => [
				'description' =>  _('Percentile P of a period T is = N'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'percentile[<>]' => [
				'description' =>  _('Percentile P of a period T is NOT N'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'prev[<]' => [
				'description' =>  _('Previous value is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'prev[>]' => [
				'description' =>  _('Previous value is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'prev[=]' => [
				'description' =>  _('Previous value is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'prev[<>]' => [
				'description' =>  _('Previous value is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'str[=]' => [
				'description' =>  _('Find string V in last (most recent) value. N = 1 - if found, 0 - otherwise'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'str[<>]' => [
				'description' =>  _('Find string V in last (most recent) value. N NOT 1 - if found, 0 - otherwise'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'strlen[<]' => [
				'description' =>  _('Length of last (most recent) T value in characters is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr
			],
			'strlen[>]' => [
				'description' =>  _('Length of last (most recent) T value in characters is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr
			],
			'strlen[=]' => [
				'description' =>  _('Length of last (most recent) T value in characters is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr
			],
			'strlen[<>]' => [
				'description' =>  _('Length of last (most recent) T value in characters is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr
			],
			'sum[<]' => [
				'description' =>  _('Sum of values of a period T is < N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'sum[>]' => [
				'description' =>  _('Sum of values of a period T is > N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'sum[=]' => [
				'description' =>  _('Sum of values of a period T is = N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'sum[<>]' => [
				'description' =>  _('Sum of values of a period T is NOT N'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'date[<]' => [
				'description' =>  _('Current date is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'date[>]' => [
				'description' =>  _('Current date is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'date[=]' => [
				'description' =>  _('Current date is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'date[<>]' => [
				'description' =>  _('Current date is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofweek[<]' => [
				'description' =>  _('Day of week is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofweek[>]' => [
				'description' =>  _('Day of week is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofweek[=]' => [
				'description' =>  _('Day of week is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofweek[<>]' => [
				'description' =>  _('Day of week is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofmonth[<]' => [
				'description' =>  _('Day of month is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofmonth[>]' => [
				'description' =>  _('Day of month is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofmonth[=]' => [
				'description' =>  _('Day of month is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'dayofmonth[<>]' => [
				'description' =>  _('Day of month is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'fuzzytime[=]' => [
				'description' =>  _('Difference between item timestamp value and Zabbix server timestamp is over T seconds, then N = 0, 1 - otherwise'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesAny
			],
			'fuzzytime[<>]' => [
				'description' =>  _('Difference between item timestamp value and Zabbix server timestamp is over T seconds, then N NOT 0, 1 - otherwise'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesAny
			],
			'regexp[=]' => [
				'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'regexp[<>]' => [
				'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'iregexp[=]' => [
				'description' =>  _('Regular expression V matching last value in period T, then N = 1, 0 - otherwise (non case-sensitive)'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'iregexp[<>]' => [
				'description' =>  _('Regular expression V matching last value in period T, then N NOT 1, 0 - otherwise (non case-sensitive)'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesAny
			],
			'logeventid[=]' => [
				'description' =>  _('Event ID of last log entry matching regular expression T, then N = 1, 0 - otherwise'),
				'params' => $this->param1Str,
				'allowed_types' => $this->allowedTypesLog
			],
			'logeventid[<>]' => [
				'description' =>  _('Event ID of last log entry matching regular expression T, then N NOT 1, 0 - otherwise'),
				'params' => $this->param1Str,
				'allowed_types' => $this->allowedTypesLog
			],
			'logseverity[<]' => [
				'description' =>  _('Log severity of the last log entry is < N'),
				'allowed_types' => $this->allowedTypesLog
			],
			'logseverity[>]' => [
				'description' =>  _('Log severity of the last log entry is > N'),
				'allowed_types' => $this->allowedTypesLog
			],
			'logseverity[=]' => [
				'description' =>  _('Log severity of the last log entry is = N'),
				'allowed_types' => $this->allowedTypesLog
			],
			'logseverity[<>]' => [
				'description' =>  _('Log severity of the last log entry is NOT N'),
				'allowed_types' => $this->allowedTypesLog
			],
			'logsource[=]' => [
				'description' =>  _('Log source of the last log entry matching parameter T, then N = 1, 0 - otherwise'),
				'params' => $this->param1Str,
				'allowed_types' => $this->allowedTypesLog
			],
			'logsource[<>]' => [
				'description' =>  _('Log source of the last log entry matching parameter T, then N NOT 1, 0 - otherwise'),
				'params' => $this->param1Str,
				'allowed_types' => $this->allowedTypesLog
			],
			'now[<]' => [
				'description' =>  _('Number of seconds since the Epoch is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'now[>]' => [
				'description' =>  _('Number of seconds since the Epoch is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'now[=]' => [
				'description' =>  _('Number of seconds since the Epoch is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'now[<>]' => [
				'description' =>  _('Number of seconds since the Epoch is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'time[<]' => [
				'description' =>  _('Current time is < N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'time[>]' => [
				'description' =>  _('Current time is > N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'time[=]' => [
				'description' =>  _('Current time is = N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'time[<>]' => [
				'description' =>  _('Current time is NOT N'),
				'allowed_types' => $this->allowedTypesAny
			],
			'nodata[=]' => [
				'description' =>  _('No data received during period of time T, then N = 1, 0 - otherwise'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesAny
			],
			'nodata[<>]' => [
				'description' =>  _('No data received during period of time T, then N NOT 1, 0 - otherwise'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesAny
			],
			'band[=]' => [
				'description' =>  _('Bitwise AND of last (most recent) T value and mask is = N'),
				'params' => $this->paramSecIntCount,
				'allowed_types' => $this->allowedTypesInt
			],
			'band[<>]' => [
				'description' =>  _('Bitwise AND of last (most recent) T value and mask is NOT N'),
				'params' => $this->paramSecIntCount,
				'allowed_types' => $this->allowedTypesInt
			],
			'forecast[<]' => [
				'description' => _('Forecast for next t seconds based on period T is < N'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'forecast[>]' => [
				'description' => _('Forecast for next t seconds based on period T is > N'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'forecast[=]' => [
				'description' => _('Forecast for next t seconds based on period T is = N'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'forecast[<>]' => [
				'description' => _('Forecast for next t seconds based on period T is NOT N'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'timeleft[<]' => [
				'description' => _('Time to reach threshold estimated based on period T is < N'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'timeleft[>]' => [
				'description' => _('Time to reach threshold estimated based on period T is > N'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'timeleft[=]' => [
				'description' => _('Time to reach threshold estimated based on period T is = N'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric
			],
			'timeleft[<>]' => [
				'description' => _('Time to reach threshold estimated based on period T is NOT N'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric
			]
		];

		CArrayHelper::sort($this->functions, ['description']);
	}

	protected function checkInput() {
		$fields = [
			'dstfrm' =>				'string|fatal',
			'dstfld1' =>			'string|not_empty',
			'expression' =>			'string',
			'itemid' =>				'db items.itemid',
			'parent_discoveryid' =>	'int32',
			'expr_type' =>			'string|not_empty',
			'params' =>				'',
			'paramtype' =>			'in '.implode(',', [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]),
			'value' =>				'string|not_empty',
			'hostid' =>				'db hosts.hostid',
			'groupid' =>			'db hosts_groups.hostgroupid',
			'add' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$itemid = $this->getInput('itemid', 0);
		$expr_type = $this->getInput('expr_type', 'last[=]');
		$param_type = $this->getInput('paramtype', PARAM_TYPE_TIME);
		$dstfld1 = $this->getInput('dstfld1');
		$expression = $this->getInput('expression', '');
		$params = $this->getInput('params', []);
		$value = $this->getInput('value', 0);

		// Opening the popup when editing an expression in the trigger constructor.
		if (($dstfld1 === 'expr_temp' || $dstfld1 === 'recovery_expr_temp') && $expression !== '') {
			$expression = utf8RawUrlDecode($expression);

			$expression_data = new CTriggerExpression();
			$result = $expression_data->parse($expression);

			if ($result) {
				// Only one item function macro is supported in an expression.
				$function_macro_tokens = $result->getTokensByType(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO);
				if (count($function_macro_tokens) == 1) {
					$function_macro_token = $function_macro_tokens[0];
					$function = $function_macro_token['data']['functionName'];

					// Determine param type.
					$params = $function_macro_token['data']['functionParams'];
					$param_number = in_array($function, ['regexp', 'iregexp', 'str']) ? 1 : 0;
					if (array_key_exists($param_number, $params) && is_string($params[$param_number])
							&& $params[$param_number] !== '' && $params[$param_number][0] === '#') {
						$param_type = PARAM_TYPE_COUNTS;
						$params[$param_number] = substr($params[$param_number], 1);
					}
					else {
						$param_type = PARAM_TYPE_TIME;
					}

					// Define default operator.
					$operator = '=';

					/*
					 * Try to find an operator and a numeric value.
					 * The value and operator can be extracted only if the immediately follow the item function macro.
					 */
					$tokens = $result->getTokens();
					foreach ($tokens as $key => $token) {
						if ($token['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO) {
							if (array_key_exists($key + 2, $tokens)
									&& $tokens[$key + 1]['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR
									&& array_key_exists($function.'['.$tokens[$key + 1]['value'].']', $this->functions)
									&& $tokens[$key + 2]['type'] == CTriggerExpressionParserResult::TOKEN_TYPE_NUMBER) {

								$operator = $tokens[$key + 1]['value'];
								$value = $tokens[$key + 2]['value'];
							}
							else {
								break;
							}
						}
					}

					$expr_type = $function.'['.$operator.']';

					// Find the item.
					$item = API::Item()->get([
						'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
						'selectHosts' => ['name'],
						'webitems' => true,
						'filter' => [
							'host' => $function_macro_token['data']['host'],
							'key_' => $function_macro_token['data']['item'],
							'flags' => null
						]
					]);

					if (($item = reset($item)) !== false) {
						$itemid = $item['itemid'];
					}
					else {
						error(_('Unknown host item, no such item in selected host'));
					}
				}
			}
		}
		// Opening an empty form or switching a function.
		else {
			if (preg_match('/^([a-z]+)\[([=><]{1,2})\]$/i', $expr_type, $matches)) {
				$function = $matches[1];
				$operator = $matches[2];

				if (!array_key_exists($expr_type, $this->functions)) {
					unset($function);
				}
			}

			// Fetch item.
			$item = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
				'selectHosts' => ['host', 'name'],
				'itemids' => $itemid,
				'webitems' => true,
				'filter' => ['flags' => null]
			]);

			$item = reset($item);
		}

		if ($itemid) {
			$items = CMacrosResolverHelper::resolveItemNames([$item]);
			$item = $items[0];

			$item_value_type = $item['value_type'];
			$item_key = $item['key_'];
			$item_host_data = reset($item['hosts']);
			$description = $item_host_data['name'].NAME_DELIMITER.$item['name_expanded'];
		}
		else {
			$item_key = '';
			$description = '';
			$item_value_type = null;
		}

		if ($param_type === null && array_key_exists($expr_type, $this->functions)
				&& array_key_exists('params', $this->functions[$expr_type])
				&& array_key_exists('M', $this->functions[$expr_type]['params'])) {
			$param_type = is_array($this->functions[$expr_type]['params']['M'])
				? reset($this->functions[$expr_type]['params']['M'])
				: $this->functions[$expr_type]['params']['M'];
		}
		elseif ($param_type === null) {
			$param_type = PARAM_TYPE_TIME;
		}

		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
			'dstfrm' => $this->getInput('dstfrm'),
			'dstfld1' => $dstfld1,
			'itemid' => $itemid,
			'value' => $value,
			'params' => $params,
			'paramtype' => $param_type,
			'description' => $description,
			'functions' => $this->functions,
			'item_key' => $item_key,
			'itemValueType' => $item_value_type,
			'selectedFunction' => null,
			'expr_type' => $expr_type,
			'groupid' => $this->getInput('groupid', 0),
			'hostid' => $this->getInput('hostid', 0),
		];

		// Check if submitted function is usable with selected item.
		foreach ($data['functions'] as $id => $f) {
			if ((!$data['itemValueType'] || array_key_exists($item_value_type, $f['allowed_types']))
					&& $id == $expr_type) {
				$data['selectedFunction'] = $id;
				break;
			}
		}

		if ($data['selectedFunction'] === null) {
			error(_s('Function "%1$s" cannot be used with selected item "%2$s"',
				$data['functions'][$expr_type]['description'],
				$data['description']
			));
		}

		// Remove functions that not correspond to chosen item.
		foreach ($data['functions'] as $id => $f) {
			if ($data['itemValueType'] && !array_key_exists($data['itemValueType'], $f['allowed_types'])) {
				unset($data['functions'][$id]);
			}
		}

		// Create and validate trigger expression before inserting it into textarea field.
		if ($this->getInput('add', false)) {
			try {
				if ($data['description']) {
					if ($data['paramtype'] == PARAM_TYPE_COUNTS) {
						$param_number = in_array($function, ['regexp', 'iregexp', 'str']) ? 1 : 0;
						$data['params'][$param_number] = '#'.$data['params'][$param_number];
					}

					if ($data['paramtype'] == PARAM_TYPE_TIME && in_array($function, ['last', 'band', 'strlen'])) {
						$data['params'][0] = '';
					}

					// Quote function param.
					$quoted_params = [];
					foreach ($data['params'] as $param) {
						$quoted_params[] = quoteFunctionParam($param);
					}

					$data['expression'] = sprintf('{%s:%s.%s(%s)}%s%s',
						$item_host_data['host'],
						$data['item_key'],
						$function,
						rtrim(implode(',', $quoted_params), ','),
						$operator,
						$data['value']
					);

					// Validate trigger expression.
					$trigger_expression = new CTriggerExpression();

					if ($trigger_expression->parse($data['expression'])) {
						$expression_data = reset($trigger_expression->expressions);

						// Validate trigger function.
						$trigger_function_validator = new CFunctionValidator();
						$is_valid = $trigger_function_validator->validate([
							'function' => $expression_data['function'],
							'functionName' => $expression_data['functionName'],
							'functionParamList' => $expression_data['functionParamList'],
							'valueType' => $data['itemValueType']
						]);

						if ($is_valid === false) {
							error($trigger_function_validator->getError());
						}
					}
					else {
						error($trigger_expression->error);
					}

					// Quote function param.
					if (array_key_exists('insert', $data)) {
						foreach ($data['params'] as $pnum => $param) {
							$data['params'][$pnum] = quoteFunctionParam($param);
						}
					}
				}
				else {
					error(_('Item not selected'));
				}
			}
			catch (Exception $e) {
				error($e->getMessage());
				error(_('Cannot insert trigger expression'));
			}

			if (($messages = getMessages()) !== null) {
				$output = [
					'errors' => $messages->toString()
				];
			}
			else {
				$output = [
					'expression' => $data['expression'],
					'dstfld1' => $data['dstfld1'],
					'dstfrm' => $data['dstfrm']
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}
		else {
			$this->setResponse(new CControllerResponseData(
				$data + [
					'title' => _('Condition'),
					'errors' => hasErrorMesssages() ? getMessages() : null,
					'user' => [
						'debug_mode' => $this->getDebugMode()
					]
				]
			));
		}
	}
}
