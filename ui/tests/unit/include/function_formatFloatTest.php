<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class function_formatFloatTest extends TestCase {

	public static function dataProvider() {
		return [
			[0,						15,	null,	false,	'0'],
			[1,						15,	null,	false,	'1'],
			[9.4,					15,	null,	false,	'9'],
			[9.5,					15,	null,	false,	'10'],
			[9.99999999999999E+14,	15,	null,	false,	'999999999999999'],
			[9.99999999999999E+15,	15,	null,	false,	'1E+16'],
			[1.1,					15,	4,		false,	'1.1'],
			[1.0001,				15,	4,		false,	'1.0001'],
			[1.00004,				15,	4,		false,	'1'],
			[1.00005,				15,	4,		false,	'1.0001'],
			[0.000012344,			15,	4,		false,	'0.00001234'],
			[0.000012345,			15,	4,		false,	'0.00001235'],
			[100.00004,				15,	4,		false,	'100'],
			[100.00005,				15,	4,		false,	'100.0001'],
			[1E-14,					15,	4,		false,	'0.00000000000001'],
			[1E-15,					15,	4,		false,	'1E-15'],
			[1.0004E-14,			15,	4,		false,	'0.00000000000001'],
			[1.0005E-14,			15,	4,		false,	'1.0005E-14'],
			[1E+6,					4,	null,	false,	'1000000'],
			[1E+7,					4,	null,	false,	'1E+7'],
			[1.4E+100,				4,	null,	false,	'1E+100'],
			[1.5E+100,				4,	null,	false,	'2E+100'],
			[1.004E+100,			4,	2,		false,	'1E+100'],
			[1.005E+100,			4,	2,		false,	'1.01E+100'],
			[0.0004,				15,	4,		true,	'0.0004'],
			[0.0005,				15,	4,		true,	'0.0005'],
			[0.00004,				15,	4,		true,	'4.0000E-5'],
			[0.00005,				15,	4,		true,	'5.0000E-5'],
			[100.0004,				15,	4,		true,	'100.0004'],
			[100.0005,				15,	4,		true,	'100.0005'],
			[100.00004,				15,	4,		true,	'100.0000'],
			[100.00005,				15,	4,		true,	'100.0001']
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int $precision
	 * @param int $decimals
	 * @param bool $exact
	 * @param string $expected
	*/
	public function test($source, $precision, $decimals, $exact, $expected) {
		$this->assertSame($expected, formatFloat($source, $precision, $decimals, $exact));
	}
}
