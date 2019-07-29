<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup widget
 */
class testPageWidgetHostAvailability extends CWebTest {

	public function getCreateWidgetData() {
		return [
			// Create a Host availability widget with default values.
			[
				[
					'fields' => [
						'Type' => 'Host availability'
					]
				]
			],
			// Create a Host availability widget specifying every parameter.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show hosts in maintenance',
						'Refresh interval' => '1 minute',
						'Host groups' => [
							'Group for Host availability widget',
							'Group in maintenance for Host availability widget'
						],
						'Layout' => 'Horizontal',
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'total' => 6,
						'available' => 2,
						'not available' => 2,
						'unknown' => 2
					]
				]
			],
			// Create the same widget as previous one, but with 'Show hosts in maintenance' = false.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Dont show hosts in maintenance',
						'Refresh interval' => '10 minutes',
						'Host groups' => [
							'Group for Host availability widget',
							'Group in maintenance for Host availability widget'
						],
						'Layout' => 'Horizontal',
						'Show hosts in maintenance' => false
					],
					'expected_values' => [
						'total' => 3,
						'available' => 1,
						'not available' => 1,
						'unknown' => 1
					]
				]
			],
			// Create a Host availability widget with vertical layout.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Vertical host availability widget',
						'Refresh interval' => '10 seconds',
						'Layout' => 'Vertical',
						'Show hosts in maintenance' => true
					],
					'interval_in_seconds' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateWidgetData
	 */
	public function testPageWidgetHostAvailability_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Add a widget.
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Make sure that the widget is present before saving the dashboard.
		$header = CTestArrayHelper::get($data['fields'], 'Name', 'Host availability');
		$dashboard->getWidget($header)->waitUntilVisible();
		$dashboard->save();
		$this->page->waitUntilReady();

		// Check that Dashboard has been saved and that widget has been added.
		$this->checkDashboardUpdateMessage();
		$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());

		// Check that widget has been added.
		$this->verifyRefreshInterval($data, $header);
		$this->verifyWidgetContent($data, $header);
	}

	public function getUpdateWidgetData() {
		return [
			// Update the widget to return info for group in maintenance and remove "Show hosts in maintenance" flag.
			[
				[
					'fields' => [
						'Name' => 'Should return zeros',
						'Refresh interval' => '1 minute',
						'Host groups' => [
							'Group in maintenance for Host availability widget'
						],
						'Show hosts in maintenance' => false
					],
					'expected_values' => [
						'total' => 0,
						'available' => 0,
						'not available' => 0,
						'unknown' => 0
					]
				]
			],
			// Update the widget to return info for group in maintenance and set "Show hosts in maintenance" flag.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Return hosts in maintenance',
						'Refresh interval' => '10 seconds',
						'Host groups' => [
							'Group in maintenance for Host availability widget'
						],
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'total' => 3,
						'available' => 1,
						'not available' => 1,
						'unknown' => 1
					],
					'interval_in_seconds' => true
				]
			],
			// Update the layout of the widget to Vertical.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Layout is now vertical',
						'Refresh interval' => '2 minutes',
						'Layout' => 'Vertical',
					]
				]
			],
			// Setting name of the widget to default value.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => '',
						'Refresh interval' => 'Default (15 minutes)'
					]
				]
			],
			// Update the show hosts in maintenance option without defining host groups.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts',
						'Refresh interval' => 'No refresh',
						'Show hosts in maintenance' => true
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateWidgetData
	 */
	public function testPageWidgetHostAvailability_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget('Reference widget')->edit();
		$dialogue = $this->query('id:overlay_dialogue')->asOverlayDialog()->one()->waitUntilReady();

		// Attempt to update the widget and revert changes if something goes wrong.
		try {
			$form = $dialogue->asForm()->fill($data['fields']);
			$form->submit();
			$this->page->waitUntilReady();

			// Verify that a widget with the corresponding header exists.
			if ($data['fields']['Name'] === '') {
				$header = 'Host availability';
			}
			else {
				$header = CTestArrayHelper::get($data['fields'], 'Name');
			}
			$dashboard->getWidget($header)->waitUntilVisible();
			$dashboard->save();
			$this->page->waitUntilReady();

			// Check that Dashboard has been saved and that widget has been added.
			$this->checkDashboardUpdateMessage();
			// Check that widget has been added.
			$this->verifyRefreshInterval($data, $header);
			$this->verifyWidgetContent($data, $header);

			// Afer verifying the changes update the widget to its original confirguration.
			$this->setOriginalWidgetParameters($data['fields']['Name']);
		}
		catch (\Exception $ex) {
			$this->setOriginalWidgetParameters($data['fields']['Name']);
			throw $ex;
		}
	}

	public function testPageWidgetHostAvailability_SimpleUpdate() {
		// Get the fields of widget "Reference widget to delete" before updating.
		$widget_sql = 'SELECT * FROM widget_field wf INNER JOIN widget w ON w.widgetid = wf.widgetid WHERE w.name = \'Reference widget to delete\';';
		$initial_values = CDBHelper::getHash($widget_sql);
		// Open a dashboard widget and then save it without applying any changes
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Reference widget')->edit();
		$dialogue = $this->query('id:overlay_dialogue')->asOverlayDialog()->one()->waitUntilReady();
		$dialogue->asForm()->submit();
		$this->page->waitUntilReady();
		$dashboard->getWidget('Reference widget')->waitUntilVisible();
		$this->query('button', 'Save changes')->one()->click();
		$this->page->waitUntilReady();

		// Check that Dashboard has been saved and that there are no changes made to the widget.
		$this->checkDashboardUpdateMessage();
		$new_values = CDBHelper::getHash($widget_sql);
		$this->assertEquals($initial_values, $new_values);
	}

	public function testPageWidgetHostAvailability_Cancel() {
		//Check cancellation prior and afore adding a widget to a dashboard in edit mode.
		foreach (['with adding widget', 'without adding widget'] as $cancel_mode) {
			// Check cancellation of create and update actions.
			foreach (['Create','Update'] as $cancelled_action) {
				$sql_hash = 'SELECT * FROM widget_field wf INNER JOIN widget w ON w.widgetid = wf.widgetid';
				$old_hash = CDBHelper::getHash($sql_hash);

				$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
				$dashboard = CDashboardElement::find()->one();
				// Start updating or creating a widget.
				if ($cancelled_action === 'Update') {
					$form = $dashboard->getWidget('Reference widget')->edit()->asForm();
				}
				else {
					$form = $dashboard->edit()->addWidget()->asForm();
					$form->getField('Type')->fill('Host availability');
				}
				$form->getField('Name')-> fill('Widget to be cancelled');
				if ($cancel_mode === 'with adding widget') {
					$form->submit();
					$this->page->waitUntilReady();
					// Check that changes took place on the unsaved dashboard.
					$this->assertTrue($dashboard->getWidget('Widget to be cancelled')->isVisible());
				}
				else {
					$this->query('button:Cancel')->one()->click();
				}
				$dashboard->cancelEditing();
				// Confirm that no changes were made to the widget.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
			}
		}
	}

/**
 * @backup-once widget_field
 */
	public function testPageWidgetHostAvailability_Delete() {
		// Open a dashboard widget to delete
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget('Reference widget to delete');
		$widget->query("xpath:.//button[@title='Delete']")->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		// Check that Dashboard has been saved
		$this->checkDashboardUpdateMessage();
		// Confirm that widget is not present on dashboard.
		$this->assertTrue($dashboard->query("xpath:.//div[@class='dashbrd-grid-widget-head']/h4[text()='Reference widget to delete']")->count() === 0);
	}

	function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	function setOriginalWidgetParameters($widget_name) {
		$initial_values = [
			'Name' => 'Reference widget',
			'Refresh interval' => '10 minutes',
			'Host groups' => '',
			'Layout' => 'Horizontal',
			'Show hosts in maintenance' => false
		];

		$dashboard = CDashboardElement::find()->one();
		if ($widget_name === '') {
			$widget_name = 'Host availability';
		}
		$dashboard->getWidget($widget_name)->edit();
		$dialogue = $this->query('id:overlay_dialogue')->asOverlayDialog()->one()->waitUntilReady();
		$form = $dialogue->asForm()->fill($initial_values);
		$form->submit();
		$this->page->waitUntilReady();
		$dashboard->save();
	}

	function verifyWidgetContent($data,$header) {
		// Get widget content.
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$content = $widget->getContent()->asTable();
		// Verify that layout is correct.
		if (CTestArrayHelper::get($data['fields'], 'Layout', 'Horizontal') === 'Horizontal') {
			$this->assertEquals($content->getRows()->count(), 1);
		}
		else {
			$this->assertEquals($content->getRows()->count(), 4);
		}
		// Get the count of hosts in each state that is returned by the widget.
		$results = [
			['name' => 'available', 'value' => $content->query("xpath:.//td[@class='host-avail-true']/span")->one()->getText()],
			['name' => 'not available', 'value' => $content->query("xpath:.//td[@class='host-avail-false']/span")->one()->getText()],
			['name' => 'unknown', 'value' => $content->query("xpath:.//td[@class='host-avail-unknown']/span")->one()->getText()],
			['name' => 'total', 'value' => $content->query("xpath:.//td[@class='host-avail-total']/span")->one()->getText()]
		];
		// If expected values defined in Data provider, verify that they match the values shown in the widget.
		if (CTestArrayHelper::get($data, 'expected_values', false)) {
			foreach ($results as $result) {
				$this->AssertEquals($result['value'], $data['expected_values'][$result['name']]);
			}
		}
		// Else get expected values from DB.
		else {
			// Select all host esntries that are not templates or proxies and that are not host prototypes.
			$total_sql = 'select hostid from hosts where status in (0,1) and flags != 2';
			// Filter out hosts in maintenence if the flag 'Show hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data['fields'], 'Show hosts in maintenance', false) === false) {
				$total_sql = $total_sql.' and maintenance_status = 0';
			}
			// 'Available' flag values: 0 - unknown, 1 - available, 2 - not available.
			$unknown_sql = $total_sql.' and available = 0;';
			$available_sql = $total_sql.' and available = 1;';
			$not_available_sql = $total_sql.' and available = 2;';

			$expected = [
				'available' => CDBHelper::getCount($available_sql),
				'not available' => CDBHelper::getCount($not_available_sql),
				'unknown' => CDBHelper::getCount($unknown_sql),
				'total' => CDBHelper::getCount($total_sql.';')
			];
			// Verify that values from the widget match values from DB.
			foreach ($results as $result) {
				$this->AssertEquals($result['value'], $expected[$result['name']]);
			}
		}
	}

	function verifyRefreshInterval($data, $header) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header)->waitUntilVisible();
		if (cTestArrayHelper::get($data, 'interval_in_seconds', false)) {
			$actual_interval = $widget->getRefreshInterval();
			$used_interval = rtrim(CTestArrayHelper::get($data['fields'], 'Refresh interval'), ' seconds');
		}
		elseif (cTestArrayHelper::get($data['fields'], 'Refresh interval', '15 minutes') === '1 minute') {
			$actual_interval = $widget->getRefreshInterval()/60;
			$used_interval = 1;
		}
		elseif (cTestArrayHelper::get($data['fields'], 'Refresh interval', '15 minutes') === 'No refresh') {
			$actual_interval = $widget->getRefreshInterval();
			$used_interval = 0;
		}
		elseif (cTestArrayHelper::get($data['fields'], 'Refresh interval', '15 minutes') === 'Default (15 minutes)') {
			$actual_interval = $widget->getRefreshInterval()/60;
			$used_interval = 15;
		}
		else {
			$actual_interval = $widget->getRefreshInterval()/60;
			$used_interval = rtrim(CTestArrayHelper::get($data['fields'], 'Refresh interval', '15 minutes'), ' minutes');
		}
		$this->assertEquals($actual_interval, $used_interval);
	}
}
