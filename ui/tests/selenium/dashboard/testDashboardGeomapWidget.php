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

/**
 * @backup config, widget
 *
 * @onBefore prepareDashboardData
 */
class testDashboardGeomapWidget extends CWebTest {

	/**
	 * Id of the dashboard where geomap widget is created and updated.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	private static $update_geomap = 'Geomap for updating';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
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
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Geomap widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600,
					'widgets' => [
						[
							'type' => 'geomap',
							'name' => 'Geomap for updating',
							'x' => 0,
							'y' => 0,
							'width' => 11,
							'height' => 5,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => '4'
								],
								[
									'type' => '3',
									'name' => 'hostids',
									'value' => '15001'
								],
								[
									'type' => '3',
									'name' => 'hostids',
									'value' => '99136'
								],
								[
									'type' => '3',
									'name' => 'hostids',
									'value' => '15003'
								],
								[
									'type' => '1',
									'name' => 'tags.tag.0',
									'value' => 'tag1'
								],
								[
									'type' => '0',
									'name' => 'tags.operator.0',
									'value' => '0'
								],
								[
									'type' => '1',
									'name' => 'tags.value.0',
									'value' => 'value1'
								],
								[
									'type' => '1',
									'name' => 'default_view',
									'value' => '51.5537236445998, -0.43871069125537776'
								]
							]
						],
						[
							'type' => 'geomap',
							'name' => 'Geomap for delete',
							'x' => 11,
							'y' => 0,
							'width' => 10,
							'height' => 5,
							'view_mode' => 0
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardGeomapWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => 'Geomap']);
		$dialog->waitUntilReady();
		$this->assertEquals(["Type", "Name", "Refresh interval", "Host groups", "Hosts", "Tags", "", "Initial view"],
				$form->getLabels()->asText()
		);
		$form->checkValue(['id:show_header' => true, 'Refresh interval' => 'Default (1 minute)']);

		// Check fields' lengths and placeholders.
		foreach (['Name', 'Initial view'] as $field) {
			$this->assertEquals(255, $form->getField($field)->getAttribute('maxlength'));
		}

		foreach (['Name' => 'default', 'Initial view' => '40.6892494,-74.0466891'] as $field => $placeholder) {
			$this->assertEquals($placeholder, $form->getField($field)->getAttribute('placeholder'));
		}

		// Check tags table initial values.
		$form->checkValue(['id:evaltype' => 'And/Or']);
		$form->query('id:tags_table_tags')->asMultifieldTable()->one()
				->checkValue([['tag' => '', 'operator' => 'Contains', 'value' => '']]);

		// Check operator's dropdown options presence.
		$this->assertEquals(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'], $form->getField('id:tags_0_operator')->asDropdown()->getOptions()->asText()
		);

		$hint_text = "Comma separated center coordinates and zoom level to display when the widget is initially loaded.".
				"\nSupported formats:".
				"\n<lat>,<lng>,<zoom>".
				"\n<lat>,<lng>".
				"\n".
				"\nThe maximum zoom level is \"0\".".
				"\nInitial view is ignored if the default view is set.";

		$form->query('xpath:.//label[text()="Initial view"]/a')->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilPresent();
		$this->assertEquals($hint_text, $hint->one()->getText());
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();
	}

	public static function getWidgetCreateData() {
		return [
			[
				[
					'fields' => [
						'Type' => 'Geomap'
					]
				]
			]
		];
	}

	public static function getWidgetCommonData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Initial view' => '1'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Zoom more than 30 in coordinates',
						'Initial view' => '56.95008,24.11509,31'
					],
					'error' => 'Invalid zoomparameter "Initial view": zoom level must be between "0" and "30".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in coordinates',
						'Initial view' => 'test'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Space before zoom in coordinates',
						'Initial view' => '56.95008,24.11509, 25'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Space before zoom in long coordinates',
						'Initial view' => '51.5537236445998, -0.43871069125537776, 25'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative zoom in coordinates',
						'Initial view' => '56.95008,24.11509,-25'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative number in coordinates',
						'Initial view' => '-5'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of '.
							'comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'show_header' => false,
					'fields' => [
						'Name' => 'Short coordinates',
						'Initial view' => '56.95008,24.11509'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Short negative coordinates',
						'Initial view' => '-56.95008,-24.11509'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Short coordinates with zoom',
						'Initial view' => '56.95008, 24.11509,25'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Short coordinates with zoom 0',
						'Initial view' => '56.95008, 24.11509,0'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'With long coordinates and zoom',
						'Initial view' => '51.5537236445998, -0.43871069125537776,5'
					]
				]
			],
			[
				[
					'show_header' => true,
					'fields' => [
						'Name' => 'New geomap widget with tags and long coordinates',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => ['Test item host', 'ЗАББИКС Сервер'],
						'Initial view' => '51.5537236445998, -0.43871069125537776'
					],
					'Tags' => [
						'evaluation' => 'Or',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => '!@#$%^&*()_+<>,.\/',
								'operator' => 'Equals',
								'value' => '!@#$%^&*()_+<>,.\/'
							],
							[
								'tag' => 'tag1',
								'operator' => 'Contains',
								'value' => 'value1'
							],
							[
								'tag' => 'tag2',
								'operator' => 'Exists'
							],
							[
								'tag' => 'tag3',
								'operator' => 'Does not exist'
							],
							[
								'tag' => '{$MACRO:A}',
								'operator' => 'Does not equal',
								'value' => '{$MACRO:A}'
							],
							[
								'tag' => '{$MACRO}',
								'operator' => 'Does not contain',
								'value' => '{$MACRO}'
							],
							[
								'tag' => 'Таг',
								'value' => 'Значение'
							]
						]
					]
				]
			]
		];
	}

	public static function getWidgetUpdateData() {
		return [
			[
				[
					'fields' => [
						'Name' => '',
						'Host groups' => '',
						'Hosts' => '',
						'Initial view' => ''
					],
					'Tags' => []
				]
			]
		];
	}

	/**
	 * @backupOnce widget
	 *
	 * @dataProvider getWidgetCreateData
	 * @dataProvider getWidgetCommonData
	 */
	public function testDashboardGeomapWidget_Create($data) {
		$this->checkFormGeomapWidget($data);
	}

	/**
	 * @dataProvider getWidgetCommonData
	 * @dataProvider getWidgetUpdateData
	 */
	public function testDashboardGeomapWidget_Update($data) {
		$this->checkFormGeomapWidget($data, true);
	}

	/**
	 * Function for checking Geomap widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	public function checkFormGeomapWidget($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_geomap)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->fill(['Type' => 'Geomap']);

		// After changing "Source", the overlay is reloaded.
		$form->invalidate();
		$form->fill($data['fields']);

		if (array_key_exists('show_header', $data)) {
			$form->getField('id:show_header')->fill($data['show_header']);
		}

		if (array_key_exists('Tags', $data)) {
			$tags_table = $form->getField('id:tags_table_tags')->asMultifieldTable();

			if (empty($data['Tags'])) {
				$tags_table->clear();
			}
			else {
				$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
				$form->getField('id:tags_table_tags')->asMultifieldTable()->fill(CTestArrayHelper::get($data['Tags'], 'tags'));
			}
		}

		$values = $form->getFields()->asValues();
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
		else {
			COverlayDialogElement::ensureNotPresent();

			/**
			 *  When name is absent in create scenario it remains default: "Geomap",
			 *  if name is absent in update scenario then previous name remains.
			 *  If name is empty string in both scenarios it is replaced by "Geomap".
			 */
			if (array_key_exists('Name', $data['fields'])) {
				$header = ($data['fields']['Name'] === '')
					? 'Geomap'
					: $data['fields']['Name'];
			}
			else {
				$header = $update ? self::$update_geomap : 'Geomap';
			}

			$dashboard->getWidget($header)->waitUntilReady();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());
			$saved_form = $dashboard->getWidget($header)->edit();

			// If tags table has been cleared, after form saving there is one empty tag field.
			if (CTestArrayHelper::get($data, 'Tags') === []) {
				$values[''] = [['tag' => '', 'operator' => 'Contains', 'value' => '']];
			}

			// Check widget form fields and values in frontend.
			$this->assertEquals($values, $saved_form->getFields()->asValues());

			if (array_key_exists('show_header', $data)) {
				$saved_form->checkValue(['id:show_header' => $data['show_header']]);
			}

			// Check that widget is saved in DB.
			$this->assertEquals(1,
					CDBHelper::getCount('SELECT * FROM widget w'.
						' WHERE EXISTS ('.
							'SELECT NULL'.
							' FROM dashboard_page dp'.
							' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
								' AND dp.dashboardid='.self::$dashboardid.
								' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).')'
			));

			// Write new name to updated widget name.
			if ($update) {
				self::$update_geomap = $header;
			}
		}
	}

	public function testDashboardGeomapWidget_SimpleUpdate() {
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
	public function testDashboardGeomapWidget_Cancel($data) {
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
			: $dashboard->getWidget(self::$update_geomap)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}
		else {
			$form->fill(['Type' => 'Geomap']);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill(
					[
						'Name' => 'new name',
						'Refresh interval' => '10 minutes',
						'Host groups' => 'Group for Host availability widget',
						'Hosts' => 'Available host',
						'Initial view' => '56.95090, 24.115,7'
					]
			);
			$form->getField('id:evaltype')->fill('Or');
			$form->getField('id:tags_table_tags')->asMultifieldTable()->fill([
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'tag' => 'new tag',
						'operator' => 'Does not equal',
						'value' => 'new value'
					]
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
			$dashboard->getWidget(!$save_dashboard ? 'new name' : self::$update_geomap)->waitUntilReady();
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
			$this->assertEquals($values, $dashboard->getWidget(self::$update_geomap)->edit()->getFields()->asValues());
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardGeomapWidget_Delete() {
		$name = 'Geomap for delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->edit()->getWidget($name)->isEditable());
		$dashboard->deleteWidget($name);
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
					' ON w.widgetid=wf.widgetid'.
					' WHERE w.name='.zbx_dbstr($name)
		));
	}
}
