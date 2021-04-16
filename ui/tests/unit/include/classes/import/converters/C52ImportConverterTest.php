<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class C44ImportConverterTest extends CImportConverterTest {

	protected function getTemplateNameUuidData(): array {
		/**
		 * For creation UUID's, template names will have to be updated by removing following part (regex):
		 *  "Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) "
		 * This should be done, only, if at least 3 (warning) letters remain in template name after that.
		 * Regex is case sensitive, test: "Template MODULE abc"
		 */
		return [
			[
				'templates' => [
					['name' => 'Template OS'],
					['name' => 'Template OS ab'],
					['name' => 'Template OS abc'],
					['name' => 'Template MODULE abc'],
				]
			],
			[
				'templates' => [
					['name' => 'Template OS', 'uuid' => generateUuidV4('Template OS')],
					['name' => 'Template OS ab', 'uuid' => generateUuidV4('Template OS ab')],
					['name' => 'Template OS abc', 'uuid' => generateUuidV4('abc')],
					['name' => 'Template MODULE abc', 'uuid' => generateUuidV4('Template MODULE abc')]
				]
			]
		];
	}

	protected function getGroupsUuidData(): array {
		/**
		 * Use "host group name".
		 * Group uuid should be generated for all groups in import, even for groups not used by templates.
		 */
		return [
			[
				'hosts' => [
					['name' => 'Host A', 'groups' => [['name' => 'Group B']]]
				],
				'templates' => [
					['name' => 'Template A', 'groups' => [['name' => 'Group A']]]
				],
				'groups' => [
					['name' => 'Group A'],
					['name' => 'Group B']
				]
			],
			[
				'hosts' => [
					['name' => 'Host A', 'groups' => [['name' => 'Group B']]]
				],
				'templates' => [
					['name' => 'Template A', 'groups' => [['name' => 'Group A']], 'uuid' => generateUuidV4('Template A')]
				],
				'groups' => [
					['name' => 'Group A', 'uuid' => generateUuidV4('Group A')],
					['name' => 'Group B', 'uuid' => generateUuidV4('Group B')]
				]
			]
		];
	}

	protected function getTemplateItemsUuidData(): array {
		// Use "template name/item key".
		return [
			[
				'templates' => [
					[
						'name' => 'Template C',
						'items' => [
							['key' => 'item1']
						]
					],
					[
						'name' => 'Template OS Old name D',
						'items' => [
							['key' => 'item1']
						]
					],
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template C',
						'items' => [
							['key' => 'item1', 'uuid' => generateUuidV4('Template C/item1')]
						],
						'uuid' => generateUuidV4('Template C')
					],
					[
						'name' => 'Template OS Old name D',
						'items' => [
							['key' => 'item1', 'uuid' => generateUuidV4('Old name D/item1')]
						],
						'uuid' => generateUuidV4('Old name D')
					]
				]
			]
		];
	}

	protected function getTriggerUuidData(): array {
		// Use "trigger name/expanded expression/expanded recovery expression".
		return [
			[
				'templates' => [
					[
						'name' => 'Template E',
						'items' => [
							[
								'key' => 'item2',
								'triggers' => [
									[
										'name' => 'trigger A',
										'expression' => '{A:expression}'
									],
									[
										'name' => 'trigger B',
										'expression' => '{B:expression}',
										'recovery_expression' => '{B:recovery}'
									]
								]
							]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template E',
						'items' => [
							[
								'key' => 'item2',
								'triggers' => [
									[
										'name' => 'trigger A',
										'expression' => '{A:expression}',
										'uuid' => generateUuidV4('trigger A/{A:expression}')
									],
									[
										'name' => 'trigger B',
										'expression' => '{B:expression}',
										'recovery_expression' => '{B:recovery}',
										'uuid' => generateUuidV4('trigger B/{B:expression}/{B:recovery}')
									]
								],
								'uuid' => generateUuidV4('Template E/item2')
							]
						],
						'uuid' => generateUuidV4('Template E')
					]
				]
			]
		];
	}

	protected function getGraphUuidData(): array {
		// Use "graph name" and "template name" of each used item.
		return [
			[
				'templates' => [
					['name' => 'Template OS'],
					['name' => 'Template OS Template G']
				],
				'graphs' => [
					[
						'name' => 'Graph A',
						'graph_items' => [
							['host' => 'Template OS', 'key' => 'item3'],
							['host' => 'Template OS Template G', 'key' => 'item4']
						]
					],
				]
			],
			[
				'templates' => [
					['name' => 'Template OS', 'uuid' => generateUuidV4('Template OS')],
					['name' => 'Template OS Template G', 'uuid' => generateUuidV4('Template G')]
				],
				'graphs' => [
					[
						'name' => 'Graph A',
						'graph_items' => [
							['host' => 'Template OS', 'key' => 'item3'],
							['host' => 'Template OS Template G', 'key' => 'item4']
						],
						'uuid' => generateUuidV4('Graph A/Template OS/Template G')
					],
				]
			]
		];
	}

	protected function getTemplateDashboardUuidData(): array {
		// Use "template name/dashboard name".
		return [
			[
				'templates' => [
					[
						'name' => 'Template H',
						'dashboards' => [
							['name' => 'Dashboard A']
						]
					],
					[
						'name' => 'Template OS Template I',
						'dashboards' => [
							['name' => 'Dashboard B']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template H',
						'dashboards' => [
							['name' => 'Dashboard A', 'uuid' => generateUuidV4('Template H/Dashboard A'), 'pages' => [[]]]
						],
						'uuid' => generateUuidV4('Template H')
					],
					[
						'name' => 'Template OS Template I',
						'dashboards' => [
							['name' => 'Dashboard B', 'uuid' => generateUuidV4('Template I/Dashboard B'), 'pages' => [[]]]
						],
						'uuid' => generateUuidV4('Template I')
					]
				]
			]
		];
	}

	protected function getHttptestUuidData(): array {
		// Use "template name/web scenario name".
		return [
			[
				'templates' => [
					[
						'name' => 'Template J',
						'httptests' => [
							['name' => 'HTTP Test A']
						]
					],
					[
						'name' => 'Template OS Template K',
						'httptests' => [
							['name' => 'HTTP Test B']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template J',
						'httptests' => [
							['name' => 'HTTP Test A', 'uuid' => generateUuidV4('Template J/HTTP Test A')]
						],
						'uuid' => generateUuidV4('Template J')
					],
					[
						'name' => 'Template OS Template K',
						'httptests' => [
							['name' => 'HTTP Test B', 'uuid' => generateUuidV4('Template K/HTTP Test B')]
						],
						'uuid' => generateUuidV4('Template K')
					]
				]
			]
			];
	}

	protected function getValuemapsUuidData(): array {
		// Use "template name/value map name".
		return [
			[
				'templates' => [
					[
						'name' => 'Template L',
						'items' => [
							['key' => 'item5', 'valuemap' => ['name' => 'Value map A']],
							['key' => 'item6', 'valuemap' => ['name' => 'Value map B']]
						]
					],
					[
						'name' => 'Template OS Template K',
						'items' => [
							['key' => 'item7', 'valuemap' => ['name' => 'Value map A']],
							['key' => 'item8', 'valuemap' => ['name' => 'Value map B']]
						]
					]
				],
				'value_maps' => [
					['name' => 'Value map A', 'mappings' => []],
					['name' => 'Value map B', 'mappings' => []]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template L',
						'items' => [
							['key' => 'item5', 'valuemap' => ['name' => 'Value map A'], 'uuid' => generateUuidV4('Template L/item5')],
							['key' => 'item6', 'valuemap' => ['name' => 'Value map B'], 'uuid' => generateUuidV4('Template L/item6')]
						],
						'valuemaps' => [
							['name' => 'Value map A', 'mappings' => [], 'uuid' => generateUuidV4('Template L/Value map A')],
							['name' => 'Value map B', 'mappings' => [], 'uuid' => generateUuidV4('Template L/Value map B')]
						],
						'uuid' => generateUuidV4('Template L')
					],
					[
						'name' => 'Template OS Template K',
						'items' => [
							['key' => 'item7', 'valuemap' => ['name' => 'Value map A'], 'uuid' => generateUuidV4('Template K/item7')],
							['key' => 'item8', 'valuemap' => ['name' => 'Value map B'], 'uuid' => generateUuidV4('Template K/item8')]
						],
						'valuemaps' => [
							['name' => 'Value map A', 'mappings' => [], 'uuid' => generateUuidV4('Template K/Value map A')],
							['name' => 'Value map B', 'mappings' => [], 'uuid' => generateUuidV4('Template K/Value map B')]
						],
						'uuid' => generateUuidV4('Template K')
					]
				]
			]
		];
	}

	protected function getDiscoveryRuleNameUuidData(): array {
		// Use "template name/discovery rule key".
		return [
			[
				'templates' => [
					[
						'name' => 'Template M',
						'discovery_rules' => [
							['key' => 'drule1']
						]
					],
					[
						'name' => 'Template OS Template N',
						'discovery_rules' => [
							['key' => 'drule2']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template M',
						'discovery_rules' => [
							['key' => 'drule1', 'uuid' => generateUuidV4('Template M/drule1')]
						],
						'uuid' => generateUuidV4('Template M')
					],
					[
						'name' => 'Template OS Template N',
						'discovery_rules' => [
							['key' => 'drule2', 'uuid' => generateUuidV4('Template N/drule2')]
						],
						'uuid' => generateUuidV4('Template N')
					]
				]
			]
		];
	}

	protected function getItemProtypeUuidData(): array {
		// Use "template name/discovery rule key/item prototype key".
		return [
			[
				'templates' => [
					[
						'name' => 'Template O',
						'discovery_rules' => [
							['key' => 'drule3', 'item_prototypes' => [['key' => 'item9']]]
						]
					],
					[
						'name' => 'Template OS Template P',
						'discovery_rules' => [
							['key' => 'drule4', 'item_prototypes' => [['key' => 'item10']]]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template O',
						'discovery_rules' => [
							[
								'key' => 'drule3',
								'item_prototypes' => [
									['key' => 'item9', 'uuid' => generateUuidV4('Template O/drule3/item9')]
								],
								'uuid' => generateUuidV4('Template O/drule3')
							]
						],
						'uuid' => generateUuidV4('Template O')
					],
					[
						'name' => 'Template OS Template P',
						'discovery_rules' => [
							[
								'key' => 'drule4',
								'item_prototypes' => [
									['key' => 'item10', 'uuid' => generateUuidV4('Template P/drule4/item10')]
								],
								'uuid' => generateUuidV4('Template P/drule4')
							]
						],
						'uuid' => generateUuidV4('Template P')
					]
				]
			]
		];
	}

	protected function getTriggerProtypeUuidData(): array {
		// Use "discovery rule key/trigger prototype name/expanded expression/expanded recovery expression".
		return [
			[
				'templates' => [
					[
						'name' => 'Template Q',
						'discovery_rules' => [
							[
								'key' => 'drule5',
								'item_prototypes' => [
									[
										'key' => 'item11',
										'trigger_prototypes' => [
											['name' => 'Trigger C', 'expression' => '{C:expression}'],
											['name' => 'Trigger D', 'expression' => '{D:expression}', 'recovery_expression' => '{D:recovery_expression}']
										]
									]
								]
							]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template Q',
						'discovery_rules' => [
							[
								'key' => 'drule5',
								'item_prototypes' => [
									[
										'key' => 'item11',
										'trigger_prototypes' => [
											[
												'name' => 'Trigger C',
												'expression' => '{C:expression}',
												'uuid' => generateUuidV4('drule5/Trigger C/{C:expression}')
											],
											[
												'name' => 'Trigger D',
												'expression' => '{D:expression}',
												'recovery_expression' => '{D:recovery_expression}',
												'uuid' => generateUuidV4('drule5/Trigger D/{D:expression}/{D:recovery_expression}')
											]
										],
										'uuid' => generateUuidV4('Template Q/drule5/item11')
									]
								],
								'uuid' => generateUuidV4('Template Q/drule5')
							]
						],
						'uuid' => generateUuidV4('Template Q')
					]
				]
			]
			];
	}

	protected function getGraphProtypeUuidData(): array {
		// Use "template name/discovery rule key/graph prototype name".
		return [
			[
				'templates' => [
					[
						'name' => 'Template R',
						'discovery_rules' => [
							[
								'key' => 'drule6',
								'graph_prototypes' => [
									['name' => 'Graph B']
								]
							]
						]
					],
					[
						'name' => 'Template OS Template S',
						'discovery_rules' => [
							[
								'key' => 'drule7',
								'graph_prototypes' => [
									['name' => 'Graph C']
								]
							]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template R',
						'discovery_rules' => [
							[
								'key' => 'drule6',
								'graph_prototypes' => [
									['name' => 'Graph B', 'uuid' => generateUuidV4('Template R/drule6/Graph B')]
								],
								'uuid' => generateUuidV4('Template R/drule6')
							]
						],
						'uuid' => generateUuidV4('Template R')
					],
					[
						'name' => 'Template OS Template S',
						'discovery_rules' => [
							[
								'key' => 'drule7',
								'graph_prototypes' => [
									['name' => 'Graph C', 'uuid' => generateUuidV4('Template S/drule7/Graph C')]
								],
								'uuid' => generateUuidV4('Template S/drule7')
							]
						],
						'uuid' => generateUuidV4('Template S')
					]
				]
			]
			];
	}

	protected function getHosthProtypeUuidData(): array {
		// Use "template name/discovery rule key/host prototype name".
		return [
			[
				'templates' => [
					[
						'name' => 'Template T',
						'discovery_rules' => [
							[
								'key' => 'drule8',
								'host_prototypes' => [
									['name' => 'Host B']
								]
							]
						]
					],
					[
						'name' => 'Template OS Template T',
						'discovery_rules' => [
							[
								'key' => 'drule9',
								'host_prototypes' => [
									['name' => 'Host C']
								]
							]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'name' => 'Template T',
						'discovery_rules' => [
							[
								'key' => 'drule8',
								'host_prototypes' => [
									['name' => 'Host B', 'uuid' => generateUuidV4('Template T/drule8/Host B')]
								],
								'uuid' => generateUuidV4('Template T/drule8')
							]
						],
						'uuid' => generateUuidV4('Template T')
					],
					[
						'name' => 'Template OS Template T',
						'discovery_rules' => [
							[
								'key' => 'drule9',
								'host_prototypes' => [
									['name' => 'Host C', 'uuid' => generateUuidV4('Template T/drule9/Host C')]
								],
								'uuid' => generateUuidV4('Template T/drule9')
							]
						],
						'uuid' => generateUuidV4('Template T')
					]
				]
			]
			];
	}

	protected function getHostUuidData(): array {
		// UUID should not be generated for host items
		return [
			[
				'hosts' => [
					[
						'name' => 'Host',
						'items' => [
							[
								'key' => 'item',
								'triggers' => [
									['name' => 'Trigger', 'expression' => '{expression}']
								],
								'valuemap' => ['name' => 'Value map']
							]
						],
						'httptests' => [
							['name' => 'HTTP Test']
						],
						'discovery_rules' => [
							[
								'key' => 'drule',
								'item_prototypes' => [
									[
										'key' => 'itemprototype',
										'trigger_prototypes' => [
											['name' => 'Trigger', 'expression' => '{expression}']
										],
										'graph_prototypes' => [
											['name' => 'Graph']
										]
									]
								],
								'host_prototypes' => [
									['name' => 'Host']
								]
							]
						]
					]
				],
				'value_maps' => [
					['name' => 'Value map', 'mappings' => []]
				]
			],
			[
				'hosts' => [
					[
						'name' => 'Host',
						'items' => [
							[
								'key' => 'item',
								'triggers' => [
									['name' => 'Trigger', 'expression' => '{expression}']
								],
								'valuemap' => ['name' => 'Value map']
							]
						],
						'httptests' => [
							['name' => 'HTTP Test']
						],
						'discovery_rules' => [
							[
								'key' => 'drule',
								'item_prototypes' => [
									[
										'key' => 'itemprototype',
										'trigger_prototypes' => [
											['name' => 'Trigger', 'expression' => '{expression}']
										],
										'graph_prototypes' => [
											['name' => 'Graph']
										]
									]
								],
								'host_prototypes' => [
									['name' => 'Host']
								]
							]
						],
						'valuemaps' => [
							['name' => 'Value map', 'mappings' => []]
						]
					]
				]
			]
		];
	}
	/**
	 * Testing data provider.
	 *
	 * @return array
	 */
	public function dataProviderConvert(): array {
		return [
			[
				[],
				[]
			],
			$this->getTemplateNameUuidData(),
			$this->getGroupsUuidData(),
			$this->getTemplateItemsUuidData(),
			$this->getTriggerUuidData(),
			$this->getGraphUuidData(),
			$this->getTemplateDashboardUuidData(),
			$this->getHttptestUuidData(),
			$this->getValuemapsUuidData(),
			$this->getDiscoveryRuleNameUuidData(),
			$this->getItemProtypeUuidData(),
			$this->getTriggerProtypeUuidData(),
			$this->getGraphProtypeUuidData(),
			$this->getHosthProtypeUuidData(),
			$this->getHostUuidData()
		];
	}

	/**
	 * @dataProvider dataProviderConvert
	 *
	 * @param array $data
	 * @param array $expected
	 */
	public function testConvert(array $data, array $expected) {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '5.2',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '5.4',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter() {
		return new C52ImportConverter();
	}
}
