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
 * @backup profiles
 */
class testHostAvailabilityWidget extends CWebTest {

	/*
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql;

	/**
	 * Overriden constructor.
	 */
	public function __construct($name = null, array $data = [], $data_name = '') {
		parent::__construct($name, $data, $data_name);

		$this->sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboardid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_groupid';
	}

	public static function getCreateWidgetData() {
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
	public function testHostAvailabilityWidget_Create($data) {
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
		$dashboard->getWidget($header);
		$dashboard->save();

		// Check that Dashboard has been saved and that widget has been added.
		$this->checkDashboardUpdateMessage();
		$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());

		// Check that widget has been added.
		$this->verifyRefreshInterval($data, $header);
		$this->verifyWidgetContent($data, $header);
	}

	public static function getUpdateWidgetData() {
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
						'Layout' => 'Vertical'
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
	 * @backup widget
	 * @dataProvider getUpdateWidgetData
	 */
	public function testHostAvailabilityWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('Reference widget')->edit();

		// Update the widget.
		$header = ($data['fields']['Name'] === '') ? 'Host availability' : $data['fields']['Name'];
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Verify that a widget with the corresponding header exists.
		$dashboard->getWidget($header);
		$dashboard->save();

		// Check that Dashboard has been saved and that widget has been added.
		$this->checkDashboardUpdateMessage();
		// Check that widget has been added.
		$this->verifyRefreshInterval($data, $header);
		$this->verifyWidgetContent($data, $header);
	}

	public function testHostAvailabilityWidget_SimpleUpdate() {
		$initial_values = CDBHelper::getHash($this->sql);

		// Open a dashboard widget and then save it without applying any changes
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('Reference widget')->edit();
		$form->submit();
		$this->page->waitUntilReady();

		$dashboard->getWidget('Reference widget');
		$dashboard->save();

		// Check that Dashboard has been saved and that there are no changes made to the widgets.
		$this->checkDashboardUpdateMessage();
		$this->assertEquals($initial_values, CDBHelper::getHash($this->sql));
	}


	public static function getCancelData() {
		return [
			// Cancel update widget.
			[
				[
					'existing_widget' => 'Reference widget',
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'existing_widget' => 'Reference widget',
					'save_widget' => false,
					'save dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save dashboard' => false
				]
			],
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testHostAvailabilityWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'existing_widget', false)) {
			$widget = $dashboard->getWidget($data['existing_widget']);
			$form = $widget->edit();
		}
		else {
			$overlay = $dashboard->edit()->addWidget();
			$form = $overlay->asForm();
			$form->getField('Type')->fill('Host availability');
			$widget = $dashboard->getWidgets()->last();
		}

		$form->getField('Name')->fill('Widget to be cancelled');

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();
			$this->page->waitUntilReady();
			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget('Widget to be cancelled')->isVisible());
		}
		else {
			$this->query('button:Cancel')->one()->click();
			// Check that widget changes wasn't took place after pressing "Cancel".
			if (CTestArrayHelper::get($data, 'existing_widget', false)) {
				$this->assertNotEquals('Widget to be cancelled', $widget->waitUntilReady()->getHeaderText());
			}
			else {
				// If test fails and widget isn't canceled, need to wait until widget appears on the dashboard.
				sleep(5);

				if ($widget->getID() !== $dashboard->getWidgets()->last()->getID()) {
					$this->fail('New widget was added after pressing "Cancel"');
				}
			}
		}

		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}

		// Confirm that no changes were made to the widget.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testHostAvailabilityWidget_Delete() {
		$name = 'Reference widget to delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget($name);
		$widget->delete();
		$this->page->waitUntilReady();

		$dashboard->save();
		// Check that Dashboard has been saved
		$this->checkDashboardUpdateMessage();
		// Confirm that widget is not present on dashboard.
		$this->assertTrue($dashboard->getWidget($name, false) === null);
	}

	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	private function verifyWidgetContent($data,$header) {
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

		$results = [];
		$classes = [
			'available' => 'host-avail-true',
			'not available' => 'host-avail-false',
			'unknown' => 'host-avail-unknown',
			'total' => 'host-avail-total'
		];

		// Get the count of hosts in each state that is returned by the widget.
		foreach ($classes as $key => $class) {
			$xpath = 'xpath:.//td[@class='.CXPathHelper::escapeQuotes($class).']/span';
			$results[$key] = $content->query($xpath)->one()->getText();
		}

		// If expected values defined in Data provider, verify that they match the values shown in the widget.
		if (CTestArrayHelper::get($data, 'expected_values', false)) {
			$this->AssertEquals($results, $data['expected_values']);
		}
		// Else get expected values from DB.
		else {
			// Select all host esntries that are not templates or proxies and that are not host prototypes.
			$total_sql = 'SELECT hostid FROM hosts WHERE status IN (0,1) AND flags != 2';

			// Filter out hosts in maintenence if the flag 'Show hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data['fields'], 'Show hosts in maintenance', false) === false) {
				$total_sql = $total_sql.' AND maintenance_status = 0';
			}

			// 'Available' flag values: 0 - unknown, 1 - available, 2 - not available.
			$db_values = [
				'available' => CDBHelper::getCount($total_sql.' AND available = 1'),
				'not available' => CDBHelper::getCount($total_sql.' AND available = 2'),
				'unknown' => CDBHelper::getCount($total_sql.' AND available = 0'),
				'total' => CDBHelper::getCount($total_sql)
			];

			// Verify that values from the widget match values from DB.
			$this->AssertEquals($results, $db_values);
		}
	}

	private function verifyRefreshInterval($data, $header) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$refresh = CTestArrayHelper::get($data['fields'], 'Refresh interval', 'Default (15 minutes)');
		$mapping = [
			'Default (15 minutes)' => 900,
			'No refresh' => 0,
			'10 seconds' => 10,
			'1 minute' => 60,
			'2 minutes' => 120,
			'10 minutes' => 600
		];
		$this->assertEquals($widget->getRefreshInterval(), $mapping[$refresh]);
	}
}
