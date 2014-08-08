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


class ConvertUnitsWithoutPrefixTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return array(
			array('0', '0'),
			array('0.015', '0.02'),
			array('0.00123456', '0.001235'),
			array('1000000', '1000000'),
			array('9999999999999999.555', '9999999999999999.56'),
			array('9999999999999999.55', '9999999999999999.55'),
			array('1.5500', '1.55'),

			array('10', '10 %', '%'),
			array('-10', '-10 %', '%')
		);
	}

	/**
	 * @dataProvider provider
	 *
	 * @param int $value
	 * @param $expectedResult
	 * @param string $unit
	 */
	public function test($value, $expectedResult, $unit = '') {
		$this->assertEquals($expectedResult, convertUnitsWithoutPrefix($value, $unit));
	}

}
