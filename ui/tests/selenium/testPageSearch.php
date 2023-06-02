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
 * @backup hstgrp
 *
 * @onBefore prepareData
 */
class testPageSearch extends CWebTest {

	use TableTrait;

	private static $search_string = 'Test object';

	private static $widget_params = [
		'hosts' => [
			'key' => 'hosts',
			'selector_id' => 'search_hosts_widget',
			'title' => 'Hosts',
			'columns' => [
				['name' => 'Host', 'skip_text_check' => true, 'href' => 'zabbix.php?action=host.edit'],
				['name' => 'IP', 'skip_text_check' => true],
				['name' => 'DNS', 'skip_text_check' => true],
				['name' => 'Latest data', 'href' => 'zabbix.php?action=latest.view'],
				['name' => 'Problems', 'href' => 'zabbix.php?action=problem.view'],
				['name' => 'Graphs', 'href' => 'zabbix.php?action=charts.view'],
				['name' => 'Dashboards', 'href' => 'zabbix.php?action=host.dashboard.view'],
				['name' => 'Web', 'href' => 'zabbix.php?action=web.view'],
				['name' => 'Items', 'href' => 'items.php?filter_set=1'],
				['name' => 'Triggers', 'href' => 'triggers.php?filter_set=1'],
				['name' => 'Graphs', 'href' => 'graphs.php?filter_set=1'],
				['name' => 'Discovery', 'href' => 'host_discovery.php?filter_set=1'],
				['name' => 'Web', 'href' => 'httpconf.php?filter_set=1']
			],
			'column_names' => ['Host', 'IP', 'DNS', 'Monitoring', 'Configuration']
		],
		'hostgroups' => [
			'key' => 'host_groups',
			'selector_id' => 'search_hostgroup_widget',
			'title' => 'Host groups',
			'columns' => [
				['name' => 'Host group', 'skip_text_check' => true, 'href' => 'hostgroups.php?form=update'],
				['name' => 'Latest data', 'href' => 'zabbix.php?action=latest.view'],
				['name' => 'Problems', 'href' => 'zabbix.php?action=problem.view'],
				['name' => 'Web', 'href' => 'zabbix.php?action=web.view'],
				['name' => 'Hosts', 'href' => 'zabbix.php?action=host.list'],
				['name' => 'Templates', 'href' => 'templates.php?filter_set=1']
			],
			'column_names' => ['Host group', 'Monitoring', 'Configuration']
		],
		'templates' => [
			'key' => 'templates',
			'selector_id' => 'search_templates_widget',
			'title' => 'Templates',
			'columns' => [
				['name' => 'Template', 'skip_text_check' => true, 'href' => 'templates.php?form=update'],
				['name' => 'Items', 'href' => 'items.php?filter_set=1'],
				['name' => 'Triggers', 'href' => 'triggers.php?filter_set=1'],
				['name' => 'Graphs', 'href' => 'graphs.php?filter_set=1'],
				['name' => 'Dashboards', 'href' => 'zabbix.php?action=template.dashboard.list'],
				['name' => 'Discovery', 'href' => 'host_discovery.php?filter_set=1'],
				['name' => 'Web', 'href' => 'httpconf.php?filter_set=1&filter_hostids']
			],
			'column_names' => ['Template', 'Configuration']
		]
	];

	public static function prepareData() {
		// This is needed so that all links in Search results are active.
		$hostGroupId = CDataHelper::call('hostgroup.create', [['name' => self::$search_string.' Hostgroup']])['groupids'][0];

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
			],
			[
				'host' => self::$search_string.' Host',
				'groups' => [
					'groupid' => $hostGroupId
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

		CDataHelper::createTemplates([
			[
				'host' => self::$search_string.' Template',
				'groups' => [
					'groupid' => $hostGroupId
				]
			]
		]);
	}

	/**
	 * Check layout of the Search form and result page.
	 */
	public function testPageSearch_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();

		$search_field = $form->getField('id:search');
		$search_button = $form->query('tag:button')->one();
		$this->assertEquals(255, $search_field->getAttribute('maxlength'));
		$this->assertEquals('off', $search_field->getAttribute('autocomplete'));
		$this->assertEquals('true', $search_button->getAttribute('disabled'));
		$this->verifyThatSuggestionsNotShown();

		// Check suggestion highlighting.
		$search_field->fill(self::$search_string);
		$this->assertEquals(null, $search_button->getAttribute('disabled'));
		$highlighted_text = $this->query('xpath://ul[@class="search-suggest"]//span[@class="suggest-found"]')->waitUntilVisible()->one()->getText();
		$this->assertEquals(strtolower(self::$search_string), strtolower($highlighted_text));

		// Check that suggestions disappear after deleting input.
		$search_field->fill('');
		$this->verifyThatSuggestionsNotShown();
		$this->assertEquals('true', $search_button->getAttribute('disabled'));

		$search_field->fill(self::$search_string);
		$search_button->waitUntilReady()->click();
		$this->assertEquals('Search: '.self::$search_string, $this->query('id:page-title-general')->waitUntilVisible()->one()->getText());

		// Assert result widget layout.
		foreach (self::$widget_params as $wp) {
			$widget_selector = 'xpath://div[@id='.CXPathHelper::escapeQuotes($wp['selector_id']).']';
			$widget = $this->query($widget_selector)->one();
			$this->assertEquals($wp['title'], $widget->query('xpath:.//h4')->one()->getText());

			// Check column names.
			$this->assertEquals($wp['column_names'], $this->query($widget_selector.'//table//th')->all()->asText());

			// Check table links.
			$table_first_row = $widget->query('xpath:.//table')->asTable()->one()->getRow(0);
			foreach ($wp['columns'] as $col_num => $column){
				if (isset($column['href'])) {
					// The same column name is sometimes used twice so need to access by index.
					$link = $table_first_row->query('xpath:./td['.($col_num + 1).']//a')->one();
					// Link text matches the column name the vast majority of time.
					if (!(isset($column['skip_text_check']) && $column['skip_text_check'])) {
						$this->assertEquals($column['name'], $link->getText());
					}
					$this->assertStringContainsString($column['href'], $link->getAttribute('href'));
				}
			}

			// Check expanding functionality.
			$widget_body = $widget->query('class:body')->one();
			$collapse_button = $widget->query('class:btn-widget-collapse')->one();
			$this->assertEquals('Collapse', $collapse_button->getAttribute('title'));

			$collapse_button->click();
			$widget_body->waitUntilNotVisible();
			$this->assertFalse($widget_body->isDisplayed());
			$this->assertEquals('Expand', $collapse_button->getAttribute('title'));

			$expand_button = $widget->query('class:btn-widget-expand')->one();
			$expand_button->click();
			$widget_body->waitUntilVisible();
			$this->assertTrue($widget_body->isDisplayed());
		}
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
					'hosts' => [['Host' => 'ЗАББИКС Сервер', 'IP' => '127.0.0.1', 'DNS' => '']]
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
					'count_from_db' => true
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
					'count_from_db' => true
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
		// Get expected result count from DB.
		if (CTestArrayHelper::get($data, 'count_from_db')) {
			$template_sql = 'SELECT NULL FROM hosts WHERE LOWER(host) LIKE \'%'.$data['search_string'].'%\' AND status=3';
			$hostgroup_sql = 'SELECT NULL FROM hstgrp WHERE LOWER(name) LIKE \'%'.$data['search_string'].'%\'';
			$host_sql = 'SELECT DISTINCT(h.host) FROM hosts h INNER JOIN interface i on i.hostid=h.hostid '.
				'WHERE h.status=0 AND h.flags=0 AND (LOWER(h.host) LIKE \'%'.$data['search_string'].'%\' OR LOWER(h.name) LIKE \'%'.$data['search_string'].'%\''.
				'OR i.dns LIKE \'%'.$data['search_string'].'%\' OR i.ip LIKE \'%'.$data['search_string'].'%\')';

			$db_count = [];
			foreach (['hosts' => $host_sql, 'host_groups' => $hostgroup_sql, 'templates' => $template_sql] as $type => $sql) {
				$db_count[$type] = CDBHelper::getCount($sql);
			}
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();
		$form->query('id:search')->one()->fill($data['search_string']);
		$form->submit();

		$title = $this->query('id:page-title-general')->waitUntilVisible()->one()->getText();
		$this->assertEquals('Search: '.$data['search_string'], $title);

		// Verify each widget type.
		foreach (self::$widget_params as $wp) {
			$widget_selector = 'xpath://div[@id='.CXPathHelper::escapeQuotes($wp['selector_id']).']';
			$widget = $this->query($widget_selector)->one();

			// Assert table data.
			$expected_count = 0;
			if (isset($data[$wp['key']])) {
				$this->assertTableHasData($data[$wp['key']],$widget_selector.'//table');
				$expected_count = count($data[$wp['key']]);
			}
			elseif (isset($data[$wp['key'].'_count'])) {
				$expected_count = $data[$wp['key'].'_count'];
			}
			elseif (CTestArrayHelper::get($data, 'count_from_db', false)) {
				$expected_count = $db_count[$wp['key']];
			}
			else {
				$this->assertTableData(null, $widget_selector.'//table');
			}

			// Assert table stats.
			$footer_text =  $widget->query('xpath:.//ul[@class="dashboard-widget-foot"]//li')->one()->getText();
			$this->assertEquals('Displaying '.(min($expected_count, 100)).' of '.$expected_count.' found', $footer_text);
		}
	}

	public static function getSuggestionsData() {
		return [
			[
				[
					'search_string' => 'Non-existent host',
					'expected_suggestions' => []
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
					]
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
					'expected_suggestions' => ['🙂⭐️']
				]
			],
			[
				[
					'search_string' => 'ignore case',
					'expected_suggestions' => ['ZaBbiX зАБбИкс āēīõšŗ']
				]
			],
			[
				[
					'search_string' => 'ZABBIX ЗАББИКС ĀĒĪÕŠŖ',
					'expected_suggestions' => ['ZaBbiX зАБбИкс āēīõšŗ']
				]
			],
			[
				[
					'search_string' => STRING_128,
					'expected_suggestions' => [STRING_128]
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

		// Verify suggestions or the total count of suggestions.
		if (isset($data['expected_suggestions'])) {
			if (count($data['expected_suggestions']) > 0) {
				$items = $this->query($item_selector)->waitUntilVisible()->all()->asText();
				$this->assertEquals($data['expected_suggestions'], array_values($items));
			}
			else {
				$this->verifyThatSuggestionsNotShown();
			}
		}
		else {
			$this->assertEquals($data['expected_count'], $this->query($item_selector)->waitUntilVisible()->all()->count());
		}
	}

	/**
	 * Test if the global search form is not being submitted with empty search string.
	 */
	public function testPageSearch_FindEmptyString() {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();

		foreach (['', '   '] as $search_string) {
			$form->query('id:search')->one()->fill($search_string);
			$form->submit();
			$this->page->assertHeader('Global view');
		}
	}

	/**
	 * Verify that the suggestion list is NOT visible.
	 */
	private function verifyThatSuggestionsNotShown() {
		try {
			$this->query('class:search-suggest')->waitUntilVisible(1);
		}
		catch (TimeoutException $e) {
			// All good, the suggestion list is not visible, continue the test.
		}
	}
}
