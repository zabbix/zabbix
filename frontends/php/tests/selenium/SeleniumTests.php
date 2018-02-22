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

require_once dirname(__FILE__).'/testGeneric.php';
require_once dirname(__FILE__).'/testPageDashboard.php';
require_once dirname(__FILE__).'/testPageOverview.php';
require_once dirname(__FILE__).'/testPageLatestData.php';
require_once dirname(__FILE__).'/testPageWeb.php';
require_once dirname(__FILE__).'/testPageStatusOfTriggers.php';
require_once dirname(__FILE__).'/testPageProblems.php';
require_once dirname(__FILE__).'/testPageScreens.php';
require_once dirname(__FILE__).'/testPageActions.php';
require_once dirname(__FILE__).'/testPageAdministrationAudit.php';
require_once dirname(__FILE__).'/testPageAdministrationAuditActions.php';
require_once dirname(__FILE__).'/testPageAdministrationDMProxies.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralImages.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralRegexp.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralValuemap.php';
require_once dirname(__FILE__).'/testPageAdministrationMediaTypes.php';
require_once dirname(__FILE__).'/testPageAdministrationScripts.php';
require_once dirname(__FILE__).'/testPageAvailabilityReport.php';
require_once dirname(__FILE__).'/testPageDiscovery.php';
require_once dirname(__FILE__).'/testPageDiscoveryRules.php';
require_once dirname(__FILE__).'/testPageHistory.php';
require_once dirname(__FILE__).'/testPageHosts.php';
require_once dirname(__FILE__).'/testPageInventory.php';
require_once dirname(__FILE__).'/testPageItems.php';
require_once dirname(__FILE__).'/testPageItemPrototypes.php';
require_once dirname(__FILE__).'/testPageTriggers.php';
require_once dirname(__FILE__).'/testPageTriggerPrototypes.php';
require_once dirname(__FILE__).'/testPageMaintenance.php';
require_once dirname(__FILE__).'/testPageMaps.php';
/*
require_once dirname(__FILE__).'/testPageQueueDetails.php';
require_once dirname(__FILE__).'/testPageQueueOverview.php';
require_once dirname(__FILE__).'/testPageQueueOverviewByProxy.php';
*/
require_once dirname(__FILE__).'/testPageSearch.php';
require_once dirname(__FILE__).'/testPageSlideShows.php';
require_once dirname(__FILE__).'/testPageStatusOfZabbix.php';
require_once dirname(__FILE__).'/testPageTemplates.php';
require_once dirname(__FILE__).'/testPageUserGroups.php';
require_once dirname(__FILE__).'/testPageUsers.php';
require_once dirname(__FILE__).'/testFormAction.php';
require_once dirname(__FILE__).'/testFormAdministrationDMProxies.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralGUI.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralHousekeeper.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralImages.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralMacro.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralOtherParams.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralRegexp.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralTrigDisplOptions.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralValuemap.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralWorkperiod.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralInstallation.php';
require_once dirname(__FILE__).'/testFormAdministrationMediaTypes.php';
require_once dirname(__FILE__).'/testFormAdministrationScripts.php';
require_once dirname(__FILE__).'/testFormAdministrationUserCreate.php';
require_once dirname(__FILE__).'/testFormAdministrationUserGroups.php';
require_once dirname(__FILE__).'/testFormConfigTriggerSeverity.php';
require_once dirname(__FILE__).'/testFormDiscoveryRule.php';
require_once dirname(__FILE__).'/testFormGraph.php';
require_once dirname(__FILE__).'/testFormGraphPrototype.php';
require_once dirname(__FILE__).'/testFormHost.php';
require_once dirname(__FILE__).'/testFormHostGroup.php';
require_once dirname(__FILE__).'/testFormItem.php';
require_once dirname(__FILE__).'/testFormItemPrototype.php';
require_once dirname(__FILE__).'/testFormLogin.php';
require_once dirname(__FILE__).'/testFormMaintenance.php';
require_once dirname(__FILE__).'/testFormMap.php';
require_once dirname(__FILE__).'/testFormScreen.php';
require_once dirname(__FILE__).'/testFormSysmap.php';
require_once dirname(__FILE__).'/testFormTrigger.php';
require_once dirname(__FILE__).'/testFormTemplate.php';
require_once dirname(__FILE__).'/testFormTriggerPrototype.php';
require_once dirname(__FILE__).'/testFormUserProfile.php';
require_once dirname(__FILE__).'/testFormWeb.php';
require_once dirname(__FILE__).'/testFormWebStep.php';
require_once dirname(__FILE__).'/testFormApplication.php';
require_once dirname(__FILE__).'/testPageApplications.php';
require_once dirname(__FILE__).'/testPageBrowserWarning.php';
require_once dirname(__FILE__).'/testInheritanceItem.php';
require_once dirname(__FILE__).'/testInheritanceTrigger.php';
require_once dirname(__FILE__).'/testInheritanceGraph.php';
require_once dirname(__FILE__).'/testInheritanceWeb.php';
require_once dirname(__FILE__).'/testInheritanceDiscoveryRule.php';
require_once dirname(__FILE__).'/testInheritanceItemPrototype.php';
require_once dirname(__FILE__).'/testInheritanceTriggerPrototype.php';
require_once dirname(__FILE__).'/testInheritanceGraphPrototype.php';
require_once dirname(__FILE__).'/testTemplateInheritance.php';
require_once dirname(__FILE__).'/testTriggerDependencies.php';
require_once dirname(__FILE__).'/testTriggerExpressions.php';
require_once dirname(__FILE__).'/testUrlParameters.php';
require_once dirname(__FILE__).'/testUrlUserPermissions.php';
require_once dirname(__FILE__).'/testZBX6339.php';
require_once dirname(__FILE__).'/testZBX6648.php';
require_once dirname(__FILE__).'/testZBX6663.php';

class SeleniumTests {
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('selenium');

		$suite->addTestSuite('testGeneric');
		$suite->addTestSuite('testPageActions');
		$suite->addTestSuite('testPageAdministrationAudit');
		$suite->addTestSuite('testPageAdministrationAuditActions');
		$suite->addTestSuite('testPageAdministrationDMProxies');
		$suite->addTestSuite('testPageAdministrationGeneralImages');
		$suite->addTestSuite('testPageAdministrationGeneralRegexp');
		$suite->addTestSuite('testPageAdministrationGeneralValuemap');
		$suite->addTestSuite('testPageAdministrationMediaTypes');
		$suite->addTestSuite('testPageAdministrationScripts');
		$suite->addTestSuite('testPageAvailabilityReport');
		$suite->addTestSuite('testPageDashboard');
		$suite->addTestSuite('testPageDiscovery');
		$suite->addTestSuite('testPageDiscoveryRules');
		$suite->addTestSuite('testPageProblems');
		$suite->addTestSuite('testPageHistory');
		$suite->addTestSuite('testPageHosts');
		$suite->addTestSuite('testPageInventory');
		$suite->addTestSuite('testPageItems');
		$suite->addTestSuite('testPageItemPrototypes');
		$suite->addTestSuite('testPageTriggers');
		$suite->addTestSuite('testPageTriggerPrototypes');
		$suite->addTestSuite('testPageLatestData');
		$suite->addTestSuite('testPageMaintenance');
		$suite->addTestSuite('testPageMaps');
		$suite->addTestSuite('testPageOverview');
/*
		$suite->addTestSuite('testPageQueueDetails');
		$suite->addTestSuite('testPageQueueOverview');
		$suite->addTestSuite('testPageQueueOverviewByProxy');
*/
		$suite->addTestSuite('testPageScreens');
		$suite->addTestSuite('testPageSearch');
		$suite->addTestSuite('testPageSlideShows');
		$suite->addTestSuite('testPageStatusOfTriggers');
		$suite->addTestSuite('testPageStatusOfZabbix');
		$suite->addTestSuite('testPageTemplates');
		$suite->addTestSuite('testPageUserGroups');
		$suite->addTestSuite('testPageUsers');
		$suite->addTestSuite('testPageWeb');
		$suite->addTestSuite('testFormAction');
		$suite->addTestSuite('testFormAdministrationDMProxies');
		$suite->addTestSuite('testFormAdministrationGeneralGUI');
		$suite->addTestSuite('testFormAdministrationGeneralHousekeeper');
		$suite->addTestSuite('testFormAdministrationGeneralImages');
		$suite->addTestSuite('testFormAdministrationGeneralMacro');
		$suite->addTestSuite('testFormAdministrationGeneralOtherParams');
		$suite->addTestSuite('testFormAdministrationGeneralRegexp');
		$suite->addTestSuite('testFormAdministrationGeneralTrigDisplOptions');
		$suite->addTestSuite('testFormAdministrationGeneralValuemap');
		$suite->addTestSuite('testFormAdministrationGeneralWorkperiod');
		$suite->addTestSuite('testFormAdministrationGeneralInstallation');
		$suite->addTestSuite('testFormAdministrationMediaTypes');
		$suite->addTestSuite('testFormAdministrationScripts');
		$suite->addTestSuite('testFormAdministrationUserCreate');
		$suite->addTestSuite('testFormAdministrationUserGroups');
		$suite->addTestSuite('testFormConfigTriggerSeverity');
		$suite->addTestSuite('testFormDiscoveryRule');
		$suite->addTestSuite('testFormGraph');
		$suite->addTestSuite('testFormGraphPrototype');
		$suite->addTestSuite('testFormHost');
		$suite->addTestSuite('testFormHostGroup');
		$suite->addTestSuite('testFormItem');
		$suite->addTestSuite('testFormItemPrototype');
		$suite->addTestSuite('testFormLogin');
		$suite->addTestSuite('testFormMaintenance');
		$suite->addTestSuite('testFormMap');
		$suite->addTestSuite('testFormScreen');
		$suite->addTestSuite('testFormSysmap');
		$suite->addTestSuite('testFormTemplate');
		$suite->addTestSuite('testFormTrigger');
		$suite->addTestSuite('testFormTriggerPrototype');
		$suite->addTestSuite('testFormUserProfile');
		$suite->addTestSuite('testFormWeb');
		$suite->addTestSuite('testFormWebStep');
		$suite->addTestSuite('testFormApplication');
		$suite->addTestSuite('testPageApplications');
		$suite->addTestSuite('testPageBrowserWarning');
		$suite->addTestSuite('testInheritanceItem');
		$suite->addTestSuite('testInheritanceTrigger');
		$suite->addTestSuite('testInheritanceGraph');
		$suite->addTestSuite('testInheritanceGraphPrototype');
		$suite->addTestSuite('testInheritanceWeb');
		$suite->addTestSuite('testInheritanceDiscoveryRule');
		$suite->addTestSuite('testInheritanceItemPrototype');
		$suite->addTestSuite('testInheritanceTriggerPrototype');
		$suite->addTestSuite('testTemplateInheritance');
		$suite->addTestSuite('testTriggerDependencies');
		$suite->addTestSuite('testTriggerExpressions');
		$suite->addTestSuite('testUrlParameters');
		$suite->addTestSuite('testUrlUserPermissions');
		$suite->addTestSuite('testZBX6339');
		$suite->addTestSuite('testZBX6648');
		$suite->addTestSuite('testZBX6663');

		return $suite;
	}
}
