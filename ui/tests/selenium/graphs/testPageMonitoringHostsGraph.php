<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup profiles, hosts
 *
 * @onBefore prepareGraphsData
 */
class testPageMonitoringHostsGraph extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	/**
	 * ID of the item used for graph creation and check.
	 *
	 * @var integer
	 */
	private static $itemids;

	/**
	 * ID of the graph used for result check.
	 *
	 * @var integer
	 */
	private static $graphids;

	public static function prepareGraphsData() {
		CDataHelper::call('host.create', [
			[
				'host' => 'Host_for_monitoring_graphs_1',
				'groups' => [
					'groupid' => 4
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
				'host' => 'Host_for_monitoring_graphs_2',
				'groups' => [
					'groupid' => 4
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
		$hostid = CDataHelper::getIds('host');

		CDataHelper::call('item.create', [
			[
				'name' => 'Item_for_graph_1',
				'key_' => 'trap_1',
				'hostid' => $hostid['Host_for_monitoring_graphs_1'],
				'type' => 2,
				'value_type' => 0,
				'tags' => [
					[
						'tag' => 'tag_name_1',
						'value' => 'tag_value_1'
					]
				]
			],
			[
				'name' => 'Item_for_graph_2',
				'key_' => 'trap_2',
				'hostid' => $hostid['Host_for_monitoring_graphs_1'],
				'type' => 2,
				'value_type' => 0,
				'tags' => [
					[
						'tag' => 'tag_name_2',
						'value' => 'tag_value_2'
					]
				]
			],
			[
				'name' => 'Item_for_graph_3',
				'key_' => 'trap_3',
				'hostid' => $hostid['Host_for_monitoring_graphs_1'],
				'type' => 2,
				'value_type' => 0,
				'tags' => [
					[
						'tag' => 'tag_name_3',
						'value' => 'tag_value_3'
					]
				]
			],
			[
				'name' => 'Item_for_graph_4',
				'key_' => 'trap_4',
				'hostid' => $hostid['Host_for_monitoring_graphs_2'],
				'type' => 2,
				'value_type' => 0
			]
		]);
		self::$itemids = CDataHelper::getIds('name');

		CDataHelper::call('graph.create', [
			[
				'name' => 'Graph_1',
				'gitems' => [
					[
						'itemid' => self::$itemids['Item_for_graph_1'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph_2',
				'gitems' => [
					[
						'itemid' => self::$itemids['Item_for_graph_2'],
						'color' => '00AA00'
					],
					[
						'itemid' => self::$itemids['Item_for_graph_2'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph_3',
				'gitems' => [
					[
						'itemid' => self::$itemids['Item_for_graph_2'],
						'color' => '00AA00'
					],
					[
						'itemid' => self::$itemids['Item_for_graph_3'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Graph_4',
				'gitems' => [
					[
						'itemid' => self::$itemids['Item_for_graph_4'],
						'color' => '00AA00'
					]
				]
			]
		]);
		self::$graphids = CDataHelper::getIds('name');
	}

	/**
	 * Check graph page layout.
	 */
	public function testPageMonitoringHostsGraph_Layout() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&filter_search_type=0&filter_set=1')->waitUntilReady();
		$this->page->assertHeader('Graphs');
		$this->page->assertTitle('Custom graphs');

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that time selector set to display Last hour data.
		$this->assertTrue($this->query('xpath://a[@data-label="Last 1 hour"]')->one()->hasClass('selected'));

		// Enable filter and check filter labels.
		$this->query('id:ui-id-2')->one()->click();
		$form = $this->query('name:zbx_filter')->asform()->one();
		$this->assertEquals(['Hosts', 'Name', 'Show'], $form->getLabels()->asText());

		// Check Show parameters and default value.
		$radio = $this->query('id:filter_show')->asSegmentedRadio()->one();
		$this->assertEquals(['All graphs', 'Host graphs', 'Simple graphs'], $radio->getLabels()->asText());
		$this->assertEquals('All graphs', $radio->getSelected());

		// Check placeholder for Hosts.
		$this->assertEquals('type here to search', $form->getField('id:filter_hostids__ms')->getAttribute('placeholder'));

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('xpath:.//div[@class="filter-forms"]/button[text()="'.$button.'"]')
					->one()->isClickable()
			);
		}

		// Check that the table is empty by default.
		$this->assertEquals('Specify host to see the graphs.', $this->query('class:no-data-message')->one()->getText());
	}

	public static function getCheckFilterData() {
		return [
			// #0 One host with several items and graphs. Show all graphs.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Show' => 'All graphs'
					],
					'graphs_amount' => 6,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3'
					]
				]
			],
			// #1 Show host graphs for host.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Show' => 'Host graphs'
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #2 Show simple graphs for host.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Show' => 'Simple graphs'
					],
					'graphs_amount' => 3,
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3'
					]
				]
			],
			// #3 Show all graphs for host with graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Graph_2',
						'Show' => 'All graphs'
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_2'
					]
				]
			],
			// #4 Show host graphs for host with graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Graph_2',
						'Show' => 'Host graphs'
					],
					'graphs_amount' => 2,
					'graph_names' => [
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #5 Show simple graphs for host with graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Graph_2',
						'Show' => 'Simple graphs'
					],
					'graphs_amount' => 1,
					'item_names' => [
						'Item_for_graph_2'
					]
				]
			],
			// #6 Show all graphs with not existing graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Nothing here',
						'Show' => 'All graphs'
					]
				]
			],
			// #7 Show host graphs with not existing graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Nothing here',
						'Show' => 'Host graphs'
					]
				]
			],
			// #8 Show simple graphs with not existing graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Nothing here',
						'Show' => 'Simple graphs'
					]
				]
			],
			// #9 Show all graphs with partial graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'gr',
						'Show' => 'All graphs'
					],
					'graphs_amount' => 6,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3'
					]
				]
			],
			// #10 Show host graphs with partial graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'gr',
						'Show' => 'Host graphs'
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #11 Show simple graphs with partial graph name.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'gr',
						'Show' => 'Simple graphs'
					],
					'graphs_amount' => 3,
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3'
					]
				]
			],
			// #12 Show graphs without Hosts - all graphs.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					]
				]
			],
			// #13 Show graphs without Hosts - host graphs.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					]
				]
			],
			// #14 Show graphs without Hosts - simple graphs.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					]
				]
			],
			// #15 Show graphs with correct graph name and without Hosts - all graphs.
			[
				[
					'filter' => [
						'Name' => 'Graph_2',
						'Show' => 'All graphs'
					]
				]
			],
			// #16 Show graphs with correct graph name and without Hosts - host graphs.
			[
				[
					'filter' => [
						'Name' => 'Graph_2',
						'Show' => 'Host graphs'
					]
				]
			],
			// #17 Show graphs with correct graph name and  without Hosts - simple graphs.
			[
				[
					'filter' => [
						'Name' => 'Graph_2',
						'Show' => 'Simple graphs'
					]
				]
			],
			// #18 Two hosts with several items and graphs. Show all graphs.
			[
				[
					'filter' => [
						'Hosts' => [
							'Host_for_monitoring_graphs_1',
							'Host_for_monitoring_graphs_2'
						],
						'Show' => 'All graphs'
					],
					'graphs_amount' => 8,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3',
						'Graph_4'
					],
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3',
						'Item_for_graph_4'
					]
				]
			],
			// #19 Two hosts with several items and graphs. Show Host graphs.
			[
				[
					'filter' => [
						'Hosts' => [
							'Host_for_monitoring_graphs_1',
							'Host_for_monitoring_graphs_2'
						],
						'Show' => 'Host graphs'
					],
					'graphs_amount' => 4,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3',
						'Graph_4'
					]
				]
			],
			// #20 Two hosts with several items and graphs. Show Simple graphs.
			[
				[
					'filter' => [
						'Hosts' => [
							'Host_for_monitoring_graphs_1',
							'Host_for_monitoring_graphs_2'
						],
						'Show' => 'Simple graphs'
					],
					'graphs_amount' => 4,
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2',
						'Item_for_graph_3',
						'Item_for_graph_4'
					]
				]
			],
			// #21 Graph from another host.
			[
				[
					'filter' => [
						'Hosts' => 'Host_for_monitoring_graphs_1',
						'Name' => 'Graph_4'
					]
				]
			],
			// #22 All graphs - filter by Tags.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						]
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_2'
					]
				]
			],
			// #23 Host graphs - filter by Tags.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						]
					],
					'graphs_amount' => 2,
					'graph_names' => [
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #24 Simple graphs - filter by Tags.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						]
					],
					'graphs_amount' => 1,
					'item_names' => [
						'Item_for_graph_2'
					]
				]
			],
			// #25 All graphs - filter by 2 Tags.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2',
							'tag_name_1'
						]
					],
					'graphs_amount' => 5,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2'
					]
				]
			],
			// #26 Host graphs - filter by 2 Tags.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2',
							'tag_name_1'
						]
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #27 Simple graphs - filter by 2 Tags.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2',
							'tag_name_1'
						]
					],
					'graphs_amount' => 2,
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2'
					]
				]
			],
			// #28 All graphs - filter by Tags and Tag value.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					],
					'graphs_amount' => 1,
					'graph_names' => [
						'Graph_3'
					]
				]
			],
			// #29 Host graphs - filter by Tag and Tag value.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					],
					'graphs_amount' => 1,
					'graph_names' => [
						'Graph_2'
					]
				]
			],
			// #30 Simple graphs - filter by Tag and Tag value.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					]
				]
			],
			// #31 All graphs - filter by 2 Tags and Tag value.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					],
					'graphs_amount' => 1,
					'graph_names' => [
						'Graph_3'
					]
				]
			],
			// #32 Host graphs - filter by 2 Tags and Tag value.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					],
					'graphs_amount' => 1,
					'graph_names' => [
						'Graph_3'
					]
				]
			],
			// #33 Simple graphs - filter by 2 Tags and Tag value.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_3'
						]
					]
				]
			],
			// #34 All graphs - filter by 2 Tags and 2 Tag value.
			[
				[
					'filter' => [
						'Show' => 'All graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_2',
							'tag_value_1'
						]
					],
					'graphs_amount' => 5,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					],
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2'
					]
				]
			],
			// #35 Host graphs - filter by 2 Tags and 2 Tag value.
			[
				[
					'filter' => [
						'Show' => 'Host graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_2',
							'tag_value_1'
						]
					],
					'graphs_amount' => 3,
					'graph_names' => [
						'Graph_1',
						'Graph_2',
						'Graph_3'
					]
				]
			],
			// #36 Simple graphs - filter by 2 Tags and 2 Tag value.
			[
				[
					'filter' => [
						'Show' => 'Simple graphs'
					],
					'subfilter' => [
						'Tags' => [
							'tag_name_1',
							'tag_name_2'
						],
						'Tag values' => [
							'tag_value_2',
							'tag_value_1'
						]
					],
					'graphs_amount' => 2,
					'item_names' => [
						'Item_for_graph_1',
						'Item_for_graph_2'
					]
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

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->query('button:Reset')->one()->click();

		// Filter using tags.
		if (array_key_exists('subfilter', $data)) {
			$form->fill(['Hosts' => 'Host_for_monitoring_graphs_1', 'Show' => 'All graphs'])->submit();
			$this->page->waitUntilReady();

			// Click on subfilter.
			foreach ($data['subfilter'] as $header => $values) {
				foreach ($values as $value) {
					$this->query("xpath://h3[text()=".CXPathHelper::escapeQuotes($header)."]/..//a[text()=".
							CXPathHelper::escapeQuotes($value)."]")->waitUntilClickable()->one()->click();
					$this->query("xpath://h3[text()=".CXPathHelper::escapeQuotes($header)."]/..//a[text()=".
							CXPathHelper::escapeQuotes($value)."]/ancestor::span")->one()->
							waitUntilAttributesPresent(['class' => 'subfilter subfilter-enabled']);
					$this->page->waitUntilReady();
				}
			}
		}

		$form->fill($data['filter'])->submit();
		$this->page->waitUntilReady();

		// Check result amount and graph/item ids.
		if (array_key_exists('graphs_amount', $data)) {
			$this->assertEquals($data['graphs_amount'],
					$this->query('xpath://tbody/tr/div[@class="flickerfreescreen"]')->all()->count());
			$this->assertTableStats($data['graphs_amount']);

			// Find links with graphs and items ids.
			$graph_sources = [];
			foreach ($this->query("xpath://tbody//img")->all() as $source) {
				$graph_sources[] = $source->getAttribute('src');
			}

			// Check that displayed graphs have correct ids.
			if (array_key_exists('graph_name', $data)) {
				$this->checkGraphsIds($data['graph_names'], $graph_sources);
			}

			// Check that displayed item graphs have correct ids.
			if (array_key_exists('item_names', $data)) {
				$this->checkGraphsIds($data['item_names'], $graph_sources, false);
			}
		}
		else {
			$message = (array_key_exists('Hosts', $data['filter']) || array_key_exists('subfilter', $data))
				? 'No data found'
				: 'Specify host to see the graphs.';
			$this->assertEquals($message, $this->query('class:no-data-message')->one()->getText());
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

	/**
	 * Find and check graphs and items ids in graphs links.
	 *
	 * @param array   $names			items or graph names list
	 * @param array   $graph_sources	displayed graphs src attribute values
	 * @param boolean $graph			true if graph id need to be checked, false if simple graph id need to be checked
	 */
	private function checkGraphsIds($names, $graph_sources, $graph = true) {
		foreach ($names as $name) {
			$array_count = count($graph_sources);
			$id = ($graph) ? self::$graphids[$name] : self::$itemids[$name];

			foreach ($graph_sources as $source) {
				if (str_contains($source, '='.$id)) {
					$graph_sources = array_values(array_diff($graph_sources, [$source]));
				}
			}

			$this->assertEquals($array_count - 1, count($graph_sources));
		}
	}
}
