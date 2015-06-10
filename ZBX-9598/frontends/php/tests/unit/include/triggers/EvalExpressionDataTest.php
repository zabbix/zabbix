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


class EvalExpressionDataTest extends PHPUnit_Framework_TestCase {

	public function testValidProvider() {
		return [
			[
				'{host:item.last()} = 0',
				[
					'{host:item.last()}' => 0
				]
			],
			[
				'{host:item.last()} <> 0',
				[
					'{host:item.last()}' => 1
				]
			],
			[
				'{host:item.last()} < 10',
				[
					'{host:item.last()}' => 5
				]
			],
			[
				'{host:item.last()} <= 10',
				[
					'{host:item.last()}' => 10
				]
			],
			[
				'{host:item.last()} > 10',
				[
					'{host:item.last()}' => 15
				]
			],
			[
				'{host:item.last()} >= 10',
				[
					'{host:item.last()}' => 10
				]
			],
			[
				'{host:item.last()} = 10.9',
				[
					'{host:item.last()}' => 10.9
				]
			],
			[
				'{host:item.last()} = 1 or {host:item2.last()} = 2',
				[
					'{host:item.last()}' => 1,
					'{host:item2.last()}' => 2,
				]
			],
			[
				'not {host:item.last()} = 0',
				[
					'{host:item.last()}' => 1
				]
			],
			[
				'{host:item.last()} = -1',
				[
					'{host:item.last()}' => -1
				]
			],
			// units
			[
				'{host:item.last()} = 10s',
				[
					'{host:item.last()}' => 10
				]
			],
			[
				'{host:item.last()} = 10m',
				[
					'{host:item.last()}' => 600
				]
			],
			[
				'{host:item.last()} = 10m',
				[
					'{host:item.last()}' => '600s'
				]
			],
			[
				'{host:item.last()} = 600',
				[
					'{host:item.last()}' => '10m'
				]
			],
		];
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
		return [
			[
				'{host:item.last()} = 0',
				[
					'{host:item.last()}' => 2
				]
			],
			[
				'{host:item.last()} <> 0',
				[
					'{host:item.last()}' => 0
				]
			],
			[
				'{host:item.last()} < 10',
				[
					'{host:item.last()}' => 15
				]
			],
			[
				'{host:item.last()} <= 10',
				[
					'{host:item.last()}' => 15
				]
			],
			[
				'{host:item.last()} > 10',
				[
					'{host:item.last()}' => 5
				]
			],
			[
				'{host:item.last()} >= 10',
				[
					'{host:item.last()}' => 5
				]
			],
			[
				'{host:item.last()} = 10.9',
				[
					'{host:item.last()}' => 10.99
				]
			],
			[
				'{host:item.last()} = 1 and {host:item2.last()} = 2',
				[
					'{host:item.last()}' => 2,
					'{host:item2.last()}' => 2,
				]
			],
			[
				'not {host:item.last()} = 0',
				[
					'{host:item.last()}' => 0
				]
			],
			[
				'{host:item.last()} = -1',
				[
					'{host:item.last()}' => -20
				]
			],
			// units
			[
				'{host:item.last()} = 10s',
				[
					'{host:item.last()}' => 11
				]
			],
			[
				'{host:item.last()} = 10m',
				[
					'{host:item.last()}' => 601
				]
			],
			[
				'{host:item.last()} = 10m',
				[
					'{host:item.last()}' => '601s'
				]
			],
			[
				'{host:item.last()} = 600',
				[
					'{host:item.last()}' => '11m'
				]
			],
			[
				'{host:item.last()} = 10s',
				[
					'{host:item.last()}' => '10m'
				]
			],
			// invalid expression
			[
				'{host:item.last()} = value',
				[
					'{host:item.last()}' => 2
				]
			],
		];
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
