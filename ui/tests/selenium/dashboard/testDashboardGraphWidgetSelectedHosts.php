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
 * @backup items, interface, hosts, dashboard
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

	/**
	 * @return array
	 */
	public static function prepareSelectedHostdata() {
		$hostgroupid = CDataHelper::call('hostgroup.create',
				[['name' => 'Host group for Graph widgets selected hosts']])['groupids'][0];

		CDataHelper::createHosts([
			[
				'host' => 'Host for widget 1',
				'interfaces' => [],
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
				'interfaces' => [],
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
				'interfaces' => [],
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
				'interfaces' => [],
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
				'interfaces' => [],
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

	public static function getCheckDependingData() {
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
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'item' => 'Item'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_3',
						'Item for Graph 1_4',
						'Item for Graph 1_5'
					]
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
					]
				]
			]
		];
	}

	/**
	 * Function checks if Graph Widget is correctly selecting and displaying hosts, their items
	 *
	 * @dataProvider getCheckDependingData
	 */
	public function testDashboardGraphWidgetSelectedHosts_CheckItems($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$form->fill(['Type' => 'Graph']);
		$merged_text = array();

		// Check if array is associative.
		if (CTestArrayHelper::isAssociative($data['Data set'])) {
			$data['Data set'] = [$data['Data set']];
		}

		// Change mapping of associative arrays from data set.
		foreach ($data['Data set'] as $data_set) {
			if (array_key_exists('item', $data_set)) {
				$mapping = [
					'item' => 'xpath://input[@placeholder="item pattern"]',
					'host' => 'xpath://div[@id="ds_0_hosts_"]/..'

				];
			}
			else {
				$mapping = [
					'host' => 'xpath://input[@placeholder="host pattern"]'
				];
			}

			// Put new mappings into data set and fill it in the form.
			foreach ($mapping as $field => $selector) {
				$data_set = [$selector => $data_set[$field]] + $data_set;
				unset($data_set[$field]);
			}
			$form->fill($data_set);

			// In case array which is filled contains more than 2 keys.
			if (count($data_set) >= 2) {

				// Get text from html, which from which later count for cycle is used.
				$itemtext = $this->query('xpath://div[@class="multiselect-control"]//div[@id="ds_0_items_"]//div[@aria-live="assertive"]')
						->one()->waitUntilTextPresent('use down,up arrow keys and enter to select')->getText();

				// Count words in the text which was queried.
				$count = str_word_count($itemtext, 1, '1234567890');

				// In case data is more than 20, in sentence 3rd word is used as the count for "for cycle" iteration.
				$i = intval($count[2]) - 1;

				// In case HTML texts first word contains the number of iterations we use it instead of third.
				if ($i < 0 ) {
					$i = intval($count[0]) - 1;
				}

				//$word_index = ($count[2] === '20') ? 2 : 0;
				//$found_matches = intval($count[$word_index]) - 1;

				// In any case, suggestion bar max values are up to 20, so in case, when there's more, either test data should be reduced,
				// or suggestion bar is broken.
				if ($i >= 20) {
					$this->fail('Reduce the amount of test data or suggestion window is broken and displays more data than it should.');
				}
				else {
					for ($x = 0; $x < $i; $x++) {
						// When data are filled in field, suggestion bar pops, in order to be sure that all expected data are provided,
						// we go through each of the suggestion, put it in array and then compare to the expected data from data provider.
						$this->page->pressKey(WebDriverKeys::ARROW_DOWN);
						$newitemtext = $this->query('xpath://div[@class="multiselect-control"]//div[@id="ds_0_items_"]//div[@aria-live="assertive"]')
								->one()->waitUntilTextPresent('Graph')->getText();

						// Put text from suggestion into previously defined empty array.
						// Function merges/combines arrays by putting newest value in the end of array.
						array_push($merged_text, $newitemtext);
					}
					$this->assertEquals($data['expected'], $merged_text);

				}
			}
			else {
				$host_text = $this->query('xpath://div[@class="multiselect-control"]//div[@id="ds_0_hosts_"]//div[@aria-live="assertive"]')
						->one()->waitUntilTextPresent('use down,up arrow keys and enter to select')->getText();
				$count = str_word_count($host_text, 1, '1234567890');
				$i = intval($count[0]) - 1;

				if ($i >= '20') {
					$this->fail('Reduce the amount of test data or suggestion window is broken and displays more data than it should.');
				}
				else {
					for ($x = 0; $x < $i; $x++) {
						$this->page->pressKey(WebDriverKeys::ARROW_DOWN);
						$new_host_text = $this->query('xpath://div[@class="multiselect-control"]//div[@id="ds_0_hosts_"]//div[@aria-live="assertive"]')
								->one()->waitUntilTextPresent('widget')->getText();
						array_push($merged_text, $new_host_text);
					}
					$this->assertEquals($data['expected'], $merged_text);
				}
			}
		}
	}
}
