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


class C52ImportConverterTest extends CImportConverterTest {

	public function importConvertProviderData(): array {
		$calculated = ['type' => CXmlConstantName::CALCULATED, 'key' => ''];

		return [
			[
				[],
				[]
			],
			[
				[
					'templates' => [
						[
							'template' => 'Template',
							'items' => [
								$calculated + ['params' => '100*last("vfs.fs.size[/,free]")/last("vfs.fs.size[/,total]")'],
								$calculated + ['params' => 'avg("Zabbix Server:zabbix[wcache,values]",600)'],
								$calculated + ['params' => 'last("net.if.in[eth0,bytes]")+last("net.if.out[eth0,bytes]")'],
								$calculated + ['params' => '100*last("net.if.in[eth0,bytes]")/(last("net.if.in[eth0,bytes]")+last("net.if.out[eth0,bytes]"))'],
								$calculated + ['params' => 'last("grpsum[\\"video\\",\\"net.if.out[eth0,bytes]\\",\\"last\\"]") / last("grpsum[\\"video\\",\\"nginx_stat.sh[active]\\",\\"last\\"]")'],
								$calculated + ['params' => 'last(es.node.indices.flush.total_time_in_millis[{#ES.NODE}]) / ( last(es.node.indices.flush.total[{#ES.NODE}]) + (last(es.node.indices.flush.total[{#ES.NODE}]) = 0) )'],
								$calculated + ['params' => 'last(haproxy.frontend.scur[{#PXNAME}:{#SVNAME}]) / last(haproxy.frontend.slim[{#PXNAME}:{#SVNAME}]) * 100'],
								$calculated + ['params' => 'last(php-fpm.listen_queue)/(last(php-fpm.listen_queue_len)+last(php-fpm.listen_queue_len)=0)*100'],
								$calculated + ['params' => 'last("vm.memory.used[hrStorageUsed.{#SNMPINDEX}]")/last("vm.memory.total[hrStorageSize.{#SNMPINDEX}]")*100'],
								$calculated + ['params' => 'last("system.swap.size[,total]") - last("system.swap.size[,total]") / 100 * last("perf_counter_en[\\"\\Paging file(_Total)\\% Usage\\"]")'],
								$calculated + ['params' => 'avg("zbxnext_6451:'."\n\r\n".'agent_numeric[wcache,values]",600)'],
								$calculated + ['params' => 'abschange("trap1") + avg(trap1,1h,1d) + band(trap1,,12)=4 + count(trap1,10m) + count(trap1,10m,"error",eq) + count(trap1,10m,12) + count(trap1, 10m,12,gt) + count(trap1, #10,12,gt) + count(trap1, 10m,12,gt,1d) + count(trap1,10m,6/7,band) + count(trap1, 10m,,,1d) + count(trap1,10m,"56",eq) + count("Zabbix server:trap3",10m,error,eq) + date("trap1") + dayofmonth(trap1) + dayofweek(trap1) + delta(trap1,30s) + diff(trap1) + forecast(trap1,#10,,1h) + forecast(trap1,1h,,30m) + forecast(trap1,1h,1d,12h) + forecast(trap1,1h,,10m,exponential) + forecast(trap1,1h,,2h,polynomial3,max) + fuzzytime(trap1,40) + count(trap2,10m,56,eq)'],
								$calculated + ['params' => 'band(trap1,,12)']
							]
						]
					]
				],
				[
					'templates' => [
						[
							'template' => 'Template',
							'uuid' => generateUuidV4('Template'),
							'items' => [
								$calculated + [
									'params' => '100*last(/'.'/vfs.fs.size[/,free])/last(/'.'/vfs.fs.size[/,total])',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'avg(/Zabbix Server/zabbix[wcache,values],600s)',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes])',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => '100*last(/'.'/net.if.in[eth0,bytes])/(last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes]))',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/grpsum["video","net.if.out[eth0,bytes]","last"]) / last(/'.'/grpsum["video","nginx_stat.sh[active]","last"])',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/es.node.indices.flush.total_time_in_millis[{#ES.NODE}]) / ( last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) + (last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) = 0) )',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/haproxy.frontend.scur[{#PXNAME}:{#SVNAME}]) / last(/'.'/haproxy.frontend.slim[{#PXNAME}:{#SVNAME}]) * 100',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/php-fpm.listen_queue)/(last(/'.'/php-fpm.listen_queue_len)+last(/'.'/php-fpm.listen_queue_len)=0)*100',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/vm.memory.used[hrStorageUsed.{#SNMPINDEX}])/last(/'.'/vm.memory.total[hrStorageSize.{#SNMPINDEX}])*100',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'last(/'.'/system.swap.size[,total]) - last(/'.'/system.swap.size[,total]) / 100 * last(/'.'/perf_counter_en["\\Paging file(_Total)\% Usage"])',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'avg(/zbxnext_6451/agent_numeric[wcache,values],600s)',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'abs(change(/'.'/trap1)) + avg(/'.'/trap1,1h:now-1d) + bitand(last(/'.'/trap1),12)=4 + count(/'.'/trap1,10m) + count(/'.'/trap1,10m,"eq","error") + count(/'.'/trap1,10m,,"12") + count(/'.'/trap1,10m,"gt","12") + count(/'.'/trap1,#10,"gt","12") + count(/'.'/trap1,10m:now-1d,"gt","12") + count(/'.'/trap1,10m,"bitand","6/7") + count(/'.'/trap1,10m:now-1d) + count(/'.'/trap1,10m,"eq","56") + count(/Zabbix server/trap3,10m,"eq","error") + date() + dayofmonth() + dayofweek() + (max(/'.'/trap1,30s)-min(/'.'/trap1,30s)) + (last(/'.'/trap1,#1)<>last(/'.'/trap1,#2)) + forecast(/'.'/trap1,#10,1h) + forecast(/'.'/trap1,1h,30m) + forecast(/'.'/trap1,1h:now-1d,12h) + forecast(/'.'/trap1,1h,10m,"exponential") + forecast(/'.'/trap1,1h,2h,"polynomial3","max") + fuzzytime(/'.'/trap1,40s) + count(/'.'/trap2,10m,"eq","56")',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
								],
								$calculated + [
									'params' => 'bitand(last(/'.'/trap1),12)',
									'uuid' => '4b6197500eda44dda4f76faadd01614c'
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
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'key',
									'triggers' => [
										[
											'name' => 'trigger-3',
											'expression' => '{min(5m)}=1',
											'recovery_expression' => ''
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
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'key',
									'triggers' => [
										[
											'name' => 'trigger-3',
											'expression' => 'min(/hostname/key,5m)=1',
											'recovery_expression' => ''
										]
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
							'name' => 'trigger-4',
							'expression' => '{hostname:key.min(5m)}=1',
							'recovery_expression' => '{hostname:key.min(5m)}<>1'
						]
					]
				],
				[
					'triggers' => [
						[
							'uuid' => '6c376983d1a14dbfbfded1ea50b9c481',
							'name' => 'trigger-4',
							'expression' => 'min(/hostname/key,5m)=1',
							'recovery_expression' => 'min(/hostname/key,5m)<>1'
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'grpmin["host group","item",last,5m]',
									'type' => 'AGGREGATE'
								]
							]
						]
					],
					'triggers' => [
						[
							'name' => 'trigger-4a',
							'expression' => '{hostname:grpmin["host group","item",last,5m].last()}=5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'hosts' => [
						[
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'grpmin["host group","item",last,5m]',
									'type' => 'CALCULATED',
									'params' => 'min(last_foreach(/*/item?[group="host group"]))'
								]
							]
						]
					],
					'triggers' => [
						[
							'uuid' => '2c3a57cd91cd48419e10c719668b3238',
							'name' => 'trigger-4a',
							'expression' => 'last(/hostname/grpmin["host group","item",last,5m])=5',
							'recovery_expression' => ''
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'grpmin["host group","item",last,5m]',
									'type' => 'AGGREGATE'
								]
							]
						]
					],
					'triggers' => [
						[
							'name' => 'trigger-5',
							'expression' => '{hostname:grpmin["host group","item",last,5m].date()}=5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'hosts' => [
						[
							'host' => 'hostname',
							'items' => [
								'item' => [
									'key' => 'grpmin["host group","item",last,5m]',
									'type' => 'CALCULATED',
									'params' => 'min(last_foreach(/*/item?[group="host group"]))'
								]
							]
						]
					],
					'triggers' => [
						[
							'uuid' => '1e52561042a24dcba0a03969ad69fff1',
							'name' => 'trigger-5',
							'expression' => '(date()=5) or (last(/hostname/grpmin["host group","item",last,5m])<>last(/hostname/grpmin["host group","item",last,5m]))',
							'recovery_expression' => ''
						]
					]
				]
			],
			[
				[
					'triggers' => [
						[
							'name' => 'trigger-6',
							'event_name' => '{?{k:grpmin["zn6451","item",last,5m].last()}}',
							'expression' => '{k:system.cpu.load.last(5s, 1d)} > 5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'triggers' => [
						[
							'uuid' => '1a4781fa8ee14310a009339c91736304',
							'name' => 'trigger-6',
							'event_name' => '{?last(/k/grpmin["zn6451","item",last,5m])}',
							'expression' => 'last(/k/system.cpu.load,#1:now-1d) > 5',
							'recovery_expression' => ''
						]
					]
				]
			],
			[
				[
					'triggers' => [
						[
							'name' => 'trigger-7',
							'event_name' => '{?{k:grpmin["zn6451","item",last,5m].date()}}',
							'expression' => '{k:system.cpu.load.last(5s)} > 5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'triggers' => [
						[
							'uuid' => '986f30da4e0f46f6abf9a440866c6e45',
							'name' => 'trigger-7',
							'event_name' => '{?date()}',
							'expression' => 'last(/k/system.cpu.load) > 5',
							'recovery_expression' => ''
						]
					]
				]
			],
			[
				[
					'maps' => [
						[
							'name' => 'Local network',
							'selements' => [
								[
									'elementtype' => '0',
									'elements' => [
										[
											'host' => 'Zabbix server'
										]
									],
									'application' => 'MySQL'
								],
								[
									'elementtype' => '2',
									'elements' => [
										[
											'description' => 'trigger',
											'expression' => '{Zabbix server:proc.num.last()} = 0',
											'recovery_expression' => '{Zabbix server:proc.num.last()} <> 0'
										]
									],
									'application' => ''
								]
							],
							'links' => [
								[
									'linktriggers' => [
										[
											'trigger' => [
												'description' => 'trigger',
												'expression' => '{Zabbix server:proc.num.last()} = 0',
												'recovery_expression' => '{Zabbix server:proc.num.last()} <> 0'
											]
										]
									]
								]
							]
						]
					]
				],
				[
					'maps' => [
						[
							'name' => 'Local network',
							'selements' => [
								[
									'elementtype' => '0',
									'elements' => [
										[
											'host' => 'Zabbix server'
										]
									],
									'evaltype' => '0',
									'tags' => [
										'tag' => [
											'tag' => 'Application',
											'value' => 'MySQL',
											'operator' => '0'
										]
									]
								],
								[
									'elementtype' => '2',
									'elements' => [
										[
											'description' => 'trigger',
											'expression' => 'last(/Zabbix server/proc.num) = 0',
											'recovery_expression' => 'last(/Zabbix server/proc.num) <> 0'
										]
									],
									'evaltype' => '0'
								]
							],
							'links' => [
								[
									'linktriggers' => [
										[
											'trigger' => [
												'description' => 'trigger',
												'expression' => 'last(/Zabbix server/proc.num) = 0',
												'recovery_expression' => 'last(/Zabbix server/proc.num) <> 0'
											]
										]
									]
								]
							]
						]
					]
				]
			],
			$this->getGroupsUuidData(),
			$this->getTemplateNameUuidData(),
			$this->getTemplateItemsUuidData(),
			$this->getTriggerUuidData(),
			$this->getGraphUuidData(),
			$this->getTemplateDashboardUuidData(),
			$this->getHttptestUuidData(),
			$this->getValuemapsUuidData(),
			$this->getDiscoveryRuleNameUuidData(),
			$this->getItemPrototypeUuidData(),
			$this->getTriggerPrototypeUuidData(),
			$this->getGraphPrototypeUuidData(),
			$this->getHostPrototypeUuidData(),
			$this->getHostUuidData()
		];
	}

	/**
	 * @dataProvider importConvertProviderData
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
				'version' => '5.2',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '5.4',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	/**
	 * For creation of UUID's, a seed is used by removing the
	 *  "Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) "
	 * prefix from template names if at least 3 characters remain after it.
	 *
	 * @see C52ImportConverter::prepareTemplateName()
	 */
	protected function getTemplateNameUuidData(): array {
		$data = [];
		$expected = [];
		$seeds = [
			'Template OS' => 'Template OS',
			'Template OS ab' => 'Template OS ab',
			'Template OS abc' => 'abc',
			'Template MODULE abc' => 'Template MODULE abc',
			'Template OS ab SNMPv1' => 'ab SNMPv1',
			'Template OS SNMPv2' => 'SNMP',
			'Template OS ab SNMPv2' => 'ab SNMP',
			'Prefixed Template OS unmatched' => 'Prefixed Template OS unmatched',
			'Template db lowercase' => 'Template db lowercase',
			'template VM lowercase T' => 'template VM lowercase T',
			// Samples of template names from 5.0 follow.
			'Cisco Catalyst 3750V2-48TS SNMP' => 'Cisco Catalyst 3750V2-48TS SNMP',
			'RabbitMQ cluster by HTTP' => 'RabbitMQ cluster by HTTP',
			'Template APP Apache Kafka by JMX' => 'Apache Kafka by JMX',
			'Template APP Systemd by Zabbix agent 2' => 'Systemd by Zabbix agent 2',
			'Template App Nginx Plus by HTTP' => 'Nginx Plus by HTTP',
			'Template App PFSense SNMP' => 'PFSense SNMP',
			'Template App Website certificate by Zabbix agent 2' => 'Website certificate by Zabbix agent 2',
			'Template DB Apache Cassandra by JMX' => 'Apache Cassandra by JMX',
			'Template DB MySQL by ODBC' => 'MySQL by ODBC',
			'Template F5 Big-IP SNMP' => 'Template F5 Big-IP SNMP',
			'Template Module Generic SNMP' => 'Generic SNMP',
			'Template Module HOST-RESOURCES-MIB CPU SNMP' => 'HOST-RESOURCES-MIB CPU SNMP',
			'Template Module Linux block devices by Zabbix agent' => 'Linux block devices by Zabbix agent',
			'Template Module Zabbix agent' => 'Zabbix agent',
			'Template Net Alcatel Timetra TiMOS SNMP' => 'Alcatel Timetra TiMOS SNMP',
			'Template Net Dell Force S-Series SNMP' => 'Dell Force S-Series SNMP',
			'Template Net Mikrotik SNMP' => 'Mikrotik SNMP',
			'Template Net Network Generic Device SNMP' => 'Network Generic Device SNMP',
			'Template Net ZYXEL XGS-4728F SNMP' => 'ZYXEL XGS-4728F SNMP',
			'Template OS AIX' => 'AIX',
			'Template OS Linux by Prom' => 'Linux by Prom',
			'Template OS Mac OS X' => 'Mac OS X',
			'Template OS Solaris' => 'Solaris',
			'Template OS Windows SNMP' => 'Windows SNMP',
			'Template Power APC Smart-UPS 2200 RM SNMP' => 'Template Power APC Smart-UPS 2200 RM SNMP',
			'Template SAN Huawei OceanStor 5300 V5 SNMP' => 'Huawei OceanStor 5300 V5 SNMP',
			'Template SAN NetApp AFF A700 by HTTP' => 'NetApp AFF A700 by HTTP',
			'Template SAN NetApp FAS3220 SNMP' => 'NetApp FAS3220 SNMP',
			'Template Server Chassis by IPMI' => 'Chassis by IPMI',
			'Template Server Cisco UCS Manager SNMP' => 'Cisco UCS Manager SNMP',
			'Template Server Dell iDRAC SNMP' => 'Dell iDRAC SNMP',
			'Template Server HP iLO SNMP' => 'HP iLO SNMP',
			'Template VM VMware' => 'VMware'
		];

		foreach ($seeds as $template_name => &$seed) {
			$data[] = ['template' => $template_name];
			$expected[] = ['template' => $template_name, 'uuid' => generateUuidV4($seed)];
		}

		return [
			['templates' => $data],
			['templates' => $expected]
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
					['host' => 'Host A', 'groups' => [['name' => 'Group B']]]
				],
				'templates' => [
					['template' => 'Template A', 'groups' => [['name' => 'Group A']]]
				],
				'groups' => [
					['name' => 'Group A'],
					['name' => 'Group B']
				]
			],
			[
				'hosts' => [
					['host' => 'Host A', 'groups' => [['name' => 'Group B']]]
				],
				'templates' => [
					['template' => 'Template A', 'groups' => [['name' => 'Group A']], 'uuid' => generateUuidV4('Template A')]
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
						'template' => 'Template C',
						'items' => [
							['key' => 'item1']
						]
					],
					[
						'template' => 'Template OS Old name D',
						'items' => [
							['key' => 'item1']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template C',
						'items' => [
							['key' => 'item1', 'uuid' => generateUuidV4('Template C/item1')]
						],
						'uuid' => generateUuidV4('Template C')
					],
					[
						'template' => 'Template OS Old name D',
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
						'template' => 'Template E',
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
						'template' => 'Template E',
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
					['template' => 'Template OS'],
					['template' => 'Template OS Template G']
				],
				'graphs' => [
					[
						'name' => 'Graph A',
						'graph_items' => [
							['item' => ['host' => 'Template OS', 'key' => 'item3']],
							['item' => ['host' => 'Template OS Template G', 'key' => 'item4']]
						]
					]
				]
			],
			[
				'templates' => [
					['template' => 'Template OS', 'uuid' => generateUuidV4('Template OS')],
					['template' => 'Template OS Template G', 'uuid' => generateUuidV4('Template G')]
				],
				'graphs' => [
					[
						'name' => 'Graph A',
						'graph_items' => [
							['item' => ['host' => 'Template OS', 'key' => 'item3']],
							['item' => ['host' => 'Template OS Template G', 'key' => 'item4']]
						],
						'uuid' => generateUuidV4('Graph A/Template OS/Template G')
					]
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
						'template' => 'Template H',
						'dashboards' => [
							['name' => 'Dashboard A']
						]
					],
					[
						'template' => 'Template OS Template I',
						'dashboards' => [
							['name' => 'Dashboard B']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template H',
						'dashboards' => [
							['name' => 'Dashboard A', 'uuid' => generateUuidV4('Template H/Dashboard A'), 'pages' => [[]]]
						],
						'uuid' => generateUuidV4('Template H')
					],
					[
						'template' => 'Template OS Template I',
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
						'template' => 'Template J',
						'httptests' => [
							['name' => 'HTTP Test A']
						]
					],
					[
						'template' => 'Template OS Template K',
						'httptests' => [
							['name' => 'HTTP Test B']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template J',
						'httptests' => [
							['name' => 'HTTP Test A', 'uuid' => generateUuidV4('Template J/HTTP Test A')]
						],
						'uuid' => generateUuidV4('Template J')
					],
					[
						'template' => 'Template OS Template K',
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
						'template' => 'Template L',
						'items' => [
							['key' => 'item5', 'valuemap' => ['name' => 'Value map A']],
							['key' => 'item6', 'valuemap' => ['name' => 'Value map B']]
						]
					],
					[
						'template' => 'Template OS Template K',
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
						'template' => 'Template L',
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
						'template' => 'Template OS Template K',
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
						'template' => 'Template M',
						'discovery_rules' => [
							['key' => 'drule1']
						]
					],
					[
						'template' => 'Template OS Template N',
						'discovery_rules' => [
							['key' => 'drule2']
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template M',
						'discovery_rules' => [
							['key' => 'drule1', 'uuid' => generateUuidV4('Template M/drule1')]
						],
						'uuid' => generateUuidV4('Template M')
					],
					[
						'template' => 'Template OS Template N',
						'discovery_rules' => [
							['key' => 'drule2', 'uuid' => generateUuidV4('Template N/drule2')]
						],
						'uuid' => generateUuidV4('Template N')
					]
				]
			]
		];
	}

	protected function getItemPrototypeUuidData(): array {
		// Use "template name/discovery rule key/item prototype key".
		return [
			[
				'templates' => [
					[
						'template' => 'Template O',
						'discovery_rules' => [
							['key' => 'drule3', 'item_prototypes' => [['key' => 'item9']]]
						]
					],
					[
						'template' => 'Template OS Template P',
						'discovery_rules' => [
							['key' => 'drule4', 'item_prototypes' => [['key' => 'item10']]]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template O',
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
						'template' => 'Template OS Template P',
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

	protected function getTriggerPrototypeUuidData(): array {
		// Use "discovery rule key/trigger prototype name/expanded expression/expanded recovery expression".
		return [
			[
				'templates' => [
					[
						'template' => 'Template Q',
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
						'template' => 'Template Q',
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

	protected function getGraphPrototypeUuidData(): array {
		// Use "template name/discovery rule key/graph prototype name".
		return [
			[
				'templates' => [
					[
						'template' => 'Template R',
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
						'template' => 'Template OS Template S',
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
						'template' => 'Template R',
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
						'template' => 'Template OS Template S',
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

	protected function getHostPrototypeUuidData(): array {
		// Use "template name/discovery rule key/host prototype name".
		return [
			[
				'templates' => [
					[
						'template' => 'Template T',
						'discovery_rules' => [
							[
								'key' => 'drule8',
								'host_prototypes' => [
									['host' => 'Host B']
								]
							]
						]
					],
					[
						'template' => 'Template OS Template T',
						'discovery_rules' => [
							[
								'key' => 'drule9',
								'host_prototypes' => [
									['host' => 'Host C']
								]
							]
						]
					]
				]
			],
			[
				'templates' => [
					[
						'template' => 'Template T',
						'discovery_rules' => [
							[
								'key' => 'drule8',
								'host_prototypes' => [
									['host' => 'Host B', 'uuid' => generateUuidV4('Template T/drule8/Host B')]
								],
								'uuid' => generateUuidV4('Template T/drule8')
							]
						],
						'uuid' => generateUuidV4('Template T')
					],
					[
						'template' => 'Template OS Template T',
						'discovery_rules' => [
							[
								'key' => 'drule9',
								'host_prototypes' => [
									['host' => 'Host C', 'uuid' => generateUuidV4('Template T/drule9/Host C')]
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
						'host' => 'Host',
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
						'host' => 'Host',
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

	protected function assertConvert(array $expected, array $source): void {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter(): C52ImportConverter {
		return new C52ImportConverter();
	}
}
