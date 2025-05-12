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
		$hostgroupid = CDataHelper::call('hostgroup.create', [['name' => 'Suggestion list group']])['groupids'][0];

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
						'host' => 'Host for widget 3'
					],
					'type' => [
						'field' => 'host',
						'value' => 'Host for widget'
					],
					'expected' => [
						'Host for widget 1',
						'Host for widget 2',
						'Host for widget 4',
						'Host for widget 5'
					],
					'keyboard_navigation' => true
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'item' => 'Item for Graph 1_3'
					],
					'type' => [
						'field' => 'item',
						'value' => 'Item for'
					],
					'expected' => [
						'Item for Graph 1_1',
						'Item for Graph 1_2',
						'Item for Graph 1_4',
						'Item for Graph 1_5'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget*'
					],
					'type' => [
						'field' => 'item',
						'value' => 'Item'
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
			],
			[
				[
					'Data set' => [
						'host' => ['Host for widget 1', 'Host for widget 2']
					],
					'type' => [
						'field' => 'item',
						'value' => 'Item'
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
					'keyboard_navigation' => true
				]
			],
			[
				[
					'Data set' => [
						'host' => ['Host for widget 1', 'Host for widget 4']
					],
					'type' => [
						'field' => 'item',
						'value' => 'Item'
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
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => ['Host for widget 1', 'Host for widget 4']
					],
					'type' => [
						'field' => 'item',
						'value' => 'Item for Graph 2'
					],
					'expected' => []
				]
			]
		];
	}

	/**
	 * Check host and item suggestion lists in Graph widget.
	 *
	 * @dataProvider getDatasetData
	 */
	public function testDashboardGraphWidgetSelectedHosts_CheckSuggestionList($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Graph')]);

		$mapping = [
			'host' => 'xpath:.//div[@id="ds_0_hosts_"]/..',
			'item' => 'xpath:.//div[@id="ds_0_items_"]/..'
		];

		// If the host or item of data set exists in data provider, add xpath selector to it.
		foreach ($mapping as $field => $selector) {
			if (array_key_exists($field, $data['Data set'])) {
				$data['Data set'][$selector] = $data['Data set'][$field];
				unset($data['Data set'][$field]);
			}
		}
		$form->fill($data['Data set']);

		// Enter a value in the host or item field to get list of suggestions.
		$field = $form->getField($mapping[$data['type']['field']]);
		$field->query('tag:input')->one()->type($data['type']['value']);

		$this->assertEquals($data['expected'], $field->getSuggestionsText());

		if (CTestArrayHelper::get($data, 'keyboard_navigation')) {
			$this->checkSuggestionListWithKeyboardNavigation($data, $form, $field);
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Suggestion list check using keyboard navigation.
	 *
	 * @param array			        $data		data provider
	 * @param CFormElement       	$form		form element of dashboard share
	 * @param CMultiselectElement 	$field		field for suggestion list
	 */
	protected function checkSuggestionListWithKeyboardNavigation($data, $form, $field) {
		$actual_suggestions = [];
		$id = CTestArrayHelper::get($data['type'], 'field').'s';

		// Go through the whole suggestion list using keyboard navigation and collect values that were in focus.
		$option = ($id === 'items') ? 'Graph' : 'widget';
		for ($x = 0; $x < (count($data['expected'])); $x++) {
			$this->page->pressKey(WebDriverKeys::ARROW_DOWN);
			$suggestion_text = $this->query('xpath://div[@id="ds_0_'.$id.'_"]//div[@aria-live="assertive"]')
					->one()->waitUntilTextPresent($option)->getText();
			array_push($actual_suggestions, $suggestion_text);
		}

		// Check that using keyboard navigation all suggestions were reachable.
		$this->assertEquals($data['expected'], $actual_suggestions);

		// Submit the last entry in the array and check that it was selected.
		$this->page->pressKey(WebDriverKeys::ENTER);

		// Check that the last value is selected.
		$selected = end($data['expected']);
		$this->assertTrue($this->query('xpath://li[@data-id='.CXPathHelper::escapeQuotes($selected).']')->one()->isValid());

		// Check that selected value is not in the list of suggestions.
		$field->query('tag:input')->one()->type(($id === 'items') ? 'Graph' : 'Host for widget');
		$this->assertEquals(array_diff($data['expected'], [$selected]), $field->getSuggestionsText());
	}
}
