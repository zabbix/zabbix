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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageProblems extends CWebTest {

	public function testPageProblems_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_0'));
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Age less than', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only',
			'Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageProblems_History_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestClickXpathWait("//label[@for='filter_show_2']");
		$this->zbxTestClickButtonText('Apply');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_2'));
		$this->zbxTestAssertNotVisibleId('filter_age_state');
		$this->zbxTestAssertElementPresentId('scrollbar_cntr');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only',
			'Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	/**
	 * Search problems by "AND" or "OR" tag options
	 */
	public function testPageProblems_FilterByTagsOptionAndOr() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		// Check the default tag filter option AND and tag value option Like
		$this->zbxTestClickButtonText('Reset');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_evaltype_0'));
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_tags_0_operator_0'));

		// Select "AND" option and two tag names with partial "Like" value match
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
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');
	}

	/**
	 * Search problems by partial or exact tag value match
	 */
	public function testPageProblems_FilterByTagsOptionLikeEqual() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Search by partial "Like" tag value match
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tag value filter to "Equal"
		$this->zbxTestClickXpath('//label[@for="filter_tags_0_operator_1"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[@class="nothing-to-show"]/td', 'No data found.');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');
	}

	/**
	 * Search problems by partial and exact tag value match and then remove one
	 */
	public function testPageProblems_FilterByTagsOptionLikeEqualAndRemoveOne() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Select tag option "OR" and exact "Equal" tag value match
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
		$this->zbxTestInputType('filter_application', 'Processes');

		// Select trigger
		$this->zbxTestClickButtonMultiselect('filter_triggerids_');
		$this->zbxTestLaunchOverlayDialog('Triggers');

		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelect('hostid', 'ЗАББИКС Сервер');
		$this->zbxTestCheckboxSelect("item_'99250'");
		$this->zbxTestCheckboxSelect("item_'99251'");
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Type problem name
		$this->zbxTestInputType('filter_name', 'Test trigger');

		// Change minimum severity to Average
		$this->zbxTestDropdownSelect('filter_severity', 'Average');
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
}
