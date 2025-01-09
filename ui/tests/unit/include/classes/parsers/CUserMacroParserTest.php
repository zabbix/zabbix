<?php declare(strict_types = 0);
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
				'minified_macro' => '{$MACRO}',
				'error' => ''
			]],
			['{$MACRO_}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_}',
				'macro' => 'MACRO_',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO_}',
				'error' => ''
			]],
			['{$MACRO_12}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_12}',
				'macro' => 'MACRO_12',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO_12}',
				'error' => ''
			]],
			['{$MACRO_1.2}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO_1.2}',
				'macro' => 'MACRO_1.2',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO_1.2}',
				'error' => ''
			]],
			// Context based unquoted macros.
			['{$MACRO:}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO: }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO:   }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:   }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO:\'\'}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\'\'}',
				'macro' => 'MACRO',
				'context' => '\'\'',
				'regex' => null,
				'minified_macro' => '{$MACRO:\'\'}',
				'error' => ''
			] ],
			['{$MACRO:A }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A }',
				'macro' => 'MACRO',
				'context' => 'A ',
				'regex' => null,
				'minified_macro' => '{$MACRO:A }',
				'error' => ''
			]],
			['{$MACRO:A}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A}',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'minified_macro' => '{$MACRO:A}',
				'error' => ''
			]],
			['{$MACRO:A"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:A"}',
				'macro' => 'MACRO',
				'context' => 'A"',
				'regex' => null,
				'minified_macro' => '{$MACRO:A"}',
				'error' => ''
			]],
			['{$MACRO:context}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:context}',
				'macro' => 'MACRO',
				'context' => 'context',
				'regex' => null,
				'minified_macro' => '{$MACRO:context}',
				'error' => ''
			]],
			['{$MACRO:<context>}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:<context>}',
				'macro' => 'MACRO',
				'context' => '<context>',
				'regex' => null,
				'minified_macro' => '{$MACRO:<context>}',
				'error' => ''
			]],
			['{$MACRO1:\"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO1:\\"}',
				'macro' => 'MACRO1',
				'context' => '\\"',
				'regex' => null,
				'minified_macro' => '{$MACRO1:\\"}',
				'error' => ''
			]],
			['{$MACRO:{}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:{}',
				'macro' => 'MACRO',
				'context' => '{',
				'regex' => null,
				'minified_macro' => '{$MACRO:{}',
				'error' => ''
			]],
			['{$MACRO:\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\\}',
				'macro' => 'MACRO',
				'context' => '\\',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\}',
				'error' => ''
			]],
			['{$MACRO:\\\\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\\\\}',
				'macro' => 'MACRO',
				'context' => '\\\\',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\\\}',
				'error' => ''
			]],
			['{$MACRO:\"\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\\"\\}',
				'macro' => 'MACRO',
				'context' => '\\"\\',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\"\\}',
				'error' => ''
			]],
			['{$MACRO:abc"def}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def}',
				'macro' => 'MACRO',
				'context' => 'abc"def',
				'regex' => null,
				'minified_macro' => '{$MACRO:abc"def}',
				'error' => ''
			]],
			['{$MACRO:abc"def"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def"}',
				'macro' => 'MACRO',
				'context' => 'abc"def"',
				'regex' => null,
				'minified_macro' => '{$MACRO:abc"def"}',
				'error' => ''
			]],
			['{$MACRO:abc"def"ghi}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"def"ghi}',
				'macro' => 'MACRO',
				'context' => 'abc"def"ghi',
				'regex' => null,
				'minified_macro' => '{$MACRO:abc"def"ghi}',
				'error' => ''
			]],
			['{$MACRO:abc"\\}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:abc"\\}',
				'macro' => 'MACRO',
				'context' => 'abc"\\',
				'regex' => null,
				'minified_macro' => '{$MACRO:abc"\\}',
				'error' => ''
			]],
			// Context based quoted macros.
			['{$MACRO:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO: " " }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: " " }',
				'macro' => 'MACRO',
				'context' => ' ',
				'regex' => null,
				'minified_macro' => '{$MACRO:" "}',
				'error' => ''
			]],
			['{$MACRO: ""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: ""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO:"" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"" }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO: "    " }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: "    " }',
				'macro' => 'MACRO',
				'context' => '    ',
				'regex' => null,
				'minified_macro' => '{$MACRO:"    "}',
				'error' => ''
			]],
			['{$MACRO:    "    "      }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:    "    "      }',
				'macro' => 'MACRO',
				'context' => '    ',
				'regex' => null,
				'minified_macro' => '{$MACRO:"    "}',
				'error' => ''
			]],
			['{$MACRO:    ""      }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:    ""      }',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['{$MACRO:"A" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"A" }',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'minified_macro' => '{$MACRO:A}',
				'error' => ''
			]],
			['{$MACRO:"{#MACRO}"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"{#MACRO}"}',
				'macro' => 'MACRO',
				'context' => '{#MACRO}',
				'regex' => null,
				'minified_macro' => '{$MACRO:"{#MACRO}"}',
				'error' => ''
			]],
			['{$MACRO:"\abc"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"\\abc"}',
				'macro' => 'MACRO',
				'context' => '\\abc',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\abc}',
				'error' => ''
			]],
			['{$MACRO:"abc\def"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"abc\\def"}',
				'macro' => 'MACRO',
				'context' => 'abc\\def',
				'regex' => null,
				'minified_macro' => '{$MACRO:abc\\def}',
				'error' => ''
			]],
			['{$MACRO:"\abc\    "}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:"\\abc\\    "}',
				'macro' => 'MACRO',
				'context' => '\\abc\\    ',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\abc\\    }',
				'error' => ''
			]],
			['{$MACRO2:"\\\""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO2:"\\\\""}',
				'macro' => 'MACRO2',
				'context' => '\\"',
				'regex' => null,
				'minified_macro' => '{$MACRO2:\\"}',
				'error' => ''
			]],
			['{$MACRO1}{$MACRO2}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO1}',
				'macro' => 'MACRO1',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO1}',
				'error' => 'incorrect syntax near "{$MACRO2}"'
			]],
			['{$MACRO1}{$MACRO2}', 9, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO2}',
				'macro' => 'MACRO2',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO2}',
				'error' => ''
			]],
			['abc"def"ghi{$MACRO:""}', 11, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => ''
			]],
			['abc"def"ghi{$MACRO:\""}}', 11, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:\\""}',
				'macro' => 'MACRO',
				'context' => '\\""',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\""}',
				'error' => 'incorrect syntax near "}"'
			]],
			['abc"def{$MACRO:\"\"}', 7, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:\\"\\"}',
				'macro' => 'MACRO',
				'context' => '\\"\\"',
				'regex' => null,
				'minified_macro' => '{$MACRO:\\"\\"}',
				'error' => ''
			]],
			['{$MACRO3:"\\\\"xyz\\\\""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO3:"\\\\"xyz\\\\""}',
				'macro' => 'MACRO3',
				'context' => '\\"xyz\\"',
				'regex' => null,
				'minified_macro' => '{$MACRO3:\\"xyz\\"}',
				'error' => ''
			]],
			['${${{{{{${${${${{{{${${{$M1{{{$M2{$M3{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}}}}}}}}}}}}}}}', 37, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
				'macro' => 'M4',
				'context' => '{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""',
				'regex' => null,
				'minified_macro' => '{$M4:{M5:{$M6:{$M7:"{$M8:""{$M9:""a{$M10:""}',
				'error' => 'incorrect syntax near "}}}}}}}}}}}}}}"'
			]],
			['{$MACRO::"abc"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO::"abc"}',
				'macro' => 'MACRO',
				'context' => ':"abc"',
				'regex' => null,
				'minified_macro' => '{$MACRO::"abc"}',
				'error' => ''
			]],
			['{$MACRO}:', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => null,
				'minified_macro' => '{$MACRO}',
				'error' => 'incorrect syntax near ":"'
			]],
			['{$MACRO:{#MACRO}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:{#MACRO}',
				'macro' => 'MACRO',
				'context' => '{#MACRO',
				'regex' => null,
				'minified_macro' => '{$MACRO:{#MACRO}',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:A}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:A}',
				'macro' => 'MACRO',
				'context' => 'A',
				'regex' => null,
				'minified_macro' => '{$MACRO:A}',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:""}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:""}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:}}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:}',
				'macro' => 'MACRO',
				'context' => '',
				'regex' => null,
				'minified_macro' => '{$MACRO:}',
				'error' => 'incorrect syntax near "}"'
			]],
			['{$MACRO:regex:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:""}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'minified_macro' => '{$MACRO:regex:}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO: regex:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: regex:""}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'minified_macro' => '{$MACRO:regex:}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO: regex :""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO: regex :""}',
				'macro' => 'MACRO',
				'context' => 'regex :""',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex :""}',
				'error' => ''
			]],
			['{$MACRO:regex}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex}',
				'macro' => 'MACRO',
				'context' => 'regex',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex}',
				'error' => ''
			]],
			['{$MACRO:regex:}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '',
				'minified_macro' => '{$MACRO:regex:}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:"/^test/"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"/^test/"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/^test/',
				'minified_macro' => '{$MACRO:regex:/^test/}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:"/([a-z])/i"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"/([a-z])/i"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/([a-z])/i',
				'minified_macro' => '{$MACRO:regex:/([a-z])/i}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:/test/}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:/test/}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '/test/',
				'minified_macro' => '{$MACRO:regex:/test/}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex: ^test}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: ^test}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'minified_macro' => '{$MACRO:regex:^test}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex: ^test }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: ^test }',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test ',
				'minified_macro' => '{$MACRO:regex:^test }',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex: "^test" }', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: "^test" }',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'minified_macro' => '{$MACRO:regex:^test}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex: "^test"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex: "^test"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^test',
				'minified_macro' => '{$MACRO:regex:^test}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:"^"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"^"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '^',
				'minified_macro' => '{$MACRO:regex:^}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:"}"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"}"}',
				'macro' => 'MACRO',
				'context' => null,
				'regex' => '}',
				'minified_macro' => '{$MACRO:regex:"}"}',
				'error' => ''
			], ['allow_regex' => true]],
			['{$MACRO:regex:}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:}',
				'macro' => 'MACRO',
				'context' => 'regex:',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex:}',
				'error' => ''
			]],
			['{$MACRO:regex:""}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:""}',
				'macro' => 'MACRO',
				'context' => 'regex:""',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex:""}',
				'error' => ''
			]],
			['{$MACRO:regex:"/^test/"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$MACRO:regex:"/^test/"}',
				'macro' => 'MACRO',
				'context' => 'regex:"/^test/"',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex:"/^test/"}',
				'error' => ''
			]],
			['{$MACRO:regex:"}"}', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$MACRO:regex:"}',
				'macro' => 'MACRO',
				'context' => 'regex:"',
				'regex' => null,
				'minified_macro' => '{$MACRO:regex:"}',
				'error' => 'incorrect syntax near ""}"'
			]],
			['', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'macro is empty'
			]],
			['{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{"'
			]],
			['{{{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{{"'
			]],
			['{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$"'
			]],
			['{${{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{{$"'
			]],
			['{$${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "${$"'
			]],
			['{${$$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$$"'
			]],
			['{${{$${$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{{$${$"'
			]],
			['{$M', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$.', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near """'
			]],
			['{$-', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "-"'
			]],
			['{$M:', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$M:"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$M:""', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$M:""{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{"'
			]],
			['{$M:""{$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$"'
			]],
			['{$M:""{$M', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$M"'
			]],
			['{$M:""{$M:', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$M:"'
			]],
			['{$M:""{$M:"', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$M:""'
			]],
			['{$M:""{$M:""', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "{$M:"""'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{MACRO', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "MACRO"'
			]],
			['{MACRO$', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "MACRO$"'
			]],
			['{MACRO}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "MACRO}"'
			]],
			['{$macro}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "macro}"'
			]],
			['{#macro}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "#macro}"'
			]],
			['{$MACRO', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$MACR-O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "-O}"'
			]],
			['{$MACR,O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near ",O}"'
			]],
			['{$MACR"O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near ""O}"'
			]],
			['{$MACR\O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "\O}"'
			]],
			['{$MACR\'O}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "\'O}"'
			]],
			["{\$MACR'O}", 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "\'O}"'
			]],
			['{$MACRo}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "o}"'
			]],
			['{$MACRO:"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:""A""}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "A""}"'
			]],
			['{$MACRO:"\}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$MACRO:"abc\"}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'unexpected end of macro'
			]],
			['{$MACR€}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "€}"'
			]],
			['{$MACR�}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "�}"'
			]],
			['{$MACRƒabcdefghijklimnopqrstuv123123456456789789000aaabbbcccdddeeefffggghhhiiijjjkkklllmmmnnnooo111}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "ƒabcdefghijklimnopqrstuv123123456456789789000aaabb ..."'
			]],
			['{$MACRƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "ƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒƒ}"'
			]],
			['�', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
				'error' => 'incorrect syntax near "�"'
			]],
			['�', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'context' => null,
				'regex' => null,
				'minified_macro' => '',
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
	 * @param array  $options
	 */
	public function testParse($source, $pos, $expected, array $options = []) {
		$user_macro_parser = new CUserMacroParser($options);

		$this->assertSame($expected, [
			'rc' => $user_macro_parser->parse($source, $pos),
			'match' => $user_macro_parser->getMatch(),
			'macro' => $user_macro_parser->getMacro(),
			'context' => $user_macro_parser->getContext(),
			'regex' => $user_macro_parser->getRegex(),
			'minified_macro' => $user_macro_parser->getMinifiedMacro(),
			'error' => $user_macro_parser->getError()
		]);
		$this->assertSame(strlen($expected['match']), $user_macro_parser->getLength());
	}
}
