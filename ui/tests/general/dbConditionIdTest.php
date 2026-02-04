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

class dbConditionIdTest extends CTest {

	public static function provider(): array {
		return [
			[
				['field', [0]],
				"(field IS NULL OR field=0)",
				"(field IS NULL OR field=0)"
			],
			[
				['field', [0, 1]],
				"(field IS NULL OR field IN (0,1))",
				"(field IS NULL OR field IN (0,1))"
			],
			[
				['field', [1, 0]],
				"(field IS NULL OR field IN (0,1))",
				"(field IS NULL OR field IN (0,1))"
			],
			[
				['field', [0, 1, 2, 3]],
				"(field IS NULL OR field IN (0,1,2,3))",
				"(field IS NULL OR field IN (0,1,2,3))"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10]],
				"(field IS NULL OR field IN (0,1,2,3,5,6,7,8,9,10))",
				"(field IS NULL OR field BETWEEN 5 AND 10 OR field IN (0,1,2,3))"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10], true],
				"field IS NOT NULL AND field NOT IN (0,1,2,3,5,6,7,8,9,10)",
				"field IS NOT NULL AND NOT field BETWEEN 5 AND 10 AND field NOT IN (0,1,2,3)"
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, string $expected_non_oracle, string $expected_oracle): void {
		global $DB;

		$result = dbConditionId(...$params);

		$this->assertSame($DB['TYPE'] === ZBX_DB_ORACLE ? $expected_oracle : $expected_non_oracle, $result);
	}

	/**
	 * @dataProvider provider
	 */
	public function testOracle(array $params, string $expected_non_oracle, string $expected_oracle): void {
		global $DB;

		if ($DB['TYPE'] === ZBX_DB_ORACLE) {
			$this->markTestSkipped();
		}

		$_DB = $DB;
		$DB['TYPE'] = ZBX_DB_ORACLE;

		$result = dbConditionId(...$params);

		$this->assertSame($expected_oracle, $result);

		$DB = $_DB;
	}
}
