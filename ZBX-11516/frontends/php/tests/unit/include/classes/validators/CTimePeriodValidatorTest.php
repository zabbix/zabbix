<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CTimePeriodValidatorTest extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return [
			['1-7,00:00-24:00', TRUE, TRUE],
			['1-7,00:00-24:00', FALSE, TRUE],
			['1-7,00:00-24:00;1-7,00:00-24:00', TRUE, TRUE],
			['1,00:00-24:00',FALSE, TRUE],
			[
				'1,00:00-24:00;2,00:00-24:00;3,00:00-24:00;4,00:00-24:00;5,00:00-24:00;6,00:00-24:00;7,00:00-24:00',
				TRUE,
				TRUE
			],
			[
				'1-1,00:00-24:00;2-2,00:00-24:00;3-3,00:00-24:00;4-4,00:00-24:00;5-5,00:00-24:00;6-6,00:00-24:00;'.
					'7-7,00:00-24:00',
				TRUE,
				TRUE
			],
			[
				'1-2,00:00-24:00;1-3,00:00-24:00;1-4,00:00-24:00;1-5,00:00-24:00;1-6,00:00-24:00;1-7,00:00-24:00',
				TRUE,
				TRUE
			],
			['2-3,00:00-24:00;2-4,00:00-24:00;2-5,00:00-24:00;2-6,00:00-24:00;2-7,00:00-24:00', TRUE, TRUE],
			['3-4,00:00-24:00;3-5,00:00-24:00;3-6,00:00-24:00;3-7,00:00-24:00', TRUE, TRUE],
			['4-5,00:00-24:00;4-6,00:00-24:00;4-7,00:00-24:00', TRUE, TRUE],
			['5-6,00:00-24:00;5-7,00:00-24:00', TRUE, TRUE],
			['6-7,00:00-24:00', FALSE, TRUE],
			['1-7,0:00-9:00', FALSE, TRUE],
			['1-7,00:00-09:00', FALSE, TRUE],
			['1-7,00:00-09:00;', TRUE, TRUE],
			['1-7,00:00-09:00;1-7,00:00-09:00', TRUE, TRUE],
			['1-7,00:00-09:00;1-7,00:00-09:00;', TRUE, TRUE],
			['1-7,08:00-09:30', TRUE, TRUE],
			['1-7,12:00-15:15', TRUE, TRUE],
			['1- 7,12:00-15:15', TRUE, FALSE],
			['1-7, 12:00-15:15', TRUE, FALSE],
			['1-7 ,12:00-15:15', TRUE, FALSE],
			['1-7,12:00 -15:15', TRUE, FALSE],
			['1-7,12:00- 15:15', TRUE, FALSE],
			[' 1-7,12:00-15:15', TRUE, FALSE],
			['1-7,13:16-20:34', FALSE, TRUE],
			['1', TRUE, FALSE],
			['asd', TRUE, FALSE],
			['a5-7,00:00-09:00', FALSE, FALSE],
			['5-7,00:00-24:59;', FALSE, FALSE],
			['5-2,00:00-09:00;', FALSE, FALSE],
			['5-7,20:00-09:00;', FALSE, FALSE],
			['5-7,20:00-00:00;', TRUE, FALSE],
			['5-7,10:00-20:00;;', FALSE, FALSE],
			['5-7,:00-20:00', FALSE, FALSE],
			['7,0:-20:00', FALSE, FALSE],
			['-,:-:', FALSE, FALSE],
			['-,:-:;-,:-:', FALSE, FALSE],
			['', FALSE, FALSE],
			[';', FALSE, FALSE],
			['1234432', TRUE, FALSE],
			['444332222', TRUE, FALSE],
			['as1s928skahf', TRUE, FALSE],
			['12s3s019s8', TRUE, FALSE],
			['*&@&^!_!+~)":}{><', TRUE, FALSE],
			['1-4,00:00-09:AB', TRUE, FALSE],
			['1-4,00:00-09:00;5,03:00-45:29;', TRUE, FALSE],
			['1-4,00:00-09:00;5,03:00-09:86;', TRUE, FALSE],
			['2,08:00-09:00;1-7,00:AB-09:00', TRUE, FALSE],
			['2,W:00-09:00;1-7,00:00-09:00', TRUE, FALSE],
			['1-7,00:00-09:00;1-7,00:00-09:00;;;', TRUE, FALSE],
			['1-7,00:00-09:00;5-2,00:00-09:00', TRUE, FALSE],
			['5-2,00:00-09:00;1-7,00:00-09:00', TRUE, FALSE],
			['1-7,10:00-09:00;1-7,00:00-09:00', TRUE, FALSE],
			['5-7,00:00-09:00;4-7,10:00-09:00', TRUE, FALSE],
			['2-7,09:50-09:40;3-7,00:00-09:00', TRUE, FALSE],
			['7-7,00:00-09:00;5-7,09:55-09:35', TRUE, FALSE],
			['22,0:00-21:00', TRUE, FALSE],
			['2-22,0:00-21:00', TRUE, FALSE],
			['22-22,0:00-21:00', TRUE, FALSE],
			['2,0:00-25:00', TRUE, FALSE],
			['3,25:00-09:00', TRUE, FALSE],
			['4,25:00-26:00', TRUE, FALSE],
			['5,07:999-09:00', TRUE, FALSE],
			['6,00:99-09:00', TRUE, FALSE],
			['7,00:00-09:99', TRUE, FALSE],
			['1,08:00-09:999', FALSE, FALSE],
			['-1-7,00:00-09:00', FALSE, FALSE],
			['1-7,24:00-24:30', FALSE, FALSE],
			['1-7,00:00-24:00;1-7,00:00-24:00', FALSE, FALSE]
		];
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseTimePeriod($period, $allowMultiple, $expectedResult) {
		$validator = new CTimePeriodValidator(['allowMultiple' => $allowMultiple]);

		$resultPeriod = $validator->validate($period);
		if ($expectedResult === false) {
			$this->assertFalse($resultPeriod, sprintf('Invalid period "%s" is treated as valid', $period));
		}
		else {
			$this->assertTrue($resultPeriod, sprintf('Valid period "%s" is treated as invalid', $period));
		}
	}
}
