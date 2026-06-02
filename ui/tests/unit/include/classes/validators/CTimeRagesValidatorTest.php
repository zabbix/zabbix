<?php
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

class CTimeRagesValidatorTest extends TestCase {

	public static function dataProvider() {
		$error_syntax = _('comma separated list of time periods is expected');
		$error_start_end = _('start time must be less than end time');
		$error_unique = _('periods must be unique');

		return [
			// success
			['00:00-23:00'],
			['07:23-23:23'],
			['23:59-24:00'],
			['8:00-17:00, 7:00-18:00'],
			['8:00-17:00,7:00-18:00'],
			['8:00-17:00,   7:00-18:00'],
			['  8:00-17:00  ,   7:00-18:00  '],
			['8:00 - 17:00, 7:00 - 18:00'],
			['8:00-17:00, 7:00-18:00, 6:15-18:15, 5:30-9:47'],
			['08:00-17:00, 07:00-18:00, 06:15-18:15, 5:30-19:47'],
			// fail parsing
			['', $error_syntax],
			['a', $error_syntax],
			['1', $error_syntax],
			['11', $error_syntax],
			['1a', $error_syntax],
			['1-', $error_syntax],
			['11:', $error_syntax],
			['11:a', $error_syntax],
			['11:1', $error_syntax],
			['11:11', $error_syntax],
			['11:11-', $error_syntax],
			['11:11 ', $error_syntax],
			['11:11- ', $error_syntax],
			['11:11--', $error_syntax],
			['11:11-1', $error_syntax],
			['11:11-11', $error_syntax],
			['11:11-11:', $error_syntax],
			['11:11-11:1', $error_syntax],
			['23:00-25:61, 07:00-09:00', $error_syntax],
			['00:00-24:0', $error_syntax],
			['00:0-24:00', $error_syntax],
			['1, 00:0-24:00', $error_syntax],
			['00:00-24:01', $error_syntax],
			// fail constraint
			['11:11-11:10', $error_start_end],
			['12:00-11:59', $error_start_end],
			// fail unique
			['7:00-9:00, 7:00-9:00', $error_unique],
			['7:00-9:00, 07:00-09:00', $error_unique],
			['7:00-09:00, 07:00-09:00', $error_unique]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testValidate(string $source, string $expected_error = '') {
		$validator = new CTimeRangesValidator();
		$result = $validator->validate($source);

		if ($expected_error === '') {
			$this->assertTrue($result, (string) $validator->getError());
		}
		else {
			$this->assertSame($validator->getError(), $expected_error);
		}
	}
}
