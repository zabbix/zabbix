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


require_once dirname(__FILE__).'/../../../include/translateDefines.inc.php';

use PHPUnit\Framework\TestCase;

class function_convertUnitsTest extends TestCase {
	protected $defaultTimezone;

	protected function setUp(): void {
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->defaultTimezone);
	}

	public static function provider() {
		return [
			// No units.

			[ 'in' => ['value' => ''],												'out' => '' ],
			[ 'in' => ['value' => 'TEXT'],											'out' => 'TEXT' ],
			[ 'in' => ['value' => '0'],												'out' => '0' ],

			[ 'in' => ['value' => '1'],												'out' => '1' ],
			[ 'in' => ['value' => '1.1'],											'out' => '1.1' ],
			[ 'in' => ['value' => '1.01'],											'out' => '1.01' ],
			[ 'in' => ['value' => '1.001'],											'out' => '1.001' ],
			[ 'in' => ['value' => '1.0001'],										'out' => '1.0001' ],
			[ 'in' => ['value' => '1.00001'],										'out' => '1' ],
			[ 'in' => ['value' => '999.9'],											'out' => '999.9' ],
			[ 'in' => ['value' => '999.99'],										'out' => '999.99' ],
			[ 'in' => ['value' => '999.999'],										'out' => '999.999' ],
			[ 'in' => ['value' => '999.9999'],										'out' => '999.9999' ],
			[ 'in' => ['value' => '999.99999'],										'out' => '1000' ],
			[ 'in' => ['value' => '999999999999999'],								'out' => '999999999999999' ],
			[ 'in' => ['value' => '999999999999999.4'],								'out' => '999999999999999' ],
			[ 'in' => ['value' => '999999999999999.5'],								'out' => '1E+15' ],

			[ 'in' => ['value' => '-1'],											'out' => '-1' ],
			[ 'in' => ['value' => '-1.1'],											'out' => '-1.1' ],
			[ 'in' => ['value' => '-1.01'],											'out' => '-1.01' ],
			[ 'in' => ['value' => '-1.001'],										'out' => '-1.001' ],
			[ 'in' => ['value' => '-1.0001'],										'out' => '-1.0001' ],
			[ 'in' => ['value' => '-999.9'],										'out' => '-999.9' ],
			[ 'in' => ['value' => '-999.99'],										'out' => '-999.99' ],
			[ 'in' => ['value' => '-999.999'],										'out' => '-999.999' ],
			[ 'in' => ['value' => '-999.9999'],										'out' => '-999.9999' ],
			[ 'in' => ['value' => '-999.99999'],									'out' => '-1000' ],
			[ 'in' => ['value' => '-999999999999999'],								'out' => '-999999999999999' ],
			[ 'in' => ['value' => '-999999999999999.4'],							'out' => '-999999999999999' ],
			[ 'in' => ['value' => '-999999999999999.5'],							'out' => '-1E+15' ],

			[ 'in' => ['value' => '1E0'],											'out' => '1' ],
			[ 'in' => ['value' => '1E+1'],											'out' => '10' ],
			[ 'in' => ['value' => '1E-1'],											'out' => '0.1' ],
			[ 'in' => ['value' => '1E+294'],										'out' => '1E+294' ],
			[ 'in' => ['value' => '1E-294'],										'out' => '1E-294' ],
			[ 'in' => ['value' => '1.23456789012345E+294'],							'out' => '1.2346E+294' ],
			[ 'in' => ['value' => '1.23456789012345E-294'],							'out' => '1.2346E-294' ],

			[ 'in' => ['value' => '-1E0'],											'out' => '-1' ],
			[ 'in' => ['value' => '-1E+1'],											'out' => '-10' ],
			[ 'in' => ['value' => '-1E-1'],											'out' => '-0.1' ],
			[ 'in' => ['value' => '-1E+294'],										'out' => '-1E+294' ],
			[ 'in' => ['value' => '-1E-294'],										'out' => '-1E-294' ],
			[ 'in' => ['value' => '-1.23456789012345E+294'],						'out' => '-1.2346E+294' ],
			[ 'in' => ['value' => '-1.23456789012345E-294'],						'out' => '-1.2346E-294' ],

			// No units, additional options.

			[ 'in' => ['value' => '1.5049', 'decimals' => 2],									'out' => '1.5' ],
			[ 'in' => ['value' => '1.5049', 'decimals' => 2, 'decimals_exact' => true], 		'out' => '1.50' ],
			[ 'in' => ['value' => '1.5051', 'decimals' => 2],									'out' => '1.51' ],
			[ 'in' => ['value' => '1.5051', 'decimals' => 2, 'decimals_exact' => true],			'out' => '1.51' ],
			[ 'in' => ['value' => '1.00000012345', 'decimals' => 4],							'out' => '1' ],
			[ 'in' => ['value' => '1.00000012345', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.0000'],
			[ 'in' => ['value' => '0.00000012345', 'decimals' => 4],							'out' => '0.0000001235' ],
			[ 'in' => ['value' => '0.00000012345', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.2350E-7' ],

			// Decimal units.

			[ 'in' => ['value' => '0', 'units' => 'U'],								'out' => '0 U' ],

			[ 'in' => ['value' => '1', 'units' => 'U'],								'out' => '1 U' ],
			[ 'in' => ['value' => '1.1', 'units' => 'U'],							'out' => '1.1 U' ],
			[ 'in' => ['value' => '1.01', 'units' => 'U'],							'out' => '1.01 U' ],
			[ 'in' => ['value' => '1.001', 'units' => 'U'],							'out' => '1.001 U' ],
			[ 'in' => ['value' => '1.0001', 'units' => 'U'],						'out' => '1.0001 U' ],
			[ 'in' => ['value' => '1.00001', 'units' => 'U'],						'out' => '1 U' ],
			[ 'in' => ['value' => '999.9', 'units' => 'U'],							'out' => '999.9 U' ],
			[ 'in' => ['value' => '999.99', 'units' => 'U'],						'out' => '999.99 U' ],
			[ 'in' => ['value' => '999.999', 'units' => 'U'],						'out' => '999.999 U' ],
			[ 'in' => ['value' => '999.9999', 'units' => 'U'],						'out' => '999.9999 U' ],
			[ 'in' => ['value' => '999.99999', 'units' => 'U'],						'out' => '1 KU' ],
			[ 'in' => ['value' => '1500', 'units' => 'U'],							'out' => '1.5 KU' ],
			[ 'in' => ['value' => '1549', 'units' => 'U'],							'out' => '1.55 KU' ],
			[ 'in' => ['value' => '1550', 'units' => 'U'],							'out' => '1.55 KU' ],
			[ 'in' => ['value' => '1559', 'units' => 'U'],							'out' => '1.56 KU' ],
			[ 'in' => ['value' => '1.5E+6', 'units' => 'U'],						'out' => '1.5 MU' ],
			[ 'in' => ['value' => '1.5E+9', 'units' => 'U'],						'out' => '1.5 GU' ],
			[ 'in' => ['value' => '1.5E+12', 'units' => 'U'],						'out' => '1.5 TU' ],
			[ 'in' => ['value' => '1.5E+15', 'units' => 'U'],						'out' => '1.5 PU' ],
			[ 'in' => ['value' => '1.5E+18', 'units' => 'U'],						'out' => '1.5 EU' ],
			[ 'in' => ['value' => '1.5E+21', 'units' => 'U'],						'out' => '1.5 ZU' ],
			[ 'in' => ['value' => '1.5E+24', 'units' => 'U'],						'out' => '1.5 YU' ],
			[ 'in' => ['value' => '1.5E+27', 'units' => 'U'],						'out' => '1500 YU' ],
			[ 'in' => ['value' => '1.5E+308', 'units' => 'U'],						'out' => '1.5E+284 YU' ],
			[ 'in' => ['value' => '1.23456E-294', 'units' => 'U'],					'out' => '1.2346E-294 U' ],

			[ 'in' => ['value' => '-1', 'units' => 'U'],							'out' => '-1 U' ],
			[ 'in' => ['value' => '-1.1', 'units' => 'U'],							'out' => '-1.1 U' ],
			[ 'in' => ['value' => '-1.01', 'units' => 'U'],							'out' => '-1.01 U' ],
			[ 'in' => ['value' => '-1.001', 'units' => 'U'],						'out' => '-1.001 U' ],
			[ 'in' => ['value' => '-1.0001', 'units' => 'U'],						'out' => '-1.0001 U' ],
			[ 'in' => ['value' => '-1.00001', 'units' => 'U'],						'out' => '-1 U' ],
			[ 'in' => ['value' => '-999.9', 'units' => 'U'],						'out' => '-999.9 U' ],
			[ 'in' => ['value' => '-999.99', 'units' => 'U'],						'out' => '-999.99 U' ],
			[ 'in' => ['value' => '-999.999', 'units' => 'U'],						'out' => '-999.999 U' ],
			[ 'in' => ['value' => '-999.9999', 'units' => 'U'],						'out' => '-999.9999 U' ],
			[ 'in' => ['value' => '-999.99999', 'units' => 'U'],					'out' => '-1 KU' ],
			[ 'in' => ['value' => '-1500', 'units' => 'U'],							'out' => '-1.5 KU' ],
			[ 'in' => ['value' => '-1549', 'units' => 'U'],							'out' => '-1.55 KU' ],
			[ 'in' => ['value' => '-1550', 'units' => 'U'],							'out' => '-1.55 KU' ],
			[ 'in' => ['value' => '-1559', 'units' => 'U'],							'out' => '-1.56 KU' ],
			[ 'in' => ['value' => '-1.5E+6', 'units' => 'U'],						'out' => '-1.5 MU' ],
			[ 'in' => ['value' => '-1.5E+9', 'units' => 'U'],						'out' => '-1.5 GU' ],
			[ 'in' => ['value' => '-1.5E+12', 'units' => 'U'],						'out' => '-1.5 TU' ],
			[ 'in' => ['value' => '-1.5E+15', 'units' => 'U'],						'out' => '-1.5 PU' ],
			[ 'in' => ['value' => '-1.5E+18', 'units' => 'U'],						'out' => '-1.5 EU' ],
			[ 'in' => ['value' => '-1.5E+21', 'units' => 'U'],						'out' => '-1.5 ZU' ],
			[ 'in' => ['value' => '-1.5E+24', 'units' => 'U'],						'out' => '-1.5 YU' ],
			[ 'in' => ['value' => '-1.5E+27', 'units' => 'U'],						'out' => '-1500 YU' ],
			[ 'in' => ['value' => '-1.5E+308', 'units' => 'U'],						'out' => '-1.5E+284 YU' ],
			[ 'in' => ['value' => '-1.23456E-294', 'units' => 'U'],					'out' => '-1.2346E-294 U' ],

			// Decimal units, additional options.

			[ 'in' => ['value' => '1.5049', 'units' => 'U', 'decimals' => 2],									'out' => '1.5 U' ],
			[ 'in' => ['value' => '1.5049', 'units' => 'U', 'decimals' => 2, 'decimals_exact' => true],			'out' => '1.50 U' ],
			[ 'in' => ['value' => '1.5051', 'units' => 'U', 'decimals' => 2],									'out' => '1.51 U' ],
			[ 'in' => ['value' => '1.5051', 'units' => 'U', 'decimals' => 2, 'decimals_exact' => true],			'out' => '1.51 U' ],
			[ 'in' => ['value' => '1.00000012345', 'units' => 'U', 'decimals' => 4],							'out' => '1 U' ],
			[ 'in' => ['value' => '1.00000012345', 'units' => 'U', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.0000 U'],
			[ 'in' => ['value' => '0.00000012345', 'units' => 'U', 'decimals' => 4],							'out' => '0.0000001235 U' ],
			[ 'in' => ['value' => '0.00000012345', 'units' => 'U', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.2350E-7 U' ],
			[ 'in' => ['value' => '1.5555E+22', 'units' => 'U', 'power' => 7],									'out' => '15.56 ZU' ],
			[ 'in' => ['value' => '1.5555E+22', 'units' => 'U', 'power' => 8],									'out' => '0.016 YU' ],

			// Binary units.

			[ 'in' => ['value' => '0', 'units' => 'B'],								'out' => '0 B' ],

			[ 'in' => ['value' => '1', 'units' => 'B'],								'out' => '1 B' ],
			[ 'in' => ['value' => '1.1', 'units' => 'B'],							'out' => '1.1 B' ],
			[ 'in' => ['value' => '1.01', 'units' => 'B'],							'out' => '1.01 B' ],
			[ 'in' => ['value' => '1.001', 'units' => 'B'],							'out' => '1.001 B' ],
			[ 'in' => ['value' => '1.0001', 'units' => 'B'],						'out' => '1.0001 B' ],
			[ 'in' => ['value' => '1.00001', 'units' => 'B'],						'out' => '1 B' ],
			[ 'in' => ['value' => '1023.9', 'units' => 'B'],						'out' => '1023.9 B' ],
			[ 'in' => ['value' => '1023.99', 'units' => 'B'],						'out' => '1023.99 B' ],
			[ 'in' => ['value' => '1023.999', 'units' => 'B'],						'out' => '1023.999 B' ],
			[ 'in' => ['value' => '1023.9999', 'units' => 'B'],						'out' => '1023.9999 B' ],
			[ 'in' => ['value' => '1023.99999', 'units' => 'B'],					'out' => '1 KB' ],

			[ 'in' => ['value' => '1152', 'units' => 'B'],							'out' => '1.13 KB' ],
			[ 'in' => ['value' => '1792', 'units' => 'B'],							'out' => '1.75 KB' ],
			[ 'in' => ['value' => '1835008', 'units' => 'B'],						'out' => '1.75 MB' ],
			[ 'in' => ['value' => '1879048192', 'units' => 'B'],					'out' => '1.75 GB' ],
			[ 'in' => ['value' => '1924145348608', 'units' => 'B'],					'out' => '1.75 TB' ],
			[ 'in' => ['value' => '1.97032483697459E+15', 'units' => 'B'],			'out' => '1.75 PB' ],
			[ 'in' => ['value' => '2.01761263306198E+18', 'units' => 'B'],			'out' => '1.75 EB' ],
			[ 'in' => ['value' => '2.06603533625546E+21', 'units' => 'B'],			'out' => '1.75 ZB' ],
			[ 'in' => ['value' => '2.11562018432560E+24', 'units' => 'B'],			'out' => '1.75 YB' ],
			[ 'in' => ['value' => '2.11562018432560E+24', 'units' => 'B'],			'out' => '1.75 YB' ],
			[ 'in' => ['value' => '2.11562018432560E+27', 'units' => 'B'],			'out' => '1750 YB' ],
			[ 'in' => ['value' => '2.11562018432560E+38', 'units' => 'B'],			'out' => '175000000000000 YB' ],
			[ 'in' => ['value' => '2.11562018432560E+294', 'units' => 'B'],			'out' => '1.75E+270 YB' ],

			[ 'in' => ['value' => '-1', 'units' => 'B'],							'out' => '-1 B' ],
			[ 'in' => ['value' => '-1.1', 'units' => 'B'],							'out' => '-1.1 B' ],
			[ 'in' => ['value' => '-1.01', 'units' => 'B'],							'out' => '-1.01 B' ],
			[ 'in' => ['value' => '-1.001', 'units' => 'B'],						'out' => '-1.001 B' ],
			[ 'in' => ['value' => '-1.0001', 'units' => 'B'],						'out' => '-1.0001 B' ],
			[ 'in' => ['value' => '-1.00001', 'units' => 'B'],						'out' => '-1 B' ],
			[ 'in' => ['value' => '-1023.9', 'units' => 'B'],						'out' => '-1023.9 B' ],
			[ 'in' => ['value' => '-1023.99', 'units' => 'B'],						'out' => '-1023.99 B' ],
			[ 'in' => ['value' => '-1023.999', 'units' => 'B'],						'out' => '-1023.999 B' ],
			[ 'in' => ['value' => '-1023.9999', 'units' => 'B'],					'out' => '-1023.9999 B' ],
			[ 'in' => ['value' => '-1023.99999', 'units' => 'B'],					'out' => '-1 KB' ],

			[ 'in' => ['value' => '-1152', 'units' => 'B'],							'out' => '-1.13 KB' ],
			[ 'in' => ['value' => '-1792', 'units' => 'B'],							'out' => '-1.75 KB' ],
			[ 'in' => ['value' => '-1835008', 'units' => 'B'],						'out' => '-1.75 MB' ],
			[ 'in' => ['value' => '-1879048192', 'units' => 'B'],					'out' => '-1.75 GB' ],
			[ 'in' => ['value' => '-1924145348608', 'units' => 'B'],				'out' => '-1.75 TB' ],
			[ 'in' => ['value' => '-1.97032483697459E+15', 'units' => 'B'],			'out' => '-1.75 PB' ],
			[ 'in' => ['value' => '-2.01761263306198E+18', 'units' => 'B'],			'out' => '-1.75 EB' ],
			[ 'in' => ['value' => '-2.06603533625546E+21', 'units' => 'B'],			'out' => '-1.75 ZB' ],
			[ 'in' => ['value' => '-2.11562018432560E+24', 'units' => 'B'],			'out' => '-1.75 YB' ],
			[ 'in' => ['value' => '-2.11562018432560E+24', 'units' => 'B'],			'out' => '-1.75 YB' ],
			[ 'in' => ['value' => '-2.11562018432560E+27', 'units' => 'B'],			'out' => '-1750 YB' ],
			[ 'in' => ['value' => '-2.11562018432560E+38', 'units' => 'B'],			'out' => '-175000000000000 YB' ],
			[ 'in' => ['value' => '-2.11562018432560E+294', 'units' => 'B'],		'out' => '-1.75E+270 YB' ],

			// Binary units, additional options.

			[ 'in' => ['value' => '1.5049', 'units' => 'B', 'decimals' => 2],									'out' => '1.5 B' ],
			[ 'in' => ['value' => '1.5049', 'units' => 'B', 'decimals' => 2, 'decimals_exact' => true],			'out' => '1.50 B' ],
			[ 'in' => ['value' => '1.5051', 'units' => 'B', 'decimals' => 2],									'out' => '1.51 B' ],
			[ 'in' => ['value' => '1.5051', 'units' => 'B', 'decimals' => 2, 'decimals_exact' => true],			'out' => '1.51 B' ],
			[ 'in' => ['value' => '1.00000012345', 'units' => 'B', 'decimals' => 4],							'out' => '1 B' ],
			[ 'in' => ['value' => '1.00000012345', 'units' => 'B', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.0000 B' ],
			[ 'in' => ['value' => '0.00000012345', 'units' => 'B', 'decimals' => 4],							'out' => '0.0000001235 B' ],
			[ 'in' => ['value' => '0.00000012345', 'units' => 'B', 'decimals' => 4, 'decimals_exact' => true],	'out' => '1.2350E-7 B' ],
			[ 'in' => ['value' => '15728640', 'units' => 'B', 'power' => 2],									'out' => '15 MB' ],
			[ 'in' => ['value' => '15728640', 'units' => 'B', 'power' => 3],									'out' => '0.015 GB' ],

			// Time units.

			[ 'in' => ['value' => '1', 'units' => 's'],				'out' => '1s' ],
			[ 'in' => ['value' => '0.1', 'units' => 's'],			'out' => '100ms' ],
			[ 'in' => ['value' => '0.001', 'units' => 's'],			'out' => '1ms' ],
			[ 'in' => ['value' => '0.0001', 'units' => 's'],		'out' => '0.1ms' ],
			[ 'in' => ['value' => '0.00000012345', 'units' => 's'],	'out' => '0.00012ms' ],
			[ 'in' => ['value' => '60', 'units' => 's'],			'out' => '1m' ],
			[ 'in' => ['value' => '61', 'units' => 's'],			'out' => '1m 1s' ],
			[ 'in' => ['value' => '3600', 'units' => 's'],			'out' => '1h' ],
			[ 'in' => ['value' => '3601', 'units' => 's'],			'out' => '1h 1s' ],
			[ 'in' => ['value' => '3660', 'units' => 's'],			'out' => '1h 1m' ],
			[ 'in' => ['value' => '3661', 'units' => 's'],			'out' => '1h 1m 1s' ],
			[ 'in' => ['value' => '86401', 'units' => 's'],			'out' => '1d' ],
			[ 'in' => ['value' => '86461', 'units' => 's'],			'out' => '1d 1m' ],
			[ 'in' => ['value' => '90001', 'units' => 's'],			'out' => '1d 1h' ],
			[ 'in' => ['value' => '90061', 'units' => 's'],			'out' => '1d 1h 1m' ],
			[ 'in' => ['value' => '604800', 'units' => 's'],		'out' => '7d' ],
			[ 'in' => ['value' => '2592061', 'units' => 's'],		'out' => '1M' ],
			[ 'in' => ['value' => '2595661', 'units' => 's'],		'out' => '1M 1h' ],
			[ 'in' => ['value' => '2678461', 'units' => 's'],		'out' => '1M 1d' ],
			[ 'in' => ['value' => '2682061', 'units' => 's'],		'out' => '1M 1d 1h' ],
			[ 'in' => ['value' => '31539661', 'units' => 's'],		'out' => '1y' ],
			[ 'in' => ['value' => '31626061', 'units' => 's'],		'out' => '1y 1d' ],
			[ 'in' => ['value' => '34131661', 'units' => 's'],		'out' => '1y 1M' ],
			[ 'in' => ['value' => '34218061', 'units' => 's'],		'out' => '1y 1M 1d' ],

			// Time units with decimals (convertUnitsSWithDecimals).

			[ 'in' => ['value' => '1', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],				'out' => '1s' ],
			[ 'in' => ['value' => '1', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],				'out' => '1.0000s' ],
			[ 'in' => ['value' => '0.0012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],		'out' => '1.2345ms' ],
			[ 'in' => ['value' => '0.0012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '1.2345ms' ],
			[ 'in' => ['value' => '0.00012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],	'out' => '0.1235ms' ],
			[ 'in' => ['value' => '0.00012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '0.1235ms' ],
			[ 'in' => ['value' => '0.00012', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],		'out' => '0.12ms' ],
			[ 'in' => ['value' => '0.00012', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '0.1200ms' ],
			[ 'in' => ['value' => '60', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],			'out' => '1m' ],
			[ 'in' => ['value' => '60', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],				'out' => '1.0000m' ],
			[ 'in' => ['value' => '90', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],			'out' => '1.5m' ],
			[ 'in' => ['value' => '90', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],				'out' => '1.5000m' ],
			[ 'in' => ['value' => '5400', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],			'out' => '1.5h' ],
			[ 'in' => ['value' => '5400', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],			'out' => '1.5000h' ],
			[ 'in' => ['value' => '129600', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],		'out' => '1.5d' ],
			[ 'in' => ['value' => '129600', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],			'out' => '1.5000d' ],
			[ 'in' => ['value' => '3888000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],		'out' => '1.5M' ],
			[ 'in' => ['value' => '3888000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '1.5000M' ],
			[ 'in' => ['value' => '47304000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],		'out' => '1.5y' ],
			[ 'in' => ['value' => '47304000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '1.5000y' ],
			[ 'in' => ['value' => '3153600000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false],	'out' => '100y' ],
			[ 'in' => ['value' => '3153600000', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true],		'out' => '100.0000y' ],

			// Time units with decimals (convertUnitsSWithDecimals), ignore milliseconds.

			[ 'in' => ['value' => '0.0012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false, 'ignore_milliseconds' => true],	'out' => '0.001235s' ],
			[ 'in' => ['value' => '0.0012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true, 'ignore_milliseconds' => true],	'out' => '1.2350E-3s' ],
			[ 'in' => ['value' => '0.00012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false, 'ignore_milliseconds' => true],	'out' => '0.0001235s' ],
			[ 'in' => ['value' => '0.00012345', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true, 'ignore_milliseconds' => true],	'out' => '1.2350E-4s' ],
			[ 'in' => ['value' => '0.00012', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false, 'ignore_milliseconds' => true],	'out' => '0.00012s' ],
			[ 'in' => ['value' => '0.00012', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true, 'ignore_milliseconds' => true],		'out' => '1.2000E-4s' ],
			[ 'in' => ['value' => '1e-100', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false, 'ignore_milliseconds' => false],	'out' => '1E-97ms' ],
			[ 'in' => ['value' => '1e-100', 'units' => 's', 'decimals' => 4, 'decimals_exact' => false, 'ignore_milliseconds' => true],		'out' => '1E-100s' ],
			[ 'in' => ['value' => '1e-100', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true, 'ignore_milliseconds' => false],		'out' => '1.0000E-97ms' ],
			[ 'in' => ['value' => '1e-100', 'units' => 's', 'decimals' => 4, 'decimals_exact' => true, 'ignore_milliseconds' => true],		'out' => '1.0000E-100s' ],

			// Hardcoded blacklisted units.

			[ 'in' => ['value' => '0', 'units' => '%'],				'out' => '0 %' ],
			[ 'in' => ['value' => '12.34', 'units' => '%'],			'out' => '12.34 %' ],
			[ 'in' => ['value' => '1000000', 'units' => '%'],		'out' => '1000000 %' ],
			[ 'in' => ['value' => '0', 'units' => 'ms'],			'out' => '0 ms' ],
			[ 'in' => ['value' => '1000000', 'units' => 'ms'],		'out' => '1000000 ms' ],
			[ 'in' => ['value' => '0', 'units' => 'rpm'],			'out' => '0 rpm' ],
			[ 'in' => ['value' => '1000000', 'units' => 'rpm'],		'out' => '1000000 rpm' ],
			[ 'in' => ['value' => '0', 'units' => 'RPM'],			'out' => '0 RPM' ],
			[ 'in' => ['value' => '1000000', 'units' => 'RPM'],		'out' => '1000000 RPM' ],

			// Blacklisted units.

			[ 'in' => ['value' => '0', 'units' => '!'],				'out' => '0' ],
			[ 'in' => ['value' => '0', 'units' => '!!'],			'out' => '0 !' ],
			[ 'in' => ['value' => '1000', 'units' => '!'],			'out' => '1000' ],
			[ 'in' => ['value' => '1000', 'units' => '!!'],			'out' => '1000 !' ],
			[ 'in' => ['value' => '0', 'units' => '!U'],			'out' => '0 U' ],
			[ 'in' => ['value' => '12.34', 'units' => '!U'],		'out' => '12.34 U' ],
			[ 'in' => ['value' => '1000.23', 'units' => '!U'],		'out' => '1000.23 U' ],
			[ 'in' => ['value' => '-1000', 'units' => '!U'],		'out' => '-1000 U' ],
			[ 'in' => ['value' => '1000', 'units' => '!%'],			'out' => '1000 %' ],
			[ 'in' => ['value' => '1000', 'units' => '!ms'],		'out' => '1000 ms' ],
			[ 'in' => ['value' => '1000', 'units' => '!rpm'],		'out' => '1000 rpm' ],
			[ 'in' => ['value' => '1000', 'units' => '!RPM'],		'out' => '1000 RPM' ],
			[ 'in' => ['value' => '1000', 'units' => '!B'],			'out' => '1000 B' ],
			[ 'in' => ['value' => '1000', 'units' => '!Bps'],		'out' => '1000 Bps' ],
			[ 'in' => ['value' => '1000', 'units' => '!b'],			'out' => '1000 b' ],
			[ 'in' => ['value' => '1000', 'units' => '!bps'],		'out' => '1000 bps' ],

			// Special processing units.

			[ 'in' => ['value' => '0', 'units' => 'B'],				'out' => '0 B' ],
			[ 'in' => ['value' => '10', 'units' => 'B'],			'out' => '10 B' ],
			[ 'in' => ['value' => '1000', 'units' => 'B'],			'out' => '1000 B' ],
			[ 'in' => ['value' => '1001', 'units' => 'B'],			'out' => '1001 B' ],
			[ 'in' => ['value' => '1024', 'units' => 'B'],			'out' => '1 KB' ],
			[ 'in' => ['value' => '2048', 'units' => 'B'],			'out' => '2 KB' ],
			[ 'in' => ['value' => '0', 'units' => 'unixtime'],		'out' => 'Never' ],
			[ 'in' => ['value' => '1', 'units' => 'unixtime'],		'out' => '1970-01-01 12:00:01 AM' ],
			[ 'in' => ['value' => '0', 'units' => 'uptime'],		'out' => '00:00:00' ],
			[ 'in' => ['value' => '10000', 'units' => 'uptime'],	'out' => '02:46:40' ],
			[ 'in' => ['value' => '10000000', 'units' => 'uptime'],	'out' => '115 days, 17:46:40' ],
			[ 'in' => ['value' => '0', 'units' => 's'],				'out' => '0' ],
			[ 'in' => ['value' => '1', 'units' => 's'],				'out' => '1s' ],
			[ 'in' => ['value' => '61', 'units' => 's'],			'out' => '1m 1s' ],
			[ 'in' => ['value' => '-61', 'units' => 's'],			'out' => '-1m 1s' ],
			[ 'in' => ['value' => '3601', 'units' => 's'],			'out' => '1h 1s' ],
			[ 'in' => ['value' => '1000000000', 'units' => 's'],	'out' => '31y 8M 19d' ],
			[ 'in' => ['value' => '1000000000', 'units' => '!s'],	'out' => '1000000000 s' ],

			// ITEM_CONVERT_NO_UNITS and ITEM_CONVERT_WITH_UNITS.

			[ 'in' => ['value' => '0.000002', 'convert' => ITEM_CONVERT_WITH_UNITS],						'out' => '0.000002' ],
			[ 'in' => ['value' => '0.000002', 'convert' => ITEM_CONVERT_NO_UNITS],							'out' => '0.000002' ],
			[ 'in' => ['value' => '0.000002', 'units' => 'U', 'convert' => ITEM_CONVERT_WITH_UNITS],		'out' => '0.000002 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => 'U', 'convert' => ITEM_CONVERT_NO_UNITS],			'out' => '0.000002 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => '!U', 'convert' => ITEM_CONVERT_WITH_UNITS],		'out' => '0.000002 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => '!U', 'convert' => ITEM_CONVERT_NO_UNITS],			'out' => '0.000002 U' ],
			[ 'in' => ['value' => '10000000', 'units' => '!uptime', 'convert' => ITEM_CONVERT_WITH_UNITS],	'out' => '10000000 uptime' ],
			[ 'in' => ['value' => '10000000', 'units' => '!uptime', 'convert' => ITEM_CONVERT_NO_UNITS],	'out' => '10000000 uptime' ],

			// Disabled scientific notation of small numbers.

			[ 'in' => ['value' => '0', 'units' => 'B', 'small_scientific' => false],									'out' => '0 B' ],
			[ 'in' => ['value' => '1.5E+100', 'units' => 'B', 'decimals_exact' => false, 'small_scientific' => false],	'out' => '1.24E+76 YB' ],
			[ 'in' => ['value' => '1.5E+100', 'units' => 'B', 'decimals_exact' => true, 'small_scientific' => false],	'out' => '1.24E+76 YB' ],
			[ 'in' => ['value' => '1.5E-20', 'units' => 'B', 'decimals_exact' => false, 'small_scientific' => false],	'out' => '0.000000000000000000015 B' ],
			[ 'in' => ['value' => '1.5E-20', 'units' => 'B', 'decimals_exact' => true, 'small_scientific' => false],	'out' => '0.0000 B' ]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test($in, $out) {
		$result = call_user_func('convertUnits', $in);

		$this->assertSame($out, $result);
	}
}
