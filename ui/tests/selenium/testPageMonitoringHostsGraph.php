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


require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup profiles, hosts, items, graphs
 *
 * @onBefore prepareGraphsData
 */
class testPageMonitoringHostsGraph extends CWebTest {

	public function prepareGraphsData() {
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Host for monitoring graphs',
				'groups' => [
					'groupid' => 4
				],
				'interfaces' => [
					'type'=> 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				]
			]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		$hostid = $hosts['hostids'][0];

		$items = CDataHelper::call('item.create', [
			[
				'name' => 'Item for graph 1',
				'key_' => 'trap_1',
				'hostid' => $hostid,
				'type' => 2,
				'value_type' => 0
			],
			[
				'name' => 'Item for graph 2',
				'key_' => 'trap_2',
				'hostid' => $hostid,
				'type' => 2,
				'value_type' => 0
			],
			[
				'name' => 'Item for graph 3',
				'key_' => 'trap_3',
				'hostid' => $hostid,
				'type' => 2,
				'value_type' => 0
			]
		]);
		$this->assertArrayHasKey('itemids', $items);
		$itemids = CDataHelper::getIds('name');

		$graphs = CDataHelper::call('graph.create', [
			[
				'name' => 'Graph 1',
				'gitems' => [
					[
						'itemid' => $itemids['Item for graph 1'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph 2',
				'gitems' => [
					[
						'itemid' => $itemids['Item for graph 2'],
						'color' => '00AA00'
					],
					[
						'itemid' => $itemids['Item for graph 2'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph 3',
				'gitems' => [
					[
						'itemid' => $itemids['Item for graph 2'],
						'color' => '00AA00'
					],
					[
						'itemid' => $itemids['Item for graph 3'],
						'color' => '00AA00'
					]

				]
			],
		]);
		$this->assertArrayHasKey('graphids', $graphs);
	}

	/**
	 * Check graph page layout.
	 */
	public function testPageMonitoringHostsGraph_Layout() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to'.
				'=now&filter_search_type=0&filter_set=1');
		$this->page->assertHeader('Graphs');
		$this->page->assertTitle('Custom graphs');

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that time selector set to display Last hour data.
		$this->assertEquals('selected', $this->query('xpath://a[@data-label="Last 1 hour"]')
			->one()->getAttribute('class')
		);

		// Enable filter and check filter labels.
		$this->query('id:ui-id-2')->one()->click();
		$form = $this->query('name:zbx_filter')->asform()->one();
		$this->assertEquals(['Hosts', 'Name', 'Show'], $form->getLabels()->asText());

		// Check Show parameters and default value.
		$radio = $this->query('id:filter_show')->asSegmentedRadio()->one();
		$this->assertEquals(['All graphs', 'Host graphs', 'Simple graphs'], $radio->getLabels()->asText());
		$this->assertEquals('All graphs', $radio->getSelected());

		// Check placeholder for Hosts.
		$this->assertEquals('type here to search', $form->query('id:filter_hostids__ms')->one()->getAttribute('placeholder'));

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('xpath:.//div[@class="filter-forms"]/button[text()="'.$button.'"]')
				->one()->isClickable()
			);
		}

		// When table result is empty.
		$this->assertEquals('Specify host to see the graphs.', $this->query('class:nothing-to-show')->one()->getText());
	}

	/**
	 * Check graph page in layout mode.
	 */
	public function testPageMonitoringHostsGraph_KioskMode() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to'.
			'=now&filter_search_type=0&filter_set=1');

		// Check Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter disappeared.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->assertFalse($this->query('xpath://div[@aria-label="Filter"]')->exists());

		// Return to normal view.
		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
		$this->page->waitUntilReady();

		// Check that Header and Filter are visible again.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
		$this->assertTrue($this->query('xpath://div[@aria-label="Filter"]')->exists());
	}

	public static function getCheckFilterData() {
		return [
			// #0
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Strict'
					],
					'graphs_result' => 3,
					'items_names' => ['Item for graph 1', 'Item for graph 2', 'Item for graph 3']
				]
			],
			// #1
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern'
					],
					'graphs_result' => 3,
					'items_names' => ['Item for graph 1', 'Item for graph 2', 'Item for graph 3']
				]
			],
			// #2
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => 'Graph 2'
					],
					'graphs_result' => 1,
					'items_names' => ['Item for graph 2']
				]
			],
			// #3
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Strict',
						'Graphs' => 'Graph 2'
					],
					'graphs_result' => 1,
					'items_names' => ['Item for graph 2']
				]
			],
			// #4
			[
				[
					'filter' => [
						'Search type' => 'Strict',
						'Graphs' => 'Graph 1'
					],
					'graphs_result' => 1,
					'items_names' => ['Item for graph 1']
				]
			],
			// #5
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => 'Graph 1'
					],
					'graphs_result' => 0
				]
			],
			// #6
			[
				[
					'filter' => [
						'Search type' => 'Strict',
						'Graphs' => ['Graph 1', 'Graph 3']
					],
					'graphs_result' => 2,
					'items_names' => ['Item for graph 1', 'Item for graph 2', 'Item for graph 3']
				]
			],
			// #7
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => ['Graph 1', 'Graph 3']
					],
					'graphs_result' => 0
				]
			],
			// #8
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => ['non_existing_graph', 'Graph 1']
					],
					'graphs_result' => 0
				]
			],
			// #9
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => 'lonely_graph'
					],
					'graphs_result' => 0
				]
			],
			// #10
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => ['non_existing_graph', 'Graph 1']
					],
					'graphs_result' => 1,
					'items_names' => ['Item for graph 1']
				]
			]
		];
	}

	/**
	 * Check graph page filter.
	 *
	 * @dataProvider getCheckFilterData
	 */
	public function testPageMonitoringHostsGraph_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to='.
			'now&filter_search_type=0&filter_set=1');

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter'])->submit();
		$this->page->waitUntilReady();

		if ($data['graphs_result'] === 0) {
			foreach(['Graph', 'Values'] as $view) {
				$this->query('id:view-as')->asDropdown()->one()->select($view);

				if ($data['filter']['Graphs'] === 'lonely_graph') {
					$this->assertEquals(['No data found.'], $this->query('class:list-table')->asTable()->one()->
					getRows()->asText()
					);
				}
				else {
					$this->assertEquals('Specify host to see the graphs.',
						$this->query("xpath://table[@class='list-table']//td")->one()->getText()
					);
				}
			}
		}
		else {
			$graphs_count = $this->query('xpath://tbody/tr/div[@class="flickerfreescreen"]')->all()->count();
			$this->assertEquals($data['graphs_result'], $graphs_count);

			// Checking from Values view.
			$this->query('id:view-as')->asDropdown()->one()->select('Values');
			$this->page->waitUntilReady();
			$table = $this->query('class:list-table')->asTable()->one();
			$this->assertEquals(['No data found.'], $table->getRows()->asText());
			foreach ($data['items_names'] as $item) {
				$this->assertTrue($table->query('xpath://tr/th[@title="'.$item.'"]')->exists());
			}
		}
	}
}
