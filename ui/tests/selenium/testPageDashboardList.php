<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup dashboard, dashboard_user, dashboard_usrgrp
 */
class testPageDashboardList extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function testPageDashboardList_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->assertPageTitle('Dashboards');
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name'], $table->getHeadersText());

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check filter fields.
		$filter_fields = ['Name', 'Show'];
		$filter_form = $this->query('name:zbx_filter')->one()->asForm();
		$this->assertEquals($filter_fields, $filter_form->getLabels()->asText());
		foreach (['All', 'Created by me'] as $show_tag) {
			$this->assertTrue($filter_form->query('xpath://ul[@id="filter_show"]/li/label[text()="'.$show_tag.'"]')
				->one()->isPresent());
		};

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($filter_form->query('button', $button)->one()->isPresent());
		}

		// Check dashboard list button.
		$dashboard_form = $this->query('name:dashboardForm')->one()->asForm();
		$this->assertTrue($dashboard_form->query('button:Delete')->one()->isPresent());

		// Check header buttons.
		$this->assertTrue($this->query('button:Create dashboard')->one()->isPresent());
		$this->assertTrue($this->query('xpath://button[@title="Kiosk mode"]')->one()->isPresent());
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'field' => [
						'Show' => 'All'
					],
					'result_count' => 14
				]
			],
			[
				[
					'field' => [
						'Show' => 'Created by me'
					],
					'result_count' => 13
				]
			],
			[
				[
					'field' => [
						'Name' => 'graph',
						'Show' => 'All'
					],
					'result_count' => 3
				]
			],
			[
				[
					'field' => [
						'Name' => 'widget',
						'Show' => 'Created by me'
					],
					'result_count' => 8
				]
			],
			[
				[
					'field' => [
						'Name' => '5'
					],
					'result_count' => 0
				]
			],
			[
				[
					'field' => [
						'Name' => 'Dashboard for Dynamic item'
					],
					'result_count' => 1
				]
			],
			[
				[
					'field' => [
						'Name' => 'Dashboard for Share testing',
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
	public function testPageDashboardList_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$start_rows_count = $table->getRows()->count();
		$this->assertRowCount($start_rows_count);
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['field']);
		$form->submit();

		// Check that filtered count matches expected.
		if ($data['result_count'] !== 0) {
			$this->assertEquals($data['result_count'], $table->getRows()->count());
		}
		$this->assertRowCount($data['result_count']);
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($start_rows_count, $table->getRows()->count());
	}

	/**
	 * Check that My and Sharing tags displays corectly in Dashboard Lists for Admin.
	 */
	public function testPageDashboardList_CheckOwners() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$table = $this->query('class:list-table')->asTable()->one();

		$dashboards = CDBHelper::getAll('SELECT name, userid, private, dashboardid FROM dashboard');
		$dashboards_usrgrps = CDBHelper::getAll('SELECT dashboardid FROM dashboard_usrgrp');
		$dashboards_users = CDBHelper::getAll('SELECT dashboardid FROM dashboard_user');

		// Checking that dashboard, owned by Admin, has My tag near its name.
		foreach ($dashboards as $dashboard) {
			if ($dashboard['userid'] == 1) {
				$this->assertEquals('My', $this->getTagText($table, $dashboard['name'], 'green'));
			}
			if ($dashboard['private'] == 0 && $dashboard['userid'] == 1) {
				$this->assertEquals('Shared', $this->getTagText($table, $dashboard['name'], 'yellow'));
			}

			// Checking that Admin dashboards, shared with groups, has Shared tag	.
			foreach ($dashboards_usrgrps as $dashboard_usrgrp) {
				if ($dashboard_usrgrp['dashboardid'] == $dashboard['dashboardid'] && $dashboard['userid'] == 1) {
					$this->assertEquals('Shared', $this->getTagText($table, $dashboard['name'], 'yellow'));
				}
			}

			// Checking that Admin dashboards, shared with users, has Shared tag.
			foreach ($dashboards_users as $dashboard_user) {
				if ($dashboard_user['dashboardid'] == $dashboard['dashboardid'] && $dashboard['userid'] == 1 ) {
					$this->assertEquals('Shared', $this->getTagText($table, $dashboard['name'], 'yellow'));
				}
			}
		}
	}

	public function testPageDashboardList_DeleteSingleDashboard() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$dashboard_name = 'Dashboard for Share testing';
		$table = $this->query('class:list-table')->asTable()->one();

		// Delete single Dashboard.
		$before_rows_count = $table->getRows()->count();
		$this->assertRowCount($before_rows_count);
		$table->findRow('Name', $dashboard_name, true)->select();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard deleted');
		$after_rows_count = $before_rows_count - 1;
		$this->assertRowCount($after_rows_count);

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dashboard WHERE name='.zbx_dbstr($dashboard_name)));
	}

	public function testPageDashboardList_DeleteAllDashboards() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->selectTableRows();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboards deleted');
		$this->assertRowCount(0);

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dashboard'));
	}

	/**
	 * Allows to check tag near Dashboard name.
	 *
	 * @param string $table_path     Table where to find row
	 * @param string $column_name	 Column name
	 * @param string $color			 Color of tag in the row
	 */
	private function getTagText($table_path, $column_name, $color) {
		$row = $table_path->findRow('Name', $column_name, true);

		return $row->query('xpath://span[@class="tag '.$color.'-bg"]')->one()->getText();
	}
}
