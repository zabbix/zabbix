<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/host/key']
			],
			[
				'last(/{HOST.HOST}/key)', 0, ['host_macro' => true],
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/{HOST.HOST}/key']
			],
			[
				'last(/{HOST.HOST}/key)', 0, ['host_macro_n' => true],
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/{HOST.HOST}/key']
			],
			[
				'last(/{HOST.HOST3}/key)', 0, ['host_macro_n' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/{HOST.HOST3}/key)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/{HOST.HOST3}/key',
							'length' => 17,
							'data' => [
								'host' => '{HOST.HOST3}',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/{HOST.HOST3}/key']
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
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
								'item' => 'key[ "param1", param2, "param3" ,"param4\""]',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/host/key[ "param1", param2, "param3" ,"param4\""]']
			],
			[
				'last(/host/*)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/host/*)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/host/*)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/*',
							'length' => 7,
							'data' => [
								'host' => 'host',
								'item' => '*',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/host/*']
			],
			[
				'last(/*/key)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/*/key)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/*/key)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/*/key',
							'length' => 6,
							'data' => [
								'host' => '*',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/*/key']
			],
			[
				'last(/'.'/key)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/'.'/key)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/'.'/key)', 0, ['empty_host' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/'.'/key)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/'.'/key',
							'length' => 5,
							'data' => [
								'host' => '',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/'.'/key']
			],
			[
				'last(/'.'/*)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/'.'/*)', 0, ['calculated' => true, 'empty_host' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/'.'/*)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/'.'/*',
							'length' => 3,
							'data' => [
								'host' => '',
								'item' => '*',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/'.'/*']
			],
			[
				'last(/*/*)', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/*/*)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(/*/*)',
					'function' => 'last',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/*/*',
							'length' => 4,
							'data' => [
								'host' => '*',
								'item' => '*',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/*/*']
			],
			[
				'sum(/host/key?[tag="a" and not tag="b"], 1m)', 0, ['calculated' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'sum(/host/key?[tag="a" and not tag="b"], 1m)',
					'function' => 'sum',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 4,
							'match' => '/host/key?[tag="a" and not tag="b"]',
							'length' => 35,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '?[tag="a" and not tag="b"]',
									'tokens' => [
										[
											'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
											'pos' => 15,
											'match' => 'tag',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 18,
											'match' => '=',
											'length' => 1
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_STRING,
											'pos' => 19,
											'match' => '"a"',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 23,
											'match' => 'and',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 27,
											'match' => 'not',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
											'pos' => 31,
											'match' => 'tag',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 34,
											'match' => '=',
											'length' => 1
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_STRING,
											'pos' => 35,
											'match' => '"b"',
											'length' => 3
										]
									]
								]
							]
						],
						1 => [
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 41,
							'match' => '1m',
							'length' => 2,
							'data' => [
								'sec_num' => '1m',
								'time_shift' => ''
							]
						]
					]
				],
				['/host/key?[tag="a" and not tag="b"]', '1m']
			],
			[
				'sum(/host/key?[tag={$MACRO} and not tag={#MACRO}], 1m)', 0, ['usermacros' => true, 'lldmacros' => true, 'calculated' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'sum(/host/key?[tag={$MACRO} and not tag={#MACRO}], 1m)',
					'function' => 'sum',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 4,
							'match' => '/host/key?[tag={$MACRO} and not tag={#MACRO}]',
							'length' => 45,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '?[tag={$MACRO} and not tag={#MACRO}]',
									'tokens' => [
										[
											'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
											'pos' => 15,
											'match' => 'tag',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 18,
											'match' => '=',
											'length' => 1
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_USER_MACRO,
											'pos' => 19,
											'match' => '{$MACRO}',
											'length' => 8
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 28,
											'match' => 'and',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 32,
											'match' => 'not',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
											'pos' => 36,
											'match' => 'tag',
											'length' => 3
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
											'pos' => 39,
											'match' => '=',
											'length' => 1
										],
										[
											'type' => CFilterParser::TOKEN_TYPE_LLD_MACRO,
											'pos' => 40,
											'match' => '{#MACRO}',
											'length' => 8
										]
									]
								]
							]
						],
						1 => [
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 51,
							'match' => '1m',
							'length' => 2,
							'data' => [
								'sec_num' => '1m',
								'time_shift' => ''
							]
						]
					]
				],
				['/host/key?[tag={$MACRO} and not tag={#MACRO}]', '1m']
			],
			[
				'sum(/host/key?[tag={$MACRO} and not tag="b"], 1m)', 0, ['lldmacros' => true, 'calculated' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'sum(/host/key?[tag={#MACRO} and not tag="b"], 1m)', 0, ['usermacros' => true, 'calculated' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
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
				'last(/{HOST.HOST5}/key)', 0, ['host_macro' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
			],
			[
				'last(/{HOST.HOST}/key)', 0, ['host_macro' => true],
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						]
					]
				],
				['/{HOST.HOST}/key']
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '#25',
							'length' => 3,
							'data' => [
								'sec_num' => '#25',
								'time_shift' => ''
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '25',
							'length' => 2,
							'data' => [
								'sec_num' => '25',
								'time_shift' => ''
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '10h',
							'length' => 3,
							'data' => [
								'sec_num' => '10h',
								'time_shift' => ''
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '1h:now/d-1h',
							'length' => 11,
							'data' => [
								'sec_num' => '1h',
								'time_shift' => 'now/d-1h'
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}:{$OFFSET}',
							'length' => 19,
							'data' => [
								'sec_num' => '{$PERIOD}',
								'time_shift' => '{$OFFSET}'
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}:now-{$ONE_HOUR}',
							'length' => 25,
							'data' => [
								'sec_num' => '{$PERIOD}',
								'time_shift' => 'now-{$ONE_HOUR}'
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{$PERIOD}',
							'length' => 9,
							'data' => [
								'sec_num' => '{$PERIOD}',
								'time_shift' => ''
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '{{#PERIOD}.regsub("^([0-9]+)", \1)}:now/{#MONTH}-{#ONE_HOUR}',
							'length' => 60,
							'data' => [
								'sec_num' => '{{#PERIOD}.regsub("^([0-9]+)", \1)}',
								'time_shift' => 'now/{#MONTH}-{#ONE_HOUR}'
							]
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
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 16,
							'match' => '#25',
							'length' => 3,
							'data' => [
								'sec_num' => '#25',
								'time_shift' => ''
							]
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
				'nodata(/host/key, "1h")', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'nodata(/host/key, "1h")',
					'function' => 'nodata',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 7,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 18,
							'match' => '"1h"',
							'length' => 4
						]
					]
				],
				['/host/key', '1h']
			],
			[
				'function(/host/key, 1h, 0.5y)', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'function(/host/key, 1h, 0.5y)',
					'function' => 'function',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 9,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_PERIOD,
							'pos' => 20,
							'match' => '1h',
							'length' => 2,
							'data' => [
								'sec_num' => '1h',
								'time_shift' => ''
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 24,
							'match' => '0.5y',
							'length' => 4
						]
					]
				],
				['/host/key', '1h', '0.5y']
			],
			[
				'nodata(/host/key, "\\\\1h\\\\")', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'nodata(/host/key, "\\\\1h\\\\")',
					'function' => 'nodata',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 7,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 18,
							'match' => '"\\\\1h\\\\"',
							'length' => 8
						]
					]
				],
				['/host/key', '\\1h\\']
			],
			[
				'nodata(/host/key, "\\"")', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'nodata(/host/key, "\\"")',
					'function' => 'nodata',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 7,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 18,
							'match' => '"\\""',
							'length' => 4
						]
					]
				],
				['/host/key', '"']
			],
			[
				'find(/host/key,,"like","\\"")', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'find(/host/key,,"like","\\"")',
					'function' => 'find',
					'parameters' => [
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUERY,
							'pos' => 5,
							'match' => '/host/key',
							'length' => 9,
							'data' => [
								'host' => 'host',
								'item' => 'key',
								'filter' => [
									'match' => '',
									'tokens' => []
								]
							]
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED,
							'pos' => 15,
							'match' => '',
							'length' => 0
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 16,
							'match' => '"like"',
							'length' => 6
						],
						[
							'type' => CHistFunctionParser::PARAM_TYPE_QUOTED,
							'pos' => 23,
							'match' => '"\\""',
							'length' => 4
						]
					]
				],
				['/host/key', '', 'like', '"']
			],
			[
				'nodata(/host/key, "\\\\1h\\")', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'function' => '',
					'parameters' => []
				],
				[]
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
