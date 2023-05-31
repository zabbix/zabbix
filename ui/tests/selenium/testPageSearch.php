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
					'search_string' => 'Non-existent host'
				]
			],
			[
				[
					'search_string' => 'ЗАББИКС Сервер',
					'hosts' => [['Host' => 'ЗАББИКС Сервер', 'IP' => '127.0.0.1', 'DNS' => '']],
					'check_title' => true
				]
			],
			[
				[
					'search_string' => 'Zabbix servers',
					'host_groups' => [['Host group' => 'Zabbix servers']]
				]
			],
			[
				[
					'search_string' => 'Form test template',
					'templates' => [['Template' => 'Form test template']]
				]
			],
			[
				[
					'search_string' => '⭐️',
					'hosts' => [['Host' => '🙂⭐️']]
				]
			],
			[
				[
					'search_string' => 'emoji visible name',
					'hosts' => [['Host' => "🙂⭐️\n(emoji visible name)"]]
				]
			],
			[
				[
					'search_string' => 'ZABBIX ЗАББИКС ĀĒĪÕŠŖ',
					'hosts' => [['Host' => 'ZaBbiX зАБбИкс āēīõšŗ']]
				]
			],
			[
				[
					'search_string' => 'ignore case',
					'hosts' => [['Host' => "ZaBbiX зАБбИкс āēīõšŗ\n(iGnoRe CaSe)"]]
				]
			],
			[
				[
					'search_string' => STRING_128,
					'hosts' => [['Host' => STRING_128]]
				]
			],
			[
				[
					'search_string' => 'a',
					'hosts_count' => 37,
					'host_groups_count' => 28,
					'templates_count' => 234
				]
			],
			[
				[
					'search_string' => '99.99.99.99',
					'hosts' => [['Host' => '🙂⭐️', 'IP' => '99.99.99.99', 'DNS' => '']]

				]
			],
			[
				[
					'search_string' => '127.0.0.1',
					'hosts_count' => 44
				]
			],
			[
				[
					'search_string' => 'testdns.example.com',
					'hosts' => [['Host' => STRING_128, 'IP' => '127.0.0.1', 'DNS' => 'testdns.example.com']]
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
		static $widget_params = [
			'hosts' => [
				'key' => 'hosts',
				'selector_id' => 'search_hosts_widget',
				'title' => 'Hosts'
			],
			'hostgroups' => [
				'key' => 'host_groups',
				'selector_id' => 'search_hostgroup_widget',
				'title' => 'Host groups'
			],
			'templates' => [
				'key' => 'templates',
				'selector_id' => 'search_templates_widget',
				'title' => 'Templates'
			]
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();
		$form->query('id:search')->one()->fill($data['search_string']);
		$form->submit();

		$title = $this->query('id:page-title-general')->waitUntilVisible()->one()->getText();
		$this->assertEquals('Search: '.$data['search_string'], $title);

		// Verify each widget type.
		foreach ($widget_params as $wp) {
			$widget_selector = 'xpath://div[@id='.CXPathHelper::escapeQuotes($wp['selector_id']).']';
			$widget = $this->query($widget_selector)->one();

			if(CTestArrayHelper::get($data, 'check_title')) {
				$this->assertEquals($wp['title'], $widget->query('xpath:.//h4')->one()->getText());
			}

			$expected_count = 0;
			if (isset($data[$wp['key']])) {
				$this->assertTableHasData($data[$wp['key']],$widget_selector.'//table');
				$expected_count = count($data[$wp['key']]);
			}
			elseif (isset($data[$wp['key'].'_count'])) {
				$expected_count = $data[$wp['key'].'_count'];
			}
			$footer_text =  $widget->query('xpath:.//ul[@class="dashbrd-widget-foot"]//li')->one()->getText();
			$this->assertEquals('Displaying '.(min($expected_count, 100)).' of '.$expected_count.' found', $footer_text);

			if ($expected_count === 0) {
				$table_text = $widget->query('xpath:.//table//td')->one()->getText();
				$this->assertEquals('No data found.', $table_text);
			}
		}
	}

	public static function getSuggestionsData() {
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

		$item_selector = 'xpath://ul[@class="search-suggest"]//li';

		// Verify suggestions.
		if (isset($data['expected_suggestions'])) {
			if (count($data['expected_suggestions']) > 0) {
				$items = $this->query($item_selector)->waitUntilVisible()->all()->asText();
				foreach ($items as $item) {
					if (in_array($item, $data['expected_suggestions'])) {
						// Remove item from the expected result array.
						unset($data['expected_suggestions'][array_search($item, $data['expected_suggestions'])]);
					}
					else {
						throw new Exception("Unexpected search suggestion: ".$item);
					}
				}
				if (count($data['expected_suggestions']) > 0) {
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
				$this->assertEquals($data['expected_count'], $this->query($item_selector)->waitUntilVisible()->all()->count());
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
	 * Verify that the suggestion list is NOT visible.
	 */
	private function verifyThatSuggestionsNotShow() {
		try {
			$this->query('class:search-suggest')->waitUntilVisible(1);
		}
		catch (TimeoutException $e) {
			// All good, the suggestion list is not visible, continue the test.
		}
	}
}
