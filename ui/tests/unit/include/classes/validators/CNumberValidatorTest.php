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

class CNumberValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid numbers.
			['10', 			['with_float' => false], 											null],
			['10.2', 		[], 																null],
			['10.35', 		['max' => '10.4'], 													null],
			['10.3',		['min' => '10.2'], 													null],
			['0',			['max' => 90, 'min' => 0], 											null],
			['4.2e-5',		[], 																null],
			['{$MACRO}', 	['usermacros' => true],												null],
			['{#MACRO}', 	['lldmacros' => true],												null],

			// Invalid integer: format.
			['10z', 		['with_float' => false], 											'value is not a valid integer'],
			['z10',			['with_float' => false], 											'value is not a valid integer'],
			['',			['with_float' => false], 											'value is not a valid integer'],
			['10.2',		['with_float' => false], 											'value is not a valid integer'],
			['4e-10',		['with_float' => false], 											'value is not a valid integer'],
			['4e2',			['with_float' => false], 											'value is not a valid integer'],

			// Invalid float: format.
			['10z', 		[], 																'value is not a valid floating point number'],
			['z10',			[], 																'value is not a valid floating point number'],
			['',			[], 																'value is not a valid floating point number'],

			// Invalid integer: only one macro is allowed.
			// TODO: uncomment when ZBX-27064 merged
//			['{$MA}{$MA}', 	['usermacros' => true, 'with_float' => false], 						'value is not a valid integer'],
//			['10{$MA}', 	['usermacros' => true, 'with_float' => false],						'value is not a valid integer'],
//			['{#MA}{$MA}', 	['usermacros' => true, 'lldmacros' => true, 'with_float' => false],	'value is not a valid integer'],

			// Invalid float: only one macro is allowed.
			// TODO: uncomment when ZBX-27064 merged
//			['{$MA}{$MA}', 	['usermacros' => true],												'value is not a valid floating point number'],
//			['10{$MA}', 	['usermacros' => true],												'value is not a valid floating point number'],
//			['{#MA}{$MA}', 	['usermacros' => true, 'lldmacros' => true],						'value is not a valid floating point number'],

			// Invalid number: out of min/max range.
			['100',			['max' => 30], 														'value must be less than or equal to 30'],
			['100',			['min' => 130], 													'value must be greater than or equal to 130'],
			['100.4',		['max' => 100], 													'value must be less than or equal to 100'],
			['100.3',		['max' => '100.25'], 												'value must be less than or equal to 100.25'],
			['100.3',		['min' => '100.31'], 												'value must be greater than or equal to 100.31'],
			['100.31',		['max' => '100.3'], 												'value must be less than or equal to 100.3']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testNumberValidator($name, $options, $expected_error): void {
		$validator = new CNumberValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
