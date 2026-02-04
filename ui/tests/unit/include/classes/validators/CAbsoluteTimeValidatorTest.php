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

class CAbsoluteTimeValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid absolute times.
			['2021-01-01', 				[], 						null],
			['2021-01-01 00', 			[], 						null],
			['2021-01-01 00:00', 		[], 						null],
			['2021-01-01 00:00:00', 	[], 						null],
			['2021-01-01 00:00:00', 	['max' => ZBX_MAX_DATE],	null],

			// Invalid absolute time: format.
			['00:00:00', 				[], 						'invalid date'],
			['2021-01-01 99:00:00',		[], 						'invalid date'],
			['2021-01-01 12:00:00a',	[], 						'invalid date'],
			['{$MACRO}', 				[], 						'invalid date'],
			['{#MACRO}', 				[],							'invalid date'],
			['{$MACRO}', 				[],							'invalid date'],
			['{MACRO}', 				[],							'invalid date'],
			['zzzz', 					[],							'invalid date'],
			['1000-01-01 00:00:00', 	[],							'invalid date'],

			// Invalid absolute time: out of min/max range.
			['2040-01-01 00:00:00',		['max' => ZBX_MAX_DATE], 	'value must be less than or equal to '.date(ZBX_FULL_DATE_TIME, ZBX_MAX_DATE)],
			['2010-01-01 00:00:00',		['min' => ZBX_MAX_DATE], 	'value must be greater than or equal to '.date(ZBX_FULL_DATE_TIME, ZBX_MAX_DATE)]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testAbsoluteTimeValidator($name, $options, $expected_error): void {
		$validator = new CAbsoluteTimeValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
