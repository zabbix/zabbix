<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class C24TriggerConverterTest extends PHPUnit_Framework_TestCase {

	public function testConvertProvider() {
		return array(
			array('1#1', '1<>1'),

			array('1|1', '1 or 1'),
			array('1 |1', '1 or 1'),
			array('1| 1', '1 or 1'),
			array('1 | 1', '1 or 1'),
			array('1  | 1', '1 or 1'),
			array('1  |  1', '1 or 1'),

			array('1&1', '1 and 1'),
			array('1 &1', '1 and 1'),
			array('1& 1', '1 and 1'),
			array('1 & 1', '1 and 1'),
			array('1  & 1',	'1 and 1'),
			array('1  &  1', '1 and 1'),

			array('{host:item.last()}      |      {host:item.str(#)}', '{host:item.last()} or {host:item.str(#)}'),
			array('{host:item.last()} > {#MAX}', '{host:item.last()} > {#MAX}'),

			array('{host:item.str(#)} # 1', '{host:item.str(#)} <> 1'),
			array('{host:item.str(|)} | 1', '{host:item.str(|)} or 1'),
			array('{host:item.str(&)} & 1', '{host:item.str(&)} and 1'),

			array('{host:item[#].last()} # 1', '{host:item[#].last()} <> 1'),
			array('{host:item[|].last()} | 1', '{host:item[|].last()} or 1'),
			array('{host:item[&].last()} and 1', '{host:item[&].last()} and 1'),

			array('{TRIGGER.VALUE}|{host:item[&].last()}', '{TRIGGER.VALUE} or {host:item[&].last()}'),
			array(
				'({TRIGGER.VALUE}=0&{Template App Zabbix Server:zabbix[process,alerter,avg,busy].avg(10m)}>75)|({TRIGGER.VALUE}=1&{Template App Zabbix Server:zabbix[process,alerter,avg,busy].avg(10m)}>65)',
				'({TRIGGER.VALUE}=0 and {Template App Zabbix Server:zabbix[process,alerter,avg,busy].avg(10m)}>75) or ({TRIGGER.VALUE}=1 and {Template App Zabbix Server:zabbix[process,alerter,avg,busy].avg(10m)}>65)'
			),

			// incorrect expressions are returned as is
			array('{host:item.last()', '{host:item.last()'),

			// an already up-to-date expression
			array('{host:item.last()} > 0', '{host:item.last()} > 0'),
		);
	}

	/**
	 * @dataProvider testConvertProvider
	 *
	 * @param $expression
	 * @param $expectedConvertedExpression
	 */
	public function testConvert($expression, $expectedConvertedExpression) {
		$converter = new C24TriggerConverter(new CFunctionMacroParser(), new CMacroParser('#'));
		$this->assertEquals($expectedConvertedExpression, $converter->convert($expression));
	}

}
