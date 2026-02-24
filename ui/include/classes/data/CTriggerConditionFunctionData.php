<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * Class containing information on trigger expression conditions.
 *
 * Must be aligned with CHistFunctionData and CMathFunctionData and triggers.inc.php:get_item_function_info().
 */
final class CTriggerConditionFunctionData {

	public const OPERATORS = ['=', '<>', '>', '<', '>=', '<='];
	public const OPERATORS_BOOL = ['=', '<>'];

	private const ITEM_VALUE_TYPES_NUM = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
	private const ITEM_VALUE_TYPES_INT = [ITEM_VALUE_TYPE_UINT64];
	private const ITEM_VALUE_TYPES_LOG = [ITEM_VALUE_TYPE_LOG];
	private const ITEM_VALUE_TYPES_STRING = [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT];
	private const ITEM_VALUE_TYPES_ALL = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR,
		ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG
	];

	/**
	 * Known functions along with their group and supported item value types.
	 *
	 * @var array
	 */
	private const VALUE_TYPES = [
		'abs' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_ALL],
		'acos' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'ascii' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'asin' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'atan' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'atan2' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'avg' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM,
			ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'between' => [ZBX_FUNCTION_TYPE_OPERATOR => self::ITEM_VALUE_TYPES_NUM],
		'bitand' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bitlength' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_ALL],
		'bitlshift' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bitnot' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bitor' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bitrshift' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bitxor' => [ZBX_FUNCTION_TYPE_BITWISE => self::ITEM_VALUE_TYPES_INT],
		'bytelength' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_ALL],
		'cbrt' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'ceil' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'change' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'changecount' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'char' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_INT],
		'concat' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_ALL],
		'cos' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'cosh' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'cot' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'count' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_ALL],
		'countunique' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_ALL],
		'date' => [ZBX_FUNCTION_TYPE_DATE_TIME => self::ITEM_VALUE_TYPES_ALL],
		'dayofmonth' => [ZBX_FUNCTION_TYPE_DATE_TIME => self::ITEM_VALUE_TYPES_ALL],
		'dayofweek' => [ZBX_FUNCTION_TYPE_DATE_TIME => self::ITEM_VALUE_TYPES_ALL],
		'degrees' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'e' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_ALL],
		'exp' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'expm1' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'find' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_ALL],
		'first' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'firstclock' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'floor' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'forecast' => [ZBX_FUNCTION_TYPE_PREDICTION => self::ITEM_VALUE_TYPES_NUM],
		'fuzzytime' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'in' => [ZBX_FUNCTION_TYPE_OPERATOR => self::ITEM_VALUE_TYPES_ALL],
		'insert' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'jsonpath' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'kurtosis' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'last' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'lastclock' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'left' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'length' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'log' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'log10' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'logeventid' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_LOG],
		'logseverity' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_LOG],
		'logsource' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_LOG],
		'logtimestamp' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_LOG],
		'ltrim' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'mad' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'max' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM,
			ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'mid' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'min' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM,
			ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'mod' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'monodec' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'monoinc' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'nodata' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'now' => [ZBX_FUNCTION_TYPE_DATE_TIME => self::ITEM_VALUE_TYPES_ALL],
		'percentile' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'pi' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_ALL],
		'power' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'radians' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'rand' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'rate' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'repeat' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'replace' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'right' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'round' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'rtrim' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'signum' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'sin' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'sinh' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'skewness' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'sqrt' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'stddevpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'stddevsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'sum' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM,
			ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'sumofsquares' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'tan' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_NUM],
		'time' => [ZBX_FUNCTION_TYPE_DATE_TIME => self::ITEM_VALUE_TYPES_ALL],
		'timeleft' => [ZBX_FUNCTION_TYPE_PREDICTION => self::ITEM_VALUE_TYPES_NUM],
		'trendavg' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'baselinedev' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'baselinewma' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'trendcount' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_ALL],
		'trendmax' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'trendmin' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'trendstl' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'trendsum' => [ZBX_FUNCTION_TYPE_HISTORY => self::ITEM_VALUE_TYPES_NUM],
		'trim' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING],
		'truncate' => [ZBX_FUNCTION_TYPE_MATH => self::ITEM_VALUE_TYPES_STRING],
		'varpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'varsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => self::ITEM_VALUE_TYPES_NUM],
		'xmlxpath' => [ZBX_FUNCTION_TYPE_STRING => self::ITEM_VALUE_TYPES_STRING]
	];

	public static function getValueTypes(): array {
		return self::VALUE_TYPES;
	}

	public static function getDescriptions(): array {
		return [
			'abs' => _('abs() - Absolute value'),
			'acos' => _('acos() - The arccosine of a value as an angle, expressed in radians'),
			'ascii' => _('ascii() - Returns the ASCII code of the leftmost character of the value'),
			'asin' => _('asin() - The arcsine of a value as an angle, expressed in radians'),
			'atan' => _('atan() - The arctangent of a value as an angle, expressed in radians'),
			'atan2' => _('atan2() - The arctangent of the ordinate (value) and abscissa coordinates specified as an angle, expressed in radians'),
			'avg' => _('avg() - Average value of a period T'),
			'between' => _('between() - Checks if a value belongs to the given range (1 - in range, 0 - otherwise)'),
			'bitand' => _('bitand() - Bitwise AND'),
			'bitlength' => _('bitlength() - Returns the length in bits'),
			'bitlshift' => _('bitlshift() - Bitwise shift left'),
			'bitnot' =>  _('bitnot() - Bitwise NOT'),
			'bitor' => _('bitor() - Bitwise OR'),
			'bitrshift' => _('bitrshift() - Bitwise shift right'),
			'bitxor' => _('bitxor() - Bitwise exclusive OR'),
			'bytelength' => _('bytelength() - Returns the length in bytes'),
			'cbrt' => _('cbrt() - Cube root'),
			'ceil' => _('ceil() - Rounds up to the nearest greater integer'),
			'change' => _('change() - Difference between last and previous value'),
			'changecount' => _('changecount() - Number of changes between adjacent values, Mode (all - all changes, inc - only increases, dec - only decreases)'),
			'char' => _('char() - Returns the character which represents the given ASCII code'),
			'concat' => _('concat() - Returns a string that is the result of concatenating value to string'),
			'cos' => _('cos() - The cosine of a value, where the value is an angle expressed in radians'),
			'cosh' => _('cosh() - The hyperbolic cosine of a value'),
			'cot' => _('cot() - The cotangent of a value, where the value is an angle expressed in radians'),
			'count' => _('count() - Number of successfully retrieved values V (which fulfill operator O) for period T'),
			'countunique' => _('countunique() - The number of unique values'),
			'date' => _('date() - Current date'),
			'dayofmonth' => _('dayofmonth() - Day of month'),
			'dayofweek' => _('dayofweek() - Day of week'),
			'degrees' => _('degrees() - Converts a value from radians to degrees'),
			'e' => _("e() - Returns Euler's number"),
			'exp' => _("exp() - Euler's number at a power of a value"),
			'expm1' => _("expm1() - Euler's number at a power of a value minus 1"),
			'find' => _('find() - Check occurrence of pattern V (which fulfill operator O) for period T (1 - match, 0 - no match)'),
			'first' => _('first() - The oldest value in the specified time interval'),
			'firstclock' => _('firstclock() - Timestamp of the oldest value in the specified time interval'),
			'floor' => _('floor() - Rounds down to the nearest smaller integer'),
			'forecast' => _('forecast() - Forecast for next t seconds based on period T'),
			'fuzzytime' => _('fuzzytime() - Difference between item value (as timestamp) and Zabbix server timestamp is less than or equal to T seconds (1 - true, 0 - false)'),
			'in' => _('in() - Checks if a value equals to one of the listed values (1 - equals, 0 - otherwise)'),
			'insert' => _('insert() - Inserts specified characters or spaces into a character string, beginning at a specified position in the string'),
			'jsonpath' => _('jsonpath() - Returns JSONPath result'),
			'kurtosis' => _('kurtosis() - Measures the "tailedness" of the probability distribution'),
			'last' => _('last() - Last (most recent) T value'),
			'lastclock' => _('lastclock() - Timestamp of the last (most recent) T value'),
			'left' => _('left() - Returns the leftmost count characters'),
			'length' => _('length() - Length of last (most recent) T value in characters'),
			'log' => _('log() - Natural logarithm'),
			'log10' => _('log10() - Decimal logarithm'),
			'logeventid' => _('logeventid() - Event ID of last log entry matching regular expression V for period T (1 - match, 0 - no match)'),
			'logseverity' => _('logseverity() - Log severity of the last log entry for period T'),
			'logsource' => _('logsource() - Log source of the last log entry matching parameter V for period T (1 - match, 0 - no match)'),
			'logtimestamp' => _('logtimestamp() - Timestamp of the last (most recent) T log message'),
			'ltrim' => _('ltrim() - Remove specified characters from the beginning of a string'),
			'mad' => _('mad() - Median absolute deviation'),
			'max' => _('max() - Maximum value for period T'),
			'mid' => _('mid() - Returns a substring beginning at the character position specified by start for N characters'),
			'min' => _('min() - Minimum value for period T'),
			'mod' => _('mod() - Division remainder'),
			'monodec' => _('monodec() - Check for continuous item value decrease (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
			'monoinc' => _('monoinc() - Check for continuous item value increase (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
			'nodata' => _('nodata() - No data received during period of time T (1 - true, 0 - false), Mode (strict - ignore proxy time delay in sending data)'),
			'now' => _('now() - Number of seconds since the Epoch'),
			'percentile' => _('percentile() - Percentile P of a period T'),
			'pi' => _('pi() - Returns the Pi constant'),
			'power' => _('power() - The power of a base value to a power value'),
			'radians' => _('radians() - Converts a value from degrees to radians'),
			'rand' => _('rand() - A random integer value'),
			'rate' => _('rate() - Returns per-second average rate for monotonically increasing counters'),
			'repeat' => _('repeat() - Returns a string composed of value repeated count times'),
			'replace' => _('replace() - Search value for occurrences of pattern, and replace with replacement'),
			'right' => _('right() - Returns the rightmost count characters'),
			'round' => _('round() - Rounds a value to decimal places'),
			'rtrim' => _('rtrim() - Removes specified characters from the end of a string'),
			'signum' => _('signum() - Returns -1 if a value is negative, 0 if a value is zero, 1 if a value is positive'),
			'sin' => _('sin() - The sine of a value, where the value is an angle expressed in radians'),
			'sinh' => _('sinh() - The hyperbolic sine of a value'),
			'skewness' => _('skewness() - Measures the asymmetry of the probability distribution'),
			'sqrt' => _('sqrt() - Square root of a value'),
			'stddevpop' => _('stddevpop() - Population standard deviation'),
			'stddevsamp' => _('stddevsamp() - Sample standard deviation'),
			'sum' => _('sum() - Sum of values of a period T'),
			'sumofsquares' => _('sumofsquares() - The sum of squares'),
			'tan' => _('tan() - The tangent of a value'),
			'time' => _('time() - Current time'),
			'timeleft' => _('timeleft() - Time to reach threshold estimated based on period T'),
			'trendavg' => _('trendavg() - Average value of a period T with exact period shift'),
			'baselinedev' => _('baselinedev() - Returns the number of deviations between data periods in seasons and the last data period'),
			'baselinewma' => _('baselinewma() - Calculates baseline by averaging data periods in seasons'),
			'trendcount' => _('trendcount() - Number of successfully retrieved values for period T'),
			'trendmax' => _('trendmax() - Maximum value for period T with exact period shift'),
			'trendmin' => _('trendmin() - Minimum value for period T with exact period shift'),
			'trendstl' => _('trendstl() - Anomaly detection for period T'),
			'trendsum' => _('trendsum() - Sum of values of a period T with exact period shift'),
			'trim' => _('trim() - Remove specified characters from the beginning and the end of a string'),
			'truncate' => _('truncate() - Truncates a value to decimal places'),
			'varpop' => _('varpop() - Population variance'),
			'varsamp' => _('varsamp() - Sample variance'),
			'xmlxpath' => _('xmlxpath() - Returns XML XPath result')
		];
	}

	public static function getValidationRules(bool $lld_macros): array {
		return [
			'abs' => [
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['db items.itemid', 'required'],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				]
			],
			'acos' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'ascii' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'asin' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'atan' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'atan2' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'abscissa' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'avg' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS, PARAM_TYPE_TIME]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				]
			],
			'between' => [ZBX_FUNCTION_TYPE_OPERATOR => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'min' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros]
					]],
					'max' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitand' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mask' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'with_float' => false, 'min' => 0, 'max' => ZBX_MAX_UINT64
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitlength' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitlshift' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'bits' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitnot' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitor' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mask' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'with_float' => false, 'min' => 0, 'max' => ZBX_MAX_UINT64
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitrshift' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'bits' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bitxor' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mask' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'with_float' => false, 'min' => 0, 'max' => ZBX_MAX_UINT64
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'bytelength' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'cbrt' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'ceil' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'change' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'changecount' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mode' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'inc', 'dec', 'all']
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'char' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'concat' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'string' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'cos' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'cosh' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'cot' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'count' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'o' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'eq', 'ne', 'gt', 'lt', 'ge', 'le', 'like', 'bitand', 'regexp', 'iregexp']
					],
					'v' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'countunique' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'o' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'eq', 'ne', 'gt', 'lt', 'ge', 'le', 'like', 'bitand', 'regexp', 'iregexp']
					],
					'v' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'date' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'dayofmonth' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'dayofweek' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'degrees' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'e' => [ZBX_FUNCTION_TYPE_MATH => []],
			'exp' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'expm1' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'find' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty']],
						['string', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'o' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'eq', 'ne', 'gt', 'lt', 'ge', 'le', 'like', 'bitand', 'regexp', 'iregexp']
					],
					'v' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'first' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty',
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'firstclock' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty',
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'floor' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'forecast' => [ZBX_FUNCTION_TYPE_PREDICTION => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'time' => ['string', 'required', 'not_empty',
						'use' => [CSimpleIntervalParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'fit' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'exponential', 'linear', 'logarithmic', 'polynomial1', 'polynomial2',
							'polynomial3', 'polynomial4', 'polynomial5', 'polynomial6', 'power'
						]
					],
					'mode' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'avg', 'delta', 'min', 'max', 'value']
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'fuzzytime' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'last' => [
						['string', 'required', 'not_empty',
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'in' => [ZBX_FUNCTION_TYPE_OPERATOR => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'values' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'insert' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'start' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]],
					'length' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]],
					'replace' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'jsonpath' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'path' => ['string', 'required', 'not_empty'],
					'replace' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'kurtosis' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'last' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'lastclock' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'left' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'count' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'length' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'log' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'log10' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'logeventid' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'v' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'logseverity' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'logsource' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'v' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'logtimestamp' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'ltrim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'chars' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'mad' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'max' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				]
			],
			'mid' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'start' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]],
					'length' => ['string', 'required', 'not_empty', 'use' => [CNumberParser::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'min' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				]
			],
			'mod' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'denominator' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'monodec' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mode' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'weak', 'strict']
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'monoinc' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mode' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'weak', 'strict']
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'nodata' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'last' => [
						['string', 'required', 'not_empty',
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'mode' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'strict']
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS_BOOL],
				'value' => ['string', 'required', 'not_empty']
			]],
			'now' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'percentile' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'p' => ['string', 'required', 'not_empty', 'regex' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => 0, 'max' => 100]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'pi' => [ZBX_FUNCTION_TYPE_MATH => []],
			'power' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'power' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'radians' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'rand' => [ZBX_FUNCTION_TYPE_MATH => []],
			'rate' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty',
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'repeat' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'count' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'replace' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'pattern' => ['string', 'required', 'not_empty'],
					'replace' => ['string', 'required', 'not_empty']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'right' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'count' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'round' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'decimals' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'rtrim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'chars' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'signum' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'sin' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'sinh' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'skewness' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'sqrt' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'stddevpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'stddevsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'sum' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['db items.itemid', 'required'],
					'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => ['object', 'fields' => [
						'shift' => ['string',
							'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
						],
						'last' => [
							['string', 'required', 'not_empty',
								'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
								'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							],
							['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
								'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
									'min' => 1, 'max' => ZBX_MAX_INT32
								]]
							]
						]
					]],
					'operator' => ['string', 'required', 'in' => self::OPERATORS],
					'value' => ['string', 'required', 'not_empty']
				]
			],
			'sumofsquares' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'tan' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'time' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'timeleft' => [ZBX_FUNCTION_TYPE_PREDICTION => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					't' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class,
							['usermacros' => true, 'lldmacros' => $lld_macros, 'min' => 0, 'max' => null]
						]
					],
					'fit' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'exponential', 'linear', 'logarithmic', 'polynomial1', 'polynomial2',
							'polynomial3', 'polynomial4', 'polynomial5', 'polynomial6', 'power'
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendavg' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'baselinedev' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					],
					'season_unit' => ['string', 'required', 'in' => ['h', 'd', 'w', 'M', 'y']],
					'num_seasons' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false, 'min' => 1]]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'baselinewma' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					],
					'season_unit' => ['string', 'required', 'in' => ['h', 'd', 'w', 'M', 'y']],
					'num_seasons' => ['string', 'required', 'not_empty', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false, 'min' => 1]]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendcount' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendmax' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendmin' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendstl' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					],
					'detect_period' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					],
					'season' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR*2, 'max' => ZBX_MAX_INT32
						]]
					],
					'deviations' => ['string', 'regex' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => 1, 'max' => ZBX_MAX_INT32
						]]
					],
					'algorithm' => ['string', 'allow_macro' => ['usermacros' => true, 'lldmacros' => $lld_macros],
						'in' => ['', 'mad', 'stddevpop', 'stddevsamp']
					],
					'season_window' => ['string', 'use' => [CNumberValidator::class,
						['usermacros' => true, 'lldmacros' => $lld_macros, 'with_float' => false,
							'min' => 7, 'max' => ZBX_MAX_INT32]
					]]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trendsum' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME]],
				'params' => ['object', 'fields' => [
					'period_shift' => ['string', 'required', 'not_empty',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => ['string', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
							'min' => SEC_PER_HOUR, 'max' => ZBX_MAX_INT32
						]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'trim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'chars' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'truncate' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'decimals' => ['string', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros]]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'varpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'varsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_COUNTS]],
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						],
						['string', 'required', 'not_empty', 'when' => ['../paramtype', 'in' => [PARAM_TYPE_TIME]],
							'use' => [CTimeUnitValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					]
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]],
			'xmlxpath' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['db items.itemid', 'required'],
				'paramtype' => ['integer', 'required', 'in' => [PARAM_TYPE_COUNTS]],
				'params' => ['object', 'fields' => [
					'shift' => ['string',
						'use' => [CRelativeTimeParser::class, ['usermacros' => true, 'lldmacros' => $lld_macros]]
					],
					'last' => [
						['string', 'required', 'not_empty', 'when' => ['shift', 'not_empty'],
							'use' => [CNumberValidator::class, ['usermacros' => true,'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]],
							'messages' => [
								'not_empty' => _('Field "Last of" cannot be empty when "Time shift" is not empty.')
							]
						],
						['string',
							'use' => [CNumberValidator::class, ['usermacros' => true, 'lldmacros' => $lld_macros,
								'with_float' => false, 'min' => 1, 'max' => ZBX_MAX_INT32
							]]
						]
					],
					'path' => ['string', 'required', 'not_empty'],
					'replace' => ['string']
				]],
				'operator' => ['string', 'required', 'in' => self::OPERATORS],
				'value' => ['string', 'required', 'not_empty']
			]]
		];
	}

	public static function getParameters(): array {
		return [
			'abs' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'acos' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'ascii' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'asin' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'atan' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'atan2' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'abscissa' => ['label' => _('Abscissa'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'avg' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				]
			],
			'between' => [ZBX_FUNCTION_TYPE_OPERATOR => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'min' => ['label' => _('Min'), 'required' => true],
					'max' => ['label' => _('Max'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result')]
			]],
			'bitand' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'mask' => ['label' => _('Mask'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bitlength' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'bitlshift' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'bits' => ['label' => _('Bits to shift'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bitnot' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bitor' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'mask' => ['label' => _('Mask'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bitrshift' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'bits' => ['label' => _('Bits to shift'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bitxor' => [ZBX_FUNCTION_TYPE_BITWISE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'mask' => ['label' => _('Mask'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'bytelength' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'cbrt' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'ceil' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'change' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'changecount' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'mode' => ['label' => _('Mode')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'char' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'concat' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'string' => ['label' => _('String'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'cos' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'cosh' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'cot' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'count' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'o' => ['label' => 'O'],
					'v' => ['label' => 'V']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'countunique' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'o' => ['label' => 'O'],
					'v' => ['label' => 'V']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'date' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'dayofmonth' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'dayofweek' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'degrees' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'e' => [ZBX_FUNCTION_TYPE_MATH => []],
			'exp' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'expm1' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'find' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'o' => ['label' => 'O'],
					'v' => ['label' => 'V']
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'first' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'firstclock' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'floor' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'forecast' => [ZBX_FUNCTION_TYPE_PREDICTION => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'time' => ['label' => _('Time').' (t)', 'required' => true],
					'fit' => ['label' => _('Fit')],
					'mode' => ['label' => _('Mode')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'fuzzytime' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'in' => [ZBX_FUNCTION_TYPE_OPERATOR => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'values' => ['label' => _('Values'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result')]
			]],
			'insert' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'start' => ['label' => _('Start'), 'required' => true],
					'length' => ['label' => _('Length'), 'required' => true],
					'replace' => ['label' => _('Replacement'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'jsonpath' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'path' => ['label' => _('JSONPath'), 'required' => true],
					'replace' => ['label' => _('Default')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'kurtosis' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'last' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'lastclock' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'left' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'count' => ['label' => _('Count'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'length' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'log' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'log10' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'logeventid' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'v' => ['label' => 'V']
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result')]
			]],
			'logseverity' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'logsource' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'v' => ['label' => 'V']
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result')]
			]],
			'logtimestamp' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result')]
			]],
			'ltrim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'chars' => ['label' => _('Chars')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'mad' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'max' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				]
			],
			'mid' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'start' => ['label' => _('Start'), 'required' => true],
					'length' => ['label' => _('Length'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'min' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				]
			],
			'mod' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'denominator' => ['label' => _('Division denominator'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'monodec' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'mode' => ['label' => _('Mode')]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'monoinc' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'mode' => ['label' => _('Mode')]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'nodata' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'mode' => ['label' => _('Mode')]
				],
				'operator' => ['options' => self::OPERATORS_BOOL],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'now' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'percentile' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					'p' => ['label' =>  _('Percentage').' (P)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'pi' => [ZBX_FUNCTION_TYPE_MATH => []],
			'power' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'power' => ['label' => _('Power value'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'radians' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'rand' => [ZBX_FUNCTION_TYPE_MATH => []],
			'rate' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'repeat' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'count' => ['label' => _('Count'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'replace' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'pattern' => ['label' => _('Pattern'), 'required' => true],
					'replace' => ['label' => _('Replacement'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'right' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'count' => ['label' => _('Count'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'round' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'decimals' => ['label' => _('Decimal places'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'rtrim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'chars' => ['label' => _('Chars')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'signum' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'sin' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'sinh' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'skewness' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'sqrt' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'stddevpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'stddevsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'sum' => [
				ZBX_FUNCTION_TYPE_AGGREGATE => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				],
				ZBX_FUNCTION_TYPE_MATH => [
					'itemid' => ['label' => _('Item'), 'required' => true],
					'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
					'params' => [
						'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
						'last' => ['label' => _('Last of').' (T)', 'required' => true]
					],
					'operator' => ['options' => self::OPERATORS],
					'value' => ['label' => _('Result'), 'required' => true]
				]
			],
			'sumofsquares' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'tan' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)']
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'time' => [ZBX_FUNCTION_TYPE_DATE_TIME => []],
			'timeleft' => [ZBX_FUNCTION_TYPE_PREDICTION => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true],
					't' => ['label' => _('Threshold'), 'required' => true],
					'fit' => ['label' => _('Fit')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendavg' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'baselinedev' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Period').' (T)', 'required' => true],
					'season_unit' => ['label' => _('Season'), 'required' => true,
						'options' => ['h', 'd', 'w', 'M', 'y']
					],
					'num_seasons' => ['label' => _('Number of seasons'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'baselinewma' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Period').' (T)', 'required' => true],
					'season_unit' => ['label' => _('Season'), 'required' => true,
						'options' => ['h', 'd', 'w', 'M', 'y']
					],
					'num_seasons' => ['label' => _('Number of seasons'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendcount' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendmax' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendmin' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendstl' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Evaluation period').' (T)', 'required' => true],
					'detect_period' => ['label' => _('Detection period'), 'required' => true],
					'season' => ['label' => _('Season'), 'required' => true],
					'deviations' => ['label' => _('Deviations')],
					'algorithm' => ['label' => _('Algorithm')],
					'season_window' => ['label' => _('Season deviation window')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trendsum' => [ZBX_FUNCTION_TYPE_HISTORY => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME]],
				'params' => [
					'period_shift' => ['label' => _('Period shift'), 'placeholder' => 'now/h', 'required' => true],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'trim' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'chars' => ['label' => _('Chars')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'truncate' => [ZBX_FUNCTION_TYPE_MATH => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'decimals' => ['label' => _('Decimal places'), 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'varpop' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'varsamp' => [ZBX_FUNCTION_TYPE_AGGREGATE => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)', 'required' => true]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]],
			'xmlxpath' => [ZBX_FUNCTION_TYPE_STRING => [
				'itemid' => ['label' => _('Item'), 'required' => true],
				'paramtype' => ['options' => [PARAM_TYPE_COUNTS]],
				'params' => [
					'shift' => ['label' => _('Time shift'), 'placeholder' => 'now-1h'],
					'last' => ['label' => _('Last of').' (T)'],
					'path' => ['label' => _('XPath'), 'required' => true],
					'replace' => ['label' => _('Default')]
				],
				'operator' => ['options' => self::OPERATORS],
				'value' => ['label' => _('Result'), 'required' => true]
			]]
		];
	}

	public static function getParameter(string $name): ?array {
		$parameters = self::getParameters();

		return array_key_exists($name, $parameters) ? $parameters[$name] : null;
	}

	public static function getParamsFields(): array {
		return [
			'shift' => ['type' => CTextBox::class, 'placeholder' => 'now-1h', 'paramtype' => _('Time')],
			'period_shift' => ['type' => CTextBox::class, 'placeholder' => 'now/h', 'paramtype' => _('Period')],
			'o' => ['type' => CTextBox::class],
			'v' => ['type' => CTextBox::class],
			't' => ['type' => CTextBox::class],
			'mask' => ['type' => CTextBox::class],
			'bits' => ['type' => CTextBox::class],
			'detect_period' => ['type' => CTextBox::class],
			'season_unit' => ['type' => CSelect::class, 'options' =>
				['h' => _('Hour'), 'd' => _('Day'), 'w' => _('Week'), 'M' => _("Month"), 'y' => _('Year')]
			],
			'season' => ['type' => CTextBox::class],
			'num_seasons' => ['type' => CTextBox::class],
			'time' => ['type' => CTextBox::class],
			'fit' => ['type' => CTextBox::class],
			'mode' => ['type' => CTextBox::class],
			'p' => ['type' => CTextBox::class],
			'deviations' => ['type' => CTextBox::class],
			'algorithm' => ['type' => CTextBox::class],
			'season_window' => ['type' => CTextBox::class],
			'denominator' => ['type' => CTextBox::class],
			'power' => ['type' => CTextBox::class],
			'decimals' => ['type' => CTextBox::class],
			'min' => ['type' => CTextBox::class],
			'max' => ['type' => CTextBox::class],
			'string' => ['type' => CTextBox::class, 'attributes' => ['data-notrim' => '']],
			'values' => ['type' => CTextBox::class],
			'start' => ['type' => CTextBox::class],
			'length' => ['type' => CTextBox::class],
			'path' => ['type' => CTextBox::class],
			'pattern' => ['type' => CTextBox::class, 'attributes' => ['data-notrim' => '']],
			'replace' => ['type' => CTextBox::class, 'attributes' => ['data-notrim' => '']],
			'count' => ['type' => CTextBox::class],
			'chars' => ['type' => CTextBox::class, 'attributes' => ['data-notrim' => '']],
			'abscissa' => ['type' => CTextBox::class]
		];
	}

	/**
	 * Get full function names with type prefix.
	 *
	 * @param int|null $valuetype	Filter functions by allowed item value type.
	 */
	public static function getFunctionNames(?int $valuetype = null): array {
		$result = [];
		$functions = self::getValueTypes();

		foreach ($functions as $name => $types) {
			foreach ($types as $type => $allowed_valuetypes) {
				if ($valuetype === null || in_array($valuetype, $allowed_valuetypes)) {
					$result[] = $type.'_'.$name;
				}
			}
		}

		return $result;
	}
}
