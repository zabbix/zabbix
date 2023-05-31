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

use Facebook\WebDriver\Exception\TimeoutException;

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

define('HOST_WIDGET', ['id' => 'search_hosts_widget', 'title' => 'Hosts']);
define('HOST_GROUP_WIDGET', ['id' => 'search_hostgroup_widget', 'title' => 'Host groups']);
define('TEMPLATE_WIDGET', ['id' => 'search_templates_widget', 'title' => 'Templates']);

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageSearch extends CWebTest {

	use TableTrait;

	public static function prepareData() {
		CDataHelper::createHosts([
			[
				'host' => 'emoji visible name',
				'name' => '🙂⭐️',
				'groups' => [
					'groupid' => '6'
				],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '99.99.99.99',
					'dns' => '',
					'port' => '10050'
				]
			],
			[
				'host' => STRING_128,
				'groups' => [
					'groupid' => '6'
				],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 0,
					'ip' => '127.0.0.1',
					'dns' => 'testdns.example.com',
					'port' => '10050'
				]
			],
			[
				'host' => 'iGnoRe CaSe',
				'name' => 'ZaBbiX зАБбИкс āēīõšŗ',
				'groups' => [
					'groupid' => '6'
				],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				]
			]
		]);


	}

	public static function getSearchData() {
		return [
			[
				[
					'search_string' => 'Non-existent host',
					'host_expected_count' => 0,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'ЗАББИКС Сервер',
					'host_expected_data' => [['Host' => 'ЗАББИКС Сервер'], ['IP' => '127.0.0.1'], ['DNS' => '']],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'Zabbix servers',
					'host_expected_count' => 0,
					'hgroup_expected_data' => [['Host group' => 'Zabbix servers']],
					'hgroup_expected_count' => 1,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'Form test template',
					'host_expected_count' => 0,
					'hgroup_expected_count' => 0,
					'template_expected_data' => [['Template' => 'Form test template']],
					'template_expected_count' => 1
				]
			],
			[
				[
					'search_string' => '⭐️',
					'host_expected_data' => [['Host' => '🙂⭐️']],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'emoji visible name',
					'host_expected_data' => [['Host' => "🙂⭐️\n(emoji visible name)"]],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'ZABBIX ЗАББИКС ĀĒĪÕŠŖ',
					'host_expected_data' => [['Host' => "ZaBbiX зАБбИкс āēīõšŗ"]],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'ignore case',
					'host_expected_data' => [['Host' => "ZaBbiX зАБбИкс āēīõšŗ\n(iGnoRe CaSe)"]],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => STRING_128,
					'host_expected_data' => [['Host' => STRING_128]],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'a',
					'host_expected_count' => 37,
					'hgroup_expected_count' => 28,
					'template_expected_count' => 234
				]
			],
			[
				[
					'search_string' => '99.99.99.99',
					'host_expected_data' => [['Host' => '🙂⭐️'], ['IP' => '99.99.99.99'], ['DNS' => '']],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => '127.0.0.1',
					'host_expected_count' => 44,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'testdns.example.com',
					'host_expected_data' => [['Host' => STRING_128], ['IP' => '127.0.0.1'], ['DNS' => 'testdns.example.com']],
					'host_expected_count' => 1,
					'hgroup_expected_count' => 0,
					'template_expected_count' => 0
				]
			]
		];
	}

	/**
	 * Search for an existing Host and check the results page.
	 *
	 * @dataProvider getSearchData
	 */
	public function testPageSearch_VerifyResults($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();
		$form->query('id:search')->one()->fill($data['search_string']);
		$form->submit();

		$title = $this->query('id:page-title-general')->waitUntilVisible()->one()->getText();
		$this->assertEquals('Search: '.$data['search_string'], $title);

		$this->verifySearchResultWidget(HOST_WIDGET, $data['host_expected_data'] ?? null, $data['host_expected_count'] ?? null);
		$this->verifySearchResultWidget(HOST_GROUP_WIDGET, $data['hgroup_expected_data'] ?? null, $data['hgroup_expected_count'] ?? null);
		$this->verifySearchResultWidget(TEMPLATE_WIDGET, $data['template_expected_data'] ?? null, $data['template_expected_count'] ?? null);
	}

	public static function getSuggestionsData()
	{
		return [
			[
				[
					'search_string' => 'Non-existent host',
					'expected_suggestions' => [],
					'expected_count' => 0
				]
			],
			[
				[
					'search_string' => 'Test host',
					'expected_suggestions' => [
						'Simple form test host',
						'Template inheritance test host',
						'Visible host for template linkage',
						'ЗАББИКС Сервер'
					],
					'expected_count' => 4
				]
			],
			[
				[
					'search_string' => 'a',
					'expected_count' => 15
				]
			],
			[
				[
					'search_string' => ' ',
					'expected_count' => 15
				]
			],
			[
				[
					'search_string' => '⭐️',
					'expected_suggestions' => ['🙂⭐️'],
					'expected_count' => 1
				]
			],
			[
				[
					'search_string' => 'ignore case',
					'expected_suggestions' => ['ZaBbiX зАБбИкс āēīõšŗ'],
					'expected_count' => 1
				]
			],
			[
				[
					'search_string' => 'ZABBIX ЗАББИКС ĀĒĪÕŠŖ',
					'expected_suggestions' => ['ZaBbiX зАБбИкс āēīõšŗ'],
					'expected_count' => 1
				]
			],
			[
				[
					'search_string' => STRING_128,
					'expected_suggestions' => [STRING_128],
					'expected_count' => 1
				]
			]
		];
	}

	/**
	 * Fill the Search input and verify that autocomplete shows the correct suggestions.
	 *
	 * @dataProvider getSuggestionsData
	 */
	public function testPageSearch_VerifySearchSuggestions($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();
		$form->query('id:search')->one()->fill($data['search_string']);

		$itemSelector = 'xpath://ul[@class="search-suggest"]//li';

		// Verify suggestions.
		if (isset($data['expected_suggestions'])) {
			if(count($data['expected_suggestions']) > 0) {
				$items = $this->query($itemSelector)->waitUntilVisible()->all()->asText();
				foreach ($items as $item){
					if(in_array($item, $data['expected_suggestions'])) {
						// Remove item from the expected result array.
						unset($data['expected_suggestions'][array_search($item, $data['expected_suggestions'])]);
					}
					else{
						throw new Exception("Unexpected search suggestion: ".$item);
					}
				}
				if(count($data['expected_suggestions']) > 0) {
					throw new Exception("Not all expected search suggestions shown. Missing: ".
						implode(', ', $data['expected_suggestions']));
				}
			}
			else {
				$this->verifyThatSuggestionsNotShow();
			}
		}

		// Verify suggestion total count.
		if (isset($data['expected_count'])) {
			if ($data['expected_count'] > 0) {
				$this->assertEquals($data['expected_count'], $this->query($itemSelector)->waitUntilVisible()->all()->count());
			}
			else {
				$this->verifyThatSuggestionsNotShow();
			}
		}

	}

	/**
	 * Test if the global search form is not being submitted with empty search string.
	 */
	public function testPageSearch_FindEmptyString() {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();

		// Do not search if the search field is empty.
		$form->query('id:search')->one()->fill('');
		$form->submit();
		$this->page->assertTitle('Dashboard');
		$this->assertEquals('Global view', $this->query('tag:h1')->waitUntilVisible()->one()->getText());

		// Do not search if search string consists only of whitespace characters.
		$form->query('id:search')->one()->fill('   ');
		$form->submit();
		$this->page->assertTitle('Dashboard');
		$this->assertEquals('Global view', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
	}

	/**
	 * Asserts that a Search result widget contains the expected values.
	 *
	 * @param $widgetParams			array of witget parameters
	 * @param $expectedTableData	expected table data as an array or a string
	 * @param $expectedCount		expected total count at the footer
	 */
	private function verifySearchResultWidget($widgetParams, $expectedTableData, $expectedCount) {
		$widgetSelector = 'xpath://div[@id='.CXPathHelper::escapeQuotes($widgetParams['id']).']';
		$widget = $this->query($widgetSelector)->one();
		$this->assertEquals($widgetParams['title'],	$widget->query('xpath:.//h4')->one()->getText());

		// Check table data or that the 'No data found' string is present.
		if (isset($expectedTableData)) {
			$this->assertTableHasData($expectedTableData,$widgetSelector.'//table');
		}
		elseif ($expectedCount === 0) {
			$tableText = $widget->query('xpath:.//table//td')->one()->getText();
			$this->assertEquals('No data found.', $tableText);
		}

		// Check shown and total count display.
		if ($expectedCount !== null) {
			$footerText =  $widget->query('xpath:.//ul[@class="dashbrd-widget-foot"]//li')->one()->getText();
			$this->assertEquals('Displaying '.(min($expectedCount, 100)).' of '.$expectedCount.' found', $footerText);
		}
	}

	/**
	 * Verify that the suggestion list is NOT visible.
	 */
	private function verifyThatSuggestionsNotShow() {
		try {
			$this->query('class:search-suggest')->waitUntilVisible(1);
		} catch (TimeoutException $e) {
			// All good, the suggestion list is not visible, continue the test.
		}
	}
}
