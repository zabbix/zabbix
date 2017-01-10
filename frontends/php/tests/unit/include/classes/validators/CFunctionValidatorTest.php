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


class CFunctionValidatorTest extends PHPUnit_Framework_TestCase {

	private static function parameterSecNumPeriod_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '12345';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1s';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1m';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1h';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1d';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1w';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1K';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1M';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1G';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1T';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M}'; 		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterSecNumOffset_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '12345';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1s';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1m';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1h';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1d';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1w';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1K';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1M';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1G';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1T';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterPeriod_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '12345';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1s';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1m';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1h';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1d';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1w';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1K';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1M';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1G';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1T';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterTimeShift_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '12345';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1s';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1m';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1h';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1d';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1w';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1K';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1M';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1G';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1T';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterPercent_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1s';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1m';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1h';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1d';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1w';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1K';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1M';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1G';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1T';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '-15.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '0.0';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1.0123';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1.01234';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.00000';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '1.';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '.1';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '.';			$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '100.0000';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '100.0001';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterString_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '12345';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '-15';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1.0';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#0';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#1';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '1{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
			}
		}

		return $tests;
	}

	private static function parameterOperator_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = 'eq';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'ne';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'gt';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'ge';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'lt';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'le';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'like';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'band';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'regexp';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = 'iregexp';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M: /}';	$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{$M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '{#M}';		$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType]) && (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)];
				$params[$no] = '';			$tests[] = [$func, $params, $valueType, $options, isset($valueTypes[$valueType])];
				$params[$no] = '0';			$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#12345';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#01';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#-15';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '#1.0';		$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = 'gt{$M}';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{$M}gt';	$tests[] = [$func, $params, $valueType, $options, false];
				$params[$no] = '{#M}gt';	$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	public static function provider() {
		$valueTypesAny = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_TEXT => true
		];
		$valueTypesLog = [
			ITEM_VALUE_TYPE_LOG => true
		];
		$valueTypesNum = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$valueTypesInt = [
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$valueTypesStr = [
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_TEXT => true
		];

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
			self::parameterTimeShift_TestCases('avg', $valueTypesNum, ['#1', ''], 1),

			// band() - (sec or #num, mask, time_shift) [int]
			self::parameterSecNumOffset_TestCases('band', $valueTypesInt, ['', '0']),
//			TODO Mask
			self::parameterTimeShift_TestCases('band', $valueTypesInt, ['#1', '0', ''], 2),

			// count() - (sec or #num, pattern, operator, time_shift) [float, int, str, text, log]
			self::parameterSecNumPeriod_TestCases('count', $valueTypesAny),
//			TODO Pattern
			self::parameterOperator_TestCases('count', $valueTypesAny, ['#1', '', ''], 2),
			self::parameterTimeShift_TestCases('count', $valueTypesAny, ['#1', '', '', ''], 3),

			// delta() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('delta', $valueTypesNum),
			self::parameterTimeShift_TestCases('delta', $valueTypesNum, ['#1', ''], 1),

			// last() - (sec or #num, time_shift) [float, int, str, text, log]
			self::parameterSecNumOffset_TestCases('last', $valueTypesAny),
			self::parameterTimeShift_TestCases('last', $valueTypesAny, ['#1', ''], 1),

			// max() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('max', $valueTypesNum),
			self::parameterTimeShift_TestCases('max', $valueTypesNum, ['#1', ''], 1),

			// min() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('min', $valueTypesNum),
			self::parameterTimeShift_TestCases('min', $valueTypesNum, ['#1', ''], 1),

			// percentile() - (sec or #num, time_shift, float) [float, int]
			self::parameterSecNumPeriod_TestCases('percentile', $valueTypesNum, ['#1', '', '50']),
			self::parameterTimeShift_TestCases('percentile', $valueTypesNum, ['#1', '', '50'], 1),
			self::parameterPercent_TestCases('percentile', $valueTypesNum, ['#1', '', '50'], 2),

			// strlen() - (sec or #num, time_shift) [str, text, log]
			self::parameterSecNumOffset_TestCases('strlen', $valueTypesStr),
			self::parameterTimeShift_TestCases('strlen', $valueTypesStr, ['#1', ''], 1),

			// sum() - (sec or #num, time_shift) [float, int]
			self::parameterSecNumPeriod_TestCases('sum', $valueTypesNum),
			self::parameterTimeShift_TestCases('sum', $valueTypesNum, ['#1', ''], 1),

			// fuzzytime() - (sec) [float, int]
			self::parameterTimeShift_TestCases('fuzzytime', $valueTypesNum),

			// nodata() - (sec) [float, int, str, text, log]
			self::parameterPeriod_TestCases('nodata', $valueTypesAny),

			// iregexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('iregexp', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('iregexp', $valueTypesStr, ['', ''], 1),

			// logeventid() - (string) [log]
			self::parameterString_TestCases('logeventid', $valueTypesLog),

			// logsource() - (string) [log]
			self::parameterString_TestCases('logsource', $valueTypesLog),

			// regexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('regexp', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('regexp', $valueTypesStr, ['', ''], 1),

			// str() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('str', $valueTypesStr),
			self::parameterSecNumPeriod_TestCases('str', $valueTypesStr, ['', ''], 1)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($functionName, $functionParamList, $valueType, $options, $expectedResult) {
		$triggerFunctionValidator = new CFunctionValidator($options);

		$result = $triggerFunctionValidator->validate([
			'function' => '',
			'functionName' => $functionName,
			'functionParamList' => $functionParamList,
			'valueType' => $valueType
		]);

		$this->assertSame($result, $expectedResult);
	}
}
