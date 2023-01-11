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
	public function test_oracle($field) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_ORACLE;

		$this->assertEquals('CAST('.$field.' AS NUMBER(20))', zbx_dbcast_2bigint($field));

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
