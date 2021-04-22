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

	public function testConvertProvider() {
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
							'host' => 'Template',
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
							'host' => 'Template',
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
								$calculated + ['params' => 'abs(change(/'.'/trap1)) + avg(/'.'/trap1,1h:now-1d) + band(/'.'/trap1,,12)=4 + count(/'.'/trap1,10m) + count(/'.'/trap1,10m,"eq","error") + count(/'.'/trap1,10m,,12) + count(/'.'/trap1,10m,"gt",12) + count(/'.'/trap1,#10,"gt",12) + count(/'.'/trap1,10m:now-1d,"gt",12) + count(/'.'/trap1,10m,"band","6/7") + count(/'.'/trap1,10m:now-1d) + count(/'.'/trap1,10m,"eq","56") + count(/Zabbix server/trap3,10m,"eq","error") + date() + dayofmonth() + dayofweek() + (max(/'.'/trap1,30s)-min(/'.'/trap1,30s)) + (last(/'.'/trap1,#1)<>last(/'.'/trap1,#2)) + forecast(/'.'/trap1,#10,1h) + forecast(/'.'/trap1,1h,30m) + forecast(/'.'/trap1,1h:now-1d,12h) + forecast(/'.'/trap1,1h,10m,"exponential") + forecast(/'.'/trap1,1h,2h,"polynomial3","max") + fuzzytime(/'.'/trap1,40s) + count(/'.'/trap2,10m,"eq","56")']
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
