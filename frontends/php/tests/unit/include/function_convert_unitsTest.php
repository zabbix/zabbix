<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../../include/translateDefines.inc.php';

class function_convert_units extends PHPUnit_Framework_TestCase {
	protected $defaultTimezone;

	public function setUp() {
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	public function tearDown() {
		date_default_timezone_set($this->defaultTimezone);
	}

	public static function provider() {
		return [
			/* No units */
			[ 'in' => ['value' => ''],								'out'  => '' ],
			[ 'in' => ['value' => 'TEXT'],							'out'  => 'TEXT' ],
			[ 'in' => ['value' => '10.99'],							'out'  => '10.99' ],
			[ 'in' => ['value' => '10'],							'out'  => '10' ],
			[ 'in' => ['value' => '-10'],							'out'  => '-10' ],
			[ 'in' => ['value' => '-10.99'],						'out'  => '-10.99' ],
			[ 'in' => ['value' => '100000000000'],					'out'  => '100000000000' ],
			[ 'in' => ['value' => '1.00000000001'],					'out'  => '1' ],
			[ 'in' => ['value' => '1.01'],							'out'  => '1.01' ],
			/* Normal unit */
			[ 'in' => ['value' => '', 'units' => ''],				'out'  => '' ],
			[ 'in' => ['value' => '0', 'units' => ''],				'out'  => '0' ],
			[ 'in' => ['value' => '0', 'units' => 'U'],				'out'  => '0 U' ],
			[ 'in' => ['value' => '10', 'units' => 'U'],			'out'  => '10 U' ],
			[ 'in' => ['value' => '-10', 'units' => 'U'],			'out'  => '-10 U' ],
			[ 'in' => ['value' => '10.99', 'units' => 'U'],			'out'  => '10.99 U' ],
			[ 'in' => ['value' => '1000', 'units' => 'U'],			'out'  => '1 KU' ],
			[ 'in' => ['value' => '-1000', 'units' => 'U'],			'out'  => '-1 KU' ],
			[ 'in' => ['value' => '1001', 'units' => 'U'],			'out'  => '1 KU' ],
			[ 'in' => ['value' => '1024', 'units' => 'U'],			'out'  => '1.02 KU' ],
			[ 'in' => ['value' => '2048', 'units' => 'U'],			'out'  => '2.05 KU' ],
			[ 'in' => ['value' => '1000000', 'units' => 'U'],		'out'  => '1 MU' ],
			[ 'in' => ['value' => '1000000000', 'units' => 'U'],	'out'  => '1 GU' ],
			[ 'in' => ['value' => '1000000000000', 'units' => 'U'],	'out'  => '1 TU' ],
			[ 'in' => ['value' => '1000000000000000', 'units' => 'U'],	'out'  => '1 PU' ],
			[ 'in' => ['value' => '1000000000000000000', 'units' => 'U'],	'out'  => '1 EU' ],
			[ 'in' => ['value' => '1000000000000000000000', 'units' => 'U'],	'out'  => '1 ZU' ],
			[ 'in' => ['value' => '1000000000000000000000000', 'units' => 'U'],	'out'  => '1 YU' ],
			[ 'in' => ['value' => '1010000000000000000000000', 'units' => 'U'],	'out'  => '1.01 YU' ],
			/* Hardcoded blacklisted units */
			[ 'in' => ['value' => '0', 'units' => '%'],				'out'  => '0 %' ],
			[ 'in' => ['value' => '12.34', 'units' => '%'],			'out'  => '12.34 %' ],
			[ 'in' => ['value' => '1000000', 'units' => '%'],		'out'  => '1000000 %' ],
			[ 'in' => ['value' => '0', 'units' => 'ms'],			'out'  => '0 ms' ],
			[ 'in' => ['value' => '1000000', 'units' => 'ms'],		'out'  => '1000000 ms' ],
			[ 'in' => ['value' => '0', 'units' => 'rpm'],			'out'  => '0 rpm' ],
			[ 'in' => ['value' => '1000000', 'units' => 'rpm'],		'out'  => '1000000 rpm' ],
			[ 'in' => ['value' => '0', 'units' => 'RPM'],			'out'  => '0 RPM' ],
			[ 'in' => ['value' => '1000000', 'units' => 'RPM'],		'out'  => '1000000 RPM' ],
			/* Blacklisted units */
			[ 'in' => ['value' => '0', 'units' => '!'],				'out'  => '0' ],
			[ 'in' => ['value' => '0', 'units' => '!!'],			'out'  => '0 !' ],
			[ 'in' => ['value' => '1000', 'units' => '!'],			'out'  => '1000' ],
			[ 'in' => ['value' => '1000', 'units' => '!!'],			'out'  => '1000 !' ],
			[ 'in' => ['value' => '0', 'units' => '!U'],			'out'  => '0 U' ],
			[ 'in' => ['value' => '12.34', 'units' => '!U'],		'out'  => '12.34 U' ],
			[ 'in' => ['value' => '1000.23', 'units' => '!U'],		'out'  => '1000.23 U' ],
			[ 'in' => ['value' => '-1000', 'units' => '!U'],		'out'  => '-1000 U' ],
			[ 'in' => ['value' => '1000', 'units' => '!%'],			'out'  => '1000 %' ],
			[ 'in' => ['value' => '1000', 'units' => '!ms'],		'out'  => '1000 ms' ],
			[ 'in' => ['value' => '1000', 'units' => '!rpm'],		'out'  => '1000 rpm' ],
			[ 'in' => ['value' => '1000', 'units' => '!RPM'],		'out'  => '1000 RPM' ],
			[ 'in' => ['value' => '1000', 'units' => '!B'],			'out'  => '1000 B' ],
			[ 'in' => ['value' => '1000', 'units' => '!Bps'],		'out'  => '1000 Bps' ],
			[ 'in' => ['value' => '1000', 'units' => '!b'],			'out'  => '1000 b' ],
			[ 'in' => ['value' => '1000', 'units' => '!bps'],		'out'  => '1000 bps' ],
			/* Special processing units */
			[ 'in' => ['value' => '0', 'units' => 'B'],				'out'  => '0 B' ],
			[ 'in' => ['value' => '10', 'units' => 'B'],			'out'  => '10 B' ],
			[ 'in' => ['value' => '1000', 'units' => 'B'],			'out'  => '1000 B' ],
			[ 'in' => ['value' => '1001', 'units' => 'B'],			'out'  => '1001 B' ],
			[ 'in' => ['value' => '1024', 'units' => 'B'],			'out'  => '1 KB' ],
			[ 'in' => ['value' => '2048', 'units' => 'B'],			'out'  => '2 KB' ],
			[ 'in' => ['value' => '0', 'units' => 'unixtime'],		'out'  => 'Never' ],
			[ 'in' => ['value' => '1', 'units' => 'unixtime'],		'out'  => '1970-01-01 00:00:01' ],
			[ 'in' => ['value' => '0', 'units' => 'uptime'],		'out'  => '00:00:00' ],
			[ 'in' => ['value' => '10000', 'units' => 'uptime'],	'out'  => '02:46:40' ],
			[ 'in' => ['value' => '10000000', 'units' => 'uptime'],	'out'  => '115 days, 17:46:40' ],
			[ 'in' => ['value' => '0', 'units' => 's'],				'out'  => '0' ],
			[ 'in' => ['value' => '1', 'units' => 's'],				'out'  => '1s' ],
			[ 'in' => ['value' => '61', 'units' => 's'],			'out'  => '1m 1s' ],
			[ 'in' => ['value' => '3601', 'units' => 's'],			'out'  => '1h 1s' ],
			[ 'in' => ['value' => '1000000000', 'units' => 's'],	'out'  => '31y 8m 19d' ],
			[ 'in' => ['value' => '1000000000', 'units' => '!s'],	'out'  => '1000000000 s' ],
			/* ITEM_CONVERT_NO_UNITS and ITEM_CONVERT_WITH_UNITS */
			[ 'in' => ['value' => '0.000002', 'convert' => ITEM_CONVERT_WITH_UNITS],						'out'  => '0.000002' ],
			[ 'in' => ['value' => '0.000002', 'convert' => ITEM_CONVERT_NO_UNITS],							'out'  => '0' ],
			[ 'in' => ['value' => '0.000002', 'units' => 'U', 'convert' => ITEM_CONVERT_WITH_UNITS],		'out'  => '0 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => 'U', 'convert' => ITEM_CONVERT_NO_UNITS],			'out'  => '0 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => '!U', 'convert' => ITEM_CONVERT_WITH_UNITS],		'out'  => '0.000002 U' ],
			[ 'in' => ['value' => '0.000002', 'units' => '!U', 'convert' => ITEM_CONVERT_NO_UNITS],			'out'  => '0.000002 U' ],
			[ 'in' => ['value' => '10000000', 'units' => '!uptime', 'convert' => ITEM_CONVERT_WITH_UNITS],	'out'  => '10000000 uptime' ],
			[ 'in' => ['value' => '10000000', 'units' => '!uptime', 'convert' => ITEM_CONVERT_NO_UNITS],	'out'  => '10000000 uptime' ],
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test($in, $out) {
		$result = call_user_func('convert_units', $in);

		$this->assertSame($out, $result);
	}
}
