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


/**
 * @backup widget
 * @backup profiles
 * @dataSource ClockWidgets
 */

class testDashboardClockWidget extends CWebTest {

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
	' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboardid, w.type, w.name, w.x, w.y,'.
	' w.width, w.height'.
	' FROM widget_field wf'.
	' INNER JOIN widget w'.
	' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
	' wf.value_itemid, wf.value_graphid';

	public static function getCreateData() {
		return [
			[
				[
					'MandatoryFields' => [
						'Type' => 'Clock',
						'Name' => 'ClockWidgetCreatedByAutotest'
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
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill($data['MandatoryFields']);
		$this->query('button', 'Add')->waitUntilClickable()->one()->click();
		$dashboard->save();
	}

	/**
	 * Check clock widgets successful simple update.
	 */
	public function testDashboardClockWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('ClockWidgetCreatedByAutotest')->edit();
		$form->submit();
		$this->page->waitUntilReady();
		$dashboard->getWidget('ClockWidgetCreatedByAutotest');
		$dashboard->save();
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getUpdateData() {
		return [
			[
				[
					'UpdateFields' => [
						'Type' => 'Clock',
						'Name' => 'UpdatedClockWidget'
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
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('ClockWidgetCreatedByAutotest')->edit();
		$form->fill($data['UpdateFields'])->waitUntilReady();
		$form->submit();
		$this->page->waitUntilReady();

		// Check that a widget with the corresponding header exists.
		$header = ($data['UpdateFields']['Name']);
		$dashboard->getWidget($header);
		$dashboard->save();

		// Check that Dashboard has been saved and that widget has been added.
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
		$this->assertEquals($dashboard->getWidget($header)->getText(), 'UpdatedClockWidget');
	}

	/**
	 * Check clock widgets successful copy.
	 */
	public function testDashboardClockWidget_Copy() {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Get size of widget which will be copied;
		$sql = "Select width, height from widget where name =".zbx_dbstr('Server')." and dashboardid =".$dashboardid." ORDER BY widgetid DESC";
		$original_size = CDBHelper::getRow($sql);

		$dashboard->copyWidget('Server');
		$dashboard->edit();
		$dashboard->pasteWidget();
		sleep(5);
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$dashboard->save();
		$this->page->waitUntilReady();

		// Get size of widget which has been copied;
		$copied_size = CDBHelper::getRow($sql);
		$this->assertEquals($original_size, $copied_size);
	}

	public static function getDeleteData() {
		return [
			[
				[
					'Fields' => [
						'Type' => 'Clock',
						'Name' => 'Local'
					]
				]
			]
		];
	}

	/**
	 * Check clock widgets deletion.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testDashboardClockWidget_Delete($data) {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget($data['Fields']['Name']);
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget($data['Fields']['Name']);
		$dashboard->save();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($data['Fields']['Name'], false)->isValid());
		$sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr($data['Fields']['Name']);
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
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Start creating a widget.
			$overlay = $dashboard->edit()->addWidget();
			$form = $overlay->asForm();
			$form->getField('Type')->fill('Clock');
			$form->getField('Name')->fill('Widget to be cancelled');
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
