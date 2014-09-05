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


class EvalExpressionDataTest extends PHPUnit_Framework_TestCase {

	public function testValidProvider() {
		return array(
			array(
				'{host:item.last()} = 0',
				array(
					'{host:item.last()}' => 0
				)
			),
			array(
				'{host:item.last()} <> 0',
				array(
					'{host:item.last()}' => 1
				)
			),
			array(
				'{host:item.last()} < 10',
				array(
					'{host:item.last()}' => 5
				)
			),
			array(
				'{host:item.last()} <= 10',
				array(
					'{host:item.last()}' => 10
				)
			),
			array(
				'{host:item.last()} > 10',
				array(
					'{host:item.last()}' => 15
				)
			),
			array(
				'{host:item.last()} >= 10',
				array(
					'{host:item.last()}' => 10
				)
			),
			array(
				'{host:item.last()} = 10.9',
				array(
					'{host:item.last()}' => 10.9
				)
			),
			array(
				'{host:item.last()} = 1 or {host:item2.last()} = 2',
				array(
					'{host:item.last()}' => 1,
					'{host:item2.last()}' => 2,
				)
			),
			array(
				'not {host:item.last()} = 0',
				array(
					'{host:item.last()}' => 1
				)
			),
			array(
				'{host:item.last()} = -1',
				array(
					'{host:item.last()}' => -1
				)
			),
			// units
			array(
				'{host:item.last()} = 10s',
				array(
					'{host:item.last()}' => 10
				)
			),
			array(
				'{host:item.last()} = 10m',
				array(
					'{host:item.last()}' => 600
				)
			),
			array(
				'{host:item.last()} = 10m',
				array(
					'{host:item.last()}' => '600s'
				)
			),
			array(
				'{host:item.last()} = 600',
				array(
					'{host:item.last()}' => '10m'
				)
			),
		);
	}

	/**
	 * @dataProvider testValidProvider
	 *
	 * @param $expression
	 * @param array $replacements
	 */
	public function testValid($expression, array $replacements) {
		$result = evalExpressionData($expression, $replacements);

		$this->assertSame(true, $result);
	}

	public function testInvalidProvider() {
		return array(
			array(
				'{host:item.last()} = 0',
				array(
					'{host:item.last()}' => 2
				)
			),
			array(
				'{host:item.last()} <> 0',
				array(
					'{host:item.last()}' => 0
				)
			),
			array(
				'{host:item.last()} < 10',
				array(
					'{host:item.last()}' => 15
				)
			),
			array(
				'{host:item.last()} <= 10',
				array(
					'{host:item.last()}' => 15
				)
			),
			array(
				'{host:item.last()} > 10',
				array(
					'{host:item.last()}' => 5
				)
			),
			array(
				'{host:item.last()} >= 10',
				array(
					'{host:item.last()}' => 5
				)
			),
			array(
				'{host:item.last()} = 10.9',
				array(
					'{host:item.last()}' => 10.99
				)
			),
			array(
				'{host:item.last()} = 1 and {host:item2.last()} = 2',
				array(
					'{host:item.last()}' => 2,
					'{host:item2.last()}' => 2,
				)
			),
			array(
				'not {host:item.last()} = 0',
				array(
					'{host:item.last()}' => 0
				)
			),
			array(
				'{host:item.last()} = -1',
				array(
					'{host:item.last()}' => -20
				)
			),
			// units
			array(
				'{host:item.last()} = 10s',
				array(
					'{host:item.last()}' => 11
				)
			),
			array(
				'{host:item.last()} = 10m',
				array(
					'{host:item.last()}' => 601
				)
			),
			array(
				'{host:item.last()} = 10m',
				array(
					'{host:item.last()}' => '601s'
				)
			),
			array(
				'{host:item.last()} = 600',
				array(
					'{host:item.last()}' => '11m'
				)
			),
			array(
				'{host:item.last()} = 10s',
				array(
					'{host:item.last()}' => '10m'
				)
			),
			// invalid expression
			array(
				'{host:item.last()} = value',
				array(
					'{host:item.last()}' => 2
				)
			),
		);
	}

	/**
	 * @dataProvider testInvalidProvider
	 *
	 * @param $expression
	 * @param array $replacements
	 */
	public function testInvalid($expression, array $replacements) {
		$result = evalExpressionData($expression, $replacements);

		$this->assertSame(false, $result);
	}

}
