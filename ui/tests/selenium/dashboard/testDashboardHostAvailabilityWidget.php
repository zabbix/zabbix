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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup widget, profiles
 */
class testDashboardHostAvailabilityWidget extends CWebTest {

	/*
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

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
			// Create a Host availability widget with default values for Zabbix agent interface.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Single interface widget - default',
						'Interface type' => 'Zabbix agent'
					]
				]
			],
			// Create a Host availability widget for Zabbix agent interface specifying every parameter.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show hosts in maintenance',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent',
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '6',
						'Available' => '2',
						'Not available' => '2',
						'Unknown' => '2'
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
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent',
						'Show hosts in maintenance' => false
					],
					'expected_values' => [
						'Total' => '3',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '1'
					]
				]
			],
			// Create a Host availability widget that displays only IPMI interfaces including the ones in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Host availability widget - IPMI with maintenance',
						'Refresh interval' => '10 seconds',
						'Interface type' => 'IPMI',
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '8',
						'Available' => '2',
						'Not available' => '2',
						'Unknown' => '4'
					]
				]
			],
			// Create a Host availability widget that displays SNMP interface.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Host availability widget - SNMP',
						'Refresh interval' => '2 minutes',
						'Interface type' => 'SNMP',
						'Layout' => 'Vertical'
					],
					'expected_values' => [
						'Total' => '6',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '4'
					]
				]
			],
			// Create a Host availability widget that displays JMX interface.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Host availability widget - JMX',
						'Refresh interval' => '10 minutes',
						'Interface type' => 'JMX'
					],
					'expected_values' => [
						'Total' => '7',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '5'
					]
				]
			],
			// Create a Host availability widget with all 4 interfaces selected.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'All interfaces selected',
						'Layout' => 'Vertical',
						'Interface type' => ['Zabbix agent', 'SNMP', 'JMX', 'IPMI']
					]
				]
			],
			// Create a Host availability widget that displays SNMP, JMX, IPMI interface and display hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'HA widget - show hosts in maintenance SNMP + JMX + IPMI',
						'Refresh interval' => '1 minute',
						'Show hosts in maintenance' => true,
						'Interface type' => ['SNMP', 'JMX', 'IPMI']
					],
					'expected_values' => [
						'SNMP' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '4',
							'Total' => '8'
						],
						'JMX' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '5',
							'Total' => '9'
						],
						'IPMI' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '4',
							'Total' => '8'
						]
					]
				]
			],
			// Create a Host availability widget with Vertical layout that displays SNMP, JMX and IPMI interfaces.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Vertical HA widget - SNMP + JMX + IPMI',
						'Refresh interval' => '10 minutes',
						'Layout' => 'Vertical',
						'Interface type' => ['SNMP', 'JMX', 'IPMI']
					],
					'expected_values' => [
						'Available' => [
							'SNMP' => '1',
							'JMX' => '1',
							'IPMI' => '1'
						],
						'Not available' => [
							'SNMP' => '1',
							'JMX' => '1',
							'IPMI' => '1'
						],
						'Unknown' => [
							'SNMP' => '4',
							'JMX' => '5',
							'IPMI' => '4'
						],
						'Total' => [
							'SNMP' => '6',
							'JMX' => '7',
							'IPMI' => '6'
						]
					]
				]
			],
			// HA widget that displays SNMP, JMX and SNMP interfaces for host group with only Zabbix agent interfaces.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Misconfiguration - zeros should be returned',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group to check Overview'],
						'Interface type' => ['SNMP', 'JMX', 'IPMI'],
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'SNMP' => [
							'Available' => '0',
							'Not available' => '0',
							'Unknown' => '0',
							'Total' => '0'
						],
						'JMX' => [
							'Available' => '0',
							'Not available' => '0',
							'Unknown' => '0',
							'Total' => '0'
						],
						'IPMI' => [
							'Available' => '0',
							'Not available' => '0',
							'Unknown' => '0',
							'Total' => '0'
						]
					]
				]
			]

		];
	}

	/**
	 * @dataProvider getCreateWidgetData
	 */
	public function testDashboardHostAvailabilityWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10110');
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Add a widget.
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();
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
		$this->checkRefreshInterval($data, $header);
		// Verify widget content depending on the number of interfaces displayed (single or multiple)
		$this->checkWidgetContent($data, $header);
	}

	public static function getUpdateWidgetData() {
		return [
			// Update the widget to return info for group in maintenance and remove "Show hosts in maintenance" flag.
			[
				[
					'fields' => [
						'Name' => 'Should return zeros',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent',
						'Show hosts in maintenance' => false
					],
					'expected_values' => [
						'Total' => '0',
						'Available' => '0',
						'Not available' => '0',
						'Unknown' => '0'
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
						'Host groups' => ['Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent',
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '3',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '1'
					]
				]
			],
			// Update the layout of the widget to Vertical and set Interface type to JMX.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Layout is now vertical',
						'Refresh interval' => '2 minutes',
						'Layout' => 'Vertical',
						'Interface type' => 'JMX'
					],
					'expected_values' => [
						'Total' => '7',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '5'
					]
				]
			],
			// Setting name of the widget to default value.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => '',
						'Refresh interval' => 'Default (15 minutes)',
						'Interface type' => 'Zabbix agent'
					]
				]
			],
			// Update the widget to show hosts in maintenance option without defining host groups.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts',
						'Refresh interval' => 'No refresh',
						'Interface type' => 'Zabbix agent',
						'Show hosts in maintenance' => true
					]
				]
			],
			// Update the widget to display host availability for 2 interface types including hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts for Zabbix agent and SNMP',
						'Refresh interval' => '10 seconds',
						'Interface type' => ['Zabbix agent', 'SNMP'],
						'Show hosts in maintenance' => true
					]
				]
			],
			// Update the widget to display host availability for 3 interface types excluding hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show hosts for Zabbix agent, SNMP and JMX',
						'Refresh interval' => '2 minutes',
						'Interface type' => ['Zabbix agent', 'SNMP', 'JMX']
					]
				]
			],
			// Update the widget to display host availability for all interfaces including hosts in maintenance. Layout = Vertical
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts for all interfaces',
						'Refresh interval' => '2 minutes',
						'Interface type' => ['Zabbix agent', 'SNMP', 'JMX', 'IPMI'],
						'Show hosts in maintenance' => true,
						'Layout' => 'Vertical'
					]
				]
			],
			// Display host availability for all interfaces for 2 host groups.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show all hosts for all interfaces of 2 groups',
						'Refresh interval' => '10 minutes',
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Show hosts in maintenance' => true
					],
					'expected_values' => [
						'Zabbix agent' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '2',
							'Total' => '6'
						],
						'SNMP' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '0',
							'Total' => '4'
						],
						'JMX' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '0',
							'Total' => '4'
						],
						'IPMI' => [
							'Available' => '2',
							'Not available' => '2',
							'Unknown' => '0',
							'Total' => '4'
						]
					]
				]
			]
		];
	}

	/**
	 * @backup widget
	 * @dataProvider getUpdateWidgetData
	 */
	public function testDashboardHostAvailabilityWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10110');
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('Reference HA widget')->edit();

		// Update the widget.
		$header = ($data['fields']['Name'] === '') ? 'Host availability' : $data['fields']['Name'];
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check that a widget with the corresponding header exists.
		$dashboard->getWidget($header);
		$dashboard->save();

		// Check that Dashboard has been saved and that widget has been added.
		$this->checkDashboardUpdateMessage();
		// Check that widget has been added.
		$this->checkRefreshInterval($data, $header);
		// Verify widget content depending on the number of interfaces displayed (single or multiple)
		$this->checkWidgetContent($data, $header);
	}

	public function testDashboardHostAvailabilityWidget_SimpleUpdate() {
		$initial_values = CDBHelper::getHash($this->sql);

		// Open a dashboard widget and then save it without applying any changes
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10110');
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('Reference HA widget')->edit();
		$form->submit();
		$this->page->waitUntilReady();

		$dashboard->getWidget('Reference HA widget');
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
					'existing_widget' => 'Reference HA widget',
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'existing_widget' => 'Reference HA widget',
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
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
	public function testDashboardHostAvailabilityWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10110');
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
			// Check that widget changes didn't take place after pressing "Cancel".
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

	public function testDashboardHostAvailabilityWidget_Delete() {
		$name = 'Reference HA widget to delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10110');
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget($name);
		$dashboard->deleteWidget($name);
		$this->page->waitUntilReady();

		$dashboard->save();
		// Check that Dashboard has been saved
		$this->checkDashboardUpdateMessage();
		// Confirm that widget is not present on dashboard
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());
		// Check that widget is removed from DB.
		$widget_sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	private function checkWidgetContent($data, $header) {
		if (array_key_exists('Interface type', $data['fields']) && !is_array($data['fields']['Interface type'])) {
			$this->checkSingleInterfaceWidgetContent($data, $header);
		}
		else {
			$this->checkMultipleInterfacesWidgetContent($data, $header);
		}
	}

	/*
	 * Function that compares the data returned in the widget with values in dataprovider or with values taken from DB,
	 * if only 1 interface is returned in the widget.
	 */
	private function checkSingleinterfaceWidgetContent($data, $header) {
		// Get widget content.
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);

		// Verify that layout is correct.
		if (CTestArrayHelper::get($data['fields'], 'Layout', 'Horizontal') === 'Horizontal') {
			$this->assertEquals($widget->query('class:totals-list-horizontal')->count(), 1);
			$this->assertEquals($widget->query('class:totals-list-vertical')->count(), 0);
		}
		else {
			$this->assertEquals($widget->query('class:totals-list-horizontal')->count(), 0);
			$this->assertEquals($widget->query('class:totals-list-vertical')->count(), 1);
		}

		$results = [];
		$classes = [
			'Available' => 'host-avail-true',
			'Not available' => 'host-avail-false',
			'Unknown' => 'host-avail-unknown',
			'Total' => 'host-avail-total'
		];

		// Get the count of hosts in each state that is returned by the widget.
		foreach ($classes as $key => $class) {
			$xpath = 'xpath:.//div[@class='.CXPathHelper::escapeQuotes($class).']/span';
			$results[$key] = $widget->query($xpath)->one()->getText();
		}

		// If expected values defined in Data provider, check that they match the values shown in the widget.
		if (CTestArrayHelper::get($data, 'expected_values', false)) {
			$this->assertEquals($data['expected_values'], $results);
		}
		// Else get expected values from DB.
		else {
			$db_values = $this->getExpectedInterfaceCountFromDB($data, $data['fields']['Interface type']);
			// Verify that values from the widget match values from DB.
			$this->assertEquals($db_values, $results);
		}
	}

	/*
	 * Function that compares the data returned in the widget with values in dataprovider or with values taken from DB,
	 * if there are more than 2 interfaces returned in the widget.
	 */
	private function checkMultipleInterfacesWidgetContent($data, $header) {
		$default_interfaces = [
			'type' => ['Zabbix agent', 'SNMP', 'JMX', 'IPMI'],
			'status' => ['Available', 'Not available', 'Unknown', 'Total']
		];
		// Get widget content.
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$content = $widget->getContent()->asTable();

		$interfaces = CTestArrayHelper::get($data, 'fields.Interface type', $default_interfaces['type']);
		// Index table with widget content by interface type, that is located in a column with a blank name.
		$rows = $content->index('');
		$table_headers = $content->getHeadersText();
		// Exclude the 1st header that is always empty from the list of actual headers.
		array_shift($table_headers);

		if (CTestArrayHelper::get($data['fields'], 'Layout', 'Horizontal') === 'Horizontal') {
			$column_headers = $default_interfaces['status'];
			$row_headers = $interfaces;
		}
		else {
			$column_headers = $interfaces;
			$row_headers = $default_interfaces['status'];
		}
		$this->assertSame($column_headers, $table_headers);

		// Check widget content based on its Layout parameter.
		if (array_key_exists('expected_values', $data)) {
			$this->assertEquals($data['expected_values'], $rows);
		}
		else {
			// Take reference values from DB takin into account widget layout parameter.
			foreach ($row_headers as $header) {
				$db_values = $this->getExpectedInterfaceCountFromDB($data, $header);
				$this->assertEquals($rows[$header], $db_values);
			}
		}
	}

	private function checkRefreshInterval($data, $header) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (15 minutes)')
			? '15 minutes'
			: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '15 minutes'));
		$this->assertEquals($refresh, $widget->getRefreshInterval());
	}

	/*
	 * Get the number of interfaces by their type or status, depending on the layout of the HA widget.
	 * For horizontal layout interface type is passed to the function, but for vertical layout - interface status.
	 */
	private function getExpectedInterfaceCountFromDB($data, $header) {
		$db_interfaces = [
			'type' => [
				'Zabbix agent' => 1,
				'SNMP' => 2,
				'IPMI' => 3,
				'JMX' => 4
			],
			'interface' => [
				'Zabbix agent' => 'available',
				'SNMP' => 'available',
				'IPMI' => 'available',
				'JMX' => 'available'
			],
			'status' => [
				'Unknown' => 0,
				'Available' => 1,
				'Not available' => 2
			]
		];
		// Select unique hostids for certain type of interfaces
		$interfaces_sql = 'SELECT DISTINCT(hostid) FROM interface WHERE type=';
		// Select hostids for host entries that are not templates or proxies and that are not host prototypes.
		$hosts_sql = 'SELECT hostid FROM hosts WHERE status IN (0,1) AND flags!=2';
		// Construct sql for horizontal widget layout.
		if (CTestArrayHelper::get($data, 'fields.Layout', 'Horizontal') === 'Horizontal') {
			$total_sql = $interfaces_sql.$db_interfaces['type'][$header].' AND hostid IN ('.$hosts_sql;
			// Filter out hosts in maintenance if the flag 'Show hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data['fields'], 'Show hosts in maintenance', false) === false) {
				$total_sql = $total_sql.' AND maintenance_status=0';
			}
			// Add interface status flag. Possible values: 0 - unknown, 1 - available, 2 - not available.
			$db_values = [
				'Available' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=1)'),
				'Not available' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=2)'),
				'Unknown' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=0)'),
				'Total' => CDBHelper::getCount($total_sql.')')
			];
		}
		// Construct sql for vertical widget layout.
		else {
			// Filter out hosts in maintenance if the flag 'Show hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data['fields'], 'Show hosts in maintenance', false) === false) {
				$hosts_sql = $hosts_sql.' AND maintenance_status=0';
			}
			// The SQL for Total interface number doesn't use interface status and needs to be constructed separately.
			if ($header === 'Total'){
				$db_values = [
					'Zabbix agent' => CDBHelper::getCount($interfaces_sql.'1 AND hostid IN ('.$hosts_sql.')'),
					'SNMP' => CDBHelper::getCount($interfaces_sql.'2 AND hostid IN ('.$hosts_sql.')'),
					'IPMI' => CDBHelper::getCount($interfaces_sql.'3 AND hostid IN ('.$hosts_sql.')'),
					'JMX' => CDBHelper::getCount($interfaces_sql.'4 AND hostid IN ('.$hosts_sql.')')
				];
			}
			else {
				// Add interface status flag based on interface type.
				$db_values = [
					'Zabbix agent' => CDBHelper::getCount($interfaces_sql.'1 AND available='.
							$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
					'SNMP' => CDBHelper::getCount($interfaces_sql.'2 AND available='.
							$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
					'IPMI' => CDBHelper::getCount($interfaces_sql.'3 AND available='.
							$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
					'JMX' => CDBHelper::getCount($interfaces_sql.'4 AND available='.
							$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')')
				];
			}
		}

		return $db_values;
	}
}
