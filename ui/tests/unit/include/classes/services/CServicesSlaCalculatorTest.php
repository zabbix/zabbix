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

class CServicesSlaCalculatorTest extends TestCase {

	protected $defaultTimezone;

	protected function setUp(): void {
		$this->defaultTimezone = date_default_timezone_get();
		date_default_timezone_set('Europe/Riga');
	}

	public function dataProviderCalculateSla() {
		return [
			[
				[
					[
						'clock' => strtotime('30 March 2014 3:00'),
						'value' => 3,
						'servicealarmid' => 1
					],
					[
						'clock' => strtotime('30 March 2014 6:00'),
						'value' => 0,
						'servicealarmid' => 2
					]
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
					'problem' => 4.545454545454546,
					'ok' => 95.45454545454545
				]
			],
			[
				[
					[
						'clock' => strtotime('30 March 2014 3:00'),
						'value' => 3,
						'servicealarmid' => 1
					],
					[
						'clock' => strtotime('30 March 2014 6:00'),
						'value' => 0,
						'servicealarmid' => 2
					]
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
					'problem' => 0.14084507042253522,
					'ok' => 99.85915492957747
				]
			],
			[
				[],
				[
					[
						'type' => SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
						'ts_from' => strtotime('15 August 2019 10:00'),
						'ts_to' => strtotime('15 August 2019 10:20')
					]
				],
				strtotime('15 August 2019 10:10'),
				strtotime('15 August 2019 10:30'),
				5,
				[
					'dt' => [
						'problemTime' => 0,
						'okTime' => 600
					],
					'ut' => [
						'problemTime' => 600,
						'okTime' => 0
					],
					'problemTime' => 600,
					'okTime' => 0,
					'downtimeTime' => 600,
					'problem' => 100,
					'ok' => 0
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderCalculateSla
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

	protected function tearDown(): void {
		date_default_timezone_set($this->defaultTimezone);
	}


}
