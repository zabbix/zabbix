<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use PHPUnit\Framework\TestCase;

class CNumberHelperTest extends TestCase {

	private function dataProviderNumberStringsEqual() {
		return [
			['0', '0'],
			['1', '1'],
			['0.25', '0.25'],
			['0.25', '2.5e-1'],
			['.5', '.5'],
			['0.25e2', '0.25e2'],
			['.25e2', '.25e2'],
			['.25e2', '25'],
			['1234567890123456789012345e500', '1234567890123456789012345e500'],
			['1234567890123456789012345', '1234567890123456789012345'],
			['0.1234567890123456789012345', '1234567890123456789012345e-25'],
			['0.1234567890123456789012345e500', '1234567890123456789012345e475'],
			['-0', '-0'],
			['-1', '-1'],
			['-0.25', '-0.25'],
			['-0.25', '-2.5e-1'],
			['-.5', '-.5'],
			['-0.25e2', '-0.25e2'],
			['-.25e2', '-.25e2'],
			['-.25e2', '-25'],
			['-1234567890123456789012345e500', '-1234567890123456789012345e500'],
			['-1234567890123456789012345', '-1234567890123456789012345'],
			['-0.1234567890123456789012345', '-1234567890123456789012345e-25'],
			['-0.1234567890123456789012345e500', '-1234567890123456789012345e475']
		];
	}

	/**
	 * @dataProvider dataProviderNumberStringsEqual
	 *
	 * @param string $a
	 * @param string $b
	 */
	public function testCompareNumberStringsEqual(string $a, string $b) {
		$this->assertSame(0, CNumberHelper::compareNumberStrings($a, $b));
	}

	private function dataProviderNumberStringsLess() {
		return [
			['0', '1'],
			['1', '2'],
			['0.25', '0.25000005'],
			['0.25', '2.5000005e-1'],
			['.5', '.50001'],
			['0.25e2', '0.251e2'],
			['.25e2', '.26e2'],
			['.25e2', '.25e3'],
			['1234567890123456789012345e500', '1234567890123456789012346e500'],
			['1234567890123456789012345', '1234567890123456789012346'],
			['0.1234567890123456789012345', '1234567890123456789012346e-25'],
			['0.1234567890123456789012345e500', '1234567890123456789012346e475'],
			['-0', '1'],
			['-1', '0'],
			['-0.25', '-0.2499999'],
			['-0.25', '-2.4999999e-1'],
			['-.5', '-.4'],
			['-0.25e2', '-0.25e1'],
			['-.25e2', '-.249999e2'],
			['-1234567890123456789012345e500', '-1234567890123456789012344e500'],
			['-1234567890123456789012345', '-1234567890123456789012344'],
			['-0.1234567890123456789012345', '-1234567890123456789012344e-25'],
			['-0.1234567890123456789012345e500', '-1234567890123456789012344e475']
		];
	}

	/**
	 * @dataProvider dataProviderNumberStringsLess
	 *
	 * @param string $a
	 * @param string $b
	 */
	public function testCompareNumberStringsLess(string $a, string $b) {
		$this->assertLessThan(0, CNumberHelper::compareNumberStrings($a, $b));
	}

	/**
	 * @dataProvider dataProviderNumberStringsLess
	 *
	 * @param string $a
	 * @param string $b
	 */
	public function testCompareNumberStringsGreater(string $a, string $b) {
		$this->assertGreaterThan(0, CNumberHelper::compareNumberStrings($b, $a));
	}

	private function dataProviderNumbersEqual() {
		return [
			[0, 0],
			[1, 1],
			[0.25, 0.25],
			[0.25, 2.5e-1],
			[.5, .5],
			[0.25e2, 0.25e2],
			[.25e2, .25e2],
			[.25e2, 25],
			[-0, -0],
			[-1, -1],
			[-0.25, -0.25],
			[-0.25, -2.5e-1],
			[-.5, -.5],
			[-0.25e2, -0.25e2],
			[-.25e2, -.25e2],
			[-.25e2, -25]
		];
	}

	private function dataProviderNumbersLess() {
		return [
			[0, 1],
			[1, 2],
			[0.25, 0.25000005],
			[0.25, 2.5000005e-1],
			[.5, .50001],
			[0.25e2, 0.251e2],
			[.25e2, .26e2],
			[.25e2, .25e3],
			[-0, 1],
			[-1, 0],
			[-0.25, -0.2499999],
			[-0.25, -2.4999999e-1],
			[-.5, -.4],
			[-0.25e2, -0.25e1],
			[-.25e2, -.249999e2],
			[0, '1'],
			[1, '2'],
			[0.25, '0.25000005'],
			[0.25, '2.5000005e-1'],
			[.5, '.50001'],
			[0.25e2, '0.251E2'],
			[.25e2, '.26E2'],
			[.25e2, '.25E3'],
			[-0, '1'],
			[-1, '0'],
			[-0.25, '-0.2499999'],
			[-0.25, '-2.4999999E-1'],
			[-.5, '-.4'],
			[-0.25e2, '-0.25E1'],
			[-.25e2, '-.249999E2'],
			['0', 1],
			['1', 2],
			['0.25', 0.25000005],
			['0.25', 2.5000005e-1],
			['.5', .50001],
			['0.25E2', 0.251e2],
			['.25E2', .26e2],
			['.25E2', .25e3],
			['-0', 1],
			['-1', 0],
			['-0.25', -0.2499999],
			['-0.25', -2.4999999e-1],
			['-.5', -.4],
			['-0.25E2', -0.25e1],
			['-.25E2', -.249999e2],
			[100, '130'],
			[100, '100.4'],
			['.01', '.012']
		];
	}
	/**
	 * @dataProvider dataProviderNumberStringsEqual
	 * @dataProvider dataProviderNumbersEqual
	 *
	 * @param int|float|string $a
	 * @param int|float|string $b
	 */
	public function testCompareNumbersEqual(int|float|string $a, int|float|string $b) {
		$this->assertSame(0, CNumberHelper::compareNumbers($a, $b));
	}

	/**
	 * @dataProvider dataProviderNumberStringsLess
	 * @dataProvider dataProviderNumbersLess
	 *
	 * @param int|float|string $a
	 * @param int|float|string $b
	 */
	public function testCompareNumbersLess(int|float|string $a, int|float|string $b) {
		$this->assertLessThan(0, CNumberHelper::compareNumbers($a, $b));
	}

	/**
	 * @dataProvider dataProviderNumberStringsLess
	 * @dataProvider dataProviderNumbersLess
	 *
	 * @param int|float|string $a
	 * @param int|float|string $b
	 */
	public function testCompareNumbersGreater(int|float|string $a, int|float|string $b) {
		$this->assertGreaterThan(0, CNumberHelper::compareNumbers($b, $a));
	}
}
