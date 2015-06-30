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
	 * @var CUserMacroParser
	 */
	protected $userMacroParser;

	public function setUp() {
		$this->userMacroParser = new CUserMacroParser();
	}

	/**
	 * Test against valid macros and compare the macro name, context and the result set
	 */
	public function testValidProvider() {
		return [
			// normal macros
			['{$MACRO}', 'MACRO', '', ['source' => '{$MACRO}', 'match' => '{$MACRO}', 'pos' => 0, 'length' => 8]],
			['{$MACRO_}', 'MACRO_', '', ['source' => '{$MACRO_}', 'match' => '{$MACRO_}', 'pos' => 0, 'length' => '9']],
			['{$MACRO_12}', 'MACRO_12', '', ['source' => '{$MACRO_12}', 'match' => '{$MACRO_12}', 'pos' => 0, 'length' => '11']],
			['{$MACRO_1.2}', 'MACRO_1.2', '', ['source' => '{$MACRO_1.2}', 'match' => '{$MACRO_1.2}', 'pos' => 0, 'length' => '12']],
			// context based macros
			['{$MACRO:}', 'MACRO', '', ['source' => '{$MACRO:}', 'match' => '{$MACRO:}', 'pos' => 0, 'length' => '10']],
			['{$MACRO: }', 'MACRO', '', ['source' => '{$MACRO: }', 'match' => '{$MACRO: }', 'pos' => 0, 'length' => '11']],
			['{$MACRO:   }', 'MACRO', '', ['source' => '{$MACRO:   }', 'match' => '{$MACRO:   }', 'pos' => 0, 'length' => '13']],
			['{$MACRO:\'\'}', 'MACRO', '\'\'', ['source' => '{$MACRO:\'\'}', 'match' => '{$MACRO:\'\'}', 'pos' => 0, 'length' => '12']],
			['{$MACRO:A }', 'MACRO', 'A ', ['source' => '{$MACRO:A }', 'match' => '{$MACRO:A }', 'pos' => 0, 'length' => '12']],
			['{$MACRO:A}', 'MACRO', 'A', ['source' => '{$MACRO:A}', 'match' => '{$MACRO:A}', 'pos' => 0, 'length' => '11']],
			['{$MACRO:A"}', 'MACRO', 'A"', ['source' => '{$MACRO:A"}', 'match' => '{$MACRO:A"}', 'pos' => 0, 'length' => '12']],
			['{$MACRO:context}', 'MACRO', 'context', ['source' => '{$MACRO:context}', 'match' => '{$MACRO:context}', 'pos' => 0, 'length' => '17']],
			['{$MACRO:<context>}', 'MACRO', '<context>', ['source' => '{$MACRO:<context>}', 'match' => '{$MACRO:<context>}', 'pos' => 0, 'length' => '19']],
			['{$MACRO:\"}', 'MACRO', '\"', ['source' => '{$MACRO:\"}', 'match' => '{$MACRO:\"}', 'pos' => 0, 'length' => '12']],
			['{$MACRO:{}', 'MACRO', '{', ['source' => '{$MACRO:{}', 'match' => '{$MACRO:{}', 'pos' => 0, 'length' => '11']],
			['{$MACRO:\}', 'MACRO', '\\', ['source' => '{$MACRO:\}', 'match' => '{$MACRO:\}', 'pos' => 0, 'length' => '11']],
			['{$MACRO:\\}', 'MACRO', '\\', ['source' => '{$MACRO:\\}', 'match' => '{$MACRO:\\}', 'pos' => 0, 'length' => '11']],
			['{$MACRO:\"\}', 'MACRO', '\"\\', ['source' => '{$MACRO:\"\}', 'match' => '{$MACRO:\"\}', 'pos' => 0, 'length' => '13']],
			['{$MACRO:abc"def}', 'MACRO', 'abc"def', ['source' => '{$MACRO:abc"def}', 'match' => '{$MACRO:abc"def}', 'pos' => 0, 'length' => '17']],
			['{$MACRO:abc"def"}', 'MACRO', 'abc"def"', ['source' => '{$MACRO:abc"def"}', 'match' => '{$MACRO:abc"def"}', 'pos' => 0, 'length' => '18']],
			['{$MACRO:abc"def"ghi}', 'MACRO', 'abc"def"ghi', ['source' => '{$MACRO:abc"def"ghi}', 'match' => '{$MACRO:abc"def"ghi}', 'pos' => 0, 'length' => '21']],
			['{$MACRO:abc"\}', 'MACRO', 'abc"\\', ['source' => '{$MACRO:abc"\}', 'match' => '{$MACRO:abc"\}', 'pos' => 0, 'length' => '15']],
			// context based quoted macros
			['{$MACRO:""}', 'MACRO', '', ['source' => '{$MACRO:""}', 'match' => '{$MACRO:""}', 'pos' => 0, 'length' => '12']],
			['{$MACRO: " " }', 'MACRO', ' ', ['source' => '{$MACRO: " " }', 'match' => '{$MACRO: " " }', 'pos' => 0, 'length' => '15']],
			['{$MACRO: ""}', 'MACRO', '', ['source' => '{$MACRO: ""}', 'match' => '{$MACRO: ""}', 'pos' => 0, 'length' => '13']],
			['{$MACRO:"" }', 'MACRO', '', ['source' => '{$MACRO:"" }', 'match' => '{$MACRO:"" }', 'pos' => 0, 'length' => '13']],
			['{$MACRO: "    " }', 'MACRO', '    ', ['source' => '{$MACRO: "    " }', 'match' => '{$MACRO: "    " }', 'pos' => 0, 'length' => '18']],
			['{$MACRO:    "    "      }', 'MACRO', '    ', ['source' => '{$MACRO:    "    "      }', 'match' => '{$MACRO:    "    "      }', 'pos' => 0, 'length' => '26']],
			['{$MACRO:    ""      }', 'MACRO', '', ['source' => '{$MACRO:    ""      }', 'match' => '{$MACRO:    ""      }', 'pos' => 0, 'length' => '22']],
			['{$MACRO:"A" }', 'MACRO', 'A', ['source' => '{$MACRO:"A" }', 'match' => '{$MACRO:"A" }', 'pos' => 0, 'length' => '14']],
			['{$MACRO:"{#MACRO}"}', 'MACRO', '{#MACRO}', ['source' => '{$MACRO:"{#MACRO}"}', 'match' => '{$MACRO:"{#MACRO}"}', 'pos' => 0, 'length' => '20']],
			['{$MACRO:"\abc"}', 'MACRO', '\abc', ['source' => '{$MACRO:"\abc"}', 'match' => '{$MACRO:"\abc"}', 'pos' => 0, 'length' => '16']],
			['{$MACRO:"abc\def"}', 'MACRO', 'abc\def', ['source' => '{$MACRO:"abc\def"}', 'match' => '{$MACRO:"abc\def"}', 'pos' => 0, 'length' => '19']],
			['{$MACRO:"\abc\    "}', 'MACRO', '\abc\    ', ['source' => '{$MACRO:"\abc\    "}', 'match' => '{$MACRO:"\abc\    "}', 'pos' => 0, 'length' => '21']],
			['{$MACRO:"\\""}', 'MACRO', '\"', ['source' => '{$MACRO:"\\""}', 'match' => '{$MACRO:"\\""}', 'pos' => 0, 'length' => '14']],
		];
	}

	/**
	 * Test against invalid macros and compare the error message.
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
			['{$MACRO}:', 'incorrect syntax near "}:"'],
			['{$MACRO:{#MACRO}}', 'incorrect syntax near "}"'],
			['{$MACRO:"}', 'unexpected end of macro'],
			['{$MACRO:""A""}', 'incorrect syntax near """}"'],
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
	 * @dataProvider testValidProvider
	 *
	 * @param $source		source string to parse
	 * @param $macro_name	expected macro name
	 * @param $context		expected context string
	 * @param $result		expected prase result from CParserResult
	 */
	public function testParseValid($source, $macro_name, $context, $result) {
		$this->userMacroParser->parse($source, 0, false);

		$this->assertTrue($this->userMacroParser->isValid());
		$this->assertEmpty($this->userMacroParser->getError());
		$this->assertEquals($macro_name, $this->userMacroParser->getMacroName());
		$this->assertEquals($context, $this->userMacroParser->getContext());
		$this->assertEquals($result, (array) $this->userMacroParser->getParseResult());
	}

	/**
	 * @dataProvider testInvalidProvider
	 *
	 * @param $source		source string to parse
	 * @param $error		expected error message
	 */
	public function testParseInvalid($source, $error) {
		$this->userMacroParser->parse($source, 0, false);

		$this->assertFalse($this->userMacroParser->isValid());
		$this->assertEquals($error, $this->userMacroParser->getError());
		$this->assertEmpty($this->userMacroParser->getMacroName());
		$this->assertEmpty($this->userMacroParser->getContext());
		$this->assertFalse($this->userMacroParser->getParseResult());
	}
}
