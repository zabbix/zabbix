<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class C20ImportConverterTest extends CImportConverterTest {

	public function testConvertHosts() {
		$source = $this->createSource([
			'hosts' => [
				[],
				[
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[],
				[
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'bulk' => SNMP_BULK_ENABLED
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'bulk' => SNMP_BULK_ENABLED
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertItems() {
		$source = $this->createSource([
			'hosts' => [
				[],
				[
					'items' => ''
				],
				[
					'items' => [
						[],
						[
							'status' => ITEM_STATUS_NOTSUPPORTED
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[],
				[
					'items' => ''
				],
				[
					'items' => [
						[],
						[
							'status' => ITEM_STATUS_ACTIVE
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggers() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource([])
		);
		$this->assertConvert(
			$this->createExpectedResult(['triggers' => '']),
			$this->createSource(['triggers' => ''])
		);

		$source = $this->createSource([
			'triggers' => [
				[
					'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'triggers' => [
				[
					'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertDiscoveryRules() {
		$source = $this->createSource([
			'hosts' => [
				[],
				[
					'discovery_rules' => ''
				],
				[
					'discovery_rules' => [
						[],
						[
							'status' => ITEM_STATUS_NOTSUPPORTED,
							'filter' => []
						],
						[
							'filter' => ''
						],
						[
							'filter' => ':'
						],
						[
							'filter' => '{#MACRO}:regex'
						],
					]
				]
			],
			'templates' => [
				[
					'discovery_rules' => [
						[],
						[
							'filter' => []
						],
						[
							'filter' => ''
						],
						[
							'filter' => ':'
						],
						[
							'filter' => '{#MACRO}:regex'
						],
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[],
				[
					'discovery_rules' => ''
				],
				[
					'discovery_rules' => [
						[],
						[
							'status' => ITEM_STATUS_ACTIVE,
							'filter' => []
						],
						[
							'filter' => ''
						],
						[],
						[
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => 'regex',
										'operator' => CONDITION_OPERATOR_REGEXP,
									]
								]
							]
						]
					]
				]
			],
			'templates' => [
				[
					'discovery_rules' => [
						[],
						[
							'filter' => []
						],
						[
							'filter' => ''
						],
						[],
						[
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => 'regex',
										'operator' => CONDITION_OPERATOR_REGEXP,
									]
								]
							]
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggerPrototypes() {
		$source = $this->createSource([
			'hosts' => [
				[
					'discovery_rules' => [
						[],
						[
							'trigger_prototypes' => ''
						],
						[
							'trigger_prototypes' => [
								[
									'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
								]
							]
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'discovery_rules' => [
						[],
						[
							'trigger_prototypes' => ''
						],
						[
							'trigger_prototypes' => [
								[
									'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
								]
							]
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '2.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '3.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expectedResult, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expectedResult, $result);
	}


	protected function createConverter() {
		return new C20ImportConverter(new C20TriggerConverter());
	}

}
