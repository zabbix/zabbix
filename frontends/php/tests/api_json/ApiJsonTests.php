<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/CHost.php';
require_once dirname(__FILE__).'/CItem.php';
require_once dirname(__FILE__).'/testAPIInfo.php';
require_once dirname(__FILE__).'/testAction.php';
require_once dirname(__FILE__).'/testApplication.php';
require_once dirname(__FILE__).'/testConfiguration.php';
require_once dirname(__FILE__).'/testCorrelation.php';
require_once dirname(__FILE__).'/testDRule.php';
require_once dirname(__FILE__).'/testHostGroup.php';
require_once dirname(__FILE__).'/testIconMap.php';
require_once dirname(__FILE__).'/testProxy.php';
require_once dirname(__FILE__).'/testScripts.php';
require_once dirname(__FILE__).'/testTaskCreate.php';
require_once dirname(__FILE__).'/testUserGroup.php';
require_once dirname(__FILE__).'/testUserMacro.php';
require_once dirname(__FILE__).'/testUsers.php';
require_once dirname(__FILE__).'/testValuemap.php';
require_once dirname(__FILE__).'/testWebScenario.php';
require_once dirname(__FILE__).'/testMap.php';

class ApiJsonTests {
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('API_JSON');

//		$suite->addTestSuite('API_JSON_Host');
//		$suite->addTestSuite('API_JSON_Item');
		$suite->addTestSuite('testAPIInfo');
		$suite->addTestSuite('testAction');
		$suite->addTestSuite('testApplication');
		$suite->addTestSuite('testConfiguration');
		$suite->addTestSuite('testCorrelation');
		$suite->addTestSuite('testDRule');
		$suite->addTestSuite('testHostGroup');
		$suite->addTestSuite('testIconMap');
		$suite->addTestSuite('testProxy');
		$suite->addTestSuite('testScripts');
		$suite->addTestSuite('testTaskCreate');
		$suite->addTestSuite('testUserGroup');
		$suite->addTestSuite('testUserMacro');
		$suite->addTestSuite('testUsers');
		$suite->addTestSuite('testValuemap');
		$suite->addTestSuite('testWebScenario');
		$suite->addTestSuite('testMap');

		return $suite;
	}
}
