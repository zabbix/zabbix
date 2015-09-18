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
			// Normal macros without context.
			[
				'{$MACRO}',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'{$MACRO_}',
				[[
					'match' => '{$MACRO_}',
					'macro' => '{$MACRO_}',
					'pos' => 0,
					'macro_name' => 'MACRO_',
					'context' => null
				]]
			],
			[
				'{$MACRO_12}',
				[[
					'match' => '{$MACRO_12}',
					'macro' => '{$MACRO_12}',
					'pos' => 0,
					'macro_name' => 'MACRO_12',
					'context' => null
				]]
			],
			[
				'{$MACRO_1.2}',
				[[
					'match' => '{$MACRO_1.2}',
					'macro' => '{$MACRO_1.2}',
					'pos' => 0,
					'macro_name' => 'MACRO_1.2',
					'context' => null
				]]
			],
			// Context based unquoted macros.
			[
				'{$MACRO:}',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: }',
				[[
					'match' => '{$MACRO: }',
					'macro' => '{$MACRO:}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:   }',
				[[
					'match' => '{$MACRO:   }',
					'macro' => '{$MACRO:}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:\'\'}',
				[[
					'match' => '{$MACRO:\'\'}',
					'macro' => '{$MACRO:\'\'}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\'\''
				]]
			],
			[
				'{$MACRO:A }',
				[[
					'match' => '{$MACRO:A }',
					'macro' => '{$MACRO:A }',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'A '
				]]
			],
			[
				'{$MACRO:A}',
				[[
					'match' => '{$MACRO:A}',
					'macro' => '{$MACRO:A}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'A'
				]]
			],
			[
				'{$MACRO:A"}',
				[[
					'match' => '{$MACRO:A"}',
					'macro' => '{$MACRO:A"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'A"'
				]]
			],
			[
				'{$MACRO:context}',
				[[
					'match' => '{$MACRO:context}',
					'macro' => '{$MACRO:context}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'context'
				]]
			],
			[
				'{$MACRO:<context>}',
				[[
					'match' => '{$MACRO:<context>}',
					'macro' => '{$MACRO:<context>}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '<context>'
				]]
			],
			[
				'{$MACRO1:\"}',
				[[
					'match' => '{$MACRO1:\"}',
					'macro' => '{$MACRO1:\"}',
					'pos' => 0,
					'macro_name' => 'MACRO1',
					'context' => '\"'
				]]
			],
			[
				'{$MACRO:{}',
				[[
					'match' => '{$MACRO:{}',
					'macro' => '{$MACRO:{}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '{'
				]]
			],
			[
				'{$MACRO:\}',
				[[
					'match' => '{$MACRO:\}',
					'macro' => '{$MACRO:\}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\\'
				]]
			],
			[
				'{$MACRO:\\\\}',
				[[
					'match' => '{$MACRO:\\\\}',
					'macro' => '{$MACRO:\\\\}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\\\\'
				]]
			],
			[
				'{$MACRO:\"\}',
				[[
					'match' => '{$MACRO:\"\}',
					'macro' => '{$MACRO:\"\}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\"\\'
				]]
			],
			[
				'{$MACRO:abc"def}',
				[[
					'match' => '{$MACRO:abc"def}',
					'macro' => '{$MACRO:abc"def}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'abc"def'
				]]
			],
			[
				'{$MACRO:abc"def"}',
				[[
					'match' => '{$MACRO:abc"def"}',
					'macro' => '{$MACRO:abc"def"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'abc"def"'
				]]
			],
			[
				'{$MACRO:abc"def"ghi}',
				[[
					'match' => '{$MACRO:abc"def"ghi}',
					'macro' => '{$MACRO:abc"def"ghi}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'abc"def"ghi'
				]]
			],
			[
				'{$MACRO:abc"\\}',
				[[
					'match' => '{$MACRO:abc"\\}',
					'macro' => '{$MACRO:abc"\\}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'abc"\\'
				]]
			],
			// Context based quoted macros.
			[
				'{$MACRO:""}',
				[[
					'match' => '{$MACRO:""}',
					'macro' => '{$MACRO:""}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: " " }',
				[[
					'match' => '{$MACRO: " " }',
					'macro' => '{$MACRO:" "}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ' '
				]]
			],
			[
				'{$MACRO: ""}',
				[[
					'match' => '{$MACRO: ""}',
					'macro' => '{$MACRO:""}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:"" }',
				[[
					'match' => '{$MACRO:"" }',
					'macro' => '{$MACRO:""}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO: "    " }',
				[[
					'match' => '{$MACRO: "    " }',
					'macro' => '{$MACRO:"    "}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '    '
				]]
			],
			[
				'{$MACRO:    "    "      }',
				[[
					'match' => '{$MACRO:    "    "      }',
					'macro' => '{$MACRO:"    "}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '    '
				]]
			],
			[
				'{$MACRO:    ""      }',
				[[
					'match' => '{$MACRO:    ""      }',
					'macro' => '{$MACRO:""}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:"A" }',
				[[
					'match' => '{$MACRO:"A" }',
					'macro' => '{$MACRO:"A"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'A'
				]]
			],
			[
				'{$MACRO:"{#MACRO}"}',
				[[
					'match' => '{$MACRO:"{#MACRO}"}',
					'macro' => '{$MACRO:"{#MACRO}"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '{#MACRO}'
				]]
			],
			[
				'{$MACRO:"\abc"}',
				[[
					'match' => '{$MACRO:"\abc"}',
					'macro' => '{$MACRO:"\abc"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\abc'
				]]
			],
			[
				'{$MACRO:"abc\def"}',
				[[
					'match' => '{$MACRO:"abc\def"}',
					'macro' => '{$MACRO:"abc\def"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => 'abc\def'
				]]
			],
			[
				'{$MACRO:"\abc\    "}',
				[[
					'match' => '{$MACRO:"\abc\    "}',
					'macro' => '{$MACRO:"\abc\    "}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '\abc\    '
				]]
			],
			[
				'{$MACRO2:"\\\""}',
				[[
					'match' => '{$MACRO2:"\\\""}',
					'macro' => '{$MACRO2:"\\\""}',
					'pos' => 0,
					'macro_name' => 'MACRO2',
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
			['', 'macro is empty'],
			['{', 'unexpected end of macro'],
			['{{', 'incorrect syntax near "{"'],
			['{{{', 'incorrect syntax near "{{"'],
			['{$', 'unexpected end of macro'],
			['{${$', 'incorrect syntax near "{$"'],
			['{${{$', 'incorrect syntax near "{{$"'],
			['{$${$', 'incorrect syntax near "${$"'],
			['{${$$', 'incorrect syntax near "{$$"'],
			['{${{$${$', 'incorrect syntax near "{{$${$"'],
			['{$M', 'unexpected end of macro'],
			['{$.', 'unexpected end of macro'],
			['{$"', 'incorrect syntax near """'],
			['{$-', 'incorrect syntax near "-"'],
			['{$M:', 'unexpected end of macro'],
			['{$M:"', 'unexpected end of macro'],
			['{$M:""', 'unexpected end of macro'],
			['{$M:""{', 'incorrect syntax near "{"'],
			['{$M:""{$', 'incorrect syntax near "{$"'],
			['{$M:""{$M', 'incorrect syntax near "{$M"'],
			['{$M:""{$M:', 'incorrect syntax near "{$M:"'],
			['{$M:""{$M:"', 'incorrect syntax near "{$M:""'],
			['{$M:""{$M:""', 'incorrect syntax near "{$M:"""'],
			['{$MACRO:"abc\"}', 'unexpected end of macro'],
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
					'pos' => 0,
					'macro_name' => 'MACRO1',
					'context' => null
				],
				[
					'match' => '{$MACRO2}',
					'macro' => '{$MACRO2}',
					'pos' => 9,
					'macro_name' => 'MACRO2',
					'context' => null
				]]
			],
			[
				'abc"def"ghi{$MACRO}',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'pos' => 11,
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'abc"def"ghi{$MACRO:""}',
				[[
					'match' => '{$MACRO:""}',
					'macro' => '{$MACRO:""}',
					'pos' => 11,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'abc"def"ghi{$MACRO:\""}',
				[[
					'match' => '{$MACRO:\""}',
					'macro' => '{$MACRO:\""}',
					'pos' => 11,
					'macro_name' => 'MACRO',
					'context' => '\""'
				]]
			],
			[
				'abc"def\"ghi{$MACRO:""}',
				[[
					'match' => '{$MACRO:""}',
					'macro' => '{$MACRO:""}',
					'pos' => 12,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'abc"def{$MACRO:\""}',
				[[
					'match' => '{$MACRO:\""}',
					'macro' => '{$MACRO:\""}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '\""'
				]]
			],
			[
				'abc"def{$MACRO:\"\"}',
				[[
					'match' => '{$MACRO:\"\"}',
					'macro' => '{$MACRO:\"\"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '\"\"'
				]]
			],
			[
				'abc"def{$MACRO:\"abc\"}',
				[[
					'match' => '{$MACRO:\"abc\"}',
					'macro' => '{$MACRO:\"abc\"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '\"abc\"'
				]]
			],
			[
				'abc"def{$MACRO:"abc"}',
				[[
					'match' => '{$MACRO:"abc"}',
					'macro' => '{$MACRO:"abc"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => 'abc'
				]]
			],
			[
				'{$MACRO1:"\"xyz\""}',
				[[
					'match' => '{$MACRO1:"\"xyz\""}',
					'macro' => '{$MACRO1:"\"xyz\""}',
					'pos' => 0,
					'macro_name' => 'MACRO1',
					'context' => '"xyz"'
				]]
			],
			[
				'{$MACRO2:\"xyz\"}',
				[[
					'match' => '{$MACRO2:\"xyz\"}',
					'macro' => '{$MACRO2:\"xyz\"}',
					'pos' => 0,
					'macro_name' => 'MACRO2',
					'context' => '\"xyz\"'
				]]
			],
			[
				'{$MACRO3:"\\\\"xyz\\\\""}',
				[[
					'match' => '{$MACRO3:"\\\\"xyz\\\\""}',
					'macro' => '{$MACRO3:"\\\\"xyz\\\\""}',
					'pos' => 0,
					'macro_name' => 'MACRO3',
					'context' => '\\"xyz\\"'
				]]
			],
			[
				'{$MACRO3:\\"xyz\\"}',
				[[
					'match' => '{$MACRO3:\\"xyz\\"}',
					'macro' => '{$MACRO3:\\"xyz\\"}',
					'pos' => 0,
					'macro_name' => 'MACRO3',
					'context' => '\\"xyz\\"'
				]]
			],
			[
				'abc"def{$MACRO:\"abc\"def\"\"}',
				[[
					'match' => '{$MACRO:\"abc\"def\"\"}',
					'macro' => '{$MACRO:\"abc\"def\"\"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '\"abc\"def\"\"'
				]]
			],
			[
				'abc"def{$MACRO:\\"abc\\\\"defgh\\\\"\\"}',
				[[
					'match' => '{$MACRO:\\"abc\\\\"defgh\\\\"\\"}',
					'macro' => '{$MACRO:\\"abc\\\\"defgh\\\\"\\"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '\\"abc\\\\"defgh\\\\"\\"'
				]]
			],
			[
				'a  abc"def"ghi{$MACRO}{$MACRO}test',
				[[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'pos' => 14,
					'macro_name' => 'MACRO',
					'context' => null
				],
				[
					'match' => '{$MACRO}',
					'macro' => '{$MACRO}',
					'pos' => 22,
					'macro_name' => 'MACRO',
					'context' => null
				]]
			],
			[
				'a  abc"defghi{$MACRO1}{}{{{$:${$${$MACRO2:\"\"}   ',
				[[
					'match' => '{$MACRO1}',
					'macro' => '{$MACRO1}',
					'pos' => 13,
					'macro_name' => 'MACRO1',
					'context' => null
				],
				[
					'match' => '{$MACRO2:\"\"}',
					'macro' => '{$MACRO2:\"\"}',
					'pos' => 33,
					'macro_name' => 'MACRO2',
					'context' => '\"\"'
				]]
			],
			[
				'echo[{{$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 6,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{${$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{$ABC{$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 10,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{$ABC:"abc{$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 15,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{$ABC:"abc"{$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 16,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'echo[{$ABC:"abc\"{$MACRO:}]',
				[[
					'match' => '{$MACRO:}',
					'macro' => '{$MACRO:}',
					'pos' => 17,
					'macro_name' => 'MACRO',
					'context' => ''
				]]
			],
			[
				'{$MACRO:{"abc"}',
				[[
					'match' => '{$MACRO:{"abc"}',
					'macro' => '{$MACRO:{"abc"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => '{"abc"'
				]]
			],
			[
				'{$ABC:"{$MACRO:{"abc"}',
				[[
					'match' => '{$MACRO:{"abc"}',
					'macro' => '{$MACRO:{"abc"}',
					'pos' => 7,
					'macro_name' => 'MACRO',
					'context' => '{"abc"'
				]]
			],
			[
				'{$ABC:"abc\{$MACRO:{"abc"}"}',
				[[
					'match' => '{$MACRO:{"abc"}',
					'macro' => '{$MACRO:{"abc"}',
					'pos' => 11,
					'macro_name' => 'MACRO',
					'context' => '{"abc"'
				]]
			],
			[
				'{$ABC:"abc\"{$MACRO:{"abc"}}',
				[[
					'match' => '{$MACRO:{"abc"}',
					'macro' => '{$MACRO:{"abc"}',
					'pos' => 12,
					'macro_name' => 'MACRO',
					'context' => '{"abc"'
				]]
			],
			[
				'{$MACRO:"abc{$A:{"xyz"}\"{$B:{"qrt"}}',
				[[
					'match' => '{$A:{"xyz"}',
					'macro' => '{$A:{"xyz"}',
					'pos' => 12,
					'macro_name' => 'A',
					'context' => '{"xyz"'
				],
				[
					'match' => '{$B:{"qrt"}',
					'macro' => '{$B:{"qrt"}',
					'pos' => 25,
					'macro_name' => 'B',
					'context' => '{"qrt"'
				]]
			],
			[
				'{$MACRO1:"{$MACRO2}"}',
				[[
					'match' => '{$MACRO1:"{$MACRO2}"}',
					'macro' => '{$MACRO1:"{$MACRO2}"}',
					'pos' => 0,
					'macro_name' => 'MACRO1',
					'context' => '{$MACRO2}'
				]]
			],
			[
				'{$MACRO:"abc{$A:{"xyz"}{$A:{"xyz"}\"{$B:{"qrt"}}',
				[[
					'match' => '{$A:{"xyz"}',
					'macro' => '{$A:{"xyz"}',
					'pos' => 12,
					'macro_name' => 'A',
					'context' => '{"xyz"'
				],
				[
					'match' => '{$A:{"xyz"}',
					'macro' => '{$A:{"xyz"}',
					'pos' => 23,
					'macro_name' => 'A',
					'context' => '{"xyz"'
				],
				[
					'match' => '{$B:{"qrt"}',
					'macro' => '{$B:{"qrt"}',
					'pos' => 36,
					'macro_name' => 'B',
					'context' => '{"qrt"'
				]]
			],
			[
				'${${{{{{${${${${{{{${${{$M1{{{$M2{$M3{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}}}}}}}}}}}}}}}}}}}}}}}}}}}}}',
				[[
					'match' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
					'macro' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
					'pos' => 37,
					'macro_name' => 'M4',
					'context' => '{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""'
				]]
			],
			[
				'{$MACRO::"abc"}',
				[[
					'match' => '{$MACRO::"abc"}',
					'macro' => '{$MACRO::"abc"}',
					'pos' => 0,
					'macro_name' => 'MACRO',
					'context' => ':"abc"'
				]]
			],
			[
				'{$ABC:{$MY.MACRO:}',
				[[
					'match' => '{$ABC:{$MY.MACRO:}',
					'macro' => '{$ABC:{$MY.MACRO:}',
					'pos' => 0,
					'macro_name' => 'ABC',
					'context' => '{$MY.MACRO:'
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
