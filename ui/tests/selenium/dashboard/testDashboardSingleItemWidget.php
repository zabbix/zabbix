<?php

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 */
class testDashboardSingleItemWidget extends CWebTest {

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

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Single Item Widget test',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'item',
								'name' => 'New item view',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid',
										'value' => 29177
									],
									[
										"type" => "0",
										"name" => "adv_conf",
										"value" => "1"
									],
									[
										'type' => 1,
										'name' => 'description',
										'value' => 'Some description here. Описание.'
									],
									[
										'type' => 0,
										'name' => 'desc_h_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'desc_v_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'value_h_pos',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'value_v_pos',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'time_h_pos',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'time_v_pos',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'desc_size',
										'value' => 17
									],
									[
										'type' => 0,
										'name' => 'decimal_size',
										'value' => 41
									],
									[
										'type' => 0,
										'name' => 'value_size',
										'value' => 56
									],
									[
										'type' => 0,
										'name' => 'time_size',
										'value' => 14
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function getWidgetData() {
		return [
			[
				[
					'name' => 'New item view' // 0
				]
			],
//			[
//				[
//					'name' => 'Clock widget', // 1
//					'check_type' => 'id',
//					'tag' => 'div'
//				]
//			]
		];
	}

	/**
	 * Test to check Single Item Widget.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardSingleItemWidget_check($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$widget = $dashboard->getWidget($data['name']);
	}

	public static function getCreateWidgetData() {
		return [
			[
				[ // Min fields. 0
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item',
						'Item' => [
							'values' => 'Available memory in %',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						]
					]
				]
			],
			[
				[ // 1
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item',
						'Name' => 'Имя виджета',
						'Refresh interval' => '10 seconds',
						'Item' => [
							'values' => 'Master item',
							'context' => [
								'values' => 'Test item host',
								'context' => 'Zabbix servers'
							]
						],
						'id:show_2' => false,
						'id:show_4' => false,
						'Advanced configuration' => true,
						'id:description' => 'Несколько слов. Dāži vārdi.',
						'id:desc_h_pos' => 'Right',
						'id:desc_v_pos' => 'Bottom',
						'id:desc_size' => '1',
						'id:time_h_pos' => 'Right',
						'id:time_v_pos' => 'Middle',
						'id:time_size' => '21',
					]
				]
			],
			[
				[ // 2
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item',
						'id:show_header' => false,
						'Name' => '#$%^&*()!@{}[]<>,.?/\|',
						'Refresh interval' => '10 minutes',
						'Item' => [
							'values' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
							'context' => [
								'values' => 'Simple form test host',
								'context' => 'Zabbix servers'
							]
						],
						'id:show_1' => false,
						'id:show_3' => false,
						'Advanced configuration' => true,
						'id:units' => 'Some Units',
						'id:units_pos' => 'Below value',
						'id:units_size' => '100',
						'id:units_bold' => true
					]
				]
			],
			[
				[ // All fields, default colors. 3
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item',
						'Name' => 'New Single Item Widget',
						'Refresh interval' => '2 minutes',
						'Item' => [
							'values' => 'Http agent item for delete',
							'context' => [
								'values' => 'Host for different items types',
								'context' => 'Zabbix servers'
							]
						],
//						'Show' => [
//							'Description' => true,
//							'Time' => true,
//							'Value' => true,
//							'Change indicator' => true
//						],
						'Advanced configuration' => true,
						'id:description' => 'Some description here.',
						'id:desc_h_pos' => 'Left',
						'id:desc_v_pos' => 'Top',
						'id:desc_size' => '11',
						'id:desc_bold' => true,
						'id:decimal_places' => '3',
						'id:value_h_pos' => 'Right',
						'id:value_v_pos' => 'Bottom',
						'id:decimal_size' => '32',
						'id:value_size' => '46',
						'id:value_bold' => true,
						'id:units' => 's',
						'id:units_pos' => 'Before value',
						'id:units_size' => '36',
						'id:units_bold' => true,
						'id:time_h_pos' => 'Left',
						'id:time_v_pos' => 'Bottom',
						'id:time_size' => '13',
						'id:time_bold' => true,
						'Dynamic item' => true
					]
				]
			],
			[
				[ // 4
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item',
					],
					'error' => ['Invalid parameter "Item": cannot be empty.']
				]
			],
			[
				[ // 5
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item',
						'Item' => [
							'values' => 'Available memory in %',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						],
						'Advanced configuration' => true,
						'id:desc_size' => '0',
						'id:decimal_size' => '0',
						'id:value_size' => '0',
						'id:units_size' => '0',
						'id:time_size' => '0',
					],
						'error' => [
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.'
						]
				]
			],
			[
				[ // 6
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item',
						'Item' => [
							'values' => 'Available memory in %',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						],
						'Advanced configuration' => true,
						'id:desc_size' => '101',
						'id:decimal_size' => '102',
						'id:value_size' => '103',
						'id:units_size' => '104',
						'id:time_size' => '105',
					],
						'error' => [
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.',
							'Invalid parameter "Size": value must be one of 1-100.'
						]
				]
			],
			[
				[ // 7
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item',
						'Item' => [
							'values' => 'Available memory in %',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						],
						'Advanced configuration' => true,
						'id:decimal_places' => '-1',
					],
						'error' => [
							'Invalid parameter "Decimal places": value must be one of 0-10.'
						]
				]
			],
			[
				[ // 8
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item',
						'Item' => [
							'values' => 'Available memory in %',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						],
						'Advanced configuration' => true,
						'id:decimal_places' => '99',
					],
						'error' => [
							'Invalid parameter "Decimal places": value must be one of 0-10.'
						]
				]
			],
		];
	}

	/**
	 * @dataProvider getCreateWidgetData
	 */
	public function testDashboardSingleItemWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Add a widget.
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$form->submit();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				// Make sure that the widget is present before saving the dashboard.
				$header = CTestArrayHelper::get($data['fields'], 'Name', 'Item');
				$dashboard->getWidget($header);
				$dashboard->save();

				// Check that Dashboard has been saved and that widget has been added.
				$this->checkDashboardUpdateMessage();
				$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());

				// Check that widget has been added.
				$this->checkRefreshInterval($data, $header);
				break;
		case TEST_BAD:
				$this->assertMessage($data['expected'], null, $data['error']);
				break;
		}
	}

	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	private function checkRefreshInterval($data, $header) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
			? '15 minutes'
			: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
		$this->assertEquals($refresh, $widget->getRefreshInterval());
	}
}
