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
					'ranges' => ['0']
				]
			],
			[
				'100', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'123456789', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123456789',
					'ranges' => ['123456789']
				]
			],
			[
				'200-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '200-300',
					'ranges' => ['200', '300']
				]
			],
			[
				'    123   ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    123   ',
					'ranges' => ['123']
				]
			],
			[
				'    234', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    234',
					'ranges' => ['234']
				]
			],
			[
				'345  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '345  ',
					'ranges' => ['345']
				]
			],
			[
				'456-457  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '456-457  ',
					'ranges' => ['456', '457']
				]
			],
			[
				'  567-568  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  567-568  ',
					'ranges' => ['567', '568']
				]
			],
			[
				'    678-679', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    678-679',
					'ranges' => ['678', '679']
				]
			],
			[
				'    789  -  800  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    789  -  800  ',
					'ranges' => ['789', '800']
				]
			],
			[
				'    800-   850  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    800-   850  ',
					'ranges' => ['800', '850']
				]
			],
			[
				'    850   -900  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '    850   -900  ',
					'ranges' => ['850', '900']
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}',
					'ranges' => ['{$M}']
				]
			],
			[
				'{$M}  -100  ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}  -100  ',
					'ranges' => ['{$M}', '100']
				]
			],
			[
				'  100-{$M}  ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  100-{$M}  ',
					'ranges' => ['100', '{$M}']
				]
			],
			[
				'   100   -   {$M}   ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   100   -   {$M}   ',
					'ranges' => ['100', '{$M}']
				]
			],
			[
				'{$M.A}-  {$M.B}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M.A}-  {$M.B}',
					'ranges' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'   {$M.A}   -   {$M.B}   ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {$M.A}   -   {$M.B}   ',
					'ranges' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}',
					'ranges' => ['{#M}']
				]
			],
			[
				'   {#M}   ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M}   ',
					'ranges' => ['{#M}']
				]
			],
			[
				'  {#M}-100  ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {#M}-100  ',
					'ranges' => ['{#M}', '100']
				]
			],
			[
				'100-   {#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-   {#M}',
					'ranges' => ['100', '{#M}']
				]
			],
			[
				'{#M.A}   -   {#M.B}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M.A}   -   {#M.B}',
					'ranges' => ['{#M.A}', '{#M.B}']
				]
			],
			[
				'   {#M.A}   -   {#M.B}   ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M.A}   -   {#M.B}   ',
					'ranges' => ['{#M.A}', '{#M.B}']
				]
			],
			[
				'  {$M}  -{#M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {$M}  -{#M}',
					'ranges' => ['{$M}', '{#M}']
				]
			],
			[
				'{#M}-  {$M}  ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}-  {$M}  ',
					'ranges' => ['{#M}', '{$M}']
				]
			],
			[
				'   {#M}   -   {$M}   ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '   {#M}   -   {$M}   ',
					'ranges' => ['{#M}', '{$M}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '100']
				]
			],
			[
				'100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'ranges' => ['100', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				' {{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}', 0,
				['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => ' {{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{{#M.B}.regsub("^([0-9]+)", "{#M.B}: \1")}',
					'ranges' => [
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
					'ranges' => ['{$M}', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'  {{#M}.regsub("^([0-9]+)", "{#M}: \1")}  -  {$M}  ', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '  {{#M}.regsub("^([0-9]+)", "{#M}: \1")}  -  {$M}  ',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '{$M}']
				]
			],
			// partial success
			[
				'random text.....0....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'ranges' => ['0']
				]
			],
			[
				'200   -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '200   ',
					'ranges' => ['200']
				]
			],
			[
				'300a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300',
					'ranges' => ['300']
				]
			],
			[
				'400-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '400',
					'ranges' => ['400']
				]
			],
			[
				'500--', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '500',
					'ranges' => ['500']
				]
			],
			[
				'600--700', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '600',
					'ranges' => ['600']
				]
			],
			[
				'700- 800  -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '700- 800  ',
					'ranges' => ['700', '800']
				]
			],
			[
				'800 -', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '800 ',
					'ranges' => ['800']
				]
			],
			[
				'  100  -  200  -  300  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  100  -  200  ',
					'ranges' => ['100', '200']
				]
			],
			[
				'  100  -  200  abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  100  -  200  ',
					'ranges' => ['100', '200']
				]
			],
			[
				'100-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'123-456-abc', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123-456',
					'ranges' => ['123', '456']
				]
			],
			[
				'123-abc-456', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '123',
					'ranges' => ['123']
				]
			],
			[
				'0,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'ranges' => ['0']
				]
			],
			[
				'100  ,   200  ,  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100  ',
					'ranges' => ['100']
				]
			],
			[
				'{$M}-', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'ranges' => ['{$M}']
				]
			],
			[
				'{$M}- ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'ranges' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M} ',
					'ranges' => ['{$M}']
				]
			],
			[
				'{$M} -', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M} ',
					'ranges' => ['{$M}']
				]
			],
			[
				'100-{$M}', 0, ['usermacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'{$M.A}  - {$M.B}     -    {$M.C}      ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M.A}  - {$M.B}     ',
					'ranges' => ['{$M.A}', '{$M.B}']
				]
			],
			[
				'{#M}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'ranges' => ['{#M}']
				]
			],
			[
				'{#M}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'ranges' => ['{#M}']
				]
			],
			[
				'{#M} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M} ',
					'ranges' => ['{#M}']
				]
			],
			[
				'  {$M}  -{#M.A}-{#M.C}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '  {$M}  -{#M.A}',
					'ranges' => ['{$M}', '{#M.A}']
				]
			],
			[
				'100-{#M}', 0, ['lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")} -', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")} ',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}- ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'ranges' => ['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}']
				]
			],
			[
				'{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}-{#M.C}', 0,
				['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}-{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}',
					'ranges' => ['{$M}', '{{#M.A}.regsub("^([0-9]+)", "{#M.A}: \1")}']
				]
			],
			[
				'100{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'100{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			[
				'{$M}100', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}',
					'ranges' => ['{$M}']
				]
			],
			[
				'{#M}100', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#M}',
					'ranges' => ['{#M}']
				]
			],
			[
				'100{$M}{#M}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => ['100']
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'a100', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'   -300-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				',500', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				',', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{$}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{#}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{$}-{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{#}-{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			// User macros are not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{$M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			// LLD macros are not enabled.
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{#M}-200', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-300', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
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
			'ranges' => $parser->getRanges(),
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
