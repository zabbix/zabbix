<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageTriggers extends CWebTest {
	public $hostid = 99020;

	public static function data() {
		return DBdata(
			'SELECT hostid,status'.
			' FROM hosts'.
			' WHERE host LIKE \'%-layout-test%\''
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageTriggers_CheckLayout($data) {
		$this->zbxTestLogin('triggers.php?hostid='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');

		$this->zbxTestTextPresent('Displaying');
		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
			$this->zbxTestTextPresent(
				[
					'Severity',
					'Name',
					'Expression',
					'Status',
					'Info'
				]
			);

			// Check Filter.
			foreach (['Severity', 'State', 'Status', 'Value', 'Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
		}

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			$this->zbxTestTextPresent(
				[
					'Severity',
					'Name',
					'Expression',
					'Status'
				]
			);
			$this->zbxTestAssertElementNotPresentXpath("//table[@class='list-table']//th[text()='Info']");

			// Check Filter.
			foreach (['Severity', 'State', 'Status','Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}

		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Delete');
	}


	public static function getCheckTagsFilterData() {
		return [
			[
				[
					'tags_operator' => 'And/Or',
					'value_operator' => 'Like',
					'tags' =>
						[
							['tag_name' =>'TagA', 'value' => 'A'],
							['tag_name' =>'TagK', 'value' => 'K']
						],
					'results' =>
						[
							['Third trigger for tag filtering']
						],
					'result_count' => '1',
					'not_results' =>
						[
							['First trigger for tag filtering'],
							['Second trigger for tag filtering'],
							['Fourth trigger for tag filtering'],
							['Fifth trigger for tag filtering (no tags)']
						]
				]
			],
			[
				[
					'tags_operator' => 'Or',
					'value_operator' => 'Like',
					'tags' =>
						[
							['tag_name' =>'TagA', 'value' => 'A'],
							['tag_name' =>'TagK', 'value' => 'K']
						],
					'results' =>
						[
							['Third trigger for tag filtering'],
							['First trigger for tag filtering']
						],
					'result_count' => '2',
					'not_results' =>
						[
							['Second trigger for tag filtering'],
							['Fourth trigger for tag filtering'],
							['Fifth trigger for tag filtering (no tags)']
						]
				]
			],
			[
				[
					'tags_operator' => 'Or',
					'value_operator' => 'Equal',
					'tags' =>
						[
							['tag_name' =>'TagZ', 'value' => 'Z'],
							['tag_name' =>'TagI', 'value' => 'I']
						],
					'result_count' => '0',
					'not_results' =>
						[
							['First trigger for tag filtering'],
							['Second trigger for tag filtering'],
							['Third trigger for tag filtering'],
							['Fourth trigger for tag filtering'],
							['Fifth trigger for tag filtering (no tags)']
						]
				]
			],
						[
				[
					'tags_operator' => 'Or',
					'value_operator' => 'Equal',
					'tags' =>
						[
							['tag_name' =>'TagZ', 'value' => 'z'],
							['tag_name' =>'TagI', 'value' => 'i']
						],
					'results' =>
						[
							['Third trigger for tag filtering'],
							['Second trigger for tag filtering']
						],
					'result_count' => '2',
					'not_results' =>
						[
							['First trigger for tag filtering'],
							['Fourth trigger for tag filtering'],
							['Fifth trigger for tag filtering (no tags)']
						]
				]
			]
		];
	}

	/**
	 *
	 * @dataProvider getCheckTagsFilterData
	 */
	public function testPageTriggers_CheckTagsFilter($data) {
		$this->zbxTestLogin('triggers.php?hostid='.$this->hostid);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 5 of 5 found');

		$data['value_operator'] == 'Like' ? $operator = 0 : $operator = 1;

		$this->zbxTestClickXpath('//label[text()="'.$data['tags_operator'].'"]');

		foreach ($data['tags'] as $i => $tag) {
			$this->zbxTestInputTypeWait('filter_tags_'.$i.'_tag', $tag['tag_name']);
			$this->zbxTestClickXpath('//label[@for="filter_tags_'.$i.'_operator_'.$operator.'"]');
			$this->zbxTestInputType('filter_tags_'.$i.'_value', $tag['value'] );
			$this->zbxTestClick('filter_tags_add');
		}
		$this->zbxTestClickButtonText('Apply');


		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying '.$data['result_count'].' of '.$data['result_count'].' found');

		if (array_key_exists('results', $data)) {
			foreach ($data['results'] as $result) {
					$this->zbxTestTextPresent($result);
			}
		}

		foreach ($data['not_results'] as $not_result) {
			$this->zbxTestTextNotPresent($not_result);
		}
	}

	public function testPageTriggers_CheckTagsResetFilter() {
		$this->zbxTestLogin('triggers.php?hostid='.$this->hostid);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 5 of 5 found');
		$triggers =
			[
				'First trigger for tag filtering',
				'Second trigger for tag filtering',
				'Third trigger for tag filtering',
				'Fourth trigger for tag filtering',
				'Fifth trigger for tag filtering (no tags)'
			];
		foreach ($triggers as $trigger) {
				$this->zbxTestTextPresent($trigger);
			}
	}
}
