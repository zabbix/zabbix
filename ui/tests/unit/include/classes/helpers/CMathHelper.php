<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

class CMathHelperTest extends TestCase {

	public function testSafeSumProvider() {
		return [
			[[1E+308, -1E+308], 0],
			[[1E+308, 1E+308], INF],
			[[-1E+308, -1E+308], -INF],
			[[1E+308, 1E+308, -1E+308, -1E+308, 5], 5],
			[[-1E+308, -1E+308, 1E+308, 1E+308, 5], 5],
			[[-1E+308, 1E+308, -1E+308, 1E+308, 5], 5],
			[[1E+308, 1E+308, 5, -1E+308, -1E+308], 5],
			[[-1E+308, -1E+308, 5, 1E+308, 1E+308], 5],
			[[-1E+308, 1E+308, 5, -1E+308, 1E+308], 5],
			[[5, 1E+308, 1E+308, -1E+308, -1E+308], 5],
			[[5, -1E+308, -1E+308, 1E+308, 1E+308], 5],
			[[5, -1E+308, 1E+308, -1E+308, 1E+308], 5]
		];
	}

	/**
	 * @dataProvider testSafeSumProvider
	 *
	 * @param array $values
	 * @param float $expected
	 */
	public function testSafeSum(array $values, float $expected) {
		$this->assertSame(CMathHelper::safeSum($values), $expected);
	}

	public function testSafeMulProvider() {
		return [
			[[1E+308, 1E-308], 1],
			[[1E+308, 1E+308], INF],
			[[1E+308, 1E+308, 1E-308, 1E-308, 5], 5],
			[[1E+308, -1E+308, 1E-308, -1E-308, 5], 5],
			[[-1E+308, 1E+308, -1E-308, 1E-308, 5], 5],
			[[1E+308, 1E+308, 5, 1E-308, 1E-308], 5],
			[[1E+308, -1E+308, 5, 1E-308, -1E-308], 5],
			[[-1E+308, 1E+308, 5, -1E-308, 1E-308], 5],
			[[5, 1E+308, 1E+308, 1E-308, 1E-308], 5],
			[[5, 1E+308, -1E+308, 1E-308, -1E-308], 5],
			[[5, -1E+308, 1E+308, -1E-308, 1E-308], 5]
		];
	}

	/**
	 * @dataProvider testSafeMulProvider
	 *
	 * @param array $values
	 * @param float $expected
	 */
	public function testSafeMul(array $values, float $expected) {
		$this->assertSame(CMathHelper::safeMul($values), $expected);
	}

	public function testSafeAvgProvider() {
		return [
			[[1E+308, 1E+308, -1E+308, -1E+308], 0],
			[[-1E+308, -1E+308, 1E+308, 1E+308], 0],
			[[-1E+308, -1E+308, 5, 1E+308, 1E+308], 1],
			[[1E-308, 1E-308, -1E-308, -1E-308], 0],
			[[-1E-308, -1E-308, 1E-308, 1E-308], 0],
			[[-1E-308, -1E-308, 5, 1E-308, 1E-308], 1]
		];
	}

	/**
	 * @dataProvider testSafeAvgProvider
	 *
	 * @param array $values
	 * @param float $expected
	 */
	public function testSafeAvg(array $values, float $expected) {
		$this->assertSame(CMathHelper::safeAvg($values), $expected);
	}
}
