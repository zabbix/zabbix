<?php declare(strict_types = 0);
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


class C64ImportConverterTest extends CImportConverterTest {

	public function importConverterDataProvider(): array {
		$item_types = [CXmlConstantName::CALCULATED, CXmlConstantName::DEPENDENT, CXmlConstantName::EXTERNAL,
			CXmlConstantName::HTTP_AGENT, CXmlConstantName::INTERNAL, CXmlConstantName::IPMI, CXmlConstantName::JMX,
			CXmlConstantName::ODBC, CXmlConstantName::SCRIPT, CXmlConstantName::SIMPLE, CXmlConstantName::SNMP_AGENT,
			CXmlConstantName::SNMP_TRAP, CXmlConstantName::SSH, CXmlConstantName::TELNET,
			CXmlConstantName::ZABBIX_ACTIVE, CXmlConstantName::TRAP, CXmlConstantName::ZABBIX_PASSIVE
		];

		$source_items = [];
		$source_lld_rules = [];
		$expected_items = [];
		$expected_lld_rules = [];

		foreach ($item_types as $type) {
			$source_item = [
				'type' => $type,
				'timeout' => '3s'
			];
			$expected_item = [
				'type' => $type
			];

			if ($type === CXmlConstantName::HTTP_AGENT || $type === CXmlConstantName::SCRIPT) {
				$expected_item['timeout'] = '3s';
			}

			if ($type !== CXmlConstantName::CALCULATED && $type !== CXmlConstantName::SNMP_TRAP) {
				$source_lld_rules[] = $source_item;
				$expected_lld_rules[] = $expected_item;
			}

			$source_items[] = $source_item;
			$expected_items[] = $expected_item;
		}

		foreach ($source_lld_rules as &$lld_rule) {
			$lld_rule['item_prototypes'] = $source_items;
		}
		unset($lld_rule);

		foreach ($expected_lld_rules as &$lld_rule) {
			$lld_rule['item_prototypes'] = $expected_items;
		}
		unset($lld_rule);

		return [
			[
				[],
				[]
			],
			[
				[
					'templates' => [
						[
							'items' => $source_items,
							'discovery_rules' => $source_lld_rules
						]
					],
					'hosts' => [
						[
							'items' => $source_items,
							'discovery_rules' => $source_lld_rules
						]
					]
				],
				[
					'templates' => [
						[
							'items' => $expected_items,
							'discovery_rules' => $expected_lld_rules
						]
					],
					'hosts' => [
						[
							'items' => $expected_items,
							'discovery_rules' => $expected_lld_rules
						]
					]
				]
			]
		];
	}

	public function importConverterDataProviderCalcItemFormula(): array {
		$formulas = [
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],0s))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], 15s))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], {$MACRO}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"], "{$MACRO: context}"))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"], 1h ))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))'.
					' or sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => false
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],{#LLD}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))',
				'prototype' => true
			],
			[
				'source' => 'sum(last_foreach(/*/key?[group="Zabbix servers"],  {#LLD}))',
				'expected' => 'sum(last_foreach(/*/key?[group="Zabbix servers"]))',
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
				'version' => '6.4',
				'date' => '2023-09-04T18:00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '7.0',
				'date' => '2023-09-04T18:00:00:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source): void {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter(): C64ImportConverter {
		return new C64ImportConverter();
	}
}
