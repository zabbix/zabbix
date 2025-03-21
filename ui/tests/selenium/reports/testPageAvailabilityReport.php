<?php
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

require_once __DIR__.'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup events
 */
class testPageAvailabilityReport extends CLegacyWebTest {

	/**
	 * SLA calculated events data in format: triggerid, starttime, duration.
	 * Triggers created via import of data/data_test.sql
	 * @var array
	 */
	public static $SLA_events = [
		[100001, '01.01.2016 00:00:00', '+1 second'],
		[100001, '01.01.2017 22:59:00', '+1 hour 59 second'],
		[100001, '02.01.2017 22:50:00', '+1 hour 11 minutes'],
		[100002, '01.01.2017 22:59:10', '+1 hour 5 minutes 59 second'],
		[100002, '10.10.2017 00:00:00', '+1 second'],
		[100002, '31.12.2017 22:00:00', '+1 hour'],
		[100003, '31.12.2017 23:00:00', '+1 hour']
	];

	/**
	 * Data provider for testPageAvailabilityReportSLA. Array have following schema:
	 * - SLA availability report filter start time
	 * - SLA availability report filter end time
	 * - SLA availability report result table row values. Only 3 first cells of every row are defined.
	 *
	 * @return array
	 */
	public function dataProviderSLA() {
		return [
			['01.01.2016 00:00:00', '30.01.2016 00:00:00', [
				['A trigger', '', '100.0000%'],
				['B trigger', '', '100.0000%'],
				['C trigger', '', '100.0000%']
			]],
			['01.01.2016 00:00:00', '20.01.2016 00:00:00', [
				['A trigger', '0.0001%', '99.9999%'],
				['B trigger', '', '100.0000%'],
				['C trigger', '', '100.0000%']
			]],
			['01.01.2017 00:00:00', '02.01.2017 23:59:00', [
				['A trigger', '4.5149%', '95.4851%'],
				['B trigger', '2.2919%', '97.7081%'],
				['C trigger', '', '100.0000%']
			]],
			['01.01.2017 00:00:00', '01.01.2018 00:00:00', [
				['A trigger', '0.0251%', '99.9749%'],
				['B trigger', '0.0240%', '99.9760%'],
				['C trigger', '0.0114%', '99.9886%']
			]],
			['31.12.2017 22:30:00', '01.01.2018 00:30:00', [
				['A trigger', '', '100.0000%'],
				['B trigger', '25.0000%', '75.0000%'],
				['C trigger', '50.0000%', '50.0000%']
			]],
			['31.12.2017 22:30:00', '01.01.2018 00:30:00', [
				['A trigger', '', '100.0000%'],
				['B trigger', '25.0000%', '75.0000%'],
				['C trigger', '50.0000%', '50.0000%']
			]]
		];
	}

	/**
	 * Initialize test data for dataProviderSLA.
	 */
	public function testInitializeTestData() {
		// Generate events data for 'SLA host' trigger items.
		$eventid = get_dbid('events', 'eventid');
		$start_time = 'INSERT INTO events (eventid, objectid, clock, value) VALUES (%1$d, %2$d, %3$d, '.TRIGGER_VALUE_TRUE.')';
		$end_time = 'INSERT INTO events (eventid, objectid, clock, value) VALUES (%1$d, %2$d, %3$d, '.TRIGGER_VALUE_FALSE.')';

		foreach (self::$SLA_events as $event) {
			array_unshift($event, $eventid);
			// Event start clock.
			$event[2] = strtotime($event[2]);
			DBexecute(vsprintf($start_time, $event));

			// Event end clock.
			$event[0]++;
			$event[2] = strtotime($event[3], $event[2]);
			DBexecute(vsprintf($end_time, $event));

			$eventid = $eventid + 2;
		}
	}

	/**
	 * @depends testInitializeTestData
	 * @dataProvider dataProviderSLA
	 */
	public function testPageAvailabilityReportSLA($start_time, $end_time, $sla_item_values) {
		$args = [
			'from' => date('Y-m-d H:i:s', strtotime($start_time)),
			'to' => date('Y-m-d H:i:s', strtotime($end_time)),
			'filter_hosts' => [50014]
		];

		$this->zbxTestLogin('zabbix.php?action=availabilityreport.list&'.http_build_query($args));
		$table_rows =$this->webDriver->findElements(WebDriverBy::xpath('//table[@class="list-table"]/tbody/tr'));
		if (!$table_rows) {
			$this->fail("Failed to get SLA reports table.");
		}

		foreach ($table_rows as $row) {
			$cells = $row->findElements(WebDriverBy::xpath('td'));
			$cells_values = [];

			foreach ($cells as $cell) {
				$cells_values[] = $cell->getText();
			}

			// Check only cells 2,3,4 in every row: Label, Problem state value, Ok state value.
			$this->assertContains(array_slice($cells_values, 1, 3), $sla_item_values);
		}
	}

	public function testPageAvailabilityReport_ByHost_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=availabilityreport.list&mode=0');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckHeader('Availability report');
		$this->zbxTestTextPresent('Mode');
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Problems', 'Ok', 'Graph']);
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByHost_NoHostNames() {
		$this->zbxTestLogin('zabbix.php?action=availabilityreport.list&mode=0');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageAvailabilityReport_ByTriggerTemplate_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=availabilityreport.list&mode=1');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckHeader('Availability report');
		$this->zbxTestTextPresent('Mode');
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Problems', 'Ok', 'Graph']);
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByTriggerTemplate_NoHostNames() {
		$this->zbxTestLogin('zabbix.php?action=availabilityreport.list&mode=1');
		$this->zbxTestCheckNoRealHostnames();
	}
}
