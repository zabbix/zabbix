<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/classes/parsers/CItemKey.php';
require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/locales.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class class_cItemKey extends PHPUnit_Framework_TestCase {
	public static function provider() {
		return [
			// valid keys
			[
				'key', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key',
					'key_id' => 'key',
					'parameters' => []
				],
				[]
			],
			[
				'key[]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 1
								]
							]
						]
					]
				],
				['']
			],
			[
				'key[][]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[][]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 1
								]
							]
						],
						1 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[]',
							'pos' => 5,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 1
								]
							]
						]
					]
				],
				['', '']
			],
			[
				'key[""]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[""]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[""]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 1
								]
							]
						]
					]
				],
				['']
			],
			[
				'key[ ]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[ ]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ ]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 2
								]
							]
						]
					]
				],
				['']
			],
			[
				'key[ ""]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[ ""]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ ""]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 2
								]
							]
						]
					]
				],
				['']
			],
			[
				'key[ "" ]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[ "" ]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ "" ]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 2
								]
							]
						]
					]
				],
				['']
			],
			[
				'key[a]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[a]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[a]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'a',
									'pos' => 1
								]
							]
						]
					]
				],
				['a']
			],
			[
				'key[ a]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[ a]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ a]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'a',
									'pos' => 2
								]
							]
						]
					]
				],
				['a']
			],
			[
				'key[ a ]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[ a ]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ a ]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'a ',
									'pos' => 2
								]
							]
						]
					]
				],
				['a ']
			],
			[
				'key["a"]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["a"]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["a"]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"a"',
									'pos' => 1
								]
							]
						]
					]
				],
				['a']
			],
			[
				'key["a",]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["a",]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["a",]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"a"',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 5
								]
							]
						]
					]
				],
				['a', '']
			],
			[
				'key[a,b,c]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key[a,b,c]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[a,b,c]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'a',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'b',
									'pos' => 3
								],
								2 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'c',
									'pos' => 5
								]
							]
						]
					]
				],
				['a', 'b', 'c']
			],
			[
				'key["a","b","c"]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["a","b","c"]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["a","b","c"]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"a"',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"b"',
									'pos' => 5
								],
								2 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"c"',
									'pos' => 9
								]
							]
						]
					]
				],
				['a', 'b', 'c']
			],
			[
				'key["a","b","c"]["d"]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["a","b","c"]["d"]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["a","b","c"]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"a"',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"b"',
									'pos' => 5
								],
								2 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"c"',
									'pos' => 9
								]
							]
						],
						1 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["d"]',
							'pos' => 16,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"d"',
									'pos' => 1
								]
							]
						]
					]
				],
				['a', 'b', 'c', 'd']
			],
			[
				'key["a","b","c",[["d", ["e\",]" ] ], f"]]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["a","b","c",[["d", ["e\",]" ] ], f"]]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["a","b","c",[["d", ["e\",]" ] ], f"]]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"a"',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"b"',
									'pos' => 5
								],
								2 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"c"',
									'pos' => 9
								],
								3 => [
									'type' => CItemKey::PARAM_ARRAY,
									'raw' => '[["d", ["e\",]" ] ], f"]',
									'pos' => 13,
									'parameters' => [
										0 => [
											'type' => CItemKey::PARAM_ARRAY,
											'raw' => '["d", ["e\",]" ] ]',
											'pos' => 1,
											'parameters' => [
												0 => [
													'type' => CItemKey::PARAM_QUOTED,
													'raw' => '"d"',
													'pos' => 1
												],
												1 => [
													'type' => CItemKey::PARAM_ARRAY,
													'raw' => '["e\",]" ]',
													'pos' => 6,
													'parameters' => [
														0 => [
															'type' => CItemKey::PARAM_QUOTED,
															'raw' => '"e\",]"',
															'pos' => 1
														]
													]
												]
											]
										],
										1 => [
											'type' => CItemKey::PARAM_UNQUOTED,
											'raw' => 'f"',
											'pos' => 21
										]
									]
								]
							]
						]
					]
				],
				['a', 'b', 'c', '["d", ["e\",]" ] ], f"']
			],
			[
				'key["\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => 'key["\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '["\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"\"aaa\""',
									'pos' => 1
								],
								1 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"bbb"',
									'pos' => 12
								],
								2 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"ccc"',
									'pos' => 18
								],
								3 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '"ddd"',
									'pos' => 26
								],
								4 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 33
								],
								5 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 37
								],
								6 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 40
								],
								7 => [
									'type' => CItemKey::PARAM_QUOTED,
									'raw' => '""',
									'pos' => 45
								],
								8 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 49
								],
								9 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 51
								],
								10 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => '',
									'pos' => 54
								],
								11 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'eee',
									'pos' => 55
								],
								12 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'fff',
									'pos' => 60
								],
								13 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'ggg ',
									'pos' => 64
								],
								14 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'hhh" ',
									'pos' => 70
								]
							]
						]
					]
				],
				['"aaa"', 'bbb', 'ccc', 'ddd', '', '', '', '', '', '', '', 'eee', 'fff', 'ggg ', 'hhh" ']
			],

			// invalid keys
			[
				'key[["a",]', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS_PART,
					'error' => 'unexpected end of key',
					'key' => 'key',
					'key_id' => 'key',
					'parameters' => []
				],
				[]
			],
			[
				'key[ГУГЛ]654', 0,
				[
					'rc' => CItemKey::PARSE_SUCCESS_PART,
					'error' => 'incorrect syntax near "654"',
					'key' => 'key[ГУГЛ]',
					'key_id' => 'key',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ГУГЛ]',
							'pos' => 3,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'ГУГЛ',
									'pos' => 1
								]
							]
						]
					]
				],
				['ГУГЛ']
			],
			[
				'key[ГУГЛ]654', 2,
				[
					'rc' => CItemKey::PARSE_SUCCESS_PART,
					'error' => 'incorrect syntax near "654"',
					'key' => 'y[ГУГЛ]',
					'key_id' => 'y',
					'parameters' => [
						0 => [
							'type' => CItemKey::PARAM_ARRAY,
							'raw' => '[ГУГЛ]',
							'pos' => 1,
							'parameters' => [
								0 => [
									'type' => CItemKey::PARAM_UNQUOTED,
									'raw' => 'ГУГЛ',
									'pos' => 1
								]
							]
						]
					]
				],
				['ГУГЛ']
			],
			[
				'key[a]654', 8,
				[
					'rc' => CItemKey::PARSE_SUCCESS,
					'error' => '',
					'key' => '4',
					'key_id' => '4',
					'parameters' => []
				],
				[]
			],
			[
				'key[a]654', 9,
				[
					'rc' => CItemKey::PARSE_FAIL,
					'error' => 'key is empty',
					'key' => '',
					'key_id' => '',
					'parameters' => []
				],
				[]
			],
/*	*/
/*			['key[a]654', false],
			['key["a"]654', false],
			['key[a][[b]', false],
			['key["a"][["b"]', false],
			['key["a"] ["b"]', false],
			['key(a)', false],
			['key[a]]', false],
			['key["a"]]', false],
			['key["a]', false],
			['abc:def', false],
			// 256 char long key (decided that this key is valid)
			//array('0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000', false),
			// UTF8 chars
			['ГУГЛ', false],
			['', false],
			[',telnet', false],
			['telnet,', false],
			['telnet,1023', false],
			['telnet,1023[]', false],
			['[]', false]*/
		];
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseItemKey($key, $pos, $expectedResult, $unquoted_params) {
		static $item_key_parser = null;

		if ($item_key_parser === null) {
			$item_key_parser = new CItemKey();
		}

		$rc = $item_key_parser->parse($key, $pos);

		$result = [
			'rc' => $rc,
			'error' => $item_key_parser->getError(),
			'key' => $item_key_parser->getKey(),
			'key_id' => $item_key_parser->getKeyId(),
			'parameters' => $item_key_parser->getParamsRaw()
		];
		$this->assertEquals($expectedResult, $result);
		$this->assertEquals(count($unquoted_params), $item_key_parser->getParamsNum());

		for ($n = 0, $count = $item_key_parser->getParamsNum(); $n < $count; $n++) {
			$this->assertEquals($unquoted_params[$n], $item_key_parser->getParam($n));
		}
	}
}
