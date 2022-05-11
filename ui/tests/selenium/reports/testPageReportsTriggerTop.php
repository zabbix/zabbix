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

class testPageReportsTriggerTop extends CLegacyWebTest {

	public function testPageReportsTriggerTop_FilterLayout() {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestTextPresent('Host groups', 'Hosts', 'Severity', 'Filter', 'From', 'Till');
		$this->zbxTestClickXpathWait('//button[text()="Reset"]');

		// Check unselected severities
		$severities = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		foreach ($severities as $severity) {
			$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
			$this->assertTrue($this->query('id', $severity_id)->waitUntilPresent()->one()->isSelected(false));
		}

		// Check closed filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertNotVisibleId('groupids_');

		// Check opened filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertVisibleId('groupids_');
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers'
					],
					'date' => [
						'from' => '2020-10-23 00:00',
						'to' => 'now/d'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers'
					],
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers',
						'host' => 'ЗАББИКС Сервер'
					],
					'date' => [
						'from' => '2020-10-23 17:00'
					],
					'result' => [
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers',
						'host' => 'Host ZBX6663'
					],
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers',
						'host' => 'ЗАББИКС Сервер'
					],
					'date' => [
						'from' => '2020-10-22 04:01',
						'to' => '2020-10-24 04:01'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'filter' => [
						'host_group' => 'Zabbix servers',
						'host' => 'ЗАББИКС Сервер'
					],
					'date' => [
						'from' => '2020-10-26 00:00',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2020-10-23 15:35',
						'to' => '2020-10-23 15:36'
					],
					'result' => [
						'Trigger for tag permissions MySQL'
					]
				]
			],
			[
				[
					'filter' => [
						'severities' => [
							'Average',
							'High',
							'Disaster'
						]
					],
					'date' => [
						'from' => '2020-10-22 00:00'
					],
					'result' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'filter' => [
						'severities' => [
							'High',
							'Disaster'
						]
					],
					'date' => [
						'from' => '2020-10-22 00:00'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2020-10-23 15:33',
						'to' => '2020-10-23 15:36'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Trigger for tag permissions MySQL'
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
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestWaitForPageToLoad();

		if (array_key_exists('filter', $data)) {
			$filter = $data['filter'];

			if (array_key_exists('host_group', $filter)) {
				$this->zbxTestClickButtonMultiselect('groupids_');
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestClickLinkTextWait($filter['host_group']);
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[contains(@class, "overlay-dialogue modal")]'));
				$this->zbxTestMultiselectAssertSelected('groupids_', $filter['host_group']);
			}

			if (array_key_exists('host', $filter)) {
				$this->zbxTestClickButtonMultiselect('hostids_');
				$this->zbxTestLaunchOverlayDialog('Hosts');
				COverlayDialogElement::find()->one()->query('class:multiselect-button')->one()->click();
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$groups_form = $this->query('name:hostGroupsform')->one();
				$all_groups = $groups_form->query('xpath:.//a')->all()->asText();
				$groups = ['Host group for tag permissions', 'Zabbix servers', 'ZBX6648 All Triggers',
					'ZBX6648 Disabled Triggers', 'ZBX6648 Enabled Triggers'];
				$this->assertTrue(count(array_diff($groups, $all_groups)) === 0);
				$groups_form->query('xpath://a[text()="Zabbix servers"]')->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
				$this->zbxTestClickXpathWait('//div[contains(@class, "overlay-dialogue modal")]//a[text()="'.
						$filter['host'].'"]');
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[contains(@class, "overlay-dialogue modal")]'));
				$this->zbxTestMultiselectAssertSelected('hostids_', $filter['host']);
			}

			if (array_key_exists('severities', $filter)) {
				foreach ($filter['severities'] as $severity) {
					$severity_id = $this->zbxTestGetAttributeValue('//label[text()="'.$severity.'"]', 'for');
					$this->zbxTestClick($severity_id);
				}
			}

			$this->zbxTestClickXpathWait('//button[@name="filter_set"][text()="Apply"]');
			$this->zbxTestWaitForPageToLoad();
		}

		// Fill in the date in filter.
		if (array_key_exists('date', $data)) {
			$this->zbxTestExpandFilterTab('Time');
			foreach ($data['date'] as $i => $full_date) {
				$this->zbxTestInputTypeOverwrite($i, $full_date);
			}
			// Wait till table id will be changed after filter apply.
			$tabel_id = $this->zbxTestGetAttributeValue('//table[@class="list-table"]', 'id');
			$this->zbxTestClickWait('apply');
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//table[@class="list-table"][not(@id="'.$tabel_id.'")]'));
			$this->zbxTestWaitForPageToLoad();
		}

		if (array_key_exists('result', $data)) {
			foreach ($data['result'] as $result) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//tbody//td[2]//a[text()="'.$result.'"]'));
				$this->zbxTestAssertElementPresentXpath('//tbody//td[2]//a[text()="'.$result.'"]');
			}
		}
		else {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//tr[@class="nothing-to-show"]'));
			$this->zbxTestAssertElementText('//tr[@class="nothing-to-show"]/td', 'No data found.');
		}
	}
}
