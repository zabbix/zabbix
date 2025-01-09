<?php declare(strict_types = 0);
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


class C54ImportConverterTest extends CImportConverterTest {

	public function importConverterDataProvider(): array {
		$simple_macros_source = '{Zabbix server:system.hostname.last()}'.
			'{Zabbix server:system.hostname.last(0)}{{HOST.HOST}:system.hostname.min(1s)}'.
			'{{HOST.HOST1}:system.hostname.max(1m)}{{HOST.HOST2}:system.hostname.avg(1h)}'.
			'{{HOSTNAME}:system.hostname.min(1d)}{{HOSTNAME1}:system.hostname.max(24h)}'.
			'{{HOSTNAME1}:system.hostname.max(24h,)}{{HOSTNAME2}:system.hostname.avg(3600)}';
		$simple_macros_expected = '{?last(/Zabbix server/system.hostname)}'.
			'{?last(/Zabbix server/system.hostname)}{?min(/'.'/system.hostname,1s)}'.
			'{?max(/'.'/system.hostname,1m)}{?avg(/{HOST.HOST2}/system.hostname,1h)}'.
			'{?min(/'.'/system.hostname,1d)}{?max(/'.'/system.hostname,24h)}'.
			'{?max(/'.'/system.hostname,24h)}{?avg(/{HOST.HOST2}/system.hostname,3600s)}';

		return [
			[
				[],
				[]
			],
			[
				[
					'hosts' => [
						[
							'host' => 'Zabbix server',
							'groups' => [
								['name' => 'Zabbix servers']
							],
							'discovery_rules' => [
								[
									'name' => 'Mounted filesystem discovery',
									'key' => 'vfs.fs.discovery',
									'graph_prototypes' => [
										[
											'name' => $simple_macros_source
										]
									]
								]
							]
						]
					],
					'graphs' => [
						[
							'name' => $simple_macros_source
						]
					],
					'maps' => [
						[
							'label_string_host' => $simple_macros_source,
							'label_string_hostgroup' => $simple_macros_source,
							'label_string_trigger' => $simple_macros_source,
							'label_string_map' => $simple_macros_source,
							'label_string_image' => $simple_macros_source,
							'selements' => [
								[
									'label' => $simple_macros_source
								]
							],
							'shapes' => [
								[
									'text' => $simple_macros_source
								]
							],
							'links' => [
								[
									'label' => $simple_macros_source
								]
							]
						]
					],
					'media_types' => [
						[
							'message_templates' => [
								[
									'subject' => $simple_macros_source,
									'message' => $simple_macros_source
								]
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'host' => 'Zabbix server',
							'groups' => [
								['name' => 'Zabbix servers']
							],
							'discovery_rules' => [
								[
									'name' => 'Mounted filesystem discovery',
									'key' => 'vfs.fs.discovery',
									'graph_prototypes' => [
										[
											'name' => $simple_macros_expected
										]
									]
								]
							]
						]
					],
					'graphs' => [
						[
							'name' => $simple_macros_expected
						]
					],
					'maps' => [
						[
							'label_string_host' => $simple_macros_expected,
							'label_string_hostgroup' => $simple_macros_expected,
							'label_string_trigger' => $simple_macros_expected,
							'label_string_map' => $simple_macros_expected,
							'label_string_image' => $simple_macros_expected,
							'selements' => [
								[
									'label' => $simple_macros_expected
								]
							],
							'shapes' => [
								[
									'text' => $simple_macros_expected
								]
							],
							'links' => [
								[
									'label' => $simple_macros_expected
								]
							]
						]
					],
					'media_types' => [
						[
							'message_templates' => [
								[
									'subject' => $simple_macros_expected,
									'message' => $simple_macros_expected
								]
							]
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'host' => 'Zabbix server',
							'groups' => [
								['name' => 'Zabbix servers']
							],
							'items' => [
								[
									'name' => 'Item1',
									'type' => CXmlConstantName::TRAP,
									'key' => 'item1',
									'preprocessing' => [
										[
											'type' => CXmlConstantName::PROMETHEUS_PATTERN,
											'parameters' => [
												'metric',
												'my_label'
											]
										]
									]
								],
								[
									'name' => 'Item2',
									'type' => CXmlConstantName::TRAP,
									'key' => 'item2',
									'preprocessing' => [
										[
											'type' => CXmlConstantName::PROMETHEUS_PATTERN,
											'parameters' => [
												'metric',
												''
											]
										]
									]
								]
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'host' => 'Zabbix server',
							'groups' => [
								['name' => 'Zabbix servers']
							],
							'items' => [
								[
									'name' => 'Item1',
									'type' => CXmlConstantName::TRAP,
									'key' => 'item1',
									'preprocessing' => [
										[
											'type' => CXmlConstantName::PROMETHEUS_PATTERN,
											'parameters' => [
												'metric',
												ZBX_PREPROC_PROMETHEUS_LABEL,
												'my_label'
											]
										]
									]
								],
								[
									'name' => 'Item2',
									'type' => CXmlConstantName::TRAP,
									'key' => 'item2',
									'preprocessing' => [
										[
											'type' => CXmlConstantName::PROMETHEUS_PATTERN,
											'parameters' => [
												'metric',
												ZBX_PREPROC_PROMETHEUS_VALUE,
												''
											]
										]
									]
								]
							]
						]
					]
				]
			]
		];
	}

	public function importConverterDataProviderCalcItemFormula(): array {
		$formulas = [
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],0s))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 0m))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 0h ))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 0d ))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"],  0w ))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"]))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"]))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"]))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], 15s))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], 15s))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], {$MACRO}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], {$MACRO}))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], "{$MACRO: context}"))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 1h ))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], "{$MACRO: context}"))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 1h ))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],{#LLD}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],{#LLD}))',
				'prototype' => true
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],  {#LLD}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],  {#LLD}))',
				'prototype' => true
			]
		];

		$source_items = [];
		$expected_items = [];
		$source_item_prototypes = [];
		$expected_item_prototypes = [];

		foreach ($formulas as $formula) {
			if (!$formula['prototype']) {
				$source_items[] = ['type' => CXmlConstantName::CALCULATED, 'params' => $formula['source']];
				$expected_items[] = ['type' => CXmlConstantName::CALCULATED, 'params' => $formula['expected']];
			}
			$source_item_prototypes[] = ['type' => CXmlConstantName::CALCULATED, 'params' => $formula['source']];
			$expected_item_prototypes[] = ['type' => CXmlConstantName::CALCULATED, 'params' => $formula['expected']];
		}

		return [
			[
				[
					'templates' => [
						[
							'items' => $source_items,
							'discovery_rules' => [
								[
									'type' => CXmlConstantName::ZABBIX_PASSIVE,
									'item_prototypes' => $source_item_prototypes
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => $source_items,
							'discovery_rules' => [
								[
									'type' => CXmlConstantName::ZABBIX_PASSIVE,
									'item_prototypes' => $source_item_prototypes
								]
							]
						]
					]
				],
				[
					'templates' => [
						[
							'items' => $expected_items,
							'discovery_rules' => [
								[
									'type' => CXmlConstantName::ZABBIX_PASSIVE,
									'item_prototypes' => $expected_item_prototypes
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => $expected_items,
							'discovery_rules' => [
								[
									'type' => CXmlConstantName::ZABBIX_PASSIVE,
									'item_prototypes' => $expected_item_prototypes
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider importConverterDataProvider
	 * @dataProvider importConverterDataProviderCalcItemFormula
	 *
	 * @param array $data
	 * @param array $expected
	 */
	public function testConvert(array $data, array $expected): void {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '5.4',
				'date' => '2021-06-01T00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '6.0',
				'date' => '2021-06-01T00:00:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source): void {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter(): C54ImportConverter {
		return new C54ImportConverter();
	}
}
