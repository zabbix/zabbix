<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/**
 * @backup problem
 */
class testPageOverview extends CLegacyWebTest {
	// Check that no real host or template names displayed
	public function testPageOverview_NoHostNames() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('Overview');
		$this->zbxTestCheckNoRealHostnames();
	}

	public function getFilterData() {
		return [
			// Overview check with type 'Triggers'.
			[
				[
					'main_filter' => [
						'groupid' => 'all',
						'type' => 'Triggers',
						'view_style' => 'Left'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview',
						'4_Host_to_check_Monitoring_Overview', 'Host for triggers filtering'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High',
						'1_trigger_Not_classified', '1_trigger_Warning', '2_trigger_Information',
						'3_trigger_Average', '4_trigger_Average', 'Inheritance trigger with tags'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Another group to check Overview',
						'type' => 'Triggers'
					],
					'result_hosts' => [
						'4_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'4_trigger_Average'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers',
						'view_style' => 'Top'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Not_classified', '1_trigger_Warning', '1_trigger_Average', '1_trigger_High',
						'1_trigger_Disaster', '2_trigger_Information', '3_trigger_Average'
					]
				]
			],
			// Severity option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers',
						'view_style' => 'Left'
					],
					'show_severity' => 'Information',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Warning', '1_trigger_Average', '1_trigger_High',	'1_trigger_Disaster',
						'2_trigger_Information', '3_trigger_Average'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers',
						'view_style' => 'Top'
					],
					'show_severity' => 'Warning',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Warning', '1_trigger_Average', '1_trigger_High', '1_trigger_Disaster',
						'3_trigger_Average'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'show_severity' => 'Average',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_High', '1_trigger_Disaster', '3_trigger_Average'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'show_severity' => 'High',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_High', '1_trigger_Disaster'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'show_severity' => 'Disaster',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Disaster'
					]
				]
			],
			// Application option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'applications' => [
						'app_group' => 'Group to check Overview',
						'app_host' => '1_Host_to_check_Monitoring_Overview',
						'application' => '1 application'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Not_classified', '1_trigger_Warning', '1_trigger_Average', '1_trigger_High',
						'1_trigger_Disaster'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'applications' => [
						'application' => '2 application'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'2_trigger_Information'
					]
				]
			],
			// Name option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'name' => 'Warning',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Warning'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'name' => '2_',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'2_trigger_Information'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'name' => 'Trigger-map-test-zbx6840'
				]
			],
			// Show unacknowledged only option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'ack_status' => true,
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Not_classified', '1_trigger_Warning', '1_trigger_Average', '1_trigger_High',
						'1_trigger_Disaster'
					]
				]
			],
			// Host inventory option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'inventories' => [
						'inventory_field' => 'Notes',
						'inventory_value' => 'Notes'
					],
					'result_hosts' => [
						'3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'3_trigger_Average'
					]
				]
			],
			// Age less than option in filter.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'age' => '1'
				]
			],
			// All filter options.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'show_severity' => 'Average',
					'triggers_status' => 'Any',
					'applications' => [
						'application' => '3 application'
					],
					'name' => '3_',
					'age' => '365',
					'inventories' => [
						'inventory_field' => 'Notes',
						'inventory_value' => 'Notes'
					],
					'result_hosts' => [
						'3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'3_trigger_Average', '3_trigger_Disaster'
					]
				]
			],
			// Triggers status option in filter.
			// Make trigger in problem state.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'triggers_status' => 'Recent problems',
					'problem' => ['3_trigger_Disaster' => TRIGGER_VALUE_TRUE],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Not_classified',
						'1_trigger_Warning', '2_trigger_Information', '3_trigger_Average', '3_trigger_Disaster'
					]
				]
			],
			// This test case depends from previous case, trigger should be in problem state.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'triggers_status' => 'Problems',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Not_classified',
						'1_trigger_Warning', '2_trigger_Information', '3_trigger_Average', '3_trigger_Disaster'
					]
				]
			],
			// Make trigger in resolved state.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'triggers_status' => 'Problems',
					'problem' => ['3_trigger_Disaster' => TRIGGER_VALUE_FALSE],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Not_classified',
						'1_trigger_Warning', '2_trigger_Information', '3_trigger_Average'
					]
				]
			],
			// This test case depends from previous case, trigger should be resolved.
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Triggers'
					],
					'triggers_status' => 'Recent problems',
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' => [
						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Not_classified',
						'1_trigger_Warning', '2_trigger_Information', '3_trigger_Average', '3_trigger_Disaster'
					]
				]
			],
			// Overview check with type 'Data'.
			[
				[
					'main_filter' => [
						'groupid' => 'Another group to check Overview',
						'type' => 'Data',
						'view_style' => 'Top'
					],
					'result_hosts' => [
						'4_Host_to_check_Monitoring_Overview'
					],
					'result_items' => [
						'4_item'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Data',
						'view_style' => 'Left'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_items' => [
						'1_item', '2_item', '3_item'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Group to check Overview',
						'type' => 'Data'
					],
					'applications' => [
						'application' => '1 application'
					],
					'result_hosts' => [
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_items' => [
						'1_item'
					]
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'all',
						'type' => 'Data'
					],
					'applications' => [
						'app_group' => 'Group to check Overview',
						'app_host' => '3_Host_to_check_Monitoring_Overview',
						'application' => '3 application'
					],
					'result_hosts' => [
						'3_Host_to_check_Monitoring_Overview'
					],
					'result_items' => [
						'3_item'
					]
				]
			],
			// Show suppressed problems with type Triggers.
			[
				[
					'main_filter' => [
						'groupid' => 'Host group for suppression',
						'type' => 'Triggers'
					],
					'result_hosts' => [
						'Host for suppression'
					],
					'result_triggers' => [
						'Trigger_for_suppression'
					],
					'show_suppressed' => true
				]
			],
			// Do not show suppressed problems with type Triggers.
			[
				[
					'main_filter' => [
						'groupid' => 'Host group for suppression',
						'type' => 'Triggers'
					]
				]
			],
			// Check suppressed problems with type Data.
			[
				[
					'main_filter' => [
						'groupid' => 'Host group for suppression',
						'type' => 'Data'
					],
					'result_hosts' => [
						'Host for suppression'
					],
					'result_items' => [
						'Trapper_for_suppression'
					],
					'show_suppressed' => true
				]
			],
			[
				[
					'main_filter' => [
						'groupid' => 'Host group for suppression',
						'type' => 'Data'
					],
					'result_hosts' => [
						'Host for suppression'
					],
					'result_items' => [
						'Trapper_for_suppression'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageOverview_CheclFilterResults($data) {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestWaitForPageToLoad();

		// Main filter options.
		$fields = ['groupid', 'type', 'view_style'];
		foreach ($fields as $field) {
			if (array_key_exists($field, $data['main_filter'])) {
				$main_filter = $data['main_filter'];
				$this->zbxTestDropdownSelectWait($field, $main_filter[$field]);
				$this->zbxTestWaitForPageToLoad();
			}
			else {
				$main_filter[$field] = $this->zbxTestGetSelectedLabel($field);
			}
		}

		// Filter options.
		if (array_key_exists('triggers_status', $data)) {
			$this->zbxTestClickXpath('//ul[@id="show_triggers"]//label[text()="'.$data['triggers_status'].'"]');
		}

		if (array_key_exists('ack_status', $data)) {
			$this->zbxTestCheckboxSelect('ack_status', $data['ack_status']);
		}

		if (array_key_exists('show_severity', $data)) {
			$this->zbxTestDropdownSelect('show_severity', $data['show_severity']);
		}

		if (array_key_exists('age', $data)) {
			$this->zbxTestCheckboxSelect('status_change');
			$this->zbxTestInputTypeOverwrite('status_change_days', $data['age']);
		}

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('txt_select', $data['name']);
		}

		if (array_key_exists('applications', $data)) {
			if (!array_key_exists('app_group', $data['applications']) && !array_key_exists('app_host', $data['applications'])) {
				$this->zbxTestInputType('application', $data['applications']['application']);
			}
			else {
				$this->zbxTestClick('application_name');
				$this->zbxTestLaunchOverlayDialog('Applications');
				foreach ($data['applications'] as $key => $value) {
					switch ($key) {
						case 'app_group':
							$this->zbxTestClickXpathWait('//div[@id="overlay_dialogue"]//select[@name="groupid"]'.
									'//option[text()="'.$value.'"]');
							break;
						case 'app_host':
							$this->zbxTestClickXpathWait('//div[@id="overlay_dialogue"]//select[@name="hostid"]'.
									'//option[text()="'.$value.'"]');
							break;
						case 'application':
							$this->zbxTestClickLinkTextWait($value);
							break;
					}
				}
			}
		}

		if (array_key_exists('inventories', $data)) {
			foreach ($data['inventories'] as $key => $value) {
				switch ($key) {
					case 'inventory_field':
						$this->zbxTestDropdownSelect('inventory_0_field', $value);
						break;
					case 'inventory_value':
						$this->zbxTestInputType('inventory_0_value', $value);
						break;
				}
			}
		}

		if (array_key_exists('show_suppressed', $data)) {
			$this->zbxTestCheckboxSelect('show_suppressed', $data['show_suppressed']);
		}

		// Make trigger in problem or resolved state.
		if (array_key_exists('problem', $data)) {
			foreach ($data['problem'] as $trigger => $state) {
				CDBHelper::setTriggerProblem($trigger, $state);
			}
		}

		// Wait till table id will change after filter apply.
		$tabel_id = $this->zbxTestGetAttributeValue('//table[@class="list-table"]', 'id');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//table[@class="list-table"][not(@id="'.$tabel_id.'")]'));

		// Check  the result in frontend.
		$this->zbxTestDropdownAssertSelected('type', $main_filter['type']);
		$this->zbxTestDropdownAssertSelected('view_style', $main_filter['view_style']);
		if (!array_key_exists('result_hosts', $data)) {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//tr[@class="nothing-to-show"]'));
			$this->zbxTestAssertElementPresentXpath('//tr[@class="nothing-to-show"]/td[text()="No data found."]');
		}
		elseif ($main_filter['type'] === 'Triggers') {
			// Check output for host location as 'Top'.
			if ($main_filter['view_style'] === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Triggers"]');
				$this->checkResultsInTable($main_filter['view_style'], $data['result_hosts'], $data['result_triggers']);
			}
			// Check output for host location as 'Left'.
			else {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Hosts"]');
				$this->checkResultsInTable($main_filter['view_style'], $data['result_triggers'], $data['result_hosts']);
			}
		}
		elseif ($main_filter['type'] === 'Data') {
			if ($main_filter['view_style'] === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Items"]');
				$this->checkResultsInTable($main_filter['view_style'], $data['result_hosts'], $data['result_items']);
			}
			else {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Hosts"]');
				$this->checkResultsInTable($main_filter['view_style'], $data['result_items'], $data['result_hosts']);
			}

			// Suppressed trigger contains background color.
			if (array_key_exists('show_suppressed', $data)) {
				$this->zbxTestAssertElementPresentXpath('//table[@class="list-table"]//td[contains(@class, "-bg")]');
			}
		}
	}

	private function checkResultsInTable($location, $thead, $tbody) {
		foreach ($thead as $column) {
			$this->zbxTestAssertElementPresentXpath('//th//div[@class="vertical_rotation_inner"][text()="'.$column.'"]');
		}
		foreach ($tbody as $row) {
			if ($location === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//table[@class="list-table"]//th[1][text()="'.$row.'"]');
			}
			else {
				$this->zbxTestAssertElementPresentXpath('//table[@class="list-table"]//th[1]/a[text()="'.$row.'"]');
			}
		}

		// Count rows and columns to compare with expected number of results.
		$columns = $this->webDriver->findElements(WebDriverBy::xpath('//th//div[@class="vertical_rotation_inner"]'));
		$rows = $this->webDriver->findElements(WebDriverBy::xpath('//table[@class="list-table"]//tbody//th[@class="nowrap"]'));
		$this->assertEquals(count($thead), count($columns));
		$this->assertEquals(count($tbody), count($rows));
	}

	public function getMenuPopup() {
		return [
			[
				[
					'type' => 'Triggers',
					'links' => [
						'zabbix.php?action=problem.view&filter_triggerids',
						'action=acknowledge.edit&eventids',
						'triggers.php?form=update&triggerid',
						'action=showgraph&itemid'
					],
					'links_text' => ['Problems', 'Acknowledge', 'Description', 'Configuration', '1_item']
				]
			],
			[
				[
					'type' => 'Data',
					'links' => [
						'action=showgraph&to=now&from=now-1h',
						'action=showgraph&to=now&from=now-7d',
						'action=showgraph&to=now&from=now-1M',
						'action=showvalues&to=now&from=now-1h'
					],
					'links_text' => ['Last hour graph', 'Last week graph', 'Last month graph', 'Latest values']
				]
			]
		];
	}

	/**
	 * @dataProvider getMenuPopup
	 */
	public function testPageOverview_MenuPopupLinks($data) {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckHeader('Overview');
		$this->zbxTestClickButtonText('Reset');

		// Select group and type, then open context menu.
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestDropdownSelectWait('type', $data['type']);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickXpathWait('//tbody//td[contains(@class, "cursor-pointer")]');
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//ul[contains(@class, "menu-popup")]//a'));

		// Check context menu links text and url.
		$this->zbxTestAssertElementPresentXpath('//ul[contains(@class, "menu-popup")]//h3[text()="History"]');
		if ($data['type'] === 'Triggers') {
			$this->zbxTestAssertElementPresentXpath('//ul[contains(@class, "menu-popup")]//h3[text()="Trigger"]');
		}

		$get_links_text = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//ul[contains(@class, "menu-popup")]//a'));
		foreach ($elements as $element) {
			$get_links_text[] = $element->getText();
		}
		$this->assertEquals($data['links_text'], $get_links_text);

		foreach ($data['links'] as $link) {
			$this->zbxTestAssertElementPresentXpath('//ul[contains(@class, "menu-popup")]//a[contains(@href, "'.$link.'")]');
		}
	}

	public function testPageOverview_FullScreenKioskMode() {
		try {
			$this->zbxTestLogin('overview.php');
			$this->zbxTestCheckHeader('Overview');
			$this->zbxTestAssertElementPresentXpath("//header");
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-max')]", 'title', 'Fullscreen');

			$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-max')]");
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Kiosk mode"]'));
			$this->zbxTestCheckHeader('Overview');
			$this->zbxTestAssertElementNotPresentXpath("//header");
			$this->zbxTestAssertElementPresentXpath("//div[@class='header-title table']");
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="mmenu"][@class="top-nav-container"]');
			$this->zbxTestAssertElementNotPresentXpath('//nav[@class="top-subnav-container"]');
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');

			$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
			$this->zbxTestAssertElementNotPresentXpath("//header");
			$this->zbxTestAssertElementNotPresentXpath("//div[@class='header-title table']");
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

			$this->webDriver->executeScript('arguments[0].click();', [$this->webDriver->findElement(WebDriverBy::className('btn-min'))]);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-max')]"));
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-max')]", 'title', 'Fullscreen');
			$this->zbxTestAssertElementPresentXpath("//header");
			$this->zbxTestAssertElementPresentXpath("//div[@class='header-title table']");
		}
		catch (Exception $e) {
			// Reset fullscreen/kiosk mode.
			$this->zbxTestLogin('overview.php?fullscreen=0');
			$this->zbxTestCheckHeader('Overview');
			throw $e;
		}
	}
}
