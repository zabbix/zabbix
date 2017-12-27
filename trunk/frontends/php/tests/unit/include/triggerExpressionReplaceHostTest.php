<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CTriggerExpressionReplaceHostTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'{host:item.func()}',
				'host', 'Zabbix server',
				'{Zabbix server:item.func()}'
			],
			[
				'5 + {host:item.func()} <> 0 or {$MACRO: "context"} or {#MACRO} or {TRIGGER.VALUE} or {host:item.func()} or {host2:item2.func()}',
				'host', 'Zabbix server',
				'5 + {Zabbix server:item.func()} <> 0 or {$MACRO: "context"} or {#MACRO} or {TRIGGER.VALUE} or {Zabbix server:item.func()} or {host2:item2.func()}'
			],
			[
				'5 + {Zabbix server:item.func()} <> 0 or {Zabbix server:item.func()} or {host2:item2.func()}',
				'Zabbix server', 'host',
				'5 + {host:item.func()} <> 0 or {host:item.func()} or {host2:item2.func()}'
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param string  $expected
	*/
	public function testTriggerExpressionReplaceHost($source, $src_host, $dst_host, $expected) {
		$this->assertSame($expected, triggerExpressionReplaceHost($source, $src_host, $dst_host));
	}
}
