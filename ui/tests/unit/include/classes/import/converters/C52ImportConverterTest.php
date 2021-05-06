<?php
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


class C52ImportConverterTest extends CImportConverterTest {

	public function importConvertProviderData() {
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
								$calculated + ['params' => 'abschange("trap1") + avg(trap1,1h,1d) + band(trap1,,12)=4 + count(trap1,10m) + count(trap1,10m,"error",eq) + count(trap1,10m,12) + count(trap1, 10m,12,gt) + count(trap1, #10,12,gt) + count(trap1, 10m,12,gt,1d) + count(trap1,10m,6/7,band) + count(trap1, 10m,,,1d) + count(trap1,10m,"56",eq) + count("Zabbix server:trap3",10m,error,eq) + date("trap1") + dayofmonth(trap1) + dayofweek(trap1) + delta(trap1,30s) + diff(trap1) + forecast(trap1,#10,,1h) + forecast(trap1,1h,,30m) + forecast(trap1,1h,1d,12h) + forecast(trap1,1h,,10m,exponential) + forecast(trap1,1h,,2h,polynomial3,max) + fuzzytime(trap1,40) + count(trap2,10m,56,eq)']
							]
						]
					]
				],
				[
					'templates' => [
						[
							'template' => 'Template',
							'items' => [
								$calculated + ['params' => '100*last(/'.'/vfs.fs.size[/,free])/last(/'.'/vfs.fs.size[/,total])'],
								$calculated + ['params' => 'avg(/Zabbix Server/zabbix[wcache,values],600s)'],
								$calculated + ['params' => 'last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes])'],
								$calculated + ['params' => '100*last(/'.'/net.if.in[eth0,bytes])/(last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes]))'],
								$calculated + ['params' => 'last(/'.'/grpsum["video","net.if.out[eth0,bytes]","last"]) / last(/'.'/grpsum["video","nginx_stat.sh[active]","last"])'],
								$calculated + ['params' => 'last(/'.'/es.node.indices.flush.total_time_in_millis[{#ES.NODE}]) / ( last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) + (last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) = 0) )'],
								$calculated + ['params' => 'last(/'.'/haproxy.frontend.scur[{#PXNAME}:{#SVNAME}]) / last(/'.'/haproxy.frontend.slim[{#PXNAME}:{#SVNAME}]) * 100'],
								$calculated + ['params' => 'last(/'.'/php-fpm.listen_queue)/(last(/'.'/php-fpm.listen_queue_len)+last(/'.'/php-fpm.listen_queue_len)=0)*100'],
								$calculated + ['params' => 'last(/'.'/vm.memory.used[hrStorageUsed.{#SNMPINDEX}])/last(/'.'/vm.memory.total[hrStorageSize.{#SNMPINDEX}])*100'],
								$calculated + ['params' => 'last(/'.'/system.swap.size[,total]) - last(/'.'/system.swap.size[,total]) / 100 * last(/'.'/perf_counter_en["\\Paging file(_Total)\% Usage"])'],
								$calculated + ['params' => 'avg(/zbxnext_6451/agent_numeric[wcache,values],600s)'],
								$calculated + ['params' => 'abs(change(/'.'/trap1)) + avg(/'.'/trap1,1h:now-1d) + bitand(last(/'.'/trap1),12)=4 + count(/'.'/trap1,10m) + count(/'.'/trap1,10m,"eq","error") + count(/'.'/trap1,10m,,"12") + count(/'.'/trap1,10m,"gt","12") + count(/'.'/trap1,#10,"gt","12") + count(/'.'/trap1,10m:now-1d,"gt","12") + count(/'.'/trap1,10m,"bitand","6/7") + count(/'.'/trap1,10m:now-1d) + count(/'.'/trap1,10m,"eq","56") + count(/Zabbix server/trap3,10m,"eq","error") + date() + dayofmonth() + dayofweek() + (max(/'.'/trap1,30s)-min(/'.'/trap1,30s)) + (last(/'.'/trap1,#1)<>last(/'.'/trap1,#2)) + forecast(/'.'/trap1,#10,1h) + forecast(/'.'/trap1,1h,30m) + forecast(/'.'/trap1,1h:now-1d,12h) + forecast(/'.'/trap1,1h,10m,"exponential") + forecast(/'.'/trap1,1h,2h,"polynomial3","max") + fuzzytime(/'.'/trap1,40s) + count(/'.'/trap2,10m,"eq","56")']
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
							'expression' => '{hostname:key.min(5m)}=1',
							'recovery_expression' => '{hostname:key.min(5m)}<>1'
						]
					]
				],
				[
					'triggers' => [
						[
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
									'params' => 'min(last_foreach(/*/item?[group="host group"],5m))'
								]
							]
						]
					],
					'triggers' => [
						[
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
									'params' => 'min(last_foreach(/*/item?[group="host group"],5m))'
								]
							]
						]
					],
					'triggers' => [
						[
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
							'event_name' => '{?{k:grpmin["zn6451","item",last,5m].last()}}',
							'expression' => '{k:system.cpu.load.last(5s, 1d)} > 5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'triggers' => [
						[
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
							'event_name' => '{?{k:grpmin["zn6451","item",last,5m].date()}}',
							'expression' => '{k:system.cpu.load.last(5s)} > 5',
							'recovery_expression' => ''
						]
					]
				],
				[
					'triggers' => [
						[
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
			]
		];
	}

	/**
	 * @dataProvider importConvertProviderData
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

	protected function createConverter() {
		return new C52ImportConverter();
	}
}
