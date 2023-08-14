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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
use Facebook\WebDriver\Exception\UnexpectedAlertOpenException;

/**
 * @backup dashboard, hosts
 *
 * @onBefore prepareTemplateDashboardsData
 */
class testFormTemplateDashboards extends CWebTest {

	const UPDATE_TEMPLATEID = 50000;	// ID of the "Template ZBX6663 First" template used for template dashboards tests.
	const HOST_FOR_TEMPLATE = 99015;	// ID of the "Empty host" host to which a template with dashboards will be linked.

	protected static $dashboardid_with_widgets;
	protected static $empty_dashboardid;
	protected static $dashboardid_for_update;

	private static $previous_widget_name = 'Widget for update';

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
	 * Function creates template dashboards and defines the corresponding dashboard IDs.
	 */
	public static function prepareTemplateDashboardsData() {
		$response = CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => self::UPDATE_TEMPLATEID,
				'name' => 'Dashboard with all widgets',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Clock widget',
								'width' => 4,
								'height' => 4
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) widget',
								'x' => 4,
								'y' => 0,
								'width' => 8,
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
										'value' => 400410
									]
								]
							],
							[
								'type' => 'plaintext',
								'name' => 'Plain text widget',
								'x' => 12,
								'y' => 0,
								'width' => 6,
								'height' => 4,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemids',
										'value' => 400410
									]
								]
							],
							[
								'type' => 'url',
								'name' => 'URL widget',
								'x' => 18,
								'y' => 0,
								'width' => 6,
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
								'type' => 'graphprototype',
								'name' => 'Graph prototype widget',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 6,
								'fields' => [
									[
										'type' => 7,
										'name' => 'graphid',
										'value' => 700016
									]
								]
							]
						]
					]
				]
			],
			[
				'templateid' => self::UPDATE_TEMPLATEID,
				'name' => 'Empty Dashboard without widgets',
				'pages' => [[]]
			],
			[
				'templateid' => self::UPDATE_TEMPLATEID,
				'name' => 'Dashboard for widget update',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Widget for update',
								'x' => 0,
								'y' => 0,
								'width' => 4,
								'height' => 4
							],
							[
								'type' => 'clock',
								'name' => 'Widget 4 duplicate check',
								'x' => 4,
								'y' => 0,
								'width' => 4,
								'height' => 4
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
	 * Function that links the template with dashboards to "Empty host" host.
	 */
	public static function prepareHostLinkageToTemplateData() {
		CDataHelper::call('host.update', [
			'hostid' => self::HOST_FOR_TEMPLATE,
			'templates' => [
				[
					'templateid' => self::UPDATE_TEMPLATEID
				]
			]
		]);
	}

	public function testFormTemplateDashboards_Layout() {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::UPDATE_TEMPLATEID);
		$this->query('button:Create dashboard')->one()->click();
		$this->checkDialogue('Dashboard properties');

		// Check the default new dashboard state (title, empty, editable).
		$dashboard = CDashboardElement::find()->asDashboard()->one()->waitUntilVisible();
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

	public static function getWidgetLayoutData() {
		return [
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
								'maxlength' => 3
							],
							'mandatory' => true
						]
					]
				]
			],
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
					'hidden_fields' => [
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
							'field' => 'Background color',
							'type' => 'color_picker'
						],
						[
							'field' => 'Date',
							'type' => 'complex_field',
							'contents' => [
								[
									'field' => 'Size',
									'element_locator' => 'id:date_size',
									'value' => 20,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'element_locator' => 'id:date_bold',
									'value' => false
								],
								[
									'field' => 'Color',
									'type' => 'color_picker'
								]
							]
						],
						[
							'field' => 'Time',
							'type' => 'complex_field',
							// TODO: remove flag when issue in framework is solved (complex field + radio button).
							'field_locator' => 'xpath:.//div[@class="fields-group fields-group-time"]',
							'contents' => [
								[
									'field' => 'Size',
									'element_locator' => 'id:time_size',
									'value' => 30,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'element_locator' => 'id:time_bold',
									'value' => false
								],
								[
									'field' => 'Color',
									'type' => 'color_picker'
								],
								[
									'field' => 'Seconds',
									'type' => 'checkbox',
									'element_locator' => 'id:time_sec',
									'value' => true
								],
								[
									'field' => 'Format',
									'type' => 'radio_button',
									'element_locator' => 'id:time_format',
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
									'field' => 'Size',
									'element_locator' => 'id:tzone_size',
									'value' => 20,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'element_locator' => 'id:tzone_bold',
									'value' => false
								],
								[
									'field' => 'Color',
									'type' => 'color_picker'
								]
							]
						]
					],
					'change_values' => [
						'Time type' => 'Host time',
						'Clock type' => 'Digital',
						'Advanced configuration' => true
					],
					'second_change' => [
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
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Discovery status'),
					'refresh_interval' => 'Default (1 minute)'
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Favorite graphs'),
					'refresh_interval' => 'Default (15 minutes)'
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Favorite maps'),
					'refresh_interval' => 'Default (15 minutes)'
				]
			],
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
					'hidden_fields' => [
						[
							'field' => 'Item',
							'type' => 'multiselect',
							'mandatory' => true,
							'replaces' => 'Graph'
						]
					],
					'change_values' => [
						'Source' => 'Simple graph'
					]
				]
			],
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
					'hidden_fields' => [
						[
							'field' => 'Item prototype',
							'type' => 'multiselect',
							'mandatory' => true
						]
					],
					'change_values' => [
						'Source' => 'Simple graph prototype'
					]
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Host availability'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Interface type',
							'type' => 'checkbox_list',
							'checkboxes' => ['Zabbix agent' => false, 'SNMP' => false, 'JMX' => false, 'IPMI' => false]
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
						]
					]
				]
			],
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
						],

					],
					'hidden_fields' => [
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
									'element_locator' => 'id:desc_size',
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
									'field' => 'Color',
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
									'element_locator' => 'id:decimal_places',
									'attributes' => [
										'maxlength' => 2
									]
								],
								[
									'field' => 'Size',
									'element_locator' => 'id:decimal_size',
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
									'field_id' => 'value_size',
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
									'element_locator' => 'id:value_bold',
									'type' => 'checkbox',
									'value' => true
								],
								[
									'field' => 'Color',
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
									'field_id' => 'units_pos',
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
									'field_id' => 'units_size',
									'value' => 35,
									'attributes' => [
										'maxlength' => 3
									],
									'symbol_after' => '%'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'field_id' => 'units_bold',
									'value' => true
								],
								[
									'field' => 'Color',
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
									'field' => 'Size',
									'element_locator' => 'id:time_size',
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
									'value' => 'Top'
								],
								[
									'field' => 'Bold',
									'type' => 'checkbox',
									'value' => false
								],
								[
									'field' => 'Color',
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
							'field' => 'Background color',
							'type' => 'color_picker'
						],
						[
							'field' => 'Thresholds',
							'type' => 'table',
							'headers' => ['', 'Threshold', 'Action'],
							'buttons' => ['Add']
						]
					],
					'change_values' => [
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
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Map'),
					'refresh_interval' => 'Default (15 minutes)',
					'fields' => [
						[
							'field' => 'Source type',
							'type' => 'radio_button',
							'possible_values' => ['Map', 'Map navigation tree'],
							'value' => 'Map'
						],
						[
							'field' => 'Map',
							'type' => 'multiselect',
							'mandatory' => true
						]
					],
					'hidden_fields' => [
						[
							'field' => 'Filter',
							'type' => 'dropdown',
							'mandatory' => true,
							'possible_values' => ['Select widget'],
							'value' => 'Select widget',
							'replaces' => 'Map'
						]
					],
					'change_values' => [
						'Source type' => 'Map navigation tree'
					]
				]
			],
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
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Plain text'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Items',
							'type' => 'multiselect',
							'mandatory' => true
						],
						[
							'field' => 'Items location',
							'type' => 'radio_button',
							'possible_values' => ['Left', 'Top'],
							'value' => 'Left'
						],
						[
							'field' => 'Show lines',
							'value' => 25,
							'attributes' => [
								'maxlength' => 3
							],
							'mandatory' => true
						],
						[
							'field' => 'Show text as HTML',
							'type' => 'checkbox',
							'value' => false
						]
					]
				]
			],
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
							'possible_values' => ['all', 'Unacknowledged', 'Acknowledged'],
							'value' => 'all'
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
								'maxlength' => 3
							],
							'mandatory' => true
						]
					],
					'disabled_fields' => [
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
							'field_locator' => 'xpath:.//label[text()="By me"]/../following-sibling::li/input[@type="checkbox"]',
							'type' => 'checkbox',
							'value' => false
						]
					],
					'change_values' => [
						'Show tags' => '3',
						'Acknowledgement status' => 'Acknowledged'
					]
				]
			],
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
							'field' => 'Host location',
							'type' => 'radio_button',
							'possible_values' => ['Left', 'Top'],
							'value' => 'Left'
						]
					]
				]
			],
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
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Data overview'),
					'refresh_interval' => 'Default (1 minute)',
					'fields' => [
						[
							'field' => 'Item tags',
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
							'field' => 'Host location',
							'type' => 'radio_button',
							'possible_values' => ['Left', 'Top'],
							'value' => 'Left'
						]
					],
					'hints' => [
						[
							'label' => 'Type',
							'type' => 'warning',
							'text' => 'Widget is deprecated.'
						]
					]
				]
			]
		];
	}

	// Graph and Top hosts layout should be checked separately.

	/**
	 * Function that checks the layout and the default settings of widget configuration forms.
	 *
	 * @dataProvider getWidgetLayoutData
	 */
	public function testFormTemplateDashboards_WidgetDefaultLayout($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::UPDATE_TEMPLATEID);
		$this->query('button:Create dashboard')->one()->click();
		COverlayDialogElement::find()->one()->waitUntilVisible()->close();

		// Select the required type of widget.
		$this->query('button:Add')->one()->waitUntilClickable()->click();
		$widget_dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$widget_form = $widget_dialog->asForm();
		$widget_form->fill(['Type' => $data['type']]);

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

		foreach (['hidden_fields', 'disabled_fields'] as $no_access_fields) {
			if (array_key_exists($no_access_fields, $data)) {
				foreach ($data[$no_access_fields] as $no_access_field) {
					if ($no_access_fields === 'hidden_fields') {
						$this->assertFalse($widget_form->query("xpath:.//label[text()=".
								CXPathHelper::escapeQuotes($no_access_field['field'])."]/following-sibling::div[1]")
								->one(false)->isDisplayed()
						);
					}
					else {
						$field_locator = (array_key_exists('field', $no_access_field))
							? $no_access_field['field']
							: $no_access_field['field_locator'];
						$this->assertFalse($widget_form->getField($field_locator)->isEnabled());
					}
				}

				/**
				 * There is no widget in data provider that has both hidden and disabled fields, so change_values is
				 * filled and field check is performed within the foreach loop.
				 */
				$widget_form->invalidate();
				$widget_form->fill($data['change_values']);

				// In case if no access fields need to be filled to expand the form, they are checked before being filled.
				if (array_key_exists('second_change', $data)) {
					// Check default configuration of field before changing its value.
					$this->checkFormFields($data['second_change']['check_fields'], $widget_form);

					// Exclude the checked field from further checks and fill data.
					foreach ($data['second_change']['check_fields'] as $checked_field) {
						foreach ($data[$no_access_fields] as $i => $all_fields) {
							if ($checked_field['field'] === $all_fields['field']) {
								unset($data[$no_access_fields][$i]);
							}
						}
					}

					$widget_form->fill($data['second_change']['change_fields']);
				}

				$widget_dialog->waitUntilReady();
				$this->checkFormFields($data[$no_access_fields], $widget_form);
			}
		}

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

		$this->assertTrue($widget_form->getField('Show header')->getValue());

		// Close editing dashboard so that next test case would not fail with "Unexpected alert" error.
		$this->closeDialogue();
	}

	private function checkFormFields($fields, $widget_form) {
		// Check form fields and their attributes based on field type.
		foreach ($fields as $field_details) {
			// Field locator is temporary workaround until issue in framework is not solved (complex field + radio button).
			if (array_key_exists('field_locator', $field_details)) {
				$field = $widget_form->query($field_details['field_locator'])->one();
			}
			else {
				$field = $widget_form->getField($field_details['field']);
			}

			if (array_key_exists('replaces', $field_details)) {
				$this->assertFalse($widget_form->getField($field_details['replaces'])->isDisplayed());
			}

			if (CTestArrayHelper::get($field_details, 'type') === 'complex_field') {
				foreach ($field_details['contents'] as $sub_field_details) {
					if (array_key_exists('field', $sub_field_details)) {
						$label_xpath = "xpath:.//label[text()=".CXPathHelper::escapeQuotes($sub_field_details['field'])."]";
						$field_locator =  array_key_exists('field_id', $sub_field_details)
							? $label_xpath."/following-sibling::div/*[@id=".CXPathHelper::escapeQuotes($sub_field_details['field_id'])."]"
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

	private function checkFieldParameters($field_details, $widget_form, $field) {
		$default_value = CTestArrayHelper::get($field_details, 'value', '');
		$element = (array_key_exists('element_locator', $field_details))
			? $field->query($field_details['element_locator'])->one()
			: $field;

		switch (CTestArrayHelper::get($field_details, 'type', 'input')) {
				case 'input':
					$this->assertEquals($default_value, $element->getValue());

					if (array_key_exists('attributes', $field_details)) {
						foreach ($field_details['attributes'] as $attribute => $value) {
							$this->assertEquals($value, $element->getAttribute($attribute));
						}
					}

					if (array_key_exists('symbol_after', $field_details)) {
						$this->assertEquals($field_details['symbol_after'], CElementQuery::getDriver()
								->executeScript('return arguments[0].nextSibling.textContent;', [$element])
						);
					}

					break;

				case 'checkbox':
					$element = $element->asCheckbox();
					$this->assertEquals($default_value, $element->isChecked());
					break;

				case 'multiselect':
					$default_value = '';
					$this->assertEquals($default_value, $element->getValue());
					$this->assertEquals('type here to search', $element->query('xpath:.//input')->one()->getAttribute('placeholder'));
					break;

				case 'dropdown':
					$element = $element->asDropdown();
					$this->assertEquals($default_value, $element->getValue());
					$this->assertEquals($field_details['possible_values'], $element->getOptions()->asText());
					break;

				case 'radio_button':
					$element = $element->asSegmentedRadio();
					$this->assertEquals($default_value, $element->getValue());
					$this->assertEquals($field_details['possible_values'], $element->getLabels()->asText());
					break;

				case 'checkbox_list':
					$checkbox_list = $element->asCheckboxList();

					foreach ($field_details['checkboxes'] as $label => $value) {
						$this->assertEquals($value, $checkbox_list->query("xpath:.//label[text()=".
								CXPathHelper::escapeQuotes($label)."]/../input")->one()->asCheckbox()->isChecked()
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

					$this->assertTrue($field->query('name:date_'.lcfirst($field_details['field']).'_calendar')->one()->isClickable());
					break;

				case 'color_picker':
					$element = $element->asColorPicker();
					$this->assertEquals($default_value, $element->getValue());
					break;

				case 'table':
					$table = $element->asTable();
					$this->assertEquals($field_details['headers'], $table->getHeadersText());

					$this->assertEquals($field_details['buttons'], $table->query('tag:button')->all()
							->filter(CElementFilter::CLICKABLE)->asText()
					);
					break;

				case 'tags_table':
					$radio = $element->asSegmentedRadio();
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
					$this->assertTrue($element->isDisplayed());
					$this->assertEquals('', $element->query('tag:polygon')->one()->getAttribute('style'));
					break;
			}

			// Complex field elements don't have mandatory fields, so no use wasting time on checkingif they are mandatory.
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
						'Name' => 'Empty Dashboard without widgets'
					],
					'error_message' => 'Dashboard "Empty Dashboard without widgets" already exists.',
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
	public function testFormTemplateDashboards_DashboardPropertiesCreate($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.list&templateid='.self::UPDATE_TEMPLATEID);
		$this->query('button:Create dashboard')->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();

		$form->fill($data['dashboard_properties']);
		$old_values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		$this->checkSettings($data, $old_values);
	}

	/**
	 * Function that checks validation of Dashboard properties overlay dialog update operations.
	 *
	 * @backupOnce dashboard
	 *
	 * @dataProvider getDashboardPropertiesData
	 */
	public function testFormTemplateDashboards_DashboardPropertiesUpdate($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$this->query('id:dashboard-config')->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();

		$form->fill($data['dashboard_properties']);
		$old_values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		$this->checkSettings($data, $old_values, 'updated');
	}

	/**
	 * Function that checks that no changes occur after saving a template dashboard without changes.
	 */
	public function testFormTemplateDashboards_SimpleUpdate() {
		$sql = 'SELECT * FROM widget w INNER JOIN dashboard_page dp ON dp.dashboard_pageid=w.dashboard_pageid '.
				'INNER JOIN dashboard d ON d.dashboardid=dp.dashboardid ORDER BY w.widgetid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$this->query('button:Save changes')->one()->waitUntilClickable()->click();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that checks that no changes occur after cancelling a template dashboard update.
	 */
	public function testFormTemplateDashboards_Cancel() {
		$sql = 'SELECT * FROM widget w INNER JOIN dashboard_page dp ON dp.dashboard_pageid=w.dashboard_pageid '.
				'INNER JOIN dashboard d ON d.dashboardid=dp.dashboardid ORDER BY w.widgetid';
		$old_hash = CDBHelper::getHash($sql);
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
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	public static function getWidgetsCreateData() {
		return [
			// Renaming a widget
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Change widget name'
					]
				]
			],
			// Two identical widgets
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Widget 4 duplicate check'
					],
					'duplicate widget' => true
				]
			],
			// Clock widget with no name
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => ''
					]
				]
			],
			// Change time type to Server time
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Clock widget server time',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// Change time type to Host time and leave item empty
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
			// Change time type to Host time and specify item
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => 'Clock widget with Host time',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item ZBX6663 Second'
					]
				]
			],
			// Widget with trailing and leading spaces in name
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Clock'),
						'Name' => '    Clock widget with trailing and leading spaces    '
					],
					'trim' => 'Name'
				]
			],
			// Graph Classic widget with missing graph
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with empty graph',
						'Source' => 'Graph',
						'Graph' => []
					],
					'error_message' => 'Invalid parameter "Graph": cannot be empty.'
				]
			],
			// Graph Classic widget with missing item
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with empty item',
						'Source' => CFormElement::RELOADABLE_FILL('Simple graph'),
						'Item' => []
					],
					'error_message' => 'Invalid parameter "Item": cannot be empty.'
				]
			],
			// Graph Classic widget with graph and legend
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Graph widget with graph and legend',
						'Source' => 'Graph',
						'Graph' => ['Graph ZBX6663 Second'],
						'Show legend' => true
					]
				]
			],
			// Graph Classic widget with Simple graph and without legend
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
						'Name' => 'Simple graph without legend',
						'Source' => CFormElement::RELOADABLE_FILL('Simple graph'),
						'Item' => ['Item ZBX6663 Second'],
						'Show legend' => false
					]
				]
			],
			// Graph prototype widget with missing graph prototype
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty graph',
						'Source' => 'Graph prototype',
						'Graph prototype' => []
					],
					'error_message' => 'Invalid parameter "Graph prototype": cannot be empty.'
				]
			],
			// Graph prototype widget with missing item prototype
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty item prototype',
						'Source' => CFormElement::RELOADABLE_FILL('Simple graph prototype'),
						'Item prototype' => []
					],
					'error_message' => 'Invalid parameter "Item prototype": cannot be empty.'
				]
			],
			// Graph prototype widget with empty Columns parameter
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with empty Columns (dropped to 0)',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Columns' => ''
					],
					'error_message' => 'Invalid parameter "Columns": value must be one of 1-24.'
				]
			],
			// Graph prototype widget with too high number of Columns
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much Columns',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Columns' => 55
					],
					'error_message' => 'Invalid parameter "Columns": value must be one of 1-24.'
				]
			],
			// Graph prototype widget with negative number of Columns
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much Columns',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Columns' => '-5'
					],
					'error_message' => 'Invalid parameter "Columns": value must be one of 1-24.'
				]
			],
			// Graph prototype widget with missing number of Rows
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with missing Rows (dropped to 0)',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Rows' => ''
					],
					'error_message' => 'Invalid parameter "Rows": value must be one of 1-16.'
				]
			],
			// Graph prototype widget with number of Rows too high
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much rows',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Rows' => 55
					],
					'error_message' => 'Invalid parameter "Rows": value must be one of 1-16.'
				]
			],
			// Graph prototype widget with negative number of Rows
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget with too much rows',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Rows' => '-5'
					],
					'error_message' => 'Invalid parameter "Rows": value must be one of 1-16.'
				]
			],
			// Graph prototype widget with graph prototype, legend, 2 rows and 2 columns
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Graph prototype widget legend',
						'Source' => 'Graph prototype',
						'Graph prototype' => ['GraphPrototype ZBX6663 Second'],
						'Show legend' => true,
						'Columns' => 2,
						'Rows' => 2
					]
				]
			],
			// Graph prototype widget with simple graph prototype, without legend, 2 row and 2 column
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
						'Name' => 'Simple Graph prototype without legend',
						'Source' => CFormElement::RELOADABLE_FILL('Simple graph prototype'),
						'Item prototype' => ['ItemProto ZBX6663 Second'],
						'Show legend' => false,
						'Columns' => 1,
						'Rows' => 1
					]
				]
			],
			// Plain text widget with empty Items parameter
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget with empty Items',
						'Items' => []
					],
					'error_message' => 'Invalid parameter "Items": cannot be empty.'
				]
			],
			// Plain text widget with empty Show lines parameter (reset to 0)
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget with empty Show lines',
						'Items' => ['Item ZBX6663 Second'],
						'Show lines' => ''
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// Plain text widget with too high value of Show lines parameter
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget with too much lines',
						'Items' => ['Item ZBX6663 Second'],
						'Show lines' => 999
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// Plain text widget with negative Show lines parameter
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget with negative Show lines',
						'Items' => ['Item ZBX6663 Second'],
						'Show lines' => '-9'
					],
					'error_message' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// Plain text widget with Items location = top and text shown as HTML
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget top location HTML',
						'Items' => ['Item ZBX6663 Second'],
						'Show lines' => 9,
						'Items location' => 'Top',
						'Show text as HTML' => true
					]
				]
			],
			// Plain text widget with Items location = left and text shown as plain text
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Plain text'),
						'Name' => 'Plain text widget left location no HTML',
						'Items' => ['Item ZBX6663 Second'],
						'Show lines' => 9,
						'Items location' => 'Left',
						'Show text as HTML' => false
					]
				]
			],
			// URL widget with empty URL
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with empty URL'
					],
					'error_message' => 'Invalid parameter "URL": cannot be empty.'
				]
			],
			// URL widget with incorrect URL
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with text URL',
						'URL' => 'home_sweet_home'
					]
				]
			],
			// URL widget with trailing and leading spaces in URL
			[
				[
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with trailing and leading spaces in URL',
						'URL' => '    URL    '
					],
					'trim' => 'URL'
				]
			],
			// URL widget with spaces in URL
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('URL'),
						'Name' => 'URL widget with space in URL',
						'URL' => '     '
					],
					'trim' => 'URL',
					'error_message' => 'Invalid parameter "URL": cannot be empty.'
				]
			]
		];
	}

	/**
	 * Function that validates the Widget configuration form for a template dashboard.
	 *
	 * @dataProvider getWidgetsCreateData
	 */
	public function testFormTemplateDashboards_CreateWidget($data) {
		try {
			$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$empty_dashboardid);
		}
		catch (UnexpectedAlertOpenException $e) {
			// Sometimes previous test leaves dashboard edit page open.
			$this->page->acceptAlert();
			$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$empty_dashboardid);
		}

		$this->query('button:Add')->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->fill($data['fields']);

		// Trimming is only triggered together with an on-change event which is generated once focus is removed.
		$this->page->removeFocus();
		$old_values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		// In case of the scenario with identical widgets the same widget needs to be added once again.
		if (array_key_exists('duplicate widget', $data)) {
			$this->query('button:Add')->one()->waitUntilClickable()->click();
			$form->invalidate();
			$form->fill($data['fields']);
			$this->page->removeFocus();
			$form->submit();
		}

		$this->checkSettings($data, $old_values, 'updated', 'widget create');
	}

	/**
	 * Function that checks update of template dashboards widgets parameters.
	 *
	 * @dataProvider getWidgetsCreateData
	 */
	public function testFormTemplateDashboards_UpdateWidget($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_for_update);

		$form = CDashboardElement::find()->one()->getWidget(self::$previous_widget_name)->edit();
		COverlayDialogElement::find()->waitUntilReady();
		$form->fill($data['fields']);
		$this->page->removeFocus();
		COverlayDialogElement::find()->waitUntilReady();
		$old_values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();

		$this->checkSettings($data, $old_values, 'updated', 'widget update');
	}

	/**
	 * Function that checks the layout of a template dashboard with widgets from monitoring hosts view.
	 * The ignore browser errors annotation is required due to the errors coming from the URL opened in the URL widget.
	 *
	 * @ignoreBrowserErrors
	 *
	 * @onBefore prepareHostLinkageToTemplateData
	 */
	public function testFormTemplateDashboards_ViewDashboardOnHost() {
		$this->page->login()->open('zabbix.php?action=host.dashboard.view&hostid='.self::HOST_FOR_TEMPLATE);
		$this->page->waitUntilReady();
		$this->query('id:dashboardid')->asDropdown()->one()->select('Dashboard with all widgets');

		$skip_selectors = [
			'class:clock',
			'class:flickerfreescreen',
			'class:widget-url',
			'xpath://footer'
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
	private function checkDialogue($title) {
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
					'Graph', 'Graph (classic)', 'Graph prototype', 'Host availability', 'Item value', 'Map',
					'Map navigation tree', 'Plain text', 'Problem hosts', 'Problems', 'Problems by severity', 'SLA report',
					'System information', 'Top hosts', 'Top triggers', 'Trigger overview', 'URL', 'Web monitoring', 'Data overview'
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
	private function checkPopup($items, $title = false) {
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
	private function closeDialogue() {
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
	 * @param array		$old_values		Values obtained from the configuration form before saving the dashboard.
	 * @param string	$status			Expected successful action that was made to the dashboard after saving it.
	 * @param string	$check			Action that should be checked.
	 */
	private function checkSettings($data, $old_values, $status = 'created', $check = 'dashboard action') {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			if (CTestArrayHelper::get($data, 'check_save')) {
				$this->query('button:Save changes')->one()->click();
			}
			else {
				if (array_key_exists('trim', $data)) {
					$old_values[$data['trim']] = trim($old_values[$data['trim']]);
				}
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
				$this->assertEquals($old_values, $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues());
			}
			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			$this->closeDialogue();
		}
		else {
			COverlayDialogElement::ensureNotPresent();
			// Wait for widgets to be present as dashboard is slow when there ame many widgets on it.
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
			$created_values = $old_values;
			if (array_key_exists('trim', $data)) {
				$created_values[$data['trim']] = trim($created_values[$data['trim']]);
			}

			$dashboard_name = ($check === 'dashboard action')
					? $created_values['Name']
					: (($check === 'widget create') ? 'Empty Dashboard without widgets' : 'Dashboard for widget update');
			$this->query('link', $dashboard_name)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();

			if ($check !== 'dashboard action') {
				$reopened_form = CDashboardElement::find()->waitUntilReady()->one()->getWidget($name)->edit();
			}
			else {
				$this->query('id:dashboard-config')->one()->click();
				$reopened_form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}

			$this->assertEquals($created_values, $reopened_form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues());

			$this->closeDialogue();
		}
	}
}
