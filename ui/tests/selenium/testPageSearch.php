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
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';

/**
 * @backup hstgrp
 *
 * @onBefore prepareData
 */
class testPageSearch extends CWebTest {

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	protected $search_string = 'Test object';

	protected static $widgets = [
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
		self::$widgets['hostgroups']['link_id'] = $response['groupids'][0];

		$response = CDataHelper::createHosts([
			[
				'host' => 'emoji visible name',
				'name' => '♥⭐️',
				'groups' => ['groupid' => 6],
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
				'groups' => ['groupid' => 6],
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
				'groups' => ['groupid' => 6],
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
				'groups' => ['groupid' => self::$widgets['hostgroups']['link_id']],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'testdnstwo.example.com',
					'port' => '10050'
				]
			],
			[
				'host' => 'Entities Host',
				'groups' => ['groupid' => 6],
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				],
				'items' => [
					[
						'name' => 'Item 1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 2',
						'key_' => 'key[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Discovery 1',
						'key_' => 'lld[1]',
						'type' => ITEM_TYPE_TRAPPER
					],
					[
						'name' => 'Discovery 2',
						'key_' => 'lld[2]',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);
		self::$widgets['hosts']['link_id'] = $response['hostids'][$this->search_string.' Host'];
		$host_id = $response['hostids']['Entities Host'];
		$item_id = $response['itemids']['Entities Host:key[1]'];

		$response = CDataHelper::createTemplates([
			[
				'host' => $this->search_string.' Template',
				'groups' => ['groupid' => self::$widgets['hostgroups']['link_id']]
			],
			[
				'host' => 'Entities Template',
				'groups' => ['groupid' => 6],
				'items' => [
					[
						'name' => 'Item 1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 2',
						'key_' => 'key[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Discovery 1',
						'key_' => 'lld[1]',
						'type' => ITEM_TYPE_TRAPPER
					],
					[
						'name' => 'Discovery 2',
						'key_' => 'lld[2]',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);
		self::$widgets['templates']['link_id'] = $response['templateids'][$this->search_string.' Template'];
		$template_id = $response['templateids']['Entities Template'];
		$template_item_id = $response['itemids']['Entities Template:key[1]'];

		foreach ([$host_id => $item_id, $template_id => $template_item_id] as $parent_id => $item_id) {
			CDataHelper::call('application.create', [
				['name' => 'Application 1', 'hostid' => $parent_id],
				['name' => 'Application 2', 'hostid' => $parent_id]
			]);
			CDataHelper::call('graph.create', [
				['name' => 'Graph 1', 'gitems' => [['itemid' => $item_id, 'color' => '00FF00']]],
				['name' => 'Graph 2', 'gitems' => [['itemid' => $item_id, 'color' => '00FF00']]]
			]);
			CDataHelper::call('httptest.create', [
				[
					'name' => 'Web 1',
					'hostid' => $parent_id,
					'steps' => [['name' => 'Step', 'url' => 'http://example.com', 'no' => 1]]
				],
				[
					'name' => 'Web 2',
					'hostid' => $parent_id,
					'steps' => [['name' => 'Step', 'url' => 'http://example.com', 'no' => 1]]
				]
			]);
		}
		CDataHelper::call('trigger.create', [
			['description' => 'Trigger 1', 'expression' => '{Entities Host:key[1].last()}>1'],
			['description' => 'Trigger 2', 'expression' => '{Entities Host:key[1].last()}>1'],
			['description' => 'Trigger 1', 'expression' => '{Entities Template:key[1].last()}>1'],
			['description' => 'Trigger 2', 'expression' => '{Entities Template:key[1].last()}>1']
		]);
		CDataHelper::call('templatescreen.create', [
			['name' => 'Screen 1', 'templateid' => $template_id],
			['name' => 'Screen 2', 'templateid' => $template_id]
		]);

		// A host group and a template with no linked entities.
		CDataHelper::call('hostgroup.create', [['name' => 'Empty Hostgroup']]);
		CDataHelper::call('template.create', ['host' => 'Empty Template B', 'groups' => ['groupid' => 6]]);
	}

	/**
	 * Check the layout of the Search form.
	 */
	public function testPageSearch_LayoutForm() {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();

		$search_field = $form->getField('id:search');
		$search_button = $form->query('tag:button')->one();
		$this->assertEquals(255, $search_field->getAttribute('maxlength'));
		$this->assertEquals('off', $search_field->getAttribute('autocomplete'));
		$this->assertFalse($search_button->isClickable());
		$this->verifyThatSuggestionsNotShown();

		// Check suggestion highlighting.
		$search_field->fill($this->search_string);
		$this->assertTrue($search_button->isClickable());
		$highlighted_text = $this->query('class:suggest-found')->waitUntilVisible()->one()->getText();
		$this->assertEquals(strtolower($this->search_string), strtolower($highlighted_text));

		// Check that suggestions disappear after deleting input.
		$search_field->fill('');
		$this->verifyThatSuggestionsNotShown();
		$this->assertFalse($search_button->isClickable());

		$search_field->fill($this->search_string);
		$search_button->waitUntilClickable()->click();
		$this->page->assertHeader('Search: '.$this->search_string);
	}

	/**
	 * Check the layout of the Search result page.
	 */
	public function testPageSearch_LayoutPage() {
		$this->openSearchResults($this->search_string);

		$this->page->assertHeader('Search: '.$this->search_string);
		$this->page->assertTitle('Search');

		// Assert result widget layout for each widget.
		foreach (self::$widgets as $widget_params) {
			$widget = $this->query($widget_params['selector'])->one();
			$this->assertEquals($widget_params['title'], $widget->query('xpath:.//h4')->one()->getText());

			$table = $widget->query('xpath:.//table')->asTable()->one();

			// Check column names.
			$this->assertEquals(array_column($widget_params['columns'], 'name'), $table->getHeadersText());

			// Check table links.
			$table_first_row = $table->getRow(0);

			foreach ($widget_params['columns'] as $i => $column) {
				// The same column name is sometimes used twice so need to access column by index.
				$column_element = $table_first_row->getColumn($i);

				// If no text specified, the column text should match its name.
				$this->assertEquals(CTestArrayHelper::get($column, 'text', $column['name']), $column_element->getText());

				if (array_key_exists('href', $column)) {
					// Check that the link href matches.
					$expected_href = str_replace('{id}', $widget_params['link_id'], $column['href']);
					$this->assertEquals($expected_href, $column_element->query('tag:a')->one()->getAttribute('href'));
				}
				else {
					$this->assertFalse($column_element->isAttributePresent('href'));
				}
			}

			// Check expanding functionality.
			$widget_body = $widget->query('class:body')->one();
			$collapse_button = $widget->query('class:btn-widget-collapse')->one();
			$this->assertEquals('Collapse', $collapse_button->getAttribute('title'));

			$collapse_button->click();
			$widget_body->waitUntilNotVisible();
			$this->assertEquals('Expand', $collapse_button->getAttribute('title'));

			$expand_button = $widget->query('class:btn-widget-expand')->one();
			$expand_button->click();
			$widget_body->waitUntilVisible();
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
					'hosts' => [['Host' => '♥⭐️']]
				]
			],
			[
				[
					'search_string' => 'emoji visible name',
					'hosts' => [['Host' => "♥⭐️\n(emoji visible name)"]]
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
					'hosts' => [['Host' => '♥⭐️', 'IP' => '99.99.99.99', 'DNS' => '']]

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
			$template_sql = 'SELECT NULL FROM hosts WHERE LOWER(host) LIKE '.zbx_dbstr('%'.$data['search_string'].'%').' AND status=3';
			$hostgroup_sql = 'SELECT NULL FROM hstgrp WHERE LOWER(name) LIKE '.zbx_dbstr('%'.$data['search_string'].'%');
			$host_sql = 'SELECT DISTINCT(h.host) FROM hosts h INNER JOIN interface i on i.hostid=h.hostid'.
					' WHERE h.status in (0,1) AND h.flags in (0,4)'.
					' AND (LOWER(h.host) LIKE '.zbx_dbstr('%'.$data['search_string'].'%').
					' OR LOWER(h.name) LIKE '.zbx_dbstr('%'.$data['search_string'].'%').
					' OR i.dns LIKE '.zbx_dbstr('%'.$data['search_string'].'%').
					' OR i.ip LIKE '.zbx_dbstr('%'.$data['search_string'].'%').')';

			$db_count = [];
			foreach (['hosts' => $host_sql, 'host_groups' => $hostgroup_sql, 'templates' => $template_sql] as $type => $sql) {
				$db_count[$type] = CDBHelper::getCount($sql);
			}
		}

		$this->openSearchResults($data['search_string']);

		$this->page->assertHeader('Search: '.$data['search_string']);

		// Verify each widget type.
		foreach (self::$widgets as $widget_params) {
			$widget = $this->query($widget_params['selector'])->one();

			// Assert table data, but only if count from DB is not set.
			if (!CTestArrayHelper::get($data, 'count_from_db')) {
				$this->assertTableData(CTestArrayHelper::get($data, $widget_params['key'], []), $widget_params['table_selector']);
			}

			// Assert table stats.
			$expected_count = CTestArrayHelper::get($data, 'count_from_db')
				? $db_count[$widget_params['key']]
				: (array_key_exists($widget_params['key'], $data) ? count($data[$widget_params['key']]) : 0);
			$footer_text = $widget->query('xpath:.//ul[@class="dashbrd-widget-foot"]//li')->one()->getText();
			// Only a maximum of 100 records are displayed at once.
			$this->assertEquals('Displaying '.(min($expected_count, 100)).' of '.$expected_count.' found', $footer_text);
		}
	}

	public static function getEntityData() {
		return [
			[
				[
					'search_string' => 'Test object Host',
					'hosts' => [
						'Host' => ['count' => null],
						'IP' => ['count' => null],
						'DNS' => ['count' => null],
						'Latest data' => ['count' => null],
						'Problems' => ['count' => null],
						'Graphs' => ['count' => null, 'column_index' => 5],
						'Screens' => ['count' => null],
						'Web' => ['count' => null, 'column_index' => 7],
						'Applications' => ['count' => null],
						'Items' => ['count' => null],
						'Triggers' => ['count' => null],
						'Graphs_2' => ['count' => null, 'column_index' => 11],
						'Discovery' => ['count' => null],
						'Web_2' => ['count' => null, 'column_index' => 13]
					]
				]
			],
			[
				[
					'search_string' => 'Entities Host',
					'hosts' => [
						'Applications' => ['count' => 2],
						'Items' => ['count' => 2],
						'Triggers' => ['count' => 2],
						'Graphs' => ['count' => 2, 'column_index' => 11],
						'Discovery' => ['count' => 2],
						'Web' => ['count' => 2, 'column_index' => 13]
					]
				]
			],
			[
				[
					'search_string' => 'Empty Hostgroup',
					'host_groups' => [
						'Host group' => ['count' => null],
						'Latest data' => ['count' => null],
						'Problems' => ['count' => null],
						'Web' => ['count' => null],
						'Hosts' => ['count' => null],
						'Templates' => ['count' => null]
					]
				]
			],
			[
				[
					'search_string' => 'Test object Hostgroup',
					'host_groups' => [
						'Hosts' => ['count' => 1],
						'Templates' => ['count' => 1]
					]
				]
			],
			[
				[
					'search_string' => 'Empty Template B',
					'templates' => [
						'Template' => ['count' => null],
						'Applications' => ['count' => null],
						'Items' => ['count' => null],
						'Triggers' => ['count' => null],
						'Graphs' => ['count' => null],
						'Screens' => ['count' => null],
						'Discovery' => ['count' => null],
						'Web' => ['count' => null]
					]
				]
			],
			[
				[
					'search_string' => 'Entities Template',
					'templates' => [
						'Applications' => ['count' => 2],
						'Items' => ['count' => 2],
						'Triggers' => ['count' => 2],
						'Graphs' => ['count' => 2],
						'Screens' => ['count' => 2],
						'Discovery' => ['count' => 2],
						'Web' => ['count' => 2]
					]
				]
			]
		];
	}

	/**
	 * Search for a string and verify linked entity counts.
	 *
	 * @dataProvider getEntityData
	 */
	public function testPageSearch_VerifyEntityCount($data) {
		$this->openSearchResults($data['search_string']);

		// For each widget type.
		foreach (self::$widgets as $widget_params) {
			// Only check widget if any expected data is set for it.
			if (!array_key_exists($widget_params['key'], $data)) {
				continue;
			}

			$table_row = $this->query($widget_params['table_selector'])->asTable()->one()->getRow(0);

			// For each expected column.
			foreach ($data[$widget_params['key']] as $column_name => $column_data) {
				// Use column index when specified. This is because some column names are not unique.
				$column = $table_row->getColumn(CTestArrayHelper::get($column_data, 'column_index', $column_name));

				if (CTestArrayHelper::get($column_data, 'count')) {
					$this->assertEquals($column_data['count'], $column->query('tag:sup')->one()->getText());
					$this->assertFalse($column->isAttributePresent('href'));
				}
				else {
					// The text should not end with a space and a number.
					$this->assertEquals(0, preg_match('/ [0-9]+$/', $column->getText()));
				}
			}
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
					'expected_suggestions' => ['♥⭐️']
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
		if (array_key_exists('expected_suggestions', $data)) {
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
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
			throw new Exception('Suggestions list shown when it should not be.');
		}
		catch (TimeoutException $e) {
			// All good, the suggestion list is not visible, continue the test.
		}
	}

	/**
	 * Opens Dashboard, enters search string and submits the search form.
	 *
	 * @param string  $search_string    text that will be entered in the search field
	 */
	protected function openSearchResults($search_string) {
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$form = $this->query('class:form-search')->waitUntilVisible()->asForm()->one();
		$form->query('id:search')->one()->fill($search_string);
		$form->submit();
	}
}
