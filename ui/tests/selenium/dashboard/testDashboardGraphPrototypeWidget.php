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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup widget, profiles
 */
class testDashboardGraphPrototypeWidget extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
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

	const DASHBOARD_ID = 1400;
	const SCREENSHOT_DASHBOARD_ID = 1410;

	private static $previous_widget_name = 'Graph prototype widget for update';

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Graph prototype' => [
							'values' => 'testFormGraphPrototype1',
							'context' => [
								'values' => 'Simple form test host',
								'context' => 'Zabbix servers'
							]
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Name' => 'Simple graph prototype'.microtime(),
						'Source' => 'Simple graph prototype',
						'Item prototype' => [
							'values' => 'testFormItemPrototype1',
							'context' => [
								'values' => 'Simple form test host',
								'context' => 'Zabbix servers'
							]
						]
					],
					'show_header' => false
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Name' => 'Graph prototype widget with all possible fields filled'.microtime(),
						'Refresh interval' => 'No refresh',
						'Source' => 'Simple graph prototype',
						'Item prototype' => 'testFormItemPrototype2',
						'Show legend' => true,
						'Dynamic item' => true,
						'Columns' => '3',
						'Rows' => '2'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype'
					],
					'error' => ['Invalid parameter "Graph prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Simple graph prototype'
					],
					'error' => ['Invalid parameter "Item prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'testFormGraphPrototype1',
						'Columns' => '0',
						'Rows' => '0'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-24.',
						'Invalid parameter "Rows": value must be one of 1-16.'
					]
				]
			],
						[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Graph prototype',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'testFormGraphPrototype1',
						'Columns' => '25',
						'Rows' => '17'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-24.',
						'Invalid parameter "Rows": value must be one of 1-16.'
					]
				]
			]
		];
	}

	/**
	 * TODO remove after DEV-1535 is fixed.
	 */
	public function cleanupProfile() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'web.popup.generic.%\'');
	}

	/**
	 * Test for checking new Graph prototype widget creation.
	 *
	 * @onAfter cleanupProfile
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardGraphPrototypeWidget_Create($data) {
		$this->checkGraphPrototypeWidget($data);
	}

	/**
	 * Test for checking existing Graph prototype widget update.
	 *
	 * @onAfter cleanupProfile
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardGraphPrototypeWidget_Update($data) {
		$this->checkGraphPrototypeWidget($data, true);
	}

	/**
	 * Test for checking Graph prototype widget update without any changes.
	 */
	public function testDashboardGraphPrototypeWidget_SimpleUpdate() {
		$this->checkDataUnchanged('Apply', true);
	}

	/**
	 * Test for checking Graph prototype creation cancelling.
	 */
	public function testDashboardGraphPrototypeWidget_CancelCreate() {
		$this->checkDataUnchanged('Cancel', false, true);
	}

	/**
	 * Test for checking Graph prototype cancelling form changes.
	 */
	public function testDashboardGraphPrototypeWidget_CancelChanges() {
		$this->checkDataUnchanged('Cancel', true, true);
	}

	/**
	 * Test for checking Graph prototype widget cancelling without making any changes.
	 */
	public function testDashboardGraphPrototypeWidget_CancelNoChanges() {
		$this->checkDataUnchanged('Cancel', true);
	}

	/**
	 * Test for checking delete of Graph prototype widget.
	 */
	public function testDashboardGraphPrototypeWidget_Delete() {
		$name = 'Graph prototype widget for delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget($name);
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget($name);

		$dashboard->save();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());
		$sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test for comparing widgets form screenshot.
	 */
	public function testDashboardGraphPrototypeWidget_FormScreenshot() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::SCREENSHOT_DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		if ($form->getField('Type')->getText() !== 'Graph prototype') {
			$form->fill(['Type' => 'Graph prototype']);
			$form->waitUntilReloaded();
		}
		$this->page->removeFocus();
		sleep(1);
		$dialog = COverlayDialogElement::find()->one();
		$this->assertScreenshot($dialog);
	}

	public static function getWidgetScreenshotData() {
		return [
			[
				[
					'screenshot_id' => 'default'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '3',
						'Rows' => '1'
					],
					'screenshot_id' => '3x1'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '2',
						'Rows' => '2'
					],
					'screenshot_id' => '2x2'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '1'
					],
					'screenshot_id' => '16x1'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '2'
					],
					'screenshot_id' => '16x2'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '3'
					],
					'screenshot_id' => 'stub16x3'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '17',
						'Rows' => '2'
					],
					'screenshot_id' => 'stub17x2'
				]
			]
		];
	}

	/**
	 * Test for comparing widgets grid screenshots.
	 * @backup widget
	 * @dataProvider getWidgetScreenshotData
	 */
	public function testDashboardGraphPrototypeWidget_GridScreenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::SCREENSHOT_DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$widget = [
			'Name' => 'Screenshot Widget',
			'Graph prototype' => 'testFormGraphPrototype1'
		];
		if ($form->getField('Type')->getText() !== 'Graph prototype') {
			$form->fill(['Type' => 'Graph prototype']);
			$form->waitUntilReloaded();
		}
		$form->fill($widget);
		if (array_key_exists('fields', $data)){
			$form->fill($data['fields']);
		}
		$form->submit();
		$dashboard->getWidget($widget['Name']);
		$dashboard->save();
		$this->page->removeFocus();
		sleep(1);
		$screenshot_area = $this->query('class:dashboard-grid')->one();
		$screenshot_area->query('xpath:.//div[contains(@class, "dashboard-grid-iterator-focus")]')->waitUntilNotVisible();
		$this->assertScreenshot($screenshot_area, $data['screenshot_id']);
	}

	private function checkGraphPrototypeWidget($data, $update = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$previous_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();
		COverlayDialogElement::find()->one()->waitUntilReady();

		if (array_key_exists('show_header', $data)) {
			$form->query('xpath:.//input[@id="show_header"]')->asCheckbox()->one()->fill($data['show_header']);
		}

		$form->fill($data['fields']);
		// After changing "Source", the overlay is reloaded.
		COverlayDialogElement::find()->one()->waitUntilReady();

		$type = array_key_exists('Item prototype', $data['fields']) ? 'Item prototype' : 'Graph prototype';

		if (!array_key_exists('Graph prototype', $data['fields']) && !array_key_exists('Item prototype', $data['fields'])) {
			$form->query('xpath:.//div[@id="graphid" or @id="itemid"]')->asMultiselect()->one()->clear();
		}

		$values = $form->getFields()->asValues();
		$form->submit();

		switch ($data['expected']) {
			case TEST_GOOD:
				COverlayDialogElement::ensureNotPresent();
				// Introduce name for finding saved widget in DB.
				$db_name = CTestArrayHelper::get($data, 'fields.Name', $update ? self::$previous_widget_name : '');

				// Make sure that the widget is present before saving the dashboard.
				if (!array_key_exists('Name', $data['fields'])) {
					$data['fields']['Name'] = $update
							? self::$previous_widget_name
							: $data['fields'][$type]['context']['values'].': '.$data['fields'][$type]['values'];
				}
				$dashboard->getWidget($data['fields']['Name']);
				$dashboard->save();

				// Check that Dashboard has been saved and that widget has been added.
				$this->assertMessage($data['expected'], 'Dashboard updated');
				$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

				// Check that widget is saved in DB.
				$db_count = CDBHelper::getCount('SELECT * FROM widget w'.
					' WHERE EXISTS ('.
						'SELECT NULL'.
						' FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.self::DASHBOARD_ID.
							' AND w.name ='.zbx_dbstr($db_name).
					')'
				);

				$this->assertEquals(1, $db_count);

				// Verify widget content
				$widget = $dashboard->getWidget($data['fields']['Name']);
				$this->assertTrue($widget->getContent()->isValid());

				// Compare placeholders count in data and created widget.
				$expected_placeholders_count =
						(CTestArrayHelper::get($data['fields'], 'Columns') && CTestArrayHelper::get($data['fields'], 'Rows'))
						? $data['fields']['Columns'] * $data['fields']['Rows']
						: 2;
				$placeholders_count = $widget->query('class:dashboard-grid-iterator-placeholder')->count();
				$this->assertEquals($expected_placeholders_count, $placeholders_count);
				// Check Dynamic item setting on Dashboard.
				if (CTestArrayHelper::get($data['fields'], 'Dynamic item')) {
					$this->assertTrue($dashboard->getControls()->query('xpath://form[@aria-label = '.
						'"Main filter"]')->one()->isPresent());
				}
				// Check widget form fields and values.
				$this->assertEquals($values, $widget->edit()->getFields()->asValues());

				// Write widget name to variable to use it in next Update test case.
				if ($update) {
					self::$previous_widget_name = CTestArrayHelper::get($data, 'fields.Name', 'Graph prototype widget for update');
				}
				break;
			case TEST_BAD:
				$this->assertMessage($data['expected'], null, $data['error']);
				break;
		}
	}

	/**
	 * Function for checking editing widget form without changes.
	 *
	 * @param string $action	name of button tested
	 * @param boolean $update	is this updating of existing widget
	 * @param boolean $changes	are there any changes made in widget form
	 */
	private function checkDataUnchanged($action, $update = false, $changes = false) {
		$initial_values = CDBHelper::getHash($this->sql);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();

		$form = $update
			? $dashboard->getWidget(self::$previous_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		if ($update) {
			$original_values = $form->getFields()->asValues();
		}

		$dialog = COverlayDialogElement::find()->one();

		if ($changes) {
			$form->fill([
					'Type' => 'Graph prototype',
					'Name' => 'Name for Cancelling',
					'Refresh interval' => 'No refresh',
					'Source' => 'Simple graph prototype',
					'Item prototype' => 'testFormItemPrototype2',
					'Show legend' => false,
					'Dynamic item' => true,
					'Columns' => '3',
					'Rows' => '2'
				]);
		}

		$dialog->query('button', $action)->one()->click();
		$this->page->waitUntilReady();

		if ($update) {
			$dashboard->getWidget(self::$previous_widget_name);
		}

		$dashboard->save();
		// Check that Dashboard has been saved and that there are no changes made to the widgets.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		if ($update) {
			$new_values = $dashboard->getWidget(self::$previous_widget_name)->edit()->getFields()->asValues();
			$this->assertEquals($original_values, $new_values);
		}

		$this->assertEquals($initial_values, CDBHelper::getHash($this->sql));
	}
}


