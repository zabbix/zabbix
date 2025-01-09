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


use PHPUnit\Framework\TestCase;

class C52AggregateItemKeyConverterTest extends TestCase {

	protected $converter;

	protected function setUp(): void {
		$this->converter = new C52AggregateItemKeyConverter();
	}

	/**
	 * Data provider to test aggregate item key conversion to calculated item formula. This does not test the validity,
	 * but merely converts the given item key to the new format. So this does not mean input and output is actually a
	 * valid key and formula. First array value is old format <=5.2 and second array value is >=5.4 format.
	 */
	public function dataProvider(): array {
		return [
			// grpavg, avg/min/max/count/sum
			[
				'grpavg["My, group","trap1",avg]',
				'avg(avg_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpavg["MySQL Servers",mysql.qps,avg,5m]',
				'avg(avg_foreach(/*/mysql.qps?[group="MySQL Servers"],5m))'
			],
			[
				'grpavg[{$M},trap1,avg,{$M3}s]',
				'avg(avg_foreach(/*/trap1?[group="{$M}"],"{$M3}s"))'
			],
			[
				'grpavg["My, group","trap1",min,]',
				'avg(min_foreach(/*/trap1?[group="My, group"],""))'
			],
			[
				'grpavg["My, group","trap1",max,0]',
				'avg(max_foreach(/*/trap1?[group="My, group"],0))'
			],
			[
				'grpavg["My, group","trap1",count,0s]',
				'avg(count_foreach(/*/trap1?[group="My, group"],0s))'
			],
			[
				'grpavg["My, group","trap1",sum,{$PERIOD}]',
				'avg(sum_foreach(/*/trap1?[group="My, group"],{$PERIOD}))'
			],
			// grpavg, last
			[
				'grpavg["My, group","trap1",last]',
				'avg(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpavg["MySQL Servers","system.cpu.load[,avg1]",last]',
				'avg(last_foreach(/*/system.cpu.load[,avg1]?[group="MySQL Servers"]))'
			],
			[
				'grpavg[["Servers A","Servers B","Servers C"],system.cpu.load,last]',
				'avg(last_foreach(/*/system.cpu.load?[group="Servers A" or group="Servers B" or group="Servers C"]))'
			],
			[
				'grpavg["My, group","trap1",last,]',
				'avg(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpavg["My, group","trap1",last,0]',
				'avg(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpavg["My, group","trap1",last,0s]',
				'avg(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpavg["My, group","trap1",last,{$PERIOD}]',
				'avg(last_foreach(/*/trap1?[group="My, group"]))'
			],
			// grpsum, avg/min/max/count/sum
			[
				'grpsum["My, group","trap1",avg]',
				'sum(avg_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpsum["My, group","trap1",min,]',
				'sum(min_foreach(/*/trap1?[group="My, group"],""))'
			],
			[
				'grpsum["My, group","trap1",max,0]',
				'sum(max_foreach(/*/trap1?[group="My, group"],0))'
			],
			[
				'grpsum["My, group","trap1",count,0s]',
				'sum(count_foreach(/*/trap1?[group="My, group"],0s))'
			],
			[
				'grpsum["My, group","trap1",sum,{$PERIOD}]',
				'sum(sum_foreach(/*/trap1?[group="My, group"],{$PERIOD}))'
			],
			// grpsum, last
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
				'grpsum["MySQL Servers","vfs.fs.size[/,total]",last]',
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))'
			],
			[
				'grpsum[ "Zabbix servers" , trap1 , last, 30s ]',
				'sum(last_foreach(/*/trap1?[group="Zabbix servers"]))'
			],
			[
				'grpsum["My, group","trap1",last,]',
				'sum(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpsum["My, group","trap1",last,0]',
				'sum(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpsum["My, group","trap1",last,0s]',
				'sum(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpsum["My, group","trap1",last,{$PERIOD}]',
				'sum(last_foreach(/*/trap1?[group="My, group"]))'
			],
			// grpmin, avg/min/max/count/sum
			[
				'grpmin["My, group","trap1",avg]',
				'min(avg_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmin["My, group","trap1",min,]',
				'min(min_foreach(/*/trap1?[group="My, group"],""))'
			],
			[
				'grpmin["My, group","trap1",max,0]',
				'min(max_foreach(/*/trap1?[group="My, group"],0))'
			],
			[
				'grpmin["My, group","trap1",count,0s]',
				'min(count_foreach(/*/trap1?[group="My, group"],0s))'
			],
			[
				'grpmin["My, group","trap1",sum,{$PERIOD}]',
				'min(sum_foreach(/*/trap1?[group="My, group"],{$PERIOD}))'
			],
			// grpmin, last
			[
				'grpmin["My, group","trap1",last]',
				'min(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmin["My, group","trap1",last,]',
				'min(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmin["My, group","trap1",last,0]',
				'min(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmin["My, group","trap1",last,0s]',
				'min(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmin["My, group","trap1",last,{$PERIOD}]',
				'min(last_foreach(/*/trap1?[group="My, group"]))'
			],
			// grpmax, avg/min/max/count/sum
			[
				'grpmax["My, group","trap1",avg]',
				'max(avg_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmax["My, group","trap1",min,]',
				'max(min_foreach(/*/trap1?[group="My, group"],""))'
			],
			[
				'grpmax["My, group","trap1",max,0]',
				'max(max_foreach(/*/trap1?[group="My, group"],0))'
			],
			[
				'grpmax["My, group","trap1",count,0s]',
				'max(count_foreach(/*/trap1?[group="My, group"],0s))'
			],
			[
				'grpmax["My, group","trap1",sum,{$PERIOD}]',
				'max(sum_foreach(/*/trap1?[group="My, group"],{$PERIOD}))'
			],
			// grpmax, last
			[
				'grpmax["My, group","trap1",last]',
				'max(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmax["My, group","trap1",last,]',
				'max(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmax["My, group","trap1",last,0]',
				'max(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmax["My, group","trap1",last,0s]',
				'max(last_foreach(/*/trap1?[group="My, group"]))'
			],
			[
				'grpmax["My, group","trap1",last,{$PERIOD}]',
				'max(last_foreach(/*/trap1?[group="My, group"]))'
			],
			// other
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
