<?php declare(strict_types = 0);
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

class function_relativeDateToTextTest extends TestCase {
	protected $tz;

	protected function setUp(): void {
		$this->tz = date_default_timezone_get();
		date_default_timezone_set('Europe/Riga');
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->tz);
	}

	public static function provider() {
		return [
			['params' => ['now-1d/d', 'now-1d/d'],		'expected' => 'Yesterday'],
			['params' => ['now-2d/d', 'now-2d/d'],		'expected' => 'Day before yesterday'],
			['params' => ['now-1w/d', 'now-1w/d'],		'expected' => 'This day last week'],
			['params' => ['now-1w/w', 'now-1w/w'],		'expected' => 'Previous week'],
			['params' => ['now-1M/M', 'now-1M/M'],		'expected' => 'Previous month'],
			['params' => ['now-1y/y', 'now-1y/y'],		'expected' => 'Previous year'],
			['params' => ['now/d', 'now/d'],			'expected' => 'Today'],
			['params' => ['now/d', 'now'],				'expected' => 'Today so far'],
			['params' => ['now/w', 'now/w'],			'expected' => 'This week'],
			['params' => ['now/w', 'now'],				'expected' => 'This week so far'],
			['params' => ['now/M', 'now/M'],			'expected' => 'This month'],
			['params' => ['now/M', 'now'],				'expected' => 'This month so far'],
			['params' => ['now/y', 'now/y'],			'expected' => 'This year'],
			['params' => ['now/y', 'now'],				'expected' => 'This year so far'],
			['params' => ['now-1', 'now'],				'expected' => 'Last 1 second'],
			['params' => ['now-5', 'now'],				'expected' => 'Last 5 seconds'],
			['params' => ['now-55s', 'now'],			'expected' => 'Last 55 seconds'],
			['params' => ['now-60s', 'now'],			'expected' => 'Last 1 minute'],
			['params' => ['now-600s', 'now'],			'expected' => 'Last 10 minutes'],
			['params' => ['now-3600s', 'now'],			'expected' => 'Last 1 hour'],
			['params' => ['now-3601s', 'now'],			'expected' => 'Last 3601 seconds'],
			['params' => ['now-86400s', 'now'],			'expected' => 'Last 1 day'],
			['params' => ['now-59m', 'now'],			'expected' => 'Last 59 minutes'],
			['params' => ['now-60m', 'now'],			'expected' => 'Last 1 hour'],
			['params' => ['now-77m', 'now'],			'expected' => 'Last 77 minutes'],
			['params' => ['now-600m', 'now'],			'expected' => 'Last 10 hours'],
			['params' => ['now-3600m', 'now'],			'expected' => 'Last 60 hours'],
			['params' => ['now-1440m', 'now'],			'expected' => 'Last 1 day'],
			['params' => ['now-23h', 'now'],			'expected' => 'Last 23 hours'],
			['params' => ['now-24h', 'now'],			'expected' => 'Last 1 day'],
			['params' => ['now-77h', 'now'],			'expected' => 'Last 77 hours'],
			['params' => ['now-1d', 'now'],				'expected' => 'Last 1 day'],
			['params' => ['now-3d', 'now'],				'expected' => 'Last 3 days'],
			['params' => ['now-1M', 'now'],				'expected' => 'Last 1 month'],
			['params' => ['now-5M', 'now'],				'expected' => 'Last 5 months'],
			['params' => ['now-1y', 'now'],				'expected' => 'Last 1 year'],
			['params' => ['now-3y', 'now'],				'expected' => 'Last 3 years'],
			['params' => ['now+5m', 'now'],				'expected' => 'now+5m – now'],
			['params' => ['now', 'now'],				'expected' => 'now – now'],
			['params' => ['now/m', 'now/m'],			'expected' => 'now/m – now/m'],
			['params' => ['now/h', 'now/h'],			'expected' => 'now/h – now/h'],
			['params' => ['now', 'now/d'],				'expected' => 'now – now/d'],
			['params' => ['now/d', 'now/w'],			'expected' => 'now/d – now/w'],
			['params' => ['now/w', 'now/M'],			'expected' => 'now/w – now/M'],
			['params' => ['now/M', 'now/y'],			'expected' => 'now/M – now/y'],
			['params' => ['now/y', 'now/d'],			'expected' => 'now/y – now/d'],
			['params' => ['now/d-3d', 'now/M-1M'],		'expected' => 'now/d-3d – now/M-1M'],
			['params' => ['now-3d/d', 'now-2M/M'],		'expected' => 'now-3d/d – now-2M/M'],
			['params' => ['now-3h/d', 'now'],			'expected' => 'now-3h/d – now'],
			['params' => ['now-3w/M', 'now+1M/M'],		'expected' => 'now-3w/M – now+1M/M']
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test($params, $expected) {
		$this->assertSame($expected, call_user_func_array('relativeDateToText', $params));
	}
}
