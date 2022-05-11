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


class C10FunctionMacroParserTest extends CParserTest {

	protected function getParser() {
		return new C10FunctionMacroParser();
	}

	public function dataProvider() {
		return [
			['{host:item.func()}', 0, CParser::PARSE_SUCCESS, '{host:item.func()}'],
			['{host:item.func(0)}', 0, CParser::PARSE_SUCCESS, '{host:item.func(0)}'],
			['{host:item.func(0, param)}', 0, CParser::PARSE_SUCCESS, '{host:item.func(0, param)}'],
			['{host:item.func(0, "param")}', 0, CParser::PARSE_SUCCESS, '{host:item.func(0, "param")}'],
			['{host:item[0].func()}', 0, CParser::PARSE_SUCCESS, '{host:item[0].func()}'],
			['{host:item[0, param].func()}', 0, CParser::PARSE_SUCCESS, '{host:item[0, param].func()}'],
			['{host:item[, param].func()}', 0, CParser::PARSE_SUCCESS, '{host:item[, param].func()}'],
			['{host:item[0, "param"].func()}', 0, CParser::PARSE_SUCCESS, '{host:item[0, "param"].func()}'],
			['{host:item.func()} = 0', 0, CParser::PARSE_SUCCESS_CONT, '{host:item.func()}'],
			['not {host:item.func()} = 0', 4, CParser::PARSE_SUCCESS_CONT, '{host:item.func()}'],
			['', 0,  CParser::PARSE_FAIL, ''],
			['{}', 0, CParser::PARSE_FAIL, ''],
			['{host}', 0, CParser::PARSE_FAIL, ''],
			['{host:item}', 0, CParser::PARSE_FAIL, ''],
			['{host:.func()}', 0, CParser::PARSE_FAIL, ''],
			['{host:item.func}', 0, CParser::PARSE_FAIL, ''],
			['{host:item.func(}', 0, CParser::PARSE_FAIL, ''],
			['{host:item.func()', 0, CParser::PARSE_FAIL, ''],
			['{host.item.func()}', 0, CParser::PARSE_FAIL, ''],
			['{ host:item.func()}', 0, CParser::PARSE_FAIL, ''],
			['{host :item.func()}', 0, CParser::PARSE_FAIL, '']
		];
	}

	public function dataProviderParseExpression() {
		return [
			[
				'{host:item[0, param1, "param2"].func(0, param1, "param2")}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{host:item[0, param1, "param2"].func(0, param1, "param2")}',
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(0, param1, "param2")'
				]
			],
			[
				'0 <> {host:item[0, param1, "param2"].func(, param1, "param2")} or ...', 5,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{host:item[0, param1, "param2"].func(, param1, "param2")}',
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(, param1, "param2")'
				]
			],
			[
				'{hostitem[0, param1, "param2"].func(0, param1, "param2")}', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => ''
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderParseExpression()
	 *
	 * @param string    $source
	 * @param array     $expected
	 */
	public function testParseExpression($source, $pos, array $expected) {
		static $parser = null;

		if ($parser === null) {
			$parser = $this->getParser();
		}

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'host' => $parser->getHost(),
			'item' => $parser->getItem(),
			'function' => $parser->getFunction()
		]);
	}

	public function dataProviderParseExpression18() {
		return [
			[
				'{host:ssh,21.last(0)}=0', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{host:ssh,21.last(0)}',
					'host' => 'host',
					'item' => 'ssh,21',
					'function' => 'last(0)'
				]
			],
			[
				'{host:ssh,{$PORT}.last(0)}=0 | {$MACRO} | {TRIGGER.VALUE} | {host:ssh,{$PORT}.last(0)}=1', 60,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{host:ssh,{$PORT}.last(0)}',
					'host' => 'host',
					'item' => 'ssh,{$PORT}',
					'function' => 'last(0)'
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderParseExpression18()
	 *
	 * @param string    $source
	 * @param array     $expected
	 */
	public function testParseExpression18($source, $pos, array $expected) {
		static $parser = null;

		if ($parser === null) {
			$parser = new C10FunctionMacroParser(['18_simple_checks' => true]);
		}

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'host' => $parser->getHost(),
			'item' => $parser->getItem(),
			'function' => $parser->getFunction()
		]);
	}
}
