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


class CDocHelper {

	const ADMINISTRATION_ACTIONLOG_LIST =						'web_interface/frontend_sections/reports/action_log';
	const ADMINISTRATION_AUDITLOG_EDIT =						'web_interface/frontend_sections/administration/audit_log';
	const ADMINISTRATION_AUTOREG_EDIT =							'web_interface/frontend_sections/administration/general#autoregistration';
	const ADMINISTRATION_CONNECTOR_LIST =						'web_interface/frontend_sections/administration/general#connectors';
	const ADMINISTRATION_GEOMAPS_EDIT =							'web_interface/frontend_sections/administration/general#geographical-maps';
	const ADMINISTRATION_GUI_EDIT =								'web_interface/frontend_sections/administration/general#gui';
	const ADMINISTRATION_HOUSEKEEPING_EDIT =					'web_interface/frontend_sections/administration/housekeeping';
	const ADMINISTRATION_ICONMAP_EDIT =							'web_interface/frontend_sections/administration/general#icon-mapping';
	const ADMINISTRATION_ICONMAP_LIST =							'web_interface/frontend_sections/administration/general#icon-mapping';
	const ADMINISTRATION_IMAGE_EDIT =							'web_interface/frontend_sections/administration/general#images';
	const ADMINISTRATION_IMAGE_LIST =							'web_interface/frontend_sections/administration/general#images';
	const ADMINISTRATION_MACROS_EDIT =							'web_interface/frontend_sections/administration/macros';
	const ADMINISTRATION_MISCCONFIG_EDIT =						'web_interface/frontend_sections/administration/general#other-parameters';
	const ADMINISTRATION_MODULE_EDIT =							'extensions/frontendmodules#manifest-preparation';
	const ADMINISTRATION_MODULE_LIST =							'web_interface/frontend_sections/administration/general#modules';
	const ADMINISTRATION_PROXY_EDIT =							'distributed_monitoring/proxies#configuration';
	const ADMINISTRATION_PROXY_LIST =							'web_interface/frontend_sections/administration/proxies';
	const ADMINISTRATION_PROXY_GROUP_EDIT =						'distributed_monitoring/proxies/ha';
	const ADMINISTRATION_PROXY_GROUP_LIST =						'web_interface/frontend_sections/administration/proxy_groups';
	const ADMINISTRATION_REGEX_EDIT =							'regular_expressions#global-regular-expressions';
	const ADMINISTRATION_REGEX_LIST =							'web_interface/frontend_sections/administration/general#regular-expressions';
	const ADMINISTRATION_TIMEOUTS =								'web_interface/frontend_sections/administration/general#timeouts';
	const ADMINISTRATION_TRIGDISPLAY_EDIT =						'web_interface/frontend_sections/administration/general#trigger-displaying-options';
	const ALERTS_ACTION_EDIT =									'config/notifications/action#configuring-an-action';
	const ALERTS_ACTION_LIST =									'web_interface/frontend_sections/alerts/actions';
	const ALERTS_MEDIATYPE_EDIT =								'config/notifications/media#common-parameters';
	const ALERTS_MEDIATYPE_LIST =								'web_interface/frontend_sections/alerts/mediatypes';
	const ALERTS_SCRIPT_EDIT =									'web_interface/frontend_sections/alerts/scripts#configuring-a-global-script';
	const ALERTS_SCRIPT_LIST =									'web_interface/frontend_sections/alerts/scripts';
	const CONFIGURATION_DASHBOARDS_EDIT =						'web_interface/frontend_sections/dashboards#creating-a-dashboard';
	const CONFIGURATION_DASHBOARDS_LIST =						'config/visualization/host_screens';
	const DASHBOARDS_LIST =										'web_interface/frontend_sections/dashboards';
	const DASHBOARDS_VIEW =										'web_interface/frontend_sections/dashboards';
	const DASHBOARDS_PAGE_PROPERTIES_EDIT =						'web_interface/frontend_sections/dashboards#adding-pages';
	const DASHBOARDS_PROPERTIES_EDIT =							'web_interface/frontend_sections/dashboards#creating-a-dashboard';
	const DASHBOARDS_SHARE_EDIT =								'web_interface/frontend_sections/dashboards#sharing';
	const DASHBOARDS_WIDGET_EDIT =								'web_interface/frontend_sections/dashboards/widgets';
	const DATA_COLLECTION_CORRELATION_EDIT =					'config/event_correlation/global#configuration';
	const DATA_COLLECTION_CORRELATION_LIST =					'web_interface/frontend_sections/data_collection/correlation';
	const DATA_COLLECTION_DISCOVERY_EDIT =						'discovery/network_discovery/rule#rule-attributes';
	const DATA_COLLECTION_DISCOVERY_LIST =						'web_interface/frontend_sections/data_collection/discovery';
	const DATA_COLLECTION_GRAPH_EDIT =							'config/visualization/graphs/custom#configuring-custom-graphs';
	const DATA_COLLECTION_HOST_GRAPH_PROTOTYPE_LIST = 			'web_interface/frontend_sections/data_collection/hosts/discovery/graph_prototypes';
	const DATA_COLLECTION_HOST_DISCOVERY_EDIT =					'discovery/low_level_discovery#discovery-rule';
	const DATA_COLLECTION_HOST_DISCOVERY_LIST =					'web_interface/frontend_sections/data_collection/hosts/discovery';
	const DATA_COLLECTION_HOST_GRAPH_LIST =						'web_interface/frontend_sections/data_collection/hosts/graphs';
	const DATA_COLLECTION_HOST_HTTPCONF_LIST =					'web_interface/frontend_sections/data_collection/hosts/web';
	const DATA_COLLECTION_HOST_ITEM_LIST =						'web_interface/frontend_sections/data_collection/hosts/items';
	const DATA_COLLECTION_HOST_ITEM_PROTOTYPE_LIST =			'web_interface/frontend_sections/data_collection/hosts/discovery/item_prototypes';
	const DATA_COLLECTION_HOST_EDIT =							'config/hosts/host#configuration';
	const DATA_COLLECTION_HOST_LIST =							'web_interface/frontend_sections/data_collection/hosts';
	const DATA_COLLECTION_HOST_PROTOTYPE_EDIT =					'discovery/low_level_discovery/host_prototypes';
	const DATA_COLLECTION_HOST_PROTOTYPE_LIST =					'web_interface/frontend_sections/data_collection/hosts/discovery/host_prototypes';
	const DATA_COLLECTION_HOST_TRIGGERS_LIST =					'web_interface/frontend_sections/data_collection/hosts/triggers';
	const DATA_COLLECTION_HOST_TRIGGER_PROTOTYPE_LIST =			'web_interface/frontend_sections/data_collection/hosts/discovery/trigger_prototypes';
	const DATA_COLLECTION_HOSTGROUPS_EDIT =						'config/hosts/host#creating-a-host-group';
	const DATA_COLLECTION_HOSTGROUPS_LIST =						'web_interface/frontend_sections/data_collection/hostgroups';
	const DATA_COLLECTION_HTTPCONF_EDIT =						'web_monitoring#configuring-a-web-scenario';
	const DATA_COLLECTION_ITEM_EDIT =							'config/items/item#configuration';
	const DATA_COLLECTION_ITEM_PROTOTYPE_EDIT =					'discovery/low_level_discovery/item_prototypes';
	const DATA_COLLECTION_MAINTENANCE_EDIT =					'maintenance#configuration';
	const DATA_COLLECTION_MAINTENANCE_LIST =					'web_interface/frontend_sections/data_collection/maintenance';
	const DATA_COLLECTION_PROTOTYPE_GRAPH_EDIT =				'discovery/low_level_discovery/graph_prototypes';
	const DATA_COLLECTION_TEMPLATE_GROUPS_EDIT =				'config/templates/template#creating-a-template-group';
	const DATA_COLLECTION_TEMPLATE_GROUPS_LIST =				'web_interface/frontend_sections/data_collection/templategroups';
	const DATA_COLLECTION_TEMPLATE_GRAPH_LIST =					'web_interface/frontend_sections/data_collection/templates/graphs';
	const DATA_COLLECTION_TEMPLATES_GRAPH_PROTOTYPE_LIST =		'web_interface/frontend_sections/data_collection/templates/discovery/graph_prototypes';
	const DATA_COLLECTION_TEMPLATE_ITEM_LIST =					'web_interface/frontend_sections/data_collection/templates/items';
	const DATA_COLLECTION_TEMPLATES_ITEM_PROTOTYPE_LIST =		'web_interface/frontend_sections/data_collection/templates/discovery/item_prototypes';
	const DATA_COLLECTION_TEMPLATE_TRIGGERS_LIST =				'web_interface/frontend_sections/data_collection/templates/triggers';
	const DATA_COLLECTION_TEMPLATES_TRIGGER_PROTOTYPE_LIST =	'web_interface/frontend_sections/data_collection/templates/discovery/trigger_prototypes';
	const DATA_COLLECTION_TEMPLATES_DISCOVERY_LIST =			'web_interface/frontend_sections/data_collection/templates/discovery';
	const DATA_COLLECTION_TEMPLATES_EDIT =						'config/templates/template#creating-a-template';
	const DATA_COLLECTION_TEMPLATES_HTTPCONF_LIST =				'web_interface/frontend_sections/data_collection/templates/web';
	const DATA_COLLECTION_TEMPLATES_LIST =						'web_interface/frontend_sections/data_collection/templates';
	const DATA_COLLECTION_TRIGGER_PROTOTYPE_EDIT =				'discovery/low_level_discovery/trigger_prototypes';
	const DATA_COLLECTION_TEMPLATES_PROTOTYPE_LIST =			'web_interface/frontend_sections/data_collection/templates/discovery/host_prototypes';
	const DATA_COLLECTION_TRIGGERS_EDIT =						'config/triggers/trigger#configuration';
	const INVENTORY_HOST_LIST =									'web_interface/frontend_sections/inventory/hosts';
	const INVENTORY_HOST_OVERVIEW =								'web_interface/frontend_sections/inventory/overview';
	const ITEM_TYPES_DB_MONITOR =								'config/items/itemtypes/odbc_checks';
	const ITEM_TYPES_IPMI_AGENT =								'config/items/itemtypes/ipmi';
	const ITEM_TYPES_JMX_AGENT =								'config/items/itemtypes/jmx_monitoring';
	const ITEM_TYPES_SIMPLE_CHECK =								'config/items/itemtypes/simple_checks';
	const ITEM_TYPES_SNMP_TRAP =								'config/items/itemtypes/snmptrap';
	const ITEM_TYPES_ZABBIX_AGENT =								'config/items/itemtypes/zabbix_agent';
	const ITEM_TYPES_ZABBIX_INTERNAL =							'config/items/itemtypes/internal';
	const MONITORING_CHARTS_VIEW =								'web_interface/frontend_sections/monitoring/hosts/graphs';
	const MONITORING_DISCOVERY_VIEW =							'web_interface/frontend_sections/monitoring/discovery';
	const MONITORING_HOST_DASHBOARD_VIEW =						'config/visualization/host_screens';
	const MONITORING_HOST_VIEW =								'web_interface/frontend_sections/monitoring/hosts';
	const MONITORING_HISTORY =									'web_interface/frontend_sections/monitoring/latest_data#graphs';
	const MONITORING_LATEST_VIEW =								'web_interface/frontend_sections/monitoring/latest_data';
	const MONITORING_PROBLEMS_VIEW =							'web_interface/frontend_sections/monitoring/problems';
	const MONITORING_SYSMAP_EDIT =								'config/visualization/maps/map#creating-a-map';
	const MONITORING_SYSMAP_LIST =								'web_interface/frontend_sections/monitoring/maps';
	const MONITORING_MAP_VIEW =									'web_interface/frontend_sections/monitoring/maps#viewing-maps';
	const MONITORING_SYSMAP_CONSTRUCTOR =						'config/visualization/maps/map#overview';
	const MONITORING_WEB_VIEW =									'web_interface/frontend_sections/monitoring/hosts/web';
	const POPUP_ACKNOWLEDGMENT_EDIT =							'acknowledgment#updating-problems';
	const POPUP_CONNECTOR_EDIT =								'config/export/streaming#configuration';
	const POPUP_HOST_IMPORT =									'xml_export_import/hosts#importing';
	const POPUP_HTTP_STEP_EDIT =								'web_monitoring#configuring-steps';
	const POPUP_MAPS_IMPORT =									'xml_export_import/maps#importing';
	const POPUP_MAP_ELEMENT =									'config/visualization/maps/map#adding-elements';
	const POPUP_MAP_SHAPE =										'config/visualization/maps/map#adding-shapes';
	const POPUP_MAP_MASSUPDATE_SHAPES =							'config/visualization/maps/map#adding-shapes';
	const POPUP_MAP_MASSUPDATE_ELEMENTS =						'config/visualization/maps/map#selecting-elements';
	const POPUP_MASSUPDATE_HOST =								'config/hosts/hostupdate#using-mass-update';
	const POPUP_MASSUPDATE_ITEM =								'config/items/itemupdate#using-mass-update';
	const POPUP_MASSUPDATE_SERVICE =							'web_interface/frontend_sections/services/service#editing-services';
	const POPUP_MASSUPDATE_TEMPLATE =							'config/templates/mass#using-mass-update';
	const POPUP_MASSUPDATE_TRIGGER =							'config/triggers/update#using-mass-update';
	const POPUP_MEDIA_IMPORT =									'xml_export_import/media#importing';
	const POPUP_SERVICE_EDIT =									'web_interface/frontend_sections/services/service#editing-services';
	const POPUP_SLA_EDIT =										'it_services/sla#configuration';
	const POPUP_TEMPLATE_IMPORT =								'xml_export_import/templates#importing';
	const POPUP_TOKEN_EDIT =									'web_interface/frontend_sections/users/api_tokens';
	const POPUP_TEST_EDIT =										'config/items/item#testing';
	const QUEUE_DETAILS =										'web_interface/frontend_sections/administration/queue#list-of-waiting-items';
	const QUEUE_OVERVIEW =										'web_interface/frontend_sections/administration/queue#overview-by-item-type';
	const QUEUE_OVERVIEW_PROXY =								'web_interface/frontend_sections/administration/queue#overview-by-proxy';
	const REPORT_STATUS =										'web_interface/frontend_sections/reports/status_of_zabbix';
	const REPORT4 =												'web_interface/frontend_sections/reports/notifications';
	const REPORTS_AUDITLOG_LIST =								'web_interface/frontend_sections/reports/audit_log';
	const REPORTS_AVAILABILITYREPORT_LIST =						'web_interface/frontend_sections/reports/availability';
	const REPORTS_SCHEDULEDREPORT_EDIT =						'config/reports#configuration';
	const REPORTS_SCHEDULEDREPORT_LIST =						'web_interface/frontend_sections/reports/scheduled';
	const REPORTS_TOPTRIGGERS =									'web_interface/frontend_sections/reports/triggers_top';
	const SEARCH =												'web_interface/global_search';
	const SERVICES_SERVICE_LIST =								'web_interface/frontend_sections/services/service#viewing-services';
	const SERVICES_SERVICE_EDIT =								'web_interface/frontend_sections/services/service#editing-services';
	const SERVICES_SLA_LIST =									'web_interface/frontend_sections/services/sla#overview';
	const SERVICES_SLAREPORT_LIST =								'web_interface/frontend_sections/services/sla_report#overview';
	const TR_EVENTS =											'web_interface/frontend_sections/monitoring/problems#viewing-details';
	const USERS_AUTHENTICATION_EDIT =							'web_interface/frontend_sections/users/authentication';
	const USERS_TOKEN_LIST =									'web_interface/frontend_sections/users/api_tokens';
	const USERS_USER_EDIT =										'config/users_and_usergroups/user';
	const USERS_USER_LIST =										'web_interface/frontend_sections/users/user_list';
	const USERS_USER_TOKEN_LIST =								'web_interface/user_profile#api-tokens';
	const USERS_USERGROUP_EDIT =								'config/users_and_usergroups/usergroup#configuration';
	const USERS_USERGROUP_LIST =								'web_interface/frontend_sections/users/user_groups';
	const USERS_USERPROFILE_EDIT =								'web_interface/user_profile#user-profile';
	const USERS_USERROLE_EDIT =									'web_interface/frontend_sections/users/user_roles#default-user-roles';
	const USERS_USERROLE_LIST =									'web_interface/frontend_sections/users/user_roles';

	public static function getUrl($path): string {
		if (CBrandHelper::isRebranded()) {
			return '';
		}

		if (preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version)) {
			return ZBX_DOCUMENTATION_URL.'/'.$version[0].'/en/manual/'.$path;
		}

		return '';
	}
}
