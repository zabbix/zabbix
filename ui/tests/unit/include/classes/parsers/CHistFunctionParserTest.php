<?php
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

class CHistFunctionParserTest extends TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'last(/host/key)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key)',
					'function' => 'last',
					'parameters' => '/host/key',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							])
						]
					]
				],
				['/host/key']
			],
			[
				'last(  /host/key  )', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(  /host/key  )',
					'function' => 'last',
					'parameters' => '  /host/key  ',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(  /host/key  )',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 7,
								'length' => 9
							])
						]
					]
				],
				['/host/key']
			],
			[
				'last( /host/key[ "param1", param2, "param3" ,"param4\""] )', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last( /host/key[ "param1", param2, "param3" ,"param4\""] )',
					'function' => 'last',
					'parameters' => ' /host/key[ "param1", param2, "param3" ,"param4\""] ',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '( /host/key[ "param1", param2, "param3" ,"param4\""] )',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key[ "param1", param2, "param3" ,"param4\""]',
								'type' => 11,
								'source' => null,
								'match' => '/host/key[ "param1", param2, "param3" ,"param4\""]',
								'pos' => 6,
								'length' => 50
							])
						]
					]
				],
				['/host/key[ "param1", param2, "param3" ,"param4\""]']
			],
			[
				'last(/host/key, #25)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, #25)',
					'function' => 'last',
					'parameters' => '/host/key, #25',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, #25)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '#25',
								'time_shift' => '',
								'sec_num_contains_macros' => false,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '#25',
								'pos' => 16,
								'length' => 3
							])
						]
					]
				],
				['/host/key', '#25']
			],
			[
				'last(/host/key, 25)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, 25)',
					'function' => 'last',
					'parameters' => '/host/key, 25',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, 25)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '25',
								'time_shift' => '',
								'sec_num_contains_macros' => false,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '25',
								'pos' => 16,
								'length' => 2
							])
						]
					]
				],
				['/host/key', '25']
			],
			[
				'last(/host/key, 10h)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, 10h)',
					'function' => 'last',
					'parameters' => '/host/key, 10h',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, 10h)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '10h',
								'time_shift' => '',
								'sec_num_contains_macros' => false,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '10h',
								'pos' => 16,
								'length' => 3
							])
						]
					]
				],
				['/host/key', '10h']
			],
			[
				'last(/host/key, 1h:now/d-1h)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, 1h:now/d-1h)',
					'function' => 'last',
					'parameters' => '/host/key, 1h:now/d-1h',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, 1h:now/d-1h)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '1h',
								'time_shift' => 'now/d-1h',
								'sec_num_contains_macros' => false,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '1h:now/d-1h',
								'pos' => 16,
								'length' => 11
							])
						]
					]
				],
				['/host/key', '1h:now/d-1h']
			],
			[
				'last(/host/key,)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key,)',
					'function' => 'last',
					'parameters' => '/host/key,',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key,)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '',
								'pos' => 15,
								'length' => 0
							])
						]
					]
				],
				['/host/key', '']
			],
			[
				'last(/host/key, {$PERIOD}:{$OFFSET})', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, {$PERIOD}:{$OFFSET})',
					'function' => 'last',
					'parameters' => '/host/key, {$PERIOD}:{$OFFSET}',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, {$PERIOD}:{$OFFSET})',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '{$PERIOD}',
								'time_shift' => '{$OFFSET}',
								'sec_num_contains_macros' => true,
								'time_shift_contains_macros' => true,
								'source' => null,
								'match' => '{$PERIOD}:{$OFFSET}',
								'pos' => 16,
								'length' => 19
							])
						]
					]
				],
				['/host/key', '{$PERIOD}:{$OFFSET}']
			],
			[
				'last(/host/key, {$PERIOD}:now-{$ONE_HOUR} )', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, {$PERIOD}:now-{$ONE_HOUR} )',
					'function' => 'last',
					'parameters' => '/host/key, {$PERIOD}:now-{$ONE_HOUR} ',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, {$PERIOD}:now-{$ONE_HOUR} )',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '{$PERIOD}',
								'time_shift' => 'now-{$ONE_HOUR}',
								'sec_num_contains_macros' => true,
								'time_shift_contains_macros' => true,
								'source' => null,
								'match' => '{$PERIOD}:now-{$ONE_HOUR}',
								'pos' => 16,
								'length' => 25
							])
						]
					]
				],
				['/host/key', '{$PERIOD}:now-{$ONE_HOUR}']
			],
			[
				'last(/host/key, {$PERIOD} )', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, {$PERIOD} )',
					'function' => 'last',
					'parameters' => '/host/key, {$PERIOD} ',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, {$PERIOD} )',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '{$PERIOD}',
								'time_shift' => '',
								'sec_num_contains_macros' => true,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '{$PERIOD}',
								'pos' => 16,
								'length' => 9
							])
						]
					]
				],
				['/host/key', '{$PERIOD}']
			],
			[
				'last(/host/key, {{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR} )', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, {{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR} )',
					'function' => 'last',
					'parameters' => '/host/key, {{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR} ',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, {{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR} )',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '{{#PERIOD}.regsub("^([0-9]+)", \1)}',
								'time_shift' => 'now/{#MONTH}/{#MONTH}-{#ONE_HOUR}',
								'sec_num_contains_macros' => true,
								'time_shift_contains_macros' => true,
								'source' => null,
								'match' => '{{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR}',
								'pos' => 16,
								'length' => 25
							])
						]
					]
				],
				['/host/key', '{{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR}']
			],
			[
				'last(/host/key, #25, "abc" ,"\"def\"", 1, 1.125, -1e12, {$M} , {$M: context}, {#M}, {{#M}.regsub()},, ,)', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key, #25, "abc" ,"\"def\"", 1, 1.125, -1e12, {$M} , {$M: context}, {#M}, {{#M}.regsub()},, ,)',
					'function' => 'last',
					'parameters' => '/host/key, #25, "abc" ,"\"def\"", 1, 1.125, -1e12, {$M} , {$M: context}, {#M}, {{#M}.regsub()},, ,',
					'params_raw' => [
						'type' => CHistFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/key, #25, "abc" ,"\"def\"", 1, 1.125, -1e12, {$M} , {$M: context}, {#M}, {{#M}.regsub()},, ,)',
						'pos' => 4,
						'parameters' => [
							0 => new CQueryParserResult([
								'host' => 'host',
								'item' => 'key',
								'type' => 11,
								'source' => null,
								'match' => '/host/key',
								'pos' => 5,
								'length' => 9
							]),
							1 => new CPeriodParserResult([
								'sec_num' => '#25',
								'time_shift' => '',
								'sec_num_contains_macros' => false,
								'time_shift_contains_macros' => false,
								'source' => null,
								'match' => '#25',
								'pos' => 16,
								'length' => 3
							]),
							2 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_QUOTED,
								'source' => null,
								'match' => '"abc"',
								'pos' => 21,
								'length' => 5
							]),
							3 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_QUOTED,
								'source' => null,
								'match' => '"\"def\""',
								'pos' => 28,
								'length' => 9
							]),
							4 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '1',
								'pos' => 39,
								'length' => 1
							]),
							5 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '1.125',
								'pos' => 42,
								'length' => 5
							]),
							6 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '-1e12',
								'pos' => 49,
								'length' => 5
							]),
							7 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '{$M}',
								'pos' => 56,
								'length' => 4
							]),
							8 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '{$M: context}',
								'pos' => 63,
								'length' => 13
							]),
							9 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '{#M}',
								'pos' => 78,
								'length' => 4
							]),
							10 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '{{#M}.regsub()}',
								'pos' => 84,
								'length' => 15
							]),
							11 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '',
								'pos' => 100,
								'length' => 0
							]),
							12 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '',
								'pos' => 102,
								'length' => 0
							]),
							13 => new CFunctionParameterResult([
								'type' => CHistFunctionParser::PARAM_UNQUOTED,
								'source' => null,
								'match' => '',
								'pos' => 103,
								'length' => 0
							])
						]
					]
				],
				['/host/key', '#25', 'abc' , '"def"', '1', '1.125', '-1e12', '{$M}', '{$M: context}', '{#M}', '{{#M}.regsub()}', '', '', '']
			],
			[
				'last()', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last(10)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last("quoted")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last({$MACRO})', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last(/host/key,{$MACRO})', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last("/host/key")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last(/host/key, "1h")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'last(/host/key, 1h, abc)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 * @param array  $unquoted_params
	 */
	public function testParse(string $source, int $pos, array $options, array $expected, array $unquoted_params): void {
		$hist_function_parser = new CHistFunctionParser($options);

		$this->assertEquals($expected, [
			'rc' => $hist_function_parser->parse($source, $pos),
			'match' => $hist_function_parser->getMatch(),
			'function' => $hist_function_parser->getFunction(),
			'parameters' => $hist_function_parser->getParameters(),
			'params_raw' => $hist_function_parser->getParamsRaw()
		]);
		$this->assertSame(strlen($expected['match']), $hist_function_parser->getLength());
		$this->assertSame(count($unquoted_params), $hist_function_parser->getParamsNum());

		for ($n = 0, $count = $hist_function_parser->getParamsNum(); $n < $count; $n++) {
			$this->assertSame($unquoted_params[$n], $hist_function_parser->getParam($n));
		}
	}
}
