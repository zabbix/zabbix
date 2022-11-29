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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup widget
 * @backup profiles
 * @dataSource ClockWidgets
 */

class testDashboardClockWidget extends CWebTest {

	private static $name = 'UpdateClock';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
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

	/**
	 * Check clock widgets layout.
	 */
	public function testDashboardClockWidget_CheckLayout() {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('LayoutClock')->edit();

		// Check edit forms header.
		$this->assertEquals('Edit widget',
			$form->query('xpath://h4[@id="dashboard-widget-head-title-widget_properties"]')->one()->getText());

		// Check if widget type is selected as "Clock".
		$form->checkValue(['Type' => 'Clock']);

		// Check "Name" field max length.
		$this->assertEquals('255', $form->query('id:name')->one()->getAttribute('maxlength'));

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (15 minutes)', 'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '15 minutes'],
			$form->query('name', 'rf_rate')->asDropdown()->one()->getOptions()->asText()
		);

		$this->assertEquals(['Local time', 'Server time', 'Host time'],
			$form->query('name', 'time_type')->asDropdown()->one()->getOptions()->asText()
		);

		// Check fields "Time type" values.
		$timetype_values = ['Local time', 'Server time', 'Host time'];
		$tt_dropdown = $form->query('name', 'time_type')->asDropdown()->one();
		$this->assertEquals($timetype_values, $tt_dropdown->getOptions()->asText());

		// Check that it's possible to select host items, when time type is "Host Time".
		$form->fill(['Time type' => 'Host time']);
		$fields = ['Type', 'Name', 'Refresh interval', 'Time type'];
		foreach (['Local time', 'Server time', 'Host time'] as $type) {
			$form->fill(['Time type' => CFormElement::RELOADABLE_FILL($type)]);
			$fields = ($type === 'Host time') ? array_merge($fields, ['Item']) : $fields;
			$this->assertEquals($fields, $form->getLabels()->asText());
		}

		// Check that it's possible to change the status of "Show header" checkbox.
		$this->assertTrue($form->query('xpath://input[contains(@id, "show_header")]')->one()->isSelected());

		// Check that clock widget with "Time Type" - "Host time", displays host name, when clock widget name is empty.
		$form = $dashboard->getWidget('LayoutClock')->edit();
		$form->fill(['Name' => '']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$hostname = $dashboard->getWidget('Host for clock widget')->getText();
		$this->assertEquals("Host for clock widget", $hostname);

		// Update widget back to it's original name.
		$form = $dashboard->getWidget('Host for clock widget')->edit();
		$form->fill(['Name' => 'LayoutClock']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();

		// Check if Apply and Cancel button are clickable.
		$dashboard->getWidget('LayoutClock')->edit();
		foreach (['Apply', 'Cancel'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isClickable());
		}
	}

	public static function getCreateData() {
		return [
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'ServerTimeClock',
						'Refresh interval' => 'No refresh',
						'Time type' => 'Server time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => false,
						'Name' => 'LocalTimeClock',
						'Refresh interval' => '10 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'HostTimeClock',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Host time',
						'Item' => 'Item for clock widget'
					]
				]
			],
			[
				[
					'Expected' => TEST_BAD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => false,
						'Name' => 'ClockWithoutItem',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Host time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'LocalTimeClock123',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => false,
						'Name' => 'Symb0l$InN@m3Cl0ck',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => '1233212',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => false,
						'Name' => '~@#$%^&*()_+|',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			]
		];
	}

	/**
	 * Check clock widget successful creation.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardClockWidget_Create($data) {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill($data['Fields']);

		if($data['Expected'] === TEST_GOOD) {
			$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilReady()->one()->click();
			//$form->query('button', 'Add')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Get fields from saved widgets.
			$fields = $dashboard->getWidget($data['Fields']['Name'])->edit()->getFields();
			$original_widget = $fields->asValues();

			// Check if added widgets are truly added and fields are filled as expected.
			$fields = $dashboard->getWidget($data['Fields']['Name'])->edit()->getFields();
			$created_widget = $fields->asValues();
			$this->assertEquals($original_widget, $created_widget);
		}
		else{
			$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilReady()->one()->click();
			$this->assertMessage(TEST_BAD, null, 'Invalid parameter "Item": cannot be empty.');
			$form->getOverlayMessage()->close();
			$this->query('button', 'Cancel')->waitUntilClickable()->one()->click();
		}
	}

	/**
	 * Check clock widgets successful simple update.
	 */
	public function testDashboardClockWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget('UpdateClock')->edit();
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		//$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getUpdateData() {
		return [
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'ServerTimeClockForUpdate',
						'Refresh interval' => 'No refresh',
						'Time type' => 'Server time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'LocalTimeClockForUpdate',
						'Refresh interval' => '10 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'HostTimeClockForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Host time',
						'Item' => 'Item for clock widget'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'LocalTimeClock123ForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'Symb0l$InN@m3Cl0ckForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => '1233212ForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_GOOD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => '~@#$%^&*()_+|ForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Local time'
					]
				]
			],
			[
				[
					'Expected' => TEST_BAD,
					'Fields' => [
						'Type' => 'Clock',
						'Show header' => true,
						'Name' => 'ClockWithoutItemForUpdate',
						'Refresh interval' => '30 seconds',
						'Time type' => 'Host time'
					]
				]
			]
		];
	}

	/**
	 * Check clock widgets successful update.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testDashboardClockWidget_Update($data) {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Get widget fields before they are updated.
		$fields = $dashboard->getWidget(self::$name)->edit()->getFields();
		$original_widget = $fields->asValues();

		$form = $dashboard->getWidget(self::$name)->edit();
		$form->fill($data['Fields']);

		if($data['Expected'] === TEST_GOOD) {
			$this->query('button', 'Apply')->waitUntilReady()->one()->click();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Use updated widget as next widget which will be updated.
			if(array_key_exists('Name', $data['Fields'])) {
				self::$name = $data['Fields']['Name'];
			}

			// Confirm that clock widget is truly updated.
			$this->assertEquals($dashboard->getWidget(self::$name)->getText(), self::$name);

			// Get fields from updated widgets.
			$fields = $dashboard->getWidget(self::$name)->edit()->getFields();
			$updated_widget = $fields->asValues();

			// Compare if widget fields are not equal with original widget fields.
			$this->assertNotEquals($original_widget, $updated_widget);
		}
		else{
			$this->query('button', 'Apply')->waitUntilReady()->one()->click();
			$this->assertMessage(TEST_BAD, null, 'Invalid parameter "Item": cannot be empty.');
			$form->getOverlayMessage()->close();
		}
	}

	/**
	 * Check clock widgets deletion.
	 */
	public function testDashboardClockWidget_Delete() {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget('DeleteClock');
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget('DeleteClock');
		$dashboard->save();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget('DeleteClock', false)->isValid());
		$sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr('DeleteClock');
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function getCancelData() {
		return [
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
	 * Check if it's possible to cancel creation of clock widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardClockWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Start creating a widget.
		$overlay = $dashboard->edit()->addWidget();
		$form = $overlay->asForm();
		$form->fill(['Type' => 'Clock', 'Name' => 'Widget to be cancelled']);
		$widget = $dashboard->getWidgets()->last();

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

		// Cancel update process of already existing widget.
		$dashboard->edit()->getWidget('CancelClock')->edit();
		$this->query('button', 'Cancel')->waitUntilClickable()->one()->click();

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
}
