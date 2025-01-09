<?php declare(strict_types = 0);
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


class CRouter {
	/**
	 * Layout used for view rendering.
	 */
	private ?string $layout = null;

	/**
	 * Controller class for action handling.
	 */
	private ?string $controller = null;

	/**
	 * View used to generate HTML, CSV, JSON and other content.
	 */
	private ?string $view = null;

	/**
	 * Unique action (request) identifier.
	 */
	private ?string $action = null;

	/**
	 * Mapping between action and corresponding controller, layout and view.
	 */
	private array $routes = [
		// action									controller												layout					view
		'acknowledge.edit'							=> ['CControllerAcknowledgeEdit',						'layout.json',			'acknowledge.edit'],
		'action.create'								=> ['CControllerActionCreate',							'layout.json',			null],
		'action.delete'								=> ['CControllerActionDelete',							'layout.json',			null],
		'action.disable'							=> ['CControllerActionDisable',							'layout.json',			null],
		'action.edit'								=> ['CControllerActionEdit',							'layout.json',			'action.edit'],
		'action.enable'								=> ['CControllerActionEnable',							'layout.json',			null],
		'action.list'								=> ['CControllerActionList',							'layout.htmlpage',		'action.list'],
		'action.operation.check'					=> ['CControllerActionOperationCheck',					'layout.json',			null],
		'action.operation.condition.check'			=> ['CControllerActionOperationConditionCheck',			'layout.json',			null],
		'action.update'								=> ['CControllerActionUpdate',							'layout.json',			null],
		'actionlog.csv'								=> ['CControllerActionLogList',							'layout.csv',			'reports.actionlog.list.csv'],
		'actionlog.list'							=> ['CControllerActionLogList',							'layout.htmlpage',		'reports.actionlog.list'],
		'audit.settings.edit'						=> ['CControllerAuditSettingsEdit',						'layout.htmlpage',		'administration.audit.settings.edit'],
		'audit.settings.update'						=> ['CControllerAuditSettingsUpdate',					null,					null],
		'auditlog.list'								=> ['CControllerAuditLogList',							'layout.htmlpage',		'reports.auditlog.list'],
		'authentication.edit'						=> ['CControllerAuthenticationEdit',					'layout.htmlpage',		'administration.authentication.edit'],
		'authentication.update'						=> ['CControllerAuthenticationUpdate',					null,					null],
		'autoreg.edit'								=> ['CControllerAutoregEdit',							'layout.htmlpage',		'administration.autoreg.edit'],
		'autoreg.update'							=> ['CControllerAutoregUpdate',							null,					null],
		'availabilityreport.list'					=> ['CControllerAvailabilityReportList',				'layout.htmlpage',		'reports.availabilityreport.list'],
		'availabilityreport.trigger'				=> ['CControllerAvailabilityReportTrigger',				'layout.htmlpage',		'reports.availabilityreport.trigger'],
		'charts.view'								=> ['CControllerChartsView',							'layout.htmlpage',		'monitoring.charts.view'],
		'charts.view.json'							=> ['CControllerChartsViewJson',						'layout.json',			'monitoring.charts.view.json'],
		'connector.create'							=> ['CControllerConnectorCreate',						'layout.json',			null],
		'connector.delete'							=> ['CControllerConnectorDelete',						'layout.json',			null],
		'connector.disable'							=> ['CControllerConnectorDisable',						'layout.json',			null],
		'connector.edit'							=> ['CControllerConnectorEdit',							'layout.json',			'connector.edit'],
		'connector.enable'							=> ['CControllerConnectorEnable',						'layout.json',			null],
		'connector.list'							=> ['CControllerConnectorList',							'layout.htmlpage',		'connector.list'],
		'connector.update'							=> ['CControllerConnectorUpdate',						'layout.json',			null],
		'copy.create'								=> ['CControllerCopyCreate',							'layout.json',			null],
		'copy.edit'									=> ['CControllerCopyEdit',								'layout.json',			'copy.edit'],
		'correlation.condition.check'				=> ['CControllerCorrelationConditionCheck',				'layout.json',			null],
		'correlation.condition.edit'				=> ['CControllerCorrelationConditionEdit',				'layout.json',			'correlation.condition.edit'],
		'correlation.create'						=> ['CControllerCorrelationCreate',						'layout.json',			null],
		'correlation.delete'						=> ['CControllerCorrelationDelete',						'layout.json',			null],
		'correlation.disable'						=> ['CControllerCorrelationDisable',					'layout.json',			null],
		'correlation.edit'							=> ['CControllerCorrelationEdit',						'layout.json',			'correlation.edit'],
		'correlation.enable'						=> ['CControllerCorrelationEnable',						'layout.json',			null],
		'correlation.list'							=> ['CControllerCorrelationList',						'layout.htmlpage',		'correlation.list'],
		'correlation.update'						=> ['CControllerCorrelationUpdate',						'layout.json',			null],
		'dashboard.config.hash'						=> ['CControllerDashboardConfigHash',					'layout.json',			null],
		'dashboard.delete'							=> ['CControllerDashboardDelete',						null,					null],
		'dashboard.list'							=> ['CControllerDashboardList',							'layout.htmlpage',		'monitoring.dashboard.list'],
		'dashboard.page.properties.check'			=> ['CControllerDashboardPagePropertiesCheck',			'layout.json',			null],
		'dashboard.page.properties.edit'			=> ['CControllerDashboardPagePropertiesEdit',			'layout.json',			'dashboard.page.properties.edit'],
		'dashboard.print'							=> ['CControllerDashboardPrint',						'layout.print',		    'monitoring.dashboard.print'],
		'dashboard.properties.check'				=> ['CControllerDashboardPropertiesCheck',				'layout.json',			null],
		'dashboard.properties.edit'					=> ['CControllerDashboardPropertiesEdit',				'layout.json',			'dashboard.properties.edit'],
		'dashboard.share.update'					=> ['CControllerDashboardShareUpdate',					'layout.json',			null],
		'dashboard.update'							=> ['CControllerDashboardUpdate',						'layout.json',			null],
		'dashboard.view'							=> ['CControllerDashboardView',							'layout.htmlpage',		'monitoring.dashboard.view'],
		'dashboard.widget.check'					=> ['CControllerDashboardWidgetCheck',					'layout.json',			null],
		'dashboard.widget.rfrate'					=> ['CControllerDashboardWidgetRfRate',					'layout.json',			null],
		'dashboard.widgets.validate'				=> ['CControllerDashboardWidgetsValidate',				'layout.json',			null],
		'discovery.check.check'						=> ['CControllerDiscoveryCheckCheck',					'layout.json',			null],
		'discovery.check.edit'						=> ['CControllerDiscoveryCheckEdit',					'layout.json',			'discovery.check.edit'],
		'discovery.create'							=> ['CControllerDiscoveryCreate',						'layout.json',			null],
		'discovery.delete'							=> ['CControllerDiscoveryDelete',						'layout.json',			null],
		'discovery.disable'							=> ['CControllerDiscoveryDisable',						'layout.json',			null],
		'discovery.edit'							=> ['CControllerDiscoveryEdit',							'layout.json',			'configuration.discovery.edit'],
		'discovery.enable'							=> ['CControllerDiscoveryEnable',						'layout.json',			null],
		'discovery.list'							=> ['CControllerDiscoveryList',							'layout.htmlpage',		'configuration.discovery.list'],
		'discovery.update'							=> ['CControllerDiscoveryUpdate',						'layout.json',			null],
		'discovery.view'							=> ['CControllerDiscoveryView',							'layout.htmlpage',		'monitoring.discovery.view'],
		'export.hosts'								=> ['CControllerExport',								'layout.export',		null],
		'export.mediatypes'							=> ['CControllerExport',								'layout.export',		null],
		'export.sysmaps'							=> ['CControllerExport',								'layout.export',		null],
		'export.templates'							=> ['CControllerExport',								'layout.export',		null],
		'favorite.create'							=> ['CControllerFavoriteCreate',						'layout.javascript',	null],
		'favorite.delete'							=> ['CControllerFavoriteDelete',						'layout.javascript',	null],
		'geomaps.edit'								=> ['CControllerGeomapsEdit',							'layout.htmlpage',		'administration.geomaps.edit'],
		'geomaps.update'							=> ['CControllerGeomapsUpdate',							null,					null],
		'gui.edit'									=> ['CControllerGuiEdit',								'layout.htmlpage',		'administration.gui.edit'],
		'gui.update'								=> ['CControllerGuiUpdate',								null,					null],
		'hintbox.actionlist'						=> ['CControllerHintboxActionlist',						'layout.json',			'hintbox.actionlist'],
		'hintbox.eventlist'							=> ['CControllerHintboxEventlist',						'layout.json',			'hintbox.eventlist'],
		'host.create'								=> ['CControllerHostCreate',							'layout.json',			null],
		'host.dashboard.view'						=> ['CControllerHostDashboardView',						'layout.htmlpage',		'monitoring.host.dashboard.view'],
		'host.disable'								=> ['CControllerHostDisable',							'layout.json',			null],
		'host.edit'									=> ['CControllerHostEdit',								'layout.json',			'host.edit'],
		'host.enable'								=> ['CControllerHostEnable',							'layout.json',			null],
		'host.list'									=> ['CControllerHostList',								'layout.htmlpage',		'configuration.host.list'],
		'host.massdelete'							=> ['CControllerHostMassDelete',						'layout.json',			null],
		'host.update'								=> ['CControllerHostUpdate',							'layout.json',			null],
		'host.view'									=> ['CControllerHostView',								'layout.htmlpage',		'monitoring.host.view'],
		'host.view.refresh'							=> ['CControllerHostViewRefresh',						'layout.json',			'monitoring.host.view.refresh'],
		'hostgroup.create'							=> ['CControllerHostGroupCreate',						'layout.json',			null],
		'hostgroup.delete'							=> ['CControllerHostGroupDelete',						'layout.json',			null],
		'hostgroup.disable'							=> ['CControllerHostGroupDisable',						'layout.json',			null],
		'hostgroup.edit'							=> ['CControllerHostGroupEdit',							'layout.json',			'hostgroup.edit'],
		'hostgroup.enable'							=> ['CControllerHostGroupEnable',						'layout.json',			null],
		'hostgroup.list'							=> ['CControllerHostGroupList',							'layout.htmlpage',		'configuration.hostgroup.list'],
		'hostgroup.update'							=> ['CControllerHostGroupUpdate',						'layout.json',			null],
		'hostmacros.list'							=> ['CControllerHostMacrosList',						'layout.json',			'hostmacros.list'],
		'housekeeping.edit'							=> ['CControllerHousekeepingEdit',						'layout.htmlpage',		'administration.housekeeping.edit'],
		'housekeeping.update'						=> ['CControllerHousekeepingUpdate',					null,					null],
		'iconmap.create'							=> ['CControllerIconMapCreate',							null,					null],
		'iconmap.delete'							=> ['CControllerIconMapDelete',							null,					null],
		'iconmap.edit'								=> ['CControllerIconMapEdit',							'layout.htmlpage',		'administration.iconmap.edit'],
		'iconmap.list'								=> ['CControllerIconMapList',							'layout.htmlpage',		'administration.iconmap.list'],
		'iconmap.update'							=> ['CControllerIconMapUpdate',							null,					null],
		'image.create'								=> ['CControllerImageCreate',							null,					null],
		'image.delete'								=> ['CControllerImageDelete',							null,					null],
		'image.edit'								=> ['CControllerImageEdit',								'layout.htmlpage',		'administration.image.edit'],
		'image.list'								=> ['CControllerImageList',								'layout.htmlpage',		'administration.image.list'],
		'image.update'								=> ['CControllerImageUpdate',							null,					null],
		'item.clear'								=> ['CControllerItemClear',								'layout.json',			null],
		'item.create'								=> ['CControllerItemCreate',							'layout.json',			null],
		'item.delete'								=> ['CControllerItemDelete',							'layout.json',			null],
		'item.disable'								=> ['CControllerItemDisable',							'layout.json',			null],
		'item.edit'									=> ['CControllerItemEdit',								'layout.json',			'item.edit'],
		'item.enable'								=> ['CControllerItemEnable',							'layout.json',			null],
		'item.execute'								=> ['CControllerItemExecuteNow',						'layout.json',			null],
		'item.list'									=> ['CControllerItemList',								'layout.htmlpage',		'item.list'],
		'item.massupdate'							=> ['CControllerItemMassupdate',						'layout.json',			'item.massupdate'],
		'item.update'								=> ['CControllerItemUpdate',							'layout.json',			null],
		'item.prototype.create'						=> ['CControllerItemPrototypeCreate',					'layout.json',			null],
		'item.prototype.delete'						=> ['CControllerItemPrototypeDelete',					'layout.json',			null],
		'item.prototype.disable'					=> ['CControllerItemPrototypeDisable',					'layout.json',			null],
		'item.prototype.edit'						=> ['CControllerItemPrototypeEdit',						'layout.json',			'item.prototype.edit'],
		'item.prototype.enable'						=> ['CControllerItemPrototypeEnable',					'layout.json',			null],
		'item.prototype.list'						=> ['CControllerItemPrototypeList',						'layout.htmlpage',		'item.prototype.list'],
		'item.prototype.massupdate'					=> ['CControllerItemMassupdate',						'layout.json',			'item.massupdate'],
		'item.prototype.update'						=> ['CControllerItemPrototypeUpdate',					'layout.json',			null],
		'item.tags.list'							=> ['CControllerItemTagsList',							'layout.json',			'item.tags.list'],
		'latest.view'								=> ['CControllerLatestView',							'layout.htmlpage',		'monitoring.latest.view'],
		'latest.view.refresh'						=> ['CControllerLatestViewRefresh',						'layout.json',			'monitoring.latest.view.refresh'],
		'macros.edit'								=> ['CControllerMacrosEdit',							'layout.htmlpage',		'administration.macros.edit'],
		'macros.update'								=> ['CControllerMacrosUpdate',							null,					null],
		'maintenance.create'						=> ['CControllerMaintenanceCreate',						'layout.json',			null],
		'maintenance.delete'						=> ['CControllerMaintenanceDelete',						'layout.json',			null],
		'maintenance.edit'							=> ['CControllerMaintenanceEdit',						'layout.json',			'maintenance.edit'],
		'maintenance.list'							=> ['CControllerMaintenanceList',						'layout.htmlpage',		'maintenance.list'],
		'maintenance.timeperiod.edit'				=> ['CControllerMaintenanceTimePeriodEdit',				'layout.json',			'maintenance.timeperiod.edit'],
		'maintenance.timeperiod.check'				=> ['CControllerMaintenanceTimePeriodCheck',			'layout.json',			null],
		'maintenance.update'						=> ['CControllerMaintenanceUpdate',						'layout.json',			null],
		'map.view'									=> ['CControllerMapView',								'layout.htmlpage',		'monitoring.map.view'],
		'mediatype.create'							=> ['CControllerMediatypeCreate',						'layout.json',			null],
		'mediatype.delete'							=> ['CControllerMediatypeDelete',						'layout.json',			null],
		'mediatype.disable'							=> ['CControllerMediatypeDisable',						'layout.json',			null],
		'mediatype.edit'							=> ['CControllerMediatypeEdit',							'layout.json',			'mediatype.edit'],
		'mediatype.enable'							=> ['CControllerMediatypeEnable',						'layout.json',			null],
		'mediatype.list'							=> ['CControllerMediatypeList',							'layout.htmlpage',		'mediatype.list'],
		'mediatype.message.check'					=> ['CControllerMediatypeMessageCheck',					'layout.json',			null],
		'mediatype.message.edit'					=> ['CControllerMediatypeMessageEdit',					'layout.json',			'mediatype.message.edit'],
		'mediatype.test.edit'						=> ['CControllerMediatypeTestEdit',						'layout.json',			'mediatype.test.edit'],
		'mediatype.test.send'						=> ['CControllerMediatypeTestSend',						'layout.json',			null],
		'mediatype.update'							=> ['CControllerMediatypeUpdate',						'layout.json',			null],
		'menu.popup'								=> ['CControllerMenuPopup',								'layout.json',			null],
		'mfa.edit'									=> ['CControllerMfaEdit',								'layout.json',			'mfa.edit'],
		'mfa.check'									=> ['CControllerMfaCheck',								'layout.json',			null],
		'miscconfig.edit'							=> ['CControllerMiscConfigEdit',						'layout.htmlpage',		'administration.miscconfig.edit'],
		'miscconfig.update'							=> ['CControllerMiscConfigUpdate',						null,					null],
		'module.disable'							=> ['CControllerModuleDisable',							'layout.json',			null],
		'module.edit'								=> ['CControllerModuleEdit',							'layout.json',			'module.edit'],
		'module.enable'								=> ['CControllerModuleEnable',							'layout.json',			null],
		'module.list'								=> ['CControllerModuleList',							'layout.htmlpage',		'module.list'],
		'module.scan'								=> ['CControllerModuleScan',							null,					null],
		'module.update'								=> ['CControllerModuleUpdate',							'layout.json',			null],
		'notifications.get'							=> ['CControllerNotificationsGet',						'layout.json',			null],
		'notifications.mute'						=> ['CControllerNotificationsMute',						'layout.json',			null],
		'notifications.read'						=> ['CControllerNotificationsRead',						'layout.json',			null],
		'notifications.snooze'						=> ['CControllerNotificationsSnooze',					'layout.json',			null],
		'popup'										=> ['CControllerPopup',									'layout.htmlpage',		'popup.view'],
		'popup.acknowledge.create'					=> ['CControllerPopupAcknowledgeCreate',				'layout.json',			null],
		'popup.action.operation.edit'				=> ['CControllerPopupActionOperationEdit',				'layout.json',			'popup.operation.edit'],
		'popup.action.operations.list'				=> ['CControllerPopupActionOperationsList',				'layout.json',			'popup.action.operations.list'],
		'popup.condition.check'						=> ['CControllerActionConditionCheck',					'layout.json',			null],
		'popup.condition.edit'						=> ['CControllerPopupActionConditionEdit',				'layout.json',			'popup.condition.edit'],
		'popup.condition.operations'				=> ['CControllerPopupConditionOperations',				'layout.json',			'popup.condition.edit'],
		'popup.dashboard.share.edit'				=> ['CControllerPopupDashboardShareEdit',				'layout.json',			'popup.dashboard.share.edit'],
		'popup.generic'								=> ['CControllerPopupGeneric',							'layout.json',			'popup.generic'],
		'popup.import'								=> ['CControllerPopupImport',							'layout.json',			'popup.import'],
		'popup.import.compare'						=> ['CControllerPopupImportCompare',					'layout.json',			'popup.import.compare'],
		'popup.itemtest.edit'						=> ['CControllerPopupItemTestEdit',						'layout.json',			'popup.itemtestedit.view'],
		'popup.itemtest.getvalue'					=> ['CControllerPopupItemTestGetValue',					'layout.json',			null],
		'popup.itemtest.send'						=> ['CControllerPopupItemTestSend',						'layout.json',			null],
		'popup.ldap.check'							=> ['CControllerPopupLdapCheck',						'layout.json',			null],
		'popup.ldap.edit'							=> ['CControllerPopupLdapEdit',							'layout.json',			'popup.ldap.edit'],
		'popup.ldap.test.edit'						=> ['CControllerPopupLdapTestEdit',						'layout.json',			'popup.ldap.test.edit'],
		'popup.ldap.test.send'						=> ['CControllerPopupLdapTestSend',						'layout.json',			null],
		'popup.lldoperation'						=> ['CControllerPopupLldOperation',						'layout.json',			'popup.lldoperation'],
		'popup.lldoverride'							=> ['CControllerPopupLldOverride',						'layout.json',			'popup.lldoverride'],
		'popup.massupdate.host'						=> ['CControllerPopupMassupdateHost',					'layout.json',			'popup.massupdate.host'],
		'popup.massupdate.service'					=> ['CControllerPopupMassupdateService',				'layout.json',			'popup.massupdate.service'],
		'popup.media'								=> ['CControllerPopupMedia',							'layout.json',			'popup.media'],
		'popup.mediatypemapping.check'				=> ['CControllerPopupMediaTypeMappingCheck',			'layout.json',			null],
		'popup.mediatypemapping.edit'				=> ['CControllerPopupMediaTypeMappingEdit',				'layout.json',			'popup.mediatypemapping.edit'],
		'popup.usergroupmapping.check'				=> ['CControllerPopupUserGroupMappingCheck',			'layout.json',			null],
		'popup.usergroupmapping.edit'				=> ['CControllerPopupUserGroupMappingEdit',				'layout.json',			'popup.usergroupmapping.edit'],
		'popup.scheduledreport.create'				=> ['CControllerPopupScheduledReportCreate',			'layout.json',			null],
		'popup.scheduledreport.edit'				=> ['CControllerPopupScheduledReportEdit',				'layout.json',			'popup.scheduledreport.edit'],
		'popup.scheduledreport.list'				=> ['CControllerPopupScheduledReportList',				'layout.json',			'popup.scheduledreport.list'],
		'popup.scheduledreport.subscription.edit'	=> ['CControllerPopupScheduledReportSubscriptionEdit',	'layout.json',			'popup.scheduledreport.subscription'],
		'popup.scheduledreport.test'				=> ['CControllerPopupScheduledReportTest',				'layout.json',			'popup.scheduledreport.test'],
		'popup.scriptexec'							=> ['CControllerPopupScriptExec',						'layout.json',			'popup.scriptexec'],
		'popup.service.statusrule.edit'				=> ['CControllerPopupServiceStatusRuleEdit',			'layout.json',			'popup.service.statusrule.edit'],
		'popup.services'							=> ['CControllerPopupServices',							'layout.json',			'popup.services'],
		'popup.sla.excludeddowntime.edit'			=> ['CControllerPopupSlaExcludedDowntimeEdit',			'layout.json',			'popup.sla.excludeddowntime.edit'],
		'popup.tabfilter.delete'					=> ['CControllerPopupTabFilterDelete',					'layout.json',			null],
		'popup.tabfilter.edit'						=> ['CControllerPopupTabFilterEdit',					'layout.json',			'popup.tabfilter.edit'],
		'popup.tabfilter.update'					=> ['CControllerPopupTabFilterUpdate',					'layout.json',			null],
		'popup.testtriggerexpr'						=> ['CControllerPopupTestTriggerExpr',					'layout.json',			'popup.testtriggerexpr'],
		'popup.token.view'							=> ['CControllerPopupTokenView',						'layout.json',			'popup.token.view'],
		'popup.triggerexpr'							=> ['CControllerPopupTriggerExpr',						'layout.json',			'popup.triggerexpr'],
		'popup.valuemap.edit'						=> ['CControllerPopupValueMapEdit',						'layout.json',			'popup.valuemap.edit'],
		'popup.valuemap.update'						=> ['CControllerPopupValueMapUpdate',					'layout.json',			null],
		'problem.view'								=> ['CControllerProblemView',							'layout.htmlpage',		'monitoring.problem.view'],
		'problem.view.csv'							=> ['CControllerProblemView',							'layout.csv',			'monitoring.problem.view'],
		'problem.view.refresh'						=> ['CControllerProblemViewRefresh',					'layout.json',			'monitoring.problem.view.refresh'],
		'profile.update'							=> ['CControllerProfileUpdate',							'layout.json',			null],
		'proxy.config.refresh'						=> ['CControllerProxyConfigRefresh',					'layout.json',			null],
		'proxy.create'								=> ['CControllerProxyCreate',							'layout.json',			null],
		'proxy.delete'								=> ['CControllerProxyDelete',							'layout.json',			null],
		'proxy.edit'								=> ['CControllerProxyEdit',								'layout.json',			'proxy.edit'],
		'proxy.host.disable'						=> ['CControllerProxyHostDisable',						'layout.json',			null],
		'proxy.host.enable'							=> ['CControllerProxyHostEnable',						'layout.json',			null],
		'proxy.list'								=> ['CControllerProxyList',								'layout.htmlpage',		'administration.proxy.list'],
		'proxy.update'								=> ['CControllerProxyUpdate',							'layout.json',			null],
		'proxygroup.create'							=> ['CControllerProxyGroupCreate',						'layout.json',			null],
		'proxygroup.delete'							=> ['CControllerProxyGroupDelete',						'layout.json',			null],
		'proxygroup.edit'							=> ['CControllerProxyGroupEdit',						'layout.json',			'proxygroup.edit'],
		'proxygroup.list'							=> ['CControllerProxyGroupList',						'layout.htmlpage',		'administration.proxygroup.list'],
		'proxygroup.update'							=> ['CControllerProxyGroupUpdate',						'layout.json',			null],
		'queue.details'								=> ['CControllerQueueDetails',							'layout.htmlpage',		'administration.queue.details'],
		'queue.overview'							=> ['CControllerQueueOverview',							'layout.htmlpage',		'administration.queue.overview'],
		'queue.overview.proxy'						=> ['CControllerQueueOverviewProxy',					'layout.htmlpage',		'administration.queue.overview.proxy'],
		'regex.create'								=> ['CControllerRegExCreate',							null,					null],
		'regex.delete'								=> ['CControllerRegExDelete',							null,					null],
		'regex.edit'								=> ['CControllerRegExEdit',								'layout.htmlpage',		'administration.regex.edit'],
		'regex.list'								=> ['CControllerRegExList',								'layout.htmlpage',		'administration.regex.list'],
		'regex.test'								=> ['CControllerRegExTest',								null,					null],
		'regex.update'								=> ['CControllerRegExUpdate',							null,					null],
		'report.status'								=> ['CControllerReportStatus',							'layout.htmlpage',		'report.status'],
		'scheduledreport.create'					=> ['CControllerScheduledReportCreate',					null,					null],
		'scheduledreport.delete'					=> ['CControllerScheduledReportDelete',					null,					null],
		'scheduledreport.disable'					=> ['CControllerScheduledReportDisable',				null,					null],
		'scheduledreport.edit'						=> ['CControllerScheduledReportEdit',					'layout.htmlpage',		'reports.scheduledreport.edit'],
		'scheduledreport.enable'					=> ['CControllerScheduledReportEnable',					null,					null],
		'scheduledreport.list'						=> ['CControllerScheduledReportList',					'layout.htmlpage',		'reports.scheduledreport.list'],
		'scheduledreport.update'					=> ['CControllerScheduledReportUpdate',					null,					null],
		'script.create'								=> ['CControllerScriptCreate',							'layout.json',			null],
		'script.delete'								=> ['CControllerScriptDelete',							'layout.json',			null],
		'script.edit'								=> ['CControllerScriptEdit',							'layout.json',			'administration.script.edit'],
		'script.list'								=> ['CControllerScriptList',							'layout.htmlpage',		'administration.script.list'],
		'script.update'								=> ['CControllerScriptUpdate',							'layout.json',			null],
		'script.userinput.edit'						=> ['CControllerScriptUserInputEdit',					'layout.json',			'script.userinput.edit'],
		'script.userinput.check'					=> ['CControllerScriptUserInputCheck',					'layout.json',			null],
		'search'									=> ['CControllerSearch',								'layout.htmlpage',		'search'],
		'service.create'							=> ['CControllerServiceCreate',							'layout.json',			null],
		'service.delete'							=> ['CControllerServiceDelete',							'layout.json',			null],
		'service.edit'								=> ['CControllerServiceEdit',							'layout.json',			'service.edit'],
		'service.list'								=> ['CControllerServiceList',							'layout.htmlpage',		'service.list'],
		'service.list.edit'							=> ['CControllerServiceListEdit',						'layout.htmlpage',		'service.list.edit'],
		'service.list.edit.refresh'					=> ['CControllerServiceListEditRefresh',				'layout.json',			'service.list.edit.refresh'],
		'service.list.refresh'						=> ['CControllerServiceListRefresh',					'layout.json',			'service.list.refresh'],
		'service.statusrule.validate'				=> ['CControllerServiceStatusRuleValidate',				'layout.json',			null],
		'service.update'							=> ['CControllerServiceUpdate',							'layout.json',			null],
		'sla.create'								=> ['CControllerSlaCreate',								'layout.json',			null],
		'sla.delete'								=> ['CControllerSlaDelete',								'layout.json',			null],
		'sla.disable'								=> ['CControllerSlaDisable',							'layout.json',			null],
		'sla.edit'									=> ['CControllerSlaEdit',								'layout.json',			'sla.edit'],
		'sla.enable'								=> ['CControllerSlaEnable',								'layout.json',			null],
		'sla.excludeddowntime.validate'				=> ['CControllerSlaExcludedDowntimeValidate',			'layout.json',			null],
		'sla.list'									=> ['CControllerSlaList',								'layout.htmlpage',		'sla.list'],
		'sla.update'								=> ['CControllerSlaUpdate',								'layout.json',			null],
		'slareport.list'							=> ['CControllerSlaReportList',							'layout.htmlpage',		'slareport.list'],
		'softwareversioncheck.get'					=> ['CControllerSoftwareVersionCheckGet',				'layout.json',			null],
		'softwareversioncheck.update'				=> ['CControllerSoftwareVersionCheckUpdate',			'layout.json',			null],
		'system.warning'							=> ['CControllerSystemWarning',							'layout.warning',		'system.warning'],
		'tabfilter.profile.update'					=> ['CControllerTabFilterProfileUpdate',				'layout.json',			null],
		'template.create'							=> ['CControllerTemplateCreate',						'layout.json',			null],
		'template.dashboard.delete'					=> ['CControllerTemplateDashboardDelete',				null,					null],
		'template.dashboard.edit'					=> ['CControllerTemplateDashboardEdit',					'layout.htmlpage',		'configuration.dashboard.edit'],
		'template.dashboard.list'					=> ['CControllerTemplateDashboardList',					'layout.htmlpage',		'configuration.dashboard.list'],
		'template.dashboard.update'					=> ['CControllerTemplateDashboardUpdate',				'layout.json',			null],
		'template.delete'							=> ['CControllerTemplateDelete',						'layout.json',			null],
		'template.edit'								=> ['CControllerTemplateEdit',							'layout.json',			'template.edit'],
		'template.list'								=> ['CControllerTemplateList',							'layout.htmlpage',		'template.list'],
		'template.massupdate'						=> ['CControllerTemplateMassupdate',					'layout.json',			'template.massupdate'],
		'template.update'							=> ['CControllerTemplateUpdate',						'layout.json',			null],
		'templategroup.create'						=> ['CControllerTemplateGroupCreate',					'layout.json',			null],
		'templategroup.delete'						=> ['CControllerTemplateGroupDelete',					'layout.json',			null],
		'templategroup.edit'						=> ['CControllerTemplateGroupEdit',						'layout.json',			'templategroup.edit'],
		'templategroup.list'						=> ['CControllerTemplateGroupList',						'layout.htmlpage',		'configuration.templategroup.list'],
		'templategroup.update'						=> ['CControllerTemplateGroupUpdate',					'layout.json',			null],
		'timeouts.edit'								=> ['CControllerTimeoutsEdit',							'layout.htmlpage',		'administration.timeouts.edit'],
		'timeouts.update'							=> ['CControllerTimeoutsUpdate',						null,					null],
		'timeselector.calc'							=> ['CControllerTimeSelectorCalc',						'layout.json',			null],
		'timeselector.update'						=> ['CControllerTimeSelectorUpdate',					'layout.json',			null],
		'token.create'								=> ['CControllerTokenCreate',							'layout.json',			null],
		'token.delete'								=> ['CControllerTokenDelete',							'layout.json',			null],
		'token.disable'								=> ['CControllerTokenDisable',							null,					null],
		'token.edit'								=> ['CControllerTokenEdit',								'layout.json',			'token.edit'],
		'token.enable'								=> ['CControllerTokenEnable',							null,					null],
		'token.list'								=> ['CControllerTokenList',								'layout.htmlpage',		'administration.token.list'],
		'token.update'								=> ['CControllerTokenUpdate',							'layout.json',			null],
		'toptriggers.list'							=> ['CControllerTopTriggersList',						'layout.htmlpage',		'reports.toptriggers.list'],
		'trigdisplay.edit'							=> ['CControllerTrigDisplayEdit',						'layout.htmlpage',		'administration.trigdisplay.edit'],
		'trigdisplay.update'						=> ['CControllerTrigDisplayUpdate',						null,					null],
		'trigger.create'							=> ['CControllerTriggerCreate',							'layout.json',			null],
		'trigger.delete'							=> ['CControllerTriggerDelete',							'layout.json',			null],
		'trigger.disable'							=> ['CControllerTriggerDisable',						'layout.json',			null],
		'trigger.edit'								=> ['CControllerTriggerEdit',							'layout.json',			'trigger.edit'],
		'trigger.enable'							=> ['CControllerTriggerEnable',							'layout.json',			null],
		'trigger.expression.constructor'			=> ['CControllerTriggerExpressionConstructor',			'layout.json',			'trigger.expression.constructor'],
		'trigger.list'								=> ['CControllerTriggerList',							'layout.htmlpage',		'trigger.list'],
		'trigger.massupdate'						=> ['CControllerTriggerMassupdate',						'layout.json',			'trigger.massupdate'],
		'trigger.prototype.create'					=> ['CControllerTriggerPrototypeCreate',				'layout.json',			null],
		'trigger.prototype.delete'					=> ['CControllerTriggerPrototypeDelete',				'layout.json',			null],
		'trigger.prototype.disable'					=> ['CControllerTriggerPrototypeDisable',				'layout.json',			null],
		'trigger.prototype.edit'					=> ['CControllerTriggerPrototypeEdit',					'layout.json',			'trigger.prototype.edit'],
		'trigger.prototype.enable'					=> ['CControllerTriggerPrototypeEnable',				'layout.json',			null],
		'trigger.prototype.list'					=> ['CControllerTriggerPrototypeList',					'layout.htmlpage',		'trigger.prototype.list'],
		'trigger.prototype.massupdate'				=> ['CControllerTriggerMassupdate',						'layout.json',			'trigger.massupdate'],
		'trigger.prototype.update'					=> ['CControllerTriggerPrototypeUpdate',				'layout.json',			null],
		'trigger.update'							=> ['CControllerTriggerUpdate',							'layout.json',			null],
		'user.create'								=> ['CControllerUserCreate',							null,					null],
		'user.delete'								=> ['CControllerUserDelete',							null,					null],
		'user.edit'									=> ['CControllerUserEdit',								'layout.htmlpage',		'administration.user.edit'],
		'user.list'									=> ['CControllerUserList',								'layout.htmlpage',		'administration.user.list'],
		'user.token.list'							=> ['CControllerUserTokenList',							'layout.htmlpage',		'administration.user.token.list'],
		'user.unblock'								=> ['CControllerUserUnblock',							null,					null],
		'user.update'								=> ['CControllerUserUpdate',							null,					null],
		'user.provision'							=> ['CControllerUserProvision',							null,					null],
		'user.reset.totp'							=> ['CControllerUserResetTotp',							null,					null],
		'usergroup.create'							=> ['CControllerUsergroupCreate',						null,					null],
		'usergroup.delete'							=> ['CControllerUsergroupDelete',						null,					null],
		'usergroup.edit'							=> ['CControllerUsergroupEdit',							'layout.htmlpage',		'usergroup.edit'],
		'usergroup.list'							=> ['CControllerUsergroupList',							'layout.htmlpage',		'usergroup.list'],
		'usergroup.massupdate'						=> ['CControllerUsergroupMassUpdate',					null,					null],
		'usergroup.tagfilter.edit'					=> ['CControllerUsergroupTagFilterEdit',				'layout.json',			'usergroup.tagfilter.edit'],
		'usergroup.tagfilter.check'					=> ['CControllerUsergroupTagFilterCheck',				'layout.json',			null],
		'usergroup.tagfilter.list'					=> ['CControllerUsergroupTagFilterList',				'layout.json',			'usergroup.tagfilter.list'],
		'usergroup.update'							=> ['CControllerUsergroupUpdate',						null,					null],
		'userprofile.edit'							=> ['CControllerUserProfileEdit',						'layout.htmlpage',		'administration.user.edit'],
		'userprofile.update'						=> ['CControllerUserProfileUpdate',						null,					null],
		'userrole.create'							=> ['CControllerUserroleCreate',						null,					null],
		'userrole.delete'							=> ['CControllerUserroleDelete',						null,					null],
		'userrole.edit'								=> ['CControllerUserroleEdit',							'layout.htmlpage',		'administration.userrole.edit'],
		'userrole.list'								=> ['CControllerUserroleList',							'layout.htmlpage',		'administration.userrole.list'],
		'userrole.update'							=> ['CControllerUserroleUpdate',						null,					null],
		'web.view'									=> ['CControllerWebView',								'layout.htmlpage',		'monitoring.web.view'],
		'webscenario.step.check'					=> ['CControllerWebScenarioStepCheck',					'layout.json',			null],
		'webscenario.step.edit'						=> ['CControllerWebScenarioStepEdit',					'layout.json',			'webscenario.step.edit'],
		'widget.navigation.tree.toggle'				=> ['CControllerWidgetNavigationTreeToggle',			'layout.json',			null],

		// legacy actions
		'auditacts.php'					=> ['CLegacyAction', null, null],
		'browserwarning.php'			=> ['CLegacyAction', null, null],
		'chart.php'						=> ['CLegacyAction', null, null],
		'chart2.php'					=> ['CLegacyAction', null, null],
		'chart3.php'					=> ['CLegacyAction', null, null],
		'chart4.php'					=> ['CLegacyAction', null, null],
		'chart6.php'					=> ['CLegacyAction', null, null],
		'chart7.php'					=> ['CLegacyAction', null, null],
		'graphs.php'					=> ['CLegacyAction', null, null],
		'history.php'					=> ['CLegacyAction', null, null],
		'host_discovery.php'			=> ['CLegacyAction', null, null],
		'host_prototypes.php'			=> ['CLegacyAction', null, null],
		'hostinventories.php'			=> ['CLegacyAction', null, null],
		'hostinventoriesoverview.php'	=> ['CLegacyAction', null, null],
		'httpconf.php'					=> ['CLegacyAction', null, null],
		'httpdetails.php'				=> ['CLegacyAction', null, null],
		'image.php'						=> ['CLegacyAction', null, null],
		'imgstore.php'					=> ['CLegacyAction', null, null],
		'index.php'						=> ['CLegacyAction', null, null],
		'index_http.php'				=> ['CLegacyAction', null, null],
		'index_mfa.php'					=> ['CLegacyAction', null, null],
		'index_sso.php'					=> ['CLegacyAction', null, null],
		'jsrpc.php'						=> ['CLegacyAction', null, null],
		'map.php'						=> ['CLegacyAction', null, null],
		'report4.php'					=> ['CLegacyAction', null, null],
		'sysmap.php'					=> ['CLegacyAction', null, null],
		'sysmaps.php'					=> ['CLegacyAction', null, null],
		'tr_events.php'					=> ['CLegacyAction', null, null]
	];

	private const DASHBOARD_ACTIONS = [
		'dashboard.print',
		'dashboard.view',
		'host.dashboard.view',
		'template.dashboard.edit'
	];

	/**
	 * Add new actions (potentially overwriting the existing ones).
	 *
	 * @param array  $actions                           List of actions.
	 * @param string $actions['action_name']            Definition of the 'action_name' action.
	 * @param string $actions['action_name']['class']   Controller class name of the 'action_name' action.
	 * @param string $actions['action_name']['layout']  Optional layout of the 'action_name' action.
	 * @param string $actions['action_name']['view']    Optional view of the 'action_name' action.
	 */
	public function addActions(array $actions): void {
		foreach ($actions as $action => $route) {
			if (is_array($route) && array_key_exists('class', $route)) {
				$this->routes[$action] = [
					$route['class'],
					array_key_exists('layout', $route) ? $route['layout'] : null,
					array_key_exists('view', $route) ? $route['view'] : null
				];
			}
		}
	}

	/**
	 * Set controller, layout and view associated with the specified action.
	 *
	 * @param string $action  Action name.
	 */
	public function setAction(string $action): void {
		$this->action = $action;

		if (array_key_exists($action, $this->routes)) {
			[$this->controller, $this->layout, $this->view] = $this->routes[$action];
		}
		else {
			$this->controller = null;
			$this->layout = null;
			$this->view = null;
		}
	}

	public function getLayout(): ?string {
		return $this->layout;
	}

	public function getController(): ?string {
		return $this->controller;
	}

	public function getView(): ?string {
		return $this->view;
	}

	public function getAction(): ?string {
		return $this->action;
	}

	public static function isDashboardAction(string $action): bool {
		return in_array($action, self::DASHBOARD_ACTIONS, true);
	}
}
