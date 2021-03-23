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
								$calculated + ['params' => 'last("system.swap.size[,total]") - last("system.swap.size[,total]") / 100 * last("perf_counter_en[\\"\\Paging file(_Total)\\% Usage\\"]")']
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
								$calculated + ['params' => 'avg(/Zabbix Server/zabbix[wcache,values],600)'],
								$calculated + ['params' => 'last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes])'],
								$calculated + ['params' => '100*last(/'.'/net.if.in[eth0,bytes])/(last(/'.'/net.if.in[eth0,bytes])+last(/'.'/net.if.out[eth0,bytes]))'],
								$calculated + ['params' => 'last(/'.'/grpsum["video","net.if.out[eth0,bytes]","last"]) / last(/'.'/grpsum["video","nginx_stat.sh[active]","last"])'],
								$calculated + ['params' => 'last(/'.'/es.node.indices.flush.total_time_in_millis[{#ES.NODE}]) / ( last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) + (last(/'.'/es.node.indices.flush.total[{#ES.NODE}]) = 0) )'],
								$calculated + ['params' => 'last(/'.'/haproxy.frontend.scur[{#PXNAME}:{#SVNAME}]) / last(/'.'/haproxy.frontend.slim[{#PXNAME}:{#SVNAME}]) * 100'],
								$calculated + ['params' => 'last(/'.'/php-fpm.listen_queue)/(last(/'.'/php-fpm.listen_queue_len)+last(/'.'/php-fpm.listen_queue_len)=0)*100'],
								$calculated + ['params' => 'last(/'.'/vm.memory.used[hrStorageUsed.{#SNMPINDEX}])/last(/'.'/vm.memory.total[hrStorageSize.{#SNMPINDEX}])*100'],
								$calculated + ['params' => 'last(/'.'/system.swap.size[,total]) - last(/'.'/system.swap.size[,total]) / 100 * last(/'.'/perf_counter_en["\\Paging file(_Total)\% Usage"])']
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
