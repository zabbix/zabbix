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

require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @dataSource TopHostsWidget, ItemValueWidget, DynamicItemWidgets, CopyWidgetsDashboards
 *
 * @backup dashboard, dashboard_user, dashboard_usrgrp
 */
class testDashboardsListPage extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	public function testDashboardsListPage_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->page->assertTitle('Dashboards');
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name'], $table->getHeadersText());

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check filter fields.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals(['Name', 'Show'], $filter_form->getLabels()->asText());
		foreach (['All', 'Created by me'] as $show_tag) {
			$this->assertTrue($filter_form->query('xpath://ul[@id="filter_show"]/li/label[text()="'.$show_tag.'"]')->exists());
		};

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($filter_form->query('button', $button)->exists());
		}

		// Check dashboard list button.
		$this->assertTrue(($this->query('name:dashboardForm')->asForm()->one())->query('button:Delete')->exists());

		// Check header buttons.
		$this->assertTrue($this->query('button:Create dashboard')->exists());
		$this->assertTrue($this->query('xpath://button[@title="Kiosk mode"]')->exists());
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'fields' => [
						'Show' => 'All'
					],
					'result_count' => 25
				]
			],
			[
				[
					'fields' => [
						'Show' => 'Created by me'
					],
					'result_count' => 24
				]
			],
			[
				[
					'fields' => [
						'Name' => 'graph',
						'Show' => 'All'
					],
					'result_count' => 2
				]
			],
			[
				[
					'fields' => [
						'Name' => 'widget',
						'Show' => 'Created by me'
					],
					'result_count' => 9
				]
			],
			[
				[
					'fields' => [
						'Name' => '5'
					],
					'result_count' => 0
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Dashboard for Dynamic item'
					],
					'result_count' => 1
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Testing share dashboard',
						'Show' => 'Created by me'
					],
					'result_count' => 0
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testDashboardsListPage_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();
		$table->waitUntilReloaded();

		// Check that filtered count matches expected.
		if ($data['result_count'] !== 0) {
			$this->assertEquals($data['result_count'], $table->getRows()->count());
		}
		$this->assertTableStats($data['result_count']);
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($start_rows_count, $table->getRows()->count());
	}

	/**
	 * Check that My and Sharing tags displays correctly in Dashboard Lists for Admin.
	 */
	public function testDashboardsListPage_CheckOwners() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$table = $this->query('class:list-table')->asTable()->one();

		$dashboards = CDBHelper::getAll('SELECT name, userid, private, dashboardid FROM dashboard');
		$dashboards_usrgrps = CDBHelper::getAll('SELECT dashboardid FROM dashboard_usrgrp');
		$dashboards_users = CDBHelper::getAll('SELECT dashboardid FROM dashboard_user');

		// Checking that dashboard, owned by Admin, has My tag near its name.
		foreach ($dashboards as $dashboard) {
			if ($dashboard['userid'] == 1) {
				$this->assertEquals('My', $this->getTagText($table, $dashboard['name'], 'green'));

				if ($dashboard['private'] == 0) {
					$this->assertEquals('Shared', $this->getTagText($table, $dashboard['name'], 'yellow'));
				}
			}

			// Checking that Admin dashboards, shared with groups, has Shared tag.
			$this->assertDashboardOwner($dashboards_usrgrps, $dashboard, $table);

			// Checking that Admin dashboards, shared with users, has Shared tag.
			$this->assertDashboardOwner($dashboards_users, $dashboard, $table);
		}
	}

	public function testDashboardsListPage_DeleteSingleDashboard() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$dashboard_name = 'Testing share dashboard';
		$table = $this->query('class:list-table')->asTable()->one();

		// Delete single Dashboard.
		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);
		$table->findRow('Name', $dashboard_name, true)->select();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard deleted');
		$this->assertTableStats($before_rows_count - 1);

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dashboard WHERE name='.zbx_dbstr($dashboard_name)));
	}

	public function testDashboardsListPage_DeleteAllDashboards() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->selectTableRows();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboards deleted');
		$this->assertTableStats(0);

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dashboard WHERE templateid IS NULL'));
	}

	/**
	 * Allows to check tag near Dashboard name.
	 *
	 * @param element $table	  Table where to find row
	 * @param string $column	  Column name
	 * @param string $color		  Color of tag in the row
	 */
	private function getTagText($table, $column, $color) {
		$row = $table->findRow('Name', $column, true);

		return $row->query('xpath://span[@class="status-'.$color.'"]')->one()->getText();
	}

	/**
	 * Allows to check values in different tables from database.
	 *
	 * @param array $ids		  Dashboard ids where to find particular dashboard id
	 * @param array $dashboard	  Dashboard table from database
	 * @param element $table      Frontend table
	 */
	private function assertDashboardOwner($ids, $dashboard, $table) {
		foreach ($ids as $id) {
			if ($id['dashboardid'] == $dashboard['dashboardid'] && $dashboard['userid'] == 1) {
				$this->assertEquals('Shared', $this->getTagText($table, $dashboard['name'], 'yellow'));
			}
		}
	}
}
