<?php declare(strict_types=1);
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

class CHostGroupNameParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'   ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   ',
					'macros' => []
				]
			],
			[
				'   a   ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   a   ',
					'macros' => []
				]
			],
			[
				' abc{#ABC}def~!@#$%^&*()[]-_+={};:\'"?<>', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' abc{#ABC}def~!@#$%^&*()[]-_+={};:\'"?<>',
					'macros' => []
				]
			],
			[
				'/abc{#ABC}//def~!@#$%^&*()[]-_+={};:\'"?<>|\\', 12, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'def~!@#$%^&*()[]-_+={};:\'"?<>|\\',
					'macros' => []
				]
			],
			[
				' a{#B}c ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' a{#B}c ',
					'macros' => ['{#B}']
				]
			],
			[
				' a{#B}c / {#D}e/f ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' a{#B}c / {#D}e/f ',
					'macros' => ['{#B}', '{#D}']
				]
			],
			[
				' a{#B}{{#C}.regsub("^([0-9]+)", "{#C}: \1")}{#D}/e ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' a{#B}{{#C}.regsub("^([0-9]+)", "{#C}: \1")}{#D}/e ',
					'macros' => ['{#B}', '{{#C}.regsub("^([0-9]+)", "{#C}: \1")}', '{#D}']
				]
			],
			[
				' a{#B}{{#C}.regsub("^([0-9]+\/[A-Za-z])", "{#C}: \1")}{#D}/e ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' a{#B}{{#C}.regsub("^([0-9]+\/[A-Za-z])", "{#C}: \1")}{#D}/e ',
					'macros' => ['{#B}', '{{#C}.regsub("^([0-9]+\/[A-Za-z])", "{#C}: \1")}', '{#D}']
				]
			],
			[
				'{#A}/{#B}/{#C}/{{#D}.regsub()}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#A}/{#B}/{#C}/{{#D}.regsub()}',
					'macros' => ['{#A}', '{#B}', '{#C}', '{{#D}.regsub()}']
				]
			],
			// partial success
			[
				'   /', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '   ',
					'macros' => []
				]
			],
			[
				' abc// edf', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => ' abc',
					'macros' => []
				]
			],
			[
				'abc    /', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc    ',
					'macros' => []
				]
			],
			[
				'abc/def/ghi/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc/def/ghi',
					'macros' => []
				]
			],
			[
				'abc/def/ghi//   ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc/def/ghi',
					'macros' => []
				]
			],
			[
				'abc / def // ghi // ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc / def ',
					'macros' => []
				]
			],
			[
				'abc/def/{#GHI}/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc/def/{#GHI}',
					'macros' => []
				]
			],
			[
				'abc/def/{#GHI}/{#JKL}/', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abc/def/{#GHI}/{#JKL}',
					'macros' => ['{#GHI}', '{#JKL}']
				]
			],
			[
				' a / {#B}{{#C}.regsub("^([0-9]+)", "{#C}: \1")} ~!@#$%^&*()[]{};:\'"|\\ \/', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => ' a / {#B}{{#C}.regsub("^([0-9]+)", "{#C}: \1")} ~!@#$%^&*()[]{};:\'"|\\ \\',
					'macros' => ['{#B}', '{{#C}.regsub("^([0-9]+)", "{#C}: \1")}']
				]
			],
			[
				'{#A}/{#B}/{#C}//{{#D}.regsub()}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#A}/{#B}/{#C}',
					'macros' => ['{#A}', '{#B}', '{#C}']
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'macros' => []
				]
			],
			[
				'/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'macros' => []
				]
			],
			[
				'//', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'macros' => []
				]
			],
			[
				'/abc', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'macros' => []
				]
			],
			[
				'/  abc{#ABC}/', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'macros' => []
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CHostGroupNameParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'macros' => $parser->getMacros()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
