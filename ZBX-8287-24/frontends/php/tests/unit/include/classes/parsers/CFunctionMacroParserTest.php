<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		return array(
			array('{host:item.func()}', 0, '{host:item.func()}', 18),
			array('{host:item.func(0)}', 0, '{host:item.func(0)}', 19),
			array('{host:item.func(0, param)}', 0, '{host:item.func(0, param)}', 26),
			array('{host:item.func(0, "param")}', 0, '{host:item.func(0, "param")}', 28),
			array('{host:item[0].func()}', 0, '{host:item[0].func()}', 21),
			array('{host:item[0, param].func()}', 0, '{host:item[0, param].func()}', 28),
			array('{host:item[, param].func()}', 0, '{host:item[, param].func()}', 27),
			array('{host:item[0, "param"].func()}', 0, '{host:item[0, "param"].func()}', 30),

			array('{host:item.func()} = 0', 0, '{host:item.func()}', 18),
			array('not {host:item.func()} = 0', 4, '{host:item.func()}', 18),
		);
	}

	public function invalidProvider() {
		return array(
			array('', 0,  0),
			array('{}', 0,  1),
			array('{host}', 0, 5),
			array('{host:item}', 0, 10),
			array('{host:item.func}', 0, 15),
			array('{host:item.func(}', 0, 16),
			array('{host:item.func()', 0, 17),
			array('{host.item.func()}', 0, 15),
		);
	}

	public function testParseExpressionProvider() {
		return array(
			array(
				'{host:item[0, param1, "param2"].func(0, param1, "param2")}',
				array(
					'expression' => '{host:item[0, param1, "param2"].func(0, param1, "param2")}',
					'pos' => 0,
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(0, param1, "param2")',
					'functionName' => 'func',
					'functionParam' => '0, param1, "param2"',
					'functionParamList' => array(
						'0',
						'param1',
						'param2'
					)
				)
			),
			array(
				'{host:item[0, param1, "param2"].func(, param1, "param2")}',
				array(
					'expression' => '{host:item[0, param1, "param2"].func(, param1, "param2")}',
					'pos' => 0,
					'host' => 'host',
					'item' => 'item[0, param1, "param2"]',
					'function' => 'func(, param1, "param2")',
					'functionName' => 'func',
					'functionParam' => ', param1, "param2"',
					'functionParamList' => array(
						'',
						'param1',
						'param2'
					)
				)
			),
		);
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
