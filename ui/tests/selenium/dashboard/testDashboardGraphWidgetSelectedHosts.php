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

use Facebook\WebDriver\WebDriverKeys;

/**
 * @backup hosts, dashboard
 *
 * @onBefore prepareSelectedHostdata
 */

class testDashboardGraphWidgetSelectedHosts extends CWebTest {

	/**
	 * Id of the dashboard with widgets.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	public static function prepareSelectedHostdata() {
		$hostgroupid = CDataHelper::call('hostgroup.create',	[['name' => 'Suggestion list group']])['groupids'][0];

		CDataHelper::createHosts([
			[
				'host' => 'Host for widget 1',
				'groups' => [
					'groupid' => $hostgroupid
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for Graph 1_1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 1_2',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 1_3',
						'key_' => 'trap3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 1_4',
						'key_' => 'trap4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 1_5',
						'key_' => 'trap5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for widget 2',
				'groups' => [
					'groupid' => $hostgroupid
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for Graph 2_1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 2_2',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 2_3',
						'key_' => 'trap3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 2_4',
						'key_' => 'trap4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 2_5',
						'key_' => 'trap5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for widget 3',
				'groups' => [
					'groupid' => $hostgroupid
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for Graph 3_1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 3_2',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 3_3',
						'key_' => 'trap3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 3_4',
						'key_' => 'trap4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 3_5',
						'key_' => 'trap5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for widget 4',
				'groups' => [
					'groupid' => $hostgroupid
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for Graph 4_1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 4_2',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 4_3',
						'key_' => 'trap3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 4_4',
						'key_' => 'trap4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 4_5',
						'key_' => 'trap5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for widget 5',
				'groups' => [
					'groupid' => $hostgroupid
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for Graph 5_1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 5_2',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 5_3',
						'key_' => 'trap3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 5_4',
						'key_' => 'trap4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Item for Graph 5_5',
						'key_' => 'trap5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating Graph widgets',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'First page'
					]
				]
			]
		]);
		self::$dashboardid = array_values(CDataHelper::getIds('name'))[0];
	}

	public static function getDatasetData() {
		return [
			[
				[
					'Data set' => [
						'host' => 'Host for widget'
					],
					'expected' => [
						'Host for widget 1',
						'Host for widget 2',
						'Host for widget 3',
						'Host for widget 4',
						'Host for widget 5'
					],
					'Arrow Key' => 'no'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'item' => 'Item for'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_3',
						'Item for Graph 1_4',
						'Item for Graph 1_5'
					],
					'Arrow Key' => 'no'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget*',
						'item' => 'Item'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_3',
						'Item for Graph 1_4',
						'Item for Graph 1_5',
						'Item for Graph 2_1',
						'Item for Graph 2_2',
						'Item for Graph 2_3',
						'Item for Graph 2_4',
						'Item for Graph 2_5',
						'Item for Graph 3_1',
						'Item for Graph 3_2',
						'Item for Graph 3_3',
						'Item for Graph 3_4',
						'Item for Graph 3_5',
						'Item for Graph 4_1',
						'Item for Graph 4_2',
						'Item for Graph 4_3',
						'Item for Graph 4_4'
					],
					'Arrow Key' => 'no'
				]
			],
			[
				[
					'Data set' => [
						'host' => [
							'Host for widget 1',
							'Host for widget 2'
						],
						'item' => 'Item'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_3',
						'Item for Graph 1_4',
						'Item for Graph 1_5',
						'Item for Graph 2_1',
						'Item for Graph 2_2',
						'Item for Graph 2_3',
						'Item for Graph 2_4',
						'Item for Graph 2_5'
					],
					'Arrow Key' => 'yes',
					'Focus' => 'Item for Graph 2_5',
					'Not Present' => 'yes'
				]
			],
			[
				[
					'Data set' => [
						'host' => [
							'Host for widget 1',
							'Host for widget 4'
						],
						'item' => 'Item'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_3',
						'Item for Graph 1_4',
						'Item for Graph 1_5',
						'Item for Graph 4_1',
						'Item for Graph 4_2',
						'Item for Graph 4_3',
						'Item for Graph 4_4',
						'Item for Graph 4_5'
					],
					'Arrow Key' => 'no'
				]
			]
		];
	}

	/**
	 * Function checks using arrow keys if Graph Widget is correctly selecting and displaying hosts, their items
	 * in suggestion list.
	 *
	 * @dataProvider getDatasetData
	 */
	public function testDashboardGraphWidgetSelectedHosts_CheckSuggestionList($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Graph']);

		// Change mapping of associative arrays from data set.
		if (array_key_exists('item', $data['Data set'])) {
			$field_data = [
				'xpath:.//div[@id="ds_0_hosts_"]/..' => $data['Data set']['host'],
				'xpath:.//input[@placeholder="item pattern"]' => $data['Data set']['item']
			];
		}
		else {
			$field_data = [
				'xpath:.//input[@placeholder="host pattern"]' => $data['Data set']['host']
			];
		}

		$form->fill($field_data);
		$this->checkSuggestionListCommon($data, $form);

		if ($data['Arrow Key'] === 'yes') {
			$this->checkSuggestionListWithKeyboardNavigation($data['Data set'], $data, $form);
		}
	}

	private function checkSuggestionListCommon ($data, $form) {
		$this->query('class', 'multiselect-suggest')->waitUntilVisible();
		$this->assertEquals($data['expected'], $form->getField('xpath://div[@id="ds_0_hosts_"]/..')->getSuggestions());
	}

	private function checkSuggestionListWithKeyboardNavigation($data_set, $data, $form) {
		$merged_text = [];
		/**
		 * In case created API data under section "Data set" there's more than two or exactly two array keys,
		 * assign value to the variable $id, which is later used in the query to get text.
		 */
		$id = (count($data_set) >= 2) ? 'items' : 'hosts';
		$get_text = $this->query('xpath://div[@id="ds_0_'.$id.'_"]//div[@aria-live="assertive"]')
				->one()->waitUntilTextPresent('use down,up arrow keys and enter to select')->getText();

		// Count words in text, accept that numbers are words.
		$count = str_word_count($get_text, 1, '1234567890');

		// Check if third key in string is '20', after that assign index.
		$word_index = ($count[2] === '20') ? 2 : 0;

		/**
		 * Since suggestion window contains maximum 20 matches, and the first one is the one which is written,
		 * extract it from the found matches.
		 */
		$found_matches = intval($count[$word_index]) - 1;

		if ($found_matches >= 20) {
			$this->fail('Reduce the amount of test data or suggestion window is broken and displays more data than it should.');
		}
		else {
			/**
			 * The cycle uses count of found matches, in each iteration on frontend arrow key down is pressed
			 * in order to read the text from suggestion window, each text read is pushed into array, which is compared
			 * with expected data from data provider getCheckDependingData.
			 */
			for ($x = 0; $x < $found_matches; $x++) {
				$this->page->pressKey(WebDriverKeys::ARROW_DOWN);
				$option = ($id === 'items') ? 'Graph' : 'widget';
				$suggestion_text = $this->query('xpath://div[@id="ds_0_'.$id.'_"]//div[@aria-live="assertive"]')
						->one()->waitUntilTextPresent($option)->getText();
				array_push($merged_text, $suggestion_text);
			}
			$this->assertEquals($data['expected'], $merged_text);
		}

		if (array_key_exists('Focus', $data)) {
			$this->page->pressKey(WebDriverKeys::ENTER);
			$this->assertTrue($this->query('xpath://div[@id="ds_0_items_"]//ul[@class="multiselect-list"]//span[@title="'.
				$data['Focus'].'"]')->exists()
			);
		}

		if (array_key_exists('Not Present', $data)) {
			$form->fill(['xpath:.//input[@placeholder="item pattern"]' => 'item']);
			$comperable = array_slice($data['expected'],0,9);
			$this->query('class', 'multiselect-suggest')->waitUntilVisible();
			$this->assertEquals($comperable, $form->getField('xpath://div[@id="ds_0_hosts_"]/..')->getSuggestions());
		}
	}
}
