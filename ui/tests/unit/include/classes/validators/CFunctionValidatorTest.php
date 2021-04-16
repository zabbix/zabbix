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

	private static function parameterNoParams_TestCases($func, bool $expected_resut) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];

		foreach ($value_types_any as $value_type) {
			$tests[] = [$func, [], $value_type, [], $expected_resut];
		}

		return $tests;
	}

	private static function parameterItem_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '/Zabbix server/system.cpu.load[all,avg1]';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"/Zabbix server/system.cpu.load[all,avg1]"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = 'Zabbix server/system.cpu.load[all,avg1]';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '12345';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '//key';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '/Zabbix server/';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{$M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{$M: /}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{{$M}.regsub("^([0-9]+)", "{#M}: \1")}}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		return $tests;
	}

	private static function parameterSecNum_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

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

			$params[$no] = '"1w"';
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

			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '1h:{#M}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '#1:{#M}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '1{$M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1h:now/h-1h';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '#1:now/h-1h';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '{$M}:now/h-1h';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '{$M}:{#M}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '#1: now/h-1h';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = ':now/h';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = ':{$M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = ':{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '#1:';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		$options = ['lldmacros' => false];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '1h:{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '#1:{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = ':{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		return $tests;
	}

	private static function parameterPercent_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

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
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '1{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		$options = ['lldmacros' => false];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		return $tests;
	}

	private static function parameterString_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"0"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"1"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"12345"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"01"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"-15"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"1.0"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#0"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#1"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#12345"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#01"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#-15"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"#1.0"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M: /}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"1{$M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"1{#M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = 'abc';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		$options = ['lldmacros' => false];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];
		}

		return $tests;
	}

	private static function parameterCountOperator_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"eq"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"ne"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"gt"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"ge"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"lt"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"le"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"like"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"bitand"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"regexp"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"iregexp"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M: /}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = 'eq';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{$M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"0"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"#12345"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"gt{$M}"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{$M}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{#M}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		$options = ['lldmacros' => false];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		return $tests;
	}

	private static function parameterFindOperator_TestCases($func, array $value_types, array $params = [], $no = 0) {
		$value_types_any = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT];

		$tests = [];
		$options = ['lldmacros' => true];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"regexp"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"iregexp"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"like"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M: /}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{$M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = '';
			$tests[] = [$func, $params, $value_type, $options, array_key_exists($value_type, $value_types)];

			$params[$no] = 'eq';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{$M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '{#M}';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"0"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"#12345"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"gt{$M}"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{$M}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{#M}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}gt"';
			$tests[] = [$func, $params, $value_type, $options, false];
		}

		$options = ['lldmacros' => false];

		foreach ($value_types_any as $value_type) {
			$params[$no] = '"{#M}"';
			$tests[] = [$func, $params, $value_type, $options, false];

			$params[$no] = '"{{#M}.regsub(\"^([0-9]+)\", \"{#M}: \1\")}"';
			$tests[] = [$func, $params, $value_type, $options, false];
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
		$supported_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
		$tests = [];

		foreach (['trendavg', 'trendcount', 'trendmax', 'trendmin', 'trendsum'] as $func) {
			foreach ($types as $value_type) {
				$supported_type = ($func === 'trendcount' || in_array($value_type, $supported_types));

				$tests = array_merge($tests, self::parameterNoParams_TestCases($func, false), [
					[$func, ['/host/key', '1M:now/M'], $value_type, [], (bool) $supported_type],
					[$func, ['/host/key', '1M:now/M-1w+1d/M'], $value_type, [], (bool) $supported_type],
					[$func, ['/host/key', '1y:{$MACRO}'], $value_type, [], (bool) $supported_type],
					[$func, ['/host/key', '{$MACRO}:now/M'], $value_type, [], (bool) $supported_type],
					[$func, ['/host/key', '{$MACRO}:{$MACRO}'], $value_type, [], (bool) $supported_type],
					[$func, ['/host/key', '1y:{#MACRO}'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$func, ['/host/key', '{#MACRO}:now/M'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$func, ['/host/key', '{#MACRO}:{#MACRO}'], $value_type, ['lldmacros' => true], (bool) $supported_type],
					[$func, [], $value_type, [], false],
					[$func, ['/host/key', '', ''], $value_type, [], false],
					[$func, ['/host/key', '1y:now/M'], $value_type, [], false],
					[$func, ['/host/key', '1M:now/w-1w+1d/M'], $value_type, [], false],
					[$func, ['/host/key', '1M:now/M-1w+1d/w'], $value_type, [], false],
					[$func, ['/host/key', '${MACRO}1y:{$MACRO}now/y'], $value_type, [], false]
				]);
			}
		}

		return $tests;
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

		$tests = array_merge(
			// avg() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterNoParams_TestCases('avg', false),
			self::parameterItem_TestCases('avg', $value_types_num, ['', '1h'], 0),
			self::parameterSecNum_TestCases('avg', $value_types_num, ['/host/key'], 1),

			// change() - (item) [float, int, str, text, log]
			self::parameterNoParams_TestCases('change', false),
			self::parameterItem_TestCases('change', $value_types_any, [''], 0),

			// count() - (item, sec|#num:<time_shift>, <pattern>,<operator>) [float, int, str, text, log]
			self::parameterNoParams_TestCases('count', false),
			self::parameterItem_TestCases('count', $value_types_any, ['', '1h'], 0),
			self::parameterSecNum_TestCases('count', $value_types_any, ['/host/key'], 1),
			self::parameterCountOperator_TestCases('count', $value_types_any, ['/host/key', '1h', ''], 2),

			// find() - (item, sec|#num:<time_shift>, <operator>, <pattern>) [str, text, log]
			self::parameterNoParams_TestCases('find', false),
			self::parameterItem_TestCases('find', $value_types_any, ['', '1h', '"like"', '"a"'], 0),
			self::parameterSecNum_TestCases('find', $value_types_any, ['/host/key', '', '"like"', '"a"'], 1),
			self::parameterFindOperator_TestCases('find', $value_types_any, ['/host/key', '1h', ''], 2),

			// last() - (item, <sec|#num>:<time_shift>) [float, int, str, text, log]
			self::parameterNoParams_TestCases('last', false),
			self::parameterItem_TestCases('last', $value_types_any, [], 0),
			self::parameterSecNum_TestCases('last', $value_types_any, ['/host/key'], 1),

			// logeventid() - (item, string) [log]
			self::parameterNoParams_TestCases('logeventid', false),
			self::parameterItem_TestCases('logeventid', $value_types_log, [], 0),
			self::parameterString_TestCases('logeventid', $value_types_log, ['/host/key', ''], 1),

			// logseverity() - (item) [log]
			self::parameterNoParams_TestCases('logseverity', false),
			self::parameterItem_TestCases('logseverity', $value_types_log, [], 0),

			// logsource() - (item, string) [log]
			self::parameterNoParams_TestCases('logsource', false),
			self::parameterItem_TestCases('logsource', $value_types_log, [], 0),
			self::parameterString_TestCases('logsource', $value_types_log, ['/host/key', ''], 1),

			// max() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterNoParams_TestCases('max', false),
			self::parameterItem_TestCases('max', $value_types_num, ['', '1h'], 0),
			self::parameterSecNum_TestCases('max', $value_types_num, ['/host/key'], 1),

			// min() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterNoParams_TestCases('min', false),
			self::parameterItem_TestCases('min', $value_types_num, ['', '1h'], 0),
			self::parameterSecNum_TestCases('min', $value_types_num, ['/host/key'], 1),

			// percentile() - (item, sec|#num:<time_shift>, float) [float, int]
			self::parameterNoParams_TestCases('percentile', false),
			self::parameterItem_TestCases('percentile', $value_types_num, ['', '1h', 10], 0),
			self::parameterSecNum_TestCases('percentile', $value_types_num, ['/host/key', '', '50'], 1),
			self::parameterPercent_TestCases('percentile', $value_types_num, ['/host/key', '#1'], 2),

			// sum() - (item, sec|#num:<time_shift>) [float, int]
			self::parameterNoParams_TestCases('sum', false),
			self::parameterItem_TestCases('sum', $value_types_num, ['', '1h'], 0),
			self::parameterSecNum_TestCases('sum', $value_types_num, ['/host/key'], 1),

			// 'trendavg', 'trendcount', 'trendmax', 'trendmin', 'trendsum'
			self::trendFunctionsTestData()
		);

		foreach ($tests as &$test) {
			$test = [$test[0].'('.implode(', ', $test[1]).')', $test[2], $test[3], $test[4]];
		}
		unset($test);

		return $tests;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($function, $value_type, $options, $expected_result) {
		$function_parser = new CFunctionParser();
		$trigger_function_validator = new CFunctionValidator($options);

		$function_parser->parse($function);

		$result = $trigger_function_validator->validate($function_parser->result)
			&& $trigger_function_validator->validateValueType($value_type, $function_parser->result);

		$this->assertSame($result, $expected_result);
	}
}
