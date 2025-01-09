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

class function_formatFloatTest extends TestCase {

	public static function dataProvider() {
		return [
			[0,						[],	'0'],
			[1,						[],	'1'],
			[9.4,					[],	'9'],
			[9.5,					[],	'10'],
			[9.99999999999999E+14,	[],	'999999999999999'],
			[9.99999999999999E+15,	[],	'1E+16'],

			[0,						['decimals' => 4],	'0'],
			[1.1,					['decimals' => 4],	'1.1'],
			[1.0001,				['decimals' => 4],	'1.0001'],
			[1.00004,				['decimals' => 4],	'1'],
			[1.00005,				['decimals' => 4],	'1.0001'],
			[0.000012344,			['decimals' => 4],	'0.00001234'],
			[0.000012345,			['decimals' => 4],	'0.00001235'],
			[100.00004,				['decimals' => 4],	'100'],
			[100.00005,				['decimals' => 4],	'100.0001'],
			[1E-14,					['decimals' => 4],	'0.00000000000001'],
			[1E-15,					['decimals' => 4],	'1E-15'],
			[1.0004E-14,			['decimals' => 4],	'1.0004E-14'],
			[1.0005E-14,			['decimals' => 4],	'1.0005E-14'],

			[1E+6,					['precision' => 4],	'1000000'],
			[1E+7,					['precision' => 4],	'1E+7'],
			[1.4E+100,				['precision' => 4],	'1E+100'],
			[1.5E+100,				['precision' => 4],	'2E+100'],

			[1.004E+100,			['precision' => 4, 'decimals' => 2],	'1E+100'],
			[1.005E+100,			['precision' => 4, 'decimals' => 2],	'1.01E+100'],
			[0.129,					['precision' => 4, 'decimals' => 2],	'0.13'],
			[0.0129,				['precision' => 4, 'decimals' => 2],	'1.29E-2'],
			[0.00129,				['precision' => 4, 'decimals' => 2],	'1.29E-3'],
			[0.000129,				['precision' => 4, 'decimals' => 2],	'1.29E-4'],

			[0.129,					['precision' => 4, 'decimals' => 2, 'decimals_exact' => true],	'0.13'],
			[0.0129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true],	'1.29E-2'],
			[0.00129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true],	'1.29E-3'],
			[0.000129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true],	'1.29E-4'],

			[0,						['decimals' => 4, 'decimals_exact' => true],	'0'],
			[0.0004,				['decimals' => 4, 'decimals_exact' => true],	'0.0004'],
			[0.0005,				['decimals' => 4, 'decimals_exact' => true],	'0.0005'],
			[0.00004,				['decimals' => 4, 'decimals_exact' => true],	'4.0000E-5'],
			[0.00005,				['decimals' => 4, 'decimals_exact' => true],	'5.0000E-5'],
			[100.0004,				['decimals' => 4, 'decimals_exact' => true],	'100.0004'],
			[100.0005,				['decimals' => 4, 'decimals_exact' => true],	'100.0005'],
			[100.00004,				['decimals' => 4, 'decimals_exact' => true],	'100.0000'],
			[100.00005,				['decimals' => 4, 'decimals_exact' => true],	'100.0001'],

			[0,						['decimals' => 4, 'decimals_exact' => true, 'zero_as_zero' => false],	'0.0000'],
			[1,						['decimals' => 4, 'decimals_exact' => true, 'zero_as_zero' => false],	'1.0000'],

			[0,						['small_scientific' => false],	'0'],
			[1,						['small_scientific' => false],	'1'],
			[9.4,					['small_scientific' => false],	'9'],
			[9.5,					['small_scientific' => false],	'10'],
			[9.99999999999999E+14,	['small_scientific' => false],	'999999999999999'],
			[9.99999999999999E+15,	['small_scientific' => false],	'1E+16'],

			[0,						['decimals' => 4, 'small_scientific' => false],	'0'],
			[1.1,					['decimals' => 4, 'small_scientific' => false],	'1.1'],
			[1.0001,				['decimals' => 4, 'small_scientific' => false],	'1.0001'],
			[1.00004,				['decimals' => 4, 'small_scientific' => false],	'1'],
			[1.00005,				['decimals' => 4, 'small_scientific' => false],	'1.0001'],
			[0.000012344,			['decimals' => 4, 'small_scientific' => false],	'0.00001234'],
			[0.000012345,			['decimals' => 4, 'small_scientific' => false],	'0.00001235'],
			[100.00004,				['decimals' => 4, 'small_scientific' => false],	'100'],
			[100.00005,				['decimals' => 4, 'small_scientific' => false],	'100.0001'],
			[1E-14,					['decimals' => 4, 'small_scientific' => false],	'0.00000000000001'],
			[1E-15,					['decimals' => 4, 'small_scientific' => false],	'0.000000000000001'],
			[1.0004E-14,			['decimals' => 4, 'small_scientific' => false],	'0.00000000000001'],
			[1.0005E-14,			['decimals' => 4, 'small_scientific' => false],	'0.00000000000001001'],

			[1E+6,					['precision' => 4, 'small_scientific' => false],	'1000000'],
			[1E+7,					['precision' => 4, 'small_scientific' => false],	'1E+7'],
			[1.4E+100,				['precision' => 4, 'small_scientific' => false],	'1E+100'],
			[1.5E+100,				['precision' => 4, 'small_scientific' => false],	'2E+100'],

			[1.004E+100,			['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'1E+100'],
			[1.005E+100,			['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'1.01E+100'],
			[0.129,					['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'0.13'],
			[0.0129,				['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'0.013'],
			[0.00129,				['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'0.0013'],
			[0.000129,				['precision' => 4, 'decimals' => 2, 'small_scientific' => false],	'0.00013'],

			[0.129,					['precision' => 4, 'decimals' => 2, 'decimals_exact' => true, 'small_scientific' => false],	'0.13'],
			[0.0129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true, 'small_scientific' => false],	'0.01'],
			[0.00129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true, 'small_scientific' => false],	'0.00'],
			[0.000129,				['precision' => 4, 'decimals' => 2, 'decimals_exact' => true, 'small_scientific' => false],	'0.00'],

			[0,						['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'0'],
			[0.0004,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'0.0004'],
			[0.0005,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'0.0005'],
			[0.00004,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'0.0000'],
			[0.00005,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'0.0001'],
			[100.0004,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'100.0004'],
			[100.0005,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'100.0005'],
			[100.00004,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'100.0000'],
			[100.00005,				['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false],	'100.0001'],

			[0,						['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false, 'zero_as_zero' => false],	'0.0000'],
			[1,						['decimals' => 4, 'decimals_exact' => true, 'small_scientific' => false, 'zero_as_zero' => false],	'1.0000']
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param float  $source
	 * @param array  $options
	 * @param string $expected
	*/
	public function test(float $source, array $options, string $expected) {
		$this->assertSame($expected, formatFloat($source, $options));
	}
}
