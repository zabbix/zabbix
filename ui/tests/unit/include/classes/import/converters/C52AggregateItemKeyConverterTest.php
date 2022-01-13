<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class C52AggregateItemKeyConverterTest extends TestCase {

	protected $converter;

	protected function setUp(): void {
		$this->converter = new C52AggregateItemKeyConverter();
	}

	public function dataProvider(): array {
		return [
			[
				'grpsum["MySQL Servers","vfs.fs.size[/,total]",last]',
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))'
			],
			[
				'grpavg["MySQL Servers","system.cpu.load[,avg1]",last]',
				'avg(last_foreach(/*/system.cpu.load[,avg1]?[group="MySQL Servers"]))'
			],
			[
				'grpavg["MySQL Servers",mysql.qps,avg,5m]',
				'avg(avg_foreach(/*/mysql.qps?[group="MySQL Servers"],5m))'
			],
			[
				'grpavg[["Servers A","Servers B","Servers C"],system.cpu.load,last]',
				'avg(last_foreach(/*/system.cpu.load?[group="Servers A" or group="Servers B" or group="Servers C"]))'
			],
			[
				'grpsum["My, group","trap1",last]',
				'sum(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpsum["MySQL\\"Servers","vfs.fs.size[/,total]",last]',
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL\\"Servers"]))'
			],
			[
				'grpsum["MySQL\\Servers","trap1",last]',
				'sum(last_foreach(/*/trap1?[group="MySQL\\\\Servers"]))'
			],
			[
				'grpsum[ "Zabbix servers" , trap1 , last, 30s ]',
				'sum(last_foreach(/*/trap1?[group="Zabbix servers"],30s))'
			],
			[
				'grpavg[{$M},trap1,avg,{$M3}s]',
				'avg(avg_foreach(/*/trap1?[group="{$M}"],"{$M3}s"))'
			],
			[
				'grpfunc["Host group",item key,func1,timeperiod]',
				'func(func1_foreach(/*/item key?[group="Host group"],"timeperiod"))'
			],
			[
				'simplekey',
				'simplekey'
			],
			[
				'grpmin[]',
				'grpmin[]'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param $key
	 * @param $expected
	 */
	public function testConvert($key, $expected) {
		$this->assertEquals($expected, $this->converter->convert($key));
	}
}
