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

class CUniqueValuesValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid.
			['',		[],							null],
			['a,b',		[],							null],
			['a;a',		[],							null],

			// Invalid.
			['aa,b,aa',	[],							'values must be unique'],
			[',',		[],							'values must be unique']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testUniqueValuesValidator($name, $options, $expected_error): void {
		$validator = new CUniqueValuesValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
