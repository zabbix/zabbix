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

	public function testConvertProvider() {
		return [
			[
				[],
				[]
			],
			[
				[
					'hosts' => [
						[],
						[
							'interfaces' => [
								['type' => INTERFACE_TYPE_AGENT],
								['type' => INTERFACE_TYPE_SNMP]
							]
						],
						[
							'items' => [
								['key' => 'agent.ping', 'status' => ITEM_STATUS_ACTIVE],
								['key' => 'net.tcp.service[ntp]', 'status' => ITEM_STATUS_DISABLED],
								['key' => 'net.tcp.service[tcp,,5432]', 'status' => ITEM_STATUS_NOTSUPPORTED]
							]
						],
						[
							'discovery_rules' => [
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
										]
									]
								],
								[
									'status' => ITEM_STATUS_DISABLED,
									'filter' => ''
								],
								[
									'status' => ITEM_STATUS_NOTSUPPORTED,
									'filter' => ':'
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => '{#MACRO}:regex'
								],
							]
						]
					]
				],
				[
					'hosts' => [
						[],
						[
							'interfaces' => [
								['type' => INTERFACE_TYPE_AGENT],
								['type' => INTERFACE_TYPE_SNMP, 'bulk' => SNMP_BULK_ENABLED]
							]
						],
						[
							'items' => [
								['key' => 'agent.ping', 'status' => ITEM_STATUS_ACTIVE],
								['key' => 'net.udp.service[ntp]', 'status' => ITEM_STATUS_DISABLED],
								['key' => 'net.tcp.service[tcp,,5432]', 'status' => ITEM_STATUS_ACTIVE]
							]
						],
						[
							'discovery_rules' => [
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
										]
									],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_DISABLED,
									'filter' => [],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
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
									],
									'host_prototypes' => []
								]
							]
						]
					]
				]
			],
			[
				[
					'triggers' => [
						[
							'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
						]
					]
				],
				[
					'triggers' => [
						[
							'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
						]
					]
				]
			],
			[
				[
					'templates' => [
						[],
						[
							'items' => [
								['key' => 'agent.ping', 'status' => ITEM_STATUS_ACTIVE],
								['key' => 'net.tcp.service[ntp]', 'status' => ITEM_STATUS_DISABLED],
								['key' => 'net.tcp.service[tcp,,5432]', 'status' => ITEM_STATUS_NOTSUPPORTED]
							]
						],
						[
							'discovery_rules' => [
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
										]
									]
								],
								[
									'status' => ITEM_STATUS_DISABLED,
									'filter' => ''
								],
								[
									'status' => ITEM_STATUS_NOTSUPPORTED,
									'filter' => ':'
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => '{#MACRO}:regex'
								],
							]
						]
					]
				],
				[
					'templates' => [
						[],
						[
							'items' => [
								['key' => 'agent.ping', 'status' => ITEM_STATUS_ACTIVE],
								['key' => 'net.udp.service[ntp]', 'status' => ITEM_STATUS_DISABLED],
								['key' => 'net.tcp.service[tcp,,5432]', 'status' => ITEM_STATUS_ACTIVE]
							]
						],
						[
							'discovery_rules' => [
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
										]
									],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_DISABLED,
									'filter' => [],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'host_prototypes' => []
								],
								[
									'status' => ITEM_STATUS_ACTIVE,
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
									],
									'host_prototypes' => []
								]
							]
						]
					]
				],
				[
					'screens' => [
						[],
						[
							'screen_items' => [
								[
									'rowspan' => 0,
									'colspan' => 0
								],
								[
									'rowspan' => 1,
									'colspan' => 2
								],
								[
									'rowspan' => 3,
									'colspan' => 4
								]
							]
						]
					]
				],
				[
					'screens' => [
						[],
						[
							'screen_items' => [
								[
									'rowspan' => 1,
									'colspan' => 1
								],
								[
									'rowspan' => 1,
									'colspan' => 2
								],
								[
									'rowspan' => 3,
									'colspan' => 4
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider testConvertProvider
	 *
	 * @param $data
	 * @param $expected
	 */
	public function testConvert(array $data, array $expected) {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
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

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}


	protected function createConverter() {
		return new C20ImportConverter(new C20TriggerConverter());
	}

}
