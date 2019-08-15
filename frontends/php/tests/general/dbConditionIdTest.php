<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	public function test($params, $expectedResult) {
		$result = call_user_func_array('dbConditionId', $params);

		$this->assertSame($expectedResult, $result);
	}
}
