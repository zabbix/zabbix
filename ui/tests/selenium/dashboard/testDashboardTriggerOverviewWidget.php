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
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup widget, profiles, triggers, problem
 *
 * @onBefore prepareDashboardData
 */
class testDashboardTriggerOverviewWidget extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	private static $dashboardid;
	private static $create_page = 'Page for creation';
	private static $update_widget = 'Trigger overview for reference';
	private static $delete_widget = 'Trigger overview for delete';
	private static $resolved_trigger = '1_trigger_Average';

	private static $background_classes = [
		'1_trigger_Average' => 'normal-bg cursor-pointer blink',
		'1_trigger_Disaster' => 'disaster-bg',
		'1_trigger_High' => 'high-bg',
		'1_trigger_Not_classified' => 'na-bg',
		'1_trigger_Warning' => 'warning-bg',
		'2_trigger_Information' => 'info-bg',
		'3_trigger_Average' => 'average-bg',
		'Trigger_for_suppression' => 'average-bg',
		'4_trigger_Average' => 'average-bg',
		'Inheritance trigger with tags' => 'average-bg',
		'3_trigger_Disaster' => 'normal-bg'
	];

	private static $trigger_icons = [
		'2_trigger_Information' => 'icon-ackn',
		'3_trigger_Average' => 'icon-ackn',
		'4_trigger_Average' => 'icon-ackn',
		'Inheritance trigger with tags' => 'icon-depend-down'
	];

	/**
	 * Function creates dashboards with widgets for test and defines the corresponding dashboard IDs.
	 */
	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Trigger overview widgets',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'trigover',
								'name' => self::$update_widget,
								'width' => 24,
								'height' => 4
							],
							[
								'type' => 'trigover',
								'name' => self::$delete_widget,
								'x' => 0,
								'y' => 4,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									]
								]
							]
						]
					],
					[
						'name' => self::$create_page,
						'widgets' => []
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];
		$timestamp = time();

		// Resolve one of existing problems to create a recent problem.
		$triggerid = CDBHelper::getValue('SELECT triggerid FROM triggers WHERE description='.zbx_dbstr(self::$resolved_trigger));

		DBexecute('UPDATE triggers SET value=0 WHERE triggerid='.$triggerid);
		DBexecute('UPDATE triggers SET lastchange='.$timestamp.' WHERE triggerid='.$triggerid);
		DBexecute('UPDATE problem SET r_eventid=9001 WHERE objectid='.$triggerid);
		DBexecute('UPDATE problem SET r_clock='.$timestamp.' WHERE objectid='.$triggerid);
	}

	public function testDashboardTriggerOverviewWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form->fill(['Type' => 'Trigger overview']);
		$dialog->waitUntilReady();

		$this->assertEquals(['Type', 'Name', 'Refresh interval', 'Show', 'Host groups', 'Hosts', 'Tags', '',
				'Show suppressed problems', 'Hosts location'], $form->getLabels()->asText()
		);
		$default_values = [
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Show' => 'Recent problems',
			'id:evaltype' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_value' => '',
			'id:tags_0_operator' => 'Contains',
			'Show suppressed problems' => false,
			'Hosts location' => 'Left'
		];
		$form->checkValue($default_values);

		// Check fields' lengths and placeholders.
		foreach (['Name' => 'default', 'id:tags_0_tag' => 'tag', 'id:tags_0_value' => 'value'] as $field => $placeholder) {
			$field = $form->getField($field);
			$this->assertEquals(255, $field->getAttribute('maxlength'));
			$this->assertEquals($placeholder, $field->getAttribute('placeholder'));
		}

		// Check operator's dropdown options presence.
		$this->assertEquals(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'], $form->getField('id:tags_0_operator')->asDropdown()->getOptions()->asText()
		);

		// Check possible values of radio buttons.
		$radio_buttons = [
			'Show' => ['Recent problems', 'Problems', 'Any'],
			'Tags' => ['And/Or', 'Or'],
			'Hosts location' => ['Left', 'Top']
		];

		foreach ($radio_buttons as $radio_button => $values) {
			$radio_element = $form->getField($radio_button);
			$this->assertEquals($values, $radio_element->getLabels()->asText());
		}


		$tags_table = $form->query('id:tags_table_tags')->one();
		$this->assertEquals(2, $tags_table->query('button', ['Add', 'Remove'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
	}

	public function getCreateWidgetData() {
		return [
			// Create a widget with default values including default name.
			[
				[
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Average',
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information',
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			// Create a widget that displays only problems.
			[
				[
					'fields' => [
						'Name' => 'Show problems',
						'Show' => 'Problems'
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information',
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show all triggers for specific host',
						'Show' => 'Any',
						'Hosts' => ['3_Host_to_check_Monitoring_Overview']
					],
					'expected' => [
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average',
							'3_trigger_Disaster'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show problems for specific hostgroup',
						'Show' => 'Problems',
						'Host groups' => ['Group to check Overview']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information',
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateWidgetData
	 */
	public function testDashboardTriggerOverviewWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$dashboard->selectPage(self::$create_page);

		// Add a widget.
		$form = $dashboard->addWidget()->asForm();

		// Set type to Trigger overview in case if this field has a different value.
		if ($form->getField('Type')->getValue() !== 'Trigger overview') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Trigger overview')]);
		}

		// Fill form in case if values that are differen from default should be filled
		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}

		if (CTestArrayHelper::get($data,'tags',false)) {
			$this->setTagSelector('id:tags_table_tags');
			$this->setTags($data['tags']);
		}
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		$widget_name = (array_key_exists('fields', $data)) ? $data['fields']['Name'] : 'Trigger overview';
		$widget = $dashboard->getWidget($widget_name);
		$widget->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$dashboard->save();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$dashboard->selectPage(self::$create_page);
		$table = $widget->getContent()->asTable();

		if (CTestArrayHelper::get($data, 'fields.Host location') === 'Top') {
			$expected_headers = ['Triggers'];
			$expected_rows = [];
		}
		else {
			$expected_headers = ['Hosts'];
			$expected_rows = array_keys($data['expected']);
		}

		foreach ($data['expected'] as $host => $triggers) {
			if (CTestArrayHelper::get($data, 'fields.Host location') === 'Top') {
				$expected_headers[] = $host;
				foreach ($triggers as $trigger) {
					$expected_rows[] = $trigger;
					$cell = $table->findRow('Triggers', $trigger);
					$this->checkTriggerCell($cell, $trigger);

				}
			}
			else {
				$row = $table->findRow('Hosts', $host);

				foreach ($triggers as $trigger) {
					$expected_headers[] = $trigger;
					$cell = $row->getColumn($trigger);

					$this->checkTriggerCell($cell, $trigger);
				}
			}
		}

		$this->assertEquals($expected_headers, $table->getHeadersText());
		$this->assertTableDataColumn($expected_rows, $expected_headers[0], 'xpath://h4[text()='.
				CXPathHelper::escapeQuotes($widget_name).']/../..//table[@class="list-table"]'
		);
	}

	private function checkTriggerCell ($cell, $trigger) {
		// Check the colour of the background.
		$this->assertStringStartsWith(self::$background_classes[$trigger], $cell->getAttribute('class'));

		// Check trigger icon if such should exist.
		if (in_array($trigger, self::$trigger_icons)) {
			$element = (self::$trigger_icons[$trigger] === 'icon-ackn') ? 'span' : 'a';
			$icon = $cell->query('xpath:.//'.$element)->one();
			$this->assertTrue($icon->isValid());
			$this->assertStringStartsWith(self::$trigger_icons[$trigger], $cell->getAttribute('class'));
		}
	}

	// Hintboxes in icons to be checked in a separate scenario.
}
