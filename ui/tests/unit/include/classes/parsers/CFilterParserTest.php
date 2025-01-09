<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use PHPUnit\Framework\TestCase;

class CFilterParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'?[tag="name"]', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[tag="name"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 2,
							'match' => 'tag',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 5,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 6,
							'match' => '"name"',
							'length' => 6
						]
					]
				]
			],
			[
				'?[ group = "\\"string1\\"" and tag = "\\"string2\\"" ]', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[ group = "\"string1\"" and tag = "\"string2\"" ]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 3,
							'match' => 'group',
							'length' => 5
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 9,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 11,
							'match' => '"\"string1\""',
							'length' => 13
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 25,
							'match' => 'and',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 29,
							'match' => 'tag',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 33,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 35,
							'match' => '"\"string2\""',
							'length' => 13
						]
					]
				]
			],
			[
				'?[((tag="tag1" or group="name") and tag="tag2")]', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[((tag="tag1" or group="name") and tag="tag2")]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_OPEN_BRACE,
							'pos' => 2,
							'match' => '(',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPEN_BRACE,
							'pos' => 3,
							'match' => '(',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 4,
							'match' => 'tag',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 7,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 8,
							'match' => '"tag1"',
							'length' => 6
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 15,
							'match' => 'or',
							'length' => 2
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 18,
							'match' => 'group',
							'length' => 5
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 23,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 24,
							'match' => '"name"',
							'length' => 6
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_CLOSE_BRACE,
							'pos' => 30,
							'match' => ')',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 32,
							'match' => 'and',
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
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 40,
							'match' => '"tag2"',
							'length' => 6
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_CLOSE_BRACE,
							'pos' => 46,
							'match' => ')',
							'length' => 1
						]
					]
				]
			],
			[
				'?[tag="name"] text', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '?[tag="name"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 2,
							'match' => 'tag',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 5,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 6,
							'match' => '"name"',
							'length' => 6
						]

					]
				]
			],
			[
				'?["string1" = "string2"]', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?["string1" = "string2"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 2,
							'match' => '"string1"',
							'length' => 9
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 12,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 14,
							'match' => '"string2"',
							'length' => 9
						]

					]
				]
			],
			[
				'?["{$MACRO}" = "{#MACRO}"]', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?["{$MACRO}" = "{#MACRO}"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 2,
							'match' => '"{$MACRO}"',
							'length' => 10
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 13,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 15,
							'match' => '"{#MACRO}"',
							'length' => 10
						]
					]
				]
			],
			[
				'?[{$MACRO} <> {#MACRO}]', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[{$MACRO} <> {#MACRO}]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_USER_MACRO,
							'pos' => 2,
							'match' => '{$MACRO}',
							'length' => 8
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 11,
							'match' => '<>',
							'length' => 2
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_LLD_MACRO,
							'pos' => 14,
							'match' => '{#MACRO}',
							'length' => 8
						]
					]
				]
			],
			[
				'?[{{$MACRO}.regsub("^([0-9]+)", \1)} <> {#MACRO}]', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[{{$MACRO}.regsub("^([0-9]+)", \1)} <> {#MACRO}]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_USER_MACRO,
							'pos' => 2,
							'match' => '{{$MACRO}.regsub("^([0-9]+)", \1)}',
							'length' => 34
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 37,
							'match' => '<>',
							'length' => 2
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_LLD_MACRO,
							'pos' => 40,
							'match' => '{#MACRO}',
							'length' => 8
						]
					]
				]
			],
			[
				'?[{$MACRO} = {#MACRO}]', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			],
			[
				'?[{$MACRO} = {#MACRO}]', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			],
			[
				'?[{$MACRO} = {#MACRO}]', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			],
			[
				'?[tag=tag]', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			],
			[
				'?[()]', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			],
			[
				'?[(tag = "tag"]', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'tokens' => []
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testFilterParser($source, $pos, $options, $expected) {
		$filter_parser = new CFilterParser($options);

		$this->assertSame($expected, [
			'rc' => $filter_parser->parse($source, $pos),
			'match' => $filter_parser->getMatch(),
			'tokens' => $filter_parser->getTokens()
		]);
		$this->assertSame(strlen($expected['match']), $filter_parser->getLength());
	}
}
