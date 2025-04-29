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


require_once dirname(__FILE__).'/testJSONRPC.php';
require_once dirname(__FILE__).'/testAPIInfo.php';

require_once dirname(__FILE__).'/testAction.php';
require_once dirname(__FILE__).'/testAuditlogAction.php';
require_once dirname(__FILE__).'/testAuditlogAutoregistration.php';
require_once dirname(__FILE__).'/testAuditlogConnector.php';
require_once dirname(__FILE__).'/testAuditlogDashboard.php';
require_once dirname(__FILE__).'/testAuditlogEventCorrelation.php';
require_once dirname(__FILE__).'/testAuditlogHousekeeping.php';
require_once dirname(__FILE__).'/testAuditlogIconMap.php';
require_once dirname(__FILE__).'/testAuditlogImages.php';
require_once dirname(__FILE__).'/testAuditlogMaintenance.php';
require_once dirname(__FILE__).'/testAuditlogMediaType.php';
require_once dirname(__FILE__).'/testAuditlogProxy.php';
require_once dirname(__FILE__).'/testAuditlogRegexp.php';
require_once dirname(__FILE__).'/testAuditlogScheduledReport.php';
require_once dirname(__FILE__).'/testAuditlogScript.php';
require_once dirname(__FILE__).'/testAuditlogService.php';
require_once dirname(__FILE__).'/testAuditlogSettings.php';
require_once dirname(__FILE__).'/testAuditlogSLA.php';
require_once dirname(__FILE__).'/testAuditlogToken.php';
require_once dirname(__FILE__).'/testAuditlogUser.php';
require_once dirname(__FILE__).'/testAuditlogUserGroups.php';
require_once dirname(__FILE__).'/testAuthentication.php';
require_once dirname(__FILE__).'/testAutoregistration.php';
require_once dirname(__FILE__).'/testConfiguration.php';
require_once dirname(__FILE__).'/testConnector.php';
require_once dirname(__FILE__).'/testCorrelation.php';
require_once dirname(__FILE__).'/testDRule.php';
require_once dirname(__FILE__).'/testDependentItems.php';
require_once dirname(__FILE__).'/testDiscoveryRule.php';
require_once dirname(__FILE__).'/testGraphPrototype.php';
require_once dirname(__FILE__).'/testHaNode.php';
require_once dirname(__FILE__).'/testHistory.php';
require_once dirname(__FILE__).'/testHost.php';
require_once dirname(__FILE__).'/testHostGroup.php';
require_once dirname(__FILE__).'/testHostImport.php';
require_once dirname(__FILE__).'/testHostInventory.php';
require_once dirname(__FILE__).'/testHostPrototype.php';
require_once dirname(__FILE__).'/testHostPrototypeInventory.php';
require_once dirname(__FILE__).'/testIconMap.php';
require_once dirname(__FILE__).'/testItem.php';
require_once dirname(__FILE__).'/testItemPrototype.php';
require_once dirname(__FILE__).'/testItemPreprocessing.php';
require_once dirname(__FILE__).'/testMaintenance.php';
require_once dirname(__FILE__).'/testMap.php';
require_once dirname(__FILE__).'/testMfa.php';
require_once dirname(__FILE__).'/testProxy.php';
require_once dirname(__FILE__).'/testProxyGroup.php';
require_once dirname(__FILE__).'/testRole.php';
require_once dirname(__FILE__).'/testScimGroup.php';
require_once dirname(__FILE__).'/testScimServiceProviderConfig.php';
require_once dirname(__FILE__).'/testScimUser.php';
require_once dirname(__FILE__).'/testScripts.php';
require_once dirname(__FILE__).'/testServices.php';
require_once dirname(__FILE__).'/testSla.php';
require_once dirname(__FILE__).'/testTagFiltering.php';
require_once dirname(__FILE__).'/testTaskCreate.php';
require_once dirname(__FILE__).'/testTemplate.php';
require_once dirname(__FILE__).'/testTemplateGroup.php';
require_once dirname(__FILE__).'/testToken.php';
require_once dirname(__FILE__).'/testTriggerPermissions.php';
require_once dirname(__FILE__).'/testTriggerValidation.php';
require_once dirname(__FILE__).'/testTriggerPrototypes.php';
require_once dirname(__FILE__).'/testTriggers.php';
require_once dirname(__FILE__).'/testUserDirectory.php';
require_once dirname(__FILE__).'/testUserGroup.php';
require_once dirname(__FILE__).'/testUserMacro.php';
require_once dirname(__FILE__).'/testUsers.php';
require_once dirname(__FILE__).'/testValuemap.php';
require_once dirname(__FILE__).'/testWebScenario.php';
require_once dirname(__FILE__).'/testWebScenarioPermissions.php';

use PHPUnit\Framework\TestSuite;

class ApiJsonTests {
	public static function suite() {
		$suite = new TestSuite('API_JSON');

		$suite->addTestSuite('testJSONRPC');
		$suite->addTestSuite('testAPIInfo');

		$suite->addTestSuite('testAction');
		$suite->addTestSuite('testAuditlogAction');
		$suite->addTestSuite('testAuditlogAutoregistration');
		$suite->addTestSuite('testAuditlogConnector');
		$suite->addTestSuite('testAuditlogDashboard');
		$suite->addTestSuite('testAuditlogEventCorrelation');
		$suite->addTestSuite('testAuditlogHousekeeping');
		$suite->addTestSuite('testAuditlogIconMap');
		$suite->addTestSuite('testAuditlogImages');
		$suite->addTestSuite('testAuditlogMaintenance');
		$suite->addTestSuite('testAuditlogMediaType');
		$suite->addTestSuite('testAuditlogProxy');
		$suite->addTestSuite('testAuditlogRegexp');
		$suite->addTestSuite('testAuditlogScheduledReport');
		$suite->addTestSuite('testAuditlogScript');
		$suite->addTestSuite('testAuditlogService');
		$suite->addTestSuite('testAuditlogSettings');
		$suite->addTestSuite('testAuditlogSLA');
		$suite->addTestSuite('testAuditlogToken');
		$suite->addTestSuite('testAuditlogUser');
		$suite->addTestSuite('testAuditlogUserGroups');
		$suite->addTestSuite('testAuthentication');
		$suite->addTestSuite('testAutoregistration');
		$suite->addTestSuite('testConfiguration');
		$suite->addTestSuite('testConnector');
		$suite->addTestSuite('testCorrelation');
		$suite->addTestSuite('testDRule');
		$suite->addTestSuite('testDependentItems');
		$suite->addTestSuite('testDiscoveryRule');
		$suite->addTestSuite('testGraphPrototype');
		$suite->addTestSuite('testHaNode');
		$suite->addTestSuite('testHistory');
		$suite->addTestSuite('testHost');
		$suite->addTestSuite('testHostGroup');
		$suite->addTestSuite('testHostImport');
		$suite->addTestSuite('testHostInventory');
		$suite->addTestSuite('testHostPrototype');
		$suite->addTestSuite('testHostPrototypeInventory');
		$suite->addTestSuite('testIconMap');
		$suite->addTestSuite('testItem');
		$suite->addTestSuite('testItemPrototype');
		$suite->addTestSuite('testItemPreprocessing');
		$suite->addTestSuite('testMaintenance');
		$suite->addTestSuite('testMap');
		$suite->addTestSuite('testMfa');
		$suite->addTestSuite('testProxy');
		$suite->addTestSuite('testRole');
		$suite->addTestSuite('testScimGroup');
		$suite->addTestSuite('testScimServiceProviderConfig');
		$suite->addTestSuite('testScimUser');
		$suite->addTestSuite('testScripts');
		$suite->addTestSuite('testServices');
		$suite->addTestSuite('testSla');
		$suite->addTestSuite('testTagFiltering');
		$suite->addTestSuite('testTaskCreate');
		$suite->addTestSuite('testTemplate');
		$suite->addTestSuite('testTemplateGroup');
		$suite->addTestSuite('testToken');
		$suite->addTestSuite('testTriggerPermissions');
		$suite->addTestSuite('testTriggerValidation');
		$suite->addTestSuite('testTriggerPrototypes');
		$suite->addTestSuite('testTriggers');
		$suite->addTestSuite('testUserDirectory');
		$suite->addTestSuite('testUserGroup');
		$suite->addTestSuite('testUserMacro');
		$suite->addTestSuite('testUsers');
		$suite->addTestSuite('testValuemap');
		$suite->addTestSuite('testWebScenario');
		$suite->addTestSuite('testWebScenarioPermissions');

		return $suite;
	}
}
