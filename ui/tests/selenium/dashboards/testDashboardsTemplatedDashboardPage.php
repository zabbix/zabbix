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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareTemplateDashboardsData
 */
class testDashboardsTemplatedDashboardPage extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	const TEMPLATEID = 99022;	// ID of the template for with a list of dashboards.
	const DASHBOARDS = ['1st dashboard', '2nd dashboard', 'middle dashboard', 'z last dashboard'];
	const DASHBOARDS_2_DELETE = ['1st dashboard', 'middle dashboard'];

	public static function prepareTemplateDashboardsData() {
		foreach (self::DASHBOARDS as $dashboard) {
			CDataHelper::call('templatedashboard.create', [
				[
					'templateid' => self::TEMPLATEID,
					'name' => $dashboard,
					'pages' => [
						[
							'widgets' => [
								[
									'type' => 'clock',
									'name' => '1st Dashboard clock'
								]
							]
						]
					]
				]
			]);
		}
	}

	public function testDashboardsTemplatedDashboardPage_Layout() {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::TEMPLATEID);
		$this->page->assertHeader('Dashboards');
		$this->page->assertTitle('Configuration of dashboards');

		// Check status of buttons on the template dashboards page.
		foreach (['Create dashboard' => true, 'Delete' => false] as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check the count of returned dashboards and the count of selected dashboards.
		$dashboards_count = count(self::DASHBOARDS);
		$this->assertTableStats($dashboards_count);

		$all_dashboards = $this->query('id:all_dashboards')->asCheckbox()->one();
		foreach ([false, true, false] as $checkbox_state) {
			$expected_count = ($checkbox_state) ? $dashboards_count : 0;

			if ($all_dashboards->isChecked() !== $checkbox_state) {
				$all_dashboards->set($checkbox_state);
			}
			$this->assertEquals($expected_count.' selected', $this->query('id:selected_count')->one()->getText());
		}

		// Check tokens table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();

		// Check content of table headers.
		array_shift($headers);
		$this->assertSame(['Name'], $headers);
		$this->assertTrue($table->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($headers[0]).']')->one()->isClickable());

		// Check list table contents.
		$this->assertTableData($this->wrapDashboardNames());
	}

	/**
	 * @backup profiles
	 */
	public function testDashboardsTemplatedDashboardPage_Sort() {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::TEMPLATEID);
		$header = $this->query('link:Name')->one()->waitUntilClickable();

		// Change sorting to descending and back and verify list of dashboard names each time.
		foreach (['asc', 'dsc', 'last'] as $sorting) {
			$names = self::DASHBOARDS;

			if ($sorting === 'dsc') {
				rsort($names);
			}
			$this->assertTableData($this->wrapDashboardNames($names));

			// No need to change sorting in the last execution of this code
			if ($sorting !== 'last') {
				$header->click();
			}
		}
	}

	public function testDashboardsTemplatedDashboardPage_Delete() {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::TEMPLATEID);
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilVisible();
		$table->findRows('Name', self::DASHBOARDS_2_DELETE)->select();

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that dashboards were deleted and that other dashboards are still there.
		$remaining_dashboards = array_diff(self::DASHBOARDS, self::DASHBOARDS_2_DELETE);
		$this->assertTableData($this->wrapDashboardNames($remaining_dashboards));
		foreach (self::DASHBOARDS_2_DELETE as $name) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT dashboardid FROM dashboard WHERE name='.zbx_dbstr($name)));
		}
	}

	/**
	 * Function formats the array of dashboard names so that it would be a valid for input into assertTableData function.
	 *
	 * @param type $unwrapped_dashboards
	 *
	 * @return type
	 */
	private function wrapDashboardNames($unwrapped_dashboards = self::DASHBOARDS) {
		$dashboards = [];
		foreach ($unwrapped_dashboards as $dashboard) {
			$dashboards[] = ['Name' => $dashboard];
		}

		return $dashboards;
	}
}
