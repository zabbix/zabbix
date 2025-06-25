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


require_once __DIR__.'/../common/testWidgets.php';

/**
 * @backup items, dashboard
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareItemCardWidgetData
 */
class testDashboardItemCardWidget extends testWidgets {

	/**
	 * Attach MessageBehavior, TagBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTagBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	* Ids of created Dashboards for Host Card widget check.
	*
	* @var array
	*/
	protected static $dashboardid;
	/**
	* Dashboard hash before update.
	*
	* @var string
	*/
	protected static $old_hash;
	/**
	* Widget counter.
	*
	* @var integer
	*/
	protected static $old_widget_count;
	protected static $itemids;
	protected static $template_items;

	public static function prepareItemCardWidgetData() {
		$template = CDataHelper::createTemplates([
			[
				'host' => 'Template for item card widget',
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => 'Master item from template',
						'key_' => 'custom_item',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_STR,
						'status' => 1
					]
				]
			]
		]);
		self::$template_items = CDataHelper::getIds('name');
		echo self::$template_items['Master item from template'][0];

		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for Item Card widget',
				'name' => 'Visible host name for Item Card widget',
				'groups' => [['groupid' => 4]], //Zabbix servers.
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.2.2.2',
						'dns' => '',
						'port' => 122,
						'details' => [
							'version' => 1,
							'bulk' => 0,
							'community' => '🙃zabbix🙃'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'selenium.test',
						'port' => 30053
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.4.4.4',
						'dns' => '',
						'port' => 426
					]
				],
				'templates' => [['templateid' => $template['templateids']['Template for item card widget']]],
				'items' => [
					[
						'name' => 'Master item',
						'key_' => 'master',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'units' => '%',
						'timeout' => '35s',
						'delay' => '100m',
						'history' => '17d',
						'trends' => '17d',
						'inventory_link' => 6,
						'description' => STRING_6000
					],
					[
						'name' => 'Item with text datatype',
						'key_' => 'datatype_text',
						'type' => ITEM_TYPE_JMX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
						'delay' => '15m'
					],
					[
						'name' => 'Item with log datatype',
						'key_' => 'datatype_log',
						'type' => ITEM_TYPE_IPMI,
						'value_type' => ITEM_VALUE_TYPE_LOG,
						'ipmi_sensor' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
						'delay' => '15m'
					]
				],
				'monitored_by' => ZBX_MONITORED_BY_SERVER,
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_AUTOMATIC
			]
		]);
		self::$itemids = CDataHelper::getIds('name');

		// Create dependent item.
		$items = [
			'Host for Item Card widget' => [
				[
					'name' => 'Dependent item 1',
					'key_' => 'dependent_item_1',
					'master_itemid' => self::$itemids['Master item'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'name' => 'Dependent item 2',
					'key_' => 'dependent_item_2',
					'master_itemid' => self::$itemids['Master item'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_BINARY
				]
			]
		];
		CDataHelper::createItems('item', $items, $hosts['hostids']);
		$depend_items= CDataHelper::getIds('name');

		// Add some metrics to 'Master item', to get Graph image and error notification.
		CDataHelper::addItemData(self::$itemids['Master item'], [10000, 200, 30000, 400, 50000, 600, 70000, 800, 9000]);
		DBexecute('UPDATE item_rtdata SET state = 1, error = '.zbx_dbstr('Value of type "string" is not suitable for '.
				'value type "Numeric (unsigned)". Value "hahah"').'WHERE itemid ='.zbx_dbstr(self::$itemids['Master item']));

		

		// Create trigger based on item.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Cannot get any metric in last 5 iterations',
				'expression' => 'last(/Host for Item Card widget/master,#5)=0',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Cannot get any metric in last 10 iterations',
				'expression' => 'last(/Host for Item Card widget/master,#10)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Cannot get any metric in last 15 iterations',
				'expression' => 'last(/Host for Item Card widget/master,#15)=0',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Cannot get any metric in last 20 iterations',
				'expression' => 'last(/Host for Item Card widget/master,#20)=0',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating ItemCard widgets',
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for Item Card widget update',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'Item card',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 10,
								'fields' => [
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => 0,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$itemids['Master item']
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for canceling Item Card widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'Item Card widget cancel scenario',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 10,
								'fields' => [
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => 0,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$itemids['Master item']
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for deleting Item Card widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'DeleteItemCardWidget',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$itemids['Master item']
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Item Card widget display check',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'Master item from host',
								'x' => 0,
								'y' => 0,
								'width' => 16,
								'height' => 9,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$itemids['Master item']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => 0,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => 1,
										'name' => 'sparkline.color',
										'value' => 'FFC107'
									],
									[
										'type' => 0,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Dependent Item from host',
								'x' => 16,
								'y' => 0,
								'width' => 15,
								'height' => 9,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => $depend_items['Dependent item 1']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => 0,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => 1,
										'name' => 'sparkline.color',
										'value' => '42A5F5'
									],
									[
										'type' => 0,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							],
							[
								'type' => 'itemcard',
								'x' => 31,
								'y' => 0,
								'width' => 18,
								'height' => 9,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$template_items['Master item from template'][0]
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => 0,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => 1,
										'name' => 'sparkline.color',
										'value' => '42A5F5'
									],
									[
										'type' => 0,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => 1,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
	}

	public function testDashboardItemCardWidget_Layout() {

	}

	public static function getCreateData() {

	}

	/**
	 * Create Item Card widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardItemCardWidget_Create($data) {

	}

	/**
	 * Item Card widget simple update without any field change.
	 */
	public function testDashboardItemCardWidget_SimpleUpdate() {
		// Hash before simple update.
		self::$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Item Card widget update'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget('Item card')->edit()->submit();
		$dashboard->getWidget('Item card');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Update Item Card widget.
	 *
	 * @backup widget
	 * @dataProvider getCreateData
	 */
	public function testDashboardItemCardWidget_Update($data) {

	}

	/**
	 * Delete Host Card widget.
	 */
	public function testDashboardItemCardWidget_Delete() {

	}

	public static function getDisplayData() {

	}

	/**
	 * Check different data display on Host Card widget.
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardItemCardWidget_Display($data) {

	}

	/**
	 * Check context menu links.
	 *
	 * @param array $data	data provider with fields values
	 */
	protected function checkContextMenuLinks($data, $hostid) {

	}

	public static function getLinkData() {

	}

	/**
	 * Check correct links in Host Card widget.
	 *
	 * @dataProvider getLinkData
	 */
	public function testDashboardItemCardWidget_CheckLinks($data) {

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
					'save_dashboard' => false
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
	 * Check cancel scenarios for Host Card widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardItemCardWidget_Cancel($data) {
		self::$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for canceling Item Card widget']
		);
		$dashboard = CDashboardElement::find()->one()->edit();
		self::$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget('Item Card widget cancel scenario')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Item' => 'Master item'
		]);

		$data = [
			'Show' => [
				['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
				['action' => USER_ACTION_REMOVE, 'index' => 1],
				['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Description'],
				['section' => 'Error text']
			]
		];
		$this->getShowTable()->fill($data['Show']);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();

			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget($new_name)->isVisible());
		}
		else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->close(true);
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach (['CancelHostCardWidget' => true, $new_name => false] as $name => $valid) {
					$this->assertTrue($dashboard->getWidget($name, $valid)->isValid($valid));
				}
			}

			$this->assertEquals(self::$old_widget_count, $dashboard->getWidgets()->count());
		}
		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}
		// Confirm that no changes were made to the widget.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	public static function getWidgetName() {
		return [
			[
				[
					'Name' => 'Fully filled host card widget'
				]
			],
			[
				[
					'Name' => 'Host card'
				]
			],
			[
				[
					'Name' => 'Default host card widget'
				]
			]
		];
	}

	/**
	 * Check different compositions for Host Card widget.
	 *
	 * @dataProvider getWidgetName
	 */
	public function testDashboardItemCardWidget_Screenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$this->assertScreenshot(CDashboardElement::find()->one()->getWidget($data['Name']), 'itemcard_'.$data['Name']);
	}

	/**
	 * Create or update Host Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update HostCard widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function fillWidgetForm($data, $action, $dashboard) {
		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('Host card')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		$form->fill($data['fields']);

		if (array_key_exists('Show', $data)) {
			$this->getShowTable()->fill($data['Show']);

			if (array_key_exists('Inventory', $data)) {
				$form->getField('Inventory fields')->fill($data['Inventory']);
			}
		}

		if (array_key_exists('Screenshot', $data) && $action === 'create') {
			$this->assertScreenshot($form->query('class:table-forms-separator')->waitUntilPresent()->one(),
					'Full list of show options'.$data['fields']['Host']
			);
		}

		$form->submit();
	}

	/**
	 * Get 'Show' table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getShowTable() {
		return $this->query('id:sections-table')->asMultifieldTable([
			'mapping' => [
				'' => [
					'name' => 'section',
					'selector' => 'xpath:./z-select',
					'class' => 'CDropdownElement'
				]
			]
		])->one();
	}

	/**
	 * Check created or updated Host Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update HostCard widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function checkWidgetForm($data, $action, $dashboard) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			// Trim leading and trailing spaces from expected results if necessary.
			if (array_key_exists('trim', $data)) {
				$data['fields']['Name'] = trim($data['fields']['Name']);
			}

			// Make sure that the widget is present before saving the dashboard.
			$header = (array_key_exists('Name', $data['fields']))
				? (($data['fields']['Name'] === '') ? 'Host card' : $data['fields']['Name'])
				: 'Host card';

			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that dashboard saved.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals(self::$old_widget_count + (($action === 'create') ? 1 : 0), $dashboard->getWidgets()->count());

			$dashboard->getWidget($header)->edit()->checkValue($data['fields']);
			$this->getShowTable()->checkValue($this->calculateShowResult(CTestArrayHelper::get($data, 'Show', [])));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
	}

	/**
	 * Convert default values into an indexed array.
	 *
	 * @param array             $rows         data provider
	 */
	protected function calculateShowResult($rows) {
		$result = [
			['section' => 'Monitoring'],
			['section' => 'Availability'],
			['section' => 'Monitored by']
		];

		foreach ($rows as $row) {
			if (array_key_exists('action', $row)) {
				if ($row['action'] === USER_ACTION_REMOVE) {
					// Remove element at index.
					array_splice($result, $row['index'], 1);
				} elseif ($row['action'] === USER_ACTION_UPDATE) {
					// Update existing element
					$result[$row['index']]['section'] = $row['section'];
				}
			} else {
				// If no action is specified, it means we are adding a new section.
				$result[] = ['section' => $row['section']];
			}
		}

		return array_values($result);
	}
}