<?php declare(strict_types = 0);
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


class CRouter {
	/**
	 * Layout used for view rendering.
	 *
	 * @var string
	 */
	private $layout = null;

	/**
	 * Controller class for action handling.
	 *
	 * @var string
	 */
	private $controller = null;

	/**
	 * View used to generate HTML, CSV, JSON and other content.
	 *
	 * @var string
	 */
	private $view = null;

	/**
	 * Unique action (request) identifier.
	 *
	 * @var string
	 */
	private $action = null;

	/**
	 * Mapping between action and corresponding controller, layout and view.
	 *
	 * @var array
	 */
	private $routes = [
		// action									controller												layout					view
		'action.operation.get'						=> ['CControllerActionOperationGet',					'layout.json',			null],
		'action.operation.validate'					=> ['CControllerActionOperationValidate',				'layout.json',			null],
		'audit.settings.edit'						=> ['CControllerAuditSettingsEdit',						'layout.htmlpage',		'administration.audit.settings.edit'],
		'audit.settings.update'						=> ['CControllerAuditSettingsUpdate',					null,					null],
		'auditlog.list'								=> ['CControllerAuditLogList',							'layout.htmlpage',		'reports.auditlog.list'],
		'authentication.edit'						=> ['CControllerAuthenticationEdit',					'layout.htmlpage',		'administration.authentication.edit'],
		'authentication.update'						=> ['CControllerAuthenticationUpdate',					null,					null],
		'autoreg.edit'								=> ['CControllerAutoregEdit',							'layout.htmlpage',		'administration.autoreg.edit'],
		'autoreg.update'							=> ['CControllerAutoregUpdate',							null,					null],
		'charts.view'								=> ['CControllerChartsView',							'layout.htmlpage',		'monitoring.charts.view'],
		'charts.view.json'							=> ['CControllerChartsViewJson',						'layout.json',			'monitoring.charts.view.json'],
		'correlation.condition.add'					=> ['CControllerCorrelationConditionAdd',				null,					null],
		'correlation.create'						=> ['CControllerCorrelationCreate',						null,					null],
		'correlation.delete'						=> ['CControllerCorrelationDelete',						null,					null],
		'correlation.disable'						=> ['CControllerCorrelationDisable',					null,					null],
		'correlation.edit'							=> ['CControllerCorrelationEdit',						'layout.htmlpage',		'configuration.correlation.edit'],
		'correlation.enable'						=> ['CControllerCorrelationEnable',						null,					null],
		'correlation.list'							=> ['CControllerCorrelationList',						'layout.htmlpage',		'configuration.correlation.list'],
		'correlation.update'						=> ['CControllerCorrelationUpdate',						null,					null],
		'dashboard.delete'							=> ['CControllerDashboardDelete',						null,					null],
		'dashboard.list'							=> ['CControllerDashboardList',							'layout.htmlpage',		'monitoring.dashboard.list'],
		'dashboard.page.properties.check'			=> ['CControllerDashboardPagePropertiesCheck',			'layout.json',			null],
		'dashboard.page.properties.edit'			=> ['CControllerDashboardPagePropertiesEdit',			'layout.json',			'dashboard.page.properties.edit'],
		'dashboard.print'							=> ['CControllerDashboardPrint',						'layout.htmlpage',		'monitoring.dashboard.print'],
		'dashboard.properties.check'				=> ['CControllerDashboardPropertiesCheck',				'layout.json',			null],
		'dashboard.properties.edit'					=> ['CControllerDashboardPropertiesEdit',				'layout.json',			'dashboard.properties.edit'],
		'dashboard.share.update'					=> ['CControllerDashboardShareUpdate',					'layout.json',			null],
		'dashboard.update'							=> ['CControllerDashboardUpdate',						'layout.json',			null],
		'dashboard.view'							=> ['CControllerDashboardView',							'layout.htmlpage',		'monitoring.dashboard.view'],
		'dashboard.widget.check'					=> ['CControllerDashboardWidgetCheck',					'layout.json',			null],
		'dashboard.widget.configure'				=> ['CControllerDashboardWidgetConfigure',				'layout.json',			null],
		'dashboard.widget.edit'						=> ['CControllerDashboardWidgetEdit',					'layout.json',			'monitoring.dashboard.widget.edit'],
		'dashboard.widget.rfrate'					=> ['CControllerDashboardWidgetRfRate',					'layout.json',			null],
		'dashboard.widgets.sanitize'				=> ['CControllerDashboardWidgetsSanitize',				'layout.json',			null],
		'discovery.create'							=> ['CControllerDiscoveryCreate',						null,					null],
		'discovery.delete'							=> ['CControllerDiscoveryDelete',						null,					null],
		'discovery.disable'							=> ['CControllerDiscoveryDisable',						null,					null],
		'discovery.edit'							=> ['CControllerDiscoveryEdit',							'layout.htmlpage',		'configuration.discovery.edit'],
		'discovery.enable'							=> ['CControllerDiscoveryEnable',						null,					null],
		'discovery.list'							=> ['CControllerDiscoveryList',							'layout.htmlpage',		'configuration.discovery.list'],
		'discovery.update'							=> ['CControllerDiscoveryUpdate',						null,					null],
		'discovery.view'							=> ['CControllerDiscoveryView',							'layout.htmlpage',		'monitoring.discovery.view'],
		'export.hosts'								=> ['CControllerExport',								'layout.export',		null],
		'export.mediatypes'							=> ['CControllerExport',								'layout.export',		null],
		'export.sysmaps'							=> ['CControllerExport',								'layout.export',		null],
		'export.templates'							=> ['CControllerExport',								'layout.export',		null],
		'export.valuemaps'							=> ['CControllerExport',								'layout.export',		null],
		'favourite.create'							=> ['CControllerFavouriteCreate',						'layout.javascript',	null],
		'favourite.delete'							=> ['CControllerFavouriteDelete',						'layout.javascript',	null],
		'geomaps.edit'								=> ['CControllerGeomapsEdit',							'layout.htmlpage',		'administration.geomaps.edit'],
		'geomaps.update'							=> ['CControllerGeomapsUpdate',							null,					null],
		'gui.edit'									=> ['CControllerGuiEdit',								'layout.htmlpage',		'administration.gui.edit'],
		'gui.update'								=> ['CControllerGuiUpdate',								null,					null],
		'hintbox.actionlist'						=> ['CControllerHintboxActionlist',						'layout.json',			'hintbox.actionlist'],
		'hintbox.eventlist'							=> ['CControllerHintboxEventlist',						'layout.json',			'hintbox.eventlist'],
		'host.create'								=> ['CControllerHostCreate',							'layout.json',			null],
		'host.dashboard.view'						=> ['CControllerHostDashboardView',						'layout.htmlpage',		'monitoring.host.dashboard.view'],
		'host.edit'									=> ['CControllerHostEdit',								'layout.htmlpage',		'configuration.host.edit'],
		'host.list'									=> ['CControllerHostList',								'layout.htmlpage',		'configuration.host.list'],
		'host.massdelete'							=> ['CControllerHostMassDelete',						'layout.json',			null],
		'host.update'								=> ['CControllerHostUpdate',							'layout.json',			null],
		'host.view'									=> ['CControllerHostView',								'layout.htmlpage',		'monitoring.host.view'],
		'host.view.refresh'							=> ['CControllerHostViewRefresh',						'layout.json',			'monitoring.host.view.refresh'],
		'hostgroup.create'							=> ['CControllerHostGroupCreate',						'layout.json',			null],
		'hostgroup.delete'							=> ['CControllerHostGroupDelete',						'layout.json',			null],
		'hostgroup.disable'							=> ['CControllerHostGroupDisable',						'layout.json',			null],
		'hostgroup.edit'							=> ['CControllerHostGroupEdit',							'layout.htmlpage',		'configuration.hostgroup.edit'],
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
		'item.masscheck_now'						=> ['CControllerItemMassCheckNow',						'layout.json',			null],
		'latest.view'								=> ['CControllerLatestView',							'layout.htmlpage',		'monitoring.latest.view'],
		'latest.view.refresh'						=> ['CControllerLatestViewRefresh',						'layout.json',			'monitoring.latest.view.refresh'],
		'macros.edit'								=> ['CControllerMacrosEdit',							'layout.htmlpage',		'administration.macros.edit'],
		'macros.update'								=> ['CControllerMacrosUpdate',							null,					null],
		'map.view'									=> ['CControllerMapView',								'layout.htmlpage',		'monitoring.map.view'],
		'mediatype.create'							=> ['CControllerMediatypeCreate',						null,					null],
		'mediatype.delete'							=> ['CControllerMediatypeDelete',						null,					null],
		'mediatype.disable'							=> ['CControllerMediatypeDisable',						null,					null],
		'mediatype.edit'							=> ['CControllerMediatypeEdit',							'layout.htmlpage',		'administration.mediatype.edit'],
		'mediatype.enable'							=> ['CControllerMediatypeEnable',						null,					null],
		'mediatype.list'							=> ['CControllerMediatypeList',							'layout.htmlpage',		'administration.mediatype.list'],
		'mediatype.update'							=> ['CControllerMediatypeUpdate',						null,					null],
		'menu.popup'								=> ['CControllerMenuPopup',								'layout.json',			null],
		'miscconfig.edit'							=> ['CControllerMiscConfigEdit',						'layout.htmlpage',		'administration.miscconfig.edit'],
		'miscconfig.update'							=> ['CControllerMiscConfigUpdate',						null,					null],
		'module.disable'							=> ['CControllerModuleUpdate',							null,					null],
		'module.edit'								=> ['CControllerModuleEdit',							'layout.htmlpage',		'administration.module.edit'],
		'module.enable'								=> ['CControllerModuleUpdate',							null,					null],
		'module.list'								=> ['CControllerModuleList',							'layout.htmlpage',		'administration.module.list'],
		'module.scan'								=> ['CControllerModuleScan',							null,					null],
		'module.update'								=> ['CControllerModuleUpdate',							null,					null],
		'notifications.get'							=> ['CControllerNotificationsGet',						'layout.json',			null],
		'notifications.mute'						=> ['CControllerNotificationsMute',						'layout.json',			null],
		'notifications.read'						=> ['CControllerNotificationsRead',						'layout.json',			null],
		'popup'										=> ['CControllerPopup',									'layout.htmlpage',		'popup.view'],
		'popup.acknowledge.create'					=> ['CControllerPopupAcknowledgeCreate',				'layout.json',			null],
		'popup.acknowledge.edit'					=> ['CControllerPopupAcknowledgeEdit',					'layout.json',			'popup.acknowledge.edit'],
		'popup.condition.actions'					=> ['CControllerPopupConditionActions',					'layout.json',			'popup.condition.common'],
		'popup.condition.event.corr'				=> ['CControllerPopupConditionEventCorr',				'layout.json',			'popup.condition.common'],
		'popup.condition.operations'				=> ['CControllerPopupConditionOperations',				'layout.json',			'popup.condition.common'],
		'popup.dashboard.share.edit'				=> ['CControllerPopupDashboardShareEdit',				'layout.json',			'popup.dashboard.share.edit'],
		'popup.discovery.check'						=> ['CControllerPopupDiscoveryCheck',					'layout.json',			'popup.discovery.check'],
		'popup.generic'								=> ['CControllerPopupGeneric',							'layout.json',			'popup.generic'],
		'popup.host.edit'							=> ['CControllerHostEdit',								'layout.json',			'popup.host.edit'],
		'popup.hostgroup.edit'						=> ['CControllerHostGroupEdit',							'layout.json',			'popup.hostgroup.edit'],
		'popup.httpstep'							=> ['CControllerPopupHttpStep',							'layout.json',			'popup.httpstep'],
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
		'popup.maintenance.period'					=> ['CControllerPopupMaintenancePeriod',				'layout.json',			'popup.maintenance.period'],
		'popup.massupdate.host'						=> ['CControllerPopupMassupdateHost',					'layout.json',			'popup.massupdate.host'],
		'popup.massupdate.item'						=> ['CControllerPopupMassupdateItem',					'layout.json',			'popup.massupdate.item'],
		'popup.massupdate.itemprototype'			=> ['CControllerPopupMassupdateItem',					'layout.json',			'popup.massupdate.item'],
		'popup.massupdate.service'					=> ['CControllerPopupMassupdateService',				'layout.json',			'popup.massupdate.service'],
		'popup.massupdate.template'					=> ['CControllerPopupMassupdateTemplate',				'layout.json',			'popup.massupdate.template'],
		'popup.massupdate.trigger'					=> ['CControllerPopupMassupdateTrigger',				'layout.json',			'popup.massupdate.trigger'],
		'popup.massupdate.triggerprototype'			=> ['CControllerPopupMassupdateTrigger',				'layout.json',			'popup.massupdate.trigger'],
		'popup.media'								=> ['CControllerPopupMedia',							'layout.json',			'popup.media'],
		'popup.mediatype.message'					=> ['CControllerPopupMediatypeMessage',					'layout.json',			'popup.mediatype.message'],
		'popup.mediatypetest.edit'					=> ['CControllerPopupMediatypeTestEdit',				'layout.json',			'popup.mediatypetest.edit'],
		'popup.mediatypetest.send'					=> ['CControllerPopupMediatypeTestSend',				'layout.json',			null],
		'popup.proxy.edit'							=> ['CControllerPopupProxyEdit',						'layout.json',			'popup.proxy.edit'],
		'popup.scheduledreport.create'				=> ['CControllerPopupScheduledReportCreate',			'layout.json',			null],
		'popup.scheduledreport.edit'				=> ['CControllerPopupScheduledReportEdit',				'layout.json',			'popup.scheduledreport.edit'],
		'popup.scheduledreport.list'				=> ['CControllerPopupScheduledReportList',				'layout.json',			'popup.scheduledreport.list'],
		'popup.scheduledreport.subscription.edit'	=> ['CControllerPopupScheduledReportSubscriptionEdit',	'layout.json',			'popup.scheduledreport.subscription'],
		'popup.scheduledreport.test'				=> ['CControllerPopupScheduledReportTest',				'layout.json',			'popup.scheduledreport.test'],
		'popup.scriptexec'							=> ['CControllerPopupScriptExec',						'layout.json',			'popup.scriptexec'],
		'popup.service.edit'						=> ['CControllerPopupServiceEdit',						'layout.json',			'popup.service.edit'],
		'popup.service.statusrule.edit'				=> ['CControllerPopupServiceStatusRuleEdit',			'layout.json',			'popup.service.statusrule.edit'],
		'popup.services'							=> ['CControllerPopupServices',							'layout.json',			'popup.services'],
		'popup.sla.edit'							=> ['CControllerPopupSlaEdit',							'layout.json',			'popup.sla.edit'],
		'popup.sla.excludeddowntime.edit'			=> ['CControllerPopupSlaExcludedDowntimeEdit',			'layout.json',			'popup.sla.excludeddowntime.edit'],
		'popup.tabfilter.delete'					=> ['CControllerPopupTabFilterDelete',					'layout.json',			null],
		'popup.tabfilter.edit'						=> ['CControllerPopupTabFilterEdit',					'layout.json',			'popup.tabfilter.edit'],
		'popup.tabfilter.update'					=> ['CControllerPopupTabFilterUpdate',					'layout.json',			null],
		'popup.templategroup.edit'					=> ['CControllerTemplateGroupEdit',						'layout.json',			'popup.templategroup.edit'],
		'popup.testtriggerexpr'						=> ['CControllerPopupTestTriggerExpr',					'layout.json',			'popup.testtriggerexpr'],
		'popup.token.edit'							=> ['CControllerPopupTokenEdit',						'layout.json',			'popup.token.edit'],
		'popup.token.view'							=> ['CControllerPopupTokenView',						'layout.json',			'popup.token.view'],
		'popup.tophosts.column.edit'				=> ['CControllerPopupTopHostsColumnEdit',				'layout.json',			'popup.tophosts.column.edit'],
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
		'proxy.host.disable'						=> ['CControllerProxyHostDisable',						'layout.json',			null],
		'proxy.host.enable'							=> ['CControllerProxyHostEnable',						'layout.json',			null],
		'proxy.list'								=> ['CControllerProxyList',								'layout.htmlpage',		'proxy.list'],
		'proxy.update'								=> ['CControllerProxyUpdate',							'layout.json',			null],
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
		'script.create'								=> ['CControllerScriptCreate',							null,					null],
		'script.delete'								=> ['CControllerScriptDelete',							null,					null],
		'script.edit'								=> ['CControllerScriptEdit',							'layout.htmlpage',		'administration.script.edit'],
		'script.list'								=> ['CControllerScriptList',							'layout.htmlpage',		'administration.script.list'],
		'script.update'								=> ['CControllerScriptUpdate',							null,					null],
		'search'									=> ['CControllerSearch',								'layout.htmlpage',		'search'],
		'service.create'							=> ['CControllerServiceCreate',							'layout.json',			null],
		'service.delete'							=> ['CControllerServiceDelete',							'layout.json',			null],
		'service.list'								=> ['CControllerServiceList',							'layout.htmlpage',		'service.list'],
		'service.list.edit'							=> ['CControllerServiceListEdit',						'layout.htmlpage',		'service.list.edit'],
		'service.list.edit.refresh'					=> ['CControllerServiceListEditRefresh',				'layout.json',			'service.list.edit.refresh'],
		'service.list.refresh'						=> ['CControllerServiceListRefresh',					'layout.json',			'service.list.refresh'],
		'service.statusrule.validate'				=> ['CControllerServiceStatusRuleValidate',				'layout.json',			null],
		'service.update'							=> ['CControllerServiceUpdate',							'layout.json',			null],
		'sla.create'								=> ['CControllerSlaCreate',								'layout.json',			null],
		'sla.delete'								=> ['CControllerSlaDelete',								'layout.json',			null],
		'sla.disable'								=> ['CControllerSlaDisable',							'layout.json',			null],
		'sla.enable'								=> ['CControllerSlaEnable',								'layout.json',			null],
		'sla.excludeddowntime.validate'				=> ['CControllerSlaExcludedDowntimeValidate',			'layout.json',			null],
		'sla.list'									=> ['CControllerSlaList',								'layout.htmlpage',		'sla.list'],
		'sla.update'								=> ['CControllerSlaUpdate',								'layout.json',			null],
		'slareport.list'							=> ['CControllerSlaReportList',							'layout.htmlpage',		'slareport.list'],
		'system.warning'							=> ['CControllerSystemWarning',							'layout.warning',		'system.warning'],
		'tabfilter.profile.update'					=> ['CControllerTabFilterProfileUpdate',				'layout.json',			null],
		'template.dashboard.delete'					=> ['CControllerTemplateDashboardDelete',				null,					null],
		'template.dashboard.edit'					=> ['CControllerTemplateDashboardEdit',					'layout.htmlpage',		'configuration.dashboard.edit'],
		'template.dashboard.list'					=> ['CControllerTemplateDashboardList',					'layout.htmlpage',		'configuration.dashboard.list'],
		'template.dashboard.update'					=> ['CControllerTemplateDashboardUpdate',				'layout.json',			null],
		'templategroup.create'						=> ['CControllerTemplateGroupCreate',					'layout.json',			null],
		'templategroup.delete'						=> ['CControllerTemplateGroupDelete',					'layout.json',			null],
		'templategroup.edit'						=> ['CControllerTemplateGroupEdit',						'layout.htmlpage',		'configuration.templategroup.edit'],
		'templategroup.list'						=> ['CControllerTemplateGroupList',						'layout.htmlpage',		'configuration.templategroup.list'],
		'templategroup.update'						=> ['CControllerTemplateGroupUpdate',					'layout.json',			null],
		'timeselector.update'						=> ['CControllerTimeSelectorUpdate',					'layout.json',			null],
		'token.create'								=> ['CControllerTokenCreate',							'layout.json',			null],
		'token.delete'								=> ['CControllerTokenDelete',							'layout.json',			null],
		'token.disable'								=> ['CControllerTokenDisable',							null,					null],
		'token.enable'								=> ['CControllerTokenEnable',							null,					null],
		'token.list'								=> ['CControllerTokenList',								'layout.htmlpage',		'administration.token.list'],
		'token.update'								=> ['CControllerTokenUpdate',							'layout.json',			null],
		'trigdisplay.edit'							=> ['CControllerTrigDisplayEdit',						'layout.htmlpage',		'administration.trigdisplay.edit'],
		'trigdisplay.update'						=> ['CControllerTrigDisplayUpdate',						null,					null],
		'user.create'								=> ['CControllerUserCreate',							null,					null],
		'user.delete'								=> ['CControllerUserDelete',							null,					null],
		'user.edit'									=> ['CControllerUserEdit',								'layout.htmlpage',		'administration.user.edit'],
		'user.list'									=> ['CControllerUserList',								'layout.htmlpage',		'administration.user.list'],
		'user.token.list'							=> ['CControllerUserTokenList',							'layout.htmlpage',		'administration.user.token.list'],
		'user.unblock'								=> ['CControllerUserUnblock',							null,					null],
		'user.update'								=> ['CControllerUserUpdate',							null,					null],
		'usergroup.create'							=> ['CControllerUsergroupCreate',						null,					null],
		'usergroup.delete'							=> ['CControllerUsergroupDelete',						null,					null],
		'usergroup.edit'							=> ['CControllerUsergroupEdit',							'layout.htmlpage',		'administration.usergroup.edit'],
		'usergroup.groupright.add'					=> ['CControllerUsergroupGrouprightAdd',				'layout.json',			'administration.usergroup.grouprights'],
		'usergroup.templategroupright.add'			=> ['CControllerUsergroupTemplateGrouprightAdd',		'layout.json',			'administration.usergroup.templategrouprights'],
		'usergroup.list'							=> ['CControllerUsergroupList',							'layout.htmlpage',		'administration.usergroup.list'],
		'usergroup.massupdate'						=> ['CControllerUsergroupMassUpdate',					null,					null],
		'usergroup.tagfilter.add'					=> ['CControllerUsergroupTagfilterAdd',					'layout.json',			'administration.usergroup.tagfilters'],
		'usergroup.update'							=> ['CControllerUsergroupUpdate',						null,					null],
		'userprofile.edit'							=> ['CControllerUserProfileEdit',						'layout.htmlpage',		'administration.user.edit'],
		'userprofile.update'						=> ['CControllerUserProfileUpdate',						null,					null],
		'userrole.create'							=> ['CControllerUserroleCreate',						null,					null],
		'userrole.delete'							=> ['CControllerUserroleDelete',						null,					null],
		'userrole.edit'								=> ['CControllerUserroleEdit',							'layout.htmlpage',		'administration.userrole.edit'],
		'userrole.list'								=> ['CControllerUserroleList',							'layout.htmlpage',		'administration.userrole.list'],
		'userrole.update'							=> ['CControllerUserroleUpdate',						null,					null],
		'web.view'									=> ['CControllerWebView',								'layout.htmlpage',		'monitoring.web.view'],
		'widget.actionlog.view'						=> ['CControllerWidgetActionLogView',					'layout.widget',		'monitoring.widget.actionlog.view'],
		'widget.clock.view'							=> ['CControllerWidgetClockView',						'layout.widget',		'monitoring.widget.clock.view'],
		'widget.dataover.view'						=> ['CControllerWidgetDataOverView',					'layout.widget',		'monitoring.widget.dataover.view'],
		'widget.discovery.view'						=> ['CControllerWidgetDiscoveryView',					'layout.widget',		'monitoring.widget.discovery.view'],
		'widget.favgraphs.view'						=> ['CControllerWidgetFavGraphsView',					'layout.widget',		'monitoring.widget.favgraphs.view'],
		'widget.favmaps.view'						=> ['CControllerWidgetFavMapsView',						'layout.widget',		'monitoring.widget.favmaps.view'],
		'widget.geomap.view'						=> ['CControllerWidgetGeoMapView',						'layout.widget',		'monitoring.widget.geomap.view'],
		'widget.graph.view'							=> ['CControllerWidgetGraphView',						'layout.widget',		'monitoring.widget.graph.view'],
		'widget.graphprototype.view'				=> ['CControllerWidgetIteratorGraphPrototypeView',		'layout.json',			null],
		'widget.hostavail.view'						=> ['CControllerWidgetHostAvailView',					'layout.widget',		'monitoring.widget.hostavail.view'],
		'widget.item.view'							=> ['CControllerWidgetItemView',						'layout.widget',		'monitoring.widget.item.view'],
		'widget.map.view'							=> ['CControllerWidgetMapView',							'layout.widget',		'monitoring.widget.map.view'],
		'widget.navtree.item.edit'					=> ['CControllerWidgetNavTreeItemEdit',					'layout.json',			'monitoring.widget.navtreeitem.edit'],
		'widget.navtree.item.update'				=> ['CControllerWidgetNavTreeItemUpdate',				'layout.json',			null],
		'widget.navtree.view'						=> ['CControllerWidgetNavTreeView',						'layout.widget',		'monitoring.widget.navtree.view'],
		'widget.plaintext.view'						=> ['CControllerWidgetPlainTextView',					'layout.widget',		'monitoring.widget.plaintext.view'],
		'widget.problemhosts.view'					=> ['CControllerWidgetProblemHostsView',				'layout.widget',		'monitoring.widget.problemhosts.view'],
		'widget.problems.view'						=> ['CControllerWidgetProblemsView',					'layout.widget',		'monitoring.widget.problems.view'],
		'widget.problemsbysv.view'					=> ['CControllerWidgetProblemsBySvView',				'layout.widget',		'monitoring.widget.problemsbysv.view'],
		'widget.slareport.view'						=> ['CControllerWidgetSlaReportView',					'layout.widget',		'monitoring.widget.slareport.view'],
		'widget.svggraph.view'						=> ['CControllerWidgetSvgGraphView',					'layout.widget',		'monitoring.widget.svggraph.view'],
		'widget.systeminfo.view'					=> ['CControllerWidgetSystemInfoView',					'layout.widget',		'monitoring.widget.systeminfo.view'],
		'widget.tophosts.view'						=> ['CControllerWidgetTopHostsView',					'layout.widget',		'monitoring.widget.tophosts.view'],
		'widget.trigover.view'						=> ['CControllerWidgetTrigOverView',					'layout.widget',		'monitoring.widget.trigover.view'],
		'widget.url.view'							=> ['CControllerWidgetUrlView',							'layout.widget',		'monitoring.widget.url.view'],
		'widget.web.view'							=> ['CControllerWidgetWebView',							'layout.widget',		'monitoring.widget.web.view'],

		// legacy actions
		'actionconf.php'				=> ['CLegacyAction', null, null],
		'auditacts.php'					=> ['CLegacyAction', null, null],
		'browserwarning.php'			=> ['CLegacyAction', null, null],
		'chart.php'						=> ['CLegacyAction', null, null],
		'chart2.php'					=> ['CLegacyAction', null, null],
		'chart3.php'					=> ['CLegacyAction', null, null],
		'chart4.php'					=> ['CLegacyAction', null, null],
		'chart6.php'					=> ['CLegacyAction', null, null],
		'chart7.php'					=> ['CLegacyAction', null, null],
		'disc_prototypes.php'			=> ['CLegacyAction', null, null],
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
		'index_sso.php'					=> ['CLegacyAction', null, null],
		'items.php'						=> ['CLegacyAction', null, null],
		'jsrpc.php'						=> ['CLegacyAction', null, null],
		'maintenance.php'				=> ['CLegacyAction', null, null],
		'map.php'						=> ['CLegacyAction', null, null],
		'report2.php'					=> ['CLegacyAction', null, null],
		'report4.php'					=> ['CLegacyAction', null, null],
		'sysmap.php'					=> ['CLegacyAction', null, null],
		'sysmaps.php'					=> ['CLegacyAction', null, null],
		'templates.php' 				=> ['CLegacyAction', null, null],
		'toptriggers.php'				=> ['CLegacyAction', null, null],
		'tr_events.php'					=> ['CLegacyAction', null, null],
		'trigger_prototypes.php'		=> ['CLegacyAction', null, null],
		'triggers.php'					=> ['CLegacyAction', null, null]
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

	/**
	 * Returns layout name.
	 *
	 * @return string|null
	 */
	public function getLayout(): ?string {
		return $this->layout;
	}

	/**
	 * Returns controller name.
	 *
	 * @return string|null
	 */
	public function getController(): ?string {
		return $this->controller;
	}

	/**
	 * Returns view name.
	 *
	 * @return string|null
	 */
	public function getView(): ?string {
		return $this->view;
	}

	/**
	 * Returns action name.
	 *
	 * @return string|null
	 */
	public function getAction(): ?string {
		return $this->action;
	}
}
