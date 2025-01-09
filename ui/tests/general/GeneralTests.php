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


require_once dirname(__FILE__).'/APITest.php';
require_once dirname(__FILE__).'/function_DBconnect.php';
require_once dirname(__FILE__).'/function_DBclose.php';
require_once dirname(__FILE__).'/function_DBselect.php';
require_once dirname(__FILE__).'/zbx_dbcast_2bigintTest.php';
require_once dirname(__FILE__).'/makeSqlTest.php';
require_once dirname(__FILE__).'/dbConditionIntTest.php';
require_once dirname(__FILE__).'/dbConditionIdTest.php';
require_once dirname(__FILE__).'/dbConditionStringTest.php';
require_once dirname(__FILE__).'/testHistoryManager.php';
require_once dirname(__FILE__).'/urlParamTest.php';
require_once dirname(__FILE__).'/CApiTagHelperTest.php';

use PHPUnit\Framework\TestSuite;

class GeneralTests {

	public static function suite() {
		$suite = new TestSuite('general');

		$suite->addTestSuite('APITest');
		$suite->addTestSuite('function_DBconnect');
		$suite->addTestSuite('function_DBclose');
		$suite->addTestSuite('function_DBselect');
		$suite->addTestSuite('zbx_dbcast_2bigintTest');
		$suite->addTestSuite('makeSqlTest');
		$suite->addTestSuite('dbConditionIntTest');
		$suite->addTestSuite('dbConditionIdTest');
		$suite->addTestSuite('dbConditionStringTest');
		$suite->addTestSuite('testHistoryManager');
		$suite->addTestSuite('urlParamTest');
		$suite->addTestSuite('CApiTagHelperTest');

		return $suite;
	}
}
