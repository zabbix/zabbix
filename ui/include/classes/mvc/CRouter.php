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
		// action									controller															layout					view
		'acknowledge.edit'							=> [CControllerAcknowledgeEdit::class,								'layout.json',			'acknowledge.edit'],
		'action.create'								=> [CControllerActionCreate::class,									'layout.json',			null],
		'action.delete'								=> [CControllerActionDelete::class,									'layout.json',			null],
		'action.disable'							=> [CControllerActionDisable::class,								'layout.json',			null],
		'action.edit'								=> [CControllerActionEdit::class,									'layout.json',			'action.edit'],
		'action.enable'								=> [CControllerActionEnable::class,									'layout.json',			null],
		'action.list'								=> [CControllerActionList::class,									'layout.htmlpage',		'action.list'],
		'action.operation.check'					=> [CControllerActionOperationCheck::class,							'layout.json',			null],
		'action.operation.condition.check'			=> [CControllerActionOperationConditionCheck::class,				'layout.json',			null],
		'action.update'								=> [CControllerActionUpdate::class,									'layout.json',			null],
		'actionlog.csv'								=> [CControllerActionLogList::class,								'layout.csv',			'reports.actionlog.list.csv'],
		'actionlog.list'							=> [CControllerActionLogList::class,								'layout.htmlpage',		'reports.actionlog.list'],
		'audit.settings.edit'						=> [CControllerAuditSettingsEdit::class,							'layout.htmlpage',		'administration.audit.settings.edit'],
		'audit.settings.update'						=> [CControllerAuditSettingsUpdate::class,							null,					null],
		'auditlog.list'								=> [CControllerAuditLogList::class,									'layout.htmlpage',		'reports.auditlog.list'],
		'authentication.edit'						=> [CControllerAuthenticationEdit::class,							'layout.htmlpage',		'administration.authentication.edit'],
		'authentication.update'						=> [CControllerAuthenticationUpdate::class,							null,					null],
		'autoreg.edit'								=> [CControllerAutoregEdit::class,									'layout.htmlpage',		'administration.autoreg.edit'],
		'autoreg.update'							=> [CControllerAutoregUpdate::class,								'layout.json',			null],
		'availabilityreport.list'					=> [CControllerAvailabilityReportList::class,						'layout.htmlpage',		'reports.availabilityreport.list'],
		'availabilityreport.trigger'				=> [CControllerAvailabilityReportTrigger::class,					'layout.htmlpage',		'reports.availabilityreport.trigger'],
		'charts.view'								=> [CControllerChartsView::class,									'layout.htmlpage',		'monitoring.charts.view'],
		'charts.view.json'							=> [CControllerChartsViewJson::class,								'layout.json',			'monitoring.charts.view.json'],
		'connector.create'							=> [CControllerConnectorCreate::class,								'layout.json',			null],
		'connector.delete'							=> [CControllerConnectorDelete::class,								'layout.json',			null],
		'connector.disable'							=> [CControllerConnectorDisable::class,								'layout.json',			null],
		'connector.edit'							=> [CControllerConnectorEdit::class,								'layout.json',			'connector.edit'],
		'connector.enable'							=> [CControllerConnectorEnable::class,								'layout.json',			null],
		'connector.list'							=> [CControllerConnectorList::class,								'layout.htmlpage',		'connector.list'],
		'connector.update'							=> [CControllerConnectorUpdate::class,								'layout.json',			null],
		'copy.create'								=> [CControllerCopyCreate::class,									'layout.json',			null],
		'copy.edit'									=> [CControllerCopyEdit::class,										'layout.json',			'copy.edit'],
		'correlation.condition.check'				=> [CControllerCorrelationConditionCheck::class,					'layout.json',			null],
		'correlation.condition.edit'				=> [CControllerCorrelationConditionEdit::class,						'layout.json',			'correlation.condition.edit'],
		'correlation.create'						=> [CControllerCorrelationCreate::class,							'layout.json',			null],
		'correlation.delete'						=> [CControllerCorrelationDelete::class,							'layout.json',			null],
		'correlation.disable'						=> [CControllerCorrelationDisable::class,							'layout.json',			null],
		'correlation.edit'							=> [CControllerCorrelationEdit::class,								'layout.json',			'correlation.edit'],
		'correlation.enable'						=> [CControllerCorrelationEnable::class,							'layout.json',			null],
		'correlation.list'							=> [CControllerCorrelationList::class,								'layout.htmlpage',		'correlation.list'],
		'correlation.update'						=> [CControllerCorrelationUpdate::class,							'layout.json',			null],
		'dashboard.config.hash'						=> [CControllerDashboardConfigHash::class,							'layout.json',			null],
		'dashboard.delete'							=> [CControllerDashboardDelete::class,								null,					null],
		'dashboard.list'							=> [CControllerDashboardList::class,								'layout.htmlpage',		'monitoring.dashboard.list'],
		'dashboard.page.properties.check'			=> [CControllerDashboardPagePropertiesCheck::class,					'layout.json',			null],
		'dashboard.page.properties.edit'			=> [CControllerDashboardPagePropertiesEdit::class,					'layout.json',			'dashboard.page.properties.edit'],
		'dashboard.print'							=> [CControllerDashboardPrint::class,								'layout.print',			'monitoring.dashboard.print'],
		'dashboard.properties.check'				=> [CControllerDashboardPropertiesCheck::class,						'layout.json',			null],
		'dashboard.properties.edit'					=> [CControllerDashboardPropertiesEdit::class,						'layout.json',			'dashboard.properties.edit'],
		'dashboard.share.update'					=> [CControllerDashboardShareUpdate::class,							'layout.json',			null],
		'dashboard.update'							=> [CControllerDashboardUpdate::class,								'layout.json',			null],
		'dashboard.view'							=> [CControllerDashboardView::class,								'layout.htmlpage',		'monitoring.dashboard.view'],
		'dashboard.widget.check'					=> [CControllerDashboardWidgetCheck::class,							'layout.json',			null],
		'dashboard.widget.rfrate'					=> [CControllerDashboardWidgetRfRate::class,						'layout.json',			null],
		'dashboard.widgets.validate'				=> [CControllerDashboardWidgetsValidate::class,						'layout.json',			null],
		'discovery.check.check'						=> [CControllerDiscoveryCheckCheck::class,							'layout.json',			null],
		'discovery.check.edit'						=> [CControllerDiscoveryCheckEdit::class,							'layout.json',			'discovery.check.edit'],
		'discovery.create'							=> [CControllerDiscoveryCreate::class,								'layout.json',			null],
		'discovery.delete'							=> [CControllerDiscoveryDelete::class,								'layout.json',			null],
		'discovery.disable'							=> [CControllerDiscoveryDisable::class,								'layout.json',			null],
		'discovery.edit'							=> [CControllerDiscoveryEdit::class,								'layout.json',			'configuration.discovery.edit'],
		'discovery.enable'							=> [CControllerDiscoveryEnable::class,								'layout.json',			null],
		'discovery.list'							=> [CControllerDiscoveryList::class,								'layout.htmlpage',		'configuration.discovery.list'],
		'discovery.update'							=> [CControllerDiscoveryUpdate::class,								'layout.json',			null],
		'discovery.view'							=> [CControllerDiscoveryView::class,								'layout.htmlpage',		'monitoring.discovery.view'],
		'export.hosts'								=> [CControllerExport::class,										'layout.export',		null],
		'export.mediatypes'							=> [CControllerExport::class,										'layout.export',		null],
		'export.sysmaps'							=> [CControllerExport::class,										'layout.export',		null],
		'export.templates'							=> [CControllerExport::class,										'layout.export',		null],
		'favorite.create'							=> [CControllerFavoriteCreate::class,								'layout.javascript',	null],
		'favorite.delete'							=> [CControllerFavoriteDelete::class,								'layout.javascript',	null],
		'geomaps.edit'								=> [CControllerGeomapsEdit::class,									'layout.htmlpage',		'administration.geomaps.edit'],
		'geomaps.update'							=> [CControllerGeomapsUpdate::class,								'layout.json',			null],
		'gui.edit'									=> [CControllerGuiEdit::class,										'layout.htmlpage',		'administration.gui.edit'],
		'gui.update'								=> [CControllerGuiUpdate::class,									null,					null],
		'graph.edit'								=> [CControllerGraphEdit::class,									'layout.json',			'graph.edit'],
		'graph.create'								=> [CControllerGraphCreate::class,									'layout.json',			null],
		'graph.delete'								=> [CControllerGraphDelete::class,									'layout.json',			null],
		'graph.list'								=> [CControllerGraphList::class,									'layout.htmlpage',		'graph.list'],
		'graph.update'								=> [CControllerGraphUpdate::class,									'layout.json',			null],
		'graph.prototype.create'					=> [CControllerGraphPrototypeCreate::class,							'layout.json',			null],
		'graph.prototype.delete'					=> [CControllerGraphPrototypeDelete::class,							'layout.json',			null],
		'graph.prototype.edit'						=> [CControllerGraphPrototypeEdit::class,							'layout.json',			'graph.prototype.edit'],
		'graph.prototype.list'						=> [CControllerGraphPrototypeList::class,							'layout.htmlpage',		'graph.prototype.list'],
		'graph.prototype.update'					=> [CControllerGraphPrototypeUpdate::class,							'layout.json',			null],
		'graph.prototype.updatediscover'			=> [CControllerGraphPrototypeUpdateDiscover::class,					'layout.json',			null],
		'hintbox.actionlist'						=> [CControllerHintboxActionlist::class,							'layout.json',			'hintbox.actionlist'],
		'hintbox.eventlist'							=> [CControllerHintboxEventlist::class,								'layout.json',			'hintbox.eventlist'],
		'host.create'								=> [CControllerHostCreate::class,									'layout.json',			null],
		'host.dashboard.view'						=> [CControllerHostDashboardView::class,							'layout.htmlpage',		'monitoring.host.dashboard.view'],
		'host.disable'								=> [CControllerHostDisable::class,									'layout.json',			null],
		'host.edit'									=> [CControllerHostEdit::class,										'layout.json',			'host.edit'],
		'host.enable'								=> [CControllerHostEnable::class,									'layout.json',			null],
		'host.list'									=> [CControllerHostList::class,										'layout.htmlpage',		'configuration.host.list'],
		'host.massdelete'							=> [CControllerHostMassDelete::class,								'layout.json',			null],
		'host.tags.list'							=> [CControllerHostTagsList::class,									'layout.json',			'host.tags.list'],
		'host.update'								=> [CControllerHostUpdate::class,									'layout.json',			null],
		'host.view'									=> [CControllerHostView::class,										'layout.htmlpage',		'monitoring.host.view'],
		'host.view.refresh'							=> [CControllerHostViewRefresh::class,								'layout.json',			'monitoring.host.view.refresh'],
		'host.prototype.create'						=> [CControllerHostPrototypeCreate::class,							'layout.json',			null],
		'host.prototype.delete'						=> [CControllerHostPrototypeDelete::class,							'layout.json',			null],
		'host.prototype.edit'						=> [CControllerHostPrototypeEdit::class,							'layout.json',			'host.prototype.edit'],
		'host.prototype.enable'						=> [CControllerHostPrototypeEnable::class,							'layout.json',			null],
		'host.prototype.list'						=> [CControllerHostPrototypeList::class,							'layout.htmlpage',		'host.prototype.list'],
		'host.prototype.update'						=> [CControllerHostPrototypeUpdate::class,							'layout.json',			null],
		'host.prototype.disable'					=> [CControllerHostPrototypeDisable::class,							'layout.json',			null],
		'host.wizard.create'						=> [CControllerHostWizardCreate::class,								'layout.json',			null],
		'host.wizard.edit'							=> [CControllerHostWizardEdit::class,								'layout.json',			'host.wizard.edit'],
		'host.wizard.get'							=> [CControllerHostWizardGet::class,								'layout.json',			null],
		'host.wizard.update'						=> [CControllerHostWizardUpdate::class,								'layout.json',			null],
		'hostgroup.create'							=> [CControllerHostGroupCreate::class,								'layout.json',			null],
		'hostgroup.delete'							=> [CControllerHostGroupDelete::class,								'layout.json',			null],
		'hostgroup.disable'							=> [CControllerHostGroupDisable::class,								'layout.json',			null],
		'hostgroup.edit'							=> [CControllerHostGroupEdit::class,								'layout.json',			'hostgroup.edit'],
		'hostgroup.enable'							=> [CControllerHostGroupEnable::class,								'layout.json',			null],
		'hostgroup.list'							=> [CControllerHostGroupList::class,								'layout.htmlpage',		'configuration.hostgroup.list'],
		'hostgroup.update'							=> [CControllerHostGroupUpdate::class,								'layout.json',			null],
		'hostmacros.list'							=> [CControllerHostMacrosList::class,								'layout.json',			'hostmacros.list'],
		'housekeeping.edit'							=> [CControllerHousekeepingEdit::class,								'layout.htmlpage',		'administration.housekeeping.edit'],
		'housekeeping.update'						=> [CControllerHousekeepingUpdate::class,							null,					null],
		'iconmap.create'							=> [CControllerIconMapCreate::class,								'layout.json',			null],
		'iconmap.delete'							=> [CControllerIconMapDelete::class,								null,					null],
		'iconmap.edit'								=> [CControllerIconMapEdit::class,									'layout.htmlpage',		'administration.iconmap.edit'],
		'iconmap.list'								=> [CControllerIconMapList::class,									'layout.htmlpage',		'administration.iconmap.list'],
		'iconmap.update'							=> [CControllerIconMapUpdate::class,								'layout.json',			null],
		'image.create'								=> [CControllerImageCreate::class,									'layout.json',			null],
		'image.delete'								=> [CControllerImageDelete::class,									null,					null],
		'image.edit'								=> [CControllerImageEdit::class,									'layout.htmlpage',		'administration.image.edit'],
		'image.list'								=> [CControllerImageList::class,									'layout.htmlpage',		'administration.image.list'],
		'image.update'								=> [CControllerImageUpdate::class,									'layout.json',			null],
		'item.clear'								=> [CControllerItemClear::class,									'layout.json',			null],
		'item.create'								=> [CControllerItemCreate::class,									'layout.json',			null],
		'item.delete'								=> [CControllerItemDelete::class,									'layout.json',			null],
		'item.disable'								=> [CControllerItemDisable::class,									'layout.json',			null],
		'item.edit'									=> [CControllerItemEdit::class,										'layout.json',			'item.edit'],
		'item.enable'								=> [CControllerItemEnable::class,									'layout.json',			null],
		'item.execute'								=> [CControllerItemExecuteNow::class,								'layout.json',			null],
		'item.list'									=> [CControllerItemList::class,										'layout.htmlpage',		'item.list'],
		'item.massupdate'							=> [CControllerItemMassupdate::class,								'layout.json',			'item.massupdate'],
		'item.update'								=> [CControllerItemUpdate::class,									'layout.json',			null],
		'item.prototype.create'						=> [CControllerItemPrototypeCreate::class,							'layout.json',			null],
		'item.prototype.delete'						=> [CControllerItemPrototypeDelete::class,							'layout.json',			null],
		'item.prototype.disable'					=> [CControllerItemPrototypeDisable::class,							'layout.json',			null],
		'item.prototype.edit'						=> [CControllerItemPrototypeEdit::class,							'layout.json',			'item.prototype.edit'],
		'item.prototype.enable'						=> [CControllerItemPrototypeEnable::class,							'layout.json',			null],
		'item.prototype.list'						=> [CControllerItemPrototypeList::class,							'layout.htmlpage',		'item.prototype.list'],
		'item.prototype.massupdate'					=> [CControllerItemMassupdate::class,								'layout.json',			'item.massupdate'],
		'item.prototype.update'						=> [CControllerItemPrototypeUpdate::class,							'layout.json',			null],
		'item.tags.list'							=> [CControllerItemTagsList::class,									'layout.json',			'item.tags.list'],
		'latest.view'								=> [CControllerLatestView::class,									'layout.htmlpage',		'monitoring.latest.view'],
		'latest.view.refresh'						=> [CControllerLatestViewRefresh::class,							'layout.json',			'monitoring.latest.view.refresh'],
		'macros.edit'								=> [CControllerMacrosEdit::class,									'layout.htmlpage',		'administration.macros.edit'],
		'macros.update'								=> [CControllerMacrosUpdate::class,									'layout.json',			null],
		'maintenance.create'						=> [CControllerMaintenanceCreate::class,							'layout.json',			null],
		'maintenance.delete'						=> [CControllerMaintenanceDelete::class,							'layout.json',			null],
		'maintenance.edit'							=> [CControllerMaintenanceEdit::class,								'layout.json',			'maintenance.edit'],
		'maintenance.list'							=> [CControllerMaintenanceList::class,								'layout.htmlpage',		'maintenance.list'],
		'maintenance.timeperiod.edit'				=> [CControllerMaintenanceTimePeriodEdit::class,					'layout.json',			'maintenance.timeperiod.edit'],
		'maintenance.timeperiod.check'				=> [CControllerMaintenanceTimePeriodCheck::class,					'layout.json',			null],
		'maintenance.update'						=> [CControllerMaintenanceUpdate::class,							'layout.json',			null],
		'map.view'									=> [CControllerMapView::class,										'layout.htmlpage',		'monitoring.map.view'],
		'mediatype.create'							=> [CControllerMediatypeCreate::class,								'layout.json',			null],
		'mediatype.delete'							=> [CControllerMediatypeDelete::class,								'layout.json',			null],
		'mediatype.disable'							=> [CControllerMediatypeDisable::class,								'layout.json',			null],
		'mediatype.edit'							=> [CControllerMediatypeEdit::class,								'layout.json',			'mediatype.edit'],
		'mediatype.enable'							=> [CControllerMediatypeEnable::class,								'layout.json',			null],
		'mediatype.list'							=> [CControllerMediatypeList::class,								'layout.htmlpage',		'mediatype.list'],
		'mediatype.message.check'					=> [CControllerMediatypeMessageCheck::class,						'layout.json',			null],
		'mediatype.message.edit'					=> [CControllerMediatypeMessageEdit::class,							'layout.json',			'mediatype.message.edit'],
		'mediatype.test.edit'						=> [CControllerMediatypeTestEdit::class,							'layout.json',			'mediatype.test.edit'],
		'mediatype.test.send'						=> [CControllerMediatypeTestSend::class,							'layout.json',			null],
		'mediatype.update'							=> [CControllerMediatypeUpdate::class,								'layout.json',			null],
		'menu.popup'								=> [CControllerMenuPopup::class,									'layout.json',			null],
		'mfa.edit'									=> [CControllerMfaEdit::class,										'layout.json',			'mfa.edit'],
		'mfa.check'									=> [CControllerMfaCheck::class,										'layout.json',			null],
		'miscconfig.edit'							=> [CControllerMiscConfigEdit::class,								'layout.htmlpage',		'administration.miscconfig.edit'],
		'miscconfig.update'							=> [CControllerMiscConfigUpdate::class,								null,					null],
		'module.disable'							=> [CControllerModuleDisable::class,								'layout.json',			null],
		'module.edit'								=> [CControllerModuleEdit::class,									'layout.json',			'module.edit'],
		'module.enable'								=> [CControllerModuleEnable::class,									'layout.json',			null],
		'module.list'								=> [CControllerModuleList::class,									'layout.htmlpage',		'module.list'],
		'module.scan'								=> [CControllerModuleScan::class,									null,					null],
		'module.update'								=> [CControllerModuleUpdate::class,									'layout.json',			null],
		'notifications.get'							=> [CControllerNotificationsGet::class,								'layout.json',			null],
		'notifications.mute'						=> [CControllerNotificationsMute::class,							'layout.json',			null],
		'notifications.read'						=> [CControllerNotificationsRead::class,							'layout.json',			null],
		'notifications.snooze'						=> [CControllerNotificationsSnooze::class,							'layout.json',			null],
		'oauth.authorize'							=> [CControllerOauthAuthorize::class,								'layout.htmlpage',		'oauth.authorize'],
		'oauth.edit'								=> [CControllerOauthEdit::class,									'layout.json',			'oauth.edit'],
		'oauth.check'								=> [CControllerOauthCheck::class,									'layout.json',			null],
		'popup'										=> [CControllerPopup::class,										'layout.htmlpage',		'popup.view'],
		'popup.acknowledge.create'					=> [CControllerPopupAcknowledgeCreate::class,						'layout.json',			null],
		'popup.action.operation.edit'				=> [CControllerPopupActionOperationEdit::class,						'layout.json',			'popup.operation.edit'],
		'popup.action.operations.list'				=> [CControllerPopupActionOperationsList::class,					'layout.json',			'popup.action.operations.list'],
		'popup.condition.check'						=> [CControllerActionConditionCheck::class,							'layout.json',			null],
		'popup.condition.edit'						=> [CControllerPopupActionConditionEdit::class,						'layout.json',			'popup.condition.edit'],
		'popup.condition.operations'				=> [CControllerPopupConditionOperations::class,						'layout.json',			'popup.condition.edit'],
		'popup.dashboard.share.edit'				=> [CControllerPopupDashboardShareEdit::class,						'layout.json',			'popup.dashboard.share.edit'],
		'popup.generic'								=> [CControllerPopupGeneric::class,									'layout.json',			'popup.generic'],
		'popup.import'								=> [CControllerPopupImport::class,									'layout.json',			'popup.import'],
		'popup.import.compare'						=> [CControllerPopupImportCompare::class,							'layout.json',			'popup.import.compare'],
		'popup.itemtest.edit'						=> [CControllerPopupItemTestEdit::class,							'layout.json',			'popup.itemtestedit.view'],
		'popup.itemtest.getvalue'					=> [CControllerPopupItemTestGetValue::class,						'layout.json',			null],
		'popup.itemtest.send'						=> [CControllerPopupItemTestSend::class,							'layout.json',			null],
		'popup.ldap.check'							=> [CControllerPopupLdapCheck::class,								'layout.json',			null],
		'popup.ldap.edit'							=> [CControllerPopupLdapEdit::class,								'layout.json',			'popup.ldap.edit'],
		'popup.ldap.test.edit'						=> [CControllerPopupLdapTestEdit::class,							'layout.json',			'popup.ldap.test.edit'],
		'popup.ldap.test.send'						=> [CControllerPopupLdapTestSend::class,							'layout.json',			null],
		'popup.lldoperation'						=> [CControllerPopupLldOperation::class,							'layout.json',			'popup.lldoperation'],
		'popup.lldoverride'							=> [CControllerPopupLldOverride::class,								'layout.json',			'popup.lldoverride'],
		'popup.massupdate.host'						=> [CControllerPopupMassupdateHost::class,							'layout.json',			'popup.massupdate.host'],
		'popup.massupdate.service'					=> [CControllerPopupMassupdateService::class,						'layout.json',			'popup.massupdate.service'],
		'popup.media.check'							=> [CControllerPopupMediaCheck::class,								'layout.json',			null],
		'popup.media.edit'							=> [CControllerPopupMediaEdit::class,								'layout.json',			'popup.media.edit'],
		'popup.mediatypemapping.check'				=> [CControllerPopupMediaTypeMappingCheck::class,					'layout.json',			null],
		'popup.mediatypemapping.edit'				=> [CControllerPopupMediaTypeMappingEdit::class,					'layout.json',			'popup.mediatypemapping.edit'],
		'popup.usergroupmapping.check'				=> [CControllerPopupUserGroupMappingCheck::class,					'layout.json',			null],
		'popup.usergroupmapping.edit'				=> [CControllerPopupUserGroupMappingEdit::class,					'layout.json',			'popup.usergroupmapping.edit'],
		'popup.scheduledreport.create'				=> [CControllerPopupScheduledReportCreate::class,					'layout.json',			null],
		'popup.scheduledreport.edit'				=> [CControllerPopupScheduledReportEdit::class,						'layout.json',			'popup.scheduledreport.edit'],
		'popup.scheduledreport.list'				=> [CControllerPopupScheduledReportList::class,						'layout.json',			'popup.scheduledreport.list'],
		'popup.scheduledreport.subscription.edit'	=> [CControllerPopupScheduledReportSubscriptionEdit::class,			'layout.json',			'popup.scheduledreport.subscription'],
		'popup.scheduledreport.test'				=> [CControllerPopupScheduledReportTest::class,						'layout.json',			'popup.scheduledreport.test'],
		'popup.scriptexec'							=> [CControllerPopupScriptExec::class,								'layout.json',			'popup.scriptexec'],
		'popup.service.statusrule.edit'				=> [CControllerPopupServiceStatusRuleEdit::class,					'layout.json',			'popup.service.statusrule.edit'],
		'popup.services'							=> [CControllerPopupServices::class,								'layout.json',			'popup.services'],
		'popup.sla.excludeddowntime.edit'			=> [CControllerPopupSlaExcludedDowntimeEdit::class,					'layout.json',			'popup.sla.excludeddowntime.edit'],
		'popup.tabfilter.delete'					=> [CControllerPopupTabFilterDelete::class,							'layout.json',			null],
		'popup.tabfilter.edit'						=> [CControllerPopupTabFilterEdit::class,							'layout.json',			'popup.tabfilter.edit'],
		'popup.tabfilter.update'					=> [CControllerPopupTabFilterUpdate::class,							'layout.json',			null],
		'popup.testtriggerexpr'						=> [CControllerPopupTestTriggerExpr::class,							'layout.json',			'popup.testtriggerexpr'],
		'popup.token.view'							=> [CControllerPopupTokenView::class,								'layout.json',			'popup.token.view'],
		'popup.triggerexpr'							=> [CControllerPopupTriggerExpr::class,								'layout.json',			'popup.triggerexpr'],
		'popup.valuemap.edit'						=> [CControllerValueMapEdit::class,									'layout.json',			'popup.valuemap.edit'],
		'popup.valuemap.check'						=> [CControllerValueMapCheck::class,								'layout.json',			null],
		'problem.view'								=> [CControllerProblemView::class,									'layout.htmlpage',		'monitoring.problem.view'],
		'problem.view.csv'							=> [CControllerProblemView::class,									'layout.csv',			'monitoring.problem.view'],
		'problem.view.refresh'						=> [CControllerProblemViewRefresh::class,							'layout.json',			'monitoring.problem.view.refresh'],
		'profile.update'							=> [CControllerProfileUpdate::class,								'layout.json',			null],
		'proxy.config.refresh'						=> [CControllerProxyConfigRefresh::class,							'layout.json',			null],
		'proxy.create'								=> [CControllerProxyCreate::class,									'layout.json',			null],
		'proxy.delete'								=> [CControllerProxyDelete::class,									'layout.json',			null],
		'proxy.edit'								=> [CControllerProxyEdit::class,									'layout.json',			'proxy.edit'],
		'proxy.host.disable'						=> [CControllerProxyHostDisable::class,								'layout.json',			null],
		'proxy.host.enable'							=> [CControllerProxyHostEnable::class,								'layout.json',			null],
		'proxy.list'								=> [CControllerProxyList::class,									'layout.htmlpage',		'administration.proxy.list'],
		'proxy.update'								=> [CControllerProxyUpdate::class,									'layout.json',			null],
		'proxygroup.create'							=> [CControllerProxyGroupCreate::class,								'layout.json',			null],
		'proxygroup.delete'							=> [CControllerProxyGroupDelete::class,								'layout.json',			null],
		'proxygroup.edit'							=> [CControllerProxyGroupEdit::class,								'layout.json',			'proxygroup.edit'],
		'proxygroup.list'							=> [CControllerProxyGroupList::class,								'layout.htmlpage',		'administration.proxygroup.list'],
		'proxygroup.update'							=> [CControllerProxyGroupUpdate::class,								'layout.json',			null],
		'queue.details'								=> [CControllerQueueDetails::class,									'layout.htmlpage',		'administration.queue.details'],
		'queue.overview'							=> [CControllerQueueOverview::class,								'layout.htmlpage',		'administration.queue.overview'],
		'queue.overview.proxy'						=> [CControllerQueueOverviewProxy::class,							'layout.htmlpage',		'administration.queue.overview.proxy'],
		'regex.create'								=> [CControllerRegExCreate::class,									'layout.json',			null],
		'regex.delete'								=> [CControllerRegExDelete::class,									null,					null],
		'regex.edit'								=> [CControllerRegExEdit::class,									'layout.htmlpage',		'administration.regex.edit'],
		'regex.list'								=> [CControllerRegExList::class,									'layout.htmlpage',		'administration.regex.list'],
		'regex.test'								=> [CControllerRegExTest::class,									'layout.json',			null],
		'regex.update'								=> [CControllerRegExUpdate::class,									'layout.json',			null],
		'report.status'								=> [CControllerReportStatus::class,									'layout.htmlpage',		'report.status'],
		'scheduledreport.create'					=> [CControllerScheduledReportCreate::class,						null,					null],
		'scheduledreport.delete'					=> [CControllerScheduledReportDelete::class,						null,					null],
		'scheduledreport.disable'					=> [CControllerScheduledReportDisable::class,						null,					null],
		'scheduledreport.edit'						=> [CControllerScheduledReportEdit::class,							'layout.htmlpage',		'reports.scheduledreport.edit'],
		'scheduledreport.enable'					=> [CControllerScheduledReportEnable::class,						null,					null],
		'scheduledreport.list'						=> [CControllerScheduledReportList::class,							'layout.htmlpage',		'reports.scheduledreport.list'],
		'scheduledreport.update'					=> [CControllerScheduledReportUpdate::class,						null,					null],
		'script.create'								=> [CControllerScriptCreate::class,									'layout.json',			null],
		'script.delete'								=> [CControllerScriptDelete::class,									'layout.json',			null],
		'script.edit'								=> [CControllerScriptEdit::class,									'layout.json',			'administration.script.edit'],
		'script.list'								=> [CControllerScriptList::class,									'layout.htmlpage',		'administration.script.list'],
		'script.update'								=> [CControllerScriptUpdate::class,									'layout.json',			null],
		'script.userinput.edit'						=> [CControllerScriptUserInputEdit::class,							'layout.json',			'script.userinput.edit'],
		'script.userinput.check'					=> [CControllerScriptUserInputCheck::class,							'layout.json',			null],
		'search'									=> [CControllerSearch::class,										'layout.htmlpage',		'search'],
		'service.create'							=> [CControllerServiceCreate::class,								'layout.json',			null],
		'service.delete'							=> [CControllerServiceDelete::class,								'layout.json',			null],
		'service.edit'								=> [CControllerServiceEdit::class,									'layout.json',			'service.edit'],
		'service.list'								=> [CControllerServiceList::class,									'layout.htmlpage',		'service.list'],
		'service.list.edit'							=> [CControllerServiceListEdit::class,								'layout.htmlpage',		'service.list.edit'],
		'service.list.edit.refresh'					=> [CControllerServiceListEditRefresh::class,						'layout.json',			'service.list.edit.refresh'],
		'service.list.refresh'						=> [CControllerServiceListRefresh::class,							'layout.json',			'service.list.refresh'],
		'service.statusrule.validate'				=> [CControllerServiceStatusRuleValidate::class,					'layout.json',			null],
		'service.update'							=> [CControllerServiceUpdate::class,								'layout.json',			null],
		'sla.create'								=> [CControllerSlaCreate::class,									'layout.json',			null],
		'sla.delete'								=> [CControllerSlaDelete::class,									'layout.json',			null],
		'sla.disable'								=> [CControllerSlaDisable::class,									'layout.json',			null],
		'sla.edit'									=> [CControllerSlaEdit::class,										'layout.json',			'sla.edit'],
		'sla.enable'								=> [CControllerSlaEnable::class,									'layout.json',			null],
		'sla.excludeddowntime.validate'				=> [CControllerSlaExcludedDowntimeValidate::class,					'layout.json',			null],
		'sla.list'									=> [CControllerSlaList::class,										'layout.htmlpage',		'sla.list'],
		'sla.update'								=> [CControllerSlaUpdate::class,									'layout.json',			null],
		'slareport.list'							=> [CControllerSlaReportList::class,								'layout.htmlpage',		'slareport.list'],
		'softwareversioncheck.get'					=> [CControllerSoftwareVersionCheckGet::class,						'layout.json',			null],
		'softwareversioncheck.update'				=> [CControllerSoftwareVersionCheckUpdate::class,					'layout.json',			null],
		'system.warning'							=> [CControllerSystemWarning::class,								'layout.warning',		'system.warning'],
		'tabfilter.profile.update'					=> [CControllerTabFilterProfileUpdate::class,						'layout.json',			null],
		'template.create'							=> [CControllerTemplateCreate::class,								'layout.json',			null],
		'template.dashboard.delete'					=> [CControllerTemplateDashboardDelete::class,						null,					null],
		'template.dashboard.edit'					=> [CControllerTemplateDashboardEdit::class,						'layout.htmlpage',		'configuration.dashboard.edit'],
		'template.dashboard.list'					=> [CControllerTemplateDashboardList::class,						'layout.htmlpage',		'configuration.dashboard.list'],
		'template.dashboard.update'					=> [CControllerTemplateDashboardUpdate::class,						'layout.json',			null],
		'template.delete'							=> [CControllerTemplateDelete::class,								'layout.json',			null],
		'template.edit'								=> [CControllerTemplateEdit::class,									'layout.json',			'template.edit'],
		'template.list'								=> [CControllerTemplateList::class,									'layout.htmlpage',		'template.list'],
		'template.massupdate'						=> [CControllerTemplateMassupdate::class,							'layout.json',			'template.massupdate'],
		'template.update'							=> [CControllerTemplateUpdate::class,								'layout.json',			null],
		'templategroup.create'						=> [CControllerTemplateGroupCreate::class,							'layout.json',			null],
		'templategroup.delete'						=> [CControllerTemplateGroupDelete::class,							'layout.json',			null],
		'templategroup.edit'						=> [CControllerTemplateGroupEdit::class,							'layout.json',			'templategroup.edit'],
		'templategroup.list'						=> [CControllerTemplateGroupList::class,							'layout.htmlpage',		'configuration.templategroup.list'],
		'templategroup.update'						=> [CControllerTemplateGroupUpdate::class,							'layout.json',			null],
		'timeouts.edit'								=> [CControllerTimeoutsEdit::class,									'layout.htmlpage',		'administration.timeouts.edit'],
		'timeouts.update'							=> [CControllerTimeoutsUpdate::class,								null,					null],
		'timeselector.calc'							=> [CControllerTimeSelectorCalc::class,								'layout.json',			null],
		'timeselector.update'						=> [CControllerTimeSelectorUpdate::class,							'layout.json',			null],
		'token.create'								=> [CControllerTokenCreate::class,									'layout.json',			null],
		'token.delete'								=> [CControllerTokenDelete::class,									'layout.json',			null],
		'token.disable'								=> [CControllerTokenDisable::class,									null,					null],
		'token.edit'								=> [CControllerTokenEdit::class,									'layout.json',			'token.edit'],
		'token.enable'								=> [CControllerTokenEnable::class,									null,					null],
		'token.list'								=> [CControllerTokenList::class,									'layout.htmlpage',		'administration.token.list'],
		'token.update'								=> [CControllerTokenUpdate::class,									'layout.json',			null],
		'toptriggers.list'							=> [CControllerTopTriggersList::class,								'layout.htmlpage',		'reports.toptriggers.list'],
		'trigdisplay.edit'							=> [CControllerTrigDisplayEdit::class,								'layout.htmlpage',		'administration.trigdisplay.edit'],
		'trigdisplay.update'						=> [CControllerTrigDisplayUpdate::class,							null,					null],
		'trigger.create'							=> [CControllerTriggerCreate::class,								'layout.json',			null],
		'trigger.delete'							=> [CControllerTriggerDelete::class,								'layout.json',			null],
		'trigger.disable'							=> [CControllerTriggerDisable::class,								'layout.json',			null],
		'trigger.edit'								=> [CControllerTriggerEdit::class,									'layout.json',			'trigger.edit'],
		'trigger.enable'							=> [CControllerTriggerEnable::class,								'layout.json',			null],
		'trigger.expression.constructor'			=> [CControllerTriggerExpressionConstructor::class,					'layout.json',			'trigger.expression.constructor'],
		'trigger.list'								=> [CControllerTriggerList::class,									'layout.htmlpage',		'trigger.list'],
		'trigger.massupdate'						=> [CControllerTriggerMassupdate::class,							'layout.json',			'trigger.massupdate'],
		'trigger.prototype.create'					=> [CControllerTriggerPrototypeCreate::class,						'layout.json',			null],
		'trigger.prototype.delete'					=> [CControllerTriggerPrototypeDelete::class,						'layout.json',			null],
		'trigger.prototype.disable'					=> [CControllerTriggerPrototypeDisable::class,						'layout.json',			null],
		'trigger.prototype.edit'					=> [CControllerTriggerPrototypeEdit::class,							'layout.json',			'trigger.prototype.edit'],
		'trigger.prototype.enable'					=> [CControllerTriggerPrototypeEnable::class,						'layout.json',			null],
		'trigger.prototype.list'					=> [CControllerTriggerPrototypeList::class,							'layout.htmlpage',		'trigger.prototype.list'],
		'trigger.prototype.massupdate'				=> [CControllerTriggerMassupdate::class,							'layout.json',			'trigger.massupdate'],
		'trigger.prototype.update'					=> [CControllerTriggerPrototypeUpdate::class,						'layout.json',			null],
		'trigger.update'							=> [CControllerTriggerUpdate::class,								'layout.json',			null],
		'user.create'								=> [CControllerUserCreate::class,									null,					null],
		'user.delete'								=> [CControllerUserDelete::class,									null,					null],
		'user.edit'									=> [CControllerUserEdit::class,										'layout.htmlpage',		'administration.user.edit'],
		'user.list'									=> [CControllerUserList::class,										'layout.htmlpage',		'administration.user.list'],
		'user.token.list'							=> [CControllerUserTokenList::class,								'layout.htmlpage',		'administration.user.token.list'],
		'user.unblock'								=> [CControllerUserUnblock::class,									null,					null],
		'user.update'								=> [CControllerUserUpdate::class,									null,					null],
		'user.provision'							=> [CControllerUserProvision::class,								null,					null],
		'user.reset.totp'							=> [CControllerUserResetTotp::class,								null,					null],
		'usergroup.create'							=> [CControllerUsergroupCreate::class,								null,					null],
		'usergroup.delete'							=> [CControllerUsergroupDelete::class,								null,					null],
		'usergroup.edit'							=> [CControllerUsergroupEdit::class,								'layout.htmlpage',		'usergroup.edit'],
		'usergroup.list'							=> [CControllerUsergroupList::class,								'layout.htmlpage',		'usergroup.list'],
		'usergroup.massupdate'						=> [CControllerUsergroupMassUpdate::class,							null,					null],
		'usergroup.tagfilter.edit'					=> [CControllerUsergroupTagFilterEdit::class,						'layout.json',			'usergroup.tagfilter.edit'],
		'usergroup.tagfilter.check'					=> [CControllerUsergroupTagFilterCheck::class,						'layout.json',			null],
		'usergroup.tagfilter.list'					=> [CControllerUsergroupTagFilterList::class,						'layout.json',			'usergroup.tagfilter.list'],
		'usergroup.update'							=> [CControllerUsergroupUpdate::class,								null,					null],
		'userprofile.edit'							=> [CControllerUserProfileEdit::class,								'layout.htmlpage',		'userprofile.edit'],
		'userprofile.update'						=> [CControllerUserProfileUpdate::class,							null,					null],
		'userprofile.notification.edit'				=> [CControllerUserProfileNotificationEdit::class,					'layout.htmlpage',		'userprofile.notification.edit'],
		'userprofile.notification.update'			=> [CControllerUserProfileNotificationUpdate::class,				null,					null],
		'userrole.create'							=> [CControllerUserroleCreate::class,								null,					null],
		'userrole.delete'							=> [CControllerUserroleDelete::class,								null,					null],
		'userrole.edit'								=> [CControllerUserroleEdit::class,									'layout.htmlpage',		'administration.userrole.edit'],
		'userrole.list'								=> [CControllerUserroleList::class,									'layout.htmlpage',		'administration.userrole.list'],
		'userrole.update'							=> [CControllerUserroleUpdate::class,								null,					null],
		'validate'									=> [CControllerValidate::class,										'layout.json',			null],
		'validate.api.exists'						=> [CControllerValidateApiExists::class, 							'layout.json',			null],
		'web.view'									=> [CControllerWebView::class,										'layout.htmlpage',		'monitoring.web.view'],
		'webscenario.step.check'					=> [CControllerWebScenarioStepCheck::class,							'layout.json',			null],
		'webscenario.step.edit'						=> [CControllerWebScenarioStepEdit::class,							'layout.json',			'webscenario.step.edit'],
		'widget.navigation.tree.toggle'				=> [CControllerWidgetNavigationTreeToggle::class,					'layout.json',			null],

		// legacy actions
		'auditacts.php'					=> [CLegacyAction::class, null, null],
		'browserwarning.php'			=> [CLegacyAction::class, null, null],
		'chart.php'						=> [CLegacyAction::class, null, null],
		'chart2.php'					=> [CLegacyAction::class, null, null],
		'chart3.php'					=> [CLegacyAction::class, null, null],
		'chart4.php'					=> [CLegacyAction::class, null, null],
		'chart6.php'					=> [CLegacyAction::class, null, null],
		'chart7.php'					=> [CLegacyAction::class, null, null],
		'history.php'					=> [CLegacyAction::class, null, null],
		'host_discovery.php'			=> [CLegacyAction::class, null, null],
		'host_discovery_prototypes.php'	=> [CLegacyAction::class, null, null],
		'hostinventories.php'			=> [CLegacyAction::class, null, null],
		'hostinventoriesoverview.php'	=> [CLegacyAction::class, null, null],
		'httpconf.php'					=> [CLegacyAction::class, null, null],
		'httpdetails.php'				=> [CLegacyAction::class, null, null],
		'image.php'						=> [CLegacyAction::class, null, null],
		'imgstore.php'					=> [CLegacyAction::class, null, null],
		'index.php'						=> [CLegacyAction::class, null, null],
		'index_http.php'				=> [CLegacyAction::class, null, null],
		'index_mfa.php'					=> [CLegacyAction::class, null, null],
		'index_sso.php'					=> [CLegacyAction::class, null, null],
		'jsrpc.php'						=> [CLegacyAction::class, null, null],
		'map.php'						=> [CLegacyAction::class, null, null],
		'report4.php'					=> [CLegacyAction::class, null, null],
		'sysmap.php'					=> [CLegacyAction::class, null, null],
		'sysmaps.php'					=> [CLegacyAction::class, null, null],
		'tr_events.php'					=> [CLegacyAction::class, null, null]
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
