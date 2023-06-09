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

	protected $search_string = 'Test object';

	protected $widgets = [
		'hosts' => [
			'key' => 'hosts',
			'selector' => 'id:search_hosts_widget',
			'table_selector' => "xpath://div[@id='search_hosts_widget']//table",
			'title' => 'Hosts',
			'columns' => [
				['name' => 'Host', 'text' => 'Test object Host', 'href' => 'hosts.php?form=update&hostid={id}'],
				['name' => 'IP', 'text' => '127.0.0.1'],
				['name' => 'DNS', 'text' => 'testdnstwo.example.com'],
				['name' => 'Latest data', 'href' => 'zabbix.php?action=latest.view&filter_hostids%5B%5D={id}&filter_set=1'],
				['name' => 'Problems', 'href' => 'zabbix.php?action=problem.view&filter_hostids%5B%5D={id}&filter_set=1'],
				['name' => 'Graphs', 'href' => 'zabbix.php?action=charts.view&view_as=showgraph&filter_hostids%5B%5D={id}&filter_set=1'],
				['name' => 'Screens', 'href' => 'host_screen.php?hostid={id}'],
				['name' => 'Web', 'href' => 'zabbix.php?action=web.view&filter_hostids%5B%5D={id}&filter_set=1'],
				['name' => 'Applications', 'href' => 'applications.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Items', 'href' => 'items.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Triggers', 'href' => 'triggers.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Graphs', 'href' => 'graphs.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Discovery', 'href' => 'host_discovery.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Web', 'href' => 'httpconf.php?filter_set=1&filter_hostids%5B0%5D={id}']
			]
		],
		'hostgroups' => [
			'key' => 'host_groups',
			'selector' => 'id:search_hostgroup_widget',
			'table_selector' => "xpath://div[@id='search_hostgroup_widget']//table",
			'title' => 'Host groups',
			'columns' => [
				['name' => 'Host group', 'text' => 'Test object Hostgroup', 'href' => 'hostgroups.php?form=update&groupid={id}&hostid=0'],
				['name' => 'Latest data', 'href' => 'zabbix.php?action=latest.view&filter_groupids%5B%5D={id}&filter_set=1'],
				['name' => 'Problems', 'href' => 'zabbix.php?action=problem.view&filter_groupids%5B%5D={id}&filter_set=1'],
				['name' => 'Web', 'href' => 'zabbix.php?action=web.view&filter_groupids%5B%5D={id}&filter_set=1'],
				['name' => 'Hosts', 'text' => 'Hosts 1', 'href' => 'hosts.php?filter_set=1&filter_groups%5B0%5D={id}'],
				['name' => 'Templates', 'text' => 'Templates 1', 'href' => 'templates.php?filter_set=1&filter_groups%5B0%5D={id}']
			]
		],
		'templates' => [
			'key' => 'templates',
			'selector' => 'id:search_templates_widget',
			'table_selector' => "xpath://div[@id='search_templates_widget']//table",
			'title' => 'Templates',
			'columns' => [
				['name' => 'Template', 'text' => 'Test object Template', 'href' => 'templates.php?form=update&&templateid={id}'],
				['name' => 'Applications', 'href' => 'applications.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Items', 'href' => 'items.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Triggers', 'href' => 'triggers.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Graphs', 'href' => 'graphs.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Screens', 'href' => 'screenconf.php?templateid={id}'],
				['name' => 'Discovery', 'href' => 'host_discovery.php?filter_set=1&filter_hostids%5B0%5D={id}'],
				['name' => 'Web', 'href' => 'httpconf.php?filter_set=1&filter_hostids%5B0%5D={id}']
			]
		]
	];

	public function prepareData() {
		// This is needed so that all links in Search results are active. Also get IDs for checking links.
		$response = CDataHelper::call('hostgroup.create', [['name' => $this->search_string.' Hostgroup']]);
		$this->widgets['hostgroups']['link_id'] = $response['groupids'][0];

		$response = CDataHelper::createHosts([
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
				'host' => $this->search_string.' Host',
				'groups' => [
					'groupid' => $this->widgets['hostgroups']['link_id']
				],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'testdnstwo.example.com',
					'port' => '10050'
				]
			]
		]);
		$this->widgets['hosts']['link_id'] = $response['hostids'][$this->search_string.' Host'];

		$response = CDataHelper::createTemplates([
			[
				'host' => $this->search_string.' Template',
				'groups' => [
					'groupid' => $this->widgets['hostgroups']['link_id']
				]
			]
		]);
		$this->widgets['templates']['link_id'] = $response['templateids'][$this->search_string.' Template'];
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
		$this->assertFalse($search_button->isEnabled());
		$this->assertFalse($search_button->isClickable());
		$this->verifyThatSuggestionsNotShown();

		// Check suggestion highlighting.
		$search_field->fill($this->search_string);
		$this->assertTrue($search_button->isEnabled());
		$this->assertTrue($search_button->isClickable());
		$highlighted_text = $this->query('class:suggest-found')->waitUntilVisible()->one()->getText();
		$this->assertEquals(strtolower($this->search_string), strtolower($highlighted_text));

		// Check that suggestions disappear after deleting input.
		$search_field->fill('');
		$this->verifyThatSuggestionsNotShown();
		$this->assertFalse($search_button->isEnabled());
		$this->assertFalse($search_button->isClickable());

		$search_field->fill($this->search_string);
		$search_button->waitUntilClickable()->click();
		$this->assertEquals('Search: '.$this->search_string, $this->query('id:page-title-general')->waitUntilVisible()->one()->getText());

		// Assert result widget layout.
		foreach ($this->widgets as $widget_params) {
			$widget = $this->query($widget_params['selector'])->one();
			$this->assertEquals($widget_params['title'], $widget->query('xpath:.//h4')->one()->getText());

			// Check column names.
			$this->assertEquals(array_column($widget_params['columns'], 'name'),
					$this->query($widget_params['table_selector']."//th")->all()->asText()
			);

			// Check table links.
			$table_first_row = $widget->query('xpath:.//table')->asTable()->one()->getRow(0);

			foreach ($widget_params['columns'] as $i => $column) {
				// The same column name is sometimes used twice so need to access column by index.
				$column_element = $table_first_row->getColumn($i);

				// If no text specified, the column text should match its name.
				$this->assertEquals(CTestArrayHelper::get($column, 'text', $column['name']), $column_element->getText());

				if (isset($column['href'])) {
					// Check that the link href matches.
					$expected_href = str_replace('{id}', $widget_params['link_id'], $column['href']);
					$this->assertEquals($expected_href, $column_element->query('tag:a')->one()->getAttribute('href'));
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
			],
			[
				[
					'search_string' => 'st obj',
					'hosts' => [['Host' => 'Test object Host']],
					'host_groups' => [['Host group' => 'Test object Hostgroup']],
					'templates' => [['Template' => 'Test object Template']]
				]
			]
		];
	}

	/**
	 * Search for a string and verify the results.
	 *
	 * @dataProvider getSearchData
	 */
	public function testPageSearch_VerifyResults($data) {
		// Get expected result count from DB.
		if (CTestArrayHelper::get($data, 'count_from_db')) {
			$template_sql = 'SELECT NULL FROM hosts WHERE LOWER(host) LIKE \'%'.$data['search_string'].'%\' AND status=3';
			$hostgroup_sql = 'SELECT NULL FROM hstgrp WHERE LOWER(name) LIKE \'%'.$data['search_string'].'%\'';
			$host_sql = 'SELECT DISTINCT(h.host) FROM hosts h INNER JOIN interface i on i.hostid=h.hostid '.
				'WHERE h.status in (0,1) AND h.flags=0 AND (LOWER(h.host) LIKE \'%'.$data['search_string'].'%\' '.
				'OR LOWER(h.name) LIKE \'%'.$data['search_string'].'%\' OR i.dns LIKE \'%'.$data['search_string'].'%\' '.
				'OR i.ip LIKE \'%'.$data['search_string'].'%\')';

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
		foreach ($this->widgets as $widget_params) {
			$widget = $this->query($widget_params['selector'])->one();

			// Assert table data, but only if count from DB is not set.
			if (!CTestArrayHelper::get($data, 'count_from_db')) {
				$this->assertTableData(($data[$widget_params['key']] ?? []), $widget_params['table_selector']);
			}

			// Assert table stats.
			$expected_count = CTestArrayHelper::get($data, 'count_from_db') ? $db_count[$widget_params['key']] :
					(isset($data[$widget_params['key']]) ? count($data[$widget_params['key']]) : 0);
			$footer_text = $widget->query('xpath:.//ul[@class="dashbrd-widget-foot"]//li')->one()->getText();
			// Only a maximum of 100 records are displayed at once.
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
			],
			[
				[
					'search_string' => 'st obj',
					'expected_suggestions' => ['Test object Host']
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
	protected function verifyThatSuggestionsNotShown() {
		try {
			$this->query('class:search-suggest')->waitUntilVisible(1);
		}
		catch (TimeoutException $e) {
			// All good, the suggestion list is not visible, continue the test.
		}
	}
}
