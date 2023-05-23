<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

define('HOST_WIDGET', ['id' => 'search_hosts_widget', 'title' => 'Hosts']);
define('HOST_GROUP_WIDGET', ['id' => 'search_hostgroup_widget', 'title' => 'Host groups']);
define('TEMPLATE_WIDGET', ['id' => 'search_templates_widget', 'title' => 'Templates']);

class testPageSearch extends CWebTest {

	use TableTrait;

	/**
	 * Search for an existing Host and check the results page.
	 */
	public function testPageSearch_SearchHost() {
		$this->openSearchResults('ЗАББИКС Сервер');
		$title = $this->query('id:page-title-general')->waitUntilVisible()->one()->getText();
		$this->assertEquals('Search: ЗАББИКС Сервер', $title);

		$expectedTableData = [['Host' => 'ЗАББИКС Сервер'], ['IP' => '127.0.0.1'], ['DNS' => '']];

		$this->verifySearchResultWidget(HOST_WIDGET, $expectedTableData, 1, 1);
		$this->verifySearchResultWidget(HOST_GROUP_WIDGET, "No data found.", 0, 0);
		$this->verifySearchResultWidget(TEMPLATE_WIDGET, "No data found.", 0, 0);
	}

	/**
	 * Opens Zabbix Dashboard, searches by search string and opens the page.
	 */
	private function openSearchResults($searchString) {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->asForm()->one()->waitUntilVisible();
		$form->query('id:search')->one()->fill($searchString);
		$form->submit();
	}

	/**
	 * Asserts that a Search result widget contains the expected values.
	 *
	 * @param $widgetParams			array of witget parameters
	 * @param $expectedTableData	expected table data as an array or a string
	 * @param $countShown			expected shown count in widget footer
	 * @param $countTotal			expected total count in widget footer
	 */
	private function verifySearchResultWidget($widgetParams, $expectedTableData = null, $countShown = null, $countTotal = null){
		$this->assertEquals($widgetParams['title'],
			$this->query('xpath://*[@id="'.$widgetParams['id'].'"]//h4')->one()->getText());
		if ($expectedTableData) {
			if(is_array($expectedTableData)){
				$this->assertTableHasData($expectedTableData,'xpath://div[@id="'.$widgetParams['id'].'"]//table');
			}else{
				$tableText = $this->query('xpath://*[@id="'.$widgetParams['id'].'"]//td')->one()->getText();
				$this->assertEquals($expectedTableData, $tableText);
			}
		}
		if ($countShown !== null && $countTotal !== null) {
			$footerText = $this->query('xpath://*[@id="'.$widgetParams['id'].'"]//ul[@class="dashbrd-widget-foot"]//li')->one()->getText();
			$this->assertEquals('Displaying '.$countShown.' of '.$countTotal.' found', $footerText);
		}
	}

	public function testPageSearch_FindNotExistingHost() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestInputTypeWait('search', 'Not existing host');
		$this->zbxTestClickXpath('//button[@class="search-icon"]');
		$this->zbxTestCheckTitle('Search');
		$this->zbxTestCheckHeader('Search: Not existing host');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('No data found.');
		$this->zbxTestTextNotPresent('Zabbix server');
	}

	/**
	 * Test if the global search form is not being submitted with empty search string.
	 */
	public function testPageSearch_FindEmptyString() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');

		// Do not search if the search field is empty.
		$this->zbxTestInputTypeWait('search', '');
		$this->zbxTestClickXpath('//button[@class="search-icon"]');
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Global view');

		// Do not search if search string consists only of whitespace characters.
		$this->zbxTestInputTypeWait('search', '   ');
		$this->zbxTestClickXpath('//button[@class="search-icon"]');
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Global view');
	}
}
