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


class CFunctionMacroParserTest extends CParserTest {

	protected $resultClassName = 'CFunctionMacroParserResult';

	protected function getParser() {
		return new CFunctionMacroParser();
	}

	public function validProvider() {
		return [
			['{host:item.func()}', 0, '{host:item.func()}', 18],
			['{host:item.func(0)}', 0, '{host:item.func(0)}', 19],
			['{host:item.func(0, param)}', 0, '{host:item.func(0, param)}', 26],
			['{host:item.func(0, "param")}', 0, '{host:item.func(0, "param")}', 28],
			['{host:item[0].func()}', 0, '{host:item[0].func()}', 21],
			['{host:item[0, param].func()}', 0, '{host:item[0, param].func()}', 28],
			['{host:item[, param].func()}', 0, '{host:item[, param].func()}', 27],
			['{host:item[0, "param"].func()}', 0, '{host:item[0, "param"].func()}', 30],

			['{host:item.func()} = 0', 0, '{host:item.func()}', 18],
			['not {host:item.func()} = 0', 4, '{host:item.func()}', 18],
		];
	}

	public function invalidProvider() {
		return [
			['', 0,  0],
			['{}', 0,  1],
			['{host}', 0, 5],
			['{host:item}', 0, 10],
			['{host:item.func}', 0, 15],
			['{host:item.func(}', 0, 16],
			['{host:item.func()', 0, 17],
			['{host.item.func()}', 0, 15],
		];
	}

	public function testParseExpressionProvider() {
		return [
			[
				'{host:item[0, param1, "param2"].func(0, param1, "param2")}',
				[
					'expression' => '{host:item[0, param1, "param2"].func(0, param1, "param2")}',
					'pos' => 0,
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(0, param1, "param2")',
					'functionName' => 'func',
					'functionParam' => '0, param1, "param2"',
					'functionParamList' => [
						'0',
						'param1',
						'param2'
					]
				]
			],
			[
				'{host:item[0, param1, "param2"].func(, param1, "param2")}',
				[
					'expression' => '{host:item[0, param1, "param2"].func(, param1, "param2")}',
					'pos' => 0,
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(, param1, "param2")',
					'functionName' => 'func',
					'functionParam' => ', param1, "param2"',
					'functionParamList' => [
						'',
						'param1',
						'param2'
					]
				]
			],
		];
	}


	/**
	 * Test the CFunctionMacroParserResult::$expression property.
	 *
	 * @dataProvider testParseExpressionProvider()
	 *
	 * @param string    $string
	 * @param array     $expectedExpression
	 */
	public function testParseExpression($string, array $expectedExpression) {
		$result = $this->getParser()->parse($string, 0);

		$this->assertTrue($result instanceof CFunctionMacroParserResult);
		$this->assertEquals($result->expression, $expectedExpression);
	}
}
