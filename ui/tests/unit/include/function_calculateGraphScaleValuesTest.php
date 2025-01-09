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

class function_calculateGraphScaleValuesTest extends TestCase {

	public static function dataProvider() {
		return [
			[
				['min' => 0, 'max' => 1, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.25, 'units' => '', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.25,	'value' => '0.25'],
					['relative_pos' => 0.5,		'value' => '0.50'],
					['relative_pos' => 0.75,	'value' => '0.75'],
					['relative_pos' => 1,		'value' => '1.00']
				]
			],
			[
				['min' => -1, 'max' => 0, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.25, 'units' => '', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '-1.00'],
					['relative_pos' => 0.25,	'value' => '-0.75'],
					['relative_pos' => 0.5,		'value' => '-0.50'],
					['relative_pos' => 0.75,	'value' => '-0.25'],
					['relative_pos' => 1,		'value' => '0']
				]
			],
			[
				['min' => -1, 'max' => 1, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.5, 'units' => '', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '-1.0'],
					['relative_pos' => 0.25,	'value' => '-0.5'],
					['relative_pos' => 0.5,		'value' => '0'],
					['relative_pos' => 0.75,	'value' => '0.5'],
					['relative_pos' => 1,		'value' => '1.0']
				]
			],
			[
				['min' => 0, 'max' => 5000, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 1000, 'units' => '', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.2,		'value' => '1000'],
					['relative_pos' => 0.4,		'value' => '2000'],
					['relative_pos' => 0.6,		'value' => '3000'],
					['relative_pos' => 0.8,		'value' => '4000'],
					['relative_pos' => 1,		'value' => '5000']
				]
			],
			[
				['min' => 0, 'max' => 5000, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 1000, 'units' => '', 'power' => 1, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.2,		'value' => '1 K'],
					['relative_pos' => 0.4,		'value' => '2 K'],
					['relative_pos' => 0.6,		'value' => '3 K'],
					['relative_pos' => 0.8,		'value' => '4 K'],
					['relative_pos' => 1,		'value' => '5 K']
				]
			],
			[
				['min' => 0, 'max' => 4096, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 1024, 'units' => 'B', 'power' => 1, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0 B'],
					['relative_pos' => 0.25,	'value' => '1 KB'],
					['relative_pos' => 0.5,		'value' => '2 KB'],
					['relative_pos' => 0.75,	'value' => '3 KB'],
					['relative_pos' => 1,		'value' => '4 KB']
				]
			],
			[
				['min' => 0, 'max' => 4096*1024, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 1024*1024/2, 'units' => 'B', 'power' => 2, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0 B'],
					['relative_pos' => 0.125,	'value' => '0.5 MB'],
					['relative_pos' => 0.25,	'value' => '1.0 MB'],
					['relative_pos' => 0.375,	'value' => '1.5 MB'],
					['relative_pos' => 0.5,		'value' => '2.0 MB'],
					['relative_pos' => 0.625,	'value' => '2.5 MB'],
					['relative_pos' => 0.75,	'value' => '3.0 MB'],
					['relative_pos' => 0.875,	'value' => '3.5 MB'],
					['relative_pos' => 1,		'value' => '4.0 MB']
				]
			],
			[
				['min' => 0, 'max' => 10, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 5, 'units' => 's', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.5,		'value' => '5s'],
					['relative_pos' => 1,		'value' => '10s']
				]
			],
			[
				['min' => 0, 'max' => 1, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.5, 'units' => 's', 'power' => 0, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.5,		'value' => '0.5s'],
					['relative_pos' => 1,		'value' => '1s']
				]
			],
			[
				['min' => 0, 'max' => 0.1, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.05, 'units' => 's', 'power' => -1, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.5,		'value' => '50ms'],
					['relative_pos' => 1,		'value' => '100ms']
				]
			],
			[
				['min' => 0, 'max' => 0.0001, 'min_calculated' => true, 'max_calculated' => true, 'interval' => 0.00005, 'units' => 's', 'power' => -1, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.5,		'value' => '0.05ms'],
					['relative_pos' => 1,		'value' => '0.10ms']
				]
			],
			[
				['min' => 0, 'max' => SEC_PER_HOUR*10, 'min_calculated' => true, 'max_calculated' => true, 'interval' => SEC_PER_HOUR*5, 'units' => 's', 'power' => 2, 'precision_max' => 15],
				[
					['relative_pos' => 0,		'value' => '0'],
					['relative_pos' => 0.5,		'value' => '5h'],
					['relative_pos' => 1,		'value' => '10h']
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param array $args
	 * @param array $expected
	*/
	public function test(array $args, array $expected) {
		$this->assertSame($expected,
			calculateGraphScaleValues($args['min'], $args['max'], $args['min_calculated'],
				$args['max_calculated'], $args['interval'], $args['units'], $args['power'], $args['precision_max']
			)
		);
	}
}
