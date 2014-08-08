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


class DetectPowerTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return array(
			array(0, 0),
			array(1, 0),
			array(1000, 1),
			array(1001, 1),
			array(1999, 1),
			array(1000000, 2),
			array('1000000000000000000000000000', 8),

			array(-1, 0),
			array(-1000, 1),
			array(-1001, 1),
			array(-1999, 1),
			array(-1000000, 2),
			array('-1000000000000000000000000000', 8),

			array(1024 * 1024, 2, 1024)
		);
	}

	/**
	 * @dataProvider provider
	 *
	 * @param $number
	 * @param $expectedResult
	 * @param $base
	 */
	public function test($number, $expectedResult, $base = 1000) {
		$this->assertEquals($expectedResult, detectPower($number, $base));
	}

}
