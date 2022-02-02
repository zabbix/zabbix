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
 * @onBefore prepareDasboardData
 */
class testFormGeomapWidget extends CWebTest {

	/**
	 * Id of the dashboard where geomap widget is created and updated.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

//	private static $previous_widget_name = 'Graph prototype widget for update';

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

	public function prepareDasboardData() {
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Geomap widget dashboard',
			'pages' => [
				[
					'name' => 'First page',
					'display_period' => 1800,
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
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testFormGeomapWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();

		$this->assertEquals('Add widget', COverlayDialogElement::find()->waitUntilReady()->one()->getTitle());
		$form->fill(['Type' => 'Geomap']);
		$form->waitUntilReady();
		$form->checkValue(['id:show_header' => true]);

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
				->checkValue([['tag' => '', 'operator' => 'Contains', 'value' => '']]
		);
		// Check operator's dropdown options presence.
		$this->assertEquals(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'], $form->getField('id:tags_0_operator')->asZDropdown()->getOptions()->asText()
		);

		$hint_text = "Comma separated center coordinates and zoom level to display when the widget is initially loaded.".
				"\nSupported formats:".
				"\n<lat>,<lng>,<zoom>".
				"\n<lat>,<lng>".
				"\n".
				"\nThe maximum zoom level is \"0\".".
				"\nInitial view is ignored if the default view is set.";

		$form->query('xpath://label[text()="Initial view"]//span')->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilPresent();
		$this->assertEquals($hint_text, $hint->one()->getText());
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Geomap',
						'Initial view' => '1'
					],
					'error' => 'Invalid parameter "Initial view": geographical coordinates (values of'.
							' comma separated latitude and longitude) are expected.'
				]
			],
			[
				[
					'fields' => [
						'Type' => 'Geomap'
					]
				]
			],
			[
				[
					'fields' => [
						'Type' => 'Geomap',
						'Name' => 'New geomap widget',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => ['ЗАББИКС Сервер', 'Test item host'],
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
								'operator' => 'Exists',
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
			],
//			// update
//			[
//				[
//					'fields' => [
//						'Type' => 'Geomap',
//						'Name' => '',
//						'Host groups' => '',
//						'Hosts' => '',
//						'Tags' => [],
//						'Initial view' => ''
//					]
//				]
//			],

		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testFormGeomapWidget_Create($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Add a widget.
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);

		if (array_key_exists('Tags', $data)) {
			$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
			$form->getField('id:tags_table_tags')->asMultifieldTable()->fill(CTestArrayHelper::get($data['Tags'], 'tags', []));
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
			$header = CTestArrayHelper::get($data['fields'], 'Name', 'Geomap');
			$widget = $dashboard->getWidget($header);
			$widget->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());

			// Check widget form fields and values in frontend.
			$this->assertEquals($values, $dashboard->getWidget($header)->edit()->getFields()->asValues());

			// Check that widget is saved in DB.
			$this->assertEquals(1,
					CDBHelper::getCount('SELECT * FROM widget w'.
						' WHERE EXISTS ('.
							'SELECT NULL'.
							' FROM dashboard_page dp'.
							' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
								' AND dp.dashboardid='.self::$dashboardid.
								' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).
						')'
					)
			);
		}
	}
}
