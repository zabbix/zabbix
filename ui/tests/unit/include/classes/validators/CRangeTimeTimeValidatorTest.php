<?php
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

class CRangeTimeTimeValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid absolute times.
			['2021-01-01', 											[], 											null],
			['2021-01-01 00', 										[], 											null],
			['2021-01-01 00:00', 									[], 											null],
			['2021-01-01 00:00:00', 								[], 											null],
			['2021-01-01 00:00:00', 								['min' => strtotime('2010-01-01')],		null],
			[date(ZBX_FULL_DATE_TIME, time()+1), 	['min_in_future' => true],						null],

			// Valid relative times.
			['now-1d', 												[], 											null],
			['now+1s',												['min_in_future' => true],						null],
			['now', 												['min' => strtotime('2010-01-01')],		null],

			// Invalid absolute or relative time: format.
			['00:00:00', 											[], 											'invalid time'],
			['2021-01-01 99:00:00',									[], 											'invalid time'],
			['2021-01-01 12:00:00a',								[], 											'invalid time'],
			['{$MACRO}', 											[], 											'invalid time'],
			['{#MACRO}', 											[],												'invalid time'],
			['{$MACRO}', 											[],												'invalid time'],
			['{MACRO}', 											[],												'invalid time'],
			['zzzz', 												[],												'invalid time'],
			['1000-01-01 00:00:00', 								[],												'invalid time'],
			['2040-01-01',											[],												'invalid time'],

			// Invalid time: out of min range.
			['2010-01-01 00:00:00',									['min' => ZBX_MAX_DATE], 						'value must be greater than or equal to '.date(ZBX_FULL_DATE_TIME, ZBX_MAX_DATE)],
			['2010-01-01 00:00:00',									['min_in_future' => true], 						'value must be greater than or equal to '.date(ZBX_FULL_DATE_TIME, time()+1)],
			['now',													['min_in_future' => true],						'value must be greater than or equal to '.date(ZBX_FULL_DATE_TIME, time()+1)],
			['now-5d',												['min' => time()],								'value must be greater than or equal to '.date(ZBX_FULL_DATE_TIME, time())]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testRangeTimeValidator($name, $options, $expected_error): void {
		$validator = new CRangeTimeValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
