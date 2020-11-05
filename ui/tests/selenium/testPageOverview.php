<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup problem
 */
class testPageOverview extends CLegacyWebTest {
	// Check that no real host or template names displayed
	public function testPageOverview_NoHostNames() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('Trigger overview');
		$this->zbxTestCheckNoRealHostnames();
	}

	public function getFilterData() {
		return [
			// Overview check with type 'Triggers'.
			[
				[
					'general_filter' => 'Trigger overview',
					'view_style' => 'Left',
					'filter' => [],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Another group to check Overview'
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
					'general_filter' => 'Trigger overview',
					'view_style' => 'Top',
					'filter' => [
						'Host groups' => 'Group to check Overview'
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
					'general_filter' => 'Trigger overview',
					'view_style' => 'Left',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'Information',
					],
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
					'general_filter' => 'Trigger overview',
					'view_style' => 'Top',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'Warning',
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'Average',
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'High',
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'Disaster',
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Application' => '1 application'
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Application' => '2 application'
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Name' => 'Warning'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Name' => '2_'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Name' => 'Trigger-map-test-zbx6840'
					]
				]
			],
			// Show unacknowledged only option in filter.
			[
				[
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Show unacknowledged only' => true
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
			// Host inventory option in filter.
			[
				[
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview'
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview'
					],
					'age' => '1'
				]
			],
			// All filter options.
			[
				[
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Minimum severity' => 'Average',
						'Show' => 'Any',
						'Application' => '3 application',
						'Name' => '3_'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Show' => 'Recent problems'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Show' => 'Problems'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Show' => 'Problems'
					],
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Show' => 'Recent problems'
					],
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
					'general_filter' => 'Data overview',
					'view_style' => 'Top',
					'filter' => [
						'Host groups' => 'Another group to check Overview'
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
					'general_filter' => 'Data overview',
					'view_style' => 'Left',
					'filter' => [
						'Host groups' => 'Group to check Overview'
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
					'general_filter' => 'Data overview',
					'filter' => [
						'Host groups' => 'Group to check Overview',
						'Application' => '1 application'
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
					'general_filter' => 'Data overview',
					'filter' => [
						'Application' => '3 application'
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
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Host group for suppression',
						'Show suppressed problems' => true
					],
					'result_hosts' => [
						'Host for suppression'
					],
					'result_triggers' => [
						'Trigger_for_suppression'
					]
				]
			],
			// Do not show suppressed problems with type Triggers.
			[
				[
					'general_filter' => 'Trigger overview',
					'filter' => [
						'Host groups' => 'Host group for suppression'
					]
				]
			],
			// Check suppressed problems with type Data.
			[
				[
					'general_filter' => 'Data overview',
					'filter' => [
						'Host groups' => 'Host group for suppression',
						'Show suppressed problems' => true
					],
					'result_hosts' => [
						'Host for suppression'
					],
					'result_items' => [
						'Trapper_for_suppression'
					]
				]
			],
			[
				[
					'general_filter' => 'Data overview',
					'filter' => [
						'Host groups' => 'Host group for suppression'
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
	public function testPageOverview_CheckFilterResults($data) {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestWaitForPageToLoad();

		$this->query('id:page-title-general')->asPopupButton()->one()->select($data['general_filter']);

		// Main filter options.
		if (array_key_exists('view_style', $data)) {
			$view_style = $data['view_style'];
			$this->zbxTestDropdownSelectWait('view_style', $view_style);
			$this->zbxTestWaitForPageToLoad();
		}
		else {
			$view_style = $this->zbxTestGetSelectedLabel('view_style');
		}
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->fill($data['filter']);

		if (array_key_exists('age', $data)) {
			$this->zbxTestCheckboxSelect('status_change');
			$this->zbxTestInputTypeOverwrite('status_change_days', $data['age']);
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
		$this->zbxTestCheckHeader($data['general_filter']);
		$this->zbxTestDropdownAssertSelected('view_style', $view_style);
		if (!array_key_exists('result_hosts', $data)) {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//tr[@class="nothing-to-show"]'));
			$this->zbxTestAssertElementPresentXpath('//tr[@class="nothing-to-show"]/td[text()="No data found."]');
		}
		elseif ($data['general_filter'] === 'Trigger overview') {
			// Check output for host location as 'Top'.
			if ($view_style === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Triggers"]');
				$this->checkResultsInTable($view_style, $data['result_hosts'], $data['result_triggers']);
			}
			// Check output for host location as 'Left'.
			else {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Hosts"]');
				$this->checkResultsInTable($view_style, $data['result_triggers'], $data['result_hosts']);
			}
		}
		elseif ($data['general_filter'] === 'Data') {
			if ($view_style === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Items"]');
				$this->checkResultsInTable($view_style, $data['result_hosts'], $data['result_items']);
			}
			else {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Hosts"]');
				$this->checkResultsInTable($view_style, $data['result_items'], $data['result_hosts']);
			}

			// Suppressed trigger contains background color.
			if (array_key_exists('Show suppressed problems', $data['filter'])) {
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
					'type' => 'Trigger overview',
					'links' => [
						'zabbix.php?action=problem.view&filter_name=&triggerids',
						'triggers.php?form=update&triggerid',
						'action=showgraph&itemid'
					],
					'links_text' => ['Problems', 'Acknowledge', 'Configuration', 'Trigger URL', 'Webhook url for all', '1_item']
				]
			],
			[
				[
					'type' => 'Data overview',
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
		$this->zbxTestLogin('overview.php?type=0');
		$this->zbxTestCheckHeader('Trigger overview');
		$this->query('button:Reset')->one()->click();
		// Select group and type, then open context menu.
		$this->query('id:page-title-general')->asPopupButton()->one()->select($data['type']);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestCheckHeader($data['type']);
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
			$this->zbxTestLogin('overview.php?type=0');
			$this->zbxTestCheckHeader('Trigger overview');
			$this->zbxTestAssertElementPresentXpath("//header");
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
			$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
			$this->zbxTestAssertElementNotPresentXpath("//header");
			$this->zbxTestAssertElementNotPresentXpath("//div[@class='header-title']");
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

			$this->query('class:btn-min')->one()->forceClick();
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-kiosk')]"));
			$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
			$this->zbxTestAssertElementPresentXpath("//header");
			$this->zbxTestAssertElementPresentXpath("//header[@class='header-title']");
		}
		catch (Exception $e) {
			// Reset fullscreen/kiosk mode.
			$this->zbxTestLogin('overview.php?fullscreen=0');
			$this->zbxTestCheckHeader('Trigger overview');
			throw $e;
		}
	}
}
