<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CStatusCodeRangeParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function testProvider() {
		return [
			// success
			[
				'0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0',
					'status_codes' => ['0']
				]
			],
			[
				'100', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100',
					'status_codes' => ['100']
				]
			],
			[
				'123456789', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123456789',
					'status_codes' => ['123456789']
				]
			],
			[
				'200-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '200-300',
					'status_codes' => ['200', '300']
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}',
					'status_codes' => ['{$M}']
				]
			],
			[
				'{$M}-100', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}-100',
					'status_codes' => ['{$M}', '100']
				]
			],
			[
				'100-{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{$M}',
					'status_codes' => ['100', '{$M}']
				]
			],
			[
				'{$M.A}-{$M.B}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M.A}-{$M.B}',
					'status_codes' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}',
					'status_codes' => ['{#M}']
				]
			],
			[
				'{#M}-100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}-100',
					'status_codes' => ['{#M}', '100']
				]
			],
			[
				'100-{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{#M}',
					'status_codes' => ['100', '{#M}']
				]
			],
			[
				'{#M.A}-{#M.B}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M.A}-{#M.B}',
					'status_codes' => ['{#M.A}', '{#M.B}']
				]
			],
			[
				'{$M}-{#M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}-{#M}',
					'status_codes' => ['{$M}', '{#M}']
				]
			],
			[
				'{#M}-{$M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}-{$M}',
					'status_codes' => ['{#M}', '{$M}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '100']
				]
			],
			[
				'100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['100', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}', 0,
				['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}',
					'status_codes' => [
						'{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}',
						'{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}'
					]
				]
			],
			[
				'{$M}-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['{$M}', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-{$M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-{$M}',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '{$M}']
				]
			],
			// partial success
			[
				'random text.....0....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'status_codes' => ['0']
				]
			],
			[
				'200 ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '200',
					'status_codes' => ['200']
				]
			],
			[
				'300a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300',
					'status_codes' => ['300']
				]
			],
			[
				'400-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '400',
					'status_codes' => ['400']
				]
			],
			[
				'500--', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '500',
					'status_codes' => ['500']
				]
			],
			[
				'600--700', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '600',
					'status_codes' => ['600']
				]
			],
			[
				'700- ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '700',
					'status_codes' => ['700']
				]
			],
			[
				'800 -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '800',
					'status_codes' => ['800']
				]
			],
			[
				'100-200-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100-200',
					'status_codes' => ['100', '200']
				]
			],
			[
				'100-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'status_codes' => ['100']
				]
			],
			[
				'123-456-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123-456',
					'status_codes' => ['123', '456']
				]
			],
			[
				'123-abc-456', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123',
					'status_codes' => ['123']
				]
			],
			[
				'0,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'status_codes' => ['0']
				]
			],
			[
				'100,200,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'status_codes' => ['100']
				]
			],
			[
				'{$M}-', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'status_codes' => ['{$M}']
				]
			],
			[
				'{$M}- ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'status_codes' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'status_codes' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'status_codes' => ['{$M}']
				]
			],
			[
				'100-{$M}', 0, ['usermacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'status_codes' => ['100']
				]
			],
			[
				'{$M.A}-{$M.B}-{$M.C}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M.A}-{$M.B}',
					'status_codes' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'status_codes' => ['{#M}']
				]
			],
			[
				'{#M}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'status_codes' => ['{#M}']
				]
			],
			[
				'{#M} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'status_codes' => ['{#M}']
				]
			],
			[
				'{$M}-{#M.A}-{#M.C}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}-{#M.A}',
					'status_codes' => ['{$M}', '{#M.A}']
				]
			],
			[
				'100-{#M}', 0, ['lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'status_codes' => ['100']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'status_codes' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{#M.C}', 0,
				['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}',
					'status_codes' => ['{$M}', '{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}']
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'a100', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'-300-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				' 400', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				',500', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				',', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{$}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{#}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{$}-{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{#}-{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			// User macros are not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{$M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			// LLD macros are not enabled.
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{#M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-300', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'status_codes' => []
				]
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CStatusCodeRangeParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'status_codes' => $parser->getStatusCodes(),
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
