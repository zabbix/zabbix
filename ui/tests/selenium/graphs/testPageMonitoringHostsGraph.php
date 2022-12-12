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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup profiles, hosts
 *
 * @onBefore prepareGraphsData
 */
class testPageMonitoringHostsGraph extends CWebTest {

	/**
	 * Time.
	 */
	private static $time;

	/**
	 * Graph id.
	 */
	private static $graphids;

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

		self::$time = time()-300;
		CDataHelper::addItemData($itemids['Item for graph 1'], 1, self::$time);
		CDataHelper::addItemData($itemids['Item for graph 2'], 2, self::$time);
		CDataHelper::addItemData($itemids['Item for graph 3'], 3, self::$time);

		$graphs = CDataHelper::call('graph.create', [
			[
				'name' => 'Graph_1',
				'gitems' => [
					[
						'itemid' => $itemids['Item for graph 1'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph_2',
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
				'name' => 'Graph_3',
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
			]
		]);
		$this->assertArrayHasKey('graphids', $graphs);
		self::$graphids = CDataHelper::getIds('name');
	}

	/**
	 * Check graph page layout.
	 */
	public function testPageMonitoringHostsGraph_Layout() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to'.
				'=now&filter_search_type=0&filter_set=1')->waitUntilReady();
		$this->page->assertHeader('Graphs');
		$this->page->assertTitle('Custom graphs');

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that timeselector set to display Last hour data.
		$this->assertEquals('selected', $this->query('xpath://a[@data-label="Last 1 hour"]')->one()->getAttribute('class'));

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asform()->one();

		// There are 2 graphs because second appears after you click pattern.
		$this->assertEquals(['Host', 'Search type', 'Graphs', 'Graphs'], $form->getLabels()->asText());

		// Check that Strict search type is selected first.
		$this->assertEquals('Strict', $form->query('id:filter_search_type')->asSegmentedRadio()->one()->getText());

		// Check placeholders.
		foreach (['filter_hostids__ms', 'filter_graphids__ms', 'filter_graph_patterns__ms'] as $id) {
			if ($id === 'filter_graph_patterns__ms') {
				$form->fill(['Search type' => 'Pattern']);
				$placeholder = 'graph pattern';
			}
			else {
				$placeholder = 'type here to search';
			}
			$this->assertEquals($placeholder, $form->query('id', $id)->one()->getAttribute('placeholder'));
		}

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('xpath:.//div[@class="filter-forms"]/button[text()="'.$button.'"]')
					->one()->isClickable()
			);
		}

		// When table result is empty.
		$this->assertEquals('Specify host to see the graphs.',
				$this->query("xpath://table[@class='list-table']//td")->one()->getText()
		);

		// Check "View as" values.
		$this->assertEquals(['Graph', 'Values'], $this->query('id:view-as')->one()->asDropdown()->getOptions()->asText());

		// Check that "Favourite" button appears and is clickable.
		$form->fill(['Search type' => 'Strict', 'Graphs' => 'CPU jumps'])->submit();
		$this->page->waitUntilReady();

		foreach (['Add to favourites', 'Remove from favourites'] as $favourite) {
			$this->assertTrue($this->query('xpath://button[@title="'.$favourite.'"]')->waitUntilReady()->exists());
			$this->query('xpath://button[@title="'.$favourite.'"]')->one()->click();
		}
	}

	/**
	 * Check graph page in kiosk mode.
	 */
	public function testPageMonitoringHostsGraph_KioskMode() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to'.
				'=now&filter_search_type=0&filter_set=1')->waitUntilReady();

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
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'items_names' => [
						'Item for graph 1',
						'Item for graph 2',
						'Item for graph 3'
					],
					'view_result' => ["1\n2\n3"]
				]
			],
			// #1
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern'
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'items_names' => [
						'Item for graph 1',
						'Item for graph 2',
						'Item for graph 3'
					],
					'view_result' => ["1\n2\n3"]
				]
			],
			// #2
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => 'Graph_2'
					],
					'graphs_amount' => 1,
					'graph_names' => ['Graph_2'],
					'items_names' => ['Item for graph 2'],
					'view_result' => ['2']
				]
			],
			// #3
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Strict',
						'Graphs' => 'Graph_2'
					],
					'graphs_amount' => 1,
					'graph_names' => ['Graph_2'],
					'items_names' => ['Item for graph 2'],
					'view_result' => ['2']
				]
			],
			// #4
			[
				[
					'filter' => [
						'Search type' => 'Strict',
						'Graphs' => 'Graph_1'
					],
					'graphs_amount' => 1,
					'graph_names' => ['Graph_1'],
					'items_names' => ['Item for graph 1'],
					'view_result' => ['1']
				]
			],
			// #5
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => 'Graph_1'
					]
				]
			],
			// #6
			[
				[
					'filter' => [
						'Search type' => 'Strict',
						'Graphs' => [
							'Graph_1',
							'Graph_3'
						]
					],
					'graphs_amount' => 2,
					'graph_names' => [
						'Graph_1',
						'Graph_3'
					],
					'items_names' => [
						'Item for graph 1',
						'Item for graph 2',
						'Item for graph 3'
					],
					'view_result' => ["1\n2\n3"]
				]
			],
			// #7
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => [
							'Graph_1',
							'Graph_3'
						]
					]
				]
			],
			// #8
			[
				[
					'filter' => [
						'Search type' => 'Pattern',
						'Graphs' => [
							'non_existing_graph',
							'Graph_1'
						]
					]
				]
			],
			// #9
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => 'lonely_graph'
					]
				]
			],
			// #10
			[
				[
					'filter' => [
						'Host' => 'Host for monitoring graphs',
						'Search type' => 'Pattern',
						'Graphs' => [
							'non_existing_graph',
							'Graph_1'
						]
					],
					'graphs_amount' => 1,
					'graph_names' => ['Graph_1'],
					'items_names' => ['Item for graph 1'],
					'view_result' => ['1']
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
				'now&filter_search_type=0&filter_set=1')->waitUntilReady();

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter'])->submit();
		$this->page->waitUntilReady();

		// Check that correct graphs displayed.
		if (array_key_exists('graphs_amount', $data)) {
			$this->assertEquals($data['graphs_amount'],
					$this->query('xpath://tbody/tr/div[@class="flickerfreescreen"]')->all()->count());

			// Find links with graphs and items ids.
			$graph_sources = [];
			foreach ($this->query("xpath://tbody//img")->all() as $source) {
				$graph_sources[] = $source->getAttribute('src');
			}

			// Check that displayed graphs has correct ids.
			foreach ($data['graph_names'] as $name) {
				$array_count = count($graph_sources);

				foreach ($graph_sources as $source) {
					if (str_contains($source, '='.self::$graphids[$name])) {
							$graph_sources = array_values(array_diff($graph_sources, [$source])
						);
					}
				}

				$this->assertEquals($array_count - 1, count($graph_sources));
			}

			// Checking from Values view.
			$this->query('id:view-as')->asDropdown()->one()->select('Values');
			$this->page->waitUntilReady();
			$table = $this->query('class:list-table')->asTable()->one();

			// Transfer results from array to string.
			$view_result = implode($data['view_result']);

			// Change date/time format to string.
			$string_time = date('Y-m-d H:i:s', self::$time);

			// Make correct array with values that should be displayed in "Values view"
			$last_result[] = $string_time."\n".$view_result;
			$this->assertEquals($last_result, $table->getRows()->asText());

			foreach ($data['items_names'] as $item) {
				$this->assertTrue($table->query('xpath://tr/th[@title="'.$item.'"]')->exists());
			}
		}
		else {
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
	}
}
