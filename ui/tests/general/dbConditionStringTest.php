<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../include/CTest.php';

class dbConditionStringTest extends CTest {

	public static function provider(): Generator {
		yield 'Equality filter with no values.' => [
			['field', []],
			'1=0'
		];

		yield 'Inequality filter with no values.' => [
			['field', [], true],
			'1=1'
		];

		yield 'Equality filter with single value.' => [
			['field', ['a']],
			"field='a'"
		];

		yield 'Inequality filter with single value.' => [
			['field', ['a'], true],
			"field!='a'"
		];

		yield 'Inclusion filter with two values.' => [
			['field', ['a', 'b']],
			"field IN ('a','b')"
		];

		yield 'Exclusion filter with two values.' => [
			['field', ['a', 'b'], true],
			"field NOT IN ('a','b')"
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, string $expected): void {
		$result = dbConditionString(...$params);

		$this->assertSame($expected, $result);
	}
}
