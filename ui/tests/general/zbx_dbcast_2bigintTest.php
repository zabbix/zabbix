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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';


use PHPUnit\Framework\TestCase;

class zbx_dbcast_2bigintTest extends TestCase {

	/**
	 * Possible test values.
	 *
	 * @return array
	 */
	public static function provider() {
		return [
			['field'],
			['field_1']
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test_mysql($field) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_MYSQL;

		$this->assertEquals('CAST('.$field.' AS UNSIGNED)', zbx_dbcast_2bigint($field));

		$DB = $oldDB;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_postgresql($field) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_POSTGRESQL;

		$this->assertEquals('CAST('.$field.' AS BIGINT)', zbx_dbcast_2bigint($field));

		$DB = $oldDB;
	}
}
