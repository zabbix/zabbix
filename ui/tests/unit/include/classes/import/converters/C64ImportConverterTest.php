<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

	public function importConverterDataProviderItemTimeout(): array {
		$item_types = [
			CXmlConstantName::CALCULATED, CXmlConstantName::DEPENDENT, CXmlConstantName::EXTERNAL,
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

			$source_items[] = $source_item + [
				'preprocessing' => [
					[
						'type' => CXmlConstantName::CHECK_NOT_SUPPORTED
					],
					[
						'type' => CXmlConstantName::CHECK_NOT_SUPPORTED,
						'parameters' => ''
					]
				]
			];
			$expected_items[] = $expected_item + [
				'preprocessing' => [
					[
						'type' => CXmlConstantName::CHECK_NOT_SUPPORTED,
						'parameters' => [(string) ZBX_PREPROC_MATCH_ERROR_ANY]
					],
					[
						'type' => CXmlConstantName::CHECK_NOT_SUPPORTED,
						'parameters' => [(string) ZBX_PREPROC_MATCH_ERROR_ANY]
					]
				]
			];
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

	public function importConverterDataProviderExpressionHistoryFunction(): array {
		$expression_simple = 'last(/host/key)';
		$expression_macro_simple = '{?'.$expression_simple.'}';

		$source_expression = 'find(/host/key,10m,"iregexp","\.+\\\"[a-z0-9]+")';
		$source_expression_empty_host = 'find(//key,10m,"iregexp","\.+\\\"[a-z0-9]+")';
		$source_expression_host_macro = 'find(/{HOST.HOST}/key,10m,"iregexp","\.+\\\"[a-z0-9]+")';
		$source_expression_macro = '{?'.$source_expression.'}';
		$source_expression_macro_function = '{'.$source_expression_macro.'.regsub("(.*)_([0-9]+)", \2)}';
		$source_expression_macro_empty_host = '{?'.$source_expression_empty_host.'}';
		$source_expression_macro_host_macro = '{?'.$source_expression_host_macro.'}';

		$expected_expression = 'find(/host/key,10m,"iregexp","\\\.+\\\\\"[a-z0-9]+")';
		$expected_expression_empty_host = 'find(//key,10m,"iregexp","\\\.+\\\\\"[a-z0-9]+")';
		$expected_expression_host_macro = 'find(/{HOST.HOST}/key,10m,"iregexp","\\\.+\\\\\"[a-z0-9]+")';
		$expected_expression_macro = '{?'.$expected_expression.'}';
		$expected_expression_macro_function = '{'.$expected_expression_macro.'.regsub("(.*)_([0-9]+)", \2)}';
		$expected_expression_macro_empty_host = '{?'.$expected_expression_empty_host.'}';
		$expected_expression_macro_host_macro = '{?'.$expected_expression_host_macro.'}';

		$source_text = 'prefix'.$expression_macro_simple.$source_expression_macro.
			$source_expression_macro_function.$source_expression_macro_empty_host.
			$source_expression_macro_host_macro.'suffix';
		$expected_text = 'prefix'.$expression_macro_simple.$expected_expression_macro.
			$expected_expression_macro_function.$expected_expression_macro_empty_host.
			$expected_expression_macro_host_macro.'suffix';

		$source_triggers = [
			[
				'expression' => $source_expression,
				'recovery_expression' => $source_expression,
				'event_name' => $source_text,
				'dependencies' => [
					[
						'expression' => $source_expression,
						'recovery_expression' => $source_expression
					]
				]
			]
		];
		$expected_triggers = [
			[
				'expression' => $expected_expression,
				'recovery_expression' => $expected_expression,
				'event_name' => $expected_text,
				'dependencies' => [
					[
						'expression' => $expected_expression,
						'recovery_expression' => $expected_expression
					]
				]
			]
		];

		$source_items = [
			[
				'type' => CXmlConstantName::CALCULATED,
				'params' => $source_expression,
				'triggers' => $source_triggers
			]
		];
		$expected_items = [
			[
				'type' => CXmlConstantName::CALCULATED,
				'params' => $expected_expression,
				'triggers' => $expected_triggers
			]
		];

		$source_lld_rules = [
			[
				'type' => CXmlConstantName::ZABBIX_PASSIVE,
				'item_prototypes' => [
					[
						'type' => CXmlConstantName::CALCULATED,
						'params' => $source_expression,
						'trigger_prototypes' => $source_triggers
					]
				],
				'trigger_prototypes' => $source_triggers
			]
		];
		$expected_lld_rules = [
			[
				'type' => CXmlConstantName::ZABBIX_PASSIVE,
				'item_prototypes' => [
					[
						'type' => CXmlConstantName::CALCULATED,
						'params' => $expected_expression,
						'trigger_prototypes' => $expected_triggers
					]
				],
				'trigger_prototypes' => $expected_triggers
			]
		];

		$source_maps = [
			[
				'selements' => [
					[
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'elements' => [
							[
								'expression' => $source_expression,
								'recovery_expression' => $source_expression
							]
						]
					]
				],
				'links' => [
					[
						'linktriggers' => [
							[
								'trigger' => [
									'expression' => $source_expression,
									'recovery_expression' => $source_expression
								]
							]
						]
					]
				]
			]
		];
		$expected_maps = [
			[
				'selements' => [
					[
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'elements' => [
							[
								'expression' => $expected_expression,
								'recovery_expression' => $expected_expression
							]
						]
					]
				],
				'links' => [
					[
						'linktriggers' => [
							[
								'trigger' => [
									'expression' => $expected_expression,
									'recovery_expression' => $expected_expression
								]
							]
						]
					]
				]
			]
		];

		$source_mediatypes = [
			[
				'type' => CXmlConstantName::SCRIPT,
				'parameters' => [
					[
						'value' => $source_text
					]
				],
				'message_templates' => [
					[
						'subject' => $source_text,
						'message' => $source_text
					]
				]
			],
			[
				'type' => CXmlConstantName::WEBHOOK,
				'parameters' => [
					[
						'name' => $source_text,
						'value' => $source_text
					]
				],
				'message_templates' => [
					[
						'subject' => $source_text,
						'message' => $source_text
					]
				]
			]
		];
		$expected_mediatypes = [
			[
				'type' => CXmlConstantName::SCRIPT,
				'parameters' => [
					[
						'value' => $expected_text
					]
				],
				'message_templates' => [
					[
						'subject' => $expected_text,
						'message' => $expected_text
					]
				]
			],
			[
				'type' => CXmlConstantName::WEBHOOK,
				'parameters' => [
					[
						'name' => $expected_text,
						'value' => $expected_text
					]
				],
				'message_templates' => [
					[
						'subject' => $expected_text,
						'message' => $expected_text
					]
				]
			]
		];

		return [
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
					],
					'triggers' => $source_triggers,
					'maps' => $source_maps,
					'media_types' => $source_mediatypes
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
					],
					'triggers' => $expected_triggers,
					'maps' => $expected_maps,
					'media_types' => $expected_mediatypes
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
	 * @dataProvider importConverterDataProviderItemTimeout
	 * @dataProvider importConverterDataProviderExpressionHistoryFunction
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
				'version' => '6.4'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '7.0'
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
