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


require_once dirname(__FILE__).'/testJSONRPC.php';
require_once dirname(__FILE__).'/testAPIInfo.php';
require_once dirname(__FILE__).'/testAction.php';
require_once dirname(__FILE__).'/testConfiguration.php';
require_once dirname(__FILE__).'/testCorrelation.php';
require_once dirname(__FILE__).'/testDRule.php';
require_once dirname(__FILE__).'/testGraphPrototype.php';
require_once dirname(__FILE__).'/testHistory.php';
require_once dirname(__FILE__).'/testHost.php';
require_once dirname(__FILE__).'/testHostInventory.php';
require_once dirname(__FILE__).'/testHostPrototypeInventory.php';
require_once dirname(__FILE__).'/testHostGroup.php';
require_once dirname(__FILE__).'/testHostImport.php';
require_once dirname(__FILE__).'/testIconMap.php';
require_once dirname(__FILE__).'/testItem.php';
require_once dirname(__FILE__).'/testItemPrototype.php';
require_once dirname(__FILE__).'/testMaintenance.php';
require_once dirname(__FILE__).'/testProxy.php';
require_once dirname(__FILE__).'/testServices.php';
require_once dirname(__FILE__).'/testScripts.php';
require_once dirname(__FILE__).'/testTemplate.php';
require_once dirname(__FILE__).'/testTriggers.php';
require_once dirname(__FILE__).'/testTriggerPermissions.php';
require_once dirname(__FILE__).'/testTriggerValidation.php';
require_once dirname(__FILE__).'/testTaskCreate.php';
require_once dirname(__FILE__).'/testTagFiltering.php';
require_once dirname(__FILE__).'/testToken.php';
require_once dirname(__FILE__).'/testUserGroup.php';
require_once dirname(__FILE__).'/testUserMacro.php';
require_once dirname(__FILE__).'/testUsers.php';
require_once dirname(__FILE__).'/testValuemap.php';
require_once dirname(__FILE__).'/testWebScenario.php';
require_once dirname(__FILE__).'/testMap.php';
require_once dirname(__FILE__).'/testDiscoveryRule.php';
require_once dirname(__FILE__).'/testDependentItems.php';
require_once dirname(__FILE__).'/testAuthentication.php';

use PHPUnit\Framework\TestSuite;

class ApiJsonTests {
	public static function suite() {
		$suite = new TestSuite('API_JSON');

		$suite->addTestSuite('testJSONRPC');
		$suite->addTestSuite('testAPIInfo');
		$suite->addTestSuite('testAction');
		$suite->addTestSuite('testConfiguration');
//		$suite->addTestSuite('testDependentItems'); TODO: To be fixed later
		$suite->addTestSuite('testCorrelation');
		$suite->addTestSuite('testDRule');
		$suite->addTestSuite('testGraphPrototype');
		$suite->addTestSuite('testHistory');
		$suite->addTestSuite('testHost');
		$suite->addTestSuite('testHostInventory');
		$suite->addTestSuite('testHostPrototypeInventory');
		$suite->addTestSuite('testHostGroup');
		$suite->addTestSuite('testHostImport');
		$suite->addTestSuite('testIconMap');
		$suite->addTestSuite('testItem');
		$suite->addTestSuite('testItemPrototype');
		$suite->addTestSuite('testMaintenance');
		$suite->addTestSuite('testProxy');
		$suite->addTestSuite('testServices');
		$suite->addTestSuite('testScripts');
		$suite->addTestSuite('testTemplate');
		$suite->addTestSuite('testTriggers');
		$suite->addTestSuite('testTriggerPermissions');
		$suite->addTestSuite('testTriggerValidation');
		$suite->addTestSuite('testTaskCreate');
		$suite->addTestSuite('testTagFiltering');
		$suite->addTestSuite('testToken');
		$suite->addTestSuite('testUserGroup');
		$suite->addTestSuite('testUserMacro');
		$suite->addTestSuite('testUsers');
		$suite->addTestSuite('testValuemap');
		$suite->addTestSuite('testWebScenario');
		$suite->addTestSuite('testMap');
		$suite->addTestSuite('testDiscoveryRule');
		$suite->addTestSuite('testAuthentication');

		return $suite;
	}
}
