<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


final class CRouter {

	private static ?self $instance = null;

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

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
		// Action									Controller															Layout					View
		'acknowledge.edit'							=> [CControllerAcknowledgeEdit::class,							ZBX_LAYOUT_JSON,		'acknowledge.edit'],
		'acknowledge.rank.change'					=> [CControllerAcknowledgeRankChange::class,					ZBX_LAYOUT_JSON,		null],
		'action.create'								=> [CControllerActionCreate::class,								ZBX_LAYOUT_JSON,		null],
		'action.delete'								=> [CControllerActionDelete::class,								ZBX_LAYOUT_JSON,		null],
		'action.disable'							=> [CControllerActionDisable::class,							ZBX_LAYOUT_JSON,		null],
		'action.edit'								=> [CControllerActionEdit::class,								ZBX_LAYOUT_JSON,		'action.edit'],
		'action.enable'								=> [CControllerActionEnable::class,								ZBX_LAYOUT_JSON,		null],
		'action.list'								=> [CControllerActionList::class,								ZBX_LAYOUT_HTMLPAGE,	'action.list'],
		'action.operation.check'					=> [CControllerActionOperationCheck::class,						ZBX_LAYOUT_JSON,		null],
		'action.operation.condition.check'			=> [CControllerActionOperationConditionCheck::class,			ZBX_LAYOUT_JSON,		null],
		'action.update'								=> [CControllerActionUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'actionlog.csv'								=> [CControllerActionLogList::class,							ZBX_LAYOUT_DOWNLOAD,	'reports.actionlog.list.csv'],
		'actionlog.list'							=> [CControllerActionLogList::class,							ZBX_LAYOUT_HTMLPAGE,	'reports.actionlog.list'],
		'audit.settings.edit'						=> [CControllerAuditSettingsEdit::class,						ZBX_LAYOUT_HTMLPAGE,	'administration.audit.settings.edit'],
		'audit.settings.update'						=> [CControllerAuditSettingsUpdate::class, 						ZBX_LAYOUT_JSON,		null],
		'auditlog.csv'								=> [CControllerAuditLogList::class,								ZBX_LAYOUT_DOWNLOAD,	'reports.auditlog.list.csv'],
		'auditlog.list'								=> [CControllerAuditLogList::class,								ZBX_LAYOUT_HTMLPAGE,	'reports.auditlog.list'],
		'authentication.edit'						=> [CControllerAuthenticationEdit::class,						ZBX_LAYOUT_HTMLPAGE,	'administration.authentication.edit'],
		'authentication.update'						=> [CControllerAuthenticationUpdate::class, 					ZBX_LAYOUT_JSON,		null],
		'autoreg.edit'								=> [CControllerAutoregEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.autoreg.edit'],
		'autoreg.update'							=> [CControllerAutoregUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'availabilityreport.list'					=> [CControllerAvailabilityReportList::class,					ZBX_LAYOUT_HTMLPAGE,	'reports.availabilityreport.list'],
		'availabilityreport.trigger'				=> [CControllerAvailabilityReportTrigger::class,				ZBX_LAYOUT_HTMLPAGE,	'reports.availabilityreport.trigger'],
		'banner.get'								=> [CControllerBannerGet::class,								ZBX_LAYOUT_JSON,		null],
		'banner.update'								=> [CControllerBannerUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'charts.view'								=> [CControllerChartsView::class,								ZBX_LAYOUT_HTMLPAGE,	'monitoring.charts.view'],
		'charts.view.json'							=> [CControllerChartsViewJson::class,							ZBX_LAYOUT_JSON,		'monitoring.charts.view.json'],
		'connector.create'							=> [CControllerConnectorCreate::class,							ZBX_LAYOUT_JSON,		null],
		'connector.delete'							=> [CControllerConnectorDelete::class,							ZBX_LAYOUT_JSON,		null],
		'connector.disable'							=> [CControllerConnectorDisable::class,							ZBX_LAYOUT_JSON,		null],
		'connector.edit'							=> [CControllerConnectorEdit::class,							ZBX_LAYOUT_JSON,		'connector.edit'],
		'connector.enable'							=> [CControllerConnectorEnable::class,							ZBX_LAYOUT_JSON,		null],
		'connector.list'							=> [CControllerConnectorList::class,							ZBX_LAYOUT_HTMLPAGE,	'connector.list'],
		'connector.update'							=> [CControllerConnectorUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'copy.create'								=> [CControllerCopyCreate::class,								ZBX_LAYOUT_JSON,		null],
		'copy.edit'									=> [CControllerCopyEdit::class,									ZBX_LAYOUT_JSON,		'copy.edit'],
		'correlation.condition.check'				=> [CControllerCorrelationConditionCheck::class,				ZBX_LAYOUT_JSON,		null],
		'correlation.condition.edit'				=> [CControllerCorrelationConditionEdit::class,					ZBX_LAYOUT_JSON,		'correlation.condition.edit'],
		'correlation.create'						=> [CControllerCorrelationCreate::class,						ZBX_LAYOUT_JSON,		null],
		'correlation.delete'						=> [CControllerCorrelationDelete::class,						ZBX_LAYOUT_JSON,		null],
		'correlation.disable'						=> [CControllerCorrelationDisable::class,						ZBX_LAYOUT_JSON,		null],
		'correlation.edit'							=> [CControllerCorrelationEdit::class,							ZBX_LAYOUT_JSON,		'correlation.edit'],
		'correlation.enable'						=> [CControllerCorrelationEnable::class,						ZBX_LAYOUT_JSON,		null],
		'correlation.list'							=> [CControllerCorrelationList::class,							ZBX_LAYOUT_HTMLPAGE,	'correlation.list'],
		'correlation.update'						=> [CControllerCorrelationUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'dashboard.config.hash'						=> [CControllerDashboardConfigHash::class,						ZBX_LAYOUT_JSON,		null],
		'dashboard.delete'							=> [CControllerDashboardDelete::class,							null,					null],
		'dashboard.list'							=> [CControllerDashboardList::class,							ZBX_LAYOUT_HTMLPAGE,	'monitoring.dashboard.list'],
		'dashboard.page.properties.check'			=> [CControllerDashboardPagePropertiesCheck::class,				ZBX_LAYOUT_JSON,		null],
		'dashboard.page.properties.edit'			=> [CControllerDashboardPagePropertiesEdit::class,				ZBX_LAYOUT_JSON,		'dashboard.page.properties.edit'],
		'dashboard.print'							=> [CControllerDashboardPrint::class,							ZBX_LAYOUT_PRINT,		'monitoring.dashboard.print'],
		'dashboard.properties.check'				=> [CControllerDashboardPropertiesCheck::class,					ZBX_LAYOUT_JSON,		null],
		'dashboard.properties.edit'					=> [CControllerDashboardPropertiesEdit::class,					ZBX_LAYOUT_JSON,		'dashboard.properties.edit'],
		'dashboard.share.update'					=> [CControllerDashboardShareUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'dashboard.update'							=> [CControllerDashboardUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'dashboard.view'							=> [CControllerDashboardView::class,							ZBX_LAYOUT_HTMLPAGE,	'monitoring.dashboard.view'],
		'dashboard.widget.check'					=> [CControllerDashboardWidgetCheck::class,						ZBX_LAYOUT_JSON,		null],
		'dashboard.widget.rfrate'					=> [CControllerDashboardWidgetRfRate::class,					ZBX_LAYOUT_JSON,		null],
		'dashboard.widgets.validate'				=> [CControllerDashboardWidgetsValidate::class,					ZBX_LAYOUT_JSON,		null],
		'discovery.check.check'						=> [CControllerDiscoveryCheckCheck::class,						ZBX_LAYOUT_JSON,		null],
		'discovery.check.edit'						=> [CControllerDiscoveryCheckEdit::class,						ZBX_LAYOUT_JSON,		'discovery.check.edit'],
		'discovery.create'							=> [CControllerDiscoveryCreate::class,							ZBX_LAYOUT_JSON,		null],
		'discovery.delete'							=> [CControllerDiscoveryDelete::class,							ZBX_LAYOUT_JSON,		null],
		'discovery.disable'							=> [CControllerDiscoveryDisable::class,							ZBX_LAYOUT_JSON,		null],
		'discovery.edit'							=> [CControllerDiscoveryEdit::class,							ZBX_LAYOUT_JSON,		'configuration.discovery.edit'],
		'discovery.enable'							=> [CControllerDiscoveryEnable::class,							ZBX_LAYOUT_JSON,		null],
		'discovery.list'							=> [CControllerDiscoveryList::class,							ZBX_LAYOUT_HTMLPAGE,	'configuration.discovery.list'],
		'discovery.update'							=> [CControllerDiscoveryUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'discovery.view'							=> [CControllerDiscoveryView::class,							ZBX_LAYOUT_HTMLPAGE,	'monitoring.discovery.view'],
		'export.hosts'								=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'export.mediatypes'							=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'export.sysmaps'							=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'export.templates'							=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'export.dashboards'							=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'export.regexes'							=> [CControllerExport::class,									ZBX_LAYOUT_DOWNLOAD,	null],
		'favorite.create'							=> [CControllerFavoriteCreate::class,							ZBX_LAYOUT_JAVASCRIPT,	null],
		'favorite.delete'							=> [CControllerFavoriteDelete::class,							ZBX_LAYOUT_JAVASCRIPT,	null],
		'geomaps.edit'								=> [CControllerGeomapsEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.geomaps.edit'],
		'geomaps.update'							=> [CControllerGeomapsUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'gui.edit'									=> [CControllerGuiEdit::class,									ZBX_LAYOUT_HTMLPAGE,	'administration.gui.edit'],
		'gui.update'								=> [CControllerGuiUpdate::class, 								ZBX_LAYOUT_JSON,		null],
		'graph.edit'								=> [CControllerGraphEdit::class,								ZBX_LAYOUT_JSON,		'graph.edit'],
		'graph.create'								=> [CControllerGraphCreate::class,								ZBX_LAYOUT_JSON,		null],
		'graph.delete'								=> [CControllerGraphDelete::class,								ZBX_LAYOUT_JSON,		null],
		'graph.list'								=> [CControllerGraphList::class,								ZBX_LAYOUT_HTMLPAGE,	'graph.list'],
		'graph.update'								=> [CControllerGraphUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'graph.prototype.create'					=> [CControllerGraphPrototypeCreate::class,						ZBX_LAYOUT_JSON,		null],
		'graph.prototype.delete'					=> [CControllerGraphPrototypeDelete::class,						ZBX_LAYOUT_JSON,		null],
		'graph.prototype.edit'						=> [CControllerGraphPrototypeEdit::class,						ZBX_LAYOUT_JSON,		'graph.prototype.edit'],
		'graph.prototype.list'						=> [CControllerGraphPrototypeList::class,						ZBX_LAYOUT_HTMLPAGE,	'graph.prototype.list'],
		'graph.prototype.update'					=> [CControllerGraphPrototypeUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'graph.prototype.updatediscover'			=> [CControllerGraphPrototypeUpdateDiscover::class,				ZBX_LAYOUT_JSON,		null],
		'hintbox.actionlist'						=> [CControllerHintboxActionlist::class,						ZBX_LAYOUT_JSON,		'hintbox.actionlist'],
		'hintbox.eventlist'							=> [CControllerHintboxEventlist::class,							ZBX_LAYOUT_JSON,		'hintbox.eventlist'],
		'host.create'								=> [CControllerHostCreate::class,								ZBX_LAYOUT_JSON,		null],
		'host.dashboard.view'						=> [CControllerHostDashboardView::class,						ZBX_LAYOUT_HTMLPAGE,	'monitoring.host.dashboard.view'],
		'host.disable'								=> [CControllerHostDisable::class,								ZBX_LAYOUT_JSON,		null],
		'host.edit'									=> [CControllerHostEdit::class,									ZBX_LAYOUT_JSON,		'host.edit'],
		'host.enable'								=> [CControllerHostEnable::class,								ZBX_LAYOUT_JSON,		null],
		'host.list'									=> [CControllerHostList::class,									ZBX_LAYOUT_HTMLPAGE,	'configuration.host.list'],
		'host.list.data'							=> [CControllerHostListData::class,								ZBX_LAYOUT_JSON,		null],
		'host.massdelete'							=> [CControllerHostMassDelete::class,							ZBX_LAYOUT_JSON,		null],
		'host.tags.list'							=> [CControllerHostTagsList::class,								ZBX_LAYOUT_JSON,		'host.tags.list'],
		'host.update'								=> [CControllerHostUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'host.view'									=> [CControllerHostView::class,									ZBX_LAYOUT_HTMLPAGE,	'monitoring.host.view'],
		'host.view.data'							=> [CControllerHostViewData::class,								ZBX_LAYOUT_JSON,		null],
		'host.prototype.create'						=> [CControllerHostPrototypeCreate::class,						ZBX_LAYOUT_JSON,		null],
		'host.prototype.delete'						=> [CControllerHostPrototypeDelete::class,						ZBX_LAYOUT_JSON,		null],
		'host.prototype.edit'						=> [CControllerHostPrototypeEdit::class,						ZBX_LAYOUT_JSON,		'host.prototype.edit'],
		'host.prototype.enable'						=> [CControllerHostPrototypeEnable::class,						ZBX_LAYOUT_JSON,		null],
		'host.prototype.list'						=> [CControllerHostPrototypeList::class,						ZBX_LAYOUT_HTMLPAGE,	'host.prototype.list'],
		'host.prototype.update'						=> [CControllerHostPrototypeUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'host.prototype.disable'					=> [CControllerHostPrototypeDisable::class,						ZBX_LAYOUT_JSON,		null],
		'host.wizard.create'						=> [CControllerHostWizardCreate::class,							ZBX_LAYOUT_JSON,		null],
		'host.wizard.edit'							=> [CControllerHostWizardEdit::class,							ZBX_LAYOUT_JSON,		'host.wizard.edit'],
		'host.wizard.get'							=> [CControllerHostWizardGet::class,							ZBX_LAYOUT_JSON,		null],
		'host.wizard.update'						=> [CControllerHostWizardUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'hostgroup.create'							=> [CControllerHostGroupCreate::class,							ZBX_LAYOUT_JSON,		null],
		'hostgroup.delete'							=> [CControllerHostGroupDelete::class,							ZBX_LAYOUT_JSON,		null],
		'hostgroup.disable'							=> [CControllerHostGroupDisable::class,							ZBX_LAYOUT_JSON,		null],
		'hostgroup.edit'							=> [CControllerHostGroupEdit::class,							ZBX_LAYOUT_JSON,		'hostgroup.edit'],
		'hostgroup.enable'							=> [CControllerHostGroupEnable::class,							ZBX_LAYOUT_JSON,		null],
		'hostgroup.list'							=> [CControllerHostGroupList::class,							ZBX_LAYOUT_HTMLPAGE,	'configuration.hostgroup.list'],
		'hostgroup.update'							=> [CControllerHostGroupUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'hostmacros.list'							=> [CControllerHostMacrosList::class,							ZBX_LAYOUT_JSON,		'hostmacros.list'],
		'housekeeping.edit'							=> [CControllerHousekeepingEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.housekeeping.edit'],
		'housekeeping.update'						=> [CControllerHousekeepingUpdate::class, 						ZBX_LAYOUT_JSON,		null],
		'iconmap.create'							=> [CControllerIconMapCreate::class,							ZBX_LAYOUT_JSON,		null],
		'iconmap.delete'							=> [CControllerIconMapDelete::class,							null,					null],
		'iconmap.edit'								=> [CControllerIconMapEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.iconmap.edit'],
		'iconmap.list'								=> [CControllerIconMapList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.iconmap.list'],
		'iconmap.update'							=> [CControllerIconMapUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'image.create'								=> [CControllerImageCreate::class,								ZBX_LAYOUT_JSON,		null],
		'image.delete'								=> [CControllerImageDelete::class,								null,					null],
		'image.edit'								=> [CControllerImageEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.image.edit'],
		'image.list'								=> [CControllerImageList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.image.list'],
		'image.update'								=> [CControllerImageUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'item.clear'								=> [CControllerItemClear::class,								ZBX_LAYOUT_JSON,		null],
		'item.create'								=> [CControllerItemCreate::class,								ZBX_LAYOUT_JSON,		null],
		'item.delete'								=> [CControllerItemDelete::class,								ZBX_LAYOUT_JSON,		null],
		'item.disable'								=> [CControllerItemDisable::class,								ZBX_LAYOUT_JSON,		null],
		'item.edit'									=> [CControllerItemEdit::class,									ZBX_LAYOUT_JSON,		'item.edit'],
		'item.enable'								=> [CControllerItemEnable::class,								ZBX_LAYOUT_JSON,		null],
		'item.execute'								=> [CControllerItemExecuteNow::class,							ZBX_LAYOUT_JSON,		null],
		'item.list'									=> [CControllerItemList::class,									ZBX_LAYOUT_HTMLPAGE,	'item.list'],
		'item.massupdate'							=> [CControllerItemMassupdate::class,							ZBX_LAYOUT_JSON,		'item.massupdate'],
		'item.update'								=> [CControllerItemUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'item.prototype.create'						=> [CControllerItemPrototypeCreate::class,						ZBX_LAYOUT_JSON,		null],
		'item.prototype.delete'						=> [CControllerItemPrototypeDelete::class,						ZBX_LAYOUT_JSON,		null],
		'item.prototype.disable'					=> [CControllerItemPrototypeDisable::class,						ZBX_LAYOUT_JSON,		null],
		'item.prototype.edit'						=> [CControllerItemPrototypeEdit::class,						ZBX_LAYOUT_JSON,		'item.prototype.edit'],
		'item.prototype.enable'						=> [CControllerItemPrototypeEnable::class,						ZBX_LAYOUT_JSON,		null],
		'item.prototype.list'						=> [CControllerItemPrototypeList::class,						ZBX_LAYOUT_HTMLPAGE,	'item.prototype.list'],
		'item.prototype.massupdate'					=> [CControllerItemMassupdate::class,							ZBX_LAYOUT_JSON,		'item.massupdate'],
		'item.prototype.update'						=> [CControllerItemPrototypeUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'item.tags.list'							=> [CControllerItemTagsList::class,								ZBX_LAYOUT_JSON,		'item.tags.list'],
		'latest.view'								=> [CControllerLatestView::class,								ZBX_LAYOUT_HTMLPAGE,	'monitoring.latest.view'],
		'latest.view.data'							=> [CControllerLatestViewData::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.list'								=> [CControllerLldRuleList::class,								ZBX_LAYOUT_HTMLPAGE,	'lldrule.list'],
		'lldrule.create'							=> [CControllerLldRuleCreate::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.delete'							=> [CControllerLldRuleDelete::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.disable'							=> [CControllerLldRuleDisable::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.edit'								=> [CControllerLldRuleEdit::class,								ZBX_LAYOUT_JSON,		'lldrule.edit'],
		'lldrule.enable'							=> [CControllerLldRuleEnable::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.update'							=> [CControllerLldRuleUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.list'					=> [CControllerLldRulePrototypeList::class,						ZBX_LAYOUT_JSON,		'lldrule.prototype.list'],
		'lldrule.prototype.create'					=> [CControllerLldRulePrototypeCreate::class, 					ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.delete'					=> [CControllerLldRulePrototypeDelete::class,					ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.disable'					=> [CControllerLldRulePrototypeDisable::class,					ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.edit'					=> [CControllerLldRulePrototypeEdit::class,						ZBX_LAYOUT_JSON,		'lldrule.prototype.edit'],
		'lldrule.prototype.enable'					=> [CControllerLldRulePrototypeEnable::class,					ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.update'					=> [CControllerLldRulePrototypeUpdate::class,					ZBX_LAYOUT_JSON,		null],
		'lldrule.prototype.updatediscover'			=> [CControllerLldRulePrototypeUpdateDiscover::class,			ZBX_LAYOUT_JSON,		null],
		'macros.edit'								=> [CControllerMacrosEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.macros.edit'],
		'macros.update'								=> [CControllerMacrosUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'maintenance.create'						=> [CControllerMaintenanceCreate::class,						ZBX_LAYOUT_JSON,		null],
		'maintenance.delete'						=> [CControllerMaintenanceDelete::class,						ZBX_LAYOUT_JSON,		null],
		'maintenance.edit'							=> [CControllerMaintenanceEdit::class,							ZBX_LAYOUT_JSON,		'maintenance.edit'],
		'maintenance.list'							=> [CControllerMaintenanceList::class,							ZBX_LAYOUT_HTMLPAGE,	'maintenance.list'],
		'maintenance.timeperiod.edit'				=> [CControllerMaintenanceTimePeriodEdit::class,				ZBX_LAYOUT_JSON,		'maintenance.timeperiod.edit'],
		'maintenance.timeperiod.check'				=> [CControllerMaintenanceTimePeriodCheck::class,				ZBX_LAYOUT_JSON,		null],
		'maintenance.update'						=> [CControllerMaintenanceUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'map.view'									=> [CControllerMapView::class,									ZBX_LAYOUT_HTMLPAGE,	'monitoring.map.view'],
		'mediatype.create'							=> [CControllerMediatypeCreate::class,							ZBX_LAYOUT_JSON,		null],
		'mediatype.delete'							=> [CControllerMediatypeDelete::class,							ZBX_LAYOUT_JSON,		null],
		'mediatype.disable'							=> [CControllerMediatypeDisable::class,							ZBX_LAYOUT_JSON,		null],
		'mediatype.edit'							=> [CControllerMediatypeEdit::class,							ZBX_LAYOUT_JSON,		'mediatype.edit'],
		'mediatype.enable'							=> [CControllerMediatypeEnable::class,							ZBX_LAYOUT_JSON,		null],
		'mediatype.list'							=> [CControllerMediatypeList::class,							ZBX_LAYOUT_HTMLPAGE,	'mediatype.list'],
		'mediatype.message.check'					=> [CControllerMediatypeMessageCheck::class,					ZBX_LAYOUT_JSON,		null],
		'mediatype.message.edit'					=> [CControllerMediatypeMessageEdit::class,						ZBX_LAYOUT_JSON,		'mediatype.message.edit'],
		'mediatype.test.edit'						=> [CControllerMediatypeTestEdit::class,						ZBX_LAYOUT_JSON,		'mediatype.test.edit'],
		'mediatype.test.send'						=> [CControllerMediatypeTestSend::class,						ZBX_LAYOUT_JSON,		null],
		'mediatype.update'							=> [CControllerMediatypeUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'menu.popup'								=> [CControllerMenuPopup::class,								ZBX_LAYOUT_JSON,		null],
		'mfa.edit'									=> [CControllerMfaEdit::class,									ZBX_LAYOUT_JSON,		'mfa.edit'],
		'mfa.check'									=> [CControllerMfaCheck::class,									ZBX_LAYOUT_JSON,		null],
		'miscconfig.edit'							=> [CControllerMiscConfigEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.miscconfig.edit'],
		'miscconfig.update'							=> [CControllerMiscConfigUpdate::class, 						ZBX_LAYOUT_JSON,		null],
		'module.disable'							=> [CControllerModuleDisable::class,							ZBX_LAYOUT_JSON,		null],
		'module.edit'								=> [CControllerModuleEdit::class,								ZBX_LAYOUT_JSON,		'module.edit'],
		'module.enable'								=> [CControllerModuleEnable::class,								ZBX_LAYOUT_JSON,		null],
		'module.list'								=> [CControllerModuleList::class,								ZBX_LAYOUT_HTMLPAGE,	'module.list'],
		'module.scan'								=> [CControllerModuleScan::class,								null,					null],
		'module.update'								=> [CControllerModuleUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'notifications.get'							=> [CControllerNotificationsGet::class,							ZBX_LAYOUT_JSON,		null],
		'notifications.mute'						=> [CControllerNotificationsMute::class,						ZBX_LAYOUT_JSON,		null],
		'notifications.read'						=> [CControllerNotificationsRead::class,						ZBX_LAYOUT_JSON,		null],
		'notifications.snooze'						=> [CControllerNotificationsSnooze::class,						ZBX_LAYOUT_JSON,		null],
		'oauth.authorize'							=> [CControllerOauthAuthorize::class,							ZBX_LAYOUT_HTMLPAGE,	'oauth.authorize'],
		'oauth.edit'								=> [CControllerOauthEdit::class,								ZBX_LAYOUT_JSON,		'oauth.edit'],
		'oauth.check'								=> [CControllerOauthCheck::class,								ZBX_LAYOUT_JSON,		null],
		'popup'										=> [CControllerPopup::class,									ZBX_LAYOUT_HTMLPAGE,	'popup.view'],
		'popup.acknowledge.create'					=> [CControllerPopupAcknowledgeCreate::class,					ZBX_LAYOUT_JSON,		null],
		'popup.action.operation.edit'				=> [CControllerPopupActionOperationEdit::class,					ZBX_LAYOUT_JSON,		'popup.operation.edit'],
		'popup.action.operations.list'				=> [CControllerPopupActionOperationsList::class,				ZBX_LAYOUT_JSON,		'popup.action.operations.list'],
		'popup.condition.check'						=> [CControllerActionConditionCheck::class,						ZBX_LAYOUT_JSON,		null],
		'popup.condition.edit'						=> [CControllerPopupActionConditionEdit::class,					ZBX_LAYOUT_JSON,		'popup.condition.edit'],
		'popup.condition.operations'				=> [CControllerPopupConditionOperations::class,					ZBX_LAYOUT_JSON,		'popup.condition.edit'],
		'popup.dashboard.share.edit'				=> [CControllerPopupDashboardShareEdit::class,					ZBX_LAYOUT_JSON,		'popup.dashboard.share.edit'],
		'popup.generic'								=> [CControllerPopupGeneric::class,								ZBX_LAYOUT_JSON,		'popup.generic'],
		'popup.import'								=> [CControllerPopupImport::class,								ZBX_LAYOUT_JSON,		'popup.import'],
		'popup.import.compare'						=> [CControllerPopupImportCompare::class,						ZBX_LAYOUT_JSON,		'popup.import.compare'],
		'popup.itemtest.edit'						=> [CControllerPopupItemTestEdit::class,						ZBX_LAYOUT_JSON,		'popup.itemtestedit.view'],
		'popup.itemtest.getvalue'					=> [CControllerPopupItemTestGetValue::class,					ZBX_LAYOUT_JSON,		null],
		'popup.itemtest.send'						=> [CControllerPopupItemTestSend::class,						ZBX_LAYOUT_JSON,		null],
		'popup.ldap.check'							=> [CControllerPopupLdapCheck::class,							ZBX_LAYOUT_JSON,		null],
		'popup.ldap.edit'							=> [CControllerPopupLdapEdit::class,							ZBX_LAYOUT_JSON,		'popup.ldap.edit'],
		'popup.ldap.test.edit'						=> [CControllerPopupLdapTestEdit::class,						ZBX_LAYOUT_JSON,		'popup.ldap.test.edit'],
		'popup.ldap.test.send'						=> [CControllerPopupLdapTestSend::class,						ZBX_LAYOUT_JSON,		null],
		'popup.lldoperation'						=> [CControllerPopupLldOperation::class,						ZBX_LAYOUT_JSON,		'popup.lldoperation'],
		'popup.lldoverride'							=> [CControllerPopupLldOverride::class,							ZBX_LAYOUT_JSON,		'popup.lldoverride'],
		'popup.massupdate.host'						=> [CControllerPopupMassupdateHost::class,						ZBX_LAYOUT_JSON,		'popup.massupdate.host'],
		'popup.massupdate.service'					=> [CControllerPopupMassupdateService::class,					ZBX_LAYOUT_JSON,		'popup.massupdate.service'],
		'popup.media.check'							=> [CControllerPopupMediaCheck::class,							ZBX_LAYOUT_JSON,		null],
		'popup.media.edit'							=> [CControllerPopupMediaEdit::class,							ZBX_LAYOUT_JSON,		'popup.media.edit'],
		'popup.mediatypemapping.check'				=> [CControllerPopupMediaTypeMappingCheck::class,				ZBX_LAYOUT_JSON,		null],
		'popup.mediatypemapping.edit'				=> [CControllerPopupMediaTypeMappingEdit::class,				ZBX_LAYOUT_JSON,		'popup.mediatypemapping.edit'],
		'popup.usergroupmapping.check'				=> [CControllerPopupUserGroupMappingCheck::class,				ZBX_LAYOUT_JSON,		null],
		'popup.usergroupmapping.edit'				=> [CControllerPopupUserGroupMappingEdit::class,				ZBX_LAYOUT_JSON,		'popup.usergroupmapping.edit'],
		'popup.scheduledreport.list'				=> [CControllerPopupScheduledReportList::class,					ZBX_LAYOUT_JSON,		'popup.scheduledreport.list'],
		'popup.scheduledreport.subscription.check'	=> [CControllerPopupScheduledReportSubscriptionCheck::class,	ZBX_LAYOUT_JSON,		null],
		'popup.scheduledreport.subscription.edit'	=> [CControllerPopupScheduledReportSubscriptionEdit::class,		ZBX_LAYOUT_JSON,		'popup.scheduledreport.subscription'],
		'popup.scheduledreport.test'				=> [CControllerPopupScheduledReportTest::class,					ZBX_LAYOUT_JSON,		'popup.scheduledreport.test'],
		'popup.scriptexec'							=> [CControllerPopupScriptExec::class,							ZBX_LAYOUT_JSON,		'popup.scriptexec'],
		'popup.service.statusrule.edit'				=> [CControllerPopupServiceStatusRuleEdit::class,				ZBX_LAYOUT_JSON,		'popup.service.statusrule.edit'],
		'popup.services'							=> [CControllerPopupServices::class,							ZBX_LAYOUT_JSON,		'popup.services'],
		'popup.sla.excludeddowntime.edit'			=> [CControllerPopupSlaExcludedDowntimeEdit::class,				ZBX_LAYOUT_JSON,		'popup.sla.excludeddowntime.edit'],
		'popup.tabfilter.delete'					=> [CControllerPopupTabFilterDelete::class,						ZBX_LAYOUT_JSON,		null],
		'popup.tabfilter.edit'						=> [CControllerPopupTabFilterEdit::class,						ZBX_LAYOUT_JSON,		'popup.tabfilter.edit'],
		'popup.tabfilter.update'					=> [CControllerPopupTabFilterUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'popup.testtriggerexpr'						=> [CControllerPopupTestTriggerExpr::class,						ZBX_LAYOUT_JSON,		'popup.testtriggerexpr'],
		'popup.token.view'							=> [CControllerPopupTokenView::class,							ZBX_LAYOUT_JSON,		'popup.token.view'],
		'popup.triggerexpr.edit'					=> [CControllerPopupTriggerExprEdit::class, 					ZBX_LAYOUT_JSON,		'popup.triggerexpr'],
		'popup.triggerexpr.check'					=> [CControllerPopupTriggerExprCheck::class, 					ZBX_LAYOUT_JSON,		null],
		'popup.valuemap.edit'						=> [CControllerValueMapEdit::class,								ZBX_LAYOUT_JSON,		'popup.valuemap.edit'],
		'popup.valuemap.check'						=> [CControllerValueMapCheck::class,							ZBX_LAYOUT_JSON,		null],
		'problem.view'								=> [CControllerProblemView::class,								ZBX_LAYOUT_HTMLPAGE,	'monitoring.problem.view'],
		'problem.view.data'							=> [CControllerProblemViewData::class,							ZBX_LAYOUT_JSON,		null],
		'profile.update'							=> [CControllerProfileUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'proxy.config.refresh'						=> [CControllerProxyConfigRefresh::class,						ZBX_LAYOUT_JSON,		null],
		'proxy.create'								=> [CControllerProxyCreate::class,								ZBX_LAYOUT_JSON,		null],
		'proxy.delete'								=> [CControllerProxyDelete::class,								ZBX_LAYOUT_JSON,		null],
		'proxy.edit'								=> [CControllerProxyEdit::class,								ZBX_LAYOUT_JSON,		'proxy.edit'],
		'proxy.host.disable'						=> [CControllerProxyHostDisable::class,							ZBX_LAYOUT_JSON,		null],
		'proxy.host.enable'							=> [CControllerProxyHostEnable::class,							ZBX_LAYOUT_JSON,		null],
		'proxy.list'								=> [CControllerProxyList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.proxy.list'],
		'proxy.update'								=> [CControllerProxyUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'proxygroup.create'							=> [CControllerProxyGroupCreate::class,							ZBX_LAYOUT_JSON,		null],
		'proxygroup.delete'							=> [CControllerProxyGroupDelete::class,							ZBX_LAYOUT_JSON,		null],
		'proxygroup.edit'							=> [CControllerProxyGroupEdit::class,							ZBX_LAYOUT_JSON,		'proxygroup.edit'],
		'proxygroup.list'							=> [CControllerProxyGroupList::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.proxygroup.list'],
		'proxygroup.update'							=> [CControllerProxyGroupUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'queue.details'								=> [CControllerQueueDetails::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.queue.details'],
		'queue.overview'							=> [CControllerQueueOverview::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.queue.overview'],
		'queue.overview.proxy'						=> [CControllerQueueOverviewProxy::class,						ZBX_LAYOUT_HTMLPAGE,	'administration.queue.overview.proxy'],
		'regex.create'								=> [CControllerRegExCreate::class,								ZBX_LAYOUT_JSON,		null],
		'regex.delete'								=> [CControllerRegExDelete::class,								null,					null],
		'regex.edit'								=> [CControllerRegExEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.regex.edit'],
		'regex.list'								=> [CControllerRegExList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.regex.list'],
		'regex.test'								=> [CControllerRegExTest::class,								ZBX_LAYOUT_JSON,		null],
		'regex.update'								=> [CControllerRegExUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'report.status'								=> [CControllerReportStatus::class,								ZBX_LAYOUT_HTMLPAGE,	'report.status'],
		'scheduledreport.create'					=> [CControllerScheduledReportCreate::class,					ZBX_LAYOUT_JSON,		null],
		'scheduledreport.delete'					=> [CControllerScheduledReportDelete::class,					ZBX_LAYOUT_JSON,		null],
		'scheduledreport.disable'					=> [CControllerScheduledReportDisable::class,					ZBX_LAYOUT_JSON,		null],
		'scheduledreport.edit'						=> [CControllerScheduledReportEdit::class,						ZBX_LAYOUT_JSON,		'reports.scheduledreport.edit'],
		'scheduledreport.enable'					=> [CControllerScheduledReportEnable::class,					ZBX_LAYOUT_JSON,		null],
		'scheduledreport.list'						=> [CControllerScheduledReportList::class,						ZBX_LAYOUT_HTMLPAGE,	'reports.scheduledreport.list'],
		'scheduledreport.update'					=> [CControllerScheduledReportUpdate::class,					ZBX_LAYOUT_JSON,		null],
		'script.create'								=> [CControllerScriptCreate::class,								ZBX_LAYOUT_JSON,		null],
		'script.delete'								=> [CControllerScriptDelete::class,								ZBX_LAYOUT_JSON,		null],
		'script.edit'								=> [CControllerScriptEdit::class,								ZBX_LAYOUT_JSON,		'administration.script.edit'],
		'script.list'								=> [CControllerScriptList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.script.list'],
		'script.update'								=> [CControllerScriptUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'script.userinput.edit'						=> [CControllerScriptUserInputEdit::class,						ZBX_LAYOUT_JSON,		'script.userinput.edit'],
		'script.userinput.check'					=> [CControllerScriptUserInputCheck::class,						ZBX_LAYOUT_JSON,		null],
		'search'									=> [CControllerSearch::class,									ZBX_LAYOUT_HTMLPAGE,	'search'],
		'service.create'							=> [CControllerServiceCreate::class,							ZBX_LAYOUT_JSON,		null],
		'service.delete'							=> [CControllerServiceDelete::class,							ZBX_LAYOUT_JSON,		null],
		'service.edit'								=> [CControllerServiceEdit::class,								ZBX_LAYOUT_JSON,		'service.edit'],
		'service.list'								=> [CControllerServiceList::class,								ZBX_LAYOUT_HTMLPAGE,	'service.list'],
		'service.list.edit'							=> [CControllerServiceListEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'service.list.edit'],
		'service.list.edit.refresh'					=> [CControllerServiceListEditRefresh::class,					ZBX_LAYOUT_JSON,		'service.list.edit.refresh'],
		'service.list.refresh'						=> [CControllerServiceListRefresh::class,						ZBX_LAYOUT_JSON,		'service.list.refresh'],
		'service.statusrule.validate'				=> [CControllerServiceStatusRuleValidate::class,				ZBX_LAYOUT_JSON,		null],
		'service.update'							=> [CControllerServiceUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'sla.create'								=> [CControllerSlaCreate::class,								ZBX_LAYOUT_JSON,		null],
		'sla.delete'								=> [CControllerSlaDelete::class,								ZBX_LAYOUT_JSON,		null],
		'sla.disable'								=> [CControllerSlaDisable::class,								ZBX_LAYOUT_JSON,		null],
		'sla.edit'									=> [CControllerSlaEdit::class,									ZBX_LAYOUT_JSON,		'sla.edit'],
		'sla.enable'								=> [CControllerSlaEnable::class,								ZBX_LAYOUT_JSON,		null],
		'sla.excludeddowntime.validate'				=> [CControllerSlaExcludedDowntimeValidate::class,				ZBX_LAYOUT_JSON,		null],
		'sla.list'									=> [CControllerSlaList::class,									ZBX_LAYOUT_HTMLPAGE,	'sla.list'],
		'sla.update'								=> [CControllerSlaUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'slareport.list'							=> [CControllerSlaReportList::class,							ZBX_LAYOUT_HTMLPAGE,	'slareport.list'],
		'softwareversioncheck.get'					=> [CControllerSoftwareVersionCheckGet::class,					ZBX_LAYOUT_JSON,		null],
		'softwareversioncheck.update'				=> [CControllerSoftwareVersionCheckUpdate::class,				ZBX_LAYOUT_JSON,		null],
		'system.warning'							=> [CControllerSystemWarning::class,							ZBX_LAYOUT_WARNING,		'system.warning'],
		'tabfilter.profile.update'					=> [CControllerTabFilterProfileUpdate::class,					ZBX_LAYOUT_JSON,		null],
		'template.create'							=> [CControllerTemplateCreate::class,							ZBX_LAYOUT_JSON,		null],
		'template.dashboard.delete'					=> [CControllerTemplateDashboardDelete::class,					null,					null],
		'template.dashboard.edit'					=> [CControllerTemplateDashboardEdit::class,					ZBX_LAYOUT_HTMLPAGE,	'configuration.dashboard.edit'],
		'template.dashboard.list'					=> [CControllerTemplateDashboardList::class,					ZBX_LAYOUT_HTMLPAGE,	'configuration.dashboard.list'],
		'template.dashboard.update'					=> [CControllerTemplateDashboardUpdate::class,					ZBX_LAYOUT_JSON,		null],
		'template.delete'							=> [CControllerTemplateDelete::class,							ZBX_LAYOUT_JSON,		null],
		'template.edit'								=> [CControllerTemplateEdit::class,								ZBX_LAYOUT_JSON,		'template.edit'],
		'template.list'								=> [CControllerTemplateList::class,								ZBX_LAYOUT_HTMLPAGE,	'template.list'],
		'template.list.data'						=> [CControllerTemplateListData::class,							ZBX_LAYOUT_JSON,		null],
		'template.massupdate'						=> [CControllerTemplateMassupdate::class,						ZBX_LAYOUT_JSON,		'template.massupdate'],
		'template.update'							=> [CControllerTemplateUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'templategroup.create'						=> [CControllerTemplateGroupCreate::class,						ZBX_LAYOUT_JSON,		null],
		'templategroup.delete'						=> [CControllerTemplateGroupDelete::class,						ZBX_LAYOUT_JSON,		null],
		'templategroup.edit'						=> [CControllerTemplateGroupEdit::class,						ZBX_LAYOUT_JSON,		'templategroup.edit'],
		'templategroup.list'						=> [CControllerTemplateGroupList::class,						ZBX_LAYOUT_HTMLPAGE,	'configuration.templategroup.list'],
		'templategroup.update'						=> [CControllerTemplateGroupUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'timeouts.edit'								=> [CControllerTimeoutsEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.timeouts.edit'],
		'timeouts.update'							=> [CControllerTimeoutsUpdate::class, 							ZBX_LAYOUT_JSON,		null],
		'timeselector.calc'							=> [CControllerTimeSelectorCalc::class,							ZBX_LAYOUT_JSON,		null],
		'timeselector.update'						=> [CControllerTimeSelectorUpdate::class,						ZBX_LAYOUT_JSON,		null],
		'token.create'								=> [CControllerTokenCreate::class,								ZBX_LAYOUT_JSON,		null],
		'token.delete'								=> [CControllerTokenDelete::class,								ZBX_LAYOUT_JSON,		null],
		'token.disable'								=> [CControllerTokenDisable::class,								null,					null],
		'token.edit'								=> [CControllerTokenEdit::class,								ZBX_LAYOUT_JSON,		'token.edit'],
		'token.enable'								=> [CControllerTokenEnable::class,								null,					null],
		'token.list'								=> [CControllerTokenList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.token.list'],
		'token.update'								=> [CControllerTokenUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'toptriggers.list'							=> [CControllerTopTriggersList::class,							ZBX_LAYOUT_HTMLPAGE,	'reports.toptriggers.list'],
		'trigdisplay.edit'							=> [CControllerTrigDisplayEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.trigdisplay.edit'],
		'trigdisplay.update'						=> [CControllerTrigDisplayUpdate::class, 						ZBX_LAYOUT_JSON,		null],
		'trigger.create'							=> [CControllerTriggerCreate::class,							ZBX_LAYOUT_JSON,		null],
		'trigger.delete'							=> [CControllerTriggerDelete::class,							ZBX_LAYOUT_JSON,		null],
		'trigger.disable'							=> [CControllerTriggerDisable::class,							ZBX_LAYOUT_JSON,		null],
		'trigger.edit'								=> [CControllerTriggerEdit::class,								ZBX_LAYOUT_JSON,		'trigger.edit'],
		'trigger.enable'							=> [CControllerTriggerEnable::class,							ZBX_LAYOUT_JSON,		null],
		'trigger.expression.constructor'			=> [CControllerTriggerExpressionConstructor::class,				ZBX_LAYOUT_JSON,		'trigger.expression.constructor'],
		'trigger.list'								=> [CControllerTriggerList::class,								ZBX_LAYOUT_HTMLPAGE,	'trigger.list'],
		'trigger.massupdate'						=> [CControllerTriggerMassupdate::class,						ZBX_LAYOUT_JSON,		'trigger.massupdate'],
		'trigger.prototype.create'					=> [CControllerTriggerPrototypeCreate::class,					ZBX_LAYOUT_JSON,		null],
		'trigger.prototype.delete'					=> [CControllerTriggerPrototypeDelete::class,					ZBX_LAYOUT_JSON,		null],
		'trigger.prototype.disable'					=> [CControllerTriggerPrototypeDisable::class,					ZBX_LAYOUT_JSON,		null],
		'trigger.prototype.edit'					=> [CControllerTriggerPrototypeEdit::class,						ZBX_LAYOUT_JSON,		'trigger.prototype.edit'],
		'trigger.prototype.enable'					=> [CControllerTriggerPrototypeEnable::class,					ZBX_LAYOUT_JSON,		null],
		'trigger.prototype.list'					=> [CControllerTriggerPrototypeList::class,						ZBX_LAYOUT_HTMLPAGE,	'trigger.prototype.list'],
		'trigger.prototype.massupdate'				=> [CControllerTriggerMassupdate::class,						ZBX_LAYOUT_JSON,		'trigger.massupdate'],
		'trigger.prototype.update'					=> [CControllerTriggerPrototypeUpdate::class,					ZBX_LAYOUT_JSON,		null],
		'trigger.update'							=> [CControllerTriggerUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'user.create'								=> [CControllerUserCreate::class,								ZBX_LAYOUT_JSON,		null],
		'user.delete'								=> [CControllerUserDelete::class,								null,					null],
		'user.device.delete'						=> [CControllerUserDeviceDelete::class,							ZBX_LAYOUT_JSON,		null],
		'user.device.init'							=> [CControllerUserDeviceInit::class,							ZBX_LAYOUT_JSON,		null],
		'user.device.init.view'						=> [CControllerUserDeviceInitView::class,						ZBX_LAYOUT_JSON,		'user.device.init.view'],
		'user.device.list'							=> [CControllerUserDeviceList::class,							ZBX_LAYOUT_HTMLPAGE,	'user.device.list'],
		'user.device.status'						=> [CControllerUserDeviceStatus::class,							ZBX_LAYOUT_JSON,		null],
		'user.edit'									=> [CControllerUserEdit::class,									ZBX_LAYOUT_HTMLPAGE,	'administration.user.edit'],
		'user.list'									=> [CControllerUserList::class,									ZBX_LAYOUT_HTMLPAGE,	'administration.user.list'],
		'user.token.list'							=> [CControllerUserTokenList::class,							ZBX_LAYOUT_HTMLPAGE,	'administration.user.token.list'],
		'user.unblock'								=> [CControllerUserUnblock::class,								null,					null],
		'user.update'								=> [CControllerUserUpdate::class,								ZBX_LAYOUT_JSON,		null],
		'user.provision'							=> [CControllerUserProvision::class,							null,					null],
		'user.reset.totp'							=> [CControllerUserResetTotp::class,							null,					null],
		'usergroup.create'							=> [CControllerUsergroupCreate::class, 							ZBX_LAYOUT_JSON,		null],
		'usergroup.delete'							=> [CControllerUsergroupDelete::class,							null,					null],
		'usergroup.edit'							=> [CControllerUsergroupEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'usergroup.edit'],
		'usergroup.list'							=> [CControllerUsergroupList::class,							ZBX_LAYOUT_HTMLPAGE,	'usergroup.list'],
		'usergroup.massupdate'						=> [CControllerUsergroupMassUpdate::class,						null,					null],
		'usergroup.tagfilter.edit'					=> [CControllerUsergroupTagFilterEdit::class,					ZBX_LAYOUT_JSON,		'usergroup.tagfilter.edit'],
		'usergroup.tagfilter.check'					=> [CControllerUsergroupTagFilterCheck::class,					ZBX_LAYOUT_JSON,		null],
		'usergroup.tagfilter.list'					=> [CControllerUsergroupTagFilterList::class,					ZBX_LAYOUT_JSON,		'usergroup.tagfilter.list'],
		'usergroup.update'							=> [CControllerUsergroupUpdate::class,							ZBX_LAYOUT_JSON,		null],
		'userprofile.edit'							=> [CControllerUserProfileEdit::class,							ZBX_LAYOUT_HTMLPAGE,	'userprofile.edit'],
		'userprofile.update'						=> [CControllerUserProfileUpdate::class, 						ZBX_LAYOUT_JSON,		null],
		'userprofile.device.list'					=> [CControllerUserProfileDeviceList::class,					ZBX_LAYOUT_HTMLPAGE,	'userprofile.device.list'],
		'userprofile.notification.edit'				=> [CControllerUserProfileNotificationEdit::class,				ZBX_LAYOUT_HTMLPAGE,	'userprofile.notification.edit'],
		'userprofile.notification.update'			=> [CControllerUserProfileNotificationUpdate::class,			ZBX_LAYOUT_JSON,		null],
		'userrole.create'							=> [CControllerUserroleCreate::class, 							ZBX_LAYOUT_JSON,		null],
		'userrole.delete'							=> [CControllerUserroleDelete::class,							null,					null],
		'userrole.edit'								=> [CControllerUserroleEdit::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.userrole.edit'],
		'userrole.list'								=> [CControllerUserroleList::class,								ZBX_LAYOUT_HTMLPAGE,	'administration.userrole.list'],
		'userrole.update'							=> [CControllerUserroleUpdate::class, 							ZBX_LAYOUT_JSON,		null],
		'validate.use'								=> [CControllerValidateUse::class,								ZBX_LAYOUT_JSON,		null],
		'validate.api.exists'						=> [CControllerValidateApiExists::class, 						ZBX_LAYOUT_JSON,		null],
		'web.view'									=> [CControllerWebView::class,									ZBX_LAYOUT_HTMLPAGE,	'monitoring.web.view'],
		'webscenario.step.check'					=> [CControllerWebScenarioStepCheck::class,						ZBX_LAYOUT_JSON,		null],
		'webscenario.step.edit'						=> [CControllerWebScenarioStepEdit::class,						ZBX_LAYOUT_JSON,		'webscenario.step.edit'],
		'widget.navigation.tree.toggle'				=> [CControllerWidgetNavigationTreeToggle::class,				ZBX_LAYOUT_JSON,		null],

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

	/**
	 * Check if the action is a registered Frontend MVC action.
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	public function isMvcAction(string $action): bool {
		return array_key_exists($action, $this->routes) && $this->routes[$action][0] !== CLegacyAction::class;
	}

	/**
	 * Check if the action file is a known Frontend legacy action file.
	 *
	 * @param string $action_file
	 *
	 * @return bool
	 */
	public function isLegacyActionFile(string $action_file): bool {
		return array_key_exists($action_file, $this->routes) && $this->routes[$action_file][0] === CLegacyAction::class;
	}

	/**
	 * Check if the MVC action requires loading dashboard and widget-related assets.
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	public static function isDashboardAction(string $action): bool {
		return in_array($action, self::DASHBOARD_ACTIONS, true);
	}
}
