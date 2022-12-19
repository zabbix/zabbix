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


require_once dirname(__FILE__).'/testDocumentationLinks.php';
require_once dirname(__FILE__).'/testGeneric.php';

// Actions.
require_once dirname(__FILE__).'/actions/testFormAction.php';
require_once dirname(__FILE__).'/actions/testPageActions.php';

// Api tokens.
require_once dirname(__FILE__).'/apiTokens/testPageApiTokensAdministrationGeneral.php';
require_once dirname(__FILE__).'/apiTokens/testPageApiTokensUserSettings.php';
require_once dirname(__FILE__).'/apiTokens/testFormApiTokensAdministrationGeneral.php';
require_once dirname(__FILE__).'/apiTokens/testFormApiTokensUserSettings.php';

// Authentication.
require_once dirname(__FILE__).'/authentication/testUsersAuthentication.php';
require_once dirname(__FILE__).'/authentication/testUsersAuthenticationHttp.php';
require_once dirname(__FILE__).'/authentication/testUsersAuthenticationLdap.php';
require_once dirname(__FILE__).'/authentication/testUsersAuthenticationSaml.php';
require_once dirname(__FILE__).'/authentication/testUsersPasswordComplexity.php';

// Dashboards.
require_once dirname(__FILE__).'/dashboard/testDashboardCopyWidgets.php';
require_once dirname(__FILE__).'/dashboard/testDashboardDynamicItemWidgets.php';
require_once dirname(__FILE__).'/dashboard/testDashboardFavoriteGraphsWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardFavoriteMapsWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardForm.php';
require_once dirname(__FILE__).'/dashboard/testDashboardGeomapWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardGraphPrototypeWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardGraphWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardHostAvailabilityWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardItemValueWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardPages.php';
require_once dirname(__FILE__).'/dashboard/testDashboardProblemsBySeverityWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardSlaReportWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardSystemInformationWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardTopHostsWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardTriggerOverviewWidget.php';
require_once dirname(__FILE__).'/dashboard/testDashboardViewMode.php';
require_once dirname(__FILE__).'/dashboard/testFormTemplateDashboards.php';
require_once dirname(__FILE__).'/dashboard/testPageDashboardList.php';
require_once dirname(__FILE__).'/dashboard/testPageDashboardWidgets.php';
require_once dirname(__FILE__).'/dashboard/testPageTemplateDashboards.php';

// Filter tabs.
require_once dirname(__FILE__).'/filterTabs/testFormFilterHosts.php';
require_once dirname(__FILE__).'/filterTabs/testFormFilterLatestData.php';
require_once dirname(__FILE__).'/filterTabs/testFormFilterProblems.php';

// Geomaps.
require_once dirname(__FILE__).'/geomaps/testFormAdministrationGeneralGeomaps.php';
require_once dirname(__FILE__).'/geomaps/testGeomapWidgetScreenshots.php';

// Graphs.
require_once dirname(__FILE__).'/graphs/testFormGraph.php';
require_once dirname(__FILE__).'/graphs/testFormGraphPrototype.php';
require_once dirname(__FILE__).'/graphs/testGraphAxis.php';
require_once dirname(__FILE__).'/graphs/testInheritanceGraph.php';
require_once dirname(__FILE__).'/graphs/testInheritanceGraphPrototype.php';
require_once dirname(__FILE__).'/graphs/testPageGraphPrototypes.php';
require_once dirname(__FILE__).'/graphs/testPageHostGraph.php';

// Hosts.
require_once dirname(__FILE__).'/hosts/testFormHostFromConfiguration.php';
require_once dirname(__FILE__).'/hosts/testFormHostFromMonitoring.php';
require_once dirname(__FILE__).'/hosts/testFormHostFromStandalone.php';
require_once dirname(__FILE__).'/hosts/testFormHostLinkTemplates.php';
require_once dirname(__FILE__).'/hosts/testFormHostPrototype.php';
require_once dirname(__FILE__).'/hosts/testPageHostInterfaces.php';
require_once dirname(__FILE__).'/hosts/testPageHostPrototypes.php';
require_once dirname(__FILE__).'/hosts/testPageHosts.php';
require_once dirname(__FILE__).'/hosts/testPageMonitoringHosts.php';

// Items.
require_once dirname(__FILE__).'/items/testFormItem.php';
require_once dirname(__FILE__).'/items/testFormItemHttpAgent.php';
require_once dirname(__FILE__).'/items/testFormItemPrototype.php';
require_once dirname(__FILE__).'/items/testFormTestItem.php';
require_once dirname(__FILE__).'/items/testFormTestItemPrototype.php';
require_once dirname(__FILE__).'/items/testFormulaCalculatedItem.php';
require_once dirname(__FILE__).'/items/testFormulaCalculatedItemPrototype.php';
require_once dirname(__FILE__).'/items/testInheritanceItem.php';
require_once dirname(__FILE__).'/items/testInheritanceItemPrototype.php';
require_once dirname(__FILE__).'/items/testItemTypeSelection.php';
require_once dirname(__FILE__).'/items/testPageItemPrototypes.php';
require_once dirname(__FILE__).'/items/testPageItems.php';

// LLD.
require_once dirname(__FILE__).'/lld/testFormLowLevelDiscovery.php';
require_once dirname(__FILE__).'/lld/testFormLowLevelDiscoveryOverrides.php';
require_once dirname(__FILE__).'/lld/testFormTestLowLevelDiscovery.php';
require_once dirname(__FILE__).'/lld/testInheritanceDiscoveryRule.php';
require_once dirname(__FILE__).'/lld/testPageLowLevelDiscovery.php';

// Macros.
require_once dirname(__FILE__).'/macros/testFormMacrosAdministrationGeneral.php';
require_once dirname(__FILE__).'/macros/testFormMacrosHost.php';
require_once dirname(__FILE__).'/macros/testFormMacrosHostPrototype.php';
require_once dirname(__FILE__).'/macros/testFormMacrosTemplate.php';

// Monitoring.
require_once dirname(__FILE__).'/monitoring/testPageMonitoringLatestData.php';

// Preprocessing.
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingCloneHost.php';
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingCloneTemplate.php';
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingItem.php';
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingItemPrototype.php';
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingLowLevelDiscovery.php';
require_once dirname(__FILE__).'/preprocessing/testFormPreprocessingTest.php';

// Problems.
require_once dirname(__FILE__).'/problems/testFormUpdateProblem.php';
require_once dirname(__FILE__).'/problems/testPageProblems.php';

// Proxies.
require_once dirname(__FILE__).'/proxies/testFormAdministrationProxies.php';
require_once dirname(__FILE__).'/proxies/testPageAdministrationProxies.php';

// Reports.
require_once dirname(__FILE__).'/reports/testFormScheduledReport.php';
require_once dirname(__FILE__).'/reports/testPageAvailabilityReport.php';
require_once dirname(__FILE__).'/reports/testPageReportsActionLog.php';
require_once dirname(__FILE__).'/reports/testPageReportsAudit.php';
require_once dirname(__FILE__).'/reports/testPageReportsNotifications.php';
require_once dirname(__FILE__).'/reports/testPageReportsSystemInformation.php';
require_once dirname(__FILE__).'/reports/testPageReportsTriggerTop.php';
require_once dirname(__FILE__).'/reports/testPageScheduledReport.php';
require_once dirname(__FILE__).'/reports/testScheduledReportPermissions.php';

// Roles.
require_once dirname(__FILE__).'/roles/testFormUserRoles.php';
require_once dirname(__FILE__).'/roles/testPageUserRoles.php';
require_once dirname(__FILE__).'/roles/testUserRolesPermissions.php';

// Services.
require_once dirname(__FILE__).'/services/testFormServicesServices.php';
require_once dirname(__FILE__).'/services/testPageServicesServices.php';
require_once dirname(__FILE__).'/services/testPageServicesServicesMassUpdate.php';

// SLA.
require_once dirname(__FILE__).'/sla/testFormServicesSla.php';
require_once dirname(__FILE__).'/sla/testPageServicesSla.php';
require_once dirname(__FILE__).'/sla/testPageServicesSlaReport.php';

// Tags.
require_once dirname(__FILE__).'/tags/testFormTagsHost.php';
require_once dirname(__FILE__).'/tags/testFormTagsHostPrototype.php';
require_once dirname(__FILE__).'/tags/testFormTagsServices.php';
require_once dirname(__FILE__).'/tags/testFormTagsServicesProblemTags.php';
require_once dirname(__FILE__).'/tags/testFormTagsItem.php';
require_once dirname(__FILE__).'/tags/testFormTagsItemPrototype.php';
require_once dirname(__FILE__).'/tags/testFormTagsTemplate.php';
require_once dirname(__FILE__).'/tags/testFormTagsTrigger.php';
require_once dirname(__FILE__).'/tags/testFormTagsTriggerPrototype.php';
require_once dirname(__FILE__).'/tags/testFormTagsWeb.php';

// Templates.
require_once dirname(__FILE__).'/templates/testFormTemplate.php';
require_once dirname(__FILE__).'/templates/testPageTemplates.php';

// Users.
require_once dirname(__FILE__).'/users/testFormUser.php';
require_once dirname(__FILE__).'/users/testFormUserMedia.php';
require_once dirname(__FILE__).'/users/testFormUserPermissions.php';
require_once dirname(__FILE__).'/users/testFormUserProfile.php';
require_once dirname(__FILE__).'/users/testPageUsers.php';

require_once dirname(__FILE__).'/testExecuteNow.php';
require_once dirname(__FILE__).'/testPageWeb.php';

require_once dirname(__FILE__).'/testFormAdministrationGeneralAutoregistration.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralIconMapping.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralImages.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralModules.php';
require_once dirname(__FILE__).'/testPageAdministrationGeneralRegexp.php';
require_once dirname(__FILE__).'/testPageAdministrationMediaTypes.php';
require_once dirname(__FILE__).'/testPageAdministrationScripts.php';
require_once dirname(__FILE__).'/testPageEventCorrelation.php';
require_once dirname(__FILE__).'/testPageHistory.php';
require_once dirname(__FILE__).'/testPageInventory.php';
require_once dirname(__FILE__).'/testPageTriggers.php';
require_once dirname(__FILE__).'/testPageTriggerUrl.php';
require_once dirname(__FILE__).'/testPageTriggerPrototypes.php';
require_once dirname(__FILE__).'/testPageMaintenance.php';
require_once dirname(__FILE__).'/testPageMaps.php';
require_once dirname(__FILE__).'/testPageMassUpdateItems.php';
require_once dirname(__FILE__).'/testPageMassUpdateItemPrototypes.php';
require_once dirname(__FILE__).'/testPageNetworkDiscovery.php';
/*
require_once dirname(__FILE__).'/testPageQueueDetails.php';
require_once dirname(__FILE__).'/testPageQueueOverview.php';
require_once dirname(__FILE__).'/testPageQueueOverviewByProxy.php';
*/
require_once dirname(__FILE__).'/testPageSearch.php';
require_once dirname(__FILE__).'/testPageStatusOfZabbix.php';
require_once dirname(__FILE__).'/testPageTriggerDescription.php';
require_once dirname(__FILE__).'/testPageUserGroups.php';
require_once dirname(__FILE__).'/testExpandExpressionMacros.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralAuditLog.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralGUI.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralIconMapping.php';
//require_once dirname(__FILE__).'/testFormAdministrationGeneralImages.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralOtherParams.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralRegexp.php';
require_once dirname(__FILE__).'/testFormAdministrationGeneralTrigDisplOptions.php';
require_once dirname(__FILE__).'/testFormAdministrationHousekeeper.php';
require_once dirname(__FILE__).'/testFormAdministrationMediaTypes.php';
require_once dirname(__FILE__).'/testFormAdministrationMediaTypeMessageTemplates.php';
require_once dirname(__FILE__).'/testFormAdministrationMediaTypeWebhook.php';
require_once dirname(__FILE__).'/testFormAdministrationScripts.php';
require_once dirname(__FILE__).'/testFormAdministrationUserGroups.php';
require_once dirname(__FILE__).'/testFormEventCorrelation.php';
require_once dirname(__FILE__).'/filterTabs/testFormFilterHosts.php';
require_once dirname(__FILE__).'/filterTabs/testFormFilterLatestData.php';
require_once dirname(__FILE__).'/filterTabs/testFormFilterProblems.php';
require_once dirname(__FILE__).'/testFormHostGroup.php';
require_once dirname(__FILE__).'/testFormLogin.php';
require_once dirname(__FILE__).'/testFormMaintenance.php';
require_once dirname(__FILE__).'/testFormMap.php';
require_once dirname(__FILE__).'/testFormNetworkDiscovery.php';
require_once dirname(__FILE__).'/testFormSetup.php';
require_once dirname(__FILE__).'/testFormSysmap.php';
require_once dirname(__FILE__).'/testFormTabIndicators.php';
require_once dirname(__FILE__).'/testFormTrigger.php';
require_once dirname(__FILE__).'/testFormTriggerPrototype.php';
require_once dirname(__FILE__).'/testFormValueMappingsHost.php';
require_once dirname(__FILE__).'/testFormValueMappingsTemplate.php';
require_once dirname(__FILE__).'/testFormWeb.php';
require_once dirname(__FILE__).'/testFormWebStep.php';
require_once dirname(__FILE__).'/testPageBrowserWarning.php';
require_once dirname(__FILE__).'/testInheritanceTrigger.php';
require_once dirname(__FILE__).'/testInheritanceWeb.php';
require_once dirname(__FILE__).'/testInheritanceTriggerPrototype.php';
require_once dirname(__FILE__).'/testInheritanceHostPrototype.php';
require_once dirname(__FILE__).'/testLanguage.php';
require_once dirname(__FILE__).'/testMultiselect.php';
require_once dirname(__FILE__).'/testTagBasedPermissions.php';
require_once dirname(__FILE__).'/testTemplateInheritance.php';
require_once dirname(__FILE__).'/testTimezone.php';
require_once dirname(__FILE__).'/testTriggerDependencies.php';
require_once dirname(__FILE__).'/testTriggerExpressions.php';
require_once dirname(__FILE__).'/testSidebarMenu.php';
require_once dirname(__FILE__).'/testUrlParameters.php';
require_once dirname(__FILE__).'/testUrlUserPermissions.php';
require_once dirname(__FILE__).'/testZBX6648.php';
require_once dirname(__FILE__).'/testZBX6663.php';
require_once dirname(__FILE__).'/testSID.php';

use PHPUnit\Framework\TestSuite;

class SeleniumTests {
	public static function suite() {
		$suite = new TestSuite('selenium');

		$suite->addTestSuite('testDocumentationLinks');
		$suite->addTestSuite('testGeneric');

		// Actions.
		$suite->addTestSuite('testFormAction');
		$suite->addTestSuite('testPageActions');

		// Api tokens.
		$suite->addTestSuite('testFormApiTokensAdministrationGeneral');
		$suite->addTestSuite('testFormApiTokensUserSettings');
		$suite->addTestSuite('testPageApiTokensAdministrationGeneral');
		$suite->addTestSuite('testPageApiTokensUserSettings');

		// Authentication.
		$suite->addTestSuite('testUsersAuthentication');
		$suite->addTestSuite('testUsersAuthenticationHttp');
		$suite->addTestSuite('testUsersAuthenticationLdap');
		$suite->addTestSuite('testUsersAuthenticationSaml');
		$suite->addTestSuite('testUsersPasswordComplexity');

		// Dashboards.
		$suite->addTestSuite('testDashboardCopyWidgets');
		$suite->addTestSuite('testDashboardDynamicItemWidgets');
		$suite->addTestSuite('testDashboardFavoriteGraphsWidget');
		$suite->addTestSuite('testDashboardFavoriteMapsWidget');
		$suite->addTestSuite('testDashboardForm');
		$suite->addTestSuite('testDashboardGeomapWidget');
		$suite->addTestSuite('testDashboardGraphPrototypeWidget');
		$suite->addTestSuite('testDashboardGraphWidget');
		$suite->addTestSuite('testDashboardHostAvailabilityWidget');
		$suite->addTestSuite('testDashboardItemValueWidget');
		$suite->addTestSuite('testDashboardPages');
		$suite->addTestSuite('testDashboardProblemsBySeverityWidget');
		$suite->addTestSuite('testDashboardSlaReportWidget');
		$suite->addTestSuite('testDashboardSystemInformationWidget');
		$suite->addTestSuite('testDashboardTopHostsWidget');
		$suite->addTestSuite('testDashboardTriggerOverviewWidget');
		$suite->addTestSuite('testDashboardViewMode');
		$suite->addTestSuite('testFormTemplateDashboards');
		$suite->addTestSuite('testPageDashboardList');
		$suite->addTestSuite('testPageDashboardWidgets');
		$suite->addTestSuite('testPageTemplateDashboards');

		// Filter tabs.
		$suite->addTestSuite('testFormFilterHosts');
		$suite->addTestSuite('testFormFilterLatestData');
		$suite->addTestSuite('testFormFilterProblems');

		// Geomaps.
		$suite->addTestSuite('testFormAdministrationGeneralGeomaps');
		$suite->addTestSuite('testGeomapWidgetScreenshots');

		// Graphs.
		$suite->addTestSuite('testFormGraph');
		$suite->addTestSuite('testFormGraphPrototype');
		$suite->addTestSuite('testGraphAxis');
		$suite->addTestSuite('testInheritanceGraph');
		$suite->addTestSuite('testInheritanceGraphPrototype');
		$suite->addTestSuite('testPageGraphPrototypes');
		$suite->addTestSuite('testPageHostGraph');

		// Hosts.
		$suite->addTestSuite('testFormHostFromConfiguration');
		$suite->addTestSuite('testFormHostFromMonitoring');
		$suite->addTestSuite('testFormHostFromStandalone');
		$suite->addTestSuite('testFormHostLinkTemplates');
		$suite->addTestSuite('testFormHostPrototype');
		$suite->addTestSuite('testPageHostInterfaces');
		$suite->addTestSuite('testPageHostPrototypes');
		$suite->addTestSuite('testPageHosts');
		$suite->addTestSuite('testPageMonitoringHosts');

		// Items.
		$suite->addTestSuite('testFormItem');
		$suite->addTestSuite('testFormItemHttpAgent');
		$suite->addTestSuite('testFormItemPrototype');
		$suite->addTestSuite('testFormTestItem');
		$suite->addTestSuite('testFormTestItemPrototype');
		$suite->addTestSuite('testFormulaCalculatedItem');
		$suite->addTestSuite('testFormulaCalculatedItemPrototype');
		$suite->addTestSuite('testInheritanceItem');
		$suite->addTestSuite('testInheritanceItemPrototype');
		$suite->addTestSuite('testItemTypeSelection');
		$suite->addTestSuite('testPageItemPrototypes');
		$suite->addTestSuite('testPageItems');

		// LLD.
		$suite->addTestSuite('testFormLowLevelDiscovery');
		$suite->addTestSuite('testFormLowLevelDiscoveryOverrides');
		$suite->addTestSuite('testFormTestLowLevelDiscovery');
		$suite->addTestSuite('testInheritanceDiscoveryRule');
		$suite->addTestSuite('testPageLowLevelDiscovery');

		// Macros.
		$suite->addTestSuite('testFormMacrosAdministrationGeneral');
		$suite->addTestSuite('testFormMacrosHost');
		$suite->addTestSuite('testFormMacrosHostPrototype');
		$suite->addTestSuite('testFormMacrosTemplate');

		// Monitoring.
		$suite->addTestSuite('testPageMonitoringLatestData');

		// Preprocessing.
		$suite->addTestSuite('testFormPreprocessingCloneHost');
		$suite->addTestSuite('testFormPreprocessingCloneTemplate');
		$suite->addTestSuite('testFormPreprocessingItem');
		$suite->addTestSuite('testFormPreprocessingItemPrototype');
		$suite->addTestSuite('testFormPreprocessingLowLevelDiscovery');
		$suite->addTestSuite('testFormPreprocessingTest');

		// Problems.
		$suite->addTestSuite('testPageProblems');
		$suite->addTestSuite('testFormUpdateProblem');

		// Proxies.
		$suite->addTestSuite('testFormAdministrationProxies');
		$suite->addTestSuite('testPageAdministrationProxies');

		// Reports.
		$suite->addTestSuite('testFormScheduledReport');
		$suite->addTestSuite('testPageAvailabilityReport');
		$suite->addTestSuite('testPageReportsActionLog');
		$suite->addTestSuite('testPageReportsAudit');
		$suite->addTestSuite('testPageReportsNotifications');
		$suite->addTestSuite('testPageReportsSystemInformation');
		$suite->addTestSuite('testPageReportsTriggerTop');
		$suite->addTestSuite('testPageScheduledReport');
		$suite->addTestSuite('testScheduledReportPermissions');

		// Roles.
		$suite->addTestSuite('testFormUserRoles');
		$suite->addTestSuite('testPageUserRoles');
		$suite->addTestSuite('testUserRolesPermissions');

		// Services.
		$suite->addTestSuite('testFormServicesServices');
		$suite->addTestSuite('testPageServicesServices');
		$suite->addTestSuite('testPageServicesServicesMassUpdate');

		// SLA.
		$suite->addTestSuite('testFormServicesSla');
		$suite->addTestSuite('testPageServicesSla');
		$suite->addTestSuite('testPageServicesSlaReport');

		// Tags.
		$suite->addTestSuite('testFormTagsHost');
		$suite->addTestSuite('testFormTagsHostPrototype');
		$suite->addTestSuite('testFormTagsServices');
		$suite->addTestSuite('testFormTagsServicesProblemTags');
		$suite->addTestSuite('testFormTagsItem');
		$suite->addTestSuite('testFormTagsItemPrototype');
		$suite->addTestSuite('testFormTagsTemplate');
		$suite->addTestSuite('testFormTagsTrigger');
		$suite->addTestSuite('testFormTagsTriggerPrototype');
		$suite->addTestSuite('testFormTagsWeb');

		// Templates.
		$suite->addTestSuite('testFormTemplate');
		$suite->addTestSuite('testPageTemplates');

		// Users.
		$suite->addTestSuite('testFormUser');
		$suite->addTestSuite('testFormUserMedia');
		$suite->addTestSuite('testFormUserPermissions');
		$suite->addTestSuite('testFormUserProfile');
		$suite->addTestSuite('testPageUsers');

		$suite->addTestSuite('testExecuteNow');
		$suite->addTestSuite('testFormAdministrationGeneralAutoregistration');
		$suite->addTestSuite('testPageAdministrationGeneralIconMapping');
		$suite->addTestSuite('testPageAdministrationGeneralImages');
		$suite->addTestSuite('testPageAdministrationGeneralModules');
		$suite->addTestSuite('testPageAdministrationGeneralRegexp');
		$suite->addTestSuite('testPageAdministrationMediaTypes');
		$suite->addTestSuite('testPageAdministrationScripts');
		$suite->addTestSuite('testPageEventCorrelation');
		$suite->addTestSuite('testPageHistory');
		$suite->addTestSuite('testPageInventory');
		$suite->addTestSuite('testPageTriggers');
		$suite->addTestSuite('testPageTriggerDescription');
		$suite->addTestSuite('testPageTriggerUrl');
		$suite->addTestSuite('testPageTriggerPrototypes');
		$suite->addTestSuite('testPageMaintenance');
		$suite->addTestSuite('testPageMaps');
		$suite->addTestSuite('testPageMassUpdateItems');
		$suite->addTestSuite('testPageMassUpdateItemPrototypes');
		$suite->addTestSuite('testPageNetworkDiscovery');
/*
		$suite->addTestSuite('testPageQueueDetails');
		$suite->addTestSuite('testPageQueueOverview');
		$suite->addTestSuite('testPageQueueOverviewByProxy');
*/
		$suite->addTestSuite('testPageSearch');
		$suite->addTestSuite('testPageStatusOfZabbix');
		$suite->addTestSuite('testPageUserGroups');
		$suite->addTestSuite('testPageWeb');
		$suite->addTestSuite('testExpandExpressionMacros');
		$suite->addTestSuite('testFormAdministrationGeneralAuditLog');
		$suite->addTestSuite('testFormAdministrationGeneralGUI');
		$suite->addTestSuite('testFormAdministrationGeneralIconMapping');
//		$suite->addTestSuite('testFormAdministrationGeneralImages');
		$suite->addTestSuite('testFormAdministrationGeneralOtherParams');
		$suite->addTestSuite('testFormAdministrationGeneralRegexp');
		$suite->addTestSuite('testFormAdministrationGeneralTrigDisplOptions');
		$suite->addTestSuite('testFormAdministrationHousekeeper');
		$suite->addTestSuite('testFormAdministrationMediaTypes');
		$suite->addTestSuite('testFormAdministrationMediaTypeMessageTemplates');
		$suite->addTestSuite('testFormAdministrationMediaTypeWebhook');
		$suite->addTestSuite('testFormAdministrationScripts');
		$suite->addTestSuite('testFormAdministrationUserGroups');
		$suite->addTestSuite('testFormEventCorrelation');
		$suite->addTestSuite('testFormHostGroup');
		$suite->addTestSuite('testFormLogin');
		$suite->addTestSuite('testFormMaintenance');
		$suite->addTestSuite('testFormMap');
		$suite->addTestSuite('testFormNetworkDiscovery');
		$suite->addTestSuite('testFormSetup');
		$suite->addTestSuite('testFormSysmap');
		$suite->addTestSuite('testFormTabIndicators');
		$suite->addTestSuite('testFormTrigger');
		$suite->addTestSuite('testFormTriggerPrototype');
		$suite->addTestSuite('testFormValueMappingsHost');
		$suite->addTestSuite('testFormValueMappingsTemplate');
		$suite->addTestSuite('testFormWeb');
		$suite->addTestSuite('testFormWebStep');
		$suite->addTestSuite('testPageBrowserWarning');
		$suite->addTestSuite('testInheritanceTrigger');
		$suite->addTestSuite('testInheritanceWeb');
		$suite->addTestSuite('testInheritanceHostPrototype');
		$suite->addTestSuite('testInheritanceTriggerPrototype');
		$suite->addTestSuite('testLanguage');
		$suite->addTestSuite('testMultiselect');
		$suite->addTestSuite('testTagBasedPermissions');
		$suite->addTestSuite('testTemplateInheritance');
		$suite->addTestSuite('testTimezone');
		$suite->addTestSuite('testTriggerDependencies');
		$suite->addTestSuite('testTriggerExpressions');
		$suite->addTestSuite('testSidebarMenu');
		$suite->addTestSuite('testUrlParameters');
		$suite->addTestSuite('testUrlUserPermissions');
		$suite->addTestSuite('testZBX6648');
		$suite->addTestSuite('testZBX6663');
		$suite->addTestSuite('testSID');

		return $suite;
	}
}
