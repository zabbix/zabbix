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


require_once dirname(__FILE__).'/../include/CWebTest.php';

use Facebook\WebDriver\WebDriverKeys;

/**
 * @dataSource Proxies
 *
 * @backup profiles, module, services, token
 *
 * @onBefore prepareServiceData
 */
class testDocumentationLinks extends CWebTest {

	public function prepareServiceData() {
		self::$version = substr(ZABBIX_VERSION, 0, 3);

		// Create a service.
		CDataHelper::call('service.create', [
			[
				'name' => 'Service_1',
				'algorithm' => 1,
				'sortorder' => 1
			]
		]);

		// Create an API token.
		CDataHelper::call('token.create', [
			[
				'name' => 'Admin token',
				'userid' => 1
			]
		]);
	}

	/**
	 * Major version of Zabbix the test is executed on.
	 */
	private static $version;

	/**
	 * Static start of each documentation link.
	 */
	private static $path_start = 'https://www.zabbix.com/documentation/';

	public static function getGeneralDocumentationLinkData() {
		return [
			// #0 Dashboard list.
			[
				[
					'url' => 'zabbix.php?action=dashboard.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards'
				]
			],
			// #1 Certain dashboard in view mode.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards'
				]
			],
			// #2 Create dashboard popup.
			[
				[
					'url' => 'zabbix.php?action=dashboard.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create dashboard'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#creating-a-dashboard'
				]
			],
			// #3 Widget Create popup.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Edit dashboard'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'id:dashboard-add-widget'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets'
				]
			],
			// #4 Widget edit form.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath:(//button[contains(@class, "btn-widget-edit")])[1]'
						]
					]
				]
			],
			// #5 Add dashboard page configuration popup.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#adding-pages',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Edit dashboard'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://button[@id="dashboard-add"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Add page"]'
						]
					]
				]
			],
			// #6 Global search view.
			[
				[
					'url' => 'zabbix.php?action=search&search=zabbix',
					'doc_link' => '/en/manual/web_interface/global_search'
				]
			],
			// #7 Problems view.
			[
				[
					'url' => 'zabbix.php?action=problem.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/problems'
				]
			],
			// #8 Event details view.
			[
				[
					'url' => 'tr_events.php?triggerid=100028&eventid=95',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/problems#viewing-details'
				]
			],
			// #9 Problems Mass update popup.
			[
				[
					'url' => 'zabbix.php?action=problem.view',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/acknowledgment#updating-problems'
				]
			],
			// #10 Problems acknowledge popup.
			[
				[
					'url' => 'zabbix.php?action=problem.view',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Update'
						]
					],
					'doc_link' => '/en/manual/acknowledgment#updating-problems'
				]
			],
			// #11 Monitoring -> Hosts view.
			[
				[
					'url' => 'zabbix.php?action=host.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/hosts'
				]
			],
			// #12 Create host popup in Monitoring -> Hosts view.
			[
				[
					'url' => 'zabbix.php?action=host.view',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create host'
						]
					],
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #13 Monitoring -> Graphs view.
			[
				[
					'url' => 'zabbix.php?action=charts.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/hosts/graphs'
				]
			],
			// #14 Monitoring -> Web monitoring view.
			[
				[
					'url' => 'zabbix.php?action=web.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/hosts/web'
				]
			],
			// #15 Monitoring -> Host dashboards view (dashboards of Zabbix server host).
			[
				[
					'url' => 'zabbix.php?action=host.dashboard.view&hostid=10084',
					'doc_link' => '/en/manual/config/visualization/host_screens'
				]
			],
			// #16 Latest data view.
			[
				[
					'url' => 'zabbix.php?action=latest.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/latest_data'
				]
			],
			// #17 Speccific item graph from latest data view.
			[
				[
					'url' => 'history.php?action=showgraph&itemids%5B%5D=42237',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/latest_data#graphs'
				]
			],
			// #18 Specific item history from latest data view.
			[
				[
					'url' => 'history.php?action=showvalues&itemids%5B%5D=42242',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/latest_data#graphs'
				]
			],
			// #19 Maps list view.
			[
				[
					'url' => 'sysmaps.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/maps'
				]
			],
			// #20 Create map form.
			[
				[
					'url' => 'sysmaps.php?form=Create+map',
					'doc_link' => '/en/manual/config/visualization/maps/map#creating-a-map'
				]
			],
			// #21 Map import popup.
			[
				[
					'url' => 'sysmaps.php',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Import'
						]
					],
					'doc_link' => '/en/manual/xml_export_import/maps#importing'
				]
			],
			// #22 View map view.
			[
				[
					'url' => 'zabbix.php?action=map.view&sysmapid=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/maps#viewing-maps'
				]
			],
			// #23 Edit map view.
			[
				[
					'url' => 'sysmap.php?sysmapid=1',
					'doc_link' => '/en/manual/config/visualization/maps/map#overview'
				]
			],
			// #24 Monitoring -> Discovery view.
			[
				[
					'url' => 'zabbix.php?action=discovery.view',
					'doc_link' => '/en/manual/web_interface/frontend_sections/monitoring/discovery'
				]
			],
			// #25 Monitoring -> Services in view mode.
			[
				[
					'url' => 'zabbix.php?action=service.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/service#viewing-services'
				]
			],
			// #26 Monitoring -> Services in edit mode.
			[
				[
					'url' => 'zabbix.php?action=service.list.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/service#editing-services'
				]
			],
			// #27 Service configuration form popup.
			[
				[
					'url' => 'zabbix.php?action=service.list.edit',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create service'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/service#editing-services'
				]
			],
			// #28 Service mass update popup.
			[
				[
					'url' => 'zabbix.php?action=service.list.edit',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/service#editing-services'
				]
			],
			// #29 List of service actions.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=4',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #30 Create service action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=4',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create action'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #31 SLA list view.
			[
				[
					'url' => 'zabbix.php?action=sla.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/sla#overview'
				]
			],
			// #32 SLA create form popup.
			[
				[
					'url' => 'zabbix.php?action=sla.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create SLA'
						]
					],
					'doc_link' => '/en/manual/it_services/sla#configuration'
				]
			],
			// #33 SLA report view.
			[
				[
					'url' => 'zabbix.php?action=slareport.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/services/sla_report#overview'
				]
			],
			// #34 Inventory overview view.
			[
				[
					'url' => 'hostinventoriesoverview.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/inventory/overview'
				]
			],
			// #35 Inventory hosts view.
			[
				[
					'url' => 'hostinventories.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/inventory/hosts'
				]
			],
			// #36 System information report view.
			[
				[
					'url' => 'zabbix.php?action=report.status',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/status_of_zabbix'
				]
			],
			// #37 Scheduled reports list view.
			[
				[
					'url' => 'zabbix.php?action=scheduledreport.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/scheduled'
				]
			],
			// #38 Scheduled report configuration form.
			[
				[
					'url' => 'zabbix.php?action=scheduledreport.edit',
					'doc_link' => '/en/manual/config/reports#configuration'
				]
			],
			// #39 Add scheduled report configuration popup from Dashboard view.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'doc_link' => '/en/manual/config/reports#configuration',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://button[@class="btn-action"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Create new report"]'
						]
					]
				]
			],
			// #40 Availability report view.
			[
				[
					'url' => 'report2.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/availability'
				]
			],
			// #41 Triggers top 100 report view.
			[
				[
					'url' => 'toptriggers.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/triggers_top'
				]
			],
			// #42 Audit log view.
			[
				[
					'url' => 'zabbix.php?action=auditlog.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/audit_log'
				]
			],
			// #43 Action log view.
			[
				[
					'url' => 'zabbix.php?action=actionlog.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/action_log'
				]
			],
			// #44 Notifications report view.
			[
				[
					'url' => 'report4.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/notifications'
				]
			],
			// #45 Host groups list view.
			[
				[
					'url' => 'zabbix.php?action=hostgroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hostgroups'
				]
			],
			// #46 Create host group popup.
			[
				[
					'url' => 'zabbix.php?action=hostgroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create host group'
						]
					],
					'open_button' => 'button:Create host group',
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #47 Edit host group popup.
			[
				[
					'url' => 'zabbix.php?action=hostgroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Zabbix servers"]'
						]
					],
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #47.1 Create host group form view (standalone).
			[
				[
					'url' => 'zabbix.php?action=hostgroup.edit',
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #48 Template list view.
			[
				[
					'url' => 'templates.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates'
				]
			],
			// #49 Create template view.
			[
				[
					'url' => 'templates.php?form=create',
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #50 Update template view.
			[
				[
					'url' => 'templates.php?form=update&templateid=10050',
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #51 Template import popup.
			[
				[
					'url' => 'templates.php',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Import'
						]
					],
					'doc_link' => '/en/manual/xml_export_import/templates#importing'
				]
			],
			// #52 Template mass update popup.
			[
				[
					'url' => 'templates.php',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/templates/mass#using-mass-update'
				]
			],
			// #53 Template items list view.
			[
				[
					'url' => 'items.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/items'
				]
			],
			// #54 Template item create form.
			[
				[
					'url' => 'items.php?form=create&hostid=15000&context=template',
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #55 Template item update form.
			[
				[
					'url' => 'items.php?form=update&hostid=15000&itemid=15000&context=template',
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #56 Template item test form.
			[
				[
					'url' => 'items.php?form=update&hostid=15000&itemid=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #57 Template item Mass update popup.
			[
				[
					'url' => 'items.php?filter_set=1&filter_hostids%5B0%5D=15000&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #58 Template trigger list view.
			[
				[
					'url' => 'triggers.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/triggers'
				]
			],
			// #59 Template trigger create form.
			[
				[
					'url' => 'triggers.php?hostid=15000&form=create&context=template',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #60 Template trigger update form.
			[
				[
					'url' => 'triggers.php?form=update&triggerid=99000&context=template',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #61 Template trigger Mass update popup.
			[
				[
					'url' => 'triggers.php?filter_set=1&filter_hostids%5B0%5D=15000&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #62 Template graph list view.
			[
				[
					'url' => 'graphs.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/graphs'
				]
			],
			// #63 Template graph create form.
			[
				[
					'url' => 'graphs.php?hostid=15000&form=create&context=template',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #64 Template graph update form.
			[
				[
					'url' => 'graphs.php?form=update&graphid=15000&context=template&filter_hostids%5B0%5D=15000',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #65 Template dashboards list view.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.list&templateid=10076&context=template',
					'doc_link' => '/en/manual/config/visualization/host_screens'
				]
			],
			// #66 Template dashboard create popup.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.list&templateid=10076&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create dashboard'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#creating-a-dashboard'
				]
			],
			// #67 Template dashboards view mode.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#creating-a-dashboard'
				]
			],
			// #68 Template dashboard widget create popup.
			// TODO: Uncomment the below case when ZBX-21929 will be merged.
//			[
//				[
//					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
//					'actions' => [
//						[
//							'callback' => 'openFormWithLink',
//							'element' => 'xpath:(//button[contains(@class, "btn-widget-edit")])[1]'
//						]
//					],
//					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets'
//				]
//			],
			// #69 Template dashboard widget edit popup.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'id:dashboard-add-widget'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets'
				]
			],
			// #70 Add Template dashboard page configuration popup.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#adding-pages',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://button[@id="dashboard-add"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Add page"]'
						]
					]
				]
			],
			// #71 Template LLD rule list view.
			[
				[
					'url' => 'host_discovery.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery'
				]
			],
			// #72 Template LLD rule configuration form.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=15000&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery#discovery-rule'
				]
			],
			// #73 Template LLD rule test form.
			[
				[
					'url' => 'host_discovery.php?form=update&itemid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #74 Template LLD item prototype list view.
			[
				[
					'url' => 'disc_prototypes.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/item_prototypes'
				]
			],
			// #75 Template LLD item prototype create form.
			[
				[
					'url' => 'disc_prototypes.php?form=create&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #76 Template LLD item prototype edit form.
			[
				[
					'url' => 'disc_prototypes.php?form=update&parent_discoveryid=15011&itemid=15021&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #77 Template LLD item prototype test form.
			[
				[
					'url' => 'items.php?form=update&hostid=40001&itemid=99102&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #78 Template LLD item prototype mass update popup.
			[
				[
					'url' => 'disc_prototypes.php?parent_discoveryid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #79 Template LLD trigger prototype list view.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/trigger_prototypes'
				]
			],
			// #80 Template LLD trigger prototype create form.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=15011&form=create&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #81 Template LLD trigger prototype edit form.
			[
				[
					'url' => 'trigger_prototypes.php?form=update&parent_discoveryid=15011&triggerid=99008&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #82 Template LLD trigger prototype mass update popup.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #83 Template LLD graph prototype list view.
			[
				[
					'url' => 'graphs.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/graph_prototypes'
				]
			],
			// #84 Template LLD graph prototype create form.
			[
				[
					'url' => 'graphs.php?form=create&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #85 Template LLD graph prototype edit form.
			[
				[
					'url' => 'graphs.php?form=update&parent_discoveryid=15011&graphid=15008&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #86 Template LLD host prototype list view.
			[
				[
					'url' => 'host_prototypes.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/host_prototypes'
				]
			],
			// #87 Template LLD host prototype create form.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/vm_monitoring#host-prototypes'
				]
			],
			// #88 Template LLD host prototype edit form.
			[
				[
					'url' => 'host_prototypes.php?form=update&parent_discoveryid=15011&hostid=99000&context=template',
					'doc_link' => '/en/manual/vm_monitoring#host-prototypes'
				]
			],
			// #89 Template Web scenario list view.
			[
				[
					'url' => 'httpconf.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/web'
				]
			],
			// #90 Template Web scenario create form.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=15000&context=template',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #91 Template Web scenario edit form.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=15000&httptestid=15000&context=template',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #92 Template Web scenario step configuration form popup.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=15000&httptestid=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[@id="tab_stepTab"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://div[@id="stepTab"]//button[text()="Add"]'
						]
					],
					'doc_link' => '/en/manual/web_monitoring#configuring-steps'
				]
			],
			// #93 Host list view.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts'
				]
			],
			// #94 Create host popup.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create host'
						]
					],
					'open_button' => 'button:Create host',
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #95 Edit host popup.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Simple form test host"]'
						]
					],
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #96 Create host form view (standalone).
			[
				[
					'url' => 'zabbix.php?action=host.edit',
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #97 Host import popup.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Import'
						]
					],
					'doc_link' => '/en/manual/xml_export_import/hosts#importing'
				]
			],
			// #98 Host mass update popup.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/hosts/hostupdate#using-mass-update'
				]
			],
			// #99 Host items list view.
			[
				[
					'url' => 'items.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/items'
				]
			],
			// #100 Host item create form.
			[
				[
					'url' => 'items.php?form=create&hostid=40001&context=host',
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #101 Host item update form.
			[
				[
					'url' => 'items.php?form=update&hostid=40001&itemid=99102&context=host',
					'doc_link' => '/en/manual/config/items/item#configuration'

				]
			],
			// #102 Host item test form.
			[
				[
					'url' => 'items.php?form=update&hostid=40001&itemid=99102&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #103 Host item Mass update popup.
			[
				[
					'url' => 'items.php?filter_set=1&filter_hostids%5B0%5D=40001&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #104 Host trigger list view.
			[
				[
					'url' => 'triggers.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/triggers'
				]
			],
			// #105 Host trigger create form.
			[
				[
					'url' => 'triggers.php?hostid=40001&form=create&context=host',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #106 Host trigger update form.
			[
				[
					'url' => 'triggers.php?form=update&triggerid=14000&context=host',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #107 Host trigger Mass update popup.
			[
				[
					'url' => 'triggers.php?filter_set=1&filter_hostids%5B0%5D=40001&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #108 Host graph list view.
			[
				[
					'url' => 'graphs.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/graphs'
				]
			],
			// #109 Host graph create form.
			[
				[
					'url' => 'graphs.php?hostid=40001&form=create&context=host',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #110 Host graph update form.
			[
				[
					'url' => 'graphs.php?form=update&graphid=300000&context=host&filter_hostids%5B0%5D=40001',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #111 Host LLD rule list view.
			[
				[
					'url' => 'host_discovery.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery'
				]
			],
			// #112 Host LLD rule configuration form.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=40001&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery#discovery-rule'
				]
			],
			// #113 Host LLD rule test form.
			[
				[
					'url' => 'host_discovery.php?form=update&itemid=90001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #114 Host LLD item prototype list view.
			[
				[
					'url' => 'disc_prototypes.php?parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/item_prototypes'
				]
			],
			// #115 Host LLD item prototype create form.
			[
				[
					'url' => 'disc_prototypes.php?form=create&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #116 Host LLD item prototype edit form.
			[
				[
					'url' => 'disc_prototypes.php?form=update&parent_discoveryid=133800&itemid=23800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #117 Host LLD item prototype test form.
			[
				[
					'url' => 'disc_prototypes.php?form=update&parent_discoveryid=133800&itemid=23800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #118 Host LLD item prototype mass update popup.
			[
				[
					'url' => 'disc_prototypes.php?cancel=1&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #119 Host LLD trigger prototype list view.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/trigger_prototypes'
				]
			],
			// #120 Host LLD trigger prototype create form.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=133800&form=create&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #121 Host LLD trigger prototype edit form.
			[
				[
					'url' => 'trigger_prototypes.php?form=update&parent_discoveryid=133800&triggerid=99518&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #122 Host LLD trigger prototype mass update popup.
			[
				[
					'url' => 'trigger_prototypes.php?cancel=1&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #123 Host LLD graph prototype list view.
			[
				[
					'url' => 'graphs.php?parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/graph_prototypes'
				]
			],
			// #124 Host LLD graph prototype create form.
			[
				[
					'url' => 'graphs.php?form=create&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #125 Host LLD graph prototype edit form.
			[
				[
					'url' => 'graphs.php?form=update&parent_discoveryid=133800&graphid=600000&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #126 Host LLD host prototype list view.
			[
				[
					'url' => 'host_prototypes.php?parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/host_prototypes'
				]
			],
			// #127 Host LLD host prototype create form.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/vm_monitoring#host-prototypes'
				]
			],
			// #128 Host LLD host prototype edit form.
			[
				[
					'url' => 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=99200&context=host',
					'doc_link' => '/en/manual/vm_monitoring#host-prototypes'
				]
			],
			// #129 Host Web scenario list view.
			[
				[
					'url' => 'httpconf.php?filter_set=1&filter_hostids%5B0%5D=40001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/web'
				]
			],
			// #130 Host Web scenario create form.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=40001&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #131 Host Web scenario edit form.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=40001&httptestid=94&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #132 Host Web scenario step configuration form popup.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=40001&httptestid=94&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[@id="tab_stepTab"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://div[@id="stepTab"]//button[text()="Add"]'
						]
					],
					'doc_link' => '/en/manual/web_monitoring#configuring-steps'
				]
			],
			// #133 Maintenance list view.
			[
				[
					'url' => 'maintenance.php',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/maintenance'
				]
			],
			// #134 Create maintenance form view.
			[
				[
					'url' => 'maintenance.php?form=create',
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #135 Edit maintenance form view.
			[
				[
					'url' => 'maintenance.php?form=update&maintenanceid=4',
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #136 Trigger actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=0',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #137 Create trigger action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=0',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create action'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #138 Edit trigger action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=0',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Report problems to Zabbix administrators"]'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #139 Discovery actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #140 Create discovery action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=1',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create action'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #141 Edit discovery action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=1',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Auto discovery. Linux servers."]'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #142 Autoregistration actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #143 Create autoregistration action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=2',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create action'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #144 Edit autoregistration action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=2',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Autoregistration action 1"]'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #145 Internal actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #146 Create internal action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=3',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create action'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #147 Edit internal action form popup.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=3',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Report not supported items"]'
						]
					],
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #148 Event correlation list view.
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/correlation'
				]
			],
			// #149 Create event correlation form view.
			[
				[
					'url' => 'zabbix.php?action=correlation.edit',
					'doc_link' => '/en/manual/config/event_correlation/global#configuration'
				]
			],
			// #150 Edit event correlation form view.
			[
				[
					'url' => 'zabbix.php?correlationid=99002&action=correlation.edit',
					'doc_link' => '/en/manual/config/event_correlation/global#configuration'
				]
			],
			// #151 Network discovery list view.
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/discovery'
				]
			],
			// #152 Create network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=discovery.edit',
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #153 Edit network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=discovery.edit&druleid=2',
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #154 Administration -> General -> GUI view.
			[
				[
					'url' => 'zabbix.php?action=gui.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#gui'
				]
			],
			// #155 Administration -> General -> Autoregistration view.
			[
				[
					'url' => 'zabbix.php?action=autoreg.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#autoregistration'
				]
			],
			// #156 Administration -> General -> Housekeeping view.
			[
				[
					'url' => 'zabbix.php?action=housekeeping.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/housekeeping'
				]
			],
			// #157 Administration -> General -> Audit log view.
			[
				[
					'url' => 'zabbix.php?action=audit.settings.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/audit_log'
				]
			],
			// #158 Administration -> General -> Images -> Icon view.
			[
				[
					'url' => 'zabbix.php?action=image.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #159 Administration -> General -> Images -> Background view.
			[
				[
					'url' => 'zabbix.php?action=image.list&imagetype=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #160 Administration -> General -> Images -> Create image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imagetype=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #161 Administration -> General -> Images -> Edit image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imageid=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #162 Administration -> General -> Images -> Create background view.
			[
				[
					'url' => 'zabbix.php?action=image.list&imagetype=2',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create background'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #163 Administration -> General -> Icon mapping list view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #164 Administration -> General -> Icon mapping -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #165 Administration -> General -> Icon mapping -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit&iconmapid=101',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #166 Administration -> General -> Regular expressions list view.
			[
				[
					'url' => 'zabbix.php?action=regex.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#regular-expressions'
				]
			],
			// #167 Administration -> General -> Regular expressions -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #168 Administration -> General -> Regular expressions -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit&regexid=3',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #169 Administration -> General -> Macros view.
			[
				[
					'url' => 'zabbix.php?action=macros.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/macros'
				]
			],
			// #170 Administration -> General -> Trigger displaying options view.
			[
				[
					'url' => 'zabbix.php?action=trigdisplay.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#trigger-displaying-options'
				]
			],
			// #171 Administration -> General -> Geographical maps view.
			[
				[
					'url' => 'zabbix.php?action=geomaps.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#geographical-maps'
				]
			],
			// #172 Administration -> General -> Modules list view.
			[
				[
					'url' => 'zabbix.php?action=module.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#modules'
				]
			],
			// #173 Administration -> General -> Module edit view.
			[
				[
					'url' => 'zabbix.php?action=module.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Scan directory'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:1st Module name'
						]
					],
					'doc_link' => '/en/manual/modules#manifest-preparation'
				]
			],
			// #174 Administration -> General -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #175 Administration -> General -> Api tokens -> Create Api token popup.
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create API token'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #176 Administration -> General -> Api tokens -> Edit Api token popup.
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Admin token'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #177 Administration -> General -> Other view.
			[
				[
					'url' => 'zabbix.php?action=miscconfig.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#other-parameters'
				]
			],
			// #178 Administration -> Proxy list view.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/proxies'
				]
			],
			// #179 Administration -> Create proxy view.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create proxy'
						]
					],
					'doc_link' => '/en/manual/distributed_monitoring/proxies#configuration'
				]
			],
			// #180 Administration -> Proxies -> Edit proxy view.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Active proxy 1'
						]
					],
					'doc_link' => '/en/manual/distributed_monitoring/proxies#configuration'
				]
			],
			// #181 Administration -> Authentication view.
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/authentication'
				]
			],
			// #182 Administration -> User groups list view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_groups'
				]
			],
			// #183 Administration -> User groups -> Create user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #184 Administration -> User groups -> Edit user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit&usrgrpid=7',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #185 Administration -> User roles list view.
			[
				[
					'url' => 'zabbix.php?action=userrole.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles'
				]
			],
			// #186 Administration -> User roles -> Create form view.
			[
				[
					'url' => '/zabbix.php?action=userrole.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #187 Administration -> User roles -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=userrole.edit&roleid=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #188 Administration -> Users list view.
			[
				[
					'url' => 'zabbix.php?action=user.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_list'
				]
			],
			// #189 Administration -> Users -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #190 Administration -> Users -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit&userid=1',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #191 Administration -> Media type list view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/mediatypes'
				]
			],
			// #192 Administration -> Media type -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.edit',
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #193 Administration -> Media type -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.edit&mediatypeid=1',
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #194 Administration -> Media type -> Import view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Import'
						]
					],
					'doc_link' => '/en/manual/xml_export_import/media#importing'
				]
			],
			// #195 Administration -> Scripts list view.
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts'
				]
			],
			// #196 Administration -> Scripts -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=script.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #197 Administration -> Scripts -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=script.edit&scriptid=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #198 Administration -> Queue overview view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-item-type'
				]
			],
			// #199 Administration -> Queue overview by proxy view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview.proxy',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-proxy'
				]
			],
			// #200 Administration -> Queue details view.
			[
				[
					'url' => 'zabbix.php?action=queue.details',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#list-of-waiting-items'
				]
			],
			// #201 User profile view.
			[
				[
					'url' => 'zabbix.php?action=userprofile.edit',
					'doc_link' => '/en/manual/web_interface/user_profile#user-profile'
				]
			],
			// #202 User settings -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'doc_link' => '/en/manual/web_interface/user_profile#api-tokens'
				]
			],
			// #203 User settings -> Api tokens -> Create Api token popup.
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create API token'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #204 User settings -> Api tokens -> Edit Api token popup.
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Admin token'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			/** TODO: uncomment and change doc links after ZBXNEXT-2592
			// #205 Template groups list view.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/configuration/hostgroups'
				]
			],
			// #206 Create template group popup.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create template group'
						]
					],
					'open_button' => 'button:Create template group',
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #207 Edit template group popup.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Templates"]'
						]
					],
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #208 Create template group form view (standalone).
			[
				[
					'url' => 'zabbix.php?action=templategroup.edit',
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			]
			 *
			 */
		];
	}

	/**
	 * @dataProvider getGeneralDocumentationLinkData
	 */
	public function testDocumentationLinks_checkGeneralLinks($data) {
		$this->page->login()->open($data['url'])->waitUntilReady();

		// Execute the corresponding callback function to open the form with doc link.
		if (array_key_exists('actions', $data)) {
			foreach ($data['actions'] as $action) {
				call_user_func_array([$this, $action['callback']], [CTestArrayHelper::get($action, 'element', null)]);
			}
		}

		$dialog = COverlayDialogElement::find()->one(false);
		$location = ($dialog->isValid()) ? $dialog->waitUntilReady() : $this;

		// Get the documentation link and compare it with expected result.
		$link = $location->query('class:icon-doc-link')->one();
		$this->assertEquals(self::$path_start.self::$version.$data['doc_link'], $link->getAttribute('href'));

		// If the link was located in a popup - close this popup.
		if ($dialog->isValid()) {
			$location->close();
		}

		// Cancel element creation/update if it impacts execution of next cases and close alert.
		$cancel_button = $this->query('id:dashboard-cancel')->one(false);
		if ($cancel_button->isClickable()) {
			$cancel_button->click();

			// Close alert if it prevents cancellation of element creation/update.
			if ($this->page->isAlertPresent()) {
				$this->page->acceptAlert();
			}
		}
	}

	/**
	 * Find and click on the element that leads to the form with the link.
	 *
	 * @param string  $locator		locator of the element that needs to be clicked to open form with doc link
	 */
	private function openFormWithLink($locator) {
		$this->query($locator)->waitUntilPresent()->one()->click();
	}

	/*
	 * Open the Mass update overlay dialog.
	 */
	private function openMassUpdate() {
		$this->query('xpath://input[contains(@id, "all_")]')->asCheckbox()->one()->set(true);
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();
	}

	public static function getMapDocumentationLinkData() {
		return [
			// #0 Edit element form.
			[
				[
					'element' => 'xpath://div[@data-id="3"]',
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-elements'
				]
			],
			// #1 Edit shape form.
			[
				[
					'element' => 'xpath://div[@data-id="101"]',
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-shapes'
				]
			],
			// #2 Edit element selection.
			[
				[
					'element' => ['xpath://div[@data-id="7"]', 'xpath://div[@data-id="5"]'],
					'doc_link' => '/en/manual/config/visualization/maps/map#selecting-elements'
				]
			],
			// #3 Edit shape selection.
			[
				[
					'element' => ['xpath://div[@data-id="100"]', 'xpath://div[@data-id="101"]'],
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-shapes'
				]
			]
		];
	}

	/**
	 * @dataProvider getMapDocumentationLinkData
	 */
	public function testDocumentationLinks_checkMapElementLinks($data) {
		$this->page->login()->open('sysmap.php?sysmapid=3')->waitUntilReady();

		// Checking element selection documentation links requires pressing control key when selecting elements.
		if (is_array($data['element'])) {
			$keyboard = CElementQuery::getDriver()->getKeyboard();
			$keyboard->pressKey(WebDriverKeys::LEFT_CONTROL);

			foreach ($data['element'] as $element) {
				$this->query($element)->one()->click();
			}

			$keyboard->releaseKey(WebDriverKeys::LEFT_CONTROL);
		}
		else {
			$this->query($data['element'])->one()->click();
		}

		$dialog = $this->query('id:map-window')->one()->waitUntilVisible();

		// Maps contain headers for all map elements, so only the visible one should be checked.
		$link = $dialog->query('class:icon-doc-link')->all()->filter(new CElementFilter(CElementFilter::VISIBLE))->first();

		$this->assertEquals(self::$path_start.self::$version.$data['doc_link'], $link->getAttribute('href'));
	}
}
