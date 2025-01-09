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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup profiles
 */
class testDashboardHostAvailabilityWidget extends testWidgets {

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
			// #0 Create a Host availability widget with default values.
			[
				[
					'fields' => [
						'Type' => 'Host availability'
					]
				]
			],
			// #1 Create a Host availability widget with default values for Zabbix agent interface.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Single interface widget - default',
						'Interface type' => 'Zabbix agent (passive checks)'
					]
				]
			],
			// #2 Create a Host availability widget for Zabbix agent interface specifying every parameter.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Include hosts in maintenance',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent (passive checks)',
						'Include hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '6',
						'Available' => '2',
						'Not available' => '2',
						'Unknown' => '2',
						'Mixed' => '0'
					]
				]
			],
			// #3 Create the same widget as previous one, but with 'Include hosts in maintenance' = false.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Dont include hosts in maintenance',
						'Refresh interval' => '10 minutes',
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent (passive checks)',
						'Include hosts in maintenance' => false
					],
					'expected_values' => [
						'Total' => '3',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '1',
						'Mixed' => '0'
					]
				]
			],
			// #4 Create a Host availability widget that displays only IPMI interfaces including the ones in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Host availability widget - IPMI with maintenance',
						'Refresh interval' => '10 seconds',
						'Interface type' => 'IPMI',
						'Include hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '7',
						'Available' => '2',
						'Not available' => '2',
						'Unknown' => '3',
						'Mixed' => '0'
					]
				]
			],
			// #5 Create a Host availability widget that displays SNMP interface.
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
						'Total' => '5',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '3',
						'Mixed' => '0'
					]
				]
			],
			// #6 Create a Host availability widget that displays JMX interface.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Host availability widget - JMX',
						'Refresh interval' => '10 minutes',
						'Interface type' => 'JMX'
					],
					'expected_values' => [
						'Total' => '5',
						'Available' => '1',
						'Not available' => '1',
						'Unknown' => '3',
						'Mixed' => '0'
					]
				]
			],
			// #7 Create a Host availability widget with all 4 interfaces selected.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'All interfaces selected',
						'Layout' => 'Vertical',
						'Interface type' => ['Zabbix agent (passive checks)', 'SNMP', 'JMX', 'IPMI']
					],
					'vertical_interfaces' => ['Agent (passive)', 'SNMP', 'JMX', 'IPMI']
				]
			],
			// #8 Create a Host availability widget that displays SNMP, JMX, IPMI interface and display hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'HA widget - include hosts in maintenance SNMP + JMX + IPMI',
						'Refresh interval' => '1 minute',
						'Include hosts in maintenance' => true,
						'Interface type' => ['SNMP', 'JMX', 'IPMI']
					],
					'expected_values' => [
						'Total Hosts' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '3',
							'Total' => '7'
						],
						'SNMP' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '3',
							'Total' => '7'
						],
						'JMX' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '3',
							'Total' => '7'
						],
						'IPMI' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '3',
							'Total' => '7'
						]
					]
				]
			],
			// #9 Create a Host availability widget with Vertical layout that displays SNMP, JMX and IPMI interfaces.
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
							'Total Hosts' => '1',
							'SNMP' => '1',
							'JMX' => '1',
							'IPMI' => '1'
						],
						'Not available' => [
							'Total Hosts' => '1',
							'SNMP' => '1',
							'JMX' => '1',
							'IPMI' => '1'
						],
						'Mixed' => [
							'Total Hosts' => '0',
							'SNMP' => '0',
							'JMX' => '0',
							'IPMI' => '0'
						],
						'Unknown' => [
							'Total Hosts' => '3',
							'SNMP' => '3',
							'JMX' => '3',
							'IPMI' => '3'
						],
						'Total' => [
							'Total Hosts' => '5',
							'SNMP' => '5',
							'JMX' => '5',
							'IPMI' => '5'
						]
					]
				]
			],
			// #10 HA widget that displays SNMP, JMX and SNMP interfaces for host group with only Zabbix agent interfaces.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Misconfiguration - zeros should be returned',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group to check Overview'],
						'Interface type' => ['SNMP', 'JMX', 'IPMI'],
						'Include hosts in maintenance' => true
					],
					'expected_values' => [
						'Total Hosts' =>
							[
							'Available' => '0',
							'Not available' => '0',
							'Mixed' => '0',
							'Unknown' => '0',
							'Total' => '0'
						],
						'SNMP' => [
							'Available' => '0',
							'Not available' => '0',
							'Mixed' => '0',
							'Unknown' => '0',
							'Total' => '0'
						],
						'JMX' => [
							'Available' => '0',
							'Not available' => '0',
							'Mixed' => '0',
							'Unknown' => '0',
							'Total' => '0'
						],
						'IPMI' => [
							'Available' => '0',
							'Not available' => '0',
							'Mixed' => '0',
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1010');
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
			// #0 Update the widget to return info for group in maintenance and remove "Include hosts in maintenance" flag.
			[
				[
					'fields' => [
						'Name' => 'Should return zeros',
						'Refresh interval' => '1 minute',
						'Host groups' => ['Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent (passive checks)',
						'Include hosts in maintenance' => false
					],
					'expected_values' => [
						'Total' => '0',
						'Available' => '0',
						'Mixed' => '0',
						'Not available' => '0',
						'Unknown' => '0'
					]
				]
			],
			// #1 Update the widget to return info for group in maintenance and set "Include hosts in maintenance" flag.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Return hosts in maintenance',
						'Refresh interval' => '10 seconds',
						'Host groups' => ['Group in maintenance for Host availability widget'],
						'Interface type' => 'Zabbix agent (passive checks)',
						'Include hosts in maintenance' => true
					],
					'expected_values' => [
						'Total' => '3',
						'Available' => '1',
						'Mixed' => '0',
						'Not available' => '1',
						'Unknown' => '1'
					]
				]
			],
			// #2 Update the layout of the widget to Vertical and set Interface type to JMX.
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
						'Total' => '5',
						'Available' => '1',
						'Mixed' => '0',
						'Not available' => '1',
						'Unknown' => '3'
					]
				]
			],
			// #3 Setting name of the widget to default value.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => '',
						'Refresh interval' => 'Default (15 minutes)',
						'Interface type' => 'Zabbix agent (passive checks)'
					]
				]
			],
			// #4 Update the widget to include hosts in maintenance option without defining host groups.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts',
						'Refresh interval' => 'No refresh',
						'Interface type' => 'Zabbix agent (passive checks)',
						'Include hosts in maintenance' => true
					]
				]
			],
			// #5 Update the widget to display host availability for 2 interface types including hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts for Zabbix agent and SNMP',
						'Refresh interval' => '10 seconds',
						'Interface type' => ['Zabbix agent (passive checks)', 'SNMP'],
						'Include hosts in maintenance' => true
					],
					'vertical_interfaces' => ['Agent (passive)', 'SNMP']
				]
			],
			// #6 Update the widget to display host availability for 3 interface types excluding hosts in maintenance.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show hosts for Zabbix agent, SNMP and JMX',
						'Refresh interval' => '2 minutes',
						'Interface type' => ['Zabbix agent (passive checks)', 'SNMP', 'JMX']
					],
					'vertical_interfaces' => ['Agent (passive)', 'SNMP', 'JMX']
				]
			],
			// #7 Update the widget to display host availability for all interfaces including hosts in maintenance. Layout = Vertical
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show completely all hosts for all interfaces',
						'Refresh interval' => '2 minutes',
						'Interface type' => ['Zabbix agent (passive checks)', 'SNMP', 'JMX', 'IPMI'],
						'Include hosts in maintenance' => true,
						'Layout' => 'Vertical'
					],
					'vertical_interfaces' => ['Agent (passive)', 'SNMP', 'JMX', 'IPMI']
				]
			],
			// #8 Display host availability for all interfaces for 2 host groups.
			[
				[
					'fields' => [
						'Type' => 'Host availability',
						'Name' => 'Show all hosts for all interfaces of 2 groups',
						'Refresh interval' => '10 minutes',
						'Host groups' => ['Group for Host availability widget', 'Group in maintenance for Host availability widget'],
						'Include hosts in maintenance' => true
					],
					'expected_values' => [
						'Total Hosts' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '2',
							'Total' => '6'
						],
						'Agent (active)' => [
							'Available' => '0',
							'Not available' => '0',
							'Mixed' => '-',
							'Unknown' => '0',
							'Total' => '0'
						],
						'Agent (passive)' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '2',
							'Total' => '6'
						],
						'SNMP' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '0',
							'Total' => '4'
						],
						'JMX' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
							'Unknown' => '0',
							'Total' => '4'
						],
						'IPMI' => [
							'Available' => '2',
							'Not available' => '2',
							'Mixed' => '0',
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1010');
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1010');
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

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1010');
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

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1010');
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
			'Mixed' => 'host-avail-mixed',
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
			if ($data['fields']['Interface type'] === 'Zabbix agent (active checks)') {
				$interface_type = 'Agent (active)';
			}
			elseif ($data['fields']['Interface type'] === 'Zabbix agent (passive checks)') {
				$interface_type = 'Agent (passive)';
			}
			else {
				$interface_type = $data['fields']['Interface type'];
			}

			$db_values = $this->getExpectedInterfaceCountFromDB($data['fields'], $interface_type);
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
			'type' => ['Agent (active)', 'Agent (passive)', 'SNMP', 'JMX', 'IPMI'],
			'status' => ['Available', 'Not available', 'Mixed', 'Unknown', 'Total']
		];
		// Get widget content.
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$content = $widget->getContent()->asTable();


		$interfaces = (array_key_exists('vertical_interfaces', $data))
			? $data['vertical_interfaces']
			: CTestArrayHelper::get($data, 'fields.Interface type', $default_interfaces['type']);

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
			array_unshift($column_headers, 'Total Hosts');
			$row_headers = $default_interfaces['status'];
		}
		$this->assertSame($column_headers, $table_headers);

		// Check widget content based on its Layout parameter.
		if (array_key_exists('expected_values', $data)) {
			$this->assertEquals($data['expected_values'], $rows);
		}
		else {
			// Take reference values from DB taking into account widget layout parameter.
			foreach ($row_headers as $header) {
				$db_values = $this->getExpectedInterfaceCountFromDB($data['fields'], $header);
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
}
