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

require_once dirname(__FILE__).'/testInitialConfSync.php';
require_once dirname(__FILE__).'/testProxyConfSync.php';
require_once dirname(__FILE__).'/testTimescaleDb.php';
require_once dirname(__FILE__).'/testDataCollection.php';
require_once dirname(__FILE__).'/testBinaryValueTypeDataCollection.php';
require_once dirname(__FILE__).'/testDiagnosticDataTask.php';
require_once dirname(__FILE__).'/testLowLevelDiscovery.php';
require_once dirname(__FILE__).'/testGoAgentDataCollection.php';
require_once dirname(__FILE__).'/testItemState.php';
require_once dirname(__FILE__).'/testValuemaps.php';
require_once dirname(__FILE__).'/testTriggerLinking.php';
require_once dirname(__FILE__).'/testGraphLinking.php';
require_once dirname(__FILE__).'/testEscalations.php';
require_once dirname(__FILE__).'/testAlertingForServices.php';
require_once dirname(__FILE__).'/testComplexServiceStatus.php';
require_once dirname(__FILE__).'/testServiceRoles.php';
require_once dirname(__FILE__).'/testExpressionMacros.php';
require_once dirname(__FILE__).'/testExpressionTriggerMacros.php';
require_once dirname(__FILE__).'/testAgentItems.php';
require_once dirname(__FILE__).'/testScriptItems.php';
require_once dirname(__FILE__).'/testItemRate.php';
require_once dirname(__FILE__).'/testHistoryValueDuplicates.php';
require_once dirname(__FILE__).'/testHighAvailability.php';
require_once dirname(__FILE__).'/testUserParametersReload.php';
require_once dirname(__FILE__).'/testTriggerState.php';
require_once dirname(__FILE__).'/testActiveAvailability.php';
require_once dirname(__FILE__).'/testEventsCauseAndSymptoms.php';
require_once dirname(__FILE__).'/testDiscoveryRules.php';
require_once dirname(__FILE__).'/testAutoregistration.php';
require_once dirname(__FILE__).'/testHistoryPush.php';
require_once dirname(__FILE__).'/testItemTimeouts.php';
require_once dirname(__FILE__).'/testUserMacrosInItemNames.php';
require_once dirname(__FILE__).'/testScriptManualInput.php';
require_once dirname(__FILE__).'/testAgentJsonProtocol.php';
require_once dirname(__FILE__).'/testSnmpTrapsInHa.php';
require_once dirname(__FILE__).'/testPermissions.php';
require_once dirname(__FILE__).'/testFunctionChangeCount.php';
require_once dirname(__FILE__).'/testProxyHa.php';
require_once dirname(__FILE__).'/testBrowserMonitoring.php';
require_once dirname(__FILE__).'/testHostConnMacroValidation.php';

use PHPUnit\Framework\TestSuite;

class IntegrationTests {
	public static function suite() {
		$suite = new TestSuite('Integration');

		if  (substr(getenv('DB'), 0, 4) === "tsdb" ) {
			$suite->addTestSuite('testTimescaleDb');
		}
		$suite->addTestSuite('testDiscoveryRules');
		$suite->addTestSuite('testAutoregistration');
		$suite->addTestSuite('testDataCollection');
		$suite->addTestSuite('testBinaryValueTypeDataCollection');
		$suite->addTestSuite('testDiagnosticDataTask');
		$suite->addTestSuite('testLowLevelDiscovery');
		$suite->addTestSuite('testGoAgentDataCollection');
		$suite->addTestSuite('testItemState');
		$suite->addTestSuite('testValuemaps');
		$suite->addTestSuite('testTriggerLinking');
		$suite->addTestSuite('testGraphLinking');
		$suite->addTestSuite('testEscalations');
		$suite->addTestSuite('testAlertingForServices');
		$suite->addTestSuite('testComplexServiceStatus');
		$suite->addTestSuite('testServiceRoles');
		$suite->addTestSuite('testExpressionMacros');
		$suite->addTestSuite('testExpressionTriggerMacros');
		$suite->addTestSuite('testScriptItems');
		$suite->addTestSuite('testItemRate');
		$suite->addTestSuite('testHistoryValueDuplicates');
		$suite->addTestSuite('testHighAvailability');
		$suite->addTestSuite('testUserParametersReload');
		$suite->addTestSuite('testTriggerState');
		$suite->addTestSuite('testActiveAvailability');
		$suite->addTestSuite('testProxyConfSync');
		$suite->addTestSuite('testInitialConfSync');
		$suite->addTestSuite('testEventsCauseAndSymptoms');
		$suite->addTestSuite('testHistoryPush');
		$suite->addTestSuite('testItemTimeouts');
		$suite->addTestSuite('testUserMacrosInItemNames');
		$suite->addTestSuite('testScriptManualInput');
		$suite->addTestSuite('testAgentJsonProtocol');
		$suite->addTestSuite('testSnmpTrapsInHa');
		$suite->addTestSuite('testPermissions');
		$suite->addTestSuite('testFunctionChangeCount');
		$suite->addTestSuite('testProxyHa');
		$suite->addTestSuite('testBrowserMonitoring');
		$suite->addTestSuite('testHostConnMacroValidation');

		return $suite;
	}
}
