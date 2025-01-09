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


require_once dirname(__FILE__).'/../include/CTest.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';
require_once dirname(__FILE__).'/../../include/classes/db/DBException.php';

class makeSqlTest extends CTest {

	public function dataProvider() {
		return [
			[
				'hosts', 'h',
				[
					'output' => ['hostid', 'host', 'name'],
					'hostids' => [10001, 10002, 10003, 10000, 9999, 9998],
					'filter' => [
						'maintenanceid' => [1]
					],
					'sortfield' => ['host', 'name'],
					'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_UP]
				],
				'SELECT h.hostid,h.host,h.name FROM hosts h WHERE h.hostid IN (9998,9999,10000,10001,10002,10003) AND h.maintenanceid=1 ORDER BY h.host DESC,h.name'
			],
			[
				'hosts', null,
				[
					'output' => ['hostid', 'host', 'name'],
					'hostids' => [10001, 10002, 10003],
					'filter' => [
						'maintenanceid' => [1]
					],
					'sortfield' => ['host', 'name'],
					'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_UP]
				],
				'SELECT hostid,host,name FROM hosts WHERE hostid IN (10001,10002,10003) AND maintenanceid=1 ORDER BY host DESC,name'
			],
			[
				'hosts', 'h',
				[
					'countOutput' => true,
					'hostids' => [10001]
				],
				'SELECT COUNT(h.*) AS rowscount FROM hosts h WHERE h.hostid=10001'
			],
			[
				'hosts', null,
				[
					'countOutput' => true,
					'hostids' => [10001, 10002, 10003]
				],
				'SELECT COUNT(*) AS rowscount FROM hosts WHERE hostid IN (10001,10002,10003)'
			],
			[
				'users', null,
				[
					'output' => ['userid'],
					'filter' => [
						'userid' => [2],
						'roleid' => [3, 4]
					]
				],
				'SELECT userid FROM users WHERE userid=2 AND roleid IN (3,4)'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param string $expected
	 */
	public function test($table_name, $table_alias, $options, $expected) {
		$sql = DB::makeSql($table_name, $options, $table_alias);

		$this->assertEquals($expected, $sql);
	}
}
