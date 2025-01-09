<?php
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
