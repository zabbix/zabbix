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

class CNumberValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid numbers.
			['10', 			['with_float' => false], 											null],
			['10.2', 		[], 																null],
			['-.5', 		['max' => '.4'], 													null],
			['-0.5e3', 		['max' => '0.4e3'], 												null],
			['-0.5e3', 		['min' => '-0.6e3'], 												null],
			['10.35', 		['max' => '10.4'], 													null],
			['10.3',		['min' => '10.2'], 													null],
			['0',			['max' => 90, 'min' => 0], 											null],
			['0',			['max' => 0, 'min' => 0], 											null],
			['-0',			['max' => 0, 'min' => 0], 											null],
			['0.00',		['max' => 0, 'min' => 0], 											null],
			['0',			['max' => '-0', 'min' => '-0'], 									null],
			['4.2e-5',		[], 																null],
			['{$MACRO}', 	['usermacros' => true],												null],
			['{#MACRO}', 	['lldmacros' => true],												null],
			['1e-100',		['min' => 0],														null],
			['1.45e999',	['max' => '1e1000'], 												null],
			['0.3',			['min' => '0.25', 'max' => '0.4'], 									null],
			['3e-1',		['min' => '0.25', 'max' => '0.4'], 									null],

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

			// Invalid number: out of min/max range.
			['100',			['max' => 30], 														'value must be less than or equal to 30'],
			['100',			['min' => 130], 													'value must be greater than or equal to 130'],
			['100.4',		['max' => 100], 													'value must be less than or equal to 100'],
			['100.3',		['max' => '100.25'], 												'value must be less than or equal to 100.25'],
			['100.3',		['max' => '0.10025e3'], 											'value must be less than or equal to 0.10025e3'],
			['100.3',		['max' => '10025e-2'], 												'value must be less than or equal to 10025e-2'],
			['100.3',		['max' => '1002.5e-1'], 											'value must be less than or equal to 1002.5e-1'],
			['100.3',		['min' => '100.31'], 												'value must be greater than or equal to 100.31'],
			['100.31',		['max' => '100.3'], 												'value must be less than or equal to 100.3'],
			['1e1000',		['max' => '1000'], 													'value must be less than or equal to 1000'],
			['1e1000',		['max' => 1e3], 													'value must be less than or equal to 1000'],
			['1e1000',		['max' => '1e3'], 													'value must be less than or equal to 1e3'],
			['1e1000',		['max' => '0.001e6'], 												'value must be less than or equal to 0.001e6'],
			['1e1000',		['max' => 0.001e6], 												'value must be less than or equal to 1000'],
			['1e1000',		['max' => '0.0011e3'], 												'value must be less than or equal to 0.0011e3'],
			['1e1000',		['max' => 0.0011e3], 												'value must be less than or equal to 1.1'],
			[1,				['max' => '.01'],													'value must be less than or equal to .01'],
			[1,				['max' => .01],														'value must be less than or equal to 0.01'],
			[1,				['max' => '.01e-4'],												'value must be less than or equal to .01e-4'],
			[1,				['max' => .01e-4],													'value must be less than or equal to 1.0E-6'],
			[1,				['min' => ZBX_MAX_UINT64],											'value must be greater than or equal to '.ZBX_MAX_UINT64],
			['-1.2e3',		['min' => '0'],														'value must be greater than or equal to 0'],
			['.01',			['min' => '.012'],													'value must be greater than or equal to .012']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testNumberValidator($value, $options, $expected_error): void {
		$validator = new CNumberValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($value));
		$this->assertSame($expected_error, $validator->getError());
	}
}
