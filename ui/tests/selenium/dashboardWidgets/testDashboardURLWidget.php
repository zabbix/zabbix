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


require_once __DIR__.'/../../include/CWebTest.php';

/**
 * The ignore browser errors annotation is required due to the errors coming from the URL opened in the URL widget.
 * @ignoreBrowserErrors
 *
 * @backup dashboard
 * @dataSource DynamicItemWidgets
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

	protected static $dashboardid;
	protected static $dashboard_create;
	protected static $dashboard_for_frame_widget;
	protected static $default_widget = 'Default URL Widget';
	protected static $update_widget = 'Update URL Widget';
	protected static $delete_widget = 'Widget for delete';
	protected static $frame_widget = 'Widget for iframe and xframe testing';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	protected $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for URL Widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'url',
								'name' => self::$default_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'url',
										'value' => 'http://zabbix.com'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'url',
								'name' => self::$delete_widget,
								'x' => 0,
								'y' => 5,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'url',
										'value' => 'zabbix.php?action=dashboard.view'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for URL Widget create/update test',
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'url',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'url',
										'value' => 'https://zabbix.com'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for frame widget test',
				'pages' => [
					[
						'name' => 'Page with frame widget',
						'widgets' => [
							[
								'type' => 'url',
								'name' => self::$frame_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'url',
										'value' => 'zabbix.php?action=popup&popup=host.edit&hostid=10084'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboard_create = $response['dashboardids'][1];
		self::$dashboard_for_frame_widget = $response['dashboardids'][2];

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
			]
		]);
	}

	public function testDashboardURLWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();

		if ($form->getField('Type')->getText() !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		// Check default state.
		$default_state = [
			'Type' => 'URL',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (No refresh)',
			'URL' => '',
			'Override host' => ''
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
				'maxlength' => '2048'
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
		$dialog->close();
		$dashboard->save();

		// Check 'Override host' functionality.
		$host_selector = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one();
		$this->assertTrue($host_selector->isVisible());
		$this->assertEquals('No host selected.', $dashboard->getWidget(self::$default_widget)
				->query('class:no-data-message')->one()->getText());
		$dashboard->getWidget(self::$default_widget)->edit();
		$this->assertEquals('Edit widget', $dialog->getTitle());
		$form->fill(['Override host' => ''])->submit();
		$dashboard->save();
		$this->assertFalse($host_selector->isVisible());
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => ''
					],
					'error' => 'Invalid parameter "URL": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '?'
					],
					'error' => 'Invalid parameter "URL": unacceptable URL.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => 'dns://zabbix.com'
					],
					'error' => 'Invalid parameter "URL": unacceptable URL.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => 'message://zabbix.com'
					],
					'error' => 'Invalid parameter "URL": unacceptable URL.'
				]
			],
			// Widget name "URL", if no name is given.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '',
						'URL' => 'zabbix.php?action=dashboard.view'
					]
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
		$dashboard->getWidget(self::$update_widget)->edit()->submit();
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

		$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'URL widget test '.microtime());
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
				? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
				: $dashboard->edit()->addWidget()->asForm();

		if ($form->getField('Type')->getText() !== 'URL') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		}

		$form->fill($data['fields']);
		$values = $form->getValues();
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));

			// Check that after error and cancellation of the widget, the widget is not available on dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			// If name is empty string it is replaced by default name "URL".
			$header = ($data['fields']['Name'] === '') ? 'URL' : $data['fields']['Name'];
			if ($update) {
				self::$update_widget = $header;
			}

			COverlayDialogElement::ensureNotPresent();
			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (No refresh)')
					? 'No refresh'
					: (CTestArrayHelper::get($data['fields'], 'Refresh interval', 'No refresh'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();
			$this->assertEquals($values, $saved_form->getValues());

			if (array_key_exists('Show header', $data['fields'])) {
				$saved_form->checkValue(['Show header' => $data['fields']['Show header']]);
			}

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();
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
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::$default_widget)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getText() !== 'URL') {
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
					$dashboard->getWidget($name, false)->isValid($valid);
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
					'popup' => true,
					'fields' => [
						'Name' => 'ЗАББИКС Сервер',
						'Override host' => 'Dashboard',
						'URL' => 'zabbix.php?action=popup&popup=host.edit&hostid={HOST.ID}'
					],
					'result' => [
						'element' => 'id:visiblename',
						'value' => 'ЗАББИКС Сервер',
						'src' => 'zabbix.php?action=popup&popup=host.edit&hostid=10084'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Dynamic widgets H1',
						'Override host' => 'Dashboard',
						'URL' => 'zabbix.php?name={HOST.NAME}&ip=&dns=&port=&status=-1&evaltype=0&tags[0][tag]=&'.
							'tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&filter_show_counter=0&'.
							'filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					],
					'result' => [
						'element' => 'id:name_#{uniqid}',
						'value' => 'Dynamic widgets H1',
						'src' => 'zabbix.php?name=Dynamic widgets H1&ip=&dns=&port=&status=-1&evaltype=0&tags[0][tag]=&'.
							'tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&filter_show_counter=0&'.
							'filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Host-layout-test-001',
						'Override host' => 'Dashboard',
						'URL' => 'zabbix.php?name=&ip={HOST.IP}&dns=&port=&status=-1&evaltype=0&tags[0][tag]=&'.
							'tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&filter_show_counter=0&'.
							'filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					],
					'result' => [
						'element' => 'id:ip_#{uniqid}',
						'value' => '127.0.7.1',
						'src' => 'zabbix.php?name=&ip=127.0.7.1&dns=&port=&status=-1&evaltype=0&tags[0][tag]=&'.
							'tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&filter_show_counter=0&'.
							'filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Host for resolved DNS macro',
						'Override host' => 'Dashboard',
						'URL' => 'zabbix.php?name=&ip=&dns={HOST.DNS}&port=&status=-1&evaltype=0&tags[0][tag]=&'.
							'tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&filter_show_counter=0&'.
							'filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					],
					'result' => [
						'element' => 'id:dns_#{uniqid}',
						'value' => 'dnsmacro.com',
						'src' => 'zabbix.php?name=&ip=&dns=dnsmacro.com&port=&status=-1&evaltype=0&'.
							'tags[0][tag]=&tags[0][operator]=0&tags[0][value]=&maintenance_status=1&filter_name=&'.
							'filter_show_counter=0&filter_custom_time=0&sort=name&sortorder=ASC&show_suppressed=0&action=host.view'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetMacroData
	 */
	public function testDashboardURLWidget_ResolvedMacro($data) {
		// Use iframe sandboxing exceptions in case of popup form.
		if (array_key_exists('popup', $data)) {
			$this->page->login()->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
			$other_form = $this->query('name:otherForm')->waitUntilVisible()->asForm()->one();
			$other_form->fill([
				'id:iframe_sandboxing_enabled' => true,
				'id:iframe_sandboxing_exceptions' => 'allow-scripts allow-same-origin allow-forms'
			]);
			$other_form->submit();
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		}
		else {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		}

		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget(self::$default_widget)->edit();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('URL')]);
		$form->fill($data['fields'])->submit();
		$dashboard->save();
		self::$default_widget = $data['fields']['Name'];

		// Select host.
		$host = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one()->fill($data['fields']['Name']);

		// Check widget content when the host match dynamic option criteria.
		$widget = $dashboard->getWidget($data['fields']['Name'])->getContent();
		$this->page->switchTo($widget->query('id:iframe')->one());
		COverlayDialogElement::find()->waitUntilReady();
		$this->assertEquals($data['result']['value'], $this->query($data['result']['element'])->one()->getValue());

		// Check iframe source link.
		$this->page->switchTo();
		$this->assertEquals($data['result']['src'], $widget->query('xpath://iframe')->one()->getAttribute('src'));
		$host->clear();

		// Revert changes made in 'Other configuration parameters'.
		if (array_key_exists('popup', $data)) {
			$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
			$other_form->fill(['id:iframe_sandboxing_exceptions' => ' ']);
			$other_form->submit();
		}
	}

	/**
	 * Modify iframe sandboxing and exception configuration. Parameters determine whether retrieved URL content
	 * should be put into the sandbox or not.
	 */
	public function testDashboardURLWidget_IframeSandboxing() {
		// TODO: test scenario should be changed regarding decision made in ZBX-25566.
		// Check that host in widget can be updated via iframe if necessary sandboxing exceptions are set.
		$this->page->login()->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$other_form = $this->query('name:otherForm')->waitUntilVisible()->asForm()->one();
		$other_form->fill([
			'id:iframe_sandboxing_enabled' => true,
			'id:iframe_sandboxing_exceptions' => 'allow-scripts allow-same-origin allow-forms'
		]);
		$other_form->submit();

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_for_frame_widget)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget(self::$frame_widget)->getContent();

		// Update host in widget.
		foreach ([true, false] as $state) {
			$this->page->switchTo($widget->query('id:iframe')->one());
			COverlayDialogElement::find()->waitUntilReady();
			$this->query('button:Update')->one()->click();
			CMessageElement::find()->one()->waitUntilVisible();
			$this->assertTrue($this->query('class:msg-good')->one()->isVisible());
			$this->assertFalse($this->query('button:Update')->one(false)->isVisible());
			$this->page->switchTo();

			// Disable 'Use iframe sandboxing' option for false state scenario.
			if ($state) {
				$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
				$other_form = $this->query('name:otherForm')->waitUntilVisible()->asForm()->one();
				$other_form->fill(['id:iframe_sandboxing_enabled' => !$state]);
				$other_form->submit();
				$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_for_frame_widget)->waitUntilReady();
			}
		}
	}

	/**
	 * Modify the URI scheme validation rules and check the result for the URL type in Widget form.
	 */
	public function testDashboardURLWidget_ValidateUriSchemes() {
		$invalid_schemes = ['dns://zabbix.com', 'message://zabbix.com'];
		$default_valid_schemes = ['http://zabbix.com', 'https://zabbix.com', 'ftp://zabbix.com', 'file://zabbix.com',
			'mailto://zabbix.com', 'tel://zabbix.com', 'ssh://zabbix.com'
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget(self::$default_widget)->edit();

		// Check default URI scheme rules: http, https, ftp, file, mailto, tel, ssh.
		$this->assertUriScheme($form, $default_valid_schemes);
		$this->assertUriScheme($form, $invalid_schemes, TEST_BAD);

		// Change valid URI schemes on "Other configuration parameters" page.
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$config_form = $this->query('name:otherForm')->asForm()->waitUntilVisible()->one();
		$config_form->fill(['id:uri_valid_schemes' => 'dns,message']);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		// Check that already created widget became invalid and returns error regarding invalid parameter.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = $dashboard->getWidget(self::$default_widget)->getContent();
		$this->assertEquals('Invalid parameter "URL": unacceptable URL.', $widget->query('class:msg-details')->one()->getText());
		$broken_form = $dashboard->getWidget(self::$default_widget)->edit();

		// Check that the widget URL field is empty.
		// TODO: url should not be empty, fix after DEV-3951
//		$broken_form->checkValue(['URL' => 'ssh://zabbix.com', 'Name' => self::$default_widget]);
		$broken_form->checkValue(['URL' => '', 'Name' => self::$default_widget]);
		COverlayDialogElement::find()->one()->close();
		$this->query('button:Save changes')->one()->click();

		// Check that Dashboard can't be saved and returns error regarding invalid parameter.
		$message = CMessageElement::find('xpath://div[@class="wrapper"]', true)->one()->waitUntilVisible();
		$this->assertMessage(TEST_BAD, null, 'Cannot save widget "'.self::$default_widget.'". Invalid parameter "URL": cannot be empty.');
		$message->close();

		// Check updated valid URI schemes.
		$dashboard->getWidget(self::$default_widget)->edit();
		$broken_form->fill(['URL' => 'any'])->submit();
		$this->assertUriScheme($form, $default_valid_schemes, TEST_BAD);
		$this->assertUriScheme($form, $invalid_schemes);

		// Disable URI scheme validation.
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$config_form->invalidate();
		$config_form->fill(['id:validate_uri_schemes' => false]);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$this->assertUriScheme($form, array_merge($default_valid_schemes, $invalid_schemes));
	}

	/**
	 * Fill in the URL field to check the uri scheme validation rules.
	 *
	 * @param CFormElement $form	form element of widget
	 * @param array $data			url field data
	 * @param string $expected		expected result after widget form submit, TEST_GOOD or TEST_BAD
	 */
	private function assertUriScheme($form, $data, $expected = TEST_GOOD) {
		$dashboard = CDashboardElement::find()->one();
		foreach ($data as $scheme) {
			$dashboard->getWidget(self::$default_widget)->edit();
			COverlayDialogElement::find()->one()->waitUntilReady();
			$form->fill(['URL' => $scheme]);
			$form->submit();

			if ($expected === TEST_GOOD) {
				$dashboard->save();
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			}
			else {
				$this->assertMessage(TEST_BAD, null, 'Invalid parameter "URL": unacceptable URL.');
				CMessageElement::find()->one()->close();
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	public function getXframOptionsData() {
		return [
			[
				[
					'x_frame_enabled' => false
				]
			],
			[
				[
					'x_frame_value' => 'null'
				]
			],
			[
				[
					'x_frame_value' => 'SAMEORIGIN'
				]
			],
			[
				[
					'x_frame_value' => "'self'"
				]
			],
			[
				[
					'x_frame_value' => "'self' space separated host.names  with-different   sp4c1.ng"
				]
			],
			[
				[
					'x_frame_value' => 'DENY',
					'refused' => true
				]
			],
			[
				[
					'x_frame_value' => "'none'",
					'refused' => true
				]
			],
			[
				[
					'x_frame_value' => 'some.other.host',
					'refused' => true
				]
			]
		];
	}

	/**
	 * Modify value of 'HTTP X-Frame-options header' and check widget content with changed Xframe options.
	 *
	 * @dataProvider getXframOptionsData
	 */
	public function testDashboardURLWidget_XframeOptions($data) {
		// Change Xframe options.
		$this->page->login()->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$other_form = $this->query('name:otherForm')->waitUntilVisible()->asForm()->one();

		$other_form->fill([
			'id:x_frame_header_enabled' => CTestArrayHelper::get($data, 'x_frame_enabled', true),
			'id:iframe_sandboxing_enabled' => true,
			'id:iframe_sandboxing_exceptions' => 'allow-scripts allow-same-origin allow-forms'
		]);

		if (array_key_exists('x_frame_value', $data)) {
			$other_form->fill(['id:x_frame_options' => $data['x_frame_value']]);
		}

		$other_form->submit();

		// Check widget content with changed Xframe options.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_for_frame_widget)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget(self::$frame_widget)->getContent();
		$this->page->switchTo($widget->query('id:iframe')->one());

		if (CTestArrayHelper::get($data, 'refused')) {
			// Assert refused to connect iframe.
			$error_details = $this->query('id:sub-frame-error-details')->one()->getText();
			$this->assertStringContainsString( 'refused to connect.', $error_details);
		}
		else {
			// Assert the iframe with Host form loaded.
			$this->assertEquals('Host', COverlayDialogElement::find()->waitUntilReady()->one()->getTitle());
		}

		$this->page->switchTo();
	}
}
