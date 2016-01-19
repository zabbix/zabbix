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


class CFunctionParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			// valid keys
			[
				'last()', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last()',
					'function' => 'last',
					'parameters' => '',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '()',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 1
							]
						]
					]
				],
				['']
			],
			[
				'{host:item.func()}', 11,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'func()',
					'function' => 'func',
					'parameters' => '',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '()',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 1
							]
						]
					]
				],
				['']
			],
			[
				'last("")', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last("")',
					'function' => 'last',
					'parameters' => '""',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '("")',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 1
							]
						]
					]
				],
				['']
			],
			[
				'last( )', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last( )',
					'function' => 'last',
					'parameters' => ' ',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '( )',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 2
							]
						]
					]
				],
				['']
			],
			[
				'last( "")', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last( "")',
					'function' => 'last',
					'parameters' => ' ""',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '( "")',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 2
							]
						]
					]
				],
				['']
			],
			[
				'last( "" )', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last( "" )',
					'function' => 'last',
					'parameters' => ' "" ',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '( "" )',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 2
							]
						]
					]
				],
				['']
			],
			[
				'last(a)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(a)',
					'function' => 'last',
					'parameters' => 'a',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(a)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'a',
								'pos' => 1
							]
						]
					]
				],
				['a']
			],
			[
				'last( a)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last( a)',
					'function' => 'last',
					'parameters' => ' a',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '( a)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'a',
								'pos' => 2
							]
						]
					]
				],
				['a']
			],
			[
				'last("a",)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last("a",)',
					'function' => 'last',
					'parameters' => '"a",',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '("a",)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"a"',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 5
							]
						]
					]
				],
				['a', '']
			],
			[
				'last(a,b,c)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(a,b,c)',
					'function' => 'last',
					'parameters' => 'a,b,c',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(a,b,c)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'a',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'b',
								'pos' => 3
							],
							2 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'c',
								'pos' => 5
							]
						]
					]
				],
				['a', 'b', 'c']
			],
			[
				'last("a","b","c")', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last("a","b","c")',
					'function' => 'last',
					'parameters' => '"a","b","c"',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '("a","b","c")',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"a"',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"b"',
								'pos' => 5
							],
							2 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"c"',
								'pos' => 9
							]
						]
					]
				],
				['a', 'b', 'c']
			],
			[
				'g.last("\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" )', 2,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last("\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" )',
					'function' => 'last',
					'parameters' => '"\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '("\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" )',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"\"aaa\""',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"bbb"',
								'pos' => 12
							],
							2 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"ccc"',
								'pos' => 18
							],
							3 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '"ddd"',
								'pos' => 26
							],
							4 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 33
							],
							5 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 37
							],
							6 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 40
							],
							7 => [
								'type' => CFunctionParser::PARAM_QUOTED,
								'raw' => '""',
								'pos' => 45
							],
							8 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 49
							],
							9 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 51
							],
							10 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 54
							],
							11 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'eee',
								'pos' => 55
							],
							12 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'fff',
								'pos' => 60
							],
							13 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'ggg ',
								'pos' => 64
							],
							14 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'hhh" ',
								'pos' => 70
							]
						]
					]
				],
				['"aaa"', 'bbb', 'ccc', 'ddd', '', '', '', '', '', '', '', 'eee', 'fff', 'ggg ', 'hhh" ']
			],
			[
				'last(("a",)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'last(("a",)',
					'function' => 'last',
					'parameters' => '("a",',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(("a",)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '("a"',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => 6
							]
						]
					]
				],
				['("a"', '']
			],
			[
				'last(ГУГЛ))654', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'last(ГУГЛ)',
					'function' => 'last',
					'parameters' => 'ГУГЛ',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(ГУГЛ)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'ГУГЛ',
								'pos' => 1
							]
						]
					]
				],
				['ГУГЛ']
			],
			[
				'last(ГУГЛ])654', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'last(ГУГЛ])',
					'function' => 'last',
					'parameters' => 'ГУГЛ]',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(ГУГЛ])',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => 'ГУГЛ]',
								'pos' => 1
							]
						]
					]
				],
				['ГУГЛ]']
			],
			[
				'last(ГУГЛ]654', 2,
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
				'lastГУГЛ]654', 2,
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
				'last("a", "b)', 0,
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
				'last', 0,
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
				'', 0,
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
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $expected, $unquoted_params) {
		static $function_parser = null;

		if ($function_parser === null) {
			$function_parser = new CFunctionParser();
		}

		$this->assertSame($expected, [
			'rc' => $function_parser->parse($source, $pos),
			'match' => $function_parser->getMatch(),
			'function' => $function_parser->getFunction(),
			'parameters' => $function_parser->getParameters(),
			'params_raw' => $function_parser->getParamsRaw()
		]);
		$this->assertSame(strlen($expected['match']), $function_parser->getLength());
		$this->assertSame(count($unquoted_params), $function_parser->getParamsNum());

		for ($n = 0, $count = $function_parser->getParamsNum(); $n < $count; $n++) {
			$this->assertSame($unquoted_params[$n], $function_parser->getParam($n));
		}
	}
}
