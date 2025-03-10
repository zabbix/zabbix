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


// Actions.
require_once __DIR__.'/actions/testFormAction.php';
require_once __DIR__.'/actions/testPageActions.php';

// Administration.
require_once __DIR__.'/administration/testFormAdministrationAuditLog.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralAutoregistration.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralGeomaps.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralGUI.php';
//require_once __DIR__.'/administration/testFormAdministrationGeneralImages.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralOtherParams.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralTimeouts.php';
require_once __DIR__.'/administration/testFormAdministrationGeneralTrigDisplOptions.php';
require_once __DIR__.'/administration/testFormAdministrationHousekeeping.php';
require_once __DIR__.'/administration/testPageAdministrationGeneralImages.php';
require_once __DIR__.'/administration/testPageAdministrationGeneralModules.php';

// Api tokens.
require_once __DIR__.'/apiTokens/testPageApiTokensAdministrationGeneral.php';
require_once __DIR__.'/apiTokens/testPageApiTokensUserSettings.php';
require_once __DIR__.'/apiTokens/testFormApiTokensAdministrationGeneral.php';
require_once __DIR__.'/apiTokens/testFormApiTokensUserSettings.php';

// Authentication.
require_once __DIR__.'/authentication/testUsersAuthentication.php';
require_once __DIR__.'/authentication/testUsersAuthenticationHttp.php';
require_once __DIR__.'/authentication/testUsersAuthenticationLdap.php';
require_once __DIR__.'/authentication/testUsersAuthenticationSaml.php';
require_once __DIR__.'/authentication/testUsersPasswordComplexity.php';

// Connectors.
require_once __DIR__.'/connectors/testFormConnectors.php';
require_once __DIR__.'/connectors/testPageConnectors.php';

// Dashboards.
require_once __DIR__.'/dashboards/testDashboardsForm.php';
require_once __DIR__.'/dashboards/testDashboardsHostDashboardPage.php';
require_once __DIR__.'/dashboards/testDashboardsListPage.php';
require_once __DIR__.'/dashboards/testDashboardsPages.php';
require_once __DIR__.'/dashboards/testDashboardsTemplatedDashboardForm.php';
require_once __DIR__.'/dashboards/testDashboardsTemplatedDashboardPage.php';
require_once __DIR__.'/dashboards/testDashboardsViewMode.php';
require_once __DIR__.'/dashboards/testDashboardsWidgetsPage.php';

// Dashboard widgets.
require_once __DIR__.'/dashboardWidgets/testDashboardClockWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardCopyWidgets.php';
require_once __DIR__.'/dashboardWidgets/testDashboardDiscoveryStatusWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardDynamicItemWidgets.php';
require_once __DIR__.'/dashboardWidgets/testDashboardFavoriteGraphsWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardFavoriteMapsWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGaugeWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGeomapWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGeomapWidgetScreenshots.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGraphPrototypeWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGraphWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardGraphWidgetSelectedHosts.php';
require_once __DIR__.'/dashboardWidgets/testDashboardHoneycombWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardHostAvailabilityWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardHostNavigatorWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardItemHistoryWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardItemNavigatorWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardItemValueWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardPieChartWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardProblemsBySeverityWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardProblemsWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardProblemsWidgetDisplay.php';
require_once __DIR__.'/dashboardWidgets/testDashboardSlaReportWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardSystemInformationWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardTopHostsWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardTopTriggersWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardTriggerOverviewWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardURLWidget.php';
require_once __DIR__.'/dashboardWidgets/testDashboardWidgetBroadcastedData.php';
require_once __DIR__.'/dashboardWidgets/testDashboardWidgetCommunication.php';

// Event correlation.
require_once __DIR__.'/eventCorrelation/testFormEventCorrelation.php';
require_once __DIR__.'/eventCorrelation/testPageEventCorrelation.php';

// Filter tabs.
require_once __DIR__.'/filterTabs/testFormFilterHosts.php';
require_once __DIR__.'/filterTabs/testFormFilterLatestData.php';
require_once __DIR__.'/filterTabs/testFormFilterProblems.php';

// Graphs.
require_once __DIR__.'/graphs/testDataDisplayInGraphs.php';
require_once __DIR__.'/graphs/testFormGraph.php';
require_once __DIR__.'/graphs/testFormGraphPrototype.php';
require_once __DIR__.'/graphs/testGraphAxis.php';
require_once __DIR__.'/graphs/testInheritanceGraph.php';
require_once __DIR__.'/graphs/testInheritanceGraphPrototype.php';
require_once __DIR__.'/graphs/testPageGraphPrototypes.php';
require_once __DIR__.'/graphs/testPageGraphPrototypesTemplate.php';
require_once __DIR__.'/graphs/testPageHostGraph.php';
require_once __DIR__.'/graphs/testPageMonitoringHostsGraph.php';

// Host and template groups.
require_once __DIR__.'/hostAndTemplateGroups/testFormHostGroup.php';
require_once __DIR__.'/hostAndTemplateGroups/testFormHostGroupSearchPage.php';
require_once __DIR__.'/hostAndTemplateGroups/testFormTemplateGroup.php';
require_once __DIR__.'/hostAndTemplateGroups/testFormTemplateGroupSearchPage.php';
require_once __DIR__.'/hostAndTemplateGroups/testPageHostGroups.php';
require_once __DIR__.'/hostAndTemplateGroups/testPageTemplateGroups.php';

// Hosts.
require_once __DIR__.'/hosts/testFormHostFromConfiguration.php';
require_once __DIR__.'/hosts/testFormHostFromMonitoring.php';
require_once __DIR__.'/hosts/testFormHostLinkTemplates.php';
require_once __DIR__.'/hosts/testFormHostPrototype.php';
require_once __DIR__.'/hosts/testInheritanceHostPrototype.php';
require_once __DIR__.'/hosts/testPageHostInterfaces.php';
require_once __DIR__.'/hosts/testPageHostPrototypes.php';
require_once __DIR__.'/hosts/testPageHostPrototypesTemplate.php';
require_once __DIR__.'/hosts/testPageHosts.php';
require_once __DIR__.'/hosts/testPageMonitoringHosts.php';

// Icon mapping.
require_once __DIR__.'/iconMapping/testFormAdministrationGeneralIconMapping.php';
require_once __DIR__.'/iconMapping/testPageAdministrationGeneralIconMapping.php';

// Items.
require_once __DIR__.'/items/testFormItem.php';
require_once __DIR__.'/items/testFormItemHttpAgent.php';
require_once __DIR__.'/items/testFormItemPrototype.php';
require_once __DIR__.'/items/testFormTestItem.php';
require_once __DIR__.'/items/testFormTestItemPrototype.php';
require_once __DIR__.'/items/testFormulaCalculatedItem.php';
require_once __DIR__.'/items/testFormulaCalculatedItemPrototype.php';
require_once __DIR__.'/items/testInheritanceItem.php';
require_once __DIR__.'/items/testInheritanceItemPrototype.php';
require_once __DIR__.'/items/testItemTypeSelection.php';
require_once __DIR__.'/items/testPageItemPrototypes.php';
require_once __DIR__.'/items/testPageItemPrototypesTemplate.php';
require_once __DIR__.'/items/testPageItems.php';
require_once __DIR__.'/items/testPageMassUpdateItemPrototypes.php';
require_once __DIR__.'/items/testPageMassUpdateItems.php';

// Latest data.
require_once __DIR__.'/latestData/testPageItemHistory.php';
require_once __DIR__.'/latestData/testPageMonitoringLatestData.php';

// LLD.
require_once __DIR__.'/lld/testFormLowLevelDiscoveryFromHost.php';
require_once __DIR__.'/lld/testFormLowLevelDiscoveryFromTemplate.php';
require_once __DIR__.'/lld/testFormLowLevelDiscoveryOverrides.php';
require_once __DIR__.'/lld/testFormTestLowLevelDiscovery.php';
require_once __DIR__.'/lld/testInheritanceDiscoveryRule.php';
require_once __DIR__.'/lld/testLowLevelDiscoveryDisabledObjects.php';
require_once __DIR__.'/lld/testPageLowLevelDiscovery.php';

// Macros.
require_once __DIR__.'/macros/testFormMacrosAdministrationGeneral.php';
require_once __DIR__.'/macros/testFormMacrosDiscoveredHost.php';
require_once __DIR__.'/macros/testFormMacrosHost.php';
require_once __DIR__.'/macros/testFormMacrosHostPrototype.php';
require_once __DIR__.'/macros/testFormMacrosTemplate.php';

// Maps.
require_once __DIR__.'/maps/testFormMapConstructor.php';
require_once __DIR__.'/maps/testFormMapProperties.php';
require_once __DIR__.'/maps/testPageMaps.php';

// Maintenance.
require_once __DIR__.'/maintenance/testFormMaintenance.php';
require_once __DIR__.'/maintenance/testPageMaintenance.php';

// Media types.
require_once __DIR__.'/mediaTypes/testFormAdministrationMediaTypes.php';
require_once __DIR__.'/mediaTypes/testFormAdministrationMediaTypeMessageTemplates.php';
require_once __DIR__.'/mediaTypes/testFormAdministrationMediaTypeWebhook.php';
require_once __DIR__.'/mediaTypes/testPageAdministrationMediaTypes.php';

// Multiselects.
require_once __DIR__.'/multiselects/testMultiselects.php';
require_once __DIR__.'/multiselects/testMultiselectsErrorsHostsTemplates.php';
require_once __DIR__.'/multiselects/testMultiselectsLatestData.php';
require_once __DIR__.'/multiselects/testMultiselectsProblems.php';
require_once __DIR__.'/multiselects/testMultiselectsWithoutData.php';

// Network discovery.
require_once __DIR__.'/networkDiscovery/testFormNetworkDiscovery.php';
require_once __DIR__.'/networkDiscovery/testPageNetworkDiscovery.php';

// Permissions.
require_once __DIR__.'/permissions/testPermissionsWithoutCSRF.php';
require_once __DIR__.'/permissions/testTagBasedPermissions.php';
require_once __DIR__.'/permissions/testUrlUserPermissions.php';

// Preprocessing.
require_once __DIR__.'/preprocessing/testFormPreprocessingCloneHost.php';
require_once __DIR__.'/preprocessing/testFormPreprocessingCloneTemplate.php';
require_once __DIR__.'/preprocessing/testFormPreprocessingItem.php';
require_once __DIR__.'/preprocessing/testFormPreprocessingItemPrototype.php';
require_once __DIR__.'/preprocessing/testFormPreprocessingLowLevelDiscovery.php';
require_once __DIR__.'/preprocessing/testFormPreprocessingTest.php';

// Problems.
require_once __DIR__.'/problems/testCauseAndSymptomEvents.php';
require_once __DIR__.'/problems/testFormUpdateProblem.php';
require_once __DIR__.'/problems/testPageProblems.php';

// Proxies.
require_once __DIR__.'/proxies/testFormAdministrationProxies.php';
require_once __DIR__.'/proxies/testPageAdministrationProxies.php';

// Proxy Groups.
require_once __DIR__.'/proxyGroups/testFormAdministrationProxyGroups.php';
require_once __DIR__.'/proxyGroups/testPageAdministrationProxyGroups.php';

// Queue.
/*
require_once __DIR__.'/queue/testPageQueueDetails.php';
require_once __DIR__.'/queue/testPageQueueOverview.php';
require_once __DIR__.'/queue/testPageQueueOverviewByProxy.php';
*/

// Regexp.
require_once __DIR__.'/regexp/testFormAdministrationGeneralRegexp.php';
require_once __DIR__.'/regexp/testPageAdministrationGeneralRegexp.php';

// Reports.
require_once __DIR__.'/reports/testFormScheduledReport.php';
require_once __DIR__.'/reports/testPageAvailabilityReport.php';
require_once __DIR__.'/reports/testPageReportsActionLog.php';
require_once __DIR__.'/reports/testPageReportsAudit.php';
require_once __DIR__.'/reports/testPageReportsNotifications.php';
require_once __DIR__.'/reports/testPageReportsSystemInformation.php';
require_once __DIR__.'/reports/testPageReportsTopTriggers.php';
require_once __DIR__.'/reports/testPageScheduledReport.php';
require_once __DIR__.'/reports/testScheduledReportPermissions.php';

// Roles.
require_once __DIR__.'/roles/testFormUserRoles.php';
require_once __DIR__.'/roles/testPageUserRoles.php';
require_once __DIR__.'/roles/testUserRolesPermissions.php';

// Scripts.
require_once __DIR__.'/scripts/testFormAlertsScripts.php';
require_once __DIR__.'/scripts/testManualActionScripts.php';
require_once __DIR__.'/scripts/testPageAlertsScripts.php';

// Services.
require_once __DIR__.'/services/testFormServicesServices.php';
require_once __DIR__.'/services/testPageServicesServices.php';
require_once __DIR__.'/services/testPageServicesServicesMassUpdate.php';

// SLA.
require_once __DIR__.'/sla/testFormServicesSla.php';
require_once __DIR__.'/sla/testPageServicesSla.php';
require_once __DIR__.'/sla/testPageServicesSlaReport.php';

// Tags.
require_once __DIR__.'/tags/testFormTagsConnectors.php';
require_once __DIR__.'/tags/testFormTagsDiscoveredHost.php';
require_once __DIR__.'/tags/testFormTagsHost.php';
require_once __DIR__.'/tags/testFormTagsHostPrototype.php';
require_once __DIR__.'/tags/testFormTagsServices.php';
require_once __DIR__.'/tags/testFormTagsServicesProblemTags.php';
require_once __DIR__.'/tags/testFormTagsItem.php';
require_once __DIR__.'/tags/testFormTagsItemPrototype.php';
require_once __DIR__.'/tags/testFormTagsTemplate.php';
require_once __DIR__.'/tags/testFormTagsTrigger.php';
require_once __DIR__.'/tags/testFormTagsTriggerPrototype.php';
require_once __DIR__.'/tags/testFormTagsWeb.php';

// Templates.
require_once __DIR__.'/templates/testFormTemplate.php';
require_once __DIR__.'/templates/testPageTemplates.php';
require_once __DIR__.'/templates/testTemplateInheritance.php';

// Timeouts.
require_once __DIR__.'/timeouts/testTimeoutsHosts.php';
require_once __DIR__.'/timeouts/testTimeoutsLinkedTemplates.php';
require_once __DIR__.'/timeouts/testTimeoutsTemplates.php';

// Trigger dependencies.
require_once __DIR__.'/triggers/testHostTriggerDependencies.php';
require_once __DIR__.'/triggers/testTemplateTriggerDependencies.php';

// Triggers.
require_once __DIR__.'/triggers/testFormTrigger.php';
require_once __DIR__.'/triggers/testFormTriggerPrototype.php';
require_once __DIR__.'/triggers/testInheritanceTrigger.php';
require_once __DIR__.'/triggers/testInheritanceTriggerPrototype.php';
require_once __DIR__.'/triggers/testPageTriggerDescription.php';
require_once __DIR__.'/triggers/testPageTriggerPrototypes.php';
require_once __DIR__.'/triggers/testPageTriggerPrototypesTemplate.php';
require_once __DIR__.'/triggers/testPageTriggers.php';
require_once __DIR__.'/triggers/testPageTriggerUrl.php';
require_once __DIR__.'/triggers/testTriggerExpressions.php';

// Users.
require_once __DIR__.'/users/testFormUser.php';
require_once __DIR__.'/users/testFormUserGroups.php';
require_once __DIR__.'/users/testFormUserMedia.php';
require_once __DIR__.'/users/testFormUserLdapMediaJit.php';
require_once __DIR__.'/users/testFormUserPermissions.php';
require_once __DIR__.'/users/testFormUserProfile.php';
require_once __DIR__.'/users/testAlarmNotification.php';
require_once __DIR__.'/users/testPageUserGroups.php';
require_once __DIR__.'/users/testPageUsers.php';

// Value mapping.
require_once __DIR__.'/valueMapping/testFormValueMappingsHost.php';
require_once __DIR__.'/valueMapping/testFormValueMappingsTemplate.php';

// Web scenarios.
require_once __DIR__.'/webScenarios/testFormWebScenario.php';
require_once __DIR__.'/webScenarios/testFormWebScenarioStep.php';
require_once __DIR__.'/webScenarios/testPageMonitoringWeb.php';
require_once __DIR__.'/webScenarios/testInheritanceWeb.php';
require_once __DIR__.'/webScenarios/testPageMonitoringWebDetails.php';

require_once __DIR__.'/testDocumentationLinks.php';
require_once __DIR__.'/testExecuteNow.php';
require_once __DIR__.'/testExpandExpressionMacros.php';
require_once __DIR__.'/testFormLogin.php';
require_once __DIR__.'/testFormSetup.php';
require_once __DIR__.'/testFormTabIndicators.php';
require_once __DIR__.'/testGeneric.php';
require_once __DIR__.'/testLanguage.php';
require_once __DIR__.'/testPageBrowserWarning.php';
require_once __DIR__.'/testPageInventory.php';
require_once __DIR__.'/testPageSearch.php';
require_once __DIR__.'/testPageStatusOfZabbix.php';
require_once __DIR__.'/testPagesWithoutData.php';
require_once __DIR__.'/testPSKEncryption.php';
require_once __DIR__.'/testSidebarMenu.php';
require_once __DIR__.'/testTimezone.php';
require_once __DIR__.'/testUrlParameters.php';
require_once __DIR__.'/testZBX6663.php';

use PHPUnit\Framework\TestSuite;

class SeleniumTests {
	public static function suite() {
		$suite = new TestSuite('selenium');

		// Actions.
		$suite->addTestSuite('testFormAction');
		$suite->addTestSuite('testPageActions');

		// Administration.
		$suite->addTestSuite('testFormAdministrationAuditLog');
		$suite->addTestSuite('testFormAdministrationGeneralAutoregistration');
		$suite->addTestSuite('testFormAdministrationGeneralGeomaps');
		$suite->addTestSuite('testFormAdministrationGeneralGUI');
//		$suite->addTestSuite('testFormAdministrationGeneralImages');
		$suite->addTestSuite('testFormAdministrationGeneralOtherParams');
		$suite->addTestSuite('testFormAdministrationGeneralTimeouts');
		$suite->addTestSuite('testFormAdministrationGeneralTrigDisplOptions');
		$suite->addTestSuite('testFormAdministrationHousekeeping');
		$suite->addTestSuite('testPageAdministrationGeneralImages');
		$suite->addTestSuite('testPageAdministrationGeneralModules');

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

		// Connectors.
		$suite->addTestSuite('testFormConnectors');
		$suite->addTestSuite('testPageConnectors');

		// Dashboards.
		$suite->addTestSuite('testDashboardsForm');
		$suite->addTestSuite('testDashboardsHostDashboardPage');
		$suite->addTestSuite('testDashboardsListPage');
		$suite->addTestSuite('testDashboardsPages');
		$suite->addTestSuite('testDashboardsTemplatedDashboardForm');
		$suite->addTestSuite('testDashboardsTemplatedDashboardPage');
		$suite->addTestSuite('testDashboardsViewMode');
		$suite->addTestSuite('testDashboardsWidgetsPage');

		// Dashboard widgets.
		$suite->addTestSuite('testDashboardClockWidget');
		$suite->addTestSuite('testDashboardCopyWidgets');
		$suite->addTestSuite('testDashboardDiscoveryStatusWidget');
		$suite->addTestSuite('testDashboardDynamicItemWidgets');
		$suite->addTestSuite('testDashboardFavoriteGraphsWidget');
		$suite->addTestSuite('testDashboardFavoriteMapsWidget');
		$suite->addTestSuite('testDashboardGaugeWidget');
		$suite->addTestSuite('testDashboardGeomapWidget');
		$suite->addTestSuite('testDashboardGeomapWidgetScreenshots');
		$suite->addTestSuite('testDashboardGraphPrototypeWidget');
		$suite->addTestSuite('testDashboardGraphWidget');
		$suite->addTestSuite('testDashboardGraphWidgetSelectedHosts');
		$suite->addTestSuite('testDashboardHoneycombWidget');
		$suite->addTestSuite('testDashboardHostAvailabilityWidget');
		$suite->addTestSuite('testDashboardHostNavigatorWidget');
		$suite->addTestSuite('testDashboardItemHistoryWidget');
		$suite->addTestSuite('testDashboardItemNavigatorWidget');
		$suite->addTestSuite('testDashboardItemValueWidget');
		$suite->addTestSuite('testDashboardPieChartWidget');
		$suite->addTestSuite('testDashboardProblemsBySeverityWidget');
		$suite->addTestSuite('testDashboardProblemsWidget');
		$suite->addTestSuite('testDashboardProblemsWidgetDisplay');
		$suite->addTestSuite('testDashboardSlaReportWidget');
		$suite->addTestSuite('testDashboardSystemInformationWidget');
		$suite->addTestSuite('testDashboardTopHostsWidget');
		$suite->addTestSuite('testDashboardTopTriggersWidget');
		$suite->addTestSuite('testDashboardTriggerOverviewWidget');
		$suite->addTestSuite('testDashboardURLWidget');
		$suite->addTestSuite('testDashboardWidgetBroadcastedData');
		$suite->addTestSuite('testDashboardWidgetCommunication');

		// Event correlation.
		$suite->addTestSuite('testFormEventCorrelation');
		$suite->addTestSuite('testPageEventCorrelation');

		// Filter tabs.
		$suite->addTestSuite('testFormFilterHosts');
		$suite->addTestSuite('testFormFilterLatestData');
		$suite->addTestSuite('testFormFilterProblems');

		// Graphs.
		$suite->addTestSuite('testDataDisplayInGraphs');
		$suite->addTestSuite('testFormGraph');
		$suite->addTestSuite('testFormGraphPrototype');
		$suite->addTestSuite('testGraphAxis');
		$suite->addTestSuite('testInheritanceGraph');
		$suite->addTestSuite('testInheritanceGraphPrototype');
		$suite->addTestSuite('testPageGraphPrototypes');
		$suite->addTestSuite('testPageGraphPrototypesTemplate');
		$suite->addTestSuite('testPageHostGraph');
		$suite->addTestSuite('testPageMonitoringHostsGraph');

		// Groups.
		$suite->addTestSuite('testFormHostGroup');
		$suite->addTestSuite('testFormHostGroupSearchPage');
		$suite->addTestSuite('testFormTemplateGroup');
		$suite->addTestSuite('testFormTemplateGroupSearchPage');
		$suite->addTestSuite('testPageHostGroups');
		$suite->addTestSuite('testPageTemplateGroups');

		// Hosts.
		$suite->addTestSuite('testFormHostFromConfiguration');
		$suite->addTestSuite('testFormHostFromMonitoring');
		$suite->addTestSuite('testFormHostLinkTemplates');
		$suite->addTestSuite('testFormHostPrototype');
		$suite->addTestSuite('testInheritanceHostPrototype');
		$suite->addTestSuite('testPageHostInterfaces');
		$suite->addTestSuite('testPageHostPrototypes');
		$suite->addTestSuite('testPageHostPrototypesTemplate');
		$suite->addTestSuite('testPageHosts');
		$suite->addTestSuite('testPageMonitoringHosts');

		// Icon mapping.
		$suite->addTestSuite('testFormAdministrationGeneralIconMapping');
		$suite->addTestSuite('testPageAdministrationGeneralIconMapping');

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
		$suite->addTestSuite('testPageItemPrototypesTemplate');
		$suite->addTestSuite('testPageItems');
		$suite->addTestSuite('testPageMassUpdateItemPrototypes');
		$suite->addTestSuite('testPageMassUpdateItems');

		// Latest data.
		$suite->addTestSuite('testPageItemHistory');
		$suite->addTestSuite('testPageMonitoringLatestData');

		// LLD.
		$suite->addTestSuite('testFormLowLevelDiscoveryFromHost');
		$suite->addTestSuite('testFormLowLevelDiscoveryFromTemplate');
		$suite->addTestSuite('testFormLowLevelDiscoveryOverrides');
		$suite->addTestSuite('testFormTestLowLevelDiscovery');
		$suite->addTestSuite('testInheritanceDiscoveryRule');
		$suite->addTestSuite('testLowLevelDiscoveryDisabledObjects');
		$suite->addTestSuite('testPageLowLevelDiscovery');

		// Macros.
		$suite->addTestSuite('testFormMacrosAdministrationGeneral');
		$suite->addTestSuite('testFormMacrosDiscoveredHost');
		$suite->addTestSuite('testFormMacrosHost');
		$suite->addTestSuite('testFormMacrosHostPrototype');
		$suite->addTestSuite('testFormMacrosTemplate');

		// Maintenance.
		$suite->addTestSuite('testFormMaintenance');
		$suite->addTestSuite('testPageMaintenance');

		// Maps.
		$suite->addTestSuite('testFormMapConstructor');
		$suite->addTestSuite('testFormMapProperties');
		$suite->addTestSuite('testPageMaps');

		// Media types.
		$suite->addTestSuite('testFormAdministrationMediaTypeMessageTemplates');
		$suite->addTestSuite('testFormAdministrationMediaTypes');
		$suite->addTestSuite('testFormAdministrationMediaTypeWebhook');
		$suite->addTestSuite('testPageAdministrationMediaTypes');

		// Multiselects.
		$suite->addTestSuite('testMultiselects');
		$suite->addTestSuite('testMultiselectsErrorsHostsTemplates');
		$suite->addTestSuite('testMultiselectsLatestData');
		$suite->addTestSuite('testMultiselectsProblems');
		$suite->addTestSuite('testMultiselectsWithoutData');

		// Network discovery.
		$suite->addTestSuite('testFormNetworkDiscovery');
		$suite->addTestSuite('testPageNetworkDiscovery');

		// Permissions.
		$suite->addTestSuite('testPermissionsWithoutCSRF');
		$suite->addTestSuite('testTagBasedPermissions');
		$suite->addTestSuite('testUrlUserPermissions');

		// Preprocessing.
		$suite->addTestSuite('testFormPreprocessingCloneHost');
		$suite->addTestSuite('testFormPreprocessingCloneTemplate');
		$suite->addTestSuite('testFormPreprocessingItem');
		$suite->addTestSuite('testFormPreprocessingItemPrototype');
		$suite->addTestSuite('testFormPreprocessingLowLevelDiscovery');
		$suite->addTestSuite('testFormPreprocessingTest');

		// Problems.
		$suite->addTestSuite('testCauseAndSymptomEvents');
		$suite->addTestSuite('testPageProblems');
		$suite->addTestSuite('testFormUpdateProblem');

		// Proxies.
		$suite->addTestSuite('testFormAdministrationProxies');
		$suite->addTestSuite('testPageAdministrationProxies');

		// Proxy groups.
		$suite->addTestSuite('testFormAdministrationProxyGroups');
		$suite->addTestSuite('testPageAdministrationProxyGroups');

		// Queue.
		/*
		$suite->addTestSuite('testPageQueueDetails');
		$suite->addTestSuite('testPageQueueOverview');
		$suite->addTestSuite('testPageQueueOverviewByProxy');
		*/

		// Regexp.
		$suite->addTestSuite('testFormAdministrationGeneralRegexp');
		$suite->addTestSuite('testPageAdministrationGeneralRegexp');

		// Reports.
		$suite->addTestSuite('testFormScheduledReport');
		$suite->addTestSuite('testPageAvailabilityReport');
		$suite->addTestSuite('testPageReportsActionLog');
		$suite->addTestSuite('testPageReportsAudit');
		$suite->addTestSuite('testPageReportsNotifications');
		$suite->addTestSuite('testPageReportsSystemInformation');
		$suite->addTestSuite('testPageReportsTopTriggers');
		$suite->addTestSuite('testPageScheduledReport');
		$suite->addTestSuite('testScheduledReportPermissions');

		// Roles.
		$suite->addTestSuite('testFormUserRoles');
		$suite->addTestSuite('testPageUserRoles');
		$suite->addTestSuite('testUserRolesPermissions');

		// Scripts.
		$suite->addTestSuite('testFormAlertsScripts');
		$suite->addTestSuite('testManualActionScripts');
		$suite->addTestSuite('testPageAlertsScripts');

		// Services.
		$suite->addTestSuite('testFormServicesServices');
		$suite->addTestSuite('testPageServicesServices');
		$suite->addTestSuite('testPageServicesServicesMassUpdate');

		// SLA.
		$suite->addTestSuite('testFormServicesSla');
		$suite->addTestSuite('testPageServicesSla');
		$suite->addTestSuite('testPageServicesSlaReport');

		// Tags.
		$suite->addTestSuite('testFormTagsConnectors');
		$suite->addTestSuite('testFormTagsDiscoveredHost');
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
		$suite->addTestSuite('testTemplateInheritance');

		// Timeouts.
		$suite->addTestSuite('testTimeoutsHosts');
		$suite->addTestSuite('testTimeoutsLinkedTemplates');
		$suite->addTestSuite('testTimeoutsTemplates');

		// Trigger dependencies.
		$suite->addTestSuite('testHostTriggerDependencies');
		$suite->addTestSuite('testTemplateTriggerDependencies');

		// Triggers.
		$suite->addTestSuite('testFormTrigger');
		$suite->addTestSuite('testFormTriggerPrototype');
		$suite->addTestSuite('testInheritanceTrigger');
		$suite->addTestSuite('testInheritanceTriggerPrototype');
		$suite->addTestSuite('testPageTriggerDescription');
		$suite->addTestSuite('testPageTriggerPrototypes');
		$suite->addTestSuite('testPageTriggerPrototypesTemplate');
		$suite->addTestSuite('testPageTriggers');
		$suite->addTestSuite('testPageTriggerUrl');
		$suite->addTestSuite('testTriggerExpressions');

		// Users.
		$suite->addTestSuite('testFormUser');
		$suite->addTestSuite('testFormUserGroups');
		$suite->addTestSuite('testFormUserMedia');
		$suite->addTestSuite('testFormUserLdapMediaJit');
		$suite->addTestSuite('testFormUserPermissions');
		$suite->addTestSuite('testAlarmNotification');
		$suite->addTestSuite('testFormUserProfile');
		$suite->addTestSuite('testPageUserGroups');
		$suite->addTestSuite('testPageUsers');

		// Value mapping.
		$suite->addTestSuite('testFormValueMappingsHost');
		$suite->addTestSuite('testFormValueMappingsTemplate');

		// Web scenarios.
		$suite->addTestSuite('testFormWebScenario');
		$suite->addTestSuite('testFormWebScenarioStep');
		$suite->addTestSuite('testInheritanceWeb');
		$suite->addTestSuite('testPageMonitoringWeb');
		$suite->addTestSuite('testPageMonitoringWebDetails');

		$suite->addTestSuite('testDocumentationLinks');
		$suite->addTestSuite('testExecuteNow');
		$suite->addTestSuite('testExpandExpressionMacros');
		$suite->addTestSuite('testFormLogin');
		$suite->addTestSuite('testFormSetup');
		$suite->addTestSuite('testFormTabIndicators');
		$suite->addTestSuite('testGeneric');
		$suite->addTestSuite('testLanguage');
		$suite->addTestSuite('testPageBrowserWarning');
		$suite->addTestSuite('testPageInventory');
		$suite->addTestSuite('testPageSearch');
		$suite->addTestSuite('testPageStatusOfZabbix');
		$suite->addTestSuite('testPagesWithoutData');
		$suite->addTestSuite('testPSKEncryption');
		$suite->addTestSuite('testSidebarMenu');
		$suite->addTestSuite('testTimezone');
		$suite->addTestSuite('testUrlParameters');
		$suite->addTestSuite('testZBX6663');

		return $suite;
	}
}
