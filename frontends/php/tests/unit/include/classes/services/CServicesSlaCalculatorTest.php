<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CServicesSlaCalculatorTest extends PHPUnit_Framework_TestCase {

	protected $defaultTimezone;

	public function setUp() {
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('Europe/Riga');
	}

	public function testCalculateSlaProvider() {
		return [
			[
				[
					[
						'clock' => strtotime('30 March 2014 3:00'),
						'value' => 3
					],
					[
						'clock' => strtotime('30 March 2014 6:00'),
						'value' => 0
					],
				],
				[
					// downtime during DST
					[
						'type' => SERVICE_TIME_TYPE_DOWNTIME,
						'ts_from' => 10800,
						'ts_to' => 18000
					]
				],
				strtotime('30 March 2014 0:00'),
				strtotime('30 March 2014 24:00'),
				0,
				[
					'dt' => [
						'problemTime' => 3600,
						'okTime' => 0
					],
					'ut' => [
						'problemTime' => 3600,
						'okTime' => 75600
					],
					'problemTime' => 3600,
					'okTime' => 75600,
					'downtimeTime' => 3600,
					'problem' => 4.5454545454545,
					'ok' => 95.454545454545
				]
			],
			[
				[
					[
						'clock' => strtotime('30 March 2014 3:00'),
						'value' => 3
					],
					[
						'clock' => strtotime('30 March 2014 6:00'),
						'value' => 0
					],
				],
				[
					// downtime during DST
					[
						'type' => SERVICE_TIME_TYPE_DOWNTIME,
						'ts_from' => 10800,
						'ts_to' => 18000
					]
				],
				strtotime('1 March 2014 0:00'),
				strtotime('30 March 2014 24:00'),
				0,
				[
					'dt' => [
						'problemTime' => 3600,
						'okTime' => 28800
					],
					'ut' => [
						'problemTime' => 3600,
						'okTime' => 2552400
					],
					'problemTime' => 3600,
					'okTime' => 2552400,
					'downtimeTime' => 32400,
					'problem' => 0.14084507042254,
					'ok' => 99.859154929577
				]
			]
		];
	}

	/**
	 * @dataProvider testCalculateSlaProvider
	 *
	 * @param array $alarms
	 * @param array $times
	 * @param $periodStart
	 * @param $periodEnd
	 * @param $prevValue
	 * @param array $expectedResult
	 */
	public function testCalculateSla(array $alarms, array $times, $periodStart, $periodEnd, $prevValue, array $expectedResult) {
		$slaCalculator = new CServicesSlaCalculator();
		$result = $slaCalculator->calculateSla($alarms, $times, $periodStart, $periodEnd, $prevValue);

		$this->assertEquals($expectedResult, $result);

		// too little test cases
		$this->markTestIncomplete();
	}

	public function tearDown() {
		date_default_timezone_set($this->defaultTimezone);
	}


}
