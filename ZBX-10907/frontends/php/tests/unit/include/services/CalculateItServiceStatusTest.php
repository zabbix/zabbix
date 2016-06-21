<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CalculateItServiceStatusTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return [
			// single service without an algorithm
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_NONE, 'triggerid' => 0],
				],
				[],
				[],
				[
					0 => SERVICE_STATUS_OK
				]
			],

			// service with SLA calculation but no trigger
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0]
				],
				[],
				[],
				[
					0 => SERVICE_STATUS_OK
				]
			],

			// max ok
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 1],
					2 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 2],
				],
				[
					0 => [1, 2]
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_FALSE),
					2 => $this->createTrigger(TRIGGER_VALUE_FALSE),
				],
				[
					0 => SERVICE_STATUS_OK,
					1 => SERVICE_STATUS_OK,
					2 => SERVICE_STATUS_OK
				]
			],

			// min ok
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MIN, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_MIN, 'triggerid' => 1],
					2 => ['algorithm' => SERVICE_ALGORITHM_MIN, 'triggerid' => 2],
				],
				[
					0 => [1, 2]
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_FALSE),
					2 => $this->createTrigger(TRIGGER_VALUE_FALSE),
				],
				[
					0 => SERVICE_STATUS_OK,
					1 => SERVICE_STATUS_OK,
					2 => SERVICE_STATUS_OK
				]
			],

			// max problem
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 1],
					2 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 2],
					3 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 3],
				],
				[
					0 => [1, 2, 3]
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_TRUE),
					2 => $this->createTrigger(TRIGGER_VALUE_TRUE, TRIGGER_SEVERITY_DISASTER),
					3 => $this->createTrigger(TRIGGER_VALUE_FALSE),
				],
				[
					0 => TRIGGER_SEVERITY_DISASTER,
					1 => TRIGGER_SEVERITY_AVERAGE,
					2 => TRIGGER_SEVERITY_DISASTER,
					3 => SERVICE_STATUS_OK
				]
			],

			// min problem
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MIN, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 1],
					2 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 2],
					3 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 3],
				],
				[
					0 => [1, 2, 3]
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_TRUE),
					2 => $this->createTrigger(TRIGGER_VALUE_TRUE, TRIGGER_SEVERITY_DISASTER),
					3 => $this->createTrigger(TRIGGER_VALUE_TRUE),
				],
				[
					0 => TRIGGER_SEVERITY_DISASTER,
					1 => TRIGGER_SEVERITY_AVERAGE,
					2 => TRIGGER_SEVERITY_DISASTER,
					3 => TRIGGER_SEVERITY_AVERAGE
				]
			],

			// graph services with soft links
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					2 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					3 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 1],
				],
				[
					0 => [1, 2],
					1 => [3],
					2 => [3],
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_TRUE),
				],
				[
					0 => TRIGGER_SEVERITY_AVERAGE,
					1 => TRIGGER_SEVERITY_AVERAGE,
					2 => TRIGGER_SEVERITY_AVERAGE,
					3 => TRIGGER_SEVERITY_AVERAGE,
				]
			],

			// a service branch with a disabled service in the middle
			[
				[
					0 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 0],
					1 => ['algorithm' => SERVICE_ALGORITHM_NONE, 'triggerid' => 0],
					2 => ['algorithm' => SERVICE_ALGORITHM_MAX, 'triggerid' => 1],
				],
				[
					0 => [1],
					1 => [2]
				],
				[
					1 => $this->createTrigger(TRIGGER_VALUE_TRUE),
				],
				[
					0 => SERVICE_STATUS_OK,
					1 => SERVICE_STATUS_OK,
					2 => TRIGGER_SEVERITY_AVERAGE,
				]
			],
		];
	}

	/**
	 * @dataProvider provider
	 *
	 * @param $services
	 * @param $serviceLinks
	 * @param $triggers
	 * @param $expectedStatuses
	 */
	public function test($services, $serviceLinks, $triggers, $expectedStatuses) {
		calculateItServiceStatus(0, $serviceLinks, $services, $triggers);

		foreach ($services as $serviceId => $service) {
			$this->assertEquals($expectedStatuses[$serviceId], $service['newStatus']);
		}
	}

	public function createTrigger($value, $severity = TRIGGER_SEVERITY_AVERAGE) {
		return [
			'status' => TRIGGER_STATUS_ENABLED,
			'value' => $value,
			'priority' => $severity
		];
	}

}
