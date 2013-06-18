<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class zbx_limitTest extends PHPUnit_Framework_TestCase {

	/**
	 * Possible test values.
	 *
	 * @return array
	 */
	public static function provider() {
		return array(
			array(0),
			array(1),
			array(100),
			array(9999),
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_ibmdb2($limit) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_DB2;

		$this->assertEquals('AND rownum<='.$limit, zbx_limit($limit));

		$DB = $oldDB;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_mysql($limit) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_MYSQL;

		$this->assertEquals('LIMIT '.$limit, zbx_limit($limit));

		$DB = $oldDB;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_oracle($limit) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_ORACLE;

		$this->assertEquals('AND rownum<='.$limit, zbx_limit($limit));

		$DB = $oldDB;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_postgresql($limit) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_POSTGRESQL;

		$this->assertEquals('LIMIT '.$limit, zbx_limit($limit));

		$DB = $oldDB;
	}

	/**
	 * @dataProvider provider
	 */
	public function test_sqlite($limit) {
		global $DB;

		$oldDB = $DB;

		$DB['TYPE'] = ZBX_DB_SQLITE3;

		$this->assertEquals('LIMIT '.$limit, zbx_limit($limit));

		$DB = $oldDB;
	}
}
