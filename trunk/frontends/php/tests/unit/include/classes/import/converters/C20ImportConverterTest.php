<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
									'type' => ITEM_TYPE_SNMPV1,
									'snmp_oid' => 'ifDescr',
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'item_prototypes' => [
										[
											'key' => 'net.if.in[{#IFNAME}]'
										],
										[
											'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
										]
									],
									'graph_prototypes' => [
										[
											'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymin_item_1' => [
												'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
											],
											'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymax_item_1' => [
												'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
											],
											'graph_items' => [
												[
													'item' => [
														'key' => 'net.if.in[{#IFNAME}]'
													]
												],
												[
													'item' => [
														'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
													]
												]
											]
										]
									],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
										]
									]
								],
								[
									'type' => ITEM_TYPE_SNMPV2C,
									'snmp_oid' => 'a,b,c',
									'status' => ITEM_STATUS_DISABLED,
									'filter' => '',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_SNMPV3,
									'snmp_oid' => ',c:\\',
									'status' => ITEM_STATUS_NOTSUPPORTED,
									'filter' => ':',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => '{#MACRO}:regex',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
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
									'type' => ITEM_TYPE_SNMPV1,
									'snmp_oid' => 'discovery[{#SNMPVALUE},ifDescr]',
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'item_prototypes' => [
										[
											'key' => 'net.if.in[{#IFNAME}]'
										],
										[
											'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
										]
									],
									'graph_prototypes' => [
										[
											'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymin_item_1' => [
												'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
											],
											'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymax_item_1' => [
												'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
											],
											'graph_items' => [
												[
													'item' => [
														'key' => 'net.if.in[{#IFNAME}]'
													]
												],
												[
													'item' => [
														'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
													]
												]
											]
										]
									],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
										]
									],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_SNMPV2C,
									'snmp_oid' => 'discovery[{#SNMPVALUE},"a,b,c"]',
									'status' => ITEM_STATUS_DISABLED,
									'filter' => [
										'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
										'formula' => '',
										'conditions' => []
									],
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_SNMPV3,
									'snmp_oid' => ',c:\\',
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [
										'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
										'formula' => '',
										'conditions' => []
									],
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
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
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								]
							]
						]
					]
				]
			],
			[
				[
					'graphs' => [
						[
							'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
							'ymin_item_1' => [
								'key' => 'net.tcp.service[ntp, localhost, 123]'
							],
							'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
							'ymax_item_1' => [
								'key' => 'net.tcp.service[ntp, localhost, 123]'
							],
							'graph_items' => [
								[
									'item' => [
										'key' => 'net.if.in[eth0]'
									]
								],
								[
									'item' => [
										'key' => 'net.tcp.service[ntp, localhost, 123]'
									]
								]
							]
						]
					]
				],
				[
					'graphs' => [
						[
							'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
							'ymin_item_1' => [
								'key' => 'net.udp.service[ntp, localhost, 123]'
							],
							'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
							'ymax_item_1' => [
								'key' => 'net.udp.service[ntp, localhost, 123]'
							],
							'graph_items' => [
								[
									'item' => [
										'key' => 'net.if.in[eth0]'
									]
								],
								[
									'item' => [
										'key' => 'net.udp.service[ntp, localhost, 123]'
									]
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
							'screens' => [
								[],
								[
									'screen_items' => [
										[
											'rowspan' => 0,
											'colspan' => 0,
											'resourcetype' => SCREEN_RESOURCE_SIMPLE_GRAPH,
											'resource' => [
												'key' => 'net.tcp.service[ntp]'
											]
										],
										[
											'rowspan' => 1,
											'colspan' => 2,
											'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
											'resource' => [
												'key' => 'net.tcp.service[ntp]'
											]
										],
										[
											'rowspan' => 3,
											'colspan' => 4,
											'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
											'resource' => [
												'key' => 'net.tcp.service[tcp,,5432]'
											]
										]
									]
								]
							]
						],
						[
							'discovery_rules' => [
								[
									'type' => ITEM_TYPE_SNMPV3,
									'snmp_oid' => 'ifDescr\\',
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'item_prototypes' => [
										[
											'key' => 'net.if.in[{#IFNAME}]'
										],
										[
											'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
										]
									],
									'graph_prototypes' => [
										[
											'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymin_item_1' => [
												'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
											],
											'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymax_item_1' => [
												'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
											],
											'graph_items' => [
												[
													'item' => [
														'key' => 'net.if.in[{#IFNAME}]'
													]
												],
												[
													'item' => [
														'key' => 'net.tcp.service[ntp, {#HOST}, {#PORT}]'
													]
												]
											]
										]
									],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
										]
									]
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_DISABLED,
									'filter' => '',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_NOTSUPPORTED,
									'filter' => ':',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => '{#MACRO}:regex',
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => []
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
							'screens' => [
								[],
								[
									'screen_items' => [
										[
											'rowspan' => 1,
											'colspan' => 1,
											'resourcetype' => SCREEN_RESOURCE_SIMPLE_GRAPH,
											'resource' => [
												'key' => 'net.udp.service[ntp]'
											]
										],
										[
											'rowspan' => 1,
											'colspan' => 2,
											'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
											'resource' => [
												'key' => 'net.udp.service[ntp]'
											]
										],
										[
											'rowspan' => 3,
											'colspan' => 4,
											'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
											'resource' => [
												'key' => 'net.tcp.service[tcp,,5432]'
											]
										]
									]
								]
							]
						],
						[
							'discovery_rules' => [
								[
									'type' => ITEM_TYPE_SNMPV3,
									'snmp_oid' => 'discovery[{#SNMPVALUE},ifDescr\\]',
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [],
									'item_prototypes' => [
										[
											'key' => 'net.if.in[{#IFNAME}]'
										],
										[
											'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
										]
									],
									'graph_prototypes' => [
										[
											'ymin_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymin_item_1' => [
												'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
											],
											'ymax_type_1' => GRAPH_YAXIS_TYPE_ITEM_VALUE,
											'ymax_item_1' => [
												'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
											],
											'graph_items' => [
												[
													'item' => [
														'key' => 'net.if.in[{#IFNAME}]'
													]
												],
												[
													'item' => [
														'key' => 'net.udp.service[ntp, {#HOST}, {#PORT}]'
													]
												]
											]
										]
									],
									'trigger_prototypes' => [
										[
											'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
										]
									],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_DISABLED,
									'filter' => [
										'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
										'formula' => '',
										'conditions' => []
									],
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
									'status' => ITEM_STATUS_ACTIVE,
									'filter' => [
										'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
										'formula' => '',
										'conditions' => []
									],
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								],
								[
									'type' => ITEM_TYPE_ZABBIX,
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
									'item_prototypes' => [],
									'graph_prototypes' => [],
									'trigger_prototypes' => [],
									'host_prototypes' => []
								]
							]
						]
					]
				]
			],
			[
				[
					'screens' => [
						[],
						[
							'screen_items' => [
								[
									'rowspan' => 0,
									'colspan' => 0,
									'resourcetype' => SCREEN_RESOURCE_SIMPLE_GRAPH,
									'resource' => [
										'key' => 'net.tcp.service[ntp]'
									]
								],
								[
									'rowspan' => 1,
									'colspan' => 2,
									'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
									'resource' => [
										'key' => 'net.tcp.service[ntp]'
									]
								],
								[
									'rowspan' => 3,
									'colspan' => 4,
									'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
									'resource' => [
										'key' => 'net.tcp.service[tcp,,5432]'
									]
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
									'colspan' => 1,
									'resourcetype' => SCREEN_RESOURCE_SIMPLE_GRAPH,
									'resource' => [
										'key' => 'net.udp.service[ntp]'
									]
								],
								[
									'rowspan' => 1,
									'colspan' => 2,
									'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
									'resource' => [
										'key' => 'net.udp.service[ntp]'
									]
								],
								[
									'rowspan' => 3,
									'colspan' => 4,
									'resourcetype' => SCREEN_RESOURCE_PLAIN_TEXT,
									'resource' => [
										'key' => 'net.tcp.service[tcp,,5432]'
									]
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
