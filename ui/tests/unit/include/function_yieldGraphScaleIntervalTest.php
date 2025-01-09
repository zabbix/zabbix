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

class function_yieldGraphScaleIntervalTest extends TestCase {

	public static function dataProvider() {
		return [
			// Non-binary, non-time units, one row only.
			[
				['min' => 0, 'max' => 1, 'units' => '', 'power' => 0, 'rows' => 1],
				[1, 2, 5, 10]
			],
			[
				['min' => 100, 'max' => 101, 'units' => '', 'power' => 0, 'rows' => 1],
				[1, 2, 5, 10]
			],
			[
				['min' => -101, 'max' => -100, 'units' => '', 'power' => 0, 'rows' => 1],
				[1, 2, 5, 10]
			],
			[
				['min' => -0.5, 'max' => 0.5, 'units' => '', 'power' => 0, 'rows' => 1],
				[1, 2, 5, 10]
			],
			[
				['min' => 0.001, 'max' => 0.005, 'units' => '', 'power' => 0, 'rows' => 1],
				[0.005, 0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10]
			],
			[
				['min' => 1003, 'max' => 1006, 'units' => '', 'power' => 0, 'rows' => 1],
				[5, 10]
			],
			[
				['min' => 1e307, 'max' => 2e307, 'units' => '', 'power' => 0, 'rows' => 1],
				[1e307, 2e307, 5e307, 1e308, INF]
			],
			[
				['min' => -2e307, 'max' => -1e307, 'units' => '', 'power' => 0, 'rows' => 1],
				[1e307, 2e307, 5e307, 1e308, INF]
			],
			[
				['min' => 0, 'max' => 3, 'units' => '', 'power' => 0, 'rows' => 1],
				[5, 10]
			],
			[
				['min' => 0, 'max' => 6, 'units' => '', 'power' => 0, 'rows' => 1],
				[10, 20]
			],
			// Binary units, one row only.
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 0, 'rows' => 1],
				[1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]
			],
			[
				['min' => 0, 'max' => 0.1, 'units' => 'B', 'power' => 0, 'rows' => 1],
				[0.1, 0.2, 0.5, 1, 2, 4, 8]
			],
			[
				['min' => -100, 'max' => -99.9, 'units' => 'B', 'power' => 0, 'rows' => 1],
				[0.1, 0.2, 0.5, 1, 2, 4, 8]
			],
			// Time units, one row only.
			[
				['min' => 0, 'max' => 0.001, 'units' => 's', 'power' => 0, 'rows' => 1],
				[0.001, 0.002, 0.005, 0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 15, 20, 30, 60, 120, 300, 600, 900,
					1200, 1800, 3600, 7200, 10800, 14400, 21600, 43200, 86400, 172800, 432000, 864000, 1296000, 2592000,
					5184000, 7776000, 10368000, 15552000, 31536000, 63072000, 157680000, 315360000
				]
			],
			[
				['min' => 0, 'max' => 1e-308, 'units' => 's', 'power' => 0, 'rows' => 1],
				[1e-308, 2e-308, 5e-308, 1e-307]
			],
			// Non-binary, non-time units, multiple rows.
			[
				['min' => 0, 'max' => 1, 'units' => '', 'power' => 0, 'rows' => 2],
				[0.5, 1, 2, 5, 10]
			],
			[
				['min' => 0, 'max' => 1, 'units' => '', 'power' => 0, 'rows' => 9],
				[0.2, 0.5, 1, 2, 5, 10]
			],
			[
				['min' => 0, 'max' => 1, 'units' => '', 'power' => 0, 'rows' => 11],
				[0.1, 0.2, 0.5, 1, 2, 5, 10]
			],
			// Binary units, multiple rows.
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 0, 'rows' => 2],
				[0.5, 1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]
			],
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 0, 'rows' => 9],
				[0.2, 0.5, 1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]
			],
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 0, 'rows' => 11],
				[0.1, 0.2, 0.5, 1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]
			],
			// Time units, multiple rows.
			[
				['min' => 0, 'max' => 0.001, 'units' => 's', 'power' => 0, 'rows' => 2],
				[0.0005, 0.001, 0.002, 0.005, 0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1]
			],
			[
				['min' => 0, 'max' => 1e-307, 'units' => 's', 'power' => 0, 'rows' => 10],
				[1e-308, 2e-308, 5e-308, 1e-307]
			],
			// Non-binary, non-time units, specific power.
			[
				['min' => 0, 'max' => 12345, 'units' => '', 'power' => 0, 'rows' => 1],
				[20000, 50000, 100000]
			],
			[
				['min' => 0, 'max' => 12345, 'units' => '', 'power' => 1, 'rows' => 1],
				[20000, 50000, 100000]
			],
			[
				['min' => 0, 'max' => 12345, 'units' => 'U', 'power' => 5, 'rows' => 1],
				[20000, 50000, 100000]
			],
			// Binary units, specific power.
			[
				['min' => 0, 'max' => 1234, 'units' => 'B', 'power' => 0, 'rows' => 1],
				[2048, 4096, 8192, 16384, 32768, 65536]
			],
			[
				['min' => 0, 'max' => 1234, 'units' => 'B', 'power' => 1, 'rows' => 1],
				[2048, 4096, 8192, 16384, 32768, 65536]
			],
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 1, 'rows' => 1],
				[1.024, 2.048, 5.12, 10.24, 20.48, 51.2, 102.4]
			],
			[
				['min' => 0, 'max' => 1, 'units' => 'B', 'power' => 2, 'rows' => 1],
				[1.048576, 2.097152, 5.24288, 10.48576, 20.97152, 52.4288, 104.8576]
			],
			[
				['min' => 0, 'max' => SEC_PER_MONTH, 'units' => 's', 'power' => 0, 'rows' => 1],
				[SEC_PER_MONTH, SEC_PER_MONTH*2, SEC_PER_MONTH*3, SEC_PER_MONTH*4, SEC_PER_MONTH*6, SEC_PER_YEAR]
			],
			[
				['min' => 0, 'max' => SEC_PER_MONTH, 'units' => 's', 'power' => -1, 'rows' => 1],
				[SEC_PER_MONTH, SEC_PER_MONTH*2, SEC_PER_MONTH*3, SEC_PER_MONTH*4, SEC_PER_MONTH*6, SEC_PER_YEAR]
			],
			[
				['min' => 0, 'max' => SEC_PER_MONTH, 'units' => 's', 'power' => 5, 'rows' => 1],
				[SEC_PER_YEAR, SEC_PER_YEAR*2, SEC_PER_YEAR*5, SEC_PER_YEAR*10]
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
		$generator = yieldGraphScaleInterval($args['min'], $args['max'], $args['units'], $args['power'], $args['rows']);

		foreach ($expected as $iteration => $expected_value) {
			if (!$generator->valid()) {
				$this->fail('yieldGraphScaleInterval is not valid.');
			}

			// Values are converted to strings to force correct comparison of small numbers.
			$this->assertSame((string) $expected_value, (string) $generator->current(),
				sprintf('Running iteration #%1$d.', $iteration + 1)
			);

			$generator->next();
		}
	}
}
