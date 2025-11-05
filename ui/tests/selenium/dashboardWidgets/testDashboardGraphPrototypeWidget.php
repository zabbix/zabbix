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
require_once __DIR__.'/../behaviors/CTableBehavior.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../common/testWidgets.php';

/**
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareData
 *
 * @backup widget, profiles
 */
class testDashboardGraphPrototypeWidget extends testWidgets {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	protected static $previous_widget_name = 'Graph prototype widget for update';
	protected static $dashboardid;

	public static function prepareData() {
		$hostgroupid = CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Graph Prototype widget']
		])['groupids'][0];

		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for Graph Prototype widget',
				'groups' => [
					['groupid' => $hostgroupid]
				],
				'items' => [
					[
						'name' => 'Item for Graph Prototype widget',
						'key_' => 'graph[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		])['hostids'];
		$itemids = CDataHelper::getIds('name');

		$discoveryruleid = CDataHelper::call('discoveryrule.create', [
			[
				'hostid' => $hosts['Host for Graph Prototype widget'],
				'name' => 'Discovery rule for Graph Prototype widget',
				'key_' => 'discovery.prototype.widget[1]',
				'type' => ITEM_TYPE_TRAPPER
			]
		])['itemids'][0];

		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Item prototype for Graph Prototype widget',
				'key_' => 'grpah.prototype[{#ID}]',
				'hostid' => $hosts['Host for Graph Prototype widget'],
				'ruleid' => $discoveryruleid,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		]);
		$prototype_itemids = CDataHelper::getIds('name');

		CDataHelper::call('graphprototype.create', [
			[
				'name' => 'First Graph prototype for widget',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids[ 'Item for Graph Prototype widget'],
						'color' => '00FFFF'
					],
					[
						'itemid' => $prototype_itemids['Item prototype for Graph Prototype widget'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Second Graph prototype for widget',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids[ 'Item for Graph Prototype widget'],
						'color' => 'E6EE9C'
					],
					[
						'itemid' => $prototype_itemids['Item prototype for Graph Prototype widget'],
						'color' => 'F9A825'
					]
				]
			]
		]);
		$graph_prototypeids = CDataHelper::getIds('name');

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Graph Prototype widget',
				'userid' => '1',
				'private' => PRIVATE_SHARING,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'graphprototype',
								'name' => 'Graph prototype widget for update',
								'x' => 0,
								'y' => 0,
								'width' => 48,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid.0',
										'value' => $graph_prototypeids['First Graph prototype for widget']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'RZALA'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'name' => 'Graph prototype widget for delete',
								'x' => 0,
								'y' => 5,
								'width' => 48,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid.0',
										'value' => $graph_prototypeids['Second Graph prototype for widget']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'MJHNB'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Sceenshoting Graph Prototype widgets',
				'userid' => '1',
				'private' => PRIVATE_SHARING,
				'pages' => [[]]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Graph prototype' => [
							'values' => 'First Graph prototype for widget',
							'context' => [
								'values' => 'Host for Graph Prototype widget',
								'context' => 'Group for Graph Prototype widget'
							]
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host for Graph Prototype widget '.microtime(),
						'Source' => 'Simple graph prototype',
						'Item prototype' => [
							'values' => 'Item prototype for Graph Prototype widget',
							'context' => [
								'values' => 'Host for Graph Prototype widget',
								'context' => 'Group for Graph Prototype widget'
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
						'Name' => 'Graph prototype widget with all possible fields filled '.microtime(),
						'Refresh interval' => 'No refresh',
						'Source' => 'Simple graph prototype',
						'Item prototype' => 'Item prototype for Graph Prototype widget',
						'Show legend' => true,
						'Override host' => 'Dashboard',
						'Columns' => '3',
						'Rows' => '2'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Source' => 'Graph prototype'
					],
					'error' => ['Invalid parameter "Graph prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Source' => 'Simple graph prototype'
					],
					'error' => ['Invalid parameter "Item prototype": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Source' => 'Graph prototype',
						'Graph prototype' => 'Second Graph prototype for widget',
						'Columns' => '0',
						'Rows' => '0'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-72.',
						'Invalid parameter "Rows": value must be one of 1-64.'
					]
				]
			],
						[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Source' => 'Graph prototype',
						'Graph prototype' => 'First Graph prototype for widget',
						'Columns' => '73',
						'Rows' => '65'
					],
					'error' => [
						'Invalid parameter "Columns": value must be one of 1-72.',
						'Invalid parameter "Rows": value must be one of 1-64.'
					]
				]
			]
		];
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

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Graph Prototype widget']
		);
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Sceenshoting Graph Prototype widgets']
		);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Graph prototype')]);
		$this->page->removeFocus();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertScreenshot($dialog);
		$dialog->close();
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
						'Rows' => '2'
					],
					'screenshot_id' => '48x2'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '16',
						'Rows' => '3'
					],
					'screenshot_id' => 'stub49x3'
				]
			],
			[
				[
					'fields' => [
						'Columns' => '17',
						'Rows' => '2'
					],
					'screenshot_id' => 'stub72x2'
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Sceenshoting Graph Prototype widgets']
		);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$widget = [
			'Name' => 'Screenshot Widget',
			'Graph prototype' => 'Second Graph prototype for widget'
		];
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Graph prototype')]);
		$form->fill($widget);

		if (array_key_exists('fields', $data)){
			$form->fill($data['fields']);
		}

		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->waitUntilReady()->getWidget($widget['Name']);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$dashboard->waitUntilReady();

		// Remove mouse from widget to hide widget name.
		$this->query('tag:h1')->one()->hoverMouse();
		$screenshot_area = $this->query('class:dashboard-grid')->one();
		$screenshot_area->query('xpath:.//div[contains(@class, "dashboard-grid-iterator-focus")]')->waitUntilNotVisible();
		$this->assertScreenshot($screenshot_area, $data['screenshot_id']);
	}

	protected function checkGraphPrototypeWidget($data, $update = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Graph Prototype widget']
		);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$previous_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();
		COverlayDialogElement::find()->one()->waitUntilReady();

		if (array_key_exists('show_header', $data)) {
			$form->query('xpath:.//input[@id="show_header"]')->asCheckbox()->one()->fill($data['show_header']);
		}

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Graph prototype')]);
		$form->fill($data['fields']);

		// After changing "Source", the overlay is reloaded.
		COverlayDialogElement::find()->one()->waitUntilReady();

		$type = array_key_exists('Item prototype', $data['fields']) ? 'Item prototype' : 'Graph prototype';

		if (!array_key_exists('Graph prototype', $data['fields']) && !array_key_exists('Item prototype', $data['fields'])) {
			$form->query('xpath:.//div[@id="graphid" or @id="itemid"]')->all()->filter(CElementFilter::VISIBLE)
					->asMultiselect()->clear();
		}

		$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			COverlayDialogElement::find()->one()->close();
		}
		else {
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
			$dashboard->save()->waitUntilReady();

			// Check that Dashboard has been saved and that widget has been added.
			$this->assertMessage($data['expected'], 'Dashboard updated');
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check that widget is saved in DB.
			$db_count = CDBHelper::getCount('SELECT * FROM widget w'.
				' WHERE EXISTS ('.
					'SELECT NULL'.
					' FROM dashboard_page dp'.
					' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
						' AND dp.dashboardid='.self::$dashboardid['Dashboard for Graph Prototype widget'].
						' AND w.name ='.zbx_dbstr($db_name).
				')'
			);

			$this->assertEquals(1, $db_count);

			// Verify widget content
			$widget = $dashboard->getWidget($data['fields']['Name']);
			$this->assertTrue($widget->getContent()->isValid());

			// Compare placeholders count in data and created widget. Default placeholder count is 2.
			$expected_count = (array_key_exists('Columns', $data['fields']) && array_key_exists('Rows', $data['fields']))
				? $data['fields']['Columns'] * $data['fields']['Rows']
				: 2;

			$placeholders_count = $widget->query('class:dashboard-grid-iterator-placeholder')->count();
			$this->assertEquals($expected_count, $placeholders_count);

			// Check Dynamic item setting on Dashboard.
			if (CTestArrayHelper::get($data['fields'], 'Override host')) {
				$this->assertTrue($dashboard->getControls()->query('xpath://form[@aria-label = '.
					'"Main filter"]')->one()->isPresent());
			}

			// Check widget form fields and values.
			$this->assertEquals($values, $widget->edit()->getFields()->filter(CElementFilter::VISIBLE)->asValues());

			// Write widget name to variable to use it in next Update test case.
			if ($update) {
				self::$previous_widget_name = CTestArrayHelper::get($data, 'fields.Name', 'Graph prototype widget for update');
			}

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();
		}
	}

	/**
	 * Function for checking editing widget form without changes.
	 *
	 * @param string $action	name of button tested
	 * @param boolean $update	is this updating of existing widget
	 * @param boolean $changes	are there any changes made in widget form
	 */
	protected function checkDataUnchanged($action, $update = false, $changes = false) {
		$initial_values = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Graph Prototype widget']
		);
		$dashboard = CDashboardElement::find()->one();

		$form = $update
			? $dashboard->getWidget(self::$previous_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		if ($update) {
			$original_values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		$dialog = COverlayDialogElement::find()->one();

		if ($changes) {
			$form->fill([
					'Type' => 'Graph prototype',
					'Name' => 'Name for Cancelling',
					'Refresh interval' => 'No refresh',
					'Source' => 'Simple graph prototype',
					'Item prototype' => 'Item prototype for Graph Prototype widget',
					'Show legend' => false,
					'Override host' => 'Dashboard',
					'Columns' => '3',
					'Rows' => '2'
				]);
		}

		$dialog->query('button', $action)->one()->click();
		$this->page->waitUntilReady();

		if ($update) {
			$dashboard->getWidget(self::$previous_widget_name);
		}

		$dashboard->save()->waitUntilReady();

		// Check that Dashboard has been saved and that there are no changes made to the widgets.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		if ($update) {
			$new_values = $dashboard->getWidget(self::$previous_widget_name)->edit()->getFields()
					->filter(CElementFilter::VISIBLE)->asValues();
			$this->assertEquals($original_values, $new_values);
			COverlayDialogElement::find()->one()->close();
		}

		$this->assertEquals($initial_values, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Test function for assuring that binary items are not available in Graph prototype widget.
	 */
	public function testDashboardGraphPrototypeWidget_CheckAvailableItems() {
		$url = 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid['Dashboard for Graph Prototype widget'];
		$this->checkAvailableItems($url, 'Graph prototype');
	}
}
