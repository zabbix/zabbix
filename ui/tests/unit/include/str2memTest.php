<?php
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

class str2memTest extends TestCase {

	public static function dataProvider() {
		return [
			['1', 1],
			['1024', 1024],
			['0', 0],
			['1K', 1024],
			['1k', 1024],
			['1M', 1024 * 1024],
			['1m', 1024 * 1024],
			['1G', 1024 * 1024 * 1024],
			['1g', 1024 * 1024 * 1024],
			['8K', 8 * 1024],
			['8k', 8 * 1024],
			['8M', 8 * 1024 * 1024],
			['8m', 8 * 1024 * 1024],
			['8G', 8 * 1024 * 1024 * 1024],
			['8g', 8 * 1024 * 1024 * 1024]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param string $expected
	*/
	public function testTriggerExpressionReplaceHost($source, $expected) {
		$this->assertSame($expected, str2mem($source));
	}
}
