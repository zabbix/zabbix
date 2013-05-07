<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/debug.inc.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CValidator.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CTriggerFunctionValidator.php';

class CTriggerFunctionValidatorTest extends PHPUnit_Framework_TestCase {

	private static function parameterSecNumPeriod_TestCases($func, array $valueTypes, array $params = array(), $no = 0) {
		$valueTypesAny = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT);

		$tests = array();

		foreach ($valueTypesAny as $valueType) {
			$params[$no] = '0';			$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '12345';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1s';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1m';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1h';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1d';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1w';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1K';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1M';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1G';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1T';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#12345';	$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1{$M}';		$tests[] = array($func, $params, $valueType, false);
		}

		return $tests;
	}

	private static function parameterSecNumOffset_TestCases($func, array $valueTypes, array $params = array(), $no = 0) {
		$valueTypesAny = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT);

		$tests = array();

		foreach ($valueTypesAny as $valueType) {
			$params[$no] = '0';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '12345';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1s';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1m';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1h';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1d';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1w';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1K';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1M';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1G';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1T';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#12345';	$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1{$M}';		$tests[] = array($func, $params, $valueType, false);
		}

		return $tests;
	}

	private static function parameterTimeShift_TestCases($func, array $valueTypes, array $params = array(), $no = 0) {
		$valueTypesAny = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT);

		$tests = array();

		foreach ($valueTypesAny as $valueType) {
			$params[$no] = '0';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '12345';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1s';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1m';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1h';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1d';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1w';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1K';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1M';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1G';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1T';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#12345';	$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#01';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1{$M}';		$tests[] = array($func, $params, $valueType, false);
		}

		return $tests;
	}

	private static function parameterString_TestCases($func, array $valueTypes, array $params = array(), $no = 0) {
		$valueTypesAny = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT);

		$tests = array();

		foreach ($valueTypesAny as $valueType) {
			$params[$no] = '0';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '12345';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '-15';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1.0';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#0';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#1';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#12345';	$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#01';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#-15';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '#1.0';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '1{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
		}

		return $tests;
	}

	private static function parameterOperator_TestCases($func, array $valueTypes, array $params = array(), $no = 0) {
		$valueTypesAny = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT);

		$tests = array();

		foreach ($valueTypesAny as $valueType) {
			$params[$no] = 'eq';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'ne';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'gt';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'ge';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'lt';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'le';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'like';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = 'band';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '{$M}';		$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '';			$tests[] = array($func, $params, $valueType, isset($valueTypes[$valueType]));
			$params[$no] = '0';			$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#12345';	$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#01';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#-15';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '#1.0';		$tests[] = array($func, $params, $valueType, false);
			$params[$no] = 'gt{$M}';	$tests[] = array($func, $params, $valueType, false);
			$params[$no] = '{$M}gt';	$tests[] = array($func, $params, $valueType, false);
		}

		return $tests;
	}

	public static function provider() {
		$valueTypesAny = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_TEXT => true
		);
		$valueTypesLog = array(
			ITEM_VALUE_TYPE_LOG => true
		);
		$valueTypesNum = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		);
		$valueTypesInt = array(
			ITEM_VALUE_TYPE_UINT64 => true
		);
		$valueTypesStr = array(
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_TEXT => true
		);

		return array_merge(
			// abschange() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('abschange', $valueTypesAny),

			// change() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('change', $valueTypesAny),

			// date() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('date', $valueTypesAny),

			// dayofmonth() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('dayofmonth', $valueTypesAny),

			// dayofweek() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('dayofweek', $valueTypesAny),

			// diff() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('diff', $valueTypesAny),

			// logseverity() - (ignored) [log]
			self::parameterString_TestCases('logseverity', $valueTypesLog),

			// now() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('now', $valueTypesAny),

			// prev() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('prev', $valueTypesAny),

			// time() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('time', $valueTypesAny),

			// avg() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('avg', $valueTypesNum),
			self::parameterTimeShift_TestCases('avg', $valueTypesNum, array('#1', ''), 1),

			// band() - (sec or #num, mask, time_shift) [int]
			self::parameterSecNumOffset_TestCases('band', $valueTypesInt, array('', '0')),
//			TODO Mask
			self::parameterTimeShift_TestCases('band', $valueTypesInt, array('#1', '0', ''), 2),

			// count() - (sec or #num, pattern, operator, time_shift) [float, int, str, text, log]
			self::parameterSecNumPeriod_TestCases('count', $valueTypesAny),
//			TODO Pattern
			self::parameterOperator_TestCases('count', $valueTypesAny, array('#1', '', ''), 2),
			self::parameterTimeShift_TestCases('count', $valueTypesAny, array('#1', '', '', ''), 3),

			// delta() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('delta', $valueTypesNum),
			self::parameterTimeShift_TestCases('delta', $valueTypesNum, array('#1', ''), 1),

			// last() - (sec or #num, time_shift) [float, int, str, text, log]
			self::parameterSecNumOffset_TestCases('last', $valueTypesAny),
			self::parameterTimeShift_TestCases('last', $valueTypesAny, array('#1', ''), 1),

			// max() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('max', $valueTypesNum),
			self::parameterTimeShift_TestCases('max', $valueTypesNum, array('#1', ''), 1),

			// min() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('min', $valueTypesNum),
			self::parameterTimeShift_TestCases('min', $valueTypesNum, array('#1', ''), 1),

			// strlen() - (sec or #num, time_shift) [str, text, log]
			self::parameterSecNumOffset_TestCases('strlen', $valueTypesStr),
			self::parameterTimeShift_TestCases('strlen', $valueTypesStr, array('#1', ''), 1),

			// sum() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('sum', $valueTypesNum),
			self::parameterTimeShift_TestCases('sum', $valueTypesNum, array('#1', ''), 1),

			// fuzzytime() - (sec) [float, int]
			self::parameterTimeShift_TestCases('fuzzytime', $valueTypesNum),

			// nodata() - (sec) [float, int, str, text, log]
			self::parameterTimeShift_TestCases('nodata', $valueTypesAny),

			// iregexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('iregexp', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('iregexp', $valueTypesStr, array('', ''), 1),

			// logeventid() - (string) [log]
			self::parameterString_TestCases('logeventid', $valueTypesLog),

			// logsource() - (string) [log]
			self::parameterString_TestCases('logsource', $valueTypesLog),

			// regexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('regexp', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('regexp', $valueTypesStr, array('', ''), 1),

			// str() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('str', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('str', $valueTypesStr, array('', ''), 1)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($functionName, $functionParamList, $valueType, $expectedResult) {
		$triggerFunctionValidator = new CTriggerFunctionValidator();

		$result = $triggerFunctionValidator->validate(array(
			'function' => '',
			'functionName' => $functionName,
			'functionParamList' => $functionParamList,
			'valueType' => $valueType
		));

		$this->assertSame($result, $expectedResult);
	}
}
