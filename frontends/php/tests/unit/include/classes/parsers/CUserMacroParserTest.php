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
			['{$MACRO}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO}',
				'macro_name' => 'MACRO',
				'context' => null,
				'error' => ''
			]],
			['{$MACRO_}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO_}',
				'macro_name' => 'MACRO_',
				'context' => null,
				'error' => ''
			]],
			['{$MACRO_12}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO_12}',
				'macro_name' => 'MACRO_12',
				'context' => null,
				'error' => ''
			]],
			['{$MACRO_1.2}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO_1.2}',
				'macro_name' => 'MACRO_1.2',
				'context' => null,
				'error' => ''
			]],
			// Context based unquoted macros.
			['{$MACRO:}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO: }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO: }',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO:   }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:   }',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO:\'\'}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:\'\'}',
				'macro_name' => 'MACRO',
				'context' => '\'\'',
				'error' => ''
			] ],
			['{$MACRO:A }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:A }',
				'macro_name' => 'MACRO',
				'context' => 'A ',
				'error' => ''
			]],
			['{$MACRO:A}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:A}',
				'macro_name' => 'MACRO',
				'context' => 'A',
				'error' => ''
			]],
			['{$MACRO:A"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:A"}',
				'macro_name' => 'MACRO',
				'context' => 'A"',
				'error' => ''
			]],
			['{$MACRO:context}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:context}',
				'macro_name' => 'MACRO',
				'context' => 'context',
				'error' => ''
			]],
			['{$MACRO:<context>}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:<context>}',
				'macro_name' => 'MACRO',
				'context' => '<context>',
				'error' => ''
			]],
			['{$MACRO1:\"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO1:\"}',
				'macro_name' => 'MACRO1',
				'context' => '\"',
				'error' => ''
			]],
			['{$MACRO:{}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:{}',
				'macro_name' => 'MACRO',
				'context' => '{',
				'error' => ''
			]],
			['{$MACRO:\}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:\}',
				'macro_name' => 'MACRO',
				'context' => '\\',
				'error' => ''
			]],
			['{$MACRO:\\\\}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:\\\\}',
				'macro_name' => 'MACRO',
				'context' => '\\\\',
				'error' => ''
			]],
			['{$MACRO:\"\}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:\"\}',
				'macro_name' => 'MACRO',
				'context' => '\"\\',
				'error' => ''
			]],
			['{$MACRO:abc"def}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:abc"def}',
				'macro_name' => 'MACRO',
				'context' => 'abc"def',
				'error' => ''
			]],
			['{$MACRO:abc"def"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:abc"def"}',
				'macro_name' => 'MACRO',
				'context' => 'abc"def"',
				'error' => ''
			]],
			['{$MACRO:abc"def"ghi}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:abc"def"ghi}',
				'macro_name' => 'MACRO',
				'context' => 'abc"def"ghi',
				'error' => ''
			]],
			['{$MACRO:abc"\\}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:abc"\\}',
				'macro_name' => 'MACRO',
				'context' => 'abc"\\',
				'error' => ''
			]],
			// Context based quoted macros.
			['{$MACRO:""}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:""}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO: " " }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO: " " }',
				'macro_name' => 'MACRO',
				'context' => ' ',
				'error' => ''
			]],
			['{$MACRO: ""}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO: ""}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO:"" }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"" }',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO: "    " }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO: "    " }',
				'macro_name' => 'MACRO',
				'context' => '    ',
				'error' => ''
			]],
			['{$MACRO:    "    "      }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:    "    "      }',
				'macro_name' => 'MACRO',
				'context' => '    ',
				'error' => ''
			]],
			['{$MACRO:    ""      }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:    ""      }',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['{$MACRO:"A" }', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"A" }',
				'macro_name' => 'MACRO',
				'context' => 'A',
				'error' => ''
			]],
			['{$MACRO:"{#MACRO}"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"{#MACRO}"}',
				'macro_name' => 'MACRO',
				'context' => '{#MACRO}',
				'error' => ''
			]],
			['{$MACRO:"\abc"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"\abc"}',
				'macro_name' => 'MACRO',
				'context' => '\abc',
				'error' => ''
			]],
			['{$MACRO:"abc\def"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"abc\def"}',
				'macro_name' => 'MACRO',
				'context' => 'abc\def',
				'error' => ''
			]],
			['{$MACRO:"\abc\    "}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:"\abc\    "}',
				'macro_name' => 'MACRO',
				'context' => '\abc\    ',
				'error' => ''
			]],
			['{$MACRO2:"\\\""}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO2:"\\\""}',
				'macro_name' => 'MACRO2',
				'context' => '\"',
				'error' => ''
			]],
			['{$MACRO1}{$MACRO2}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO1}',
				'macro_name' => 'MACRO1',
				'context' => null,
				'error' => 'incorrect syntax near "{$MACRO2}"'
			],],
			['{$MACRO1}{$MACRO2}', 9, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO2}',
				'macro_name' => 'MACRO2',
				'context' => null,
				'error' => ''
			],],
			['abc"def"ghi{$MACRO:""}', 11, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:""}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => ''
			]],
			['abc"def"ghi{$MACRO:\""}}', 11, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO:\""}',
				'macro_name' => 'MACRO',
				'context' => '\""',
				'error' => 'incorrect syntax near "}"'
			]],
			['abc"def{$MACRO:\"\"}', 7, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO:\"\"}',
				'macro_name' => 'MACRO',
				'context' => '\"\"',
				'error' => ''
			]],
			['{$MACRO3:"\\\\"xyz\\\\""}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO3:"\\\\"xyz\\\\""}',
				'macro_name' => 'MACRO3',
				'context' => '\\"xyz\\"',
				'error' => ''
			]],
			['${${{{{{${${${${{{{${${{$M1{{{$M2{$M3{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}}}}}}}}}}}}}}}', 37, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
				'macro_name' => 'M4',
				'context' => '{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""',
				'error' => 'incorrect syntax near "}}}}}}}}}}}}}}"'
			]],
			['{$MACRO::"abc"}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS,
				'macro' => '{$MACRO::"abc"}',
				'macro_name' => 'MACRO',
				'context' => ':"abc"',
				'error' => ''
			]],
			['{$MACRO}:', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO}',
				'macro_name' => 'MACRO',
				'context' => null,
				'error' => 'incorrect syntax near ":"'
			]],
			['{$MACRO:{#MACRO}}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO:{#MACRO}',
				'macro_name' => 'MACRO',
				'context' => '{#MACRO',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:A}}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO:A}',
				'macro_name' => 'MACRO',
				'context' => 'A',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:""}}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO:""}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:}}', 0, [
				'rc' => CUserMacroParser::PARSE_SUCCESS_PART,
				'macro' => '{$MACRO:}',
				'macro_name' => 'MACRO',
				'context' => '',
				'error' => 'incorrect syntax near "}"'
			]],
			['', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'macro is empty'
			]],
			['{', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{{', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{"'
			]],
			['{{{', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{{"'
			]],
			['{$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{${$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$"'
			]],
			['{${{$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{{$"'
			]],
			['{$${$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "${$"'
			]],
			['{${$$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$$"'
			]],
			['{${{$${$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{{$${$"'
			]],
			['{$M', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$.', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$"', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near """'
			]],
			['{$-', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "-"'
			]],
			['{$M:', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:"', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:""', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$M:""{', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{"'
			]],
			['{$M:""{$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$"'
			]],
			['{$M:""{$M', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$M"'
			]],
			['{$M:""{$M:', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$M:"'
			]],
			['{$M:""{$M:"', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$M:""'
			]],
			['{$M:""{$M:""', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "{$M:"""'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{MACRO', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "MACRO"'
			]],
			['{MACRO$', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "MACRO$"'
			]],
			['{MACRO}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "MACRO}"'
			]],
			['{$macro}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "macro}"'
			]],
			['{#macro}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "#macro}"'
			]],
			['{$MACRO', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACR-O}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "-O}"'
			]],
			['{$MACR,O}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near ",O}"'
			]],
			['{$MACR"O}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near ""O}"'
			]],
			['{$MACR\O}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "\O}"'
			]],
			['{$MACR\'O}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "\'O}"'
			]],
			["{\$MACR'O}", 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "\'O}"'
			]],
			['{$MACRo}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "o}"'
			]],
			['{$MACRO:"}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:""A""}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "A""}"'
			]],
			['{$MACRO:"\}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"\"}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'unexpected end of macro'
			]],
			['{$MACR€}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "€}"'
			]],
			['{$MACR�}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "�}"'
			]],
			['{$MACRƒabcdefghijklimnopqrstuv123123456456789789000aaabbbcccdddeeefffggghhhiiijjjkkklllmmmnnnooo111}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "ƒabcdefghijklimnopqrstuv123123456456789789000aaabb ..."'
			]],
			['{$MACRƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "ƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}"'
			]],
			['�', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "�"'
			]],
			['�', 0, [
				'rc' => CUserMacroParser::PARSE_FAIL,
				'macro' => '',
				'macro_name' => '',
				'context' => null,
				'error' => 'incorrect syntax near "�"'
			]]
		];
	}

	/**
	 * @dataProvider testValidProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParseValid($source, $pos, $expected) {
		static $user_macro_parser = null;

		if ($user_macro_parser === null) {
			$user_macro_parser = new CUserMacroParser();
		}
		$rc = $user_macro_parser->parse($source, $pos);

		$result = [
			'rc' => $rc,
			'macro' => $user_macro_parser->getMacro(),
			'macro_name' => $user_macro_parser->getMacroName(),
			'context' => $user_macro_parser->getContext(),
			'error' => $user_macro_parser->getError()
		];
		$this->assertEquals($expected, $result);
	}
}
