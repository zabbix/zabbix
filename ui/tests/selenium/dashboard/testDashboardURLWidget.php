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
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 */
class testDashboardURLWidget extends CWebTest {

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

	private static $dashboardid;
	private static $default_widget = 'Default URL Widget';

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

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Single URL Widget test',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'url',
								'name' => self::$default_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'url',
										'value' => 'http://zabbix.com'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardURLWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();
		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		// Check default state.
		$default_state = [
			'Name' => '',
			'id:show_header' => true,
			'Refresh interval' => 'Default (No refresh)',
			'URL' => '',
			'Enable host selection' => false
		];

		foreach ($default_state as $field => $value) {
			$this->assertEquals($value, $form->getField($field)->getValue());
		}

		// Check 'Add widget' form header.
		$this->assertEquals('Add widget', $form->query('xpath://h4[contains(@id, "head-title")]')->one()->getText());

		// Check that widget type is selected correctly.
		$this->assertEquals('URL', $form->query('id:label-type')->one()->getText());

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'URL' => [
				'maxlength' => 255
			]
		];

		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check "Refresh interval" dropdown options.
		$refresh_interval = ['Default (No refresh)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'];

		$this->assertEquals($refresh_interval, $form->getField('id:rf_rate')->asDropdown()->getOptions()->asText());

		// Check if buttons are present.
		$widget_buttons = ['dialogue-widget-save', 'btn-alt js-cancel'];

		foreach ($widget_buttons as $button) {
			$this->assertTrue($form->query('xpath://button[@class="'.$button.'"]')->one()->isVisible());
		}
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => ''
					],
					'error' => ['Invalid parameter "URL": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '?'
					],
					'error' => ['Invalid parameter "URL": unacceptable URL.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => 'dns://zabbix.com'
					],
					'error' => ['Invalid parameter "URL": unacceptable URL.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => 'message://zabbix.com'
					],
					'error' => ['Invalid parameter "URL": unacceptable URL.']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'id:show_header' => false,
						'Refresh interval' => '10 seconds',
						'URL' => 'http://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'id:show_header' => false,
						'Refresh interval' => '30 seconds',
						'URL' => 'https://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '1 minute',
						'URL' => 'http://localhost/DEV-2341-6.3/zabbix.php?action=dashboard.view'
					]
				]
			]
		];
	}

	/**
	 * @backupOnce dashboard
	 * @dataProvider getWidgetData
	 */
	public function testDashboardURLWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardURLWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardURLWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Perform URL widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	public function checkWidgetForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
		}

		$data['fields']['Name'] = 'URL widget create '.microtime();
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$default_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->waitUntilReady()->asForm();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data['fields']);
		$values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid'));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			if ($update) {
				self::$default_widget = $data['fields']['Name'];
			}

			COverlayDialogElement::ensureNotPresent();
			$header = CTestArrayHelper::get($data['fields'], 'Name');
			$dashboard->getWidget($header)->waitUntilReady();

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->waitUntilReady()->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget form fields and values in frontend.
			$saved_form = $dashboard->getWidget($header)->edit();
			$this->assertEquals($values, $saved_form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues());

			if (array_key_exists('show_header', $data['fields'])) {
				$saved_form->checkValue(['id:show_header' => $data['fields']['show_headedr']]);
			}

			$saved_form->submit();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (No refresh)')
				? '30 seconds'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, CDashboardElement::find()->one()->getWidget($header)->getRefreshInterval());
		}
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
					'create_widget' => true,
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
	public function testDashboardURLWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking canceling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget(self::$default_widget)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Name' => 'Widget to cancel',
				'URL' => 'http://zabbix.com'
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
			$dashboard->getWidget(!$save_dashboard ? 'Widget to cancel' : self::$default_widget)->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updated widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget(self::$default_widget)->edit()->getFields()->asValues());
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardURLWidget_Delete() {
		$data = [
			'Name' => 'Widget for delete',
			'URL' => 'https://www.zabbix.com/'
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data)->submit();
		$dashboard->save();
		$old_widget_count = $dashboard->getWidgets()->count();

		$dashboard->edit();
		$this->assertEquals(true, $dashboard->getWidget($data['Name'])->isEditable());
		$dashboard->deleteWidget($data['Name']);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertEquals('', CDBHelper::getRow('SELECT * from widget WHERE name = '.zbx_dbstr('Widget to delete')));
	}

	public function testDashboardURLWidget_CheckMacro() {
		$data = [
			'Name' => 'ЗАББИКС Сервер',
			'Enable host selection' => true,
			'URL' => 'http://{HOST.*}'
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data)->submit();
		$dashboard->save();

		// Check widget empty content, because the host doesn't match dynamic option criteria.
		$content = $dashboard->getWidget($data['Name'])->query('class:nothing-to-show')->one()->getText();
		$this->assertEquals('No host selected.', $content);

		// Select host.
		$host = $this->query('class:multiselect-control')->asMultiselect()->one()->fill($data['Name']);

		// Check widget content when the host match dynamic option criteria.
		$this->assertFalse($dashboard->getWidget($data['Name'])->query('class:nothing-to-show')->one(false)->isValid());
		$host->clear();
	}
}
