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


require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../include/CTest.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';

class dbConditionIdTest extends CTest {

	public static function provider() {
		return [
			[
				['field', [0]],
				"field IS NULL"
			],
			[
				['field', [0, 1]],
				"(field=1 OR field IS NULL)"
			],
			[
				['field', [1, 0]],
				"(field=1 OR field IS NULL)"
			],
			[
				['field', [0, 1, 2, 3]],
				"(field IN (1,2,3) OR field IS NULL)"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10]],
				"(field IN (1,2,3,5,6,7,8,9,10) OR field IS NULL)"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10], true],
				"field NOT IN (1,2,3,5,6,7,8,9,10) AND field IS NOT NULL"
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test($params, $expected) {
		$result = call_user_func_array('dbConditionId', $params);

		$this->assertSame($expected, $result);
	}
}
