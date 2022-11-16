<?php
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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 *
 * @onBefore prepareTriggersData
 */
class testPageReportsTriggerTop extends CLegacyWebTest {

	/**
	 * Id of the host with problems.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Ids of the triggers for problems.
	 *
	 * @var array
	 */
	protected static $triggerids;

	/**
	 * Time when events were created.
	 *
	 * @var int
	 */
	protected static $time;
	protected static $one_year_ago_approx;
	protected static $two_years_ago_approx;
	protected static $three_months_ago_approx;
	protected static $two_months_ago_approx;

	public function prepareTriggersData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Reports Trigger']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Reports Trigger',
			'groups' => [['groupid' => $groupid]]
		]);

		$this->assertArrayHasKey('hostids', $hosts);
		self::$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char', 'log', 'unsigned'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => self::$hostid,
				'name' => $item,
				'key_' => $item,
				'type' => 2,
				'value_type' => $i
			];
		}

		$items = CDataHelper::call('item.create', $items_data);
		$this->assertArrayHasKey('itemids', $items);

		// Create triggers based on items.
		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem 1 year ago',
				'expression' => 'last(/Host for Reports Trigger/float)=0',
				'priority' => 0
			],
			[
				'description' => 'Problem 2 years ago',
				'expression' => 'last(/Host for Reports Trigger/char)=0',
				'priority' => 1
			],
			[
				'description' => 'Problem 3 months ago',
				'expression' => 'last(/Host for Reports Trigger/log)=0',
				'priority' => 2
			],
			[
				'description' => 'Problem 2 months ago',
				'expression' => 'last(/Host for Reports Trigger/unsigned)=0',
				'priority' => 3
			]
		]);

		$this->assertArrayHasKey('triggerids', $triggers);
		self::$triggerids = CDataHelper::getIds('description');

		self::$time = time();
		// Make timestamp a little less 1 year ago.
		self::$one_year_ago_approx = self::$time - 31556952;

		// Make timestamp a little less than 2 years ago.
		self::$two_years_ago_approx = self::$time - 62985600;

		// Make timestamp a little less than 2 months ago.
		self::$two_months_ago_approx = self::$time - 5097600;

		// Make timestamp a little less than 3 months ago.
		self::$three_months_ago_approx = self::$time - 7689600;

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005500, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 1 year ago']).', '.self::$one_year_ago_approx.', 0, 1, '.zbx_dbstr('Problem 1 year ago').', 0)'
		);

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005501, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 2 years ago']).', '.self::$two_years_ago_approx.', 0, 1, '.zbx_dbstr('Problem 2 years ago').', 1)'
		);

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005502, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 3 months ago']).', '.self::$three_months_ago_approx.', 0, 1, '.zbx_dbstr('Problem 3 months ago').', 2)'
		);

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005503, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 2 months ago']).', '.self::$two_months_ago_approx.', 0, 1, '.zbx_dbstr('Problem 2 months ago').', 3)'
		);

		// Create problems.
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005500, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 1 year ago']).', '.self::$one_year_ago_approx.', 0, '.zbx_dbstr('Problem 1 year ago').', 0)'
		);

		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005501, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 2 years ago']).', '.self::$two_years_ago_approx.', 0, '.zbx_dbstr('Problem 2 years ago').', 1)'
		);

		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005502, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 3 months ago']).', '.self::$three_months_ago_approx.', 0, '.zbx_dbstr('Problem 3 months ago').', 2)'
		);

		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005503, 0, 0, '.
				zbx_dbstr(self::$triggerids['Problem 2 months ago']).', '.self::$two_months_ago_approx.', 0, '.zbx_dbstr('Problem 2 months ago').', 3)'
		);

		// Change triggers' state to Problem.
		DBexecute('UPDATE triggers SET value = 1 WHERE description IN ('.zbx_dbstr('Problem 1 year ago').', '.
				zbx_dbstr('Problem 2 year ago').', '.zbx_dbstr('Problem 3 months ago').', '.zbx_dbstr('Problem 2 months ago').')'
		);
	}

	public function testPageReportsTriggerTop_FilterLayout() {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestTextPresent('Host groups', 'Hosts', 'Severity', 'Filter', 'From', 'Till');
		$this->zbxTestClickXpathWait('//button[text()="Reset"]');

		// Check unselected severities
		$severities = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		foreach ($severities as $severity) {
			$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
			$this->assertTrue($this->query('id', $severity_id)->waitUntilPresent()->one()->isSelected(false));
		}

		// Check closed filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertNotVisibleId('groupids_');

		// Check opened filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertVisibleId('groupids_');
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger'
					],
					'date' => [
						'from' => 'now-2y',
						'to' => 'now'
					],
					'result' => [
						'Problem 1 year ago',
						'Problem 2 months ago',
						'Problem 2 years ago',
						'Problem 3 months ago'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger'
					],
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger',
						'host' => 'Host for Reports Trigger'
					],
					'date' => [
						'relative' => true,
						// Time is now - 2 years exactly.
						'from' => 62985600
					],
					'result' => [
						'Problem 1 year ago',
						'Problem 2 months ago',
						'Problem 2 years ago',
						'Problem 3 months ago'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger',
						'host' => 'Host ZBX6663'
					],
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger',
						'host' => 'Host for Reports Trigger'
					],
					'date' => [
						'relative' => true,
						// Time around 1 year ago.
						'from' => 31556990,
						'to' => 31556900
					],
					'result' => [
						'Problem 1 year ago'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Group for Reports Trigger',
						'host' => 'Host for Reports Trigger'
					],
					'date' => [
						'relative' => true,
						// Less thant 2 month ago.
						'from' => 501120,
						'to' => 'now-1d/d'
					]
				]
			],
			[
				[
					'date' => [
						'relative' => true,
						// Time around 3 months ago.
						'from' => 7689700,
						'to' => 7689500
					],
					'result' => [
						'Problem 3 months ago'
					]
				]
			],
			[
				[
					'filter' => [
						'severities' => [
							'Warning',
							'High',
							'Disaster'
						]
					],
					'date' => [
						'from' => 'now-2y'
					],
					'result' => [
						'Problem 3 months ago'
					]
				]
			],
			[
				[
					'filter' => [
						'severities' => [
							'High',
							'Disaster'
						]
					],
					'date' => [
						'from' => 'now-2y'
					]
				]
			],
			[
				[
					'date' => [
						'relative' => true,
						// Time interval 3 - 2 months ago.
						'from' => 7872400,
						'to' => 5011200
					],
					'result' => [
						'Problem 2 months ago',
						'Problem 3 months ago'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageReportsTriggerTop_CheckFilter($data) {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestWaitForPageToLoad();

		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		if (array_key_exists('filter', $data)) {
			$filter = $data['filter'];

			if (array_key_exists('host_group', $filter)) {
				$filter_form->fill(['Host groups' => $filter['host_group']]);
			}

			if (array_key_exists('host', $filter)) {
				$filter_form->fill(['Hosts' => $filter['host']]);
			}

			if (array_key_exists('severities', $filter)) {
				foreach ($filter['severities'] as $severity) {
					$severity_id = $this->zbxTestGetAttributeValue('//label[text()="'.$severity.'"]', 'for');
					$this->zbxTestClick($severity_id);
				}
			}

			$this->zbxTestClickXpathWait('//button[@name="filter_set"][text()="Apply"]');
			$this->zbxTestWaitForPageToLoad();
		}

		// Fill in the date in filter.
		if (array_key_exists('date', $data)) {
			if (CTestArrayHelper::get($data['date'], 'relative')) {
				if (array_key_exists('from', $data['date'])) {
					$data['date']['from'] = date('Y-m-d H:i', self::$time - $data['date']['from']);
				}

				if (array_key_exists('to', $data['date']) && is_int($data['date']['to'])) {
					$data['date']['to'] = date('Y-m-d H:i', self::$time - $data['date']['to']);
				}

				array_shift($data['date']);
			}

			$this->zbxTestExpandFilterTab('Time');
			foreach ($data['date'] as $i => $full_date) {
				$this->zbxTestInputTypeOverwrite($i, $full_date);
			}
			// Wait till table id will be changed after filter apply.
			$tabel_id = $this->zbxTestGetAttributeValue('//table[@class="list-table"]', 'id');
			$this->zbxTestClickWait('apply');
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//table[@class="list-table"][not(@id="'.$tabel_id.'")]'));
			$this->zbxTestWaitForPageToLoad();
		}

		if (array_key_exists('result', $data)) {
			foreach ($data['result'] as $result) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//tbody//td[2]//a[text()="'.$result.'"]'));
				$this->zbxTestAssertElementPresentXpath('//tbody//td[2]//a[text()="'.$result.'"]');
			}
		}
		else {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//tr[@class="nothing-to-show"]'));
			$this->zbxTestAssertElementText('//tr[@class="nothing-to-show"]/td', 'No data found.');
		}
	}
}
