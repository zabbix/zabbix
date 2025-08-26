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
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource ScheduledReports
 *
 * @backup report
 */
class testPageScheduledReport extends CWebTest {

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

	/**
	 * Get all report names from the database.
	 *
	 * @return array
	 */
	private function getAllReportNames() {
		$result = [];

		$names = CDBHelper::getAll('SELECT name FROM report');
		usort($names, function ($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		});
		foreach ($names as $name) {
			$result[] = $name['name'];
		}

		return $result;
	}

	public static function getDashboardData() {
		return [
			[
				[
					'name' => 'Zabbix server'
				]
			],
			[
				[
					'name' => 'Zabbix server health',
					'no_reports' => true
				]
			]
		];
	}

	/**
	 * Test related reports view in dashboard.
	 *
	 * @dataProvider getDashboardData
	 *
	 * @backupOnce profiles
	 */
	public function testPageScheduledReport_Dashboard($data) {
		$dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name='.zbx_dbstr($data['name']));
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid)->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();

		if (array_key_exists('no_reports', $data)) {
			$this->assertFalse($popup->getItem('View related reports')->isEnabled());
		}
		else {
			$popup->select('View related reports');
			$overlay = COverlayDialogElement::find()->waitUntilReady()->one();
			$this->page->removeFocus();
			$this->assertScreenshot($overlay);
			$overlay->query('button:Ok')->one()->click();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	public function testPageScheduledReport_Layout() {
		$expired_report = [
			'Name' => 'Report for filter - expired',
			'Owner' => 'Admin (Zabbix Administrator)',
			'Repeats' => 'Yearly',
			'Period' => 'Previous year',
			'Last sent' => 'Never',
			'Status' => 'Expired',
			'Info' => 'Expired on 2020-01-01.'
		];
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$this->page->assertHeader('Scheduled reports');

		$this->assertEquals(3, $this->query('button', ['Enable', 'Disable', 'Delete'])->all()
				->filter(new CElementFilter(CElementFilter::ATTRIBUTES_PRESENT, ['disabled']))->count());

		// Check displaying and hiding the filter.
		$filter = CFilterElement::find()->one();
		$filter_form = $filter->getForm();
		$this->assertEquals('Filter', $filter->getSelectedTabName());
		// Check that filter is expanded by default.
		$this->assertTrue($filter->isExpanded());
		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter->expand($status);
			$this->assertTrue($filter->isExpanded($status));
		}

		// Check default values of filter.
		$filter_form->checkValue(['Name' => '', 'Show' => 'All', 'Status' => 'Any']);

		// Check the count of displaying reports and the count of selected reports.
		$reports = CDBHelper::getCount('SELECT reportid FROM report');
		$this->assertTableStats($reports);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$this->selectTableRows();
		$this->assertEquals($reports.' selected', $selected_count->getText());
		// Check that buttons became enabled.
		$this->assertEquals(3, $this->query('button', ['Enable', 'Disable', 'Delete'])->all()
				->filter(new CElementFilter(CElementFilter::ATTRIBUTES_NOT_PRESENT, ['disabled']))->count());

		// Reset filter and check that reports unselected.
		$filter_form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name', 'Owner', 'Repeats', 'Period', 'Last sent', 'Status', 'Info'], $table->getHeadersText());
		$this->assertEquals(['Name'], $table->getSortableHeaders()->asText());

		// Check all columns and info icon for one report.
		$row = $table->findRow('Name', $expired_report['Name']);
		$row->getColumn('Info')->query('class:zi-i-warning')->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
		$this->assertEquals($expired_report['Info'], $hint->getText());
		$hint->close();
		$this->query('xpath://div[@data-hintboxid]')->waitUntilNotVisible();
		unset($expired_report['Info']);
		foreach ($expired_report as $column => $value) {
			$this->assertEquals($value, $row->getColumn($column)->getText());
		}

		// Check that the filter is still expanded after page refresh.
		$this->page->refresh()->waitUntilReady();
		$this->assertTrue($filter->isExpanded());
	}

	public static function getFilterData() {
		return [
			// Retrieve only Created by me reports.
			[
				[
					'filter' => [
						'Show' => 'Created by me'
					],
					'result' => [
						'Report for filter - disabled',
						'Report for filter - enabled',
						'Report for filter - expired',
						'Report for testFormScheduledReport',
						'Report for update',
						'Report to update all fields'
					]
				]
			],
			// Retrieve only Enabled reports.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'result' => [
						'Report for delete',
						'Report for filter - enabled',
						'Report for filter - owner admin',
						'Report for update'
					]
				]
			],
			// Retrieve only Disabled reports.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'result' => [
						'Report for filter - disabled',
						'Report for testFormScheduledReport',
						'Report to update all fields'
					]
				]
			],
			// Retrieve only Expired reports.
			[
				[
					'filter' => [
						'Status' => 'Expired'
					],
					'result' => [
						'Report for filter - expired',
						'Report for filter - expired, owner admin'
					]
				]
			],
			// Mixed filter options
			[
				[
					'filter' => [
						'Show' => 'Created by me',
						'Status' => 'Expired'
					],
					'result' => [
						'Report for filter - expired'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'disabled',
						'Show' => 'Created by me',
						'Status' => 'Disabled'
					],
					'result' => [
						'Report for filter - disabled'
					]
				]
			],
			// Exact name match.
			[
				[
					'filter' => [
						'Name' => 'Report for filter - expired, owner admin'
					],
					'result' => [
						'Report for filter - expired, owner admin'
					]
				]
			],
			// Partial name match.
			[
				[
					'filter' => [
						'Name' => '- expired'
					],
					'result' => [
						'Report for filter - expired',
						'Report for filter - expired, owner admin'
					]
				]
			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageScheduledReport_Filter($data) {
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['filter'])->submit();
		$this->page->waitUntilReady();

		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
		$displaying = count(CTestArrayHelper::get($data, 'result', []));
		$this->assertTableStats($displaying);

		// Reset the filter and check that all reports are displayed.
		$this->query('button:Reset')->one()->click();
		$this->assertTableStats(count($this->getAllReportNames()));
	}

	public static function getStatusData() {
		return [
			// Enable/disable single report by clicking on table column "Status".
			[
				[
					'Name' => 'Report for update',
					'Status' => 'Enabled'
				]
			],
			[
				[
					'Name' => 'Report for filter - disabled',
					'Status' => 'Disabled'
				]
			],
			[
				[
					'Name' => 'Report for filter - expired',
					'Status' => 'Expired'
				]
			],
			// Enable/disable report by clicking the button.
			[
				[
					'Name' => 'Report for update',
					'button' => 'Enable'
				]
			],
			[
				[
					'Name' => 'Report for filter - disabled',
					'button' => 'Disable'
				]
			],
			[
				[
					'Name' => [
						'Report for filter - disabled',
						'Report for filter - expired'
					],
					'button' => 'Enable'
				]
			],
			[
				[
					'Name' => [
						'Report for update',
						'Report for filter - expired, owner admin'
					],
					'button' => 'Disable'
				]
			],
			// Enable/disable all reports by clicking the button.
			[
				[
					'button' => 'Enable'
				]
			],
			[
				[
					'button' => 'Disable'
				]
			]
		];
	}

	/**
	 * Test report status change.
	 *
	 * @dataProvider getStatusData
	 */
	public function testPageScheduledReport_Status($data) {
		// The status can't be "enabled" for expired reports.
		$expired = [
			'Report for filter - expired',
			'Report for filter - expired, owner admin'
		];
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$this->page->waitUntilReady();

		//  Enable/disable single report by clicking on table column "Status".
		if (array_key_exists('Status', $data)) {
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['Name']);
			$row->getColumn('Status')->query('tag:a')->one()->click();

			// Prepare data to check status after changes.
			$status = (in_array($data['Status'], ['Enabled', 'Expired'])) ? 'disabled' : 'enabled';
			$message_title = 'Scheduled report '.$status;
			$column_status = ucfirst($status);
			$db_status = (in_array($data['Status'], ['Enabled', 'Expired'])) ? 1 : 0;
		}
		// Enable/disable report via button.
		else {
			$this->selectTableRows(CTestArrayHelper::get($data, 'Name', []));
			$this->query('button', $data['button'])->one()->waitUntilClickable()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();

			// Prepare data to check status after changes.
			$plural = (array_key_exists('Name', $data) && !is_array($data['Name'])) ? 'report' : 'reports';
			$message_title = 'Scheduled '.$plural.' '.lcfirst($data['button']).'d';
			$column_status = $data['button'].'d';
			$db_status = ($data['button'] === 'Enable') ? 0 : 1;
		}

		// Checks the status of the report in the report list.
		$this->assertMessage(TEST_GOOD, $message_title);

		$table = $this->query('class:list-table')->asTable()->one();
		$rows = array_key_exists('Name', $data) ? $table->findRows('Name', $data['Name']) : $table->getRows();
		foreach ($rows as $row) {
			if (in_array($row->getColumn('Name')->getText(), $expired) && $db_status === 0) {
				$this->assertEquals('Expired', $row->getColumn('Status')->getText());
				continue;
			}
			$this->assertEquals($column_status, $row->getColumn('Status')->getText());
		}

		$names = CTestArrayHelper::get($data, 'Name', $this->getAllReportNames());
		if (!is_array($names)) {
			$names = [$names];
		}
		foreach ($names as $name) {
			$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM report WHERE name='.zbx_dbstr($name)));
		}
	}

	/**
	 * Test reports sorting by Name column.
	 */
	public function testPageScheduledReport_Sorting() {
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="Name"]')->one();
		$names = $this->getAllReportNames();

		foreach(['asc', 'desc'] as $sorting) {
			$expected = ($sorting === 'asc') ? $names : array_reverse($names);
			$values = [];

			foreach ($table->getRows() as $row) {
				$values[] = $row->getColumn('Name')->getText();
			}
			$this->assertEquals($expected, $values);
			$header->click();
		}
	}

	public static function getDeleteData() {
		return [
			// Delete single, delete multiple and delete all reports.
			[
				[
					'Name' => 'Report for delete'
				]
			],
			[
				[
					'Name' => [
						'Report for filter - owner admin',
						'Report for filter - enabled'
					]
				]
			],
			[
				[
					'delete_all' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageScheduledReport_Delete($data) {
		$reports = CDBHelper::getCount('SELECT reportid FROM report');
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$this->page->waitUntilReady();

		$this->selectTableRows(CTestArrayHelper::get($data, 'Name', []));
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());

		if (array_key_exists('delete_all', $data)) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM report'));
			$this->assertTableStats(0);
		}
		else {
			if (!is_array($data['Name'])) {
				$data['Name'] = [$data['Name']];
			}
			$remaining = $reports - count($data['Name']);
			$this->assertTableStats($remaining);

			foreach ($data['Name'] as $name) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM report WHERE name='.zbx_dbstr($name)));
			}
		}
	}
}
