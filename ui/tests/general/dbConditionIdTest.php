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
				"(field IS NULL OR field=0)"
			],
			[
				['field', [0, 1]],
				"(field IS NULL OR field IN (0,1))"
			],
			[
				['field', [1, 0]],
				"(field IS NULL OR field IN (0,1))"
			],
			[
				['field', [0, 1, 2, 3]],
				"(field IS NULL OR field IN (0,1,2,3))"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10]],
				"(field IS NULL OR field IN (0,1,2,3,5,6,7,8,9,10))"
			],
			[
				['field', [0, 1, 2, 3, 5, 6, 7, 8, 9, 10], true],
				"field IS NOT NULL AND field NOT IN (0,1,2,3,5,6,7,8,9,10)"
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
