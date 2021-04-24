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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						]
					]
				],
				['/host/key']
			],
			[
				'last(/{HOST.HOST}/key)', 0, ['host_macro' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/{HOST.HOST}/key)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/{HOST.HOST}/key',
							'length' => 16,
							'data' => [
								'host' => '{HOST.HOST}',
								'item' => 'key'
							]
						]
					]
				],
				['/{HOST.HOST}/key']
			],
			[
				'{$A} = 5 or last(/host/key)', 12, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/key)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 17,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 7,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 6,
							'match' => '/host/key[ "param1", param2, "param3" ,"param4\""]',
							'length' => 50,
							'data' => [
								'host' => 'host',
								'item' => 'key[ "param1", param2, "param3" ,"param4\""]'
							]
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '#25',
							'length' => 3
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '25',
							'length' => 2
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '10h',
							'length' => 3
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '1h:now/d-1h',
							'length' => 11
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 15,
							'match' => '',
							'length' => 0
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}:{$OFFSET}',
							'length' => 19
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}:now-{$ONE_HOUR}',
							'length' => 25
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}',
							'length' => 9
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR}',
							'length' => 60
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
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key'
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '#25',
							'length' => 3
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 21,
							'match' => '"abc"',
							'length' => 5
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 28,
							'match' => '"\"def\""',
							'length' => 9
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 39,
							'match' => '1',
							'length' => 1
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 42,
							'match' => '1.125',
							'length' => 5
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 49,
							'match' => '-1e12',
							'length' => 5
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 56,
							'match' => '{$M}',
							'length' => 4
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 63,
							'match' => '{$M: context}',
							'length' => 13
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 78,
							'match' => '{#M}',
							'length' => 4
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 84,
							'match' => '{{#M}.regsub()}',
							'length' => 15
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 100,
							'match' => '',
							'length' => 0
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 102,
							'match' => '',
							'length' => 0
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 103,
							'match' => '',
							'length' => 0
						]
					]
				],
				['/host/key', '#25', 'abc' , '"def"', '1', '1.125', '-1e12', '{$M}', '{$M: context}', '{#M}', '{{#M}.regsub()}', '', '', '']
			],
			[
				'last(/{HOST.HOST}/key)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last()', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(10)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last("quoted")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last({$MACRO})', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/host/key,{$MACRO})', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last("/host/key")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/host/key, "1h")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/host/key, 1h, abc)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
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

		$this->assertSame($expected, [
			'rc' => $hist_function_parser->parse($source, $pos),
			'match' => $hist_function_parser->getMatch(),
			'function' => $hist_function_parser->getFunction(),
			'parameters' => $hist_function_parser->getParameters()
		]);
		$this->assertSame(strlen($expected['match']), $hist_function_parser->getLength());
		$this->assertSame(count($unquoted_params), count($hist_function_parser->getParameters()));

		foreach ($unquoted_params as $num => $unquoted_param) {
			$this->assertSame($unquoted_param, $hist_function_parser->getParam($num));
		}
	}
}
