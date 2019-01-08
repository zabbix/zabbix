<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CRangeParserTest extends PHPUnit_Framework_TestCase {

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
					'range' => ['0']
				]
			],
			[
				'100', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'123456789', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123456789',
					'range' => ['123456789']
				]
			],
			[
				'200-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '200-300',
					'range' => ['200', '300']
				]
			],
			[
				'    123   ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    123   ',
					'range' => ['123']
				]
			],
			[
				'    234', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    234',
					'range' => ['234']
				]
			],
			[
				'345  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '345  ',
					'range' => ['345']
				]
			],
			[
				'456-457  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '456-457  ',
					'range' => ['456', '457']
				]
			],
			[
				'  567-568  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  567-568  ',
					'range' => ['567', '568']
				]
			],
			[
				'    678-679', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    678-679',
					'range' => ['678', '679']
				]
			],
			[
				'    789  -  800  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    789  -  800  ',
					'range' => ['789', '800']
				]
			],
			[
				'    800-   850  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    800-   850  ',
					'range' => ['800', '850']
				]
			],
			[
				'    850   -900  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    850   -900  ',
					'range' => ['850', '900']
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}',
					'range' => ['{$M}']
				]
			],
			[
				'{$M}  -100  ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}  -100  ',
					'range' => ['{$M}', '100']
				]
			],
			[
				'  100-{$M}  ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  100-{$M}  ',
					'range' => ['100', '{$M}']
				]
			],
			[
				'   100   -   {$M}   ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   100   -   {$M}   ',
					'range' => ['100', '{$M}']
				]
			],
			[
				"\r\n\t 100\r\n\t - \r\n\t 200 \r\n\t ", 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => "\r\n\t 100\r\n\t - \r\n\t 200 \r\n\t ",
					'range' => ['100', '200']
				]
			],
			[
				'{$M.A}-  {$M.B}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M.A}-  {$M.B}',
					'range' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'   {$M.A}   -   {$M.B}   ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {$M.A}   -   {$M.B}   ',
					'range' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}',
					'range' => ['{#M}']
				]
			],
			[
				'   {#M}   ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M}   ',
					'range' => ['{#M}']
				]
			],
			[
				'  {#M}-100  ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {#M}-100  ',
					'range' => ['{#M}', '100']
				]
			],
			[
				'100-   {#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-   {#M}',
					'range' => ['100', '{#M}']
				]
			],
			[
				'{#M.A}   -   {#M.B}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M.A}   -   {#M.B}',
					'range' => ['{#M.A}', '{#M.B}']
				]
			],
			[
				'   {#M.A}   -   {#M.B}   ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M.A}   -   {#M.B}   ',
					'range' => ['{#M.A}', '{#M.B}']
				]
			],
			[
				'  {$M}  -{#M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {$M}  -{#M}',
					'range' => ['{$M}', '{#M}']
				]
			],
			[
				'{#M}-  {$M}  ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}-  {$M}  ',
					'range' => ['{#M}', '{$M}']
				]
			],
			[
				'   {#M}   -   {$M}   ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M}   -   {$M}   ',
					'range' => ['{#M}', '{$M}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '100']
				]
			],
			[
				'100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'range' => ['100', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				' {{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}', 0,
				['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' {{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}',
					'range' => [
						'{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}',
						'{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}'
					]
				]
			],
			[
				'{$M}-   {{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}-   {{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'range' => ['{$M}', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'  {{#M}.regsub("^([0-9]+)", "{#M}: \1")}  -  {$M}  ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {{#M}.regsub("^([0-9]+)", "{#M}: \1")}  -  {$M}  ',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '{$M}']
				]
			],
			// partial success
			[
				'random text.....0....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'range' => ['0']
				]
			],
			[
				'200   -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '200   ',
					'range' => ['200']
				]
			],
			[
				'300a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300',
					'range' => ['300']
				]
			],
			[
				'400-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '400',
					'range' => ['400']
				]
			],
			[
				'500--', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '500',
					'range' => ['500']
				]
			],
			[
				'600--700', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '600',
					'range' => ['600']
				]
			],
			[
				'700- 800  -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '700- 800  ',
					'range' => ['700', '800']
				]
			],
			[
				'700-600', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '700',
					'range' => ['700']
				]
			],
			[
				'800 -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '800 ',
					'range' => ['800']
				]
			],
			[
				'  100  -  200  -  300  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  100  -  200  ',
					'range' => ['100', '200']
				]
			],
			[
				'  100  -  200  abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  100  -  200  ',
					'range' => ['100', '200']
				]
			],
			[
				'100-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'123-456-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123-456',
					'range' => ['123', '456']
				]
			],
			[
				'123-abc-456', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123',
					'range' => ['123']
				]
			],
			[
				'0,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'range' => ['0']
				]
			],
			[
				'100  ,   200  ,  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100  ',
					'range' => ['100']
				]
			],
			[
				'{$M}-', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'range' => ['{$M}']
				]
			],
			[
				'{$M}- ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'range' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M} ',
					'range' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M} ',
					'range' => ['{$M}']
				]
			],
			[
				'100-{$M}', 0, ['usermacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'{$M.A}  - {$M.B}     -    {$M.C}      ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M.A}  - {$M.B}     ',
					'range' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'range' => ['{#M}']
				]
			],
			[
				'{#M}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'range' => ['{#M}']
				]
			],
			[
				'{#M} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M} ',
					'range' => ['{#M}']
				]
			],
			[
				'  {$M}  -{#M.A}-{#M.C}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  {$M}  -{#M.A}',
					'range' => ['{$M}', '{#M.A}']
				]
			],
			[
				'100-{#M}', 0, ['lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")} ',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'range' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{#M.C}', 0,
				['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}',
					'range' => ['{$M}', '{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}']
				]
			],
			[
				'100{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'100{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			[
				'{$M}100', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'range' => ['{$M}']
				]
			],
			[
				'{#M}100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'range' => ['{#M}']
				]
			],
			[
				'100{$M}{#M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'range' => ['100']
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'a100', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'   -300-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				',500', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				',', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{$}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{#}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{$}-{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{#}-{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			// User macros are not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{$M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			// LLD macros are not enabled.
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{#M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-300', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'range' => []
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
		$parser = new CRangeParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'range' => $parser->getRange(),
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
