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


class CConditionFormulaTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CConditionFormula
	 */
	protected $conditionFormula;

	public function setUp() {
		$this->conditionFormula = new CConditionFormula();
	}

	public function testParseValidProvider() {
		return [
			['A'],
			['A and B'],
			['A or B'],
			['(A)'],
			['((A))'],
			['A and (B and C)'],
			['A and(B and C)'],
			['(A and B)and C'],
		];
	}

	/**
	 * @dataProvider testParseValidProvider
	 *
	 * @param $string
	 */
	public function testParseValid($string) {
		$result = $this->conditionFormula->parse($string);

		$this->assertSame(true, $result);
	}

	public function testParseInvalidProvider() {
		return [
			['a'],
			['A B'],
			['A and'],
			['A and or'],
			['A an'],
			['(A'],
			['A)'],
			['((A)'],
			['(A))'],
			['(A)B'],
			['A and (B and C'],
			['A andB'],
			['AandB'],
			['A and BandC'],
		];
	}

	/**
	 * @dataProvider testParseInvalidProvider
	 *
	 * @param $string
	 */
	public function testParseInvalid($string) {
		$result = $this->conditionFormula->parse($string);

		$this->assertSame(false, $result);
	}

	public function parseConstantsProvider() {
		return [
			['A', [
				['value' => 'A', 'pos' => 0]
			]],
			['A and B', [
				['value' => 'A', 'pos' => 0],
				['value' => 'B', 'pos' => 6]
			]],
			['A and B or C', [
				['value' => 'A', 'pos' => 0],
				['value' => 'B', 'pos' => 6],
				['value' => 'C', 'pos' => 11]
			]],
			['A and B or A', [
				['value' => 'A', 'pos' => 0],
				['value' => 'B', 'pos' => 6],
				['value' => 'A', 'pos' => 11]
			]]
		];
	}

	/**
	 * @dataProvider parseConstantsProvider
	 *
	 * @param $string
	 * @param $expectedConstants
	 */
	public function testParseConstants($string, $expectedConstants) {
		$result = $this->conditionFormula->parse($string);

		$this->assertSame(true, $result);
		$this->assertSame($expectedConstants, $this->conditionFormula->constants);
	}
}
