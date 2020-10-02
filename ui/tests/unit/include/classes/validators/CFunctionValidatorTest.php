<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

	private static function parameterSecNum_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, false];
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
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterSec_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];
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
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];
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
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '0.0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1.0123';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1.01234';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.00000';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '.1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '.';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '100.0000';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '100.0001';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];
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
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];
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
				$params[$no] = 'eq';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'ne';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'gt';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'ge';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'lt';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'le';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'like';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'band';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'regexp';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'iregexp';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'gt{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M}gt';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}gt';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}gt';
				$tests[] = [$func, $params, $valueType, $options, false];
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
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '36000';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterPeriodShift_TestCases($func, array $valueTypes, array $params = [], $no = 0) {
		$valueTypesAny = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($valueTypesAny as $valueType) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-3601s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h-60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-61m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h-1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/h/d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/h/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h-1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/h-1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h-1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/h-1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '/h-1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-3601s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d-60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-61m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d-1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/d/w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/d/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d-1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/d-1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d-1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/d-1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '/d-1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-3601s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w-60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-61m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w-1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/w/M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/w/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w-1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/w-1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w-1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/w-1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '/w-1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-3601s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M-60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-61m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M-1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/M/y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/M/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M-1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/M-1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M-1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/M-1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '/M-1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-3600s';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-3601s';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y-60m';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-61m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y-1h';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now-1h/y/m';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y-1d';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-1w';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-1M';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-1y';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = 'now/y-1K';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y-1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = 'now/y-1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '/y-1y';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $valueType, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $valueType, $options, array_key_exists($valueType, $valueTypes)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $valueType, $options, false];
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
			self::parameterSecNum_TestCases('avg', $valueTypesNum),
			self::parameterTimeShift_TestCases('avg', $valueTypesNum, ['#1', ''], 1),

			// band() - (sec or #num, mask, time_shift) [int]
			self::parameterSecNumOffset_TestCases('band', $valueTypesInt, ['', '0']),
//			TODO Mask
			self::parameterTimeShift_TestCases('band', $valueTypesInt, ['#1', '0', ''], 2),

			// count() - (sec or #num, pattern, operator, time_shift) [float, int, str, text, log]
			self::parameterSecNum_TestCases('count', $valueTypesAny),
//			TODO Pattern
			self::parameterOperator_TestCases('count', $valueTypesAny, ['#1', '', ''], 2),
			self::parameterTimeShift_TestCases('count', $valueTypesAny, ['#1', '', '', ''], 3),

			// delta() - (sec or #num, time_shift) [float, int]
			self::parameterSecNum_TestCases('delta', $valueTypesNum),
			self::parameterTimeShift_TestCases('delta', $valueTypesNum, ['#1', ''], 1),

			// last() - (sec or #num, time_shift) [float, int, str, text, log]
			self::parameterSecNumOffset_TestCases('last', $valueTypesAny),
			self::parameterTimeShift_TestCases('last', $valueTypesAny, ['#1', ''], 1),

			// max() - (sec or #num, time_shift) [float, int]
			self::parameterSecNum_TestCases('max', $valueTypesNum),
			self::parameterTimeShift_TestCases('max', $valueTypesNum, ['#1', ''], 1),

			// min() - (sec or #num, time_shift) [float, int]
			self::parameterSecNum_TestCases('min', $valueTypesNum),
			self::parameterTimeShift_TestCases('min', $valueTypesNum, ['#1', ''], 1),

			// percentile() - (sec or #num, time_shift, float) [float, int]
			self::parameterSecNum_TestCases('percentile', $valueTypesNum, ['#1', '', '50']),
			self::parameterTimeShift_TestCases('percentile', $valueTypesNum, ['#1', '', '50'], 1),
			self::parameterPercent_TestCases('percentile', $valueTypesNum, ['#1', '', '50'], 2),

			// strlen() - (sec or #num, time_shift) [str, text, log]
			self::parameterSecNumOffset_TestCases('strlen', $valueTypesStr),
			self::parameterTimeShift_TestCases('strlen', $valueTypesStr, ['#1', ''], 1),

			// sum() - (sec or #num, time_shift) [float, int]
			self::parameterSecNum_TestCases('sum', $valueTypesNum),
			self::parameterTimeShift_TestCases('sum', $valueTypesNum, ['#1', ''], 1),

			// fuzzytime() - (sec) [float, int]
			self::parameterTimeShift_TestCases('fuzzytime', $valueTypesNum),

			// nodata() - (sec) [float, int, str, text, log]
			self::parameterSec_TestCases('nodata', $valueTypesAny),

			// iregexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('iregexp', $valueTypesStr),
			self::parameterSecNum_TestCases('iregexp', $valueTypesStr, ['', ''], 1),

			// logeventid() - (string) [log]
			self::parameterString_TestCases('logeventid', $valueTypesLog),

			// logsource() - (string) [log]
			self::parameterString_TestCases('logsource', $valueTypesLog),

			// regexp() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('regexp', $valueTypesStr),
			self::parameterSecNum_TestCases('regexp', $valueTypesStr, ['', ''], 1),

			// str() - (string, sec or #num) [str, text, log]
			self::parameterString_TestCases('str', $valueTypesStr),
			self::parameterSecNum_TestCases('str', $valueTypesStr, ['', ''], 1),

			// trendavg() - (period, period_shift) [float, int]
			self::parameterPeriod_TestCases('trendavg', $valueTypesNum, ['', 'now/h']),
			self::parameterPeriodShift_TestCases('trendavg', $valueTypesNum, ['1h', ''], 1),

			[
				['fmtnum', ['5'], ITEM_VALUE_TYPE_UINT64, [], true],
				['fmtnum', ['-9'], ITEM_VALUE_TYPE_UINT64, [], false],
				['fmtnum', [], ITEM_VALUE_TYPE_UINT64, [], false],
				['fmtnum', ['7', '7'], ITEM_VALUE_TYPE_UINT64, [], false],
				['fmtnum', ['5'], ITEM_VALUE_TYPE_FLOAT, [], false],
				['fmtnum', ['5.3'], ITEM_VALUE_TYPE_UINT64, [], false],
				['fmtnum', ['NaN'], ITEM_VALUE_TYPE_STR, [], false],
				['fmtnum', ['NaN'], ITEM_VALUE_TYPE_TEXT, [], false],
			],
			[
				['fmttime', ['%B', '-1M'], ITEM_VALUE_TYPE_STR, [], true],
				['fmttime', ['"%m-%Y"', '1M'], ITEM_VALUE_TYPE_STR, [], true],
				['fmttime', ['no_strftime_syntax_validation', '1M'], ITEM_VALUE_TYPE_STR, [], true],
				['fmttime', ['%s', '1K'], ITEM_VALUE_TYPE_STR, [], false],
				['fmttime', ['%B', '"-1M"'], ITEM_VALUE_TYPE_STR, [], false],
			]
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
