<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * The ignore browser errors annotation is required due to the errors coming from the URL opened in the URL widget.
 * @ignoreBrowserErrors
 *
 * @backup dashboard
 * @onBefore prepareData
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
	private static $dashboard_create;
	private static $default_widget = 'Default URL Widget';
	private static $update_widget = 'Update URL Widget';
	private static $delete_widget = 'Widget for delete';

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

	public static function prepareData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for URL Widget test',
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
							],
							[
								'type' => 0,
								'name' => 'dynamic',
								'value' => '1'
							]
						]
					],
					[
						'type' => 'url',
						'name' => self::$delete_widget,
						'x' => 12,
						'y' => 0,
						'width' => 12,
						'height' => 5,
						'fields' => [
							[
								'type' => 1,
								'name' => 'url',
								'value' => 'zabbix.php?action=dashboard.view'
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for URL Widget create/update test',
				'widgets' => [
					[
						'type' => 'url',
						'name' => self::$update_widget,
						'x' => 0,
						'y' => 0,
						'width' => 12,
						'height' => 5,
						'fields' => [
							[
								'type' => 1,
								'name' => 'url',
								'value' => 'https://zabbix.com'
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboard_create = $response['dashboardids'][1];

		// Create host for ResolvedMacro test purposes.
		CDataHelper::createHosts([
			[
				'host' => 'Host for resolved DNS macro',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'dnsmacro.com',
						'port' => '10051'
					]
				],
				'groups' => [
					'groupid' => '4'
				],
				'items' => [
					[
						'name' => 'Test DNS',
						'key_' => 'dns_macro',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '30'
					]
				]
			],
			[
				'host' => 'Host for resolved IP macro',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.9',
						'dns' => '',
						'port' => '10099'
					]
				],
				'groups' => [
					'groupid' => '4'
				],
				'items' => [
					[
						'name' => 'Test IP',
						'key_' => 'ip_macro',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '60'
					]
				]
			]
		]);
	}

	public function testDashboardURLWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		// Check default state.
		$default_state = [
			'Type' => 'URL',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (No refresh)',
			'URL' => '',
			'Dynamic item' => false
		];

		$form->checkValue($default_state);
		$this->assertTrue($form->isRequired('URL'));

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => '255',
				'placeholder' => 'default'
			],
			'URL' => [
				'maxlength' => '255'
			]
		];
		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		$refresh_interval = ['Default (No refresh)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
			'2 minutes', '10 minutes', '15 minutes'];
		$this->assertEquals($refresh_interval, $form->getField('Refresh interval')->getOptions()->asText());

		// Check if buttons present and clickable.
		$this->assertEquals(2, $dialog->query('button', ['Add', 'Cancel'])->all()
			->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
		COverlayDialogElement::find()->one()->close();
		COverlayDialogElement::ensureNotPresent();
		CDashboardElement::find()->one()->save();

		// Check parameter 'Dynamic item' true/false state.
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one()->isVisible());
		$this->assertEquals('No host selected.', $dashboard->getWidget(self::$default_widget)
			->query('class:nothing-to-show')->one()->getText());
		$dashboard->getWidget(self::$default_widget)->edit();
		$this->assertEquals('Edit widget', $dialog->getTitle());
		$form->fill(['Dynamic item' => false])->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->save();
		$this->assertFalse($dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one(false)->isVisible());
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
			// The 'Refresh interval' value depends on the previous test case value = 'No refresh'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'URL' => 'http://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => 'Default (No refresh)',
						'URL' => 'http://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Refresh interval' => '10 seconds',
						'URL' => 'http://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
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
						'URL' => 'ftp://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '2 minutes',
						'URL' => 'file://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '10 minutes',
						'URL' => 'mailto://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '15 minutes',
						'URL' => 'tel://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => 'No refresh',
						'URL' => 'ssh://zabbix.com'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardURLWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardURLWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget(self::$update_widget)->edit();
		$form->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
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
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$data['fields']['Name'] = 'URL widget create '.microtime();
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data['fields']);
		$values = $form->getValues();
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			if ($update) {
				self::$update_widget = $data['fields']['Name'];
			}

			COverlayDialogElement::ensureNotPresent();
			$header = CTestArrayHelper::get($data['fields'], 'Name');
			$dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$this->page->scrollToTop();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget form fields and values in frontend.
			$saved_form = $dashboard->getWidget($header)->edit();
			$this->assertEquals($values, $saved_form->getValues());

			if (array_key_exists('Show header', $data['fields'])) {
				$saved_form->checkValue(['Show header' => $data['fields']['Show header']]);
			}

			$saved_form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->scrollToTop();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (No refresh)')
				? 'No refresh'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', 'No refresh'));

			$mapping = [
			'Default (No refresh)' => 0,
			'No refresh' => 0,
			'10 seconds' => 10,
			'30 seconds' => 30,
			'1 minute' => 60,
			'2 minutes' => 120,
			'10 minutes' => 600,
			'15 minutes' => 900
			];

			$this->assertEquals($mapping[$refresh], CDashboardElement::find()->one()->getWidget($header)->getRefreshInterval());
		}
	}

	public static function getCancelData() {
		return [
			// Cancel update widget.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'update' => true,
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
	public function testDashboardURLWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();;
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::$default_widget)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type') !== 'URL') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
			}
		}
		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'URL' => 'zabbix.php?action=dashboard.view'
		]);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();

			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget($new_name)->isVisible());
		}
		else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach ([self::$default_widget => true, $new_name => false] as $name => $valid) {
					$this->assertTrue($dashboard->getWidget($name, $valid)->isValid($valid));
				}
			}

			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
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

	public function testDashboardURLWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::$delete_widget);
		$dashboard->deleteWidget(self::$delete_widget);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::$delete_widget, false)->isValid());
		$widget_sql = 'SELECT NULL FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr(self::$delete_widget);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	public static function getWidgetMacroData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'ЗАББИКС Сервер',
						'Dynamic item' => true,
						'URL' => 'hosts.php?form=update&hostid={HOST.ID}'
					],
					'result' => [
						'element' => 'id:visiblename',
						'value' => 'ЗАББИКС Сервер',
						'src' => 'hosts.php?form=update&hostid=10084'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Dynamic widgets H1',
						'Dynamic item' => true,
						'URL' => 'zabbix.php?action=host.view&filter_name={HOST.NAME}&filter_ip=&'.
							'filter_dns=&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					],
					'result' => [
						'element' => 'class:link-action',
						'text' => 'Dynamic widgets H1',
						'src' => 'zabbix.php?action=host.view&filter_name=Dynamic widgets H1&filter_ip=&'.
							'filter_dns=&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Host for resolved IP macro',
						'Dynamic item' => true,
						'URL' => 'zabbix.php?action=host.view&filter_name=&filter_ip={HOST.IP}&'.
							'filter_dns=&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					],
					'result' => [
						'element' => 'class:link-action',
						'text' => 'Host for resolved IP macro',
						'src' => 'zabbix.php?action=host.view&filter_name=&filter_ip=127.0.7.9&'.
							'filter_dns=&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Host for resolved DNS macro',
						'Dynamic item' => true,
						'URL' => 'zabbix.php?action=host.view&filter_name=&filter_ip=&'.
							'filter_dns={HOST.DNS}&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					],
					'result' => [
						'element' => 'class:link-action',
						'text' => 'Host for resolved DNS macro',
						'src' => 'zabbix.php?action=host.view&filter_name=&filter_ip=&'.
							'filter_dns=dnsmacro.com&filter_port=&filter_status=-1&filter_evaltype=0&filter_maintenance_status=1&'.
							'filter_show_suppressed=0&filter_set=1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetMacroData
	 */
	public function testDashboardURLWidget_ResolvedMacro($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget(self::$default_widget)->edit();

		if ($form->getField('Type') !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data['fields'])->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->save();
		self::$default_widget = $data['fields']['Name'];

		// Select host.
		$host = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one()->fill($data['fields']['Name']);

		// Check widget content when the host match dynamic option criteria.
		$widget = $dashboard->getWidget($data['fields']['Name'])->getContent();
		$this->page->switchTo($widget->query('id:iframe')->one());

		if (array_key_exists('value', $data['result'])) {
			$this->assertEquals($data['result']['value'], $this->query($data['result']['element'])->one()->getValue());
		}
		else {
			$this->assertEquals($data['result']['text'], $this->query($data['result']['element'])->one()->getText());
		}

		// Check iframe source link
		$this->page->switchTo();
		$this->assertEquals($data['result']['src'], $widget->query('xpath://iframe')->one()->getAttribute('src'));
		$host->clear();
	}
}
