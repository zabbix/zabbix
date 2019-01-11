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


class CalculateItServiceStatusByTriggerTest extends PHPUnit_Framework_TestCase {

	public function provider() {
		return [
			[TRIGGER_STATUS_ENABLED, TRIGGER_VALUE_FALSE, TRIGGER_SEVERITY_DISASTER, SERVICE_STATUS_OK],
			[TRIGGER_STATUS_DISABLED, TRIGGER_VALUE_TRUE, TRIGGER_SEVERITY_DISASTER, SERVICE_STATUS_OK],

			[TRIGGER_STATUS_ENABLED, TRIGGER_VALUE_TRUE, TRIGGER_SEVERITY_DISASTER, TRIGGER_SEVERITY_DISASTER],
			[TRIGGER_STATUS_ENABLED, TRIGGER_VALUE_TRUE, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_AVERAGE],
		];
	}

	/**
	 * @dataProvider provider
	 *
	 * @param $triggerStatus
	 * @param $triggerValue
	 * @param $triggerPriority
	 * @param $expectedStatus
	 */
	public function test($triggerStatus, $triggerValue, $triggerPriority, $expectedStatus) {
		$status = calculateItServiceStatusByTrigger($triggerStatus, $triggerValue, $triggerPriority);
		$this->assertEquals($expectedStatus, $status);
	}

}
