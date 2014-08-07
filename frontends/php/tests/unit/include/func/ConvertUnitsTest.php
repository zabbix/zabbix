<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class ConvertUnitTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return array(
			// no units or black listed units
			array(
				array(
					'value' => 0,
					'units' => null,
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				0
			),
			array(
				array(
					'value' => 0.001,
					'units' => null,
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.001'
			),
			array(
				array(
					'value' => '0.00123456',
					'units' => null,
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.001235'
			),
			array(
				array(
					'value' => '0.015',
					'units' => null,
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.02'
			),
			array(
				array(
					'value' => '1000000',
					'units' => null,
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'1000000'
			),
			array(
				array(
					'value' => 0,
					'units' => '%',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0 %'
			),
			array(
				array(
					'value' => 0.001,
					'units' => '%',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.001 %'
			),
			array(
				array(
					'value' => '0.00123456',
					'units' => '%',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.001235 %'
			),
			array(
				array(
					'value' => '0.015',
					'units' => '%',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.02 %'
			),
			array(
				array(
					'value' => '1000000',
					'units' => '%',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'1000000 %'
			),

			// units
			array(
				array(
					'value' => '0.00005',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'0.0001 A'
			),
			array(
				array(
					'value' => '0.00005',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 2   // length smaller than the result
				),
				'0.00 A'
			),
			array(
				array(
					'value' => '0.00005',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 5   // length greater than the result
				),
				'0.00010 A'
			),

			// units without pow
			array(
				array(
					'value' => '1.235',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'1.24 A'
			),
			array(
				array(
					'value' => '1.235',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 2   // length smaller than the result
				),
				'1.24 A'
			),
			array(
				array(
					'value' => '1.235',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 5   // length greater than the result
				),
				'1.24000 A'
			),

			// unit with pow
			array(
				array(
					'value' => '1000000',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'1 MA'
			),
			array(
				array(
					'value' => '1000000',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => 1,     // force pow
					'length' => false
				),
				'1000 KA'
			),
			array(
				array(
					'value' => '1000000',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 2
				),
				'1.00 MA'
			),

			// byte units with pow
			array(
				array(
					'value' => 1024 * 1024,
					'units' => 'B',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'1 MB'
			),
			array(
				array(
					'value' => 1024 * 1024,
					'units' => 'B',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => 1,     // force pow
					'length' => false
				),
				'1024 KB'
			),
			array(
				array(
					'value' => 1024 * 1024,
					'units' => 'B',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => 2
				),
				'1.00 MB'
			),

			// negative unit
			array(
				array(
					'value' => '-1000000',
					'units' => 'A',
					'convert' => ITEM_CONVERT_WITH_UNITS,
					'pow' => false,
					'length' => false
				),
				'-1 MA'
			),
		);
	}

	/**
	 * @dataProvider provider
	 *
	 * @param array $options
	 * @param $expectedResult
	 */
	public function test(array $options, $expectedResult) {
		$this->assertEquals($expectedResult, convert_units($options));
	}

}
