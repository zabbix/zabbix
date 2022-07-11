<?php declare(strict_types = 0);
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

class C52EventNameConverterTest extends TestCase {

	/**
	 * @var C52EventNameConverter
	 */
	private $converter;

	protected function setUp(): void {
		$this->converter = new C52EventNameConverter();
	}

	protected function tearDown(): void {
		$this->converter = null;
	}

	public function simpleProviderData() {
		return [
			[
				'String containing expression macro {?{host:item.last()} = 0}.',
				'String containing expression macro {?last(/host/item) = 0}.'
			],
			[
				'String containing expression macro {?{host:item[{#M}].last()} = 0}.',
				'String containing expression macro {?last(/host/item[{#M}]) = 0}.'
			],
			[
				'String containing expression macro {?{{HOST.HOST}:item.func(1)} = 0}.',
				'String containing expression macro {?func(//item,"1") = 0}.'
			],
			[
				'String containing expression macro '.
				'{{?100*'.
					'{Zabbux Server:system.cpu.load.trendavg(1M,now/M)}'.
					'/'.
					'{Zabbux Server:system.cpu.load.trendavg(1M,now/M-1M)}'.
				'}.fmtnum(0)}'.
				'%',

				'String containing expression macro {{?100*'.
					'trendavg(/Zabbux Server/system.cpu.load,1M:now/M)'.
					'/'.
					'trendavg(/Zabbux Server/system.cpu.load,1M:now/M-1M)'.
				'}.fmtnum(0)}%'
			],
			[
				'String containing expression macro {?{host:item.date()}}=1 and {?{host:item.date()}}.',
				'String containing expression macro {?date()}=1 and {?date()}.'
			],
			[
				'String containing expression macro {?{host:item.date()}}=1.',
				'String containing expression macro {?date()}=1.'
			],
			[
				'String containing expression macro {?{host:item.date()}=1}.',
				'String containing expression macro {?date()=1}.'
			]
		];
	}

	/**
	 * @dataProvider simpleProviderData
	 *
	 * @param string $old_event_name
	 * @param string $new_event_name
	 */
	public function testSimpleConversion(string $old_event_name, string $new_event_name) {
		$this->assertSame($new_event_name, $this->converter->convert($old_event_name, '', ''));
	}
}
