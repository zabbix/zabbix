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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/traits/TagTrait.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testPageProblems extends CLegacyWebTest {

	use TagTrait;
	use TableTrait;

	public function testPageProblems_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->assertTrue($this->zbxTestCheckboxSelected('show_10'));
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Triggers', 'Problem', 'Not classified',
			'Information', 'Warning', 'Average', 'High', 'Disaster', 'Age less than', 'Host inventory', 'Tags',
			'Show suppressed problems', 'Show unacknowledged only', 'Severity', 'Time', 'Recovery time', 'Status', 'Host',
			'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageProblems_History_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestClickXpathWait("//label[text()='History']");
		$this->query('name:filter_apply')->one()->click();
		$this->assertTrue($this->zbxTestCheckboxSelected('show_20'));
		$this->zbxTestAssertNotVisibleId('age_state_0');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Triggers', 'Problem', 'Not classified',
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
		$this->assertTrue($this->zbxTestCheckboxSelected('evaltype_00'));
		$form = $this->query('id:tabfilter_0')->asForm()->one();
		$this->zbxTestDropdownAssertSelected('tags_00_operator', 'Contains');
		$result_form = $this->query('xpath://form[@name="problem"]')->one();

		// Select "AND" option and two tag names with partial "Contains" value match
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$this->query('name:tags_add')->one()->click();
		$form->query('name:tags[1][tag]')->one()->clear()->sendKeys('Database');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tags select to "OR" option
		$this->zbxTestClickXpath('//label[@for="evaltype_20"]');
		$this->query('name:filter_apply')->one()->click();
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
		$form = $this->query('id:tabfilter_0')->asForm()->one();
		$result_form = $this->query('xpath://form[@name="problem"]')->one();

		// Search by partial "Contains" tag value match
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tag value filter to "Equals"
		$this->zbxTestDropdownSelect('tags_00_operator', 'Equals');
		$this->query('name:filter_apply')->one()->click();
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
		$form = $this->query('id:tabfilter_0')->asForm()->one();

		// Select tag option "OR" and exact "Equals" tag value match
		$this->zbxTestClickXpath('//label[@for="evaltype_20"]');
		$this->zbxTestDropdownSelect('tags_00_operator', 'Equals');

		// Filter by two tags
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		$this->query('name:tags_add')->one()->click();
		$form->query('name:tags[1][tag]')->one()->clear()->sendKeys('service');
		$form->query('name:tags[1][value]')->one()->clear()->sendKeys('abc');

		// Search and check result
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');

		// Remove first tag option
		$this->zbxTestClickXpath('//button[@name="tags[0][remove]"]');
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
	}

	public static function getFilterByTagsExceptContainsEqualsData() {
		return [
			// "And" and "And/Or" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists'],
						['name' => 'Database', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists'],
						['name' => 'Database', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not exist'],
						['name' => 'Database', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not exist'],
						['name' => 'Database', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Trigger for tag permissions Oracle',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
					],
					'absent_problems' => [
						'Inheritance trigger with tags'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
					],
					'absent_problems' => [
						'Inheritance trigger with tags'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium'],
						['name' => 'Beta', 'operator' => 'Does not equal', 'value' => 'b']
					],
					'absent_problems' => [
						'Inheritance trigger with tags',
						'Second test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not equal', 'value' => 'abc'],
						['name' => 'Database', 'operator' => 'Does not equal']
					],
					'absent_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'],
						['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'],
						['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
					],
					'absent_problems' => [
						'First test trigger with tag priority'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterByTagsExceptContainsEqualsData
	 */
	public function testPageProblems_FilterByTagsExceptContainsEquals($data) {
		$this->page->login()->open('zabbix.php?show_timeline=0&action=problem.view&sort=name&sortorder=ASC');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:evaltype_0' => $data['evaluation_type']]);
		$this->setTagSelector('id:filter-tags_0');
		$this->setTags($data['tags']);
		$this->query('name:filter_apply')->one()->click();
		$this->page->waitUntilReady();

		// We remove from the result list templates that is not displayed there.
		if (array_key_exists('absent_problems', $data)) {
			$filtering = $this->getTableResult('Problem');
			foreach ($data['absent_problems'] as $absence) {
				if (($key = array_search($absence, $filtering))) {
					unset($filtering[$key]);
				}
			}
			$filtering = array_values($filtering);
			$this->assertTableDataColumn($filtering, 'Problem');
		}
		else {
			$this->assertTableDataColumn($data['expected_problems'], 'Problem');
		}

		// Reset filter due to not influence further tests.
		$this->query('name:filter_reset')->one()->click();
	}

	/**
	 * Search by all options in filter
	 */
	public function testPageProblems_FilterByAllOptions() {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');
		$form = $this->query('id:tabfilter_0')->asForm()->one();

		// Select host group
		$this->zbxTestClickButtonMultiselect('groupids_0');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestCheckboxSelect('item_4');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Select host
		$this->zbxTestClickButtonMultiselect('hostids_0');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		$this->zbxTestClickWait('spanid10084');

		// Select trigger
		$this->zbxTestClickButtonMultiselect('triggerids_0');
		$this->zbxTestLaunchOverlayDialog('Triggers');
		$this->zbxTestCheckboxSelect("item_'99250'");
		$this->zbxTestCheckboxSelect("item_'99251'");
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Type problem name
		$this->zbxTestInputType('name_0', 'Test trigger');

		// Select average, high and disaster severities
		$this->query('name:zbx_filter')->asForm()->one()->getField('Severity')->fill(['Average', 'High', 'Disaster']);

		// Add tag
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		// Check Show unacknowledged only
		$this->zbxTestCheckboxSelect('unacknowledged_0');
		// Check Show details
		$this->zbxTestCheckboxSelect('details_0');

		// Apply filter and check result
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestClickButtonText('Reset');
	}

	public function testPageProblems_ShowTags() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');
		$form = $this->query('id:tabfilter_0')->asForm()->one()->waitUntilVisible();
		$result_form = $this->query('xpath://form[@name="problem"]')->one();

		// Check Show tags NONE
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('service');
		$this->zbxTestClickXpath('//label[@for="show_tags_00"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		// Check result
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestAssertElementNotPresentXpath('//thead/tr/th[text()="Tags"]');

		// Check Show tags 1
		$this->zbxTestClickXpath('//label[@for="show_tags_10"]');
		$this->query('name:filter_apply')->one()->click();
		$this->page->waitUntilReady();

		// Check Tags column in result
		$this->zbxTestAssertVisibleXpath('//thead/tr/th[text()="Tags"]');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestTextNotVisible('Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');

		// Check Show tags 2
		$this->zbxTestClickXpath('//label[@for="show_tags_20"]');
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestWaitForPageToLoad();
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/button[@class="icon-wzrd-action"]');

		// Check Show tags 3
		$this->zbxTestClickXpath('//label[@for="show_tags_30"]');
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestWaitForPageToLoad();
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[3]', 'Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/button[@class="icon-wzrd-action"]');
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
		$this->zbxTestInputType('name_0', 'trigger with tag priority');

		if (array_key_exists('show_tags', $data)) {
			$this->zbxTestClickXpath('//label[@for="show_tags_'.$data['show_tags'].'0"]');
		}

		if (array_key_exists('tag_priority', $data)) {
			$this->zbxTestInputType('tag_priority_0', $data['tag_priority']);
		}

		if (array_key_exists('tag_name_format', $data)) {
			$this->zbxTestClickXpath('//ul[@id="tag_name_format_0"]//label[text()="'.$data['tag_name_format'].'"]');
		}

		$this->query('name:filter_apply')->one()->click();
		$this->page->waitUntilReady();

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
			$this->zbxTestAssertElementPresentXpath('//input[@id="tag_priority_0"][@disabled]');
			$this->zbxTestAssertElementPresentXpath('//input[contains(@id, "tag_name_format_")][@disabled]');
		}
	}

	public function testPageProblems_SuppressedProblems() {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');
		$this->page->waitUntilReady();
		$result_form = $this->query('xpath://form[@name="problem"]')->one();

		$this->zbxTestClickButtonMultiselect('hostids_0');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		COverlayDialogElement::find()->one()->waitUntilReady()->setDataContext('Host group for suppression');

		$this->zbxTestClickLinkTextWait('Host for suppression');
		COverlayDialogElement::ensureNotPresent();
		$this->query('name:filter_apply')->one()->waitUntilClickable()->click();
		$result_form->waitUntilReloaded();

		$this->zbxTestTextNotPresent('Trigger_for_suppression');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');

		$this->zbxTestCheckboxSelect('show_suppressed_0');
		$this->query('name:filter_apply')->one()->click();

		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Trigger_for_suppression');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'SupTag: A');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');

		// Click on suppression icon and check text in hintbox.
		$this->zbxTestClickXpathWait('//tbody/tr/td[8]/div/a[contains(@class, "icon-invisible")]');
		$this->zbxTestAssertElementText('//div[@data-hintboxid]', 'Suppressed till: 12:17 Maintenance: Maintenance for suppression test');
	}
}
