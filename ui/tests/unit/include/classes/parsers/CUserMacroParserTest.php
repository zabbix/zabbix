<?php declare(strict_types = 0);
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

class CUserMacroParserTest extends TestCase {

	/**
	 * An array of user macros and parsed results.
	 */
	public function dataProvider() {
		return [
			// Normal macros without context.
			['{$MACRO}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO_}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_}',
				'macro' => 'MACRO_',
				'context' => null,
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO_12}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_12}',
				'macro' => 'MACRO_12',
				'context' => null,
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO_1.2}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_1.2}',
				'macro' => 'MACRO_1.2',
				'context' => null,
				'regex' => null,
				'error' => ''
			]],
			// Context based unquoted macros.
			['{$MACRO:}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO: }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:   }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:   }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:\'\'}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\'\'}',
				'macro' => 'MACRO',
				'context' => '\'\'',
				'regex' => null,
				'error' => ''
			] ],
			['{$MACRO:A }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A }',
				'macro' => 'MACRO',
				'context' => 'A ',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:A}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A}',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:A"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A"}',
				'macro' => 'MACRO',
				'context' => 'A"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:context}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:context}',
				'macro' => 'MACRO',
				'context' => 'context',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:<context>}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:<context>}',
				'macro' => 'MACRO',
				'context' => '<context>',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO1:\"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO1:\"}',
				'macro' => 'MACRO1',
				'context' => '\"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:{}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:{}',
				'macro' => 'MACRO',
				'context' => '{',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\}',
				'macro' => 'MACRO',
				'context' => '\\',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:\\\\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\\\\}',
				'macro' => 'MACRO',
				'context' => '\\\\',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:\"\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\"\}',
				'macro' => 'MACRO',
				'context' => '\"\\',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:abc"def}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def}',
				'macro' => 'MACRO',
				'context' => 'abc"def',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:abc"def"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def"}',
				'macro' => 'MACRO',
				'context' => 'abc"def"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:abc"def"ghi}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def"ghi}',
				'macro' => 'MACRO',
				'context' => 'abc"def"ghi',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:abc"\\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"\\}',
				'macro' => 'MACRO',
				'context' => 'abc"\\',
				'regex' => null,
				'error' => ''
			]],
			// Context based quoted macros.
			['{$MACRO:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO: " " }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: " " }',
				'macro' => 'MACRO',
				'context' => ' ',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO: ""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: ""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"" }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO: "    " }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: "    " }',
				'macro' => 'MACRO',
				'context' => '    ',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:    "    "      }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:    "    "      }',
				'macro' => 'MACRO',
				'context' => '    ',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:    ""      }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:    ""      }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"A" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"A" }',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"{#MACRO}"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"{#MACRO}"}',
				'macro' => 'MACRO',
				'context' => '{#MACRO}',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"\abc"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"\abc"}',
				'macro' => 'MACRO',
				'context' => '\abc',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"abc\def"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"abc\def"}',
				'macro' => 'MACRO',
				'context' => 'abc\def',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:"\abc\    "}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"\abc\    "}',
				'macro' => 'MACRO',
				'context' => '\abc\    ',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO2:"\\\""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO2:"\\\""}',
				'macro' => 'MACRO2',
				'context' => '\"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO1}{$MACRO2}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO1}',
				'macro' => 'MACRO1',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$MACRO2}"'
			]],
			['{$MACRO1}{$MACRO2}', 9, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO2}',
				'macro' => 'MACRO2',
				'context' => null,
				'regex' => null,
				'error' => ''
			]],
			['abc"def"ghi{$MACRO:""}', 11, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => ''
			]],
			['abc"def"ghi{$MACRO:\""}}', 11, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:\""}',
				'macro' => 'MACRO',
				'context' => '\""',
				'regex' => null,
				'error' => 'incorrect syntax near "}"'
			]],
			['abc"def{$MACRO:\"\"}', 7, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\"\"}',
				'macro' => 'MACRO',
				'context' => '\"\"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO3:"\\\\"xyz\\\\""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO3:"\\\\"xyz\\\\""}',
				'macro' => 'MACRO3',
				'context' => '\\"xyz\\"',
				'regex' => null,
				'error' => ''
			]],
			['${${{{{{${${${${{{{${${{$M1{{{$M2{$M3{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}}}}}}}}}}}}}}}', 37, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
				'macro' => 'M4',
				'context' => '{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""',
				'regex' => null,
				'error' => 'incorrect syntax near "}}}}}}}}}}}}}}"'
			]],
			['{$MACRO::"abc"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO::"abc"}',
				'macro' => 'MACRO',
				'context' => ':"abc"',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO}:', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near ":"'
			]],
			['{$MACRO:{#MACRO}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:{#MACRO}',
				'macro' => 'MACRO',
				'context' => '{#MACRO',
				'regex' => null,
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:A}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:A}',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:""}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:regex:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:""}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'error' => ''
			]],
			['{$MACRO: regex:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: regex:""}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'error' => ''
			]],
			['{$MACRO: regex :""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: regex :""}',
				'macro' => 'MACRO',
				'context' => 'regex :""',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:regex}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex}',
				'macro' => 'MACRO',
				'context' => 'regex',
				'regex' => null,
				'error' => ''
			]],
			['{$MACRO:regex:}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'error' => ''
			]],
			['{$MACRO:regex:"/^test/"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"/^test/"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/^test/',
				'error' => ''
			]],
			['{$MACRO:regex:"/([a-z])/i"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"/([a-z])/i"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/([a-z])/i',
				'error' => ''
			]],
			['{$MACRO:regex:/test/}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:/test/}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/test/',
				'error' => ''
			]],
			['{$MACRO:regex: ^test}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: ^test}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'error' => ''
			]],
			['{$MACRO:regex: ^test }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: ^test }',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test ',
				'error' => ''
			]],
			['{$MACRO:regex: "^test" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: "^test" }',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'error' => ''
			]],
			['{$MACRO:regex: "^test"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: "^test"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'error' => ''
			]],
			['{$MACRO:regex:"^"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"^"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^',
				'error' => ''
			]],
			['', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'macro is empty'
			]],
			['{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{"'
			]],
			['{{{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{{"'
			]],
			['{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$"'
			]],
			['{${{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{{$"'
			]],
			['{$${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "${$"'
			]],
			['{${$$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$$"'
			]],
			['{${{$${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{{$${$"'
			]],
			['{$M', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$.', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near """'
			]],
			['{$-', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "-"'
			]],
			['{$M:', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:""', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:""{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{"'
			]],
			['{$M:""{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$"'
			]],
			['{$M:""{$M', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$M"'
			]],
			['{$M:""{$M:', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$M:"'
			]],
			['{$M:""{$M:"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$M:""'
			]],
			['{$M:""{$M:""', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "{$M:"""'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{MACRO', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "MACRO"'
			]],
			['{MACRO$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "MACRO$"'
			]],
			['{MACRO}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "MACRO}"'
			]],
			['{$macro}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "macro}"'
			]],
			['{#macro}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "#macro}"'
			]],
			['{$MACRO', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACR-O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "-O}"'
			]],
			['{$MACR,O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near ",O}"'
			]],
			['{$MACR"O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near ""O}"'
			]],
			['{$MACR\O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "\O}"'
			]],
			['{$MACR\'O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "\'O}"'
			]],
			["{\$MACR'O}", 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "\'O}"'
			]],
			['{$MACRo}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "o}"'
			]],
			['{$MACRO:"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:""A""}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "A""}"'
			]],
			['{$MACRO:"\}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACR€}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "€}"'
			]],
			['{$MACR�}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "�}"'
			]],
			['{$MACRƒabcdefghijklimnopqrstuv123123456456789789000aaabbbcccdddeeefffggghhhiiijjjkkklllmmmnnnooo111}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "ƒabcdefghijklimnopqrstuv123123456456789789000aaabb ..."'
			]],
			['{$MACRƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "ƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}"'
			]],
			['�', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "�"'
			]],
			['�', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'error' => 'incorrect syntax near "�"'
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $expected) {
		static $user_macro_parser = null;

		if ($user_macro_parser === null) {
			$user_macro_parser = new CUserMacroParser();
		}

		$this->assertSame($expected, [
			'rc' => $user_macro_parser->parse($source, $pos),
			'match' => $user_macro_parser->getMatch(),
			'macro' => $user_macro_parser->getMacro(),
			'context' => $user_macro_parser->getContext(),
			'regex' => $user_macro_parser->getRegex(),
			'error' => $user_macro_parser->getError()
		]);
		$this->assertSame(strlen($expected['match']), $user_macro_parser->getLength());
	}
}
