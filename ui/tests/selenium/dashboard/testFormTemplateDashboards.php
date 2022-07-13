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
				'name' => 'Dashboard without widgets',
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
					$controls->query($selector)->one()->click();
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
					'type' => 'Clock',
					'fields' => [
						[
							'name' => 'Name',
							'attributes' => [
								'placeholder' => 'default',
								'maxlength' => 255
							]
						],
						[
							'name' => 'Time type',
							'type' => 'dropdown',
							'possible_values' => ['Local time', 'Server time', 'Host time'],
							'value' => 'Local time'
						]
					]
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Graph (classic)'),
					'fields' => [
						[
							'name' => 'Name',
							'attributes' => [
								'placeholder' => 'default',
								'maxlength' => 255
							]
						],
						[
							'name' => 'Source',
							'type' => 'radio_button',
							'possible_values' => ['Graph', 'Simple graph'],
							'value' => 'Graph'
						],
						[
							'name' => 'Graph',
							'type' => 'multiselect'
						],
						[
							'name' => 'Show legend',
							'type' => 'checkbox',
							'value' => true
						]
					]
				]
			],
			[
				[

					'type' => CFormElement::RELOADABLE_FILL('Graph prototype'),
					'fields' => [
						[
							'name' => 'Name',
							'attributes' => [
								'placeholder' => 'default',
								'maxlength' => 255
							]
						],
						[
							'name' => 'Source',
							'type' => 'radio_button',
							'possible_values' => ['Graph prototype', 'Simple graph prototype'],
							'value' => 'Graph prototype'
						],
						[
							'name' => 'Graph prototype',
							'type' => 'multiselect'
						],
						[
							'name' => 'Show legend',
							'type' => 'checkbox',
							'value' => true
						],
						[
							'name' => 'Columns',
							'value' => 2,
							'attributes' => [
								'maxlength' => 2
							]
						],
						[
							'name' => 'Rows',
							'value' => 1,
							'attributes' => [
								'maxlength' => 2
							]
						]
					]
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('Plain text'),
					'fields' => [
						[
							'name' => 'Name',
							'attributes' => [
								'placeholder' => 'default',
								'maxlength' => 255
							]
						],
						[
							'name' => 'Items',
							'type' => 'multiselect'
						],
						[
							'name' => 'Items location',
							'type' => 'radio_button',
							'possible_values' => ['Left', 'Top'],
							'value' => 'Left'
						],
						[
							'name' => 'Show lines',
							'value' => 25,
							'attributes' => [
								'maxlength' => 3
							]
						],
						[
							'name' => 'Show text as HTML',
							'type' => 'checkbox',
							'value' => false
						]
					]
				]
			],
			[
				[
					'type' => CFormElement::RELOADABLE_FILL('URL'),
					'fields' => [
						[
							'name' => 'Name',
							'attributes' => [
								'placeholder' => 'default',
								'maxlength' => 255
							]
						],
						[
							'name' => 'URL',
							'attributes' => [
								'maxlength' => 255
							]
						]
					]
				]
			]
		];
	}

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
		$widget_dialog = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$widget_dialog->fill(['Type' => $data['type']]);
		COverlayDialogElement::find()->waitUntilReady();

		// Check form fields and their attributes based on field type.
		foreach ($data['fields'] as $field_details) {
			$field = $widget_dialog->getField($field_details['name']);
			$default_value = CTestArrayHelper::get($field_details, 'value', '');

			switch (CTestArrayHelper::get($field_details, 'type', 'input')) {
				case 'input':
				case 'checkbox':
					$this->assertEquals($default_value, $field->getValue());
					if (array_key_exists('attributes', $field_details)) {
						foreach ($field_details['attributes'] as $attribute => $value) {
							$this->assertEquals($value, $field->getAttribute($attribute));
						}
					}
					break;

				case 'multiselect':
					$default_value = [];
					$this->assertEquals($default_value, array_values($field->getValue()));
					$this->assertEquals('type here to search', $field->query('xpath:.//input')->one()->getAttribute('placeholder'));
					break;

				case 'dropdown':
					$this->assertEquals($default_value, $field->getValue());
					$this->assertEquals($field_details['possible_values'], $field->getOptions()->asText());
					break;

				case 'radio_button':
					$this->assertEquals($default_value, $field->getValue());
					$this->assertEquals($field_details['possible_values'], $field->getLabels()->asText());
					break;
			}
		}
		$this->assertTrue($widget_dialog->getField('Show header')->getValue());

		// Close editing dashboard so that next test case would not fail with "Unexpected alert" error.
		$this->closeDialogue();
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
						'Name' => 'Dashboard without widgets'
					],
					'error_message' => 'Dashboard "Dashboard without widgets" already exists.',
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
		$old_values = $form->getFields()->asValues();
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
		$old_values = $form->getFields()->asValues();
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

		// Close the opened alert so that the next running scenario would not fail.
		$this->page->acceptAlert();
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
						'Type' => 'Clock',
						'Name' => 'Widget 4 duplicate check'
					],
					'duplicate widget' => true
				]
			],
			// Clock widget with no name
			[
				[
					'fields' => [
						'Type' => 'Clock',
						'Name' => ''
					]
				]
			],
			// Change time type to Server time
			[
				[
					'fields' => [
						'Type' => 'Clock',
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
						'Type' => 'Clock',
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
						'Type' => 'Clock',
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
						'Type' => 'Clock',
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
						'Type' => 'Graph (classic)',
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
						'Type' => 'Graph (classic)',
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
						'Type' => 'Graph (classic)',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Graph prototype',
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
						'Type' => 'Plain text',
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
						'Type' => 'Plain text',
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
						'Type' => 'Plain text',
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
						'Type' => 'Plain text',
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
						'Type' => 'Plain text',
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
						'Type' => 'URL',
						'Name' => 'URL widget with text URL',
						'URL' => 'home_sweet_home'
					]
				]
			],
			// URL widget with trailing and leading spaces in URL
			[
				[
					'fields' => [
						'Type' => 'URL',
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
						'Type' => 'URL',
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
		$old_values = $form->getFields()->asValues();
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
		$form->fill($data['fields']);
		$this->page->removeFocus();
		COverlayDialogElement::find()->waitUntilReady();
		$old_values = $form->getFields()->asValues();
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

		$dialog = COverlayDialogElement::find()->one()->waitUntilVisible();
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
			$this->assertEquals(['Clock', 'Graph (classic)', 'Graph prototype', 'Item value', 'Plain text', 'URL'],
					$form->getField('Type')->getOptions()->asText()
			);
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
//		$this->setNetworkThrottlingMode(self::NETWORK_THROTTLING_SLOW);
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			if (CTestArrayHelper::get($data, 'check_save')) {
				$this->query('button:Save changes')->one()->click();
			}
			else {
				if (array_key_exists('trim', $data)) {
					$old_values[$data['trim']] = trim($old_values[$data['trim']]);
				}
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
				$this->assertEquals($old_values, $form->getFields()->asValues());
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
				CDashboardElement::find()->asDashboard()->one()->getWidget($name)->waitUntilVisible();
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
					: (($check === 'widget create') ? 'Dashboard without widgets' : 'Dashboard for widget update');
			$this->query('link', $dashboard_name)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();

			if ($check !== 'dashboard action') {
				$reopened_form = CDashboardElement::find()->asDashboard()->one()->waitUntilVisible()->getWidget($name)->edit();
			}
			else {
				$this->query('id:dashboard-config')->one()->click();
				$reopened_form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}

			$this->assertEquals($created_values, $reopened_form->getFields()->asValues());

			$this->closeDialogue();
		}
	}
}
