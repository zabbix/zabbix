<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class CFunctionValidatorTest extends TestCase {

	private static function parameterItem_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '/Zabbix server/system.cpu.load[all,avg1]';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'Zabbix server/system.cpu.load[all,avg1]';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '//key';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '/Zabbix server/';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterSecNum_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterSecNumOffset_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterSec_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterTimeShift_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '#1:now-1d';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '60s:now-3600s';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '12345:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01:now-5h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1s:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1m:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1h:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1d:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1w:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1K:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1M:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1G:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1T:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.0:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#0:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#12345:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#01:now-1h';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#-15:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}:now-1h';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterPercent_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1s';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1m';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1h';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1d';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1w';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1K';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1M';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1G';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1T';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '-15.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '0.0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1.0123';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1.01234';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.00000';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '1.';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '.1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '.';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '100.0000';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '100.0001';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	private static function parameterString_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = '0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '-15';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1.0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#1';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '1{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];
			}
		}

		return $tests;
	}

	private static function parameterOperator_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ([[], ['lldmacros' => true], ['lldmacros' => false]] as $options) {
			foreach ($value_types_any as $value_type) {
				$params[$no] = 'eq';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'ne';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'gt';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'ge';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'lt';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'le';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'like';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'band';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'regexp';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = 'iregexp';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M: /}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{$M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '{#M}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)
						&& (!array_key_exists('lldmacros', $options) || $options['lldmacros'] === true)
				];

				$params[$no] = '';
				$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

				$params[$no] = '0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#12345';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#01';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#-15';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '#1.0';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = 'gt{$M}';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{$M}gt';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{#M}gt';
				$tests[] = [$func, $params, $value_type, $options, false];

				$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}gt';
				$tests[] = [$func, $params, $value_type, $options, false];
			}
		}

		return $tests;
	}

	/**
	 * Tests for trend functions: 'trendavg', 'trendcount', 'trendmax', 'trendmin', 'trendsum'.
	 */
	private static function trendFunctionsTestData() {
		$types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
			ITEM_VALUE_TYPE_TEXT
		];
		$functions = ['trendavg', 'trendcount', 'trendmax', 'trendmin', 'trendsum'];
		$supported_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
		$test_data = [];

		foreach ($functions as $function) {
			foreach ($types as $value_type) {
				$supported_type = ($function === 'trendcount' || in_array($value_type, $supported_types));

				$test_data = array_merge($test_data, [
					[$function, ['/host/key', '1M:now/M'], $value_type, [], (bool) $supported_type],
					[$function, ['/host/key', '1M:now/M-1w+1d/M'], $value_type, [], (bool) $supported_type],
					[$function, ['/host/key', '1y:{$MACRO}'], $value_type, [], (bool) $supported_type],
					[$function, ['/host/key', '{$MACRO}:now/M'], $value_type, [], (bool) $supported_type],
					[$function, ['/host/key', '{$MACRO}:{$MACRO}'], $value_type, [], (bool) $supported_type],
					[$function, ['/host/key', '1y:{#MACRO}'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$function, ['/host/key', '{#MACRO}:now/M'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$function, ['/host/key', '{#MACRO}:{#MACRO}'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$function, [], $value_type, [], false],
					[$function, ['/host/key', '', ''], $value_type, [], false],
					[$function, ['/host/key', '1y:now/M'], $value_type, [], false],
					[$function, ['/host/key', '1M:now/w-1w+1d/M'], $value_type, [], false],
					[$function, ['/host/key', '1M:now/M-1w+1d/w'], $value_type, [], false],
					[$function, ['/host/key', '${MACRO}1y:{$MACRO}now/y'], $value_type, [], false]
				]);
			}
		}

		return $test_data;
	}

	public static function provider() {
		$value_types_any = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_TEXT => true
		];
		$value_types_log = [
			ITEM_VALUE_TYPE_LOG => true
		];
		$value_types_num = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$value_types_int = [
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$value_types_str = [
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_LOG => true,
			ITEM_VALUE_TYPE_TEXT => true
		];

		return array_merge(
			// avg() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterItem_TestCases('avg', $value_types_num, ['', '1h'], 0),
			self::parameterSecNum_TestCases('avg', $value_types_num, ['/host/key'], 1),
			self::parameterTimeShift_TestCases('avg', $value_types_num, ['/host/key'], 1),

			// band() - (item, <sec|#num>:<time_shift>, mask) [int]
			self::parameterItem_TestCases('band', $value_types_int, ['', '1h', '1'], 0),
			self::parameterSecNumOffset_TestCases('band', $value_types_int, ['/host/key', '0', '1'], 1),

			// count() - (item, sec|#num:<time_shift>, <pattern>,<operator>) [float, int, str, text, log]
			self::parameterItem_TestCases('count', $value_types_any, ['', '1h', '', ''], 0),
			self::parameterOperator_TestCases('count', $value_types_any, ['/host/key', '1h', '', ''], 3),
			self::parameterTimeShift_TestCases('count', $value_types_any, ['/host/key', '', '', ''], 1),

			// date() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('date', $value_types_any),

			// dayofmonth() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('dayofmonth', $value_types_any),

			// dayofweek() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('dayofweek', $value_types_any),

			// find() - (item, sec|#num:<time_shift>, <operator>, <pattern>) [str, text, log]
			self::parameterItem_TestCases('find', $value_types_any, ['', '1h', 'iregexp', 'a'], 0),
			self::parameterSecNum_TestCases('find', $value_types_any, ['/host/key', '', 'iregexp', 'a'], 1),
			self::parameterTimeShift_TestCases('find', $value_types_any, ['/host/key', '', 'iregexp', 'a'], 1),
			self::parameterItem_TestCases('find', $value_types_any, ['', '1h', 'regexp', 'a'], 0),
			self::parameterSecNum_TestCases('find', $value_types_any, ['/host/key', '', 'regexp', 'a'], 1),
			self::parameterTimeShift_TestCases('find', $value_types_any, ['/host/key', '', 'regexp', 'a'], 1),
			self::parameterItem_TestCases('find', $value_types_any, ['', '1h', 'like', 'a'], 0),
			self::parameterSecNum_TestCases('find', $value_types_any, ['/host/key', '', 'like', 'a'], 1),
			self::parameterTimeShift_TestCases('find', $value_types_any, ['/host/key', '', 'like', 'a'], 1),

			// last() - (item, <sec|#num>:<time_shift>) [float, int, str, text, log]
			self::parameterSecNumOffset_TestCases('last', $value_types_any, ['/host/key'], 1),
			self::parameterTimeShift_TestCases('last', $value_types_any, ['/host/key'], 1),

			// logeventid() - (item, string) [log]
			self::parameterString_TestCases('logeventid', $value_types_log, ['/host/key', ''], 1),

			// logsource() - (item, <string>) [log]
			self::parameterString_TestCases('logsource', $value_types_log, ['/host/key', ''], 1),

			// max() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterSecNum_TestCases('max', $value_types_num, ['/host/key'], 1),
			self::parameterTimeShift_TestCases('max', $value_types_num, ['/host/key'], 1),

			// min() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterSecNum_TestCases('min', $value_types_num, ['/host/key'], 1),
			self::parameterTimeShift_TestCases('min', $value_types_num, ['/host/key'], 1),

			// now() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('now', $value_types_any),

			// percentile() - (item, sec|#num:<time_shift>, float) [float, int]
			self::parameterSecNum_TestCases('percentile', $value_types_num, ['/host/key', '', '50'], 1),
			self::parameterTimeShift_TestCases('percentile', $value_types_num, ['/host/key', '', '50'], 1),
			self::parameterPercent_TestCases('percentile', $value_types_num, ['/host/key', '#1'], 2),

			// sum() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterSecNum_TestCases('sum', $value_types_num, ['/host/key'], 1),
			self::parameterTimeShift_TestCases('sum', $value_types_num, ['/host/key'], 1),

			// time() - (ignored) [float, int, str, text, log]
			self::parameterString_TestCases('time', $value_types_any),

			// 'trendavg', 'trendcount', 'trendmax', 'trendmin', 'trendsum'
			self::trendFunctionsTestData()
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($function_name, $function_param_list, $value_type, $options, $expected_result) {
		$trigger_function_validator = new CFunctionValidator($options);

		$result = $trigger_function_validator->validate([
			'function' => '',
			'functionName' => $function_name,
			'functionParamList' => $function_param_list,
			'valueType' => $value_type
		]);

		$this->assertSame($result, $expected_result);
	}
}
