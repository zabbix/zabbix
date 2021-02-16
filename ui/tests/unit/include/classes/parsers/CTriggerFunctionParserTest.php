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


// TODO miks: delete thist file. There will be no separate parser for trigger/math functions. One parser will parse all functions.
class CTriggerFunctionParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			// valid keys
			[
				'func(/host/item)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							]
						]
					]
				],
				['/host/item']
			],
			[
				'func(/host/item,0)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,0)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,0',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,0)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '0',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '0']
			],
			[
				'func(/host/item,#5)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,#5)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,#5',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,#5)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '#5',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '#5']
			],
			[
				'func(/host/item,30s)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,30s)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,30s',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,30s)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '30s',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '30s']
			],
			[
				'func(/host/item,#5:now-1d)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,#5:now-1d)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,#5:now-1d',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,#5:now-1d)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '#5:now-1d',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '#5:now-1d']
			],
			[
				'func(/host/item,30m:now-1d)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,30m:now-1d)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,30m:now-1d',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,30m:now-1d)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '30m:now-1d',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '30m:now-1d']
			],
			[
				'func(/host/item,1h:now/1h)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item,1h:now/1h)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item,1h:now/1h',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,1h:now/1h)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '1h:now/1h',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '1h:now/1h']
			],
			[
				'func(/host/vfs.fs.size[/tmp,pfree])', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/vfs.fs.size[/tmp,pfree])',
					'host' => 'host',
					'item' => 'vfs.fs.size[/tmp,pfree]',
					'function' => 'func',
					'parameters' => '/host/vfs.fs.size[/tmp,pfree]',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/vfs.fs.size[/tmp,pfree])',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/vfs.fs.size[/tmp,pfree]',
								'pos' => 1
							]
						]
					]
				],
				['/host/vfs.fs.size[/tmp,pfree]']
			],
			[
				'min(min(/host/item,1h),min(/host/item,1h),25)', 4,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'min(/host/item,1h)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'min',
					'parameters' => '/host/item,1h',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item,1h)',
						'pos' => 3,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '1h',
								'pos' => 12
							]
						]
					]
				],
				['/host/item', '1h']
			],
			[
				'min(min(/host1/item2,1h),avg(/host2/item2,1h)*100,25)', 25,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'avg(/host2/item2,1h)',
					'host' => 'host2',
					'item' => 'item2',
					'function' => 'avg',
					'parameters' => '/host2/item2,1h',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host2/item2,1h)',
						'pos' => 3,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host2/item2',
								'pos' => 1
							],
							1 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '1h',
								'pos' => 14
							]
						]
					]
				],
				['/host2/item2', '1h']
			],
			// invalid keys
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func("', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func()', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func( )', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(,)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(")', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func("")', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func( "" )', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(a)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func( a)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func("a",)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(a,b,c)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func("a","b","c")', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func( /host/key)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(/host)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			],
			[
				'func(/host/)', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
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
	 * @param array  $unquoted_params
	 */
	public function testParse(string $source, int $pos, array $expected, array $unquoted_params): void {
		$parser = new CTriggerFunctionParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'host' => $parser->getHost(),
			'item' => $parser->getItem(),
			'function' => $parser->getFunction(),
			'parameters' => $parser->getParameters(),
			'params_raw' => $parser->getParamsRaw()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
		$this->assertSame(count($unquoted_params), $parser->getParamsNum());

		for ($n = 0, $count = $parser->getParamsNum(); $n < $count; $n++) {
			$this->assertSame($unquoted_params[$n], $parser->getParam($n));
		}
	}
}
