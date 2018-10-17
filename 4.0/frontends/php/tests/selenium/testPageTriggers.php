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
	public $hostid = 99050;

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
		// Get table headers.
		$result = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath("//thead/tr/th"));
		foreach ($elements as $element) {
			$result[] = $element->getText();
		}

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
			// Check table headers.
			$this->assertEquals(['', 'Severity', 'Value', 'Name', 'Expression', 'Status', 'Info', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status', 'Value', 'Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
		}

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			// Check table headers.
			$this->assertEquals(['', 'Severity', 'Name', 'Expression', 'Status', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status','Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
			$this->zbxTestAssertElementNotPresentXpath('//label[text()="Value"]');
		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Delete');
	}


	public static function getTagsFilterData() {
		return [
			[
				[
					'tags_operator' => 'And/Or',
					'tags' => [
						['tag_name' =>'TagA', 'value' => 'a', 'operator' => 'Equal'],
						['tag_name' =>'TagK', 'value' => 'K', 'operator' => 'Like']
					],
					'results' => [
						'Third trigger for tag filtering'
					],
					'not_results' => [
						'First trigger for tag filtering',
						'Second trigger for tag filtering',
						'Fourth trigger for tag filtering',
						'Fifth trigger for tag filtering (no tags)'
					]
				]
			],
			[
				[
					'tags_operator' => 'And/Or',
					'tags' => [
						['tag_name' =>'TagA', 'value' => 'A', 'operator' => 'Like'],
						['tag_name' =>'TagK', 'value' => 'K', 'operator' => 'Like']
					],
					'results' => [
						'Third trigger for tag filtering'
					],
					'not_results' => [
						'First trigger for tag filtering',
						'Second trigger for tag filtering',
						'Fourth trigger for tag filtering',
						'Fifth trigger for tag filtering (no tags)'
					]
				]
			],
			[
				[
					'tags_operator' => 'Or',
					'tags' => [
						['tag_name' =>'TagA', 'value' => 'A', 'operator' => 'Like'],
						['tag_name' =>'TagK', 'value' => 'K', 'operator' => 'Like']
					],
					'results' => [
						'Third trigger for tag filtering',
						'First trigger for tag filtering'
					],
					'not_results' => [
						'Second trigger for tag filtering',
						'Fourth trigger for tag filtering',
						'Fifth trigger for tag filtering (no tags)'
					]
				]
			],
			[
				[
					'tags_operator' => 'Or',
					'tags' => [
						['tag_name' =>'TagZ', 'value' => 'Z', 'operator' => 'Equal'],
						['tag_name' =>'TagI', 'value' => 'I', 'operator' => 'Equal']
					],
					'not_results' => [
						'First trigger for tag filtering',
						'Second trigger for tag filtering',
						'Third trigger for tag filtering',
						'Fourth trigger for tag filtering',
						'Fifth trigger for tag filtering (no tags)'
					]
				]
			],
			[
				[
					'tags_operator' => 'Or',
					'tags' => [
						['tag_name' =>'TagZ', 'value' => 'z', 'operator' => 'Equal'],
						['tag_name' =>'TagI', 'value' => 'i', 'operator' => 'Equal']
					],
					'results' => [
						'Third trigger for tag filtering',
						'Second trigger for tag filtering'
					],
					'not_results' => [
						'First trigger for tag filtering',
						'Fourth trigger for tag filtering',
						'Fifth trigger for tag filtering (no tags)'
					]
				]
			]
		];
	}

	/**
	 *
	 * @dataProvider getTagsFilterData
	 */
	public function testPageTriggers_TagsFilter($data) {
		$this->zbxTestLogin('triggers.php?hostid='.$this->hostid);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 5 of 5 found');

		$this->zbxTestClickXpath('//label[text()="'.$data['tags_operator'].'"]');

		foreach ($data['tags'] as $i => $tag) {
			$this->zbxTestInputTypeWait('filter_tags_'.$i.'_tag', $tag['tag_name']);
			$this->zbxTestInputType('filter_tags_'.$i.'_value', $tag['value'] );

			$operator = ($tag['operator'] === 'Like') ?  0 : 1;
			$this->zbxTestClickXpath('//label[@for="filter_tags_'.$i.'_operator_'.$operator.'"]');
			$this->zbxTestClick('filter_tags_add');
		}
		$this->zbxTestClickButtonText('Apply');

		if (array_key_exists('results', $data)) {
			$result_count = count($data['results']);
			$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying '.$result_count.' of '.$result_count.' found');
			$this->zbxTestTextPresent($data['results']);
		}
		else {
			$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');
		}

		$this->zbxTestTextNotPresent($data['not_results']);
	}

	public function testPageTriggers_TagsResetFilter() {
		$this->zbxTestLogin('triggers.php?hostid='.$this->hostid);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 5 of 5 found');
		$this->zbxTestTextPresent([
			'First trigger for tag filtering',
			'Second trigger for tag filtering',
			'Third trigger for tag filtering',
			'Fourth trigger for tag filtering',
			'Fifth trigger for tag filtering (no tags)'
		]);
	}
}
