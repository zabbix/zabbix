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


class C62ImportConverterTest extends CImportConverterTest {

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
				'version' => '6.2',
				'date' => '2023-09-04T18:00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '6.4'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source): void {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter(): C62ImportConverter {
		return new C62ImportConverter();
	}
}
