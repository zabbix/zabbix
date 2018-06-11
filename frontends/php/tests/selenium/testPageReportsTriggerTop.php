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

class testPageReportsTriggerTop extends CWebTest {

	public function testPageReportsTriggerTop_FilterLayout() {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestTextPresent('Host groups', 'Hosts', 'Severity', 'Filter', 'From', 'Till');

		$this->zbxTestClickButtonText('Reset');

		// Check selected severities
		$severities = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		foreach ($severities as $severity) {
			$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
			$this->assertTrue($this->zbxTestCheckboxSelected($severity_id));
		}

		// Check closed filter
		$this->zbxTestClickWait('filter-mode');
		$this->zbxTestAssertNotVisibleId('filter-space');
		$this->zbxTestAssertNotVisibleId('groupids_');

		// Check opened filter
		$this->zbxTestClickWait('filter-mode');
		$this->zbxTestAssertVisibleId('filter-space');
		$this->zbxTestAssertVisibleId('groupids_');

		// Ckeck empty trigger list
		$this->zbxTestAssertElementText('//tr[@class=\'nothing-to-show\']/td', 'No data found.');
	}

	public static function getFilterData() {
		return [
			[
				[
					'host_group' => 'Zabbix servers'
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'date' => [
						'from' => '01.01.2016 00:00'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'Host ZBX6663',
					'date' => [
						'from' => '01.01.2016 00:00'
					],
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'date' => [
						'from' => '01.01.2016 00:00'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'date' => [
						'from' => '01.01.2016 15:15',
						'till' => '01.01.2017 15:15'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'date' => [
						'from' => '22.10.2017 01:01',
						'till' => '24.10.2017 01:01'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '23.10.2017 12:35',
						'till' => '23.10.2017 12:36'
					],
					'result' => [
						'Trigger for tag permissions MySQL'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '23.10.2017 12:33',
						'till' => '23.10.2017 12:36'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Trigger for tag permissions MySQL'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '01.01.2016 00:00'
					],
					'severities' => [
						'Not classified',
						'Information',
						'Warning'
					],
					'result' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '01.01.2016 00:00'
					],
					'severities' => [
						'Not classified',
						'Warning',
						'Information',
						'Average'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageReportsTriggerTop_CheckFilter($data) {
		$this->zbxTestLogin('toptriggers.php');
		// Click button 'Reset'
		$this->zbxTestClickButtonText('Reset');

		if (array_key_exists('host_group', $data)) {
			$this->zbxTestClickButtonMultiselect('groupids_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkTextWait($data['host_group']);
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@id='overlay_dialogue']"));
			$this->zbxTestMultiselectAssertSelected('groupids_', $data['host_group']);
		}

		if (array_key_exists('host', $data)) {
			$this->zbxTestClickButtonMultiselect('hostids_');
			$this->zbxTestLaunchOverlayDialog('Hosts');
			$this->zbxTestDropdownHasOptions('groupid', ['Host group for tag permissions', 'Zabbix servers',
				'ZBX6648 All Triggers', 'ZBX6648 Disabled Triggers', 'ZBX6648 Enabled Triggers']
			);
			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestClickLinkTextWait($data['host']);
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@id='overlay_dialogue']"));
			$this->zbxTestMultiselectAssertSelected('hostids_', $data['host']);
		}

		// Fill in the date in filter
		if (array_key_exists('date', $data)) {
			$this->fillInDate($data['date']);
		}

		if (array_key_exists('severities', $data)) {
			foreach ($data['severities'] as $severity) {
				$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
				$this->zbxTestClick($severity_id);
			}
		}

		$this->zbxTestClickXpathWait('//button[@name=\'filter_set\']');
		$this->zbxTestWaitForPageToLoad();
		if (array_key_exists('result', $data)) {
			$this->zbxTestTextPresent($data['result']);
		}
		else {
			$this->zbxTestAssertElementText('//tr[@class=\'nothing-to-show\']/td', 'No data found.');
		}
	}

	/*
	 * Update date in 'From' and/or 'Till' filter field
	 */
	public function fillInDate($data) {
		foreach ($data as $i => $full_date) {
			$split_date = explode(' ', $full_date);
			$date = explode('.', $split_date[0]);
			$time = explode(':', $split_date[1]);

			$fields = [
				'day' => $date[0], 'month' => $date[1], 'year' => $date[2], 'hour' => $time[0], 'minute' => $time[1]
			];

			foreach ($fields as $key => $value) {
				$this->zbxTestWaitUntilElementClickable(WebDriverBy::id('filter_'.$i.'_'.$key));
				$this->zbxTestInputTypeOverwrite('filter_'.$i.'_'.$key, $value);

				// Fire onchange event.
				$this->webDriver->executeScript('var event = document.createEvent("HTMLEvents");'.
						'event.initEvent("change", false, true);'.
						'document.getElementById("filter_'.$i.'_'.$key.'").dispatchEvent(event);'
				);
			}
		}
	}
}
