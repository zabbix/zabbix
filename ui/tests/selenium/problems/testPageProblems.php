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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testPageProblems extends CLegacyWebTest {

	public function testPageProblems_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_0'));
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem', 'Not classified',
			'Information', 'Warning', 'Average', 'High', 'Disaster', 'Age less than', 'Host inventory', 'Tags',
			'Show suppressed problems', 'Show unacknowledged only', 'Severity', 'Time', 'Recovery time', 'Status', 'Host',
			'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageProblems_History_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestClickXpathWait("//label[@for='filter_show_2']");
		$this->zbxTestClickButtonText('Apply');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_2'));
		$this->zbxTestAssertNotVisibleId('filter_age_state');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem', 'Not classified',
			'Information', 'Warning', 'Average', 'High', 'Disaster', 'Host inventory', 'Tags', 'Show suppressed problems',
			'Show unacknowledged only', 'Severity', 'Time', 'Recovery time','Status', 'Host', 'Problem', 'Duration',
			'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	/**
	 * Search problems by "AND" or "OR" tag options
	 */
	public function testPageProblems_FilterByTagsOptionAndOr() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		// Check the default tag filter option AND and tag value option Contains
		$this->zbxTestClickButtonText('Reset');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_evaltype_0'));
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_tags_0_operator_0'));

		// Select "AND" option and two tag names with partial "Contains" value match
		$this->zbxTestInputType('filter_tags_0_tag', 'Service');
		$this->zbxTestClick('filter_tags_add');
		$this->zbxTestInputTypeWait('filter_tags_1_tag', 'Database');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tags select to "OR" option
		$this->zbxTestClickXpath('//label[@for="filter_evaltype_1"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[4]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 4 of 4 found');
	}

	/**
	 * Search problems by partial or exact tag value match
	 */
	public function testPageProblems_FilterByTagsOptionContainsEquals() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Search by partial "Contains" tag value match
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tag value filter to "Equals"
		$this->zbxTestClickXpath('//label[@for="filter_tags_0_operator_1"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[@class="nothing-to-show"]/td', 'No data found.');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');
	}

	/**
	 * Search problems by partial and exact tag value match and then remove one
	 */
	public function testPageProblems_FilterByTagsOptionContainsEqualsAndRemoveOne() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Select tag option "OR" and exact "Equals" tag value match
		$this->zbxTestClickXpath('//label[@for="filter_evaltype_1"]');
		$this->zbxTestClickXpath('//label[@for="filter_tags_0_operator_1"]');

		// Filter by two tags
		$this->zbxTestInputType('filter_tags_0_tag', 'Service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		$this->zbxTestClick('filter_tags_add');
		$this->zbxTestInputTypeWait('filter_tags_1_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');

		// Search and check result
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');

		// Remove first tag option
		$this->zbxTestClick('filter_tags_0_remove');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
	}

	/**
	 * Search by all options in filter
	 */
	public function testPageProblems_FilterByAllOptions() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Select host group
		$this->zbxTestClickButtonMultiselect('filter_groupids_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestCheckboxSelect('item_4');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Select host
		$this->zbxTestClickButtonMultiselect('filter_hostids_');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		$this->zbxTestClickWait('spanid10084');

		// Type application
		$this->zbxTestInputType('filter_application', 'General');

		// Select trigger
		$this->zbxTestClickButtonMultiselect('filter_triggerids_');
		$this->zbxTestLaunchOverlayDialog('Triggers');
		$this->zbxTestCheckboxSelect("item_'99250'");
		$this->zbxTestCheckboxSelect("item_'99251'");
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Type problem name
		$this->zbxTestInputType('filter_name', 'Test trigger');

		// Select average, high and disaster severities
		$this->query('name:zbx_filter')->asForm()->one()->getField('Severity')->fill(['Average', 'High', 'Disaster']);

		// Add tag
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		// Check Show unacknowledged only
		$this->zbxTestCheckboxSelect('filter_unacknowledged');
		// Check Show details
		$this->zbxTestCheckboxSelect('filter_details');

		// Apply filter and check result
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestClickButtonText('Reset');
	}

	public function testPageProblems_ShowTags() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Check Show tags NONE
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestClickXpath('//label[@for="filter_show_tags_0"]');
		$this->zbxTestClickButtonText('Apply');
		// Check result
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestAssertElementNotPresentXpath('//thead/tr/th[text()="Tags"]');

		// Check Show tags 1
		$this->zbxTestClickXpath('//label[@for="filter_show_tags_1"]');
		$this->zbxTestClickButtonText('Apply');
		// Check Tags column in result
		$this->zbxTestAssertVisibleXpath('//thead/tr/th[text()="Tags"]');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestTextNotVisible('Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');

		// Check Show tags 2
		$this->zbxTestClickXpath('//label[@for="filter_show_tags_2"]');
		$this->zbxTestClickButtonText('Apply');
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/span/button[@class="icon-wzrd-action"]');

		// Check Show tags 3
		$this->zbxTestClickXpath('//label[@for="filter_show_tags_3"]');
		$this->zbxTestClickButtonText('Apply');
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[3]', 'Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/span/button[@class="icon-wzrd-action"]');
	}

	public function getTagPriorityData() {
		return [
			// Check tag priority.
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a', 'Beta: b', 'Delta: d'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e', 'Gamma: g']
					]
				]
			],
			[
				[
					'tag_priority' => 'Kappa, Beta',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Beta: b', 'Alpha: a', 'Delta: d'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e', 'Gamma: g']
					]
				]
			],
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Gamma: g','Beta: b', 'Alpha: a'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Gamma: g','Delta: t', 'Eta: e']
					]
				]
			],
			// Check tag name format.
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'tag_name_format' => 'Shortened',
					'sorting' => [
						'First test trigger with tag priority' => ['Gam: g','Bet: b', 'Alp: a'],
						'Second test trigger with tag priority' => ['Bet: b', 'Eps: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kap: k', 'Alp: a', 'Iot: i'],
						'Fourth test trigger with tag priority' => ['Gam: g','Del: t', 'Eta: e']
					]
				]
			],
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'tag_name_format' => 'None',
					'sorting' => [
						'First test trigger with tag priority' => ['g','b', 'a'],
						'Second test trigger with tag priority' => ['b', 'e', 'e'],
						'Third test trigger with tag priority' => ['k', 'a', 'i'],
						'Fourth test trigger with tag priority' => ['g','t', 'e']
					]
				]
			],
			// Check tags count.
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '2',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a', 'Beta: b'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e']
					]
				]
			],
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '1',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a'],
						'Second test trigger with tag priority' => ['Beta: b'],
						'Third test trigger with tag priority' => ['Kappa: k'],
						'Fourth test trigger with tag priority' => ['Delta: t']
					]
				]
			],
			[
				[
					'show_tags' => '0'
				]
			]
		];
	}

	/**
	 * @dataProvider getTagPriorityData
	 */
	public function testPageProblems_TagPriority($data) {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestInputType('filter_name', 'trigger with tag priority');

		if (array_key_exists('show_tags', $data)) {
			$this->zbxTestClickXpath('//label[@for="filter_show_tags_'.$data['show_tags'].'"]');
		}

		if (array_key_exists('tag_priority', $data)) {
			$this->zbxTestInputType('filter_tag_priority', $data['tag_priority']);
		}

		if (array_key_exists('tag_name_format', $data)) {
			$this->zbxTestClickXpath('//ul[@id="filter_tag_name_format"]//label[text()="'.$data['tag_name_format'].'"]');
		}

		$this->zbxTestClickButtonText('Apply');

		// Check tag priority sorting.
		if (array_key_exists('sorting', $data)) {
			foreach ($data['sorting'] as $problem => $tags) {
				$tags_priority = [];
				$get_tags_rows = $this->webDriver->findElements(WebDriverBy::xpath('//a[text()="'.$problem.'"]/../../td/span[@class="tag"]'));
				foreach ($get_tags_rows as $row) {
					$tags_priority[] = $row->getText();
				}
				$this->assertEquals($tags, $tags_priority);
			}
		}

		if ($data['show_tags'] === '0') {
			$this->zbxTestAssertElementNotPresentXpath('//th[text()="Tags"]');
			$this->zbxTestAssertElementPresentXpath('//input[@id="filter_tag_priority"][@disabled]');
			$this->zbxTestAssertElementPresentXpath('//input[contains(@id, "filter_tag_name_format")][@disabled]');
		}
	}

	public function testPageProblems_SuppressedProblems() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		$this->zbxTestClickButtonMultiselect('filter_hostids_');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		COverlayDialogElement::find()->one()->setDataContext('Host group for suppression');

		$this->zbxTestClickLinkTextWait('Host for suppression');
		$this->zbxTestClickButtonText('Apply');

		$this->zbxTestTextNotPresent('Trigger_for_suppression');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');

		$this->zbxTestCheckboxSelect('filter_show_suppressed');
		$this->zbxTestClickButtonText('Apply');

		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Trigger_for_suppression');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'SupTag: A');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');

		// Click on suppression icon and check text in hintbox.
		$this->zbxTestClickXpathWait('//tbody/tr/td[8]/div/a[contains(@class, "icon-invisible")]');
		$this->zbxTestAssertElementText('//div[@data-hintboxid]', 'Suppressed till: 12:17 Maintenance: Maintenance for suppression test');
	}
}
