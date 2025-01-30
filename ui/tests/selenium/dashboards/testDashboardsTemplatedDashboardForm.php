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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\Exception\UnexpectedAlertOpenException;

/**
 * @backup dashboard, hosts
 *
 * @dataSource Services, Sla, Proxies
 *
 * @onBefore prepareTemplateDashboardsData
 */
class testDashboardsTemplatedDashboardForm extends CWebTest {

	const WIDGET_SQL = 'SELECT * FROM widget w INNER JOIN dashboard_page dp ON dp.dashboard_pageid=w.dashboard_pageid'.
			' INNER JOIN dashboard d ON d.dashboardid=dp.dashboardid ORDER BY w.widgetid';
	const TEMPLATE = 'Template for dashboard testing';
	const TEMPLATE_ITEM = 'Templates widget item';
	protected static $update_templateid; // ID of the "Template for dashboard testing" for template dashboards tests.
	protected static $hostid_for_template; // ID of the "Empty host for template" to which a template with dashboards will be linked.
	protected static $template_itemid; // ID of item "Templates widget item" that is used to create widgets.
	protected static $dashboardid_with_widgets;
	protected static $empty_dashboardid;
	protected static $dashboardid_for_update;
	protected static $previous_widget_name = 'Widget for update';

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
	 * Function creates host, template, template dashboards and defines the corresponding dashboard IDs.
	 */
	public static function prepareTemplateDashboardsData() {
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Empty host for template',
			'groups' => [['groupid' => 4]] //Zabbix servers.
		]);
		self::$hostid_for_template = $hosts['hostids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE,
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => self::TEMPLATE_ITEM,
						'key_' => 'templ_key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'LLD rule for graph prototype widget',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		self::$update_templateid = $templates['templateids']['Template for dashboard testing'];
		self::$template_itemid = $templates['itemids']['Template for dashboard testing:templ_key[1]'];
		$discoveryruleid =  $templates['discoveryruleids']['Template for dashboard testing:drule'];

		CDataHelper::call('graph.create', [
			[
				'name' => 'Templated graph',
				'gitems' => [['itemid' => self::$template_itemid, 'color' => '00AA00']]
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Templated trigger',
				'expression' => 'last(/'.self::TEMPLATE.'/templ_key[1])=0'
			]
		]);

		$item_protototypes = CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::$update_templateid,
				'ruleid' => $discoveryruleid,
				'name' => 'Template item prototype {#KEY}',
				'key_' => 'trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$item_prototypeid = $item_protototypes['itemids'][0];

		$graph_protototypes = CDataHelper::call('graphprototype.create', [
			[
				'name' => 'Template graph prototype {#KEY}',
				'gitems' => [['itemid' => $item_prototypeid, 'color' => '3333FF']]
			]
		]);
		$graph_prototypeid = $graph_protototypes['graphids'][0];

		$response = CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => self::$update_templateid,
				'name' => 'Dashboard with all widgets',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'actionlog',
								'name' => 'Action log widget',
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'clock',
								'name' => 'Clock widget',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'discovery',
								'name' => 'Discovery status widget',
								'x' => 24,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'favgraphs',
								'name' => 'Favorite graphs widget',
								'x' => 36,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'favmaps',
								'name' => 'Favorite maps widget',
								'x' => 48,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'gauge',
								'name' => 'Gauge widget',
								'x' => 60,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'max',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'min',
										'value' => '0'
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => self::$template_itemid
									]
								]
							],
							[
								'type' => 'geomap',
								'name' => 'Geomap widget',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) widget',
								'x' => 12,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => self::$template_itemid
									]
								]
							],
							[
								'type' => 'graphprototype',
								'name' => 'Graph prototype widget',
								'x' => 24,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 7,
										'name' => 'graphid',
										'value' => $graph_prototypeid
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Graph widget',
								'x' => 36,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'ds.0.items.0',
										'value' => self::TEMPLATE_ITEM
									],
									[
										'type' => 1,
										'name' => 'ds.0.color',
										'value' => 'FF465C'
									]
								]
							],
							[
								'type' => 'hostavail',
								'name' => 'Host availability widget',
								'x' => 48,
								'y' => 4,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'item',
								'name' => 'Item value widget',
								'x' => 60,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => self::$template_itemid
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 4
									]
								]
							],
							[
								'type' => 'map',
								'name' => 'Map widget',
								'x' => 0,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 8,
										'name' => 'sysmapid',
										'value' => 1
									]
								]
							],
							[
								'type' => 'navtree',
								'name' => 'Map navigation tree widget',
								'x' => 12,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FFFFF'
									]
								]
							],
							[
								'type' => 'piechart',
								'name' => 'Pie chart widget',
								'x' => 24,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'fields' => [
									['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Host'],
									['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Item'],
									['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF465C']
								]
							],
							[
								'type' => 'itemhistory',
								'name' => 'Item history widget',
								'x' => 36,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Column_1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => self::$template_itemid
									]
								]
							],
							[
								'type' => 'problemhosts',
								'name' => 'Problem hosts widget',
								'x' => 48,
								'y' => 8,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'problems',
								'name' => 'Problems widget',
								'x' => 60,
								'y' => 8,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'problemsbysv',
								'name' => 'Problems by severity widget',
								'x' => 0,
								'y' => 12,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'slareport',
								'name' => 'SLA report widget',
								'x' => 12,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 10,
										'name' => 'slaid',
										'value' => 6
									],
									[
										'type' => 1,
										'name' => 'date_period.from',
										'value' => '2023-09-01'
									]
								]
							],
							[
								'type' => 'systeminfo',
								'name' => 'System info details widget',
								'x' => 24,
								'y' => 12,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'systeminfo',
								'name' => 'System info HA nodes widget',
								'x' => 36,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'info_type',
										'value' => 1
									]
								]
							],
							[
								'type' => 'tophosts',
								'name' => 'Top hosts widget',
								'x' => 48,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'columns.0.decimal_places',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'columns.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columns.0.name',
										'value' => 'Column 1'
									],
									[
										'type' => 1,
										'name' => 'columns.0.item',
										'value' => self::TEMPLATE_ITEM
									],
									[
										'type' => 0,
										'name' => 'columns.0.display',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.0.history',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.0.data',
										'value' => 1
									]
								]
							],
							[
								'type' => 'toptriggers',
								'name' => 'Top triggers widget',
								'x' => 60,
								'y' => 12,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'trigover',
								'name' => 'Trigger overview widget',
								'x' => 0,
								'y' => 16,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'url',
								'name' => 'URL widget',
								'x' => 12,
								'y' => 16,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'url',
										'value' => 'http://zabbix.com'
									]
								]
							],
							[
								'type' => 'web',
								'name' => 'Web monitoring widget',
								'x' => 24,
								'y' => 16,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'topitems',
								'name' => 'Top items widget',
								'x' => 36,
								'y' => 16,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.items.0',
										'value' => 'Test item'
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb widget',
								'x' => 48,
								'y' => 16,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Test dashboard honeycomb'
									]
								]
							]
						]
					]
				]
			],
			[
				'templateid' => self::$update_templateid,
				'name' => 'Dashboard for widget creation',
				'pages' => [
					[
						'name' => '1st page',
						'widgets' => [
							[
								'type' => 'navtree',
								'name' => 'Empty navtree widget',
								'x' => 0,
								'y' => 0,
								'width' => 24,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'ABCDE'
									]
								]
							]
						]
					],
					[
						'name' => '2nd page'
					]
				]
			],
			[
				'templateid' => self::$update_templateid,
				'name' => 'Dashboard for widget update',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Widget for update',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'clock',
								'name' => 'Widget 4 duplicate check',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'navtree',
								'name' => 'Empty navtree widget',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'EDCBA'
									]
								]
							]
						]
					]
				]
			]
		]);

		self::$dashboardid_with_widgets = $response['dashboardids'][0];
		self::$empty_dashboardid = $response['dashboardids'][1];
		self::$dashboardid_for_update = $response['dashboardids'][2];
	}

	/**
	 * Link the created template with dashboards to "Empty host" host.
	 */
	public static function prepareHostLinkageToTemplateData() {
		CDataHelper::call('host.update', [
			'hostid' => self::$hostid_for_template,
			'templates' => [
				[
					'templateid' => self::$update_templateid
				]
			]
		]);
	}

	public function testDashboardsTemplatedDashboardForm_Layout() {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::$update_templateid);
		$this->query('button:Create dashboard')->one()->click();
		$this->checkDialogue('Dashboard properties');
		// TODO: added updateViewport due to unstable test on Jenkins, scroll appears for 0.5 seconds
		// after closing the overlay dialog and incorrect click location of $control_buttons occurs.
		$this->page->updateViewport();

		// Check the default new dashboard state (title, empty, editable).
		$dashboard = CDashboardElement::find()->asDashboard()->one()->waitUntilReady();

		$this->assertEquals('Dashboards', $dashboard->getTitle());
		$this->assertTrue($dashboard->isEditable());
		$this->assertTrue($dashboard->isEmpty());

		$controls = $dashboard->getControls();
		$control_buttons = [
			'id:dashboard-config',		// Dashboard properties menu icon.
			'button:Add',				// Add widget menu button.
			'id:dashboard-add',			// Dashboard actions chevron.
			'button:Save changes',		// Save changes button.
			'link:Cancel'				// Cancel button.
		];

		// Check dashboard controls and their corresponding actions.
		foreach ($control_buttons as $selector) {
			$this->assertTrue($controls->query($selector)->one(false)->isValid());

			switch ($selector) {
				case 'id:dashboard-config':
					$controls->query($selector)->waitUntilClickable()->one()->click();
					$this->checkDialogue('Dashboard properties');
					break;

				case 'id:dashboard-add':
					$reference_items = [
						'Add widget' => true,
						'Add page' => true,
						'Paste widget' => false,
						'Paste page' => false
					];
					$controls->query($selector)->one()->click();
					$this->checkPopup($reference_items);
					break;

				case 'button:Add':
					$controls->query($selector)->one()->click();
					$this->checkDialogue('Add widget');
					break;
			}
		}

		// Check breadcrumbs.
		foreach (['Hierarchy', 'Content menu'] as $aria_label) {
			$this->assertTrue($this->query('xpath://ul[@aria-label='.zbx_dbstr($aria_label).']')->one()->isClickable());
		}

		// Check the page title and its corresponding actions.
		$this->assertEquals(1, $this->query('class:sortable-item')->all()->count());
		$page_button = $this->query('class:selected-tab')->one();
		$this->assertEquals('Page 1', $page_button->getText());
		$page_button->query('xpath:./button')->one()->forceClick();
		$page_popup_items = [
			'Copy' => true,
			'Delete' => false,
			'Properties' => true
		];
		$this->checkPopup($page_popup_items, 'ACTIONS');

		// Close the dashboard and corresponding popups so that the next scenario would start without alerts.
		$this->closeDialogue();
	}

	/**
	 * Field locators are used for locating individual fields, as well as in cases when it is not possible to get the
	 * field via label or the field element is not positioned right after the label element.
	 * Field and fieldid combination is used when locating fields that are a part of some complex field and that cannot
	 * be located directly by label (non-unique fields within the complex field, other layout anomalies).
	 * In case if fields that are a part of a complex field are checked as individual fields, then field_locator should be
	 * used instead of the Field and fieldid combination (like when checking disable fields).
	 */
	public static function getWidgetDefaultLayoutData() {
		return [
			// #0 Action log widget.
			[
				[
					'type' => 'Action log',
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Recipients',
							'type' => 'multiselect'
						],
						[
							'field' => 'Actions',
							'type' => 'multiselect'
						],
						[
							'field' => 'Media types',
							'type' => 'multiselect'
						],
						[
							'field' => 'Status',
							'type' => 'checkbox_list',
							'checkboxes' => ['In progress' => false, 'Sent/Executed' => false, 'Failed' => false]
						],
						[
							'field' => 'Search string',
							'attributes' => [
								'placeholder' => 'subject or body text',
								'maxlength' => 2048
							]
						],
						[
							'field' => 'Sort entries by',
							'type' => 'dropdown',
							'possible_values' => [
								'Time (descending)',
								'Time (ascending)',
								'Media type (descending)',
								'Media type (ascending)',
								'Status (descending)',
								'Status (ascending)',
								'Recipient (descending)',
								'Recipient (ascending)'
							],
							'value' => 'Time (descending)'
						],
						[
							'field' => 'Show lines',
							'value' => 25,
							'attributes' => [
								'maxlength' => 4
							],
							'mandatory' => true
						]
					]
				]
			],
			// #1 Clock widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Clock'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Time type',
							'type' => 'dropdown',
							'possible_values' => ['Local time', 'Server time', 'Host time'],
							'value' => 'Local time'
						],
						[
							'field' => 'Clock type',
							'type' => 'radio_button',
							'possible_values' => ['Analog', 'Digital'],
							'value' => 'Analog'
						]
					],
					'hidden' => [
						[
							'field' => 'Item',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Show',
							'type' => 'checkbox_list',
							'labels' => ['Date', 'Time', 'Time zone'],
							'values' => ['id:show_1' => false, 'id:show_2' => true, 'id:show_3' => false],
							'mandatory' => true
						],
						[
							'field' => 'Background colour',
							'type' => 'color_picker'
						],
						[
							'field' => 'Date',
							'type' => 'complex_field',
							'contents' => [
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'fieldid' => 'date_bold',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Time',
							'type' => 'complex_field',
							// TODO: remove flag here and in other complex fields when DEV-2652 is fixed.
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-time"]',
							'contents' => [
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'fieldid' => 'time_bold',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								],
								[
									'field' => 'Seconds',
									'type' => 'checkbox',
									'fieldid' => 'time_sec',
									'value' => true
								],
								[
									'field' => 'Format',
									'type' => 'radio_button',
									'fieldid' => 'time_format',
									'possible_values' => ['24-hour', '12-hour'],
									'value' => '24-hour'
								]
							]
						],
						[
							'field' => 'Time zone',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-tzone"]',
							'contents' => [
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'fieldid' => 'tzone_bold',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								]
							]
						]
					],
					'fill_for_hidden' => [
						'Time type' => 'Host time',
						'Clock type' => 'Digital',
						'Advanced configuration' => true
					],
					'second_fill_hidden' => [
						'change_fields' => [
							'id:show_1' => true,
							'id:show_3' => true
						],
						'check_fields' => [
							[
								'field' => 'Show',
								'type' => 'checkbox_list',
								'checkboxes' => ['Date' => false, 'Time' => true, 'Time zone' => false],
								'mandatory' => true
							]
						]
					]
				]
			],
			// #2 Discovery status widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Discovery status'),
					'refresh_interval' => 'Default (1 minute)'
				]
			],
			// #3 Favorite graphs widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Favorite graphs'),
					'refresh_interval' => 'Default (15 minutes)'
				]
			],
			// #4 Favorite maps widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Favorite maps'),
					'refresh_interval' => 'Default (15 minutes)'
				]
			],
			// #5 Gauge widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Gauge'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Item',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Min',
							'value' => 0,
							'attributes' => [
								'maxlength' => 255
							],
							'mandatory' => true
						],
						[
							'field' => 'Max',
							'value' => 100,
							'attributes' => [
								'maxlength' => 255
							],
							'mandatory' => true
						],
						[
							'field' => 'Colours',
							'contents' => [
								[
									'field' => 'Value arc',
									'type' => 'color_picker'
								],
								[
									'field' => 'Arc background',
									'type' => 'color_picker'
								],
								[
									'field' => 'Background',
									'type' => 'color_picker'
								]
							]
						]
					],
					'hidden' => [
						[
							'field' => 'Angle',
							'type' => 'radio_button',
							'possible_values' => ['180°', '270°'],
							'value' => '180°'
						],
						[
							'field' => 'Description',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-description"]',
							'contents' => [
								[
									'field_locator' => 'id:description',
									'value' => '{ITEM.NAME}',
									'attributes' => [
										'maxlength' => 2048,
										'aria-required' => 'true',
										'rows' => 7
									]
								],
								[
									'field' => 'Size',
									'fieldid' => 'desc_size',
									'value' => 15,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Vertical position',
									'type' => 'radio_button',
									'possible_values' => ['Top', 'Bottom'],
									'value' => 'Bottom'
								],
								[
									'field' => 'Bold',
									'fieldid' => 'desc_bold',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Value',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-value"]',
							'contents' => [
								[
									'field' => 'Decimal places',
									'value' => 2,
									'fieldid' => 'decimal_places',
									'attributes' => [
										'maxlength' => 2
									]
								],
								[
									'field' => 'Size',
									'fieldid' => 'value_size',
									'value' => 25,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'fieldid' => 'value_bold',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Colour',
									'field_locator' => 'id:lbl_value_color',
									'type' => 'color_picker'
								],
								[
									'field_locator' => 'id:units_show',
									'type' => 'checkbox',
									'value' => true
								],
								[
									'field_locator' => 'xpath:.//label[text()="Units"]/../following-sibling::div[1]/input',
									'attributes' => [
										'maxlength' => 255
									]
								],
								[
									'field' => 'Size',
									'fieldid' => 'units_size',
									'value' => 25,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'fieldid' => 'units_bold',
									'value' => false
								],
								[
									'field' => 'Position',
									'type' => 'dropdown',
									'fieldid' => 'units_pos',
									'possible_values' => [
										'Before value',
										'Above value',
										'After value',
										'Below value'
									],
									'value' => 'After value'
								],
								[
									'field' => 'Colour',
									'field_locator' => 'id:lbl_units_color',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Value arc',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-value-arc"]',
							'contents' => [
								[
									'field' => 'Size',
									'fieldid' => 'value_arc_size',
									'value' => 20,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								]
							]
						],
						[
							'field' => 'Needle',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-needle"]',
							'contents' => [
								[
									'field_locator' => 'xpath:.//button[@id="lbl_needle_color"]/..',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Scale',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-scale"]',
							'contents' => [
								[
									'field_locator' => 'id:scale_show_units',
									'type' => 'checkbox',
									'value' => true
								],
								[
									'field' => 'Size',
									'fieldid' => 'scale_size',
									'value' => 15,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Decimal places',
									'value' => 0,
									'fieldid' => 'scale_decimal_places',
									'attributes' => [
										'maxlength' => 2
									]
								]
							]
						],
						[
							'field' => 'Thresholds',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-thresholds"]',
							'type' => 'complex_field',
							'contents' => [
								[
									'field_locator' => 'id:thresholds-table',
									'type' => 'table',
									'value' => true,
									'headers' => ['', 'Threshold', ''],
									'buttons' => ['', 'Remove', 'Add']
								],
								[
									'field' => 'Show labels',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Show arc',
									'type' => 'checkbox',
									'value' => false
								],
								// Field parameters are checked in the Disabled section. Here only the presence is checked.
								[
									'field' => 'Arc size'
								]
							]
						]
					],
					/**
					 * Disabled fields are duplicated in hidden fields in order to properly check their default values,
					 * as in Gauge widget to enable some of the fields, value of other disabled fields need to be changed.
					 */
					'disabled' => [
						[
							'field' => 'Show labels',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Show arc',
							'type' => 'checkbox',
							'value' => true
						],
						[
							'field_locator' => 'id:th_arc_size',
							'value' => 5,
							'attributes' => [
								'maxlength' => 3
							],
							'symbol_after' => '%',
							'skip_mandatory_check' => true
						]
					],
					'fill_for_hidden' => [
						'Advanced configuration' => true,
						'id:show_3' => true // Show: Needle
					],
					'click_hidden' => [
						'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]'
					],
					'fill_for_disabled' => [
						'id:thresholds_0_threshold' => '33',
						'Show arc' => true
					],
					'hints' => [
						[
							'label' => 'Description',
							'text' => "Supported macros:\n".
									"{HOST.*}\n".
									"{ITEM.*}\n".
									"{INVENTORY.*}\n".
									"User macros"
						],
						[
							'label' => 'Position',
							'text' => 'Position is ignored for s, uptime and unixtime units.'
						]
					]
				]
			],
			// #6 Geomap widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Geomap'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Initial view',
							'attributes' => [
								'placeholder' => '40.6892494,-74.0466891',
								'maxlength' => 255
							]
						]
					],
					'hints' => [
						[
							'label' => 'Initial view',
							'text' => "Comma separated center coordinates and zoom level to display when the widget ".
									"is initially loaded.\n".
									"Supported formats:\n".
									"<lat>,<lng>,<zoom>\n".
									"<lat>,<lng>\n\n".
									"The maximum zoom level is \"0\".\n".
									"Initial view is ignored if the default view is set."
						]
					]
				]
			],
			// #7 Graph (classic) widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Source',
							'type' => 'radio_button',
							'possible_values' => ['Graph', 'Simple graph'],
							'value' => 'Graph'
						],
						[
							'field' => 'Graph',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Show legend',
							'type' => 'checkbox',
							'value' => true
						]
					],
					'hidden' => [
						[
							'field' => 'Item',
							'type' => 'multiselect',
							'mandatory' => true,
							'replaces' => 'Graph'
						]
					],
					'fill_for_hidden' => [
						'Source' => 'Simple graph'
					]
				]
			],
			// #8 Graph prototype widget.
			[
				[

					'type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Source',
							'type' => 'radio_button',
							'possible_values' => ['Graph prototype', 'Simple graph prototype'],
							'value' => 'Graph prototype'
						],
						[
							'field' => 'Graph prototype',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Show legend',
							'type' => 'checkbox',
							'value' => true
						],
						[
							'field' => 'Columns',
							'value' => 2,
							'attributes' => [
								'maxlength' => 2
							],
							'mandatory' => true
						],
						[
							'field' => 'Rows',
							'value' => 1,
							'attributes' => [
								'maxlength' => 2
							],
							'mandatory' => true
						]
					],
					'hidden' => [
						[
							'field' => 'Item prototype',
							'type' => 'multiselect',
							'mandatory' => true
						]
					],
					'fill_for_hidden' => [
						'Source' => 'Simple graph prototype'
					]
				]
			],
			// #9 Host availability widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Host availability'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Interface type',
							'type' => 'checkbox_list',
							'checkboxes' => [
								'Zabbix agent (active checks)' => false,
								'Zabbix agent (passive checks)' => false,
								'SNMP' => false,
								'JMX' => false,
								'IPMI' => false]
						],
						[
							'field' => 'Layout',
							'type' => 'radio_button',
							'possible_values' => ['Horizontal', 'Vertical'],
							'value' => 'Horizontal'
						],
						[
							'field' => 'Show data in maintenance',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Show only totals',
							'type' => 'checkbox',
							'value' => false
						]
					]
				]
			],
			// #10 Item value widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Item value'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Item',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Show',
							'type' => 'checkbox_list',
							'checkboxes' => ['Description' => true, 'Time' => true, 'Value' => true, 'Change indicator' => true],
							'mandatory' => true
						]
					],
					'hidden' => [
						[
							'field' => 'Description',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-description"]',
							'contents' => [
								[
									'field_locator' => 'id:description',
									'value' => '{ITEM.NAME}',
									'attributes' => [
										'maxlength' => 2048,
										'aria-required' => 'true',
										'rows' => 7
									]
								],
								[
									'field' => 'Horizontal position',
									'type' => 'radio_button',
									'possible_values' => ['Left', 'Center', 'Right'],
									'value' => 'Center'
								],
								[
									'field' => 'Size',
									'fieldid' => 'desc_size',
									'value' => 15,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Vertical position',
									'type' => 'radio_button',
									'possible_values' => ['Top', 'Middle', 'Bottom'],
									'value' => 'Bottom'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Value',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-value"]',
							'contents' => [
								[
									'field' => 'Decimal places',
									'value' => 2,
									'fieldid' => 'decimal_places',
									'attributes' => [
										'maxlength' => 2
									]
								],
								[
									'field' => 'Size',
									'fieldid' => 'decimal_size',
									'value' => 35,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Horizontal position',
									'type' => 'radio_button',
									'possible_values' => ['Left', 'Center', 'Right'],
									'value' => 'Center'
								],
								[
									'field' => 'Size',
									'fieldid' => 'value_size',
									'value' => 45,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Vertical position',
									'type' => 'radio_button',
									'possible_values' => ['Top', 'Middle', 'Bottom'],
									'value' => 'Middle'
								],
								[
									'field' => 'Bold',
									'fieldid' => 'value_bold',
									'type' => 'checkbox',
									'value' => true
								],
								[
									'field' => 'Colour',
									'field_locator' => 'id:lbl_value_color',
									'type' => 'color_picker'
								],
								[
									'field_locator' => 'id:units_show',
									'type' => 'checkbox',
									'value' => true
								],
								[
									'field_locator' => 'xpath:.//label[text()="Units"]/../following-sibling::div[1]/input',
									'attributes' => [
										'maxlength' => 255
									]
								],
								[
									'field' => 'Position',
									'type' => 'dropdown',
									'fieldid' => 'units_pos',
									'possible_values' => [
										'Before value',
										'Above value',
										'After value',
										'Below value'
									],
									'value' => 'After value'
								],
								[
									'field' => 'Size',
									'fieldid' => 'units_size',
									'value' => 35,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'fieldid' => 'units_bold',
									'value' => true
								],
								[
									'field' => 'Colour',
									'field_locator' => 'id:lbl_units_color',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Time',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-time"]',
							'contents' => [
								[
									'field' => 'Horizontal position',
									'type' => 'radio_button',
									'possible_values' => ['Left', 'Center', 'Right'],
									'value' => 'Center'
								],
								[
									'field' => 'Vertical position',
									'type' => 'radio_button',
									'possible_values' => ['Top', 'Middle', 'Bottom'],
									'value' => 'Top'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Colour',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Change indicator',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-change-indicator"]',
							'contents' => [
								[
									'field_locator' => 'id:change-indicator-up',
									'type' => 'indicator'
								],
								[
									'field_locator' => 'xpath:.//input[@id="up_color"]/..',
									'type' => 'color_picker'
								],
								[
									'field_locator' => 'id:change-indicator-down',
									'type' => 'indicator'
								],
								[
									'field_locator' => 'xpath:.//input[@id="down_color"]/..',
									'type' => 'color_picker'
								],
								[
									'field_locator' => 'id:change-indicator-updown',
									'type' => 'indicator'
								],
								[
									'field_locator' => 'xpath:.//input[@id="updown_color"]/..',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Background colour',
							'type' => 'color_picker'
						],
						[
							'field' => 'Thresholds',
							'type' => 'table',
							'headers' => ['', 'Threshold', ''],
							'buttons' => ['Add']
						],
						[
							'field' => 'Aggregation function',
							'type' => 'dropdown',
							'fieldid' => 'aggregate_function',
							'possible_values' => [
								'not used',
								'min',
								'max',
								'avg',
								'count',
								'sum',
								'first',
								'last'
							],
							'value' => 'not used'
						],
						[
							'field' => 'History data',
							'type' => 'radio_button',
							'possible_values' => ['Auto', 'History', 'Trends'],
							'value' => 'Auto'
						]
					],
					'fill_for_hidden' => [
						'Advanced configuration' => true
					],
					'hints' => [
						[
							'label' => 'Description',
							'text' => "Supported macros:\n".
									"{HOST.*}\n".
									"{ITEM.*}\n".
									"{INVENTORY.*}\n".
									"User macros"
						],
						[
							'label' => 'Position',
							'text' => 'Position is ignored for s, uptime and unixtime units.'
						],
						[
							'label' => 'Thresholds',
							'type' => 'warning',
							'text' => 'This setting applies only to numeric data.'
						]
					]
				]
			],
			// #11 Map widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Map'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Map',
							'type' => 'multiselect',
							'mandatory' => true,
							'popup_menu_items' => ['Map', 'Widget']
						]
					]
				]
			],
			// #12 Map navigation tree widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Map navigation tree'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Show unavailable maps',
							'type' => 'checkbox',
							'value' => false
						]
					]
				]
			],
			// #13 Item history widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Item history'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Layout',
							'type' => 'radio_button',
							'possible_values' => ['Horizontal', 'Vertical'],
							'value' => 'Horizontal'
						],
						[
							'field' => 'Items',
							'type' => 'table',
							'headers' => ['', 'Name', 'Item', 'Actions'],
							'buttons' => ['Add'],
							'mandatory' => true
						],
						[
							'field' => 'Show lines',
							'value' => 25,
							'attributes' => [
								'maxlength' => 4
							],
							'mandatory' => true
						]
					],
					'hidden' => [
						[
							'field' => 'New values',
							'type' => 'radio_button',
							'possible_values' => ['Top', 'Bottom'],
							'value' => 'Top'
						],
						[
							'field' => 'Show timestamp',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Show column header',
							'type' => 'radio_button',
							'possible_values' => ['Off', 'Horizontal', 'Vertical'],
							'value' => 'Vertical'
						]
					],
					'fill_for_hidden' => [
						'Advanced configuration' => true
					]
				]
			],
			// #14 Problems hosts widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Problem hosts'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Problem',
							'attributes' => [
								'maxlength' => 2048
							]
						],
						[
							'field' => 'Severity',
							'type' => 'checkbox_list',
							'checkboxes' => [
								'Not classified' => false,
								'Information' => false,
								'Warning' => false,
								'Average' => false,
								'High' => false,
								'Disaster' => false
							]
						],
						[
							'field' => 'Problem tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Show suppressed problems',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Problem display',
							'type' => 'radio_button',
							'possible_values' => ['All', 'Separated', 'Unacknowledged only'],
							'value' => 'All'
						]
					]
				]
			],
			// #15 Problems widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Problems'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Show',
							'type' => 'radio_button',
							'possible_values' => ['Recent problems', 'Problems', 'History'],
							'value' => 'Recent problems'
						],
						[
							'field' => 'Problem',
							'attributes' => [
								'maxlength' => 2048
							]
						],
						[
							'field' => 'Severity',
							'type' => 'checkbox_list',
							'checkboxes' => [
								'Not classified' => false,
								'Information' => false,
								'Warning' => false,
								'Average' => false,
								'High' => false,
								'Disaster' => false
							]
						],
						[
							'field' => 'Problem tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Show tags',
							'type' => 'radio_button',
							'possible_values' => ['None', '1', '2', '3'],
							'value' => 'None'
						],
						[
							'field' => 'Show operational data',
							'type' => 'radio_button',
							'possible_values' => ['None', 'Separately', 'With problem name'],
							'value' => 'None'
						],
						[
							'field' => 'Show symptoms',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Show suppressed problems',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Acknowledgement status',
							'type' => 'radio_button',
							'possible_values' => ['All', 'Unacknowledged', 'Acknowledged'],
							'value' => 'All'
						],
						[
							'field' => 'Sort entries by',
							'type' => 'dropdown',
							'possible_values' => [
								'Time (descending)',
								'Time (ascending)',
								'Severity (descending)',
								'Severity (ascending)',
								'Problem (descending)',
								'Problem (ascending)'
							],
							'value' => 'Time (descending)'
						],
						[
							'field' => 'Show timeline',
							'type' => 'checkbox',
							'value' => true
						],
						[
							'field' => 'Show lines',
							'value' => 25,
							'attributes' => [
								'maxlength' => 4
							],
							'mandatory' => true
						]
					],
					'disabled' => [
						[
							'field' => 'Tag name',
							'type' => 'radio_button',
							'possible_values' => ['Full', 'Shortened', 'None'],
							'value' => 'Full'
						],
						[
							'field' => 'Tag display priority',
							'attributes' => [
								'maxlength' => 2048,
								'placeholder' => 'comma-separated list'
							]
						],
						[
							'skip_mandatory_check' => true,
							'field_locator' => 'id:acknowledged_by_me',
							'type' => 'checkbox',
							'value' => false
						]
					],
					'fill_for_disabled' => [
						'Show tags' => '3',
						'Acknowledgement status' => 'Acknowledged'
					]
				]
			],
			// #16 Problems by severity widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Problems by severity'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Problem',
							'attributes' => [
								'maxlength' => 2048
							]
						],
						[
							'field' => 'Severity',
							'type' => 'checkbox_list',
							'checkboxes' => [
								'Not classified' => false,
								'Information' => false,
								'Warning' => false,
								'Average' => false,
								'High' => false,
								'Disaster' => false
							]
						],
						[
							'field' => 'Problem tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Layout',
							'type' => 'radio_button',
							'possible_values' => ['Horizontal', 'Vertical'],
							'value' => 'Horizontal'
						],
						[
							'field' => 'Show operational data',
							'type' => 'radio_button',
							'possible_values' => ['None', 'Separately', 'With problem name'],
							'value' => 'None'
						],
						[
							'field' => 'Show suppressed problems',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Problem display',
							'type' => 'radio_button',
							'possible_values' => ['All', 'Separated', 'Unacknowledged only'],
							'value' => 'All'
						],
						[
							'field' => 'Show timeline',
							'type' => 'checkbox',
							'value' => true
						]
					]
				]
			],
			// #17 SLA report widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('SLA report'),
					'refresh_interval' => 'Default (No refresh)',
					'fields' => [
						[
							'field' => 'SLA',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Service',
							'type' => 'multiselect'
						],
						[
							'field' => 'Show periods',
							'value' => 20,
							'attributes' => [
								'maxlength' => 3
							]
						],
						[
							'field' => 'From',
							'type' => 'composite_input',
							'attributes' => [
								'maxlength' => 255,
								'placeholder' =>'YYYY-MM-DD'
							]
						],
						[
							'field' => 'To',
							'type' => 'composite_input',
							'attributes' => [
								'maxlength' => 255,
								'placeholder' =>'YYYY-MM-DD'
							]
						]
					]
				]
			],
			// #18 System information widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('System information'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Show',
							'type' => 'radio_button',
							'possible_values' => ['System stats', 'High availability nodes'],
							'value' => 'System stats'
						]
					]
				]
			],
			// #19 Top triggers widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Top triggers'),
					'refresh_interval' => 'Default (No refresh)',
					'fields' => [
						[
							'field' => 'Problem',
							'attributes' => [
								'maxlength' => 2048
							]
						],
						[
							'field' => 'Severity',
							'type' => 'checkbox_list',
							'checkboxes' => [
								'Not classified' => false,
								'Information' => false,
								'Warning' => false,
								'Average' => false,
								'High' => false,
								'Disaster' => false
							]
						],
						[
							'field' => 'Problem tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Trigger limit',
							'attributes' => [
								'maxlength' => 4
							],
							'value' => 10,
							'mandatory' => true
						]
					]
				]
			],
			// #20 Trigger overview widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Trigger overview'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Show',
							'type' => 'radio_button',
							'possible_values' => ['Recent problems', 'Problems', 'Any'],
							'value' => 'Recent problems'
						],
						[
							'field' => 'Problem tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Show suppressed problems',
							'type' => 'checkbox',
							'value' => false
						],
						[
							'field' => 'Layout',
							'type' => 'radio_button',
							'possible_values' => ['Horizontal', 'Vertical'],
							'value' => 'Horizontal'
						]
					]
				]
			],
			// #21 URL widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('URL'),
					'refresh_interval' => 'Default (No refresh)',
					'fields' => [
						[
							'field' => 'URL',
							'attributes' => [
								'maxlength' => 2048
							],
							'mandatory' => true
						]
					]
				]
			],
			// #22 Web monitoring widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Web monitoring'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Scenario tags',
							'type' => 'tags_table',
							'operators' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
							'default_operator' => 'Contains'
						],
						[
							'field' => 'Show data in maintenance',
							'type' => 'checkbox',
							'value' => true
						]
					]
				]
			],
			// #23 Top items widget.
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Top items'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Layout',
							'type' => 'radio_button',
							'possible_values' => ['Horizontal', 'Vertical'],
							'value' => 'Horizontal'
						],
						[
							'field' => 'Show problems',
							'type' => 'radio_button',
							'possible_values' => ['All', 'Unsuppressed', 'None'],
							'value' => 'Unsuppressed'
						],
						[
							'field' => 'Items',
							'type' => 'table',
							'headers' => ['Patterns', 'Actions'],
							'buttons' => ['Add'],
							'mandatory' => true
						]
					],
					'hidden' => [
						[
							'field' => 'Host ordering',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-host-ordering"]',
							'contents' => [
								[
									'field' => 'Order by',
									'type' => 'radio_button',
									'possible_values' => ['Host name', 'Item value'],
									'value' => 'Host name'
								],
								[
									'field' => 'Order',
									'type' => 'radio_button',
									'possible_values' => ['Top N', 'Bottom N'],
									'value' => 'Top N'
								],
								[
									'field' => 'Limit',
									'mandatory' => true
								]
							]
						],
						[
							'field' => 'Item ordering',
							'type' => 'complex_field',
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-item-ordering"]',
							'contents' => [
								[
									'field' => 'Order by',
									'type' => 'radio_button',
									'possible_values' => ['Item value', 'Item name', 'Host'],
									'value' => 'Item value'
								],
								[
									'field' => 'Order',
									'type' => 'radio_button',
									'possible_values' => ['Top N', 'Bottom N'],
									'value' => 'Top N'
								],
								[
									'field' => 'Limit',
									'mandatory' => true
								]
							]
						],
						[
							'field' => 'Show column header',
							'type' => 'radio_button',
							'possible_values' => ['Off', 'Horizontal', 'Vertical'],
							'value' => 'Vertical'
						]
					],
					'fill_for_hidden' => [
						'Advanced configuration' => true
					]
				]
			]
		];
	}

	/**
	 * Function that checks the layout and the default settings of widget configuration forms.
	 *
	 * @dataProvider getWidgetDefaultLayoutData
	 */
	public function testDashboardsTemplatedDashboardForm_WidgetDefaultLayout($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::$update_templateid);
		$this->query('button:Create dashboard')->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady()->close();

		// Select the required type of widget.
		$this->query('button:Add')->one()->waitUntilClickable()->click();
		$widget_dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$widget_form = $widget_dialog->asForm();
		$widget_form->fill(['Type' => $data['type']]);

		// Add Name and refresh interval fields to reference data before checking configuration.
		$refresh_intervals = array_merge([$data['refresh_interval']], ['No refresh', '10 seconds', '30 seconds',
				'1 minute', '2 minutes', '10 minutes', '15 minutes']
		);
		$common_fields = [
			[
				'field' => 'Name',
				'attributes' => [
					'placeholder' => 'default',
					'maxlength' => 255
				]
			],
			[
				'field' => 'Refresh interval',
				'type' => 'dropdown',
				'possible_values' => $refresh_intervals,
				'value' => $data['refresh_interval']
			]
		];
		$data['fields'] = array_merge($common_fields, CTestArrayHelper::get($data, 'fields', []));

		$this->checkFormFields($data['fields'], $widget_form);

		// Check that hidden/disabled fields aren't visible/enabled, perform changes and check that they become available.
		foreach (['hidden', 'disabled'] as $no_access_fields) {
			if (array_key_exists($no_access_fields, $data)) {
				// Check that each of the fields in the list is hidden/disabled.
				foreach ($data[$no_access_fields] as $no_access_field) {
					if ($no_access_fields === 'hidden') {
						$locator = (array_key_exists('field_locator', $no_access_field))
							? $no_access_field['field_locator']
							: 'xpath:.//label[text()='.CXPathHelper::escapeQuotes($no_access_field['field']).
									']/following-sibling::div[1]';

						$this->assertFalse($widget_form->query($locator)->one(false)->isDisplayed());
					}
					else {
						$field_locator = (array_key_exists('disabled_locator', $no_access_field))
							? $no_access_field['disabled_locator']
							: (array_key_exists('field', $no_access_field)
								? $no_access_field['field']
								: $no_access_field['field_locator']);
						$this->assertFalse($widget_form->getField($field_locator)->isEnabled());
					}
				}
				// Reference values are filled in to defined form fields to access the hidden/disabled fields.
				$widget_form->invalidate();
				$widget_form->fill($data['fill_for_'.$no_access_fields]);

				// In some cases it is required to click on link or button for hidden/disabled element to become available.
				if (array_key_exists('click_'.$no_access_fields, $data)) {
					foreach ($data['click_'.$no_access_fields] as $link) {
						$this->query($link)->one()->click();
					}
				}

				// In case if no access fields need to be filled to expand the form, they are checked before being filled.
				if (array_key_exists('second_fill_'.$no_access_fields, $data)) {
					// Check default configuration of field before changing its value.
					$this->checkFormFields($data['second_fill_'.$no_access_fields]['check_fields'], $widget_form);

					// Exclude the checked field from further checks and fill data.
					foreach ($data['second_fill_'.$no_access_fields]['check_fields'] as $checked_field) {
						foreach ($data[$no_access_fields] as $i => $all_fields) {
							if ($checked_field['field'] === $all_fields['field']) {
								unset($data[$no_access_fields][$i]);
							}
						}
					}

					$widget_form->fill($data['second_fill_'.$no_access_fields]['change_fields']);
				}

				$widget_dialog->waitUntilReady();
				$this->checkFormFields($data[$no_access_fields], $widget_form);
			}
		}

		// Check the content of hints in the configuration form.
		if (array_key_exists('hints', $data)) {
			foreach ($data['hints'] as $hint) {
				// Open hint and check text.
				$class = (CTestArrayHelper::get($hint, 'type', 'help') === 'warning') ? 'zi-i-warning' : 'zi-help-filled-small';
				$button = $widget_form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($hint['label']).']/button')->one();
				$this->assertStringContainsString($class, $button->getAttribute('class'));
				$button->click();
				$hint_dialog = $this->query('xpath://div[@data-hintboxid]')->waitUntilPresent()->one();
				$this->assertEquals($hint['text'], $hint_dialog->getText());

				// Close hint.
				$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
				$hint_dialog->waitUntilNotPresent();
			}
		}

		// Check that show header field is set to true.
		$this->assertTrue($widget_form->getField('Show header')->getValue());

		// Close editing dashboard so that next test case would not fail with "Unexpected alert" error.
		$this->closeDialogue();
	}

	/**
	 * Function that locates each and every field from the reference list and passes it for verification of parameters.
	 *
	 * @param	array			$fields			reference array of fields and their parameters
	 * @param	CFormElement	$widget_form	form, in which the field should be checked
	 */
	protected function checkFormFields($fields, $widget_form) {
		// Check form fields and their attributes based on field type.
		foreach ($fields as $field_details) {
			// Field locator is used for stand-alone fields that cannot be located via label.
			$field = (array_key_exists('field_locator', $field_details))
				? $widget_form->query($field_details['field_locator'])->one()->detect()
				: $widget_form->getField($field_details['field']);

			$this->assertTrue($field->isVisible());

			// If the field replaces some other field, make sure that this other field is not displayed anymore.
			if (array_key_exists('replaces', $field_details)) {
				$this->assertFalse($widget_form->getField($field_details['replaces'])->isDisplayed());
			}

			// In case of complex fields each of their subfields is sent for parameter check separately.
			if (CTestArrayHelper::get($field_details, 'type') === 'complex_field') {
				foreach ($field_details['contents'] as $sub_field_details) {
					if (array_key_exists('field', $sub_field_details)) {
						/**
						 * Locate the field from the perspective of its label. It's either the following div or one of
						 * the div elements right after the label with the specified id.
						 */
						$label_xpath = 'xpath:.//label[text()='.CXPathHelper::escapeQuotes($sub_field_details['field']).']';
						$field_locator = array_key_exists('fieldid', $sub_field_details)
							? $label_xpath.'/following-sibling::div/*[@id='.CXPathHelper::escapeQuotes($sub_field_details['fieldid'])."]"
							: $label_xpath.'/following-sibling::div[1]';
					}
					else {
						$field_locator = $sub_field_details['field_locator'];
					}

					$sub_field = $field->query($field_locator)->one();
					$this->checkFieldParameters($sub_field_details, null, $sub_field);
				}
			}
			else {
				$this->checkFieldParameters($field_details, $widget_form, $field);
			}
		}
	}

	/**
	 * Function that verifies the properties of the passed fields according to their type.
	 *
	 * @param array			$field_details		array of reference field parameters
	 * @param CFormElement	$widget_form		form that the field under attention is located in
	 * @param CElement		$field				element that represents the field to be checked
	 */
	protected function checkFieldParameters($field_details, $widget_form, $field) {
		$default_value = CTestArrayHelper::get($field_details, 'value', '');

		switch (CTestArrayHelper::get($field_details, 'type', 'input')) {
			case 'input':
				$this->assertEquals($default_value, $field->getValue());

				if (array_key_exists('attributes', $field_details)) {
					foreach ($field_details['attributes'] as $attribute => $value) {
						$this->assertEquals($value, $field->getAttribute($attribute));
					}
				}
				// Some input elements have a symbol placed right after them, like the "%" sign after Size field.
				if (array_key_exists('symbol_after', $field_details)) {
					$this->assertEquals($field_details['symbol_after'], CElementQuery::getDriver()
							->executeScript('return arguments[0].nextSibling.textContent;', [$field])
					);
				}
				break;

			case 'checkbox':
				$field = $field->asCheckbox();
				$this->assertEquals($default_value, $field->isChecked());
				break;

			case 'multiselect':
				$default_value = '';
				$this->assertEquals($default_value, $field->getValue());
				$this->assertEquals('type here to search', $field->query('xpath:.//input')->one()->getAttribute('placeholder'));

				if (array_key_exists('popup_menu_items', $field_details)) {
					$field->query('class:multiselect-optional-select-button')->one()->click();
					$popup_menu = CPopupMenuElement::find()->one()->waitUntilVisible();
					$this->assertEquals($field_details['popup_menu_items'], $popup_menu->getItems()->asText());
					$popup_menu->close();
				}
				break;

			case 'dropdown':
				$field = $field->asDropdown();
				$this->assertEquals($default_value, $field->getValue());
				$this->assertEquals($field_details['possible_values'], $field->getOptions()->asText());
				break;

			case 'radio_button':
				$field = $field->asSegmentedRadio();
				$this->assertEquals($default_value, $field->getValue());
				$this->assertEquals($field_details['possible_values'], $field->getLabels()->asText());
				break;

			case 'checkbox_list':
				$checkbox_list = $field->asCheckboxList();

				foreach ($field_details['checkboxes'] as $label => $value) {
					$this->assertEquals($value, $checkbox_list->query('xpath:.//label[text()='.
							CXPathHelper::escapeQuotes($label).']/../input')->one()->asCheckbox()->isChecked()
					);
				}
				break;

			case 'composite_input':
				$input = $field->getInput();
				$this->assertEquals($default_value, $input->getValue());

				if (array_key_exists('attributes', $field_details)) {
					foreach ($field_details['attributes'] as $attribute => $value) {
						$this->assertEquals($value, $input->getAttribute($attribute));
					}
				}

				$this->assertTrue($field->query('id:date_period_'.lcfirst($field_details['field']).'_calendar')->one()
						->isClickable()
				);
				break;

			case 'color_picker':
				$field = $field->asColorPicker();
				$this->assertEquals($default_value, $field->getValue());
				break;

			case 'table':
				$table = $field->asTable();
				$this->assertEquals($field_details['headers'], $table->getHeadersText());
				$this->assertEquals($field_details['buttons'], $table->query('tag:button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);
				break;

			case 'tags_table':
				$radio = $field->asSegmentedRadio();
				$this->assertEquals('And/Or', $radio->getValue());
				$this->assertEquals(['And/Or', 'Or'], $radio->getLabels()->asText());

				$table = $radio->query('xpath:./../following-sibling::div[1]')->one();
				$inputs = [
					'id:tags_0_tag' => 'tag',
					'id:tags_0_value' => 'value'
				];

				foreach ($inputs as $locator => $placeholder) {
					$input = $table->query($locator)->one();
					$this->assertEquals(255, $input->getAttribute('maxlength'));
					$this->assertEquals($placeholder, $input->getAttribute('placeholder'));
				}

				$operator = $table->query('id:tags_0_operator')->one()->asDropdown();
				$this->assertEquals($field_details['default_operator'], $operator->getValue());
				$this->assertEquals($field_details['operators'], $operator->getOptions()->asText());
				$this->assertEquals(['Remove', 'Add'], $table->query('class:btn-link')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);
				break;

			case 'indicator':
				$this->assertTrue($field->isDisplayed());
				$this->assertEquals('', $field->query('tag:polygon')->one()->getAttribute('style'));
				break;
		}

		/**
		 * Check is skipped for complex field elements that are checked as individual fields and that cannot be located
		 * by their label. Complex field elements are never marked as mandatory.
		 */
		if ($widget_form && !CTestArrayHelper::get($field_details, 'skip_mandatory_check')) {
			$mandatory = CTestArrayHelper::get($field_details, 'mandatory');
			$this->assertEquals($mandatory, $widget_form->isRequired($field_details['field']));
		}
	}

	public static function getDashboardPropertiesData() {
		return [
			[
				[
					'dashboard_properties' => [
						'Name' => 'Empty dashboard'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => '   '
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => 'Dashboard for widget creation'
					],
					'error_message' => 'Dashboard "Dashboard for widget creation" already exists.',
					'check_save' => true
				]
			],
			[
				[
					'dashboard_properties' => [
						'Name' => '!@#$%^&*()_+=-09[]{};:\'"',
						'Default page display period' => '10 seconds',
						'Start slideshow automatically' => true
					]
				]
			],
			[
				[
					'dashboard_properties' => [
						'Name' => '    Trailing & leading spaces    ',
						'Start slideshow automatically' => false
					],
					'trim' => 'Name'
				]
			],
			[
				[
					'dashboard_properties' => [
						'Name' => '⭐️😀⭐️ Smiley Dashboard ⭐️😀⭐️',
						'Default page display period' => '1 hour'
					]
				]
			]
		];
	}

	/**
	 * Function that checks validation of the Dashboard properties overlay dialog when creating a dashboard.
	 *
	 * @backupOnce dashboard
	 *
	 * @dataProvider getDashboardPropertiesData
	 */
	public function testDashboardsTemplatedDashboardForm_DashboardPropertiesCreate($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::$update_templateid);
		$this->query('button:Create dashboard')->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();

		$form->fill($data['dashboard_properties']);
		$filled_data = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		$this->checkSettings($data, $filled_data);
	}

	/**
	 * Function that checks validation of Dashboard properties overlay dialog update operations.
	 *
	 * @backupOnce dashboard
	 *
	 * @dataProvider getDashboardPropertiesData
	 */
	public function testDashboardsTemplatedDashboardForm_DashboardPropertiesUpdate($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$this->query('id:dashboard-config')->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();

		$form->fill($data['dashboard_properties']);
		$filled_data = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		$this->checkSettings($data, $filled_data, 'updated');
	}

	/**
	 * Function that checks that no changes occur after saving a template dashboard without changes.
	 */
	public function testDashboardsTemplatedDashboardForm_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::WIDGET_SQL);

		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$this->query('button:Save changes')->one()->waitUntilClickable()->click();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::WIDGET_SQL));
	}

	/**
	 * Function that checks that no changes occur after cancelling a template dashboard update.
	 */
	public function testDashboardsTemplatedDashboardForm_Cancel() {
		$old_hash = CDBHelper::getHash(self::WIDGET_SQL);
		$fields = [
			'Name' => 'Cancel dashboard update',
			'Default page display period' => '10 minutes',
			'Start slideshow automatically' => false
		];

		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$this->query('id:dashboard-config')->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->fill($fields);
		$form->submit();

		$this->query('link:Cancel')->one()->waitUntilClickable()->click();
		$this->assertEquals($old_hash, CDBHelper::getHash(self::WIDGET_SQL));
	}

	public static function getWidgetData() {
		return [
			// #0 Action log widget with Show lines = 0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Action log'),
						'Name' => 'Action log with 0 show lines',
						'Show lines' => 0
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #1 Action log widget with too big value in Show lines field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Action log'),
						'Name' => 'Action log with 101 show lines',
						'Show lines' => 1001
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #2 Action log with default values.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Action log'),
						'Name' => 'Action log with default params'
					]
				]
			],
			// #3 Action log with all possible fields specified.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Action log'),
						'Name' => 'Action log with all fields specified',
						'Refresh interval' => '10 minutes',
						'Recipients' => ['Admin', 'guest'],
						'Actions' => 'Report problems to Zabbix administrators',
						'Media types' => ['Email', 'SMS'],
						'Status' => ['In progress', 'Sent/Executed', 'Failed'],
						'Search string' => 'Action log',
						'Sort entries by' => 'Status (ascending)',
						'Show lines' => 100
					],
					'swap_expected' => [
						'Recipients' => ['Admin (Zabbix Administrator)', 'guest']
					]
				]
			],
			// #4 Renaming a widget.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Change widget name'
					]
				]
			],
			// #5 Two identical widgets.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Widget 4 duplicate check'
					],
					'duplicate widget' => true
				]
			],
			// #6 Clock widget with no name.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => ''
					]
				]
			],
			// #7 Change time type to Server time.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Clock widget server time',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// #8 Change time type to Host time and leave item empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Clock widget with Host time no item',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time')
					],
					'error_message' => 'Invalid parameter "Item": cannot be empty.'
				]
			],
			// #9 Change time type to Host time and specify item.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Clock widget with Host time',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => self::TEMPLATE_ITEM
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #10 Widget with trailing and leading spaces in name.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => '    Clock widget with trailing and leading spaces    '
					],
					'trim' => 'Name'
				]
			],
			// #11 Discovery status widget.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Discovery status'),
						'Name' => 'Discovery status widget'
					]
				]
			],
			// #12 Favorite graphs widget.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Favorite graphs'),
						'Name' => 'Favorite graphs widget'
					]
				]
			],
			// #13 Favorite maps widget.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Favorite maps'),
						'Name' => 'Favorite maps widget'
					]
				]
			],
			// #14 Gauge widget with empty Item, Min, Mix and Description.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with missing mandatory input fields',
						'Min' => '',
						'Max' => '',
						'Advanced configuration' => true,
						'id:description' => ''
					],
					'error_message' => [
						'Invalid parameter "Item": cannot be empty.',
						'Invalid parameter "Min": cannot be empty.',
						'Invalid parameter "Max": cannot be empty.',
						'Invalid parameter "Description": cannot be empty.'
					]
				]
			],
			// #15 Gauge widget with non-numeric Min and Max.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with non-numeric min and max',
						'Item' => self::TEMPLATE_ITEM,
						'Min' => 'abc',
						'Max' => 'def'
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.'
					]
				]
			],
			// #16 Gauge widget with non-numeric gauge element sizes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with non-numeric gauge element sizes',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 'abc',
						'id:value_size' => 'abc',
						'id:value_arc_size' => 'abc',
						'id:units_size' => 'abc',
						'id:scale_size' => 'abc'
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM,
						'id:desc_size' => 0,
						'id:value_size' => 0,
						'id:value_arc_size' => 0,
						'id:units_size' => 0,
						'id:scale_size' => 0
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => '9',
							'id:th_show_arc' => true,
							'id:th_arc_size' => 'abc'
						]
					],
					'error_message' => [
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #17 Gauge widget with 0 gauge element sizes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with 0 gauge element sizes',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 0,
						'id:value_size' => 0,
						'id:value_arc_size' => 0,
						'id:units_size' => 0,
						'id:scale_size' => 0
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => '9',
							'id:th_show_arc' => true,
							'id:th_arc_size' => 0
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #18 Gauge widget with too big gauge element sizes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with too big gauge element sizes',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 101,
						'id:value_size' => 101,
						'id:value_arc_size' => 101,
						'id:units_size' => 101,
						'id:scale_size' => 101
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => '5',
							'id:th_show_arc' => true,
							'id:th_arc_size' => 101
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #19 Gauge widget with non-numeric threshold.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with non-numeric threshold',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => 'abc'
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => 'Invalid parameter "Thresholds/1/threshold": a number is expected.'
				]
			],
			// #20 Gauge widget with negative threshold.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with negative threshold',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => '-1'
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => 'Invalid parameter "Thresholds": value must be no less than "0".'
				]
			],
			// #21 Gauge widget with too high threshold.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with too high threshold',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => '101'
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => 'Invalid parameter "Thresholds": value must be no greater than "100".'
				]
			],
			// #22 Gauge widget with too much value and scale decimal places.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with too much value and scale decimal places',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:decimal_places' => 11,
						'id:scale_decimal_places' => 11
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.',
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			// #23 Gauge widget with minimum parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with minimal set of parameters',
						'Item' => self::TEMPLATE_ITEM
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #24 Gauge widget with all parameters defined.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
						'Name' => 'Gauge with all possible parameters',
						'Refresh interval' => '10 minutes',
						'Item' => self::TEMPLATE_ITEM,
						'Min' => 11,
						'Max' => 99,
						'xpath:.//input[@id="value_arc_color"]/..' => '64B5F6',
						'xpath:.//input[@id="empty_color"]/..' => 'FFBF00',
						'xpath:.//input[@id="bg_color"]/..' => 'BA68C8',
						'Show' => ['Description', 'Value', 'Value arc', 'Needle', 'Scale'],
						'Advanced configuration' => true,
						'Angle' => '270°',
						'id:description' => '𒀐 New test Description 😁🙂😁🙂',
						'id:desc_size' => 30,
						'id:desc_bold' => true,
						'id:desc_v_pos' => 'Top',
						'xpath:.//input[@id="desc_color"]/..' => 'FFB300',
						'id:decimal_places' => 10,
						'id:value_size' => 50,
						'id:value_bold' => true,
						'xpath:.//input[@id="value_color"]/..' => '283593',
						'id:value_arc_size' => 12,
						'id:units' => 'Bytes 𒀐  😁',
						'id:units_size' => 27,
						'id:units_bold' => true,
						'id:units_pos' => 'Above value',
						'xpath:.//input[@id="units_color"]/..' => '4E342E',
						'xpath:.//input[@id="needle_color"]/..' => '4DD0E1',
						'id:scale_size' => 33,
						'id:scale_decimal_places' => 8
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'xpath:.//input[@id="thresholds_0_color"]/..' => 'FFC107',
							'id:thresholds_0_threshold' => '50',
							'id:th_show_labels' => true,
							'id:th_show_arc' => true,
							'id:th_arc_size' => 55
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #25 Geomap with non-numeric Initial view.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with non-numeric Initial view',
						'Initial view' => 'abc'
					],
					'error_message' => 'Invalid parameter "Initial view": geographical coordinates (values of comma'.
							' separated latitude and longitude) are expected.'
				]
			],
			// #26 Geomap with a single coordinate in Initial view.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with one coordinate in Initial view',
						'Initial view' => '40.68543,'
					],
					'error_message' => 'Invalid parameter "Initial view": geographical coordinates (values of comma'.
							' separated latitude and longitude) are expected.'
				]
			],
			// #27 Geomap with out of range latitude in Initial view.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with out of range latitude in Initial view',
						'Initial view' => '90.000001,-74'
					],
					'error_message' => 'Invalid parameter "Initial view": geographical coordinates (values of comma'.
							' separated latitude and longitude) are expected.'
				]
			],
			// #28 Geomap with out of range longitude in Initial view.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with out of range longitude in Initial view',
						'Initial view' => '46,180.0001'
					],
					'error_message' => 'Invalid parameter "Initial view": geographical coordinates (values of comma'.
							' separated latitude and longitude) are expected.'
				]
			],
			// #29 Geomap with minimal set of parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with minimal set of parameters'
					]
				]
			],
			// #30 Geomap with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Geomap'),
						'Name' => 'Geomap with all possible parameters',
						'Refresh interval' => '10 seconds',
						'Initial view' => '89.99999,180'
					]
				]
			],
			// #31 Graph Classic widget with missing graph.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with empty graph',
						'Source' => 'Graph',
						'Graph' => ''
					],
					'error_message' => 'Invalid parameter "Graph": cannot be empty.'
				]
			],
			// #32 Graph Classic widget with missing item.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with empty item',
						'Source' => 'Simple graph',
						'Item' => ''
					],
					'error_message' => 'Invalid parameter "Item": cannot be empty.'
				]
			],
			// #33 Graph Classic widget with graph and legend.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with graph and legend',
						'Source' => 'Graph',
						'Graph' => 'Templated graph',
						'Show legend' => true
					],
					'swap_expected' => [
						'Graph' => self::TEMPLATE.': '.'Templated graph'
					]
				]
			],
			// #34 Graph Classic widget with Simple graph and without legend.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Simple graph without legend',
						'Source' => 'Simple graph',
						'Item' => self::TEMPLATE_ITEM,
						'Show legend' => false
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #35 Graph prototype widget with missing graph prototype.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty graph',
						'Source' => 'Graph prototype',
						'Graph prototype' => ''
					],
					'error_message' => [
						'Invalid parameter "Graph prototype": cannot be empty.'
					]
				]
			],
			// #36 Graph prototype widget with missing Columns and Rows.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty graph',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'Template graph prototype {#KEY}',
						'Columns' => '',
						'Rows' => ''
					],
					'swap_expected' => [
						'Graph prototype' => self::TEMPLATE.': '.'Template graph prototype {#KEY}'
					],
					'error_message' => [
						'Invalid parameter "Columns": value must be one of 1-72.',
						'Invalid parameter "Rows": value must be one of 1-64.'
					]
				]
			],
			// #37 Graph prototype widget with missing item prototype.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty item prototype',
						'Source' => 'Simple graph prototype',
						'Item prototype' => ''
					],
					'error_message' => 'Invalid parameter "Item prototype": cannot be empty.'
				]
			],
			// #38 Graph prototype widget with too high number of Columns and Rows.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much Columns',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'Template graph prototype {#KEY}',
						'Columns' => 73,
						'Rows' => 65
					],
					'swap_expected' => [
						'Graph prototype' => self::TEMPLATE.': '.'Template graph prototype {#KEY}'
					],
					'error_message' => [
						'Invalid parameter "Columns": value must be one of 1-72.',
						'Invalid parameter "Rows": value must be one of 1-64.'
					]
				]
			],
			// #39 Graph prototype widget with negative number of Columns.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much Columns',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'Template graph prototype {#KEY}',
						'Columns' => '-5',
						'Rows' => '-5'
					],
					'swap_expected' => [
						'Graph prototype' => self::TEMPLATE.': '.'Template graph prototype {#KEY}'
					],
					'error_message' => [
						'Invalid parameter "Columns": value must be one of 1-72.',
						'Invalid parameter "Rows": value must be one of 1-64.'
					]
				]
			],
			// #40 Graph prototype widget with graph prototype, legend, 2 rows and 2 columns.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget legend',
						'Source' => 'Graph prototype',
						'Graph prototype' => 'Template graph prototype {#KEY}',
						'Show legend' => true,
						'Columns' => 2,
						'Rows' => 2
					],
					'swap_expected' => [
						'Graph prototype' => self::TEMPLATE.': '.'Template graph prototype {#KEY}'
					]
				]
			],
			// #41 Graph prototype widget with simple graph prototype, without legend, 1 row and 1 column.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Simple Graph prototype without legend',
						'Source' => 'Simple graph prototype',
						'Item prototype' => 'Template item prototype {#KEY}',
						'Show legend' => false,
						'Columns' => 1,
						'Rows' => 1
					],
					'swap_expected' => [
						'Item prototype' => self::TEMPLATE.': '.'Template item prototype {#KEY}'
					]
				]
			],
			// #42 Host availability widget with minimal set of parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Host availability'),
						'Name' => 'Host availability with minimal set of parameters'
					]
				]
			],
			// #43 Host availability widget with all possible parameters defined.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Host availability'),
						'Name' => 'Host availability with all possible parameters',
						'Refresh interval' => '10 minutes',
						'Interface type' => [
							'Zabbix agent (active checks)',
							'Zabbix agent (passive checks)',
							'SNMP',
							'JMX',
							'IPMI'
						],
						'Layout' => 'Vertical',
						'Show data in maintenance' => true,
						'Show only totals' => true
					]
				]
			],
			// #44 Item value widget with missing field values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with missing field values',
						'Advanced configuration' => true,
						'id:description' => '',
						'id:desc_size' => '',
						'id:decimal_size' => '',
						'id:value_size' => '',
						'id:units_size' => '',
						'Aggregation function' => 'min',
						'Time period' => 'Custom',
						'id:time_period_from' => '',
						'id:time_period_to' => ''
					],
					'error_message' => [
						'Invalid parameter "Item": cannot be empty',
						'Invalid parameter "Description": cannot be empty.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// #45 Item value widget with non-numeric field values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with non-numeric field values',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 'abc',
						'id:time_size' => 'abc',
						'id:decimal_size' => 'abc',
						'id:value_size' => 'abc',
						'id:units_size' => 'abc',
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'abc',
						'id:time_period_to' => 'abc'
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => 'abc'
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM,
						'id:desc_size' => 0,
						'id:time_size' => 0,
						'id:decimal_size' => 0,
						'id:value_size' => 0,
						'id:units_size' => 0
					],
					'error_message' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.',
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #46 Item value widget with too low field values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with out of range description size',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 0,
						'id:decimal_places' => -1,
						'id:time_size' => 0,
						'id:decimal_size' => 0,
						'id:value_size' => 0,
						'id:units_size' => 0,
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h',
						'id:time_period_to' => 'now-3550'
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Decimal places": value must be one of 0-10.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #47 Item value widget with out of range field values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with out of range time size',
						'Item' => self::TEMPLATE_ITEM,
						'Advanced configuration' => true,
						'id:desc_size' => 101,
						'id:decimal_places' => 11,
						'id:time_size' => 101,
						'id:decimal_size' => 101,
						'id:value_size' => 101,
						'id:units_size' => 101,
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-4y',
						'id:time_period_to' => 'now-1y'
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					],
					'error_message' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Decimal places": value must be one of 0-10.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #49 Item value widget with minimal set of parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with minimal set of parameters',
						'Item' => self::TEMPLATE_ITEM
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #50 Item value widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item value'),
						'Name' => 'Item value with all possible parameters',
						'Refresh interval' => '15 minutes',
						'Item' => self::TEMPLATE_ITEM,
						'Show' => ['Description', 'Time', 'Value', 'Change indicator'],
						'Advanced configuration' => true,
						'id:description' => '!@#$%^&*()_+𒀐😁 description',
						'id:desc_h_pos' => 'Right',
						'id:desc_size' => 11,
						'id:desc_v_pos' => 'Top',
						'id:desc_bold' => true,
						'xpath:.//input[@id="desc_color"]/..' => '0080FF',
						'id:decimal_places' => 7,
						'id:decimal_size' => 23,
						'id:value_h_pos' => 'Left',
						'id:value_size' => 24,
						'id:value_v_pos' => 'Bottom',
						'id:value_bold' => false,
						'xpath:.//input[@id="value_color"]/..' => 'BF00FF',
						'id:units' => '!@#$%^&*()_+𒀐😁 units',
						'Position' => 'Below value',
						'id:units_size' => 15,
						'id:units_bold' => false,
						'xpath:.//input[@id="units_color"]/..' => '00FF00',
						'id:time_h_pos' => 'Right',
						'id:time_size' => 17,
						'id:time_v_pos' => 'Middle',
						'id:time_bold' => true,
						'xpath:.//input[@id="time_color"]/..' => 'B0BEC5',
						'xpath:.//input[@id="up_color"]/..' => 'FFBF00',
						'xpath:.//input[@id="down_color"]/..' => '7B1FA2',
						'xpath:.//input[@id="updown_color"]/..' => 'AFB42B',
						'xpath:.//input[@id="bg_color"]/..' => '00131D',
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1M',
						'id:time_period_to' => 'now-1w',
						'History data' => 'Trends'
					],
					'actions' => [
						'click' => 'xpath:.//table[@id="thresholds-table"]//button[text()="Add"]',
						'fill' => [
							'id:thresholds_0_threshold' => 50,
							'xpath:.//input[@id="thresholds_0_color"]/..' => '8D6E63'
						]
					],
					'swap_expected' => [
						'Item' => self::TEMPLATE.': '.self::TEMPLATE_ITEM
					]
				]
			],
			// #51 Map widget with missing map.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Map'),
						'Name' => 'Map widget with missing map'
					],
					'error_message' => 'Invalid parameter "Map": cannot be empty.'
				]
			],
			// #52 Map widget with map.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Map'),
						'Name' => 'Map widget with map',
						'Map' => 'Local network'
					]
				]
			],
			// #53 Map navigation tree widget.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Map navigation tree'),
						'Name' => 'Map navigation tree widget',
						'Refresh interval' => '1 minute',
						'Show unavailable maps' => true
					]
				]
			],
			// #54 Item history widget with empty Items parameter.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item history'),
						'Name' => 'Item history widget with empty Items',
						'Show lines' => ''
					],
					'error_message' => [
						'Invalid parameter "Items": cannot be empty.',
						'Invalid parameter "Show lines": value must be one of 1-1000.'
					]
				]
			],
			// TODO: Uncomment and fix when DEV-4069 is ready.
//			// #55 Item history widget with too high value of Show lines parameter.
//			[
//				[
//					'expected' => TEST_BAD,
//					'fields' => [
//						'Type' => CFormElement::RELOADABLE_FILL('Item history'),
//						'Name' => 'Item history widget with too much lines',
//						'Show lines' => 9999
//					],
//					'Column' => [
//						'Name' => 'Column1',
//						'Item' => [
//							'values' => self::TEMPLATE_ITEM,
//							'context' => ['values' => self::TEMPLATE]
//						]
//					],
//					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-100.'
//				]
//			],
//			// #56 Item history widget with negative Show lines parameter.
//			[
//				[
//					'expected' => TEST_BAD,
//					'fields' => [
//						'Type' => CFormElement::RELOADABLE_FILL('Item history'),
//						'Name' => 'Item history widget with too much lines',
//						'Show lines' => -99
//					],
//					'Column' => [
//						'Name' => 'Column1',
//						'Item' => [
//							'values' => self::TEMPLATE_ITEM,
//							'context' => ['values' => self::TEMPLATE]
//						]
//					],
//					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-100.'
//				]
//			],
			// #57 Item history widget with Values location = Bottom, Show timestamp =true and Column header = Off.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item history'),
						'Name' => 'Item history widget top location HTML',
						'Show lines' => 9,
						'Advanced configuration' => true,
						'New values' => 'Bottom',
						'Show timestamp' => true,
						'Show column header' => 'Off'
					],
					'Column' => [
						'Name' => 'Column1',
						'Item' => [
							'values' => self::TEMPLATE_ITEM,
							'context' => ['values' => self::TEMPLATE]
						]
					]
				]
			],
			// #58 Item history widget with Vertical layout and Horizontal header.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Item history'),
						'Name' => 'Item history widget left location no HTML',
						'Layout' => 'Vertical',
						'Show lines' => 100,
						'Advanced configuration' => true,
						'Show column header' => 'Horizontal'
					],
					'Column' => [
						'Item' => [
							'values' => self::TEMPLATE_ITEM,
							'context' => ['values' => self::TEMPLATE]
						]
					]
				]
			],
			// #59 Problem hosts widget with default parameters.
			[
				[

					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problem hosts'),
						'Name' => 'Problem hosts with default parameters'
					]
				]
			],
			// #60 Problem hosts widget with all possible parameters.
			[
				[

					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problem hosts'),
						'Name' => 'Problem hosts with all possible parameters',
						'Refresh interval' => '10 minutes',
						'Problem' => 'Everything became so expensive',
						'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
						'Problem tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Does not contain',
						'id:tags_0_value' => 'tag_value',
						'Show suppressed problems' => true,
						'Problem display' => 'Unacknowledged only'
					]
				]
			],
			// #61 Problems widget with empty Show lines parameter (reset to 0).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems'),
						'Name' => 'Problems widget with empty Show lines',
						'Show lines' => ''
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #62 Problems widget with too high value of Show lines parameter.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems'),
						'Name' => 'Problems widget with too much lines',
						'Show lines' => 1001
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #63 Problems widget with negative Show lines parameter.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems'),
						'Name' => 'Problems widget with negative Show lines',
						'Show lines' => -1
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #64 Problems widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems'),
						'Name' => 'Problems widget with default parameters'
					]
				]
			],
			// #65 Problems widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems'),
						'Name' => 'Problems widget with all parameters',
						'Refresh interval' => 'No refresh',
						'Show' => 'History',
						'Problem' => 'Education and healthcare are regressing in my country',
						'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
						'Problem tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Does not contain',
						'id:tags_0_value' => 'tag_value',
						'Show tags' => 2,
						'Tag name' => 'Shortened',
						'Tag display priority' => 'young,old',
						'Show operational data' => 'With problem name',
						'Show symptoms' => true,
						'Show suppressed problems' => true,
						'id:acknowledgement_status' => 'Acknowledged',
						'id:acknowledged_by_me' => true,
						'Sort entries by' => 'Time (ascending)',
						'Show timeline' => true,
						'Show lines' => 11
					]
				]
			],
			// #66 Problems by severity widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems by severity'),
						'Name' => 'Problems by severity widget with default parameters'
					]
				]
			],
			// #67 Problems by severity widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Problems by severity'),
						'Name' => 'Problems by severity widget with all parameters',
						'Refresh interval' => '10 seconds',
						'Problem' => 'Our reality is disappointing',
						'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
						'Problem tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Does not equal',
						'id:tags_0_value' => 'tag_value',
						'Layout' => 'Vertical',
						'Show operational data' => 'Separately',
						'Show suppressed problems' => true,
						'Problem display' => 'Separated',
						'Show timeline' => true
					]
				]
			],
			// #68 SLA report widget with missing SLA.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with missing SLA'
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "SLA": cannot be empty.'
				]
			],
			// #69 SLA widget with non-numeric show periods.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with non-numeric show periods',
						'SLA' => 'SLA Daily',
						'Show periods' => 'abc'
					],
					'swap_expected' => [
						'Show periods' => 0
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// #70 SLA widget with too large value in show periods.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with too large value in show periods',
						'SLA' => 'SLA Daily',
						'Show periods' => '101'
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// #71 SLA widget with floating point value in show periods.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with float in show periods',
						'SLA' => 'SLA Daily',
						'Show periods' => '0.5'
					],
					'swap_expected' => [
						'Show periods' => 0
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// #72 SLA widget with negative value in show periods.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with negative show periods',
						'SLA' => 'SLA Daily',
						'Show periods' => '-5'
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// #73 SLA widget with string type From and To dates.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with strings in From and To',
						'SLA' => 'SLA Daily',
						'From' => 'yesterday',
						'To' => 'today + 1 day'
					],
					'page' => '2nd page',
					'error_message' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// #74 SLA widget with wrong From date and To date format.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with wrong From and To format',
						'SLA' => 'SLA Daily',
						'From' => '2022/01/01',
						'To' => '2022/02/01'
					],
					'page' => '2nd page',
					'error_message' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// #75 SLA widget with From date and To date too far in the past.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with From and To too far in the past',
						'SLA' => 'SLA Daily',
						'From' => '1968-01-01',
						'To' => '1969-10-10'
					],
					'page' => '2nd page',
					'error_message' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// #76 SLA widget with From date and To date too far in the future.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with From and To too far in the future',
						'SLA' => 'SLA Daily',
						'From' => '2040-01-01',
						'To' => '2050-10-10'
					],
					'page' => '2nd page',
					'error_message' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// #77 SLA widget with minimal set of parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with minimal set of parameters',
						'SLA' => 'SLA Daily'
					],
					'page' => '2nd page'
				]
			],
			// #78 SLA widget with all possible parameters set.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with all possible parameters',
						'Refresh interval' => '15 minutes',
						'SLA' => 'SLA Daily',
						'Service' => 'Simple actions service',
						'Show periods' => 15,
						'From' => '2023-08-01',
						'To' => '2023-08-10'
					],
					'page' => '2nd page'
				]
			],
			// #79 SLA widget with dynamic From and To.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('SLA report'),
						'Name' => 'SLA report widget with dynamic From and To',
						'SLA' => 'SLA Daily',
						'From' => 'now/y',
						'To' => 'now/y+1M-1w-1d'
					],
					'page' => '2nd page'
				]
			],
			// #80 System information widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('System information'),
						'Name' => 'System information widget with default parameters'
					],
					'page' => '2nd page'
				]
			],
			// #81 System information widget with all parameters specified.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('System information'),
						'Name' => 'System information widget with all parameters',
						'Refresh interval' => '2 minutes',
						'Show' => 'High availability nodes'
					],
					'page' => '2nd page'
				]
			],
			// #82 Top triggers widget with empty Trigger limit.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with empty Trigger limit',
						'Trigger limit' => ''
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			// #83 Top triggers widget with non-numeric Trigger limit.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with non-numeric Trigger limit',
						'Trigger limit' => 'abc'
					],
					'swap_expected' => [
						'Trigger limit' => '0'
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			// #84 Top triggers widget with zero Trigger limit.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with zero Trigger limit',
						'Trigger limit' => 0
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			// #85 Top triggers widget with out of range Trigger limit.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with out of range Trigger limit',
						'Trigger limit' => 1001
					],
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			// #86 Top triggers widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with default parameters'
					],
					'page' => '2nd page'
				]
			],
			// #87 Top triggers widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top triggers'),
						'Name' => 'Top triggers widget with all possible parameters',
						'Refresh interval' => '30 seconds',
						'Problem' => 'Our plnet is doomed',
						'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
						'Problem tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Does not contain',
						'id:tags_0_value' => 'tag_value',
						'Trigger limit' => 100
					],
					'page' => '2nd page'
				]
			],
			// #88 Trigger overview widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Trigger overview'),
						'Name' => 'Trigger overview widget with default parameters'
					],
					'page' => '2nd page'
				]
			],
			// #89 Trigger overview widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Trigger overview'),
						'Name' => 'Trigger overview widget with all parameters',
						'Refresh interval' => '10 seconds',
						'Show' => 'Any',
						'Problem tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Contains',
						'id:tags_0_value' => 'tag_value',
						'Show suppressed problems' => true,
						'Layout' => 'Vertical'
					],
					'page' => '2nd page'
				]
			],
			// #90 URL widget with special symbols in URL.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with text URL',
						'Refresh interval' => '10 minutes',
						'URL' => '!@#$%^&*()_+ļūāšķ'
					],
					'page' => '2nd page'
				]
			],
			// #91 URL widget with trailing and leading spaces in URL.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with trailing and leading spaces in URL',
						'URL' => '    URL    '
					],
					'trim' => 'URL',
					'page' => '2nd page'
				]
			],
			// #92 URL widget with empty URL (after trimming).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with space in URL',
						'URL' => '     '
					],
					'trim' => 'URL',
					'page' => '2nd page',
					'error_message' => 'Invalid parameter "URL": cannot be empty.'
				]
			],
			// #93 Web monitoring widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Web monitoring'),
						'Name' => 'Web monitoring widget with default parameters'
					],
					'page' => '2nd page'
				]
			],
			// #94 Web monitoring widget with all possible parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Web monitoring'),
						'Name' => 'Web monitoring widget with all parameters',
						'Refresh interval' => '30 seconds',
						'Scenario tags' => 'Or',
						'id:tags_0_tag' => 'tag_name',
						'id:tags_0_operator' => 'Equals',
						'id:tags_0_value' => 'tag_value',
						'Show data in maintenance' => true
					],
					'page' => '2nd page'
				]
			],
			// #95 Data overview widget with default parameters. TODO: Update to correct Top Items - DEV-4101
//			[
//				[
//					'fields' => [
//						'Type' => CFormElement::RELOADABLE_FILL('Data overview'),
//						'Name' => 'Data overview widget with default parameters'
//					],
//					'page' => '2nd page'
//				]
//			],
			// #96 Data overview widget with all possible parameters. TODO: Update to correct Top Items - DEV-4101
//			[
//				[
//					'fields' => [
//						'Type' => CFormElement::RELOADABLE_FILL('Data overview'),
//						'Name' => 'Data overview widget with all parameters',
//						'Refresh interval' => '10 seconds',
//						'Item tags' => 'Or',
//						'id:tags_0_tag' => 'tag_name',
//						'id:tags_0_operator' => 'Equals',
//						'id:tags_0_value' => 'tag_value',
//						'Show suppressed problems' => true,
//						'Host location' => 'Top'
//					],
//					'page' => '2nd page'
//				]
//			],
			// #97 Top hosts widget with default parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top hosts'),
						'Name' => 'Top hosts widget with required fields'
					],
					'Column' => [
						'Name' => 'Column1',
						'Item name' => [
							'values' => self::TEMPLATE_ITEM,
							'context' => ['values' => self::TEMPLATE]
						]
					],
					'page' => '2nd page'
				]
			],
			// #98 Top hosts widget with all parameters.
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Top hosts'),
						'Name' => 'Top hosts widget with other fields',
						'Show data in maintenance' => true,
						'Order' => 'Bottom N'
					],
					'Column' => [
						'Name' => 'Column1',
						'Item name' => [
							'values' => self::TEMPLATE_ITEM,
							'context' => ['values' => self::TEMPLATE]
						],
						'xpath:.//input[@id="base_color"]/..' => '00796B',
						'Display item value as' => 'Numeric',
						'Display' => 'Indicators',
						'Min' => 10,
						'Max' => 99,
						'Decimal places' => 9,
						'Advanced configuration' => true,
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'From' => 'now-20h',
						'To' => 'now-10h',
						'History data' => 'Trends'
					],
					'page' => '2nd page'
				]
			]
		];
	}

	/**
	 * Function that validates the Widget configuration form for a template dashboard.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardsTemplatedDashboardForm_CreateWidget($data) {
		try {
			$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$empty_dashboardid);
		}
		catch (UnexpectedAlertOpenException $e) {
			// Sometimes previous test leaves dashboard edit page open.
			$this->page->acceptAlert();
			$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$empty_dashboardid);
		}

		if (CTestArrayHelper::get($data, 'page')) {
			CDashboardElement::find()->one()->selectPage($data['page']);
		}

		$this->query('button:Add')->one()->waitUntilClickable()->click();

		// Set widget configuration and save filled in data for further validation.
		$filled_data = $this->fillWidgetConfigurationFrom($data);

		// For some fields default values or context should be added to the reference array before comparison.
		if (array_key_exists('swap_expected', $data)) {
			foreach ($data['swap_expected'] as $swap_field => $new_value) {
				$data['fields'][$swap_field] = $new_value;
			}
		}

		$this->checkSettings($data, $filled_data, 'updated', 'widget create');
	}

	/**
	 * Function that checks update of template dashboards widgets parameters.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardsTemplatedDashboardForm_UpdateWidget($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_for_update);
		CDashboardElement::find()->one()->getWidget(self::$previous_widget_name)->edit();

		// Update widget configuration and save filled in data for further validation.
		$filled_data = $this->fillWidgetConfigurationFrom($data, true);

		// For some fields default values or context should be added to the reference array before comparison.
		if (array_key_exists('swap_expected', $data)) {
			foreach ($data['swap_expected'] as $swap_field => $new_value) {
				$data['fields'][$swap_field] = $new_value;
			}
		}

		$this->checkSettings($data, $filled_data, 'updated', 'widget update');
	}

	/**
	 * Fill in widget configuration and save filled in data for further validation.
	 *
	 * @param array	$data	data provider
	 *
	 * @return array
	 */
	protected function fillWidgetConfigurationFrom($data, $update = false) {
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill($data['fields']);

		if (array_key_exists('Column', $data)) {
			if ($update) {
				$button_remove = $form->query('button:Remove');
				$remove_count = $button_remove->count();

				for ($i = 0; $i < $remove_count; $i++) {
					$button_remove->waitUntilClickable()->one()->click();
					$form->waitUntilReloaded();
				}
			}

			$container = CTestArrayHelper::get($data, 'Column.Item', false) ? 'Items' : 'Columns';
			$form->getFieldContainer($container)->query('button:Add')->one()->waitUntilClickable()->click();
			$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$column_overlay->asForm()->fill($data['Column']);
			$column_overlay->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();
			$column_overlay->waitUntilNotVisible();
			$form->waitUntilReloaded();

			// Open Advanced config again because after column filling it becomes collapsed for Item history widget.
			if ($container === 'Items') {
				$form->fill(['Advanced configuration' => true]);
			}
		}

		// Some field changes, like changing the type of map widget, result in dialog reload, so need to wait until it's done.
		if (CTestArrayHelper::get($data, 'form_reload')) {
			$dialog->waitUntilReady();
		}

		// To reach some fields additional actions need to be taken, sometimes even more than one action.
		if (array_key_exists('actions', $data)) {
			foreach ($data['actions'] as $type => $action_element) {
				if ($type === 'click') {
					$form->query($action_element)->one()->click();
				}
				else {
					foreach ($action_element as $field => $value) {
						$form->getField($field)->fill($value);
					}
				}
			}
		}

		// Trimming is only triggered together with an on-change event which is generated once focus is removed.
		$this->page->removeFocus();
		$form->invalidate();
		$filled_data = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		// In case of the scenario with identical widgets the same widget needs to be added once again.
		if (array_key_exists('duplicate widget', $data)) {
			$this->query('button:Add')->one()->waitUntilClickable()->click();
			$form->invalidate();
			$form->fill($data['fields']);
			$this->page->removeFocus();
			$form->submit();
		}

		return $filled_data;
	}

	/**
	 * Function that checks the layout of a template dashboard with widgets from monitoring hosts view.
	 * The ignore browser errors annotation is required due to the errors coming from the URL opened in the URL widget.
	 *
	 * @ignoreBrowserErrors
	 *
	 * @onBefore prepareHostLinkageToTemplateData
	 */
	public function testDashboardsTemplatedDashboardForm_ViewDashboardOnHost() {
		$this->page->login()->open('zabbix.php?action=host.dashboard.view&hostid='.self::$hostid_for_template);
		$this->query('xpath://span[text()="Dashboard with all widgets"]')->one()->waitUntilVisible()->click();
		$this->page->waitUntilReady();

		$time_period = [
			'id:from' => '2023-09-01 08:00:00',
			'id:to' => '2023-09-01 10:00:00'
		];

		foreach ($time_period as $field => $time) {
			$this->query($field)->one()->fill($time);
		}

		$this->query('button:Apply')->one()->click();
		CDashboardElement::find()->one()->waitUntilReady();

		$skip_selectors = [
			'class:clock',
			'xpath://th[text()="Zabbix frontend version"]/following-sibling::td[1]',
			'class:widget-url',
			'xpath://footer',
			// Cover Geomap widget, because it's screenshots are not stable.
			'xpath://div[contains(@class, "leaflet-container")]'
		];
		$skip_elements = [];

		foreach ($skip_selectors as $identifier) {
			$skip_elements[] = $this->query($identifier)->waitUntilVisible()->one();
		}

		$this->assertScreenshotExcept(null, $skip_elements, 'dashboard_on_host');
	}

	/**
	 * Functions that checks the layout of the "Dashboard properties" and "Add widget" overlay dialogs.
	 *
	 * @param string	$title	The title of the overlay dialog.
	 */
	protected function checkDialogue($title) {
		if ($title === 'Dashboard properties') {
			$parameters = [
				'Name' => 'New dashboard',
				'Default page display period' => '30 seconds',
				'Start slideshow automatically' => true
			];
			$buttons = ['Apply', 'Cancel'];
			$display_periods = ['10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'];
		}
		else {
			$parameters = [
				'Show header' => true
			];
			$buttons = ['Add', 'Cancel'];
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$this->assertEquals($title, $dialog->getTitle());

		$this->assertEquals(2, $dialog->getFooter()->query('button', $buttons)->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		foreach ($parameters as $name => $value) {
			$this->assertEquals($value, $form->getField($name)->getValue());
		}

		if ($title === 'Dashboard properties') {
			$this->assertEquals($display_periods, $form->getField('Default page display period')->getOptions()->asText());
		}
		else {
			$all_types = ['Action log', 'Clock', 'Discovery status', 'Favorite graphs', 'Favorite maps', 'Gauge', 'Geomap',
				'Graph', 'Graph (classic)', 'Graph prototype', 'Honeycomb', 'Host availability', 'Host card',
				'Host navigator', 'Item history', 'Item navigator', 'Item value', 'Map', 'Map navigation tree',
				'Pie chart', 'Problem hosts', 'Problems', 'Problems by severity', 'SLA report', 'System information',
				'Top hosts', 'Top items', 'Top triggers', 'Trigger overview', 'URL', 'Web monitoring'
			];
			$this->assertEquals($all_types, $form->getField('Type')->getOptions()->asText());
		}
		$dialog->close();
	}

	/**
	 * Function that checks the content of popup menu elements on a template dashboard.
	 *
	 * @param array		$items	An array of items and their states in a popup menu.
	 * @param string	$title	The title of the popup menu.
	 */
	protected function checkPopup($items, $title = false) {
		$popup = CPopupMenuElement::find()->one()->waitUntilVisible();

		foreach ($items as $item => $enabled) {
			$this->assertTrue($popup->getItem($item)->isEnabled($enabled));
		}

		if ($title) {
			$this->assertEquals([$title], $popup->getTitles()->asText());
		}
	}

	/**
	 * Function that closes an overlay dialog and alert on a template dashboard before proceeding to the next test.
	 */
	protected function closeDialogue() {
		$overlay = COverlayDialogElement::find()->one(false);
		if ($overlay->isValid()) {
			$overlay->close();
		}
		$this->query('link:Cancel')->one()->forceClick();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}
	}

	/**
	 * Function that checks the previously saved dashboard settings form.
	 *
	 * @param array		$data			Data provider.
	 * @param array		$filled_data	Values obtained from the configuration form before saving the dashboard.
	 * @param string	$status			Expected successful action that was made to the dashboard after saving it.
	 * @param string	$check			Action that should be checked.
	 */
	protected function checkSettings($data, $filled_data, $status = 'created', $check = 'dashboard action') {
		$reference_data = ($check === 'dashboard action') ? $data['dashboard_properties'] : $data['fields'];

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			if (CTestArrayHelper::get($data, 'check_save')) {
				$this->query('button:Save changes')->one()->click();
			}
			else {
				if (array_key_exists('trim', $data)) {
					$filled_data[$data['trim']] = trim($filled_data[$data['trim']]);
					$reference_data[$data['trim']] = trim($data['fields'][$data['trim']]);
				}
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
				$this->assertEquals($filled_data, $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues());
				$form->checkValue($reference_data);
			}

			// Count of days mentioned in error depends ot presence of leap year february in selected period.
			if (CTestArrayHelper::get($data, 'days_count')) {
				$data['error_message'] = str_replace('{days}', CDateTimeHelper::countDays('now', 'P2Y'), $data['error_message']);
			}

			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			$this->closeDialogue();
		}
		else {
			COverlayDialogElement::ensureNotPresent();
			// Wait for widgets to be present as dashboard is slow when there are many widgets on it.
			if ($check !== 'dashboard action') {
				if (CTestArrayHelper::get($data, 'trim') === 'Name') {
					$data['fields']['Name'] = trim($data['fields']['Name']);
				}
				$name = ($data['fields']['Name'] === '') ? 'Local' : $data['fields']['Name'];
				CDashboardElement::find()->waitUntilReady()->one()->getWidget($name);
			}
			$this->query('button:Save changes')->one()->click();

			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard '.$status);

			// In case of successful widget update rewrite the widget name to be updated for the next scenario.
			if ($check === 'widget update') {
				self::$previous_widget_name = $name;
			}

			// Trim trailing and leading spaces from reference dashboard name if necessary.
			$created_values = $filled_data;
			if (array_key_exists('trim', $data)) {
				$created_values[$data['trim']] = trim($created_values[$data['trim']]);
				$reference_data[$data['trim']] = trim($reference_data[$data['trim']]);
			}

			$dashboard_name = ($check === 'dashboard action')
					? $reference_data['Name']
					: (($check === 'widget create') ? 'Dashboard for widget creation' : 'Dashboard for widget update');
			$this->query('link', $dashboard_name)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();

			if (CTestArrayHelper::get($data, 'page') && $check === 'widget create') {
				CDashboardElement::find()->one()->selectPage($data['page']);
			}

			if ($check !== 'dashboard action') {
				$reopened_form = CDashboardElement::find()->waitUntilReady()->one()->getWidget($name)->edit();
			}
			else {
				$this->query('id:dashboard-config')->one()->click();
				$reopened_form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}

			if (array_key_exists('Advanced configuration', $reference_data)) {
				$reopened_form->fill(['Advanced configuration' => true]);
				$this->assertTrue($reopened_form->query('xpath:.//button[@title="Collapse"]')->one()->isVisible());
			}

			$this->assertEquals($created_values, $reopened_form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))
					->asValues()
			);
			$reopened_form->checkValue($reference_data);

			// Check saved column and item name.
			if (array_key_exists('Column', $data)) {
				$row = $reopened_form->query('id:list_columns')->asTable()->one()->getRow(0);
				$this->assertEquals(CTestArrayHelper::get($data['Column'], 'Name', CTestArrayHelper::get($data, 'Column.Item.values')),
						$row->getColumn('Name')->getText()
				);

				$column_name = CTestArrayHelper::get($data, 'Column.Item', false) ? 'Item' : 'Data';
				$this->assertEquals(self::TEMPLATE_ITEM, $row->getColumn($column_name)->getText());
			}

			$this->closeDialogue();
		}
	}
}
