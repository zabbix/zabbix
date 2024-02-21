<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 *
 * @onBefore prepareClockWidgetData
 */

class testDashboardClockWidget extends CWebTest {

	/**
	 * Id of the dashboard with widgets.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

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
			' ON w.widgetid=wf.widgetid '.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_itemid, wf.value_graphid';

	/**
	 * Create data for autotests which use ClockWidget.
	 *
	 * @return array
	 */
	public function prepareClockWidgetData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for clock widget'
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

		CDataHelper::call('host.create', [
			'host' => 'Host for clock widget',
			'groups' => [
				[
					'groupid' => $hostgrpid['Host group for clock widget']
				]
			],
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '192.168.3.217',
				'dns' => '',
				'port' => '10050'
			]
		]);
		$hostid = CDataHelper::getIds('host');
		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for clock widget']
		);

		CDataHelper::call('item.create', [
			[
				'hostid' => $hostid['Host for clock widget'],
				'name' => 'Item for clock widget',
				'key_' => 'system.localtime[local]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => $interfaceid,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for clock widget'],
				'name' => 'Item for clock widget 2',
				'key_' => 'system.localtime[local2]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => $interfaceid,
				'delay' => '5s'
			]
		]);
		$itemid = CDataHelper::getIds('name');

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating clock widgets',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'DeleteClock',
								'x' => 5,
								'y' => 0,
								'width' => 5,
								'height' => 5
							],
							[
								'type' => 'clock',
								'name' => 'CancelClock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5
							],
							[
								'type' => 'clock',
								'name' => 'LayoutClock',
								'x' => 10,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => $itemid['Item for clock widget']
									],
									[
										'type' => 0,
										'name' => 'time_type',
										'value' => 2
									]
								]
							]
						]
					],
					[
						'name' => 'Second page'
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 7,
						'permission' => 3
					]
				]
			],
			[
				'name' => 'Dashboard for updating clock widgets',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'UpdateClock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5
							]
						]
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 7,
						'permission' => 3
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
	}

	/**
	 * Check clock widgets layout.
	 */
	public function testDashboardClockWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);

		$form->checkValue([
			'Name' => '',
			'Refresh interval' => 'Default (15 minutes)',
			'Time type' => 'Local time',
			'id:show_header' => true
		]);

		// Check "Name" field max length.
		$this->assertEquals('255', $form->query('id:name')->one()->getAttribute('maxlength'));

		// Check fields "Refresh interval" and "Time type" values.
		$dropdowns = [
			'Refresh interval' => ['Default (15 minutes)',  'No refresh', '10 seconds', '30 seconds',
					'1 minute', '2 minutes', '10 minutes', '15 minutes'
			],
			'Time type' => ['Local time', 'Server time', 'Host time']
		];

		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Check that it's possible to select host items, when time type is "Host Time".
		$fields = ['Type', 'Name', 'Refresh interval', 'Time type'];

		foreach (['Local time', 'Server time', 'Host time'] as $type) {
			$form->fill(['Time type' => CFormElement::RELOADABLE_FILL($type)]);

			/**
			 * If the clock widgets type equals to "Host time", then additional field appears - 'Item',
			 * which requires to select item of the "Host", in this case array_splice function allows us to put
			 * this fields name into the array. Positive offset (4) starts from the beginning of the array,
			 * while - (0) length parameter - specifies how many elements will be removed.
			 */
			if ($type === 'Host time') {
				array_splice($fields, 4, 0, ['Item']);
				$form->checkValue(['Item' => '']);
				$form->isRequired('Item');
			}

			$this->assertEquals($fields, $form->getLabels()->filter(new CElementFilter(CElementFilter::VISIBLE))->asText());
		}

		// Check if Add and Cancel button are clickable and there are two of them.
		$dialog->invalidate();
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
	}

	/**
	 * Function checks specific scenario when Clock widget has "Time type" as "Host time"
	 * and name for widget itself isn't provided, after creating widget, host name should be displayed on widget as
	 * the widget name.
	 */
	public function testDashboardClockWidget_CheckClockWidgetsName() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('LayoutClock')->edit();
		$form->fill(['Name' => '']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertTrue($dashboard->getWidget('Host for clock widget', false)->isValid());
		$dashboard->getWidget('Host for clock widget')->edit()->fill(['Name' => 'LayoutClock']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals('LayoutClock', $dashboard->getWidget('LayoutClock')->getHeaderText());
	}

	public static function getClockWidgetCommonData() {
		return [
			// #0 Name and show header change.
			[
				[
					'check_dialog_properties' => true,
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Name and show header name'
					]
				]
			],
			// #1 Refresh interval change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '10 seconds',
						'Name' => 'Refresh interval change name'
					]
				]
			],
			// #2 Time type changed to Server time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type changed to Server time',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// #3 Time type changed to Local time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type changed to Local time',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time')
					]
				]
			],
			// #4 Time type and refresh interval changed.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Clock',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time'),
						'Refresh interval' => '10 seconds',
						'Name' => 'Time type and refresh interval changed'
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
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// #9 Host time without item.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show header' => false,
						'Name' => 'ClockWithoutItem',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time')
					],
					'Error message' => [
						'Invalid parameter "Item": cannot be empty.'
					]
				]
			],
			// #10 Time type with item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type with item',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget'
					]
				]
			],
			// #11 Update item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Update item',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget 2'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'HostTimeClock',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'LocalTimeClock123',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time')
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => '1233212',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time')
					]
				]
			]
		];
	}

	/**
	 * Function for checking Clock widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 *
	 * @dataProvider getClockWidgetCommonData
	 */
	public function checkFormClockWidget($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$linkid = $update
			? self::$dashboardid['Dashboard for updating clock widgets']
			: self::$dashboardid['Dashboard for creating clock widgets'];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$linkid);
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();

		if (array_key_exists('second_page', $data) && $update == false) {
			$dashboard->selectPage('Second page');
			$dashboard->invalidate();
		}

		$form = $update
			? $dashboard->getWidgets()->last()->edit()
			: $dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->one();

		if (CTestArrayHelper::get($data, 'check_dialog_properties', false) && $update === true) {
			$this->assertEquals('Edit widget', $dialog->getTitle());
			$form->checkValue(['Type' => 'Clock']);
		}

		if (!$update) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);
		}

		$form->fill($data['fields']);
		$form->query('xpath://button[@class="dialogue-widget-save"]')->waitUntilReady()->one()->click();

		if ($data['expected'] === TEST_GOOD) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			/**
			 * After saving dashboard, it returns you to first page, if widget created in 2nd page,
			 * then it needs to be opened.
			 */
			if (array_key_exists('second_page', $data) && $update === false) {
				$dashboard->selectPage('Second page');
				$dashboard->invalidate();
			}

			if (array_key_exists('Item', $data['fields'])) {
				$data['fields'] = array_replace($data['fields'], ['Item' => 'Host for clock widget: '.
					$data['fields']['Item']]);
			}

			// Check that widget updated.
			$dashboard->edit();
			$dashboard->getWidgets()->last()->edit()->checkValue($data['fields']);

			// Check that widget is saved in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT *'.
				' FROM widget w'.
				' WHERE EXISTS ('.
						' SELECT NULL'.
						' FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.$linkid.
							' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).
				')'
			));
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['Error message']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
	}

	/**
	 * Function for checking Clock Widgets creation.
	 *
	 * @param array      $data      data provider
	 * @dataProvider getClockWidgetCommonData
	 */
	public function testDashboardClockWidget_Create($data) {
		$this->checkFormClockWidget($data);
	}

	/**
	 * Function for checking Clock Widgets successful update.
	 *
	 * @param array      $data      data provider
	 * @dataProvider getClockWidgetCommonData
	 */
	public function testDashboardClockWidget_Update($data) {
		$this->checkFormClockWidget($data, true);
	}

	public function testDashboardClockWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			],
			// Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardClockWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking cancelling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('CancelClock')->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}
		else {
			$form->fill(['Type' => 'Clock']);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Name' => 'Widget to be cancelled',
				'Refresh interval' => '10 minutes',
				'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
				'Item' => 'Item for clock widget 2'
			]);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->getWidget(!$save_dashboard ? 'Widget to be cancelled' : 'CancelClock')->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget('CancelClock')->edit()->getFields()->asValues());
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * Check clock widgets deletion.
	 */
	public function testDashboardClockWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget('DeleteClock');
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget('DeleteClock');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget('DeleteClock', false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT *'.
			' FROM widget_field wf'.
			' LEFT JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr('DeleteClock')
		));
	}
}
