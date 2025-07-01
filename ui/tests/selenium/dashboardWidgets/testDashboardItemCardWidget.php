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
	* Ids of created Dashboards for Item Card widget check.
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
	/**
	* List of created items.
	*
	* @var array
	*/
	protected static $itemids;
	/**
	* List of created from template items.
	*
	* @var integer
	*/
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
						'value_type' => ITEM_VALUE_TYPE_STR
					]
				]
			]
		]);
		self::$template_items = CDataHelper::getIds('name');

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
						'name' => STRING_255,
						'key_' => 'master',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'units' => '%',
						'timeout' => '35s',
						'delay' => '100m',
						'history' => '17d',
						'trends' => '17d',
						'inventory_link' => 6,
						'description' => STRING_6000,
						'status' => 0
					],
					[
						'name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'key_' => 'xxs',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'description' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'delay' => '13m',
						'status' => 1
					],
					[
						'name' => '105\'; --DROP TABLE Users',
						'key_' => 'sql_injection',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'description' => '105\'; --DROP TABLE Users',
						'delay' => '13m'
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
					'master_itemid' => self::$itemids[STRING_255],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'description' => 'simple description'
				],
				[
					'name' => 'Dependent item 2',
					'key_' => 'dependent_item_2',
					'master_itemid' => self::$itemids[STRING_255],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_BINARY
				]
			]
		];
		CDataHelper::createItems('item', $items, $hosts['hostids']);
		$depend_items= CDataHelper::getIds('name');

		// Create trigger based on item.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Not classidied trigger',
				'expression' => 'last(/Host for Item Card widget/master)>100',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Information trigger',
				'expression' => 'last(/Host for Item Card widget/master)>200',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Warning trigger',
				'expression' => 'last(/Host for Item Card widget/master)>300',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Average trigger',
				'expression' => 'last(/Host for Item Card widget/master)>400',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'High trigger',
				'expression' => 'last(/Host for Item Card widget/master)>500',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Disaster trigger',
				'expression' => 'last(/Host for Item Card widget/master)>600',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Trigger 1',
				'expression' => 'last(/Host for Item Card widget/datatype_text)>100',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Trigger 2',
				'expression' => 'last(/Host for Item Card widget/datatype_text)>100',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);

		// Add some metrics to STRING_255 item, to get Graph image and error notification.
		foreach ([STRING_255, 'Item with text datatype'] as $item) {
			CDataHelper::addItemData(self::$itemids[$item], [10000, 200, 30000, 400, 50000, 600, 70000, 800, 9000]);
		}

		$trigger_names = ['Not classidied trigger', 'Information trigger', 'Warning trigger', 'Average trigger',
			'High trigger', 'Disaster trigger', 'Disaster trigger', 'Trigger 1', 'Trigger 2'];
		CDBHelper::setTriggerProblem($trigger_names, TRIGGER_VALUE_TRUE);

		// Add red error message.
		DBexecute('UPDATE item_rtdata SET state = 1, error = '.zbx_dbstr('Value of type "string" is not suitable for '.
				'value type "Numeric (unsigned)". Value "hahah"').'WHERE itemid ='.zbx_dbstr(self::$itemids[STRING_255]));

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating Item Card widgets',
				'private' => PUBLIC_SHARING,
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'geomap',
								'x' => 0,
								'y' => 0,
								'width' => 13,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EKBHK'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 13,
								'y' => 0,
								'width' => 16,
								'height' => 5,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid.0',
										'value' => 2232 // Linux: CPU utilization.
									],
									[
										'type'=> 1,
										'name'=> 'reference',
										'value'=> 'XIBBD'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Item Card widget update',
				'display_period' => 1800,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'geomap',
								'x' => 0,
								'y' => 0,
								'width' => 14,
								'height' => 5
							],
							[
								'type' => 'graph',
								'x' => 14,
								'y' => 0,
								'width' => 14,
								'height' => 5,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid.0',
										'value' => 2232 // Linux: CPU utilization.
									],
									[
										'type'=> 1,
										'name'=> 'reference',
										'value'=> 'XIBBD'
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Item card',
								'x' => 28,
								'y' => 0,
								'width' => 19,
								'height' => 10,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => self::$itemids[STRING_255]
									],
									[
										'type'=> '0',
										'name'=> 'sections.0',
										'value'=> '2'
									],
									[
										'type'=> '0',
										'name'=> 'sections.1',
										'value'=> '4'
									],
									[
										'type'=> '0',
										'name'=> 'sections.2',
										'value'=> '6'
									],
									[
										'type'=> '0',
										'name'=> 'sections.3',
										'value'=> '7'
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
								'name' => 'CancelItemCardWidget',
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
										'value' => self::$itemids[STRING_255]
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
										'value' => self::$itemids[STRING_255]
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
										'value' => self::$itemids[STRING_255]
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
										'value' => self::$template_items['Master item from template']+1
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

	public function testDashboardHostCardWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating Item Card widgets'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);

		// Check name field maxlength.
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));

		foreach (['Type', 'Show header', 'Name', 'Refresh interval', 'Item', 'Show', 'Override host'] as $lable) {
			$this->assertTrue($form->getField($lable)->isVisible(true));
		}

		$this->assertEquals(['Item'], $form->getRequiredLabels());

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '15 minutes'],
				$form->getField('Refresh interval')->getOptions()->asText()
		);

		// Check default values.
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Item' => '',
			'Show header' => true,
			'Override host' => ''
		];

		$form->checkValue($default_values);
		$label = $form->getField('Item');

		// Check Select dropdown menu button.
		$menu_button = $label->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->asPopupButton()->one();
		$this->assertEquals(['Item', 'Widget'], $menu_button->getMenu()->getItems()->asText());

		// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
		$menu_button->select('Widget');
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// After clicking on Select button, check overlay dialog appearance and title.
		$label->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Items', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// Check default and available options in 'Show' section.
		$show_form = $form->getFieldContainer('Show')->asMultifieldTable(['mapping' => ['' => 'section']]);
		$show_form->checkValue([
			['section' => 'Metrics'],
			['section' => 'Type of information'],
			['section' => 'Host interface'],
			['section' => 'Type']
		]);

		// Clear all default options
		$show_form->query('button:Remove')->all()->click();

		$show_options = ['Description', 'Error text', 'Metrics', 'Latest data', 'Type of information', 'Triggers',
				'Host interface', 'Type', 'Host inventory', 'Tags'];
		$disabled_result = [];
		foreach ($show_options as $i => $option) {
			$show_form->query('button:Add')->one()->click();

			// Check that added correct option by default.
			$select = $show_form->query('id', 'sections_'.$i)->one()->asDropdown();
			$this->assertEquals($option, $select->getText());

			// Check that added options are disabled in dropdown menu.
			$disabled = $select->getOptions()->filter(CElementFilter::DISABLED)->asText();
			$this->assertEquals($disabled_result, $disabled);
			$disabled_result[] = $option;
		}

		// Check that Add button became disabled.
		$this->assertFalse($show_form->query('button:Add')->one()->isEnabled());

		// If the 'Latest data' option was selected, the Sparkline becomes visible.
		$show_form->query('button:Remove')->all()->click();
		$show_form->query('button:Add')->one()->click();

		$sparkline = $form->getFieldContainer('Sparkline');
		foreach ($show_options as $option) {
			$show_form->query('id:sections_0')->one()->asDropdown()->select($option);

			if ($option === 'Latest data') {
				$this->assertTrue($sparkline->isVisible(true));

				// Check sparkline default values.
				$sparkline_default_values = [
					'id:sparkline_width' => 1,
					'id:sparkline_fill' => 3,
					'id:sparkline_time_period_data_source' => 'Custom',
					'id:sparkline_time_period_from' => 'now-1h',
					'id:sparkline_time_period_to' => 'now',
					'id:sparkline_history' => 'Auto'
				];
				foreach ($sparkline_default_values as $field => $value) {
					$this->assertEquals($value, $form->getField($field)->getValue());
					$this->assertTrue($form->getField($field)->isVisible(true));
				}

				// Check default color code.
				$this->assertEquals('#42A5F5', $form->getField('id:lbl_sparkline_color')->getAttribute('title'));

				// Check radio button options.
				$radio_buttons = [
					'id:sparkline_time_period_data_source' => ['Dashboard', 'Widget', 'Custom'],
					'id:sparkline_history' => ['Auto', 'History', 'Trends']
				];
				foreach ($radio_buttons as $locator => $labels) {
					foreach ($labels as $option) {
						$form->getField($locator)->asSegmentedRadio()->select($option);
					}
					$this->assertEquals($labels, $form->getField($locator)->getLabels()->asText());
				}

				// Check that user may open a calendar.
				foreach (['from', 'to'] as $type) {
					$icon = $form->query('id', 'sparkline_time_period_'.$type.'_calendar')->one();
					$icon->click();
					$calendar = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$this->assertTrue($calendar->isVisible());
					$icon->click();
				}

				// Check color-picker form is opened.
				$icon = $form->query('class', 'color-picker-box')->one()->click();
				$colorpicker = $this->query('id', 'color_picker')->one()->waitUntilReady();
				$this->assertTrue($colorpicker->isVisible(true));
				$colorpicker->query('button:Apply')->one()->click();
				$this->assertTrue(!$colorpicker->isVisible());
			}
			else {
				$this->assertTrue($sparkline->isVisible(false));
			}
		}

		$label = $form->getField('Override host');

		// Check Select dropdown menu button.
		$menu_button = $label->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->asPopupButton()->one();
		$this->assertEquals(['Widget', 'Dashboard'], $menu_button->getMenu()->getItems()->asText());

		// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
		$menu_button->select('Widget');
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// After selecting Dashboard from dropdown menu, check hint and field value.
		$menu_button->select('Dashboard');
		$form->checkValue(['Override host' => 'Dashboard']);
		$this->assertTrue($label->query('xpath', './/span[@data-hintbox-contents="Dashboard is used as data source."]')
				->one()->isVisible()
		);

		// After clicking on Select button, check overlay dialog appearance and title.
		$label->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);
	}

	public static function getCreateData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item is not selected',
						'Item' => ''
					],
					'error_message' => [
						'Invalid parameter "Item": cannot be empty.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 5,
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => '',
						'id:sparkline_time_period_to' => '',
						'color' => 'CDDC39'
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/From": cannot be empty.',
						'Invalid parameter "Sparkline: Time period/To": cannot be empty.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Selected more than 731 day for graph filter.',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 5,
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => 'now-1000d',
						'id:sparkline_time_period_to' => 'now'
					],
					'error_message' => [
						'Maximum time period to display is 731 days.'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty widget value',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 5,
						'id:sparkline_time_period_data_source' => 'Widget'
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/Widget": cannot be empty.'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show header' => false,
						'Name' => 'Incorrect number for sparkline parameters',
						'Refresh interval' => 'No refresh',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['section' => 'Metrics'],
						['section' => 'Error text'],
						['section' => 'Description'],
						['section' => 'Tags'],
						['section' => 'Triggers'],
						['section' => 'Host inventory']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5000,
						'id:sparkline_fill' => -5,
						'id:sparkline_time_period_data_source' => 'Dashboard',
						'color' => '44F44A'
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Width": value must be one of 0-10.',
						'Invalid parameter "Sparkline: Fill": value must be one of 0-10.'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'A time is expected',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 5,
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => 'dsa',
						'id:sparkline_time_period_to' => '321',
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/From": a time is expected.',
						'Invalid parameter "Sparkline: Time period/To": a time is expected.'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Name' => '  Trimmed name_3  ',
						'Refresh interval' => 'No refresh',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
					],
					'trim' => true
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'кирилица, ñ ç ö ø, 🙂🙂🙂🙂, みけめ, "],*,a[x=": "],*,a[x="/\|',
						'Refresh interval' => '10 seconds',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					],
					'trim' => true
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'Refresh interval' => '30 seconds',
						'Item' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'trim' => true
				]
			],
			// #9.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '105\'; --DROP TABLE Users',
						'Refresh interval' => '1 minute',
						'Item' => '105\'; --DROP TABLE Users',
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'trim' => true
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Update then remove one Show option',
						'Refresh interval' => '2 minutes',
						'Item' => STRING_255,
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Host inventory'],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'trim' => true
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Dashboard souce for override host field',
						'Refresh interval' => '10 minutes',
						'Item' => STRING_255,
						'Override host' => 'Dashboard'
					],
					'Show' => [
						['section' => 'Description'],
						['section' => 'Error text'],
						['section' => 'Latest data'],
						['section' => 'Triggers'],
						['section' => 'Host inventory'],
						['section' => 'Tags']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 3,
						'id:sparkline_time_period_data_source' => 'Dashboard',
						'id:sparkline_history' => 'History',
						'color' => 'F48FB1'
					],
					'trim' => true
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Other widget as the souce for override host field',
						'Refresh interval' => '15 minutes',
						'Item' => STRING_255,
						'Override host' => 'Geomap'
					],
					'Show' => [
						['section' => 'Description'],
						['section' => 'Error text'],
						['section' => 'Latest data'],
						['section' => 'Triggers'],
						['section' => 'Host inventory'],
						['section' => 'Tags']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 3,
						'id:sparkline_time_period_data_source' => 'Widget',
						'id:sparkline_history' => 'Trends',
						'color' => '9A34A1',
						'widget' => 'ЗАББИКС Сервер: Linux: CPU utilization'
					],
					'trim' => true
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'User changing Show options',
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['section' => 'Latest data'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Error text'],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['section' => 'Triggers'],
						['action' => USER_ACTION_UPDATE, 'index' => 2, 'section' => 'Type of information'],
						['section' => 'Tags'],
						['section' => 'Host interface'],
						['action' => USER_ACTION_UPDATE, 'index' => 4, 'section' => 'Type']
					]
				]
			]
		];
	}

	/**
	 * Create Item Card widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardItemCardWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating Item Card widgets'])->waitUntilReady();

		// Get hash if expected is TEST_BAD.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			self::$old_hash = CDBHelper::getHash(self::SQL);
		}
		else {
			self::$old_widget_count = CDashboardElement::find()->waitUntilReady()->one()->getWidgets()->count();
		}

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'create', $dashboard);
		$this->checkWidgetForm($data, 'create', $dashboard);
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Item Card widget update'])->waitUntilReady();

		// Get hash if expected is TEST_BAD.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			self::$old_hash = CDBHelper::getHash(self::SQL);
		}
		else {
			self::$old_widget_count = CDashboardElement::find()->waitUntilReady()->one()->getWidgets()->count();
		}

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'update', $dashboard);
		$this->checkWidgetForm($data, 'update', $dashboard);
	}

	/**
	 * Delete Item Card widget.
	 */
	public function testDashboardItemCardWidget_Delete() {
		$widget_name = 'DeleteItemCardWidget';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for deleting Item Card widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady()->edit();
		$widget = $dashboard->getWidget($widget_name);
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget($widget_name);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($widget_name, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
				' ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr($widget_name)
		));
	}

	public static function getDisplayData() {

	}

	/**
	 * Check different data display on Item Card widget.
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
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasTitles(array_keys($data)));

		$menu_level1_items = [];
		foreach (array_values($data) as $menu_items) {
			foreach ($menu_items as $menu_level1 => $link) {
				$menu_level1_items[] = $menu_level1;

				if (is_array($link)) {
					foreach ($link as $menu_level2 => $attribute) {
						// Check 2-level menu links.
						$item_link = $popup->getItem($menu_level1)->query('xpath:./../ul//a')->one();

						if (str_contains($attribute, 'menu-popup-item')) {
							$this->assertEquals($attribute, $item_link->getAttribute('class'));
						}
						else {
							$this->assertEquals($menu_level2, $item_link->getText());
							$this->assertStringContainsString($attribute, $item_link->getAttribute('href'));
						}
					}
				}
				else {
					// Check 1-level menu links.
					if (str_contains($link, 'menu-popup-item')) {
						$this->assertEquals($link, $popup->getItem($menu_level1)->getAttribute('class'));
					}
					else {
						$link = str_replace('{hostid}', $hostid, $link);
						$this->assertTrue($popup->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($menu_level1).
								" and contains(@href, ".CXPathHelper::escapeQuotes($link).")]")->exists()
						);
					}
				}
			}
		}

		$this->assertTrue($popup->hasItems($menu_level1_items));
		$popup->close();
	}

	public static function getLinkData() {

	}

	/**
	 * Check correct links in Item Card widget.
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
	 * Check cancel scenarios for Item Card widget.
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
			$form = $dashboard->getWidget('CancelItemCardWidget')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Item' => STRING_255
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
				foreach (['CancelItemCardWidget' => true, $new_name => false] as $name => $valid) {
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
					'Name' => 'Master item from host'
				]
			],
			[
				[
					'Name' => 'Dependent Item from host'
				]
			],
			[
				[
					'Name' => 'Item card'
				]
			]
		];
	}

	/**
	 * Check different compositions for Item Card widget.
	 *
	 * @dataProvider getWidgetName
	 */
	public function testDashboardItemCardWidget_Screenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Item Card widget display check'])->waitUntilReady();
		$this->assertScreenshot(CDashboardElement::find()->one()->getWidget($data['Name']), 'itemcard_'.$data['Name']);
	}

	/**
	 * Create or update Item Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update item card widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function fillWidgetForm($data, $action, $dashboard) {
		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('Item card')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		$form->fill($data['fields']);

		if (array_key_exists('Show', $data)) {
			$this->getShowTable()->fill($data['Show']);
		}

		if (array_key_exists('Sparkline', $data)) {
			foreach ($data['Sparkline'] as $field => $value){
				if ($field === 'color') {
					$form->query('class', 'color-picker-box')->one()->click();
					$colorpicker = $this->query('id', 'color_picker')->one()->waitUntilReady();
					$colorpicker->query('class','color-picker-input')->one()->fill($value);
					$colorpicker->query('button:Apply')->one()->click();
				}
				else if ($field === 'widget') {
					$sparkline = $form->query('class', 'widget-field-sparkline')->one()->waitUntilReady();
					$sparkline->query('button', 'Select')->one()->waitUntilClickable()->click();
					$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$dialog->query('link', $value)->one()->click();
				}
				else {
					$form->getField($field)->fill($value);
				}
			}
		}

		if (array_key_exists('Screenshot', $data) && $action === 'create') {
			$this->assertScreenshot($form->query('class:table-forms-separator')->waitUntilPresent()->one(),
					'Full list of show options'.$data['fields']['Item']
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
	 * Check created or updated Item Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update item card widget
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
			$data['fields']['Item'] = 'Visible host name for Item Card widget: '.trim($data['fields']['Item'], 255);

			// Make sure that the widget is present before saving the dashboard.
			$header = (array_key_exists('Name', $data['fields']))
				? (($data['fields']['Name'] === '') ? 'Item card' : $data['fields']['Name'])
				: 'Item card';

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
			['section' => 'Metrics'],
			['section' => 'Type of information'],
			['section' => 'Host interface'],
			['section' => 'Type']
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
