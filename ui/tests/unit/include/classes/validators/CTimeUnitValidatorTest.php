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

class CTimeUnitValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid time units.
			['10', 			['max' => ZBX_MAX_DATE], 						null],
			['10s', 		['max' => ZBX_MAX_DATE], 						null],
			['10h', 		['max' => ZBX_MAX_DATE], 						null],
			['90s',			['max' => 90], 									null],
			['0',			['max' => 90], 									null],
			['{$MACRO}', 	['usermacros' => true, 'max' => ZBX_MAX_DATE],	null],
			['{#MACRO}', 	['lldmacros' => true, 'max' => ZBX_MAX_DATE],	null],

			// Invalid time: format.
			['-2s', 		[], 											'a time unit is expected'],
			['10y',			[], 											'a time unit is expected'],
			['10h 10s', 	['max' => ZBX_MAX_DATE], 						'a time unit is expected'],
			['{$MACRO}', 	[],												'a time unit is expected'],
			['{MACRO}', 	[],												'a time unit is expected'],
			['zzzz', 		[],												'a time unit is expected'],
			['now', 		[],												'a time unit is expected'],

			// Invalid time: only one macro is allowed.
			['{$MA}{$MA}', 	['usermacros' => true],							'a time unit is expected'],
			['10{$MA}', 	['usermacros' => true],							'a time unit is expected'],
			['{#MA}{$MA}', 	['usermacros' => true, 'lldmacros' => true],	'a time unit is expected'],

			// Invalid time: out of min/max range.
			['100s',		['max' => 30], 									'value must be between 0 and 30s'],
			['100s',		['max' => 60], 									'value must be between 0 and 1m'],
			['100s',		['max' => 61], 									'value must be between 0 and 1m 1s (61s)'],
			['100s',		['max' => 90], 									'value must be between 0 and 1m 30s (90s)'],
			['100s',		['min' => SEC_PER_HOUR, 'max' => SEC_PER_DAY], 	'value must be between 1h (3600s) and 1d (86400s)']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testAbsoluteTimeValidator($name, $options, $expected_error): void {
		$validator = new CTimeUnitValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
