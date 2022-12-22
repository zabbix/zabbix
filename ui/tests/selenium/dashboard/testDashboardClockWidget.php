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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup widget, profiles
 * @dataSource ClockWidgets
 */

class testDashboardClockWidget extends CWebTest {

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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
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

		// Check fields "Time type" values.
		$this->assertEquals(['Local time', 'Server time', 'Host time'],
			$form->query('name', 'time_type')->asDropdown()->one()->getOptions()->asText());

		// Check that it's possible to select host items, when time type is "Host Time".
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
		$this->assertEquals('Host for clock widget', $dashboard->getWidget('Host for clock widget')->getHeaderText());

		// Update widget back to it's original name and check if Apply and Cancel button are clickable.
		$form = $dashboard->getWidget('Host for clock widget')->edit();

		foreach (['Apply', 'Cancel'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isClickable());
		}

		$form->fill(['Name' => 'LayoutClock']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
	}

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_BAD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
					'expected' => TEST_GOOD,
					'fields' => [
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill($data['fields']);
		$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilReady()->one()->click();

		if ($data['expected'] === TEST_GOOD) {
			$this->page->waitUntilReady();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check that created widget has correct values fom dataprovider.
			if ($data['fields']['Time type'] === 'Host time') {
				$data['fields'] = array_replace($data['fields'], ['Item' => 'Host for clock widget: Item for clock widget']);
			}

			$dashboard->getWidget($data['fields']['Name'])->edit()->checkValue($data['fields']);
		} else {
			$this->assertMessage(TEST_BAD, null, 'Invalid parameter "Item": cannot be empty.');
			$form->getOverlayMessage()->close();
			$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilReady()->one()->click();
		}
	}

	/**
	 * Check clock widgets successful simple update.
	 */
	public function testDashboardClockWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for updating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget('UpdateClock')->edit();
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getUpdateData() {
		return [
			// #0 name and show header change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Name' => 'Changed name'
					]
				]
			],
			// #1 Refresh interval change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #2 Time type changed to Server time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Time type' => 'Server time'
					]
				]
			],
			// #3 Time type changed to Local time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Time type' => 'Local time'
					]
				]
			],
			// #4 Time type and refresh interval changed.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Time type' => 'Server time',
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #5 Empty name added.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					]
				]
			],
			// #6 Symbols/numbers name added.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '!@#$%^&*()1234567890-='
					]
				]
			],
			// #7 Cyrillic added in name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Имя кирилицей'
					]
				]
			],
			// #8 all fields changed.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Updated_name',
						'Refresh interval' => '10 minutes',
						'Time type' => 'Server time'
					]
				]
			],
			// #9 Host time without item.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time type' => 'Host time'
					]
				]
			],
			// #10 Time type with item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Time type' => 'Host time',
						'Item' => 'Item for clock widget'
					]
				]
			],
			// #11 Update item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Time type' => 'Host time',
						'Item' => 'Item for clock widget 2'
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
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for updating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidgets()->last()->edit();
		$form->fill($data['fields']);
		$form->query('xpath://button[normalize-space()="Apply"]')->waitUntilReady()->one()->click();

		if ($data['expected'] === TEST_GOOD) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Change item value to correct (with host name).
			if (array_key_exists('Item', $data['fields'])) {
				$item_name = ($data['fields']['Item'] === 'Item for clock widget')
					? 'Host for clock widget: Item for clock widget'
					: 'Host for clock widget: Item for clock widget 2';
				$data['fields'] = array_replace($data['fields'], ['Item' => $item_name]);
			}

			// Check that created widget has correct values fom dataprovider.
			$dashboard->getWidgets()->last()->edit()->checkValue($data['fields']);
		} else {
			$this->assertMessage(TEST_BAD, null, 'Invalid parameter "Item": cannot be empty.');
		}
	}

	/**
	 * Check clock widget deletion.
	 */
	public function testDashboardClockWidget_Delete() {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->edit()->getWidget('DeleteClock')->isEditable());
		$dashboard->deleteWidget('DeleteClock')->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget('DeleteClock', false)->isValid());
		$sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid' .
			' WHERE w.name=' . zbx_dbstr('DeleteClock');
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function getCancelData() {
		return [
			// Cancel update widget.
			[
				[
					'existing_widget' => 'CancelClock',
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'existing_widget' => 'CancelClock',
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
	 * Check if it's possible to cancel creation of clock widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardClockWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.Dashboard for creating clock widgets');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=' . $dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'existing_widget', false)) {
			$widget = $dashboard->getWidget($data['existing_widget']);
			$form = $widget->edit();
		} else {
			$overlay = $dashboard->edit()->addWidget();
			$form = $overlay->asForm();
			$form->fill(['Type' => 'Clock']);
			$widget = $dashboard->getWidgets()->last();
		}

		$form->fill(['Name' => 'Widget to be cancelled']);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();
			$this->page->waitUntilReady();

			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget('Widget to be cancelled')->isValid());
		} else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			// Check that widget changes didn't take place after pressing "Cancel".
			if (CTestArrayHelper::get($data, 'existing_widget', false)) {
				$this->assertNotEquals('Widget to be cancelled', $widget->waitUntilReady()->getHeaderText());
			} else {
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
		} else {
			$dashboard->cancelEditing();
		}

		// Confirm that no changes were made to the widget.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}
}
