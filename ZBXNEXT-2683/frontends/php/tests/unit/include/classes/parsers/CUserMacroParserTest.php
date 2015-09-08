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


class CUserMacroParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of valid macros and parsed results.
	 */
	public function testValidProvider() {
		return [
			// normal macros
			[
				'{$MACRO}',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'positions' => [
						'start' => 0,
						'length' => 8
					],
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'{$MACRO_}',
				[[
					'match' => '{$MACRO_}',
					'macro' => '{$MACRO_}',
					'positions' => [
						'start' => 0,
						'length' => 9
					],
					'macro_name' => 'MACRO_',
					'context' => null
				]]
			],
			[
				'{$MACRO_12}',
				[[
					'match' => '{$MACRO_12}',
					'macro' => '{$MACRO_12}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO_12',
					'context' => null
				]]
			],
			[
				'{$MACRO_1.2}',
				[[
					'match' => '{$MACRO_1.2}',
					'macro' => '{$MACRO_1.2}',
					'positions' => [
						'start' => 0,
						'length' => 12
					],
					'macro_name' => 'MACRO_1.2',
					'context' => null
				]]
			],
			// context based unquoted macros
			[
				'{$MACRO:}',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'positions' => [
						'start' => 0,
						'length' => 9
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: }',
				[[
					'match' => '{$MACRO: }',
					'macro' => '{$MACRO:}',
					'positions' => [
						'start' => 0,
						'length' => 10
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:   }',
				[[
					'match' => '{$MACRO:   }',
					'macro' => '{$MACRO:}',
					'positions' => [
						'start' => 0,
						'length' => 12
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:\'\'}',
				[[
					'match' => '{$MACRO:\'\'}',
					'macro' => '{$MACRO:\'\'}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => '\'\''
				]]
			],
			[
				'{$MACRO:A }',
				[[
					'match' => '{$MACRO:A }',
					'macro' => '{$MACRO:A }',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => 'A '
				]]
			],
			[
				'{$MACRO:A}',
				[[
					'match' => '{$MACRO:A}',
					'macro' => '{$MACRO:A}',
					'positions' => [
						'start' => 0,
						'length' => 10
					],
					'macro_name' => 'MACRO',
					'context' => 'A'
				]]
			],
			[
				'{$MACRO:A"}',
				[[
					'match' => '{$MACRO:A"}',
					'macro' => '{$MACRO:A"}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => 'A"'
				]]
			],
			[
				'{$MACRO:context}',
				[[
					'match' => '{$MACRO:context}',
					'macro' => '{$MACRO:context}',
					'positions' => [
						'start' => 0,
						'length' => 16
					],
					'macro_name' => 'MACRO',
					'context' => 'context'
				]]
			],
			[
				'{$MACRO:<context>}',
				[[
					'match' => '{$MACRO:<context>}',
					'macro' => '{$MACRO:<context>}',
					'positions' => [
						'start' => 0,
						'length' => 18
					],
					'macro_name' => 'MACRO',
					'context' => '<context>'
				]]
			],
			[
				'{$MACRO:\"}',
				[[
					'match' => '{$MACRO:\"}',
					'macro' => '{$MACRO:\"}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => '\"'
				]]
			],
			[
				'{$MACRO:{}',
				[[
					'match' => '{$MACRO:{}',
					'macro' => '{$MACRO:{}',
					'positions' => [
						'start' => 0,
						'length' => 10
					],
					'macro_name' => 'MACRO',
					'context' => '{'
				]]
			],
			[
				'{$MACRO:\}',
				[[
					'match' => '{$MACRO:\}',
					'macro' => '{$MACRO:\}',
					'positions' => [
						'start' => 0,
						'length' => 10
					],
					'macro_name' => 'MACRO',
					'context' => '\\'
				]]
			],
			[
				'{$MACRO:\\\\}',
				[[
					'match' => '{$MACRO:\\\\}',
					'macro' => '{$MACRO:\\\\}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => '\\\\'
				]]
			],
			[
				'{$MACRO:\"\}',
				[[
					'match' => '{$MACRO:\"\}',
					'macro' => '{$MACRO:\"\}',
					'positions' => [
						'start' => 0,
						'length' => 12
					],
					'macro_name' => 'MACRO',
					'context' => '\"\\'
				]]
			],
			[
				'{$MACRO:abc"def}',
				[[
					'match' => '{$MACRO:abc"def}',
					'macro' => '{$MACRO:abc"def}',
					'positions' => [
						'start' => 0,
						'length' => 16
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"def'
				]]
			],
			[
				'{$MACRO:abc"def"}',
				[[
					'match' => '{$MACRO:abc"def"}',
					'macro' => '{$MACRO:abc"def"}',
					'positions' => [
						'start' => 0,
						'length' => 17
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"def"'
				]]
			],
			[
				'{$MACRO:abc"def"ghi}',
				[[
					'match' => '{$MACRO:abc"def"ghi}',
					'macro' => '{$MACRO:abc"def"ghi}',
					'positions' => [
						'start' => 0,
						'length' => 20
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"def"ghi'
				]]
			],
			[
				'{$MACRO:abc"\\}',
				[[
					'match' => '{$MACRO:abc"\\}',
					'macro' => '{$MACRO:abc"\\}',
					'positions' => [
						'start' => 0,
						'length' => 14
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"\\'
				]]
			],
			// context based quoted macros
			[
				'{$MACRO:""}',
				[[
					'match' => '{$MACRO:""}',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 0,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: " " }',
				[[
					'match' => '{$MACRO: " " }',
					'macro' => '{$MACRO:" "}',
					'positions' => [
						'start' => 0,
						'length' => 14
					],
					'macro_name' => 'MACRO',
					'context' => ' '
				]]
			],
			[
				'{$MACRO: ""}',
				[[
					'match' => '{$MACRO: ""}',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 0,
						'length' => 12
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:"" }',
				[[
					'match' => '{$MACRO:"" }',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 0,
						'length' => 12
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: "    " }',
				[[
					'match' => '{$MACRO: "    " }',
					'macro' => '{$MACRO:"    "}',
					'positions' => [
						'start' => 0,
						'length' => 17
					],
					'macro_name' => 'MACRO',
					'context' => '    '
				]]
			],
			[
				'{$MACRO:    "    "      }',
				[[
					'match' => '{$MACRO:    "    "      }',
					'macro' => '{$MACRO:"    "}',
					'positions' => [
						'start' => 0,
						'length' => 25
					],
					'macro_name' => 'MACRO',
					'context' => '    '
				]]
			],
			[
				'{$MACRO:    ""      }',
				[[
					'match' => '{$MACRO:    ""      }',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 0,
						'length' => 21
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:"A" }',
				[[
					'match' => '{$MACRO:"A" }',
					'macro' => '{$MACRO:"A"}',
					'positions' => [
						'start' => 0,
						'length' => 13
					],
					'macro_name' => 'MACRO',
					'context' => 'A'
				]]
			],
			[
				'{$MACRO:"{#MACRO}"}',
				[[
					'match' => '{$MACRO:"{#MACRO}"}',
					'macro' => '{$MACRO:"{#MACRO}"}',
					'positions' => [
						'start' => 0,
						'length' => 19
					],
					'macro_name' => 'MACRO',
					'context' => '{#MACRO}'
				]]
			],
			[
				'{$MACRO:"\abc"}',
				[[
					'match' => '{$MACRO:"\abc"}',
					'macro' => '{$MACRO:"\abc"}',
					'positions' => [
						'start' => 0,
						'length' => 15
					],
					'macro_name' => 'MACRO',
					'context' => '\abc'
				]]
			],
			[
				'{$MACRO:"abc\def"}',
				[[
					'match' => '{$MACRO:"abc\def"}',
					'macro' => '{$MACRO:"abc\def"}',
					'positions' => [
						'start' => 0,
						'length' => 18
					],
					'macro_name' => 'MACRO',
					'context' => 'abc\def'
				]]
			],
			[
				'{$MACRO:"\abc\    "}',
				[[
					'match' => '{$MACRO:"\abc\    "}',
					'macro' => '{$MACRO:"\abc\    "}',
					'positions' => [
						'start' => 0,
						'length' => 20
					],
					'macro_name' => 'MACRO',
					'context' => '\abc\    '
				]]
			],
			[
				'{$MACRO:"\\""}',
				[[
					'match' => '{$MACRO:"\\""}',
					'macro' => '{$MACRO:"\\""}',
					'positions' => [
						'start' => 0,
						'length' => 13
					],
					'macro_name' => 'MACRO',
					'context' => '\"'
				]]
			]
		];
	}

	/**
	 * An array of invalid macros and error messages.
	 */
	public function testInvalidProvider() {
		return [
			['{', 'unexpected end of macro'],
			['{MACRO', 'incorrect syntax near "MACRO"'],
			['{MACRO$', 'incorrect syntax near "MACRO$"'],
			['{MACRO}', 'incorrect syntax near "MACRO}"'],
			['{$macro}', 'incorrect syntax near "macro}"'],
			['{#macro}', 'incorrect syntax near "#macro}"'],
			['{$MACRO', 'unexpected end of macro'],
			['{$MACR-O}', 'incorrect syntax near "-O}"'],
			['{$MACR,O}', 'incorrect syntax near ",O}"'],
			['{$MACR"O}', 'incorrect syntax near ""O}"'],
			['{$MACR\O}', 'incorrect syntax near "\O}"'],
			['{$MACR\'O}', 'incorrect syntax near "\'O}"'],
			["{\$MACR'O}", 'incorrect syntax near "\'O}"'],
			['{$MACRo}', 'incorrect syntax near "o}"'],
			['{$MACRO}:', 'incorrect syntax near ":"'],
			['{$MACRO:{#MACRO}}', 'incorrect syntax near "}"'],
			['{$MACRO:"}', 'unexpected end of macro'],
			['{$MACRO:""A""}', 'incorrect syntax near "A""}"'],
			['{$MACRO:"\}', 'unexpected end of macro'],
			['{$MACRO:"\"}', 'unexpected end of macro'],
			['{$MACRO:A}}', 'incorrect syntax near "}"'],
			['{$MACRO:""}}', 'incorrect syntax near "}"'],
			['{$MACRO:}}', 'incorrect syntax near "}"'],
			['{$MACRO:"abc\"}', 'unexpected end of macro'],
			['{$MACR€}', 'incorrect syntax near "€}"'],
			['{$MACR�}', 'incorrect syntax near "�}"'],
			['{$MACRƒabcdefghijklimnopqrstuv123123456456789789000aaabbbcccdddeeefffggghhhiiijjjkkklllmmmnnnooo111}', 'incorrect syntax near "ƒabcdefghijklimnopqrstuv123123456456789789000aaabb ..."'],
			['{$MACRƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}', 'incorrect syntax near "ƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}"'],
			['�', 'incorrect syntax near "�"'],
			['�', 'incorrect syntax near "�"']
		];
	}

	/**
	 * An array of strings containing none or multiple macros and parsed results.
	 */
	public function testMixedProvider() {
		return [
			[
				'{$MACRO1}{$MACRO2}',
				[[
					'match' => '{$MACRO1}',
					'macro' => '{$MACRO1}',
					'positions' => [
						'start' => 0,
						'length' => 9
					],
					'macro_name' => 'MACRO1',
					'context' => null
				],
				[
					'match' => '{$MACRO2}',
					'macro' => '{$MACRO2}',
					'positions' => [
						'start' => 9,
						'length' => 9
					],
					'macro_name' => 'MACRO2',
					'context' => null
				]]
			],
			[
				'abc"def"ghi{$MACRO}',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'positions' => [
						'start' => 11,
						'length' => 8
					],
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'abc"def"ghi{$MACRO:""}',
				[[
					'match' => '{$MACRO:""}',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 11,
						'length' => 11
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'abc"def"ghi{$MACRO:\""}',
				[[
					'match' => '{$MACRO:\""}',
					'macro' => '{$MACRO:\""}',
					'positions' => [
						'start' => 11,
						'length' => 12
					],
					'macro_name' => 'MACRO',
					'context' => '\""'
				]]
			],
			[
				'abc"def\"ghi{$MACRO:""}',
				[]
			],
			[
				'abc"def{$MACRO:\""}',
				[]
			],
			[
				'abc"def{$MACRO:\"\"}',
				[[
					'match' => '{$MACRO:\"\"}',
					'macro' => '{$MACRO:""}',
					'positions' => [
						'start' => 7,
						'length' => 13
					],
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'abc"def{$MACRO:\"abc\"}',
				[[
					'match' => '{$MACRO:\"abc\"}',
					'macro' => '{$MACRO:"abc"}',
					'positions' => [
						'start' => 7,
						'length' => 16
					],
					'macro_name' => 'MACRO',
					'context' => 'abc'
				]]
			],
			[
				'abc"def{$MACRO69:\"abc\"def\"\"}',
				[]
			],
			[
				'abc"def{$MACRO:\\"abc\\\\"defgh\\\\"\\"}',
				[[
					'match' => '{$MACRO:\\"abc\\\\"defgh\\\\"\\"}',
					'macro' => '{$MACRO:"abc\\"defgh\\""}',
					'positions' => [
						'start' => 7,
						'length' => 27
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"defgh"'
				]]
			],
			[
				'"def{$MACRO:\"abc\\\\"defxyz\\\\"\"}',
				[[
					'match' => '{$MACRO:\"abc\\\\"defxyz\\\\"\"}',
					'macro' => '{$MACRO:"abc\"defxyz\""}',
					'positions' => [
						'start' => 4,
						'length' => 28
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"defxyz"'
				]]
			],
			[
				'     "def{$MACRO:\"abc\\\\"qwerty\\\\"\"}',
				[[
					'match' => '{$MACRO:\"abc\\\\"qwerty\\\\"\"}',
					'macro' => '{$MACRO:"abc\"qwerty\""}',
					'positions' => [
						'start' => 9,
						'length' => 28
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"qwerty"'
				]]
			],
			[
				'     "def{$MACRO:     \"abc\\\\"def\\\\"\"}',
				[[
					'match' => '{$MACRO:     \"abc\\\\"def\\\\"\"}',
					'macro' => '{$MACRO:"abc\"def\""}',
					'positions' => [
						'start' => 9,
						'length' => 30
					],
					'macro_name' => 'MACRO',
					'context' => 'abc"def"'
				]]
			],
			[
				'a  abc"def"ghi{$MACRO}{$MACRO}test',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'positions' => [
						'start' => 14,
						'length' => 8
					],
					'macro_name' => 'MACRO',
					'context' => null
				],
				[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'positions' => [
						'start' => 22,
						'length' => 8
					],
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'a  abc"defghi{$MACRO1}{}{{{$:${$${$MACRO2:\"\"}   ',
				[[
					'match' => '{$MACRO1}',
					'macro' => '{$MACRO1}',
					'positions' => [
						'start' => 13,
						'length' => 9
					],
					'macro_name' => 'MACRO1',
					'context' => null
				],
				[
					'match' => '{$MACRO2:\"\"}',
					'macro' => '{$MACRO2:""}',
					'positions' => [
						'start' => 33,
						'length' => 14
					],
					'macro_name' => 'MACRO2',
					'context' => ''
				]]
			],
			[
				'echo[{${$MY.MACRO:}]',
				[[
					'match' => '{$MY.MACRO:}',
					'macro' => '{$MY.MACRO:}',
					'positions' => [
						'start' => 7,
						'length' => 12
					],
					'macro_name' => 'MY.MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{$ABC{$MY.MACRO:}]',
				[[
					'match' => '{$MY.MACRO:}',
					'macro' => '{$MY.MACRO:}',
					'positions' => [
						'start' => 10,
						'length' => 12
					],
					'macro_name' => 'MY.MACRO',
					'context' => ''
				]]
			]
		];
	}

	/**
	 * @dataProvider testValidProvider
	 *
	 * @param string $source		source string to parse
	 * @param array $result			expected resulting array
	 */
	public function testParseValid($source, $result) {
		$parser = new CUserMacroParser($source);

		$this->assertTrue($parser->isValid());
		$this->assertEmpty($parser->getError());
		$this->assertEquals($result, $parser->getMacros());
	}

	/**
	 * @dataProvider testInvalidProvider
	 *
	 * @param string $source		source string to parse
	 * @param string $error		expected error message
	 */
	public function testParseInvalid($source, $error) {
		$parser = new CUserMacroParser($source);

		$this->assertFalse($parser->isValid());
		$this->assertEquals($error, $parser->getError());
		$this->assertEmpty($parser->getMacros());
	}

	/**
	 * @dataProvider testMixedProvider
	 *
	 * @param string $source
	 * @param array $result
	 */

	public function testMixed($source, $result) {
		$parser = new CUserMacroParser($source, false);

		$this->assertFalse($parser->isValid());
		$this->assertEmpty($parser->getError());
		$this->assertEquals($result, $parser->getMacros());
	}
}
