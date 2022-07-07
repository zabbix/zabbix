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


class CDocHelper {

	const ADMINISTRATION_AUDITACTS_LIST =					'web_interface/frontend_sections/reports/action_log';
	const ADMINISTRATION_AUDIT_SETTINGS_EDIT =				'web_interface/frontend_sections/administration/general#audit-log';
	const ADMINISTRATION_AUTHENTICATION_EDIT =				'web_interface/frontend_sections/administration/authentication';
	const ADMINISTRATION_AUTOREG_EDIT =						'web_interface/frontend_sections/administration/general#autoregistration';
	const ADMINISTRATION_GEOMAPS_EDIT =						'web_interface/frontend_sections/administration/general#geographical-maps';
	const ADMINISTRATION_GUI_EDIT =							'web_interface/frontend_sections/administration/general#gui';
	const ADMINISTRATION_HOUSEKEEPING_EDIT =				'web_interface/frontend_sections/administration/general#housekeeper';
	const ADMINISTRATION_ICONMAP_EDIT =						'web_interface/frontend_sections/administration/general#icon-mapping';
	const ADMINISTRATION_ICONMAP_LIST =						'web_interface/frontend_sections/administration/general#icon-mapping';
	const ADMINISTRATION_IMAGE_EDIT =						'web_interface/frontend_sections/administration/general#images';
	const ADMINISTRATION_IMAGE_LIST =						'web_interface/frontend_sections/administration/general#images';
	const ADMINISTRATION_MACROS_EDIT =						'web_interface/frontend_sections/administration/general#macros';
	const ADMINISTRATION_MEDIATYPE_EDIT =					'config/notifications/media#common-parameters';
	const ADMINISTRATION_MEDIATYPE_LIST =					'web_interface/frontend_sections/administration/mediatypes';
	const ADMINISTRATION_MISCCONFIG_EDIT =					'web_interface/frontend_sections/administration/general#other-parameters';
	const ADMINISTRATION_MODULE_EDIT =						'modules#manifest-preparation';
	const ADMINISTRATION_MODULE_LIST =						'web_interface/frontend_sections/administration/general#modules';
	const ADMINISTRATION_PROXY_EDIT =						'distributed_monitoring/proxies#configuration';
	const ADMINISTRATION_PROXY_LIST =						'web_interface/frontend_sections/administration/proxies';
	const ADMINISTRATION_REGEX_EDIT =						'regular_expressions#global-regular-expressions';
	const ADMINISTRATION_REGEX_LIST =						'web_interface/frontend_sections/administration/general#regular-expressions';
	const ADMINISTRATION_SCRIPT_EDIT =						'web_interface/frontend_sections/administration/scripts#configuring-a-global-script';
	const ADMINISTRATION_SCRIPT_LIST =						'web_interface/frontend_sections/administration/scripts';
	const ADMINISTRATION_TOKEN_LIST =						'web_interface/frontend_sections/administration/general#api-tokens';
	const ADMINISTRATION_TRIGDISPLAY_EDIT =					'web_interface/frontend_sections/administration/general#trigger-displaying-options';
	const ADMINISTRATION_USER_EDIT =						'config/users_and_usergroups/user';
	const ADMINISTRATION_USER_LIST =						'web_interface/frontend_sections/administration/users';
	const ADMINISTRATION_USER_TOKEN_LIST =					'web_interface/user_profile#api-tokens';
	const ADMINISTRATION_USERGROUP_EDIT =					'config/users_and_usergroups/usergroup#configuration';
	const ADMINISTRATION_USERGROUP_LIST =					'web_interface/frontend_sections/administration/user_groups';
	const ADMINISTRATION_USERPROFILE_EDIT =					'web_interface/user_profile#user-profile';
	const ADMINISTRATION_USERROLE_EDIT =					'web_interface/frontend_sections/administration/user_roles#default-user-roles';
	const ADMINISTRATION_USERROLE_LIST =					'web_interface/frontend_sections/administration/user_roles';
	const CONFIGURATION_ACTION_EDIT =						'config/notifications/action#configuring-an-action';
	const CONFIGURATION_ACTION_LIST =						'web_interface/frontend_sections/configuration/actions';
	const CONFIGURATION_CORRELATION_EDIT =					'config/event_correlation/global#configuration';
	const CONFIGURATION_CORRELATION_LIST =					'web_interface/frontend_sections/configuration/correlation';
	const CONFIGURATION_DASHBOARD_EDIT =					'web_interface/frontend_sections/monitoring/dashboard#creating-a-dashboard';
	const CONFIGURATION_DASHBOARD_LIST =					'config/visualization/host_screens';
	const CONFIGURATION_DISCOVERY_EDIT =					'discovery/network_discovery/rule#rule-attributes';
	const CONFIGURATION_DISCOVERY_LIST =					'web_interface/frontend_sections/configuration/discovery';
	const CONFIGURATION_GRAPH_EDIT =						'config/visualization/graphs/custom#configuring-custom-graphs';
	const CONFIGURATION_HOST_GRAPH_PROTOTYPE_LIST = 		'web_interface/frontend_sections/configuration/hosts/discovery/graph_prototypes';
	const CONFIGURATION_HOST_DISCOVERY_EDIT =				'discovery/low_level_discovery#discovery-rule';
	const CONFIGURATION_HOST_DISCOVERY_LIST =				'web_interface/frontend_sections/configuration/hosts/discovery';
	const CONFIGURATION_HOST_GRAPH_LIST =					'web_interface/frontend_sections/configuration/hosts/graphs';
	const CONFIGURATION_HOST_HTTPCONF_LIST =				'web_interface/frontend_sections/configuration/hosts/web';
	const CONFIGURATION_HOST_ITEM_LIST =					'web_interface/frontend_sections/configuration/hosts/items';
	const CONFIGURATION_HOST_ITEM_PROTOTYPE_LIST =			'web_interface/frontend_sections/configuration/hosts/discovery/item_prototypes';
	const CONFIGURATION_HOST_EDIT =							'config/hosts/host#configuration';
	const CONFIGURATION_HOST_LIST =							'web_interface/frontend_sections/configuration/hosts';
	const CONFIGURATION_HOST_PROTOTYPE_EDIT =				'vm_monitoring#host-prototypes';
	const CONFIGURATION_HOST_PROTOTYPE_LIST =				'web_interface/frontend_sections/configuration/hosts/discovery/host_prototypes';
	const CONFIGURATION_HOST_TRIGGERS_LIST =				'web_interface/frontend_sections/configuration/hosts/triggers';
	const CONFIGURATION_HOST_TRIGGER_PROTOTYPE_LIST =		'web_interface/frontend_sections/configuration/hosts/discovery/trigger_prototypes';
	const CONFIGURATION_HOSTGROUPS_EDIT =					'config/hosts/host#creating-a-host-group';
	const CONFIGURATION_HOSTGROUPS_LIST =					'web_interface/frontend_sections/configuration/hostgroups';
	const CONFIGURATION_HTTPCONF_EDIT =						'web_monitoring#configuring-a-web-scenario';
	const CONFIGURATION_ITEM_EDIT =							'config/items/item#configuration';
	const CONFIGURATION_ITEM_PROTOTYPE_EDIT =				'discovery/low_level_discovery/item_prototypes';
	const CONFIGURATION_MAINTENANCE_EDIT =					'maintenance#configuration';
	const CONFIGURATION_MAINTENANCE_LIST =					'web_interface/frontend_sections/configuration/maintenance';
	const CONFIGURATION_PROTOTYPE_GRAPH_EDIT =				'discovery/low_level_discovery/graph_prototypes';
	const CONFIGURATION_SERVICES_ACTION_LIST =				'web_interface/frontend_sections/services/service_actions';
	const CONFIGURATION_TEMPLATE_GRAPH_LIST =				'web_interface/frontend_sections/configuration/templates/graphs';
	const CONFIGURATION_TEMPLATES_GRAPH_PROTOTYPE_LIST =	'web_interface/frontend_sections/configuration/templates/discovery/graph_prototypes';
	const CONFIGURATION_TEMPLATE_ITEM_LIST =				'web_interface/frontend_sections/configuration/templates/items';
	const CONFIGURATION_TEMPLATES_ITEM_PROTOTYPE_LIST =		'web_interface/frontend_sections/configuration/templates/discovery/item_prototypes';
	const CONFIGURATION_TEMPLATE_TRIGGERS_LIST =			'web_interface/frontend_sections/configuration/templates/triggers';
	const CONFIGURATION_TEMPLATES_TRIGGER_PROTOTYPE_LIST =	'web_interface/frontend_sections/configuration/templates/discovery/trigger_prototypes';
	const CONFIGURATION_TEMPLATES_DISCOVERY_LIST =			'web_interface/frontend_sections/configuration/templates/discovery';
	const CONFIGURATION_TEMPLATES_EDIT =					'config/templates/template#creating-a-template';
	const CONFIGURATION_TEMPLATES_HTTPCONF_LIST =			'web_interface/frontend_sections/configuration/templates/web';
	const CONFIGURATION_TEMPLATES_LIST =					'web_interface/frontend_sections/configuration/templates';
	const CONFIGURATION_TRIGGER_PROTOTYPE_EDIT =			'discovery/low_level_discovery/trigger_prototypes';
	const CONFIGURATION_TEMPLATES_PROTOTYPE_LIST =			'web_interface/frontend_sections/configuration/templates/discovery/host_prototypes';
	const CONFIGURATION_TRIGGERS_EDIT =						'config/triggers/trigger#configuration';
	const DASHBOARD_PAGE_PROPERTIES_EDIT =					'web_interface/frontend_sections/monitoring/dashboard#adding-pages';
	const DASHBOARD_PROPERTIES_EDIT =						'web_interface/frontend_sections/monitoring/dashboard#creating-a-dashboard';
	const DASHBOARD_SHARE_EDIT =							'web_interface/frontend_sections/monitoring/dashboard#sharing';
	const INVENTORY_HOST_LIST =								'web_interface/frontend_sections/inventory/hosts';
	const INVENTORY_HOST_OVERVIEW =							'web_interface/frontend_sections/inventory/overview';
	const MONITORING_CHARTS_VIEW =							'web_interface/frontend_sections/monitoring/hosts/graphs';
	const MONITORING_DASHBOARD_LIST =						'web_interface/frontend_sections/monitoring/dashboard';
	const MONITORING_DASHBOARD_VIEW =						'web_interface/frontend_sections/monitoring/dashboard';
	const MONITORING_DASHBOARD_WIDGET_EDIT =				'web_interface/frontend_sections/monitoring/dashboard/widgets';
	const MONITORING_DISCOVERY_VIEW =						'web_interface/frontend_sections/monitoring/discovery';
	const MONITORING_HOST_DASHBOARD_VIEW =					'config/visualization/host_screens';
	const MONITORING_HOST_VIEW =							'web_interface/frontend_sections/monitoring/hosts';
	const MONITORING_HISTORY =								'web_interface/frontend_sections/monitoring/latest_data#graphs';
	const MONITORING_LATEST_VIEW =							'web_interface/frontend_sections/monitoring/latest_data';
	const MONITORING_PROBLEM_VIEW =							'web_interface/frontend_sections/monitoring/problems';
	const MONITORING_SYSMAP_EDIT =							'config/visualization/maps/map#creating-a-map';
	const MONITORING_SYSMAP_LIST =							'web_interface/frontend_sections/monitoring/maps';
	const MONITORING_MAP_VIEW =								'web_interface/frontend_sections/monitoring/maps#viewing-maps';
	const MONITORING_SYSMAP_CONSTRUCTOR =					'config/visualization/maps/map#overview';
	const MONITORING_WEB_VIEW =								'web_interface/frontend_sections/monitoring/hosts/web';
	const POPUP_ACKNOWLEDGE_EDIT =							'acknowledges#updating-problems';
	const POPUP_HOST_IMPORT =								'xml_export_import/hosts#importing';
	const POPUP_HTTP_STEP_EDIT =							'web_monitoring#configuring-steps';
	const POPUP_MAPS_IMPORT =								'xml_export_import/maps#importing';
	const POPUP_MAP_ELEMENT =								'config/visualization/maps/map#adding-elements';
	const POPUP_MAP_SHAPE =									'config/visualization/maps/map#adding-shapes';
	const POPUP_MAP_MASSUPDATE_SHAPES =						'config/visualization/maps/map#adding-shapes';
	const POPUP_MAP_MASSUPDATE_ELEMENTS =					'config/visualization/maps/map#selecting-elements';
	const POPUP_MASSUPDATE_HOST =							'config/hosts/hostupdate#using-mass-update';
	const POPUP_MASSUPDATE_ITEM =							'config/items/itemupdate#using-mass-update';
	const POPUP_MASSUPDATE_SERVICE =						'web_interface/frontend_sections/services/service#editing-services';
	const POPUP_MASSUPDATE_TEMPLATE =						'config/templates/mass#using-mass-update';
	const POPUP_MASSUPDATE_TRIGGER =						'config/triggers/update#using-mass-update';
	const POPUP_MEDIA_IMPORT =								'xml_export_import/media#importing';
	const POPUP_SERVICE_EDIT =								'web_interface/frontend_sections/services/service#service-configuration';
	const POPUP_SLA_EDIT =									'web_interface/frontend_sections/services/sla#configuration';
	const POPUP_TEMPLATE_IMPORT =							'xml_export_import/templates#importing';
	const POPUP_TOKEN_EDIT =								'web_interface/frontend_sections/administration/general#api-tokens';
	const POPUP_TEST_EDIT =									'config/items/item#testing';
	const QUEUE_DETAILS =									'web_interface/frontend_sections/administration/queue#list-of-waiting-items';
	const QUEUE_OVERVIEW =									'web_interface/frontend_sections/administration/queue#overview-by-item-type';
	const QUEUE_OVERVIEW_PROXY =							'web_interface/frontend_sections/administration/queue#overview-by-proxy';
	const REPORT_STATUS =									'web_interface/frontend_sections/reports/status_of_zabbix';
	const REPORT2 =											'web_interface/frontend_sections/reports/availability';
	const REPORT4 =											'web_interface/frontend_sections/reports/notifications';
	const REPORTS_AUDITLOG_LIST =							'web_interface/frontend_sections/reports/audit';
	const REPORTS_SCHEDULEDREPORT_EDIT =					'config/reports#configuration';
	const REPORTS_SCHEDULEDREPORT_LIST =					'web_interface/frontend_sections/reports/scheduled';
	const REPORTS_TOPTRIGGERS =								'web_interface/frontend_sections/reports/triggers_top';
	const SEARCH =											'web_interface/global_search';
	const SERVICE_LIST =									'web_interface/frontend_sections/services/service#viewing-services';
	const SERVICE_LIST_EDIT =								'web_interface/frontend_sections/services/service#editing-services';
	const SLA_LIST =										'web_interface/frontend_sections/services/sla#overview';
	const SLAREPORT_LIST =									'web_interface/frontend_sections/services/sla_report#overview';
	const TR_EVENTS =										'web_interface/frontend_sections/monitoring/problems#viewing-details';

	public static function getUrl($path): ?string {
		if (CBrandHelper::isRebranded()) {
			return null;
		}

		if (preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version)) {
			return ZBX_DOCUMENTATION_URL.'/'.$version[0].'/en/manual/'.$path;
		}

		return null;
	}
}
