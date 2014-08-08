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


class ConvertUnitsWithPrefixTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return array(
			array('0', '0'),
			array('0.00005', '0.0001'),
			array('0.00005', '0.0001'),
			array('0.00005', '0.00010'),
			array('1.235', '1.24'),
			array('1.235', '1.24'),
			array('9999999999999999.555', '10 P'),

			array('1.235', '1.24', '', false, 2),
			array('1.235', '1.24000', '', false, 5),

			array('1000000', '1 M'),

			array('1000000', '1000 KA', 'A', 1),
			array('1000000', '1.00 MA', 'A', false, 2),

			array(1024 * 1024, '1 MB', 'B'),
			array(1024 * 1024, '1024 KB', 'B', 1),
			array(1024 * 1024, '1.00 MB', 'B', false, 2),

			array('-1000000', '-1 MA', 'A')
		);
	}

	/**
	 * @dataProvider provider
	 *
	 * @param $value
	 * @param $expectedResult
	 * @param string $unit
	 * @param $power
	 * @param $scale
	 */
	public function test($value, $expectedResult, $unit = '', $power = false, $scale = false) {
		$this->assertEquals($expectedResult, convertUnitsWithPrefix($value, $unit, $power, $scale));
	}

}
