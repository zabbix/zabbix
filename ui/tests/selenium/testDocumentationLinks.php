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


require_once dirname(__FILE__).'/../include/CWebTest.php';

use Facebook\WebDriver\WebDriverKeys;

/**
 * @dataSource Actions, Maps, Proxies
 *
 * @backup profiles, module, services, token, connector
 *
 * @onBefore prepareData
 */
class testDocumentationLinks extends CWebTest {

	// LLD and host prototype for case 'Host LLD host prototype edit form'.
	protected static $lldid;
	protected static $host_prototypeid;

	public function prepareData() {
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

		// Create a Connector.
		CDataHelper::call('connector.create', [
			[
				'name' => 'Default connector',
				'url' => '{$URL}'
			]
		]);

		// Create event correlation.
		CDataHelper::call('correlation.create', [
			[
				'name' => 'Event correlation for links check',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'links tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			]
		]);

		CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for documentation links test',
				'maintenance_type' => MAINTENANCE_TYPE_NODATA,
				'active_since' => 1534885200,
				'active_till' => 1534971600,
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'timeperiods' => [[]]
			]
		]);

		// Create host prototype.
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host with host prototype for documentations links',
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for documentation links check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		self::$lldid = $response['discoveryruleids']['Host with host prototype for documentations links:drule'];

		CDataHelper::call('hostprototype.create', [
			[
				'host' => 'Host prototype for documentation links test {#H}',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> 4]] // Zabbix servers.
			]
		]);
		$prototype_hostids = CDataHelper::getIds('host');
		self::$host_prototypeid = $prototype_hostids['Host prototype for documentation links test {#H}'];
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
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/action_log'
				]
			],
			// #4 Widget edit form.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/top_hosts',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath:(//button[contains(@class, "js-widget-edit")])[1]'
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
					'url' => 'tr_events.php?triggerid=100032&eventid=9000',
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
							'element' => 'xpath://button[@id="dashboard-actions"]'
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
					'url' => 'zabbix.php?action=availabilityreport.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/reports/availability'
				]
			],
			// #41 Top 100 triggers report view.
			[
				[
					'url' => 'zabbix.php?action=toptriggers.list',
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
			// #49 Template list view.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates'
				]
			],
			// #50 Create template view.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create template'
						]
					],
					'open_button' => 'button:Create template',
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #51 Update template view.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'actions' => [
								[
									'callback' => 'openFormWithLink',
									'element' => 'xpath://a[text()="AIX by Zabbix agent"]'
								]
							],
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #52 Template import popup.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Import'
						]
					],
					'doc_link' => '/en/manual/xml_export_import/templates#importing'
				]
			],
			// #53 Template mass update popup.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/templates/mass#using-mass-update'
				]
			],
			// #54 Template items list view.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/items'
				]
			],
			// #55 Template item create form.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create item'
						]
					],
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #56 Template item update form.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:itemInheritance'
						]
					],
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #57 Template item test form.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:itemInheritance'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #58 Template item Mass update popup.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #59 Template trigger list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/triggers'
				]
			],
			// #60 Template trigger create form.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create trigger'
						]
					],
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #61 Template trigger update form.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testInheritanceTrigger1'
						]
					],
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #62 Template trigger Mass update popup.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D=15000&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #63 Template graph list view.
			[
				[
					'url' => 'graphs.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/graphs'
				]
			],
			// #64 Template graph create form.
			[
				[
					'url' => 'graphs.php?hostid=15000&form=create&context=template',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #65 Template graph update form.
			[
				[
					'url' => 'graphs.php?form=update&graphid=15000&context=template&filter_hostids%5B0%5D=15000',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #66 Template dashboards list view.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.list&templateid=10076&context=template',
					'doc_link' => '/en/manual/config/visualization/host_screens'
				]
			],
			// #67 Template dashboard create popup.
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
			// #68 Template dashboards view mode.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards#creating-a-dashboard'
				]
			],
			// #69 Template dashboard widget create popup.
			[
				[
				'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath:(//button[contains(@class, "js-widget-edit")])[1]'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/graph_classic'
				]
			],
			// #70 Template dashboard widget edit popup.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.edit&dashboardid=50',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'id:dashboard-add-widget'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/action_log'
				]
			],
			// #71 Add Template dashboard page configuration popup.
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
			// #72 Template LLD rule list view.
			[
				[
					'url' => 'host_discovery.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery'
				]
			],
			// #73 Template LLD rule configuration form.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=15000&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery#discovery-rule'
				]
			],
			// #74 Template LLD rule test form.
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
			// #75 Template LLD item prototype list view.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/item_prototypes'
				]
			],
			// #76 Template LLD item prototype create form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create item prototype'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #77 Template LLD item prototype edit form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:itemDiscovery'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #78 Template LLD item prototype test form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:itemDiscovery'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #79 Template LLD item prototype mass update popup.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #80 Template LLD trigger prototype list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/trigger_prototypes'
				]
			],
			// #81 Template LLD trigger prototype create form.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create trigger prototype'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #82 Template LLD trigger prototype edit form.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://table[@class="list-table"]//tr[1]/td[3]/a'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #83 Template LLD trigger prototype mass update popup.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=15011&context=template',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #84 Template LLD graph prototype list view.
			[
				[
					'url' => 'graphs.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/graph_prototypes'
				]
			],
			// #85 Template LLD graph prototype create form.
			[
				[
					'url' => 'graphs.php?form=create&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #86 Template LLD graph prototype edit form.
			[
				[
					'url' => 'graphs.php?form=update&parent_discoveryid=15011&graphid=15008&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #87 Template LLD host prototype list view.
			[
				[
					'url' => 'host_prototypes.php?parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/host_prototypes'
				]
			],
			// #88 Template LLD host prototype create form.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #89 Template LLD host prototype edit form.
			[
				[
					'url' => 'host_prototypes.php?form=update&parent_discoveryid=15011&hostid=99000&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #90 Template Web scenario list view.
			[
				[
					'url' => 'httpconf.php?context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/web'
				]
			],
			// #91 Template Web scenario create form.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=15000&context=template',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #92 Template Web scenario edit form.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=15000&httptestid=15000&context=template',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #93 Template Web scenario step configuration form popup.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=15000&httptestid=15000&context=template',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[@id="tab_steps-tab"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://div[@id="steps-tab"]//button[text()="Add"]'
						]
					],
					'doc_link' => '/en/manual/web_monitoring#configuring-steps'
				]
			],
			// #94 Host list view.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts'
				]
			],
			// #95 Create host popup.
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
			// #96 Edit host popup.
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
			// #98 Host import popup.
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
			// #99 Host mass update popup.
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
			// #100 Host items list view.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/items'
				]
			],
			// #101 Host item create form.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create item'
						]
					],
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #102 Host item update form.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormItem'
						]
					],
					'doc_link' => '/en/manual/config/items/item#configuration'

				]
			],
			// #103 Host item test form.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormItem'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #104 Host item Mass update popup.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #105 Host trigger list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/triggers'
				]
			],
			// #106 Host trigger create form.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create trigger'
						]
					],
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #107 Host trigger update form.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids[0]=40001&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormTrigger1'
						]
					],
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #108 Host trigger Mass update popup.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D=40001&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #109 Host graph list view.
			[
				[
					'url' => 'graphs.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/graphs'
				]
			],
			// #110 Host graph create form.
			[
				[
					'url' => 'graphs.php?hostid=40001&form=create&context=host',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #111 Host graph update form.
			[
				[
					'url' => 'graphs.php?form=update&graphid=300000&context=host&filter_hostids%5B0%5D=40001',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #112 Host LLD rule list view.
			[
				[
					'url' => 'host_discovery.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery'
				]
			],
			// #113 Host LLD rule configuration form.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=40001&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery#discovery-rule'
				]
			],
			// #114 Host LLD rule test form.
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
			// #115 Host LLD item prototype list view.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/item_prototypes'
				]
			],
			// #116 Host LLD item prototype create form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create item prototype'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #117 Host LLD item prototype edit form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormItemPrototype1'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #118 Host LLD item prototype test form.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormItemPrototype1'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Test'
						]
					],
					'doc_link' => '/en/manual/config/items/item#testing'
				]
			],
			// #119 Host LLD item prototype mass update popup.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/items/itemupdate#using-mass-update'
				]
			],
			// #120 Host LLD trigger prototype list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/trigger_prototypes'
				]
			],
			// #121 Host LLD trigger prototype create form.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create trigger prototype'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #122 Host LLD trigger prototype edit form.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:testFormTriggerPrototype1'
						]
					],
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #123 Host LLD trigger prototype mass update popup.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'actions' => [
						[
							'callback' => 'openMassUpdate'
						]
					],
					'doc_link' => '/en/manual/config/triggers/update#using-mass-update'
				]
			],
			// #124 Host LLD graph prototype list view.
			[
				[
					'url' => 'graphs.php?parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/graph_prototypes'
				]
			],
			// #125 Host LLD graph prototype create form.
			[
				[
					'url' => 'graphs.php?form=create&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #126 Host LLD graph prototype edit form.
			[
				[
					'url' => 'graphs.php?form=update&parent_discoveryid=133800&graphid=600000&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #127 Host LLD host prototype list view.
			[
				[
					'url' => 'host_prototypes.php?parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/host_prototypes'
				]
			],
			// #128 Host LLD host prototype create form.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #129 Host LLD host prototype edit form.
			[
				[
					'url' => 'host_prototype',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #130 Host Web scenario list view.
			[
				[
					'url' => 'httpconf.php?filter_set=1&filter_hostids%5B0%5D=50001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/web'
				]
			],
			// #131 Host Web scenario create form.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=50001&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #132 Host Web scenario edit form.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #133 Host Web scenario step configuration form popup.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[@id="tab_steps-tab"]'
						],
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://div[@id="steps-tab"]//button[text()="Add"]'
						]
					],
					'doc_link' => '/en/manual/web_monitoring#configuring-steps'
				]
			],
			// #134 Maintenance list view.
			[
				[
					'url' => 'zabbix.php?action=maintenance.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/maintenance'
				]
			],
			// #135 Create maintenance form popup.
			[
				[
					'url' => 'zabbix.php?action=maintenance.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create maintenance period'
						]
					],
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #136 Edit maintenance form popup.
			[
				[
					'url' => 'zabbix.php?action=maintenance.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Maintenance for documentation links test"]'
						]
					],
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #137 Trigger actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=0',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #138 Create trigger action form popup.
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
			// #139 Edit trigger action form popup.
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
			// #140 Discovery actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #141 Create discovery action form popup.
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
			// #142 Edit discovery action form popup.
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
			// #143 Autoregistration actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #144 Create autoregistration action form popup.
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
			// #145 Edit autoregistration action form popup.
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
			// #146 Internal actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #147 Create internal action form popup.
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
			// #148 Edit internal action form popup.
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
			// #149 Event correlation list view.
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/correlation'
				]
			],
			// #150 Create event correlation form view.
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create event correlation'
						]
					],
					'doc_link' => '/en/manual/config/event_correlation/global#configuration'
				]
			],
			// #151 Edit event correlation form view.
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Event correlation for links check'
						]
					],
					'doc_link' => '/en/manual/config/event_correlation/global#configuration'
				]
			],
			// #152 Network discovery list view.
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/discovery'
				]
			],
			// #153 Create network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create discovery rule'
						]
					],
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #154 Edit network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Local network'
						]
					],
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #155 Administration -> General -> GUI view.
			[
				[
					'url' => 'zabbix.php?action=gui.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#gui'
				]
			],
			// #156 Administration -> General -> Autoregistration view.
			[
				[
					'url' => 'zabbix.php?action=autoreg.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#autoregistration'
				]
			],
			// #157 Administration -> General -> Housekeeping view.
			[
				[
					'url' => 'zabbix.php?action=housekeeping.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/housekeeping'
				]
			],
			// #158 Administration -> General -> Audit log view.
			[
				[
					'url' => 'zabbix.php?action=audit.settings.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/audit_log'
				]
			],
			// #159 Administration -> General -> Images -> Icon view.
			[
				[
					'url' => 'zabbix.php?action=image.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #160 Administration -> General -> Images -> Background view.
			[
				[
					'url' => 'zabbix.php?action=image.list&imagetype=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #161 Administration -> General -> Images -> Create image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imagetype=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #162 Administration -> General -> Images -> Edit image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imageid=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #163 Administration -> General -> Images -> Create background view.
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
			// #164 Administration -> General -> Icon mapping list view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #165 Administration -> General -> Icon mapping -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #166 Administration -> General -> Icon mapping -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit&iconmapid=101',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #167 Administration -> General -> Regular expressions list view.
			[
				[
					'url' => 'zabbix.php?action=regex.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#regular-expressions'
				]
			],
			// #168 Administration -> General -> Regular expressions -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #169 Administration -> General -> Regular expressions -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit&regexid=3',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #170 Administration -> General -> Macros view.
			[
				[
					'url' => 'zabbix.php?action=macros.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/macros'
				]
			],
			// #171 Administration -> General -> Trigger displaying options view.
			[
				[
					'url' => 'zabbix.php?action=trigdisplay.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#trigger-displaying-options'
				]
			],
			// #172 Administration -> General -> Geographical maps view.
			[
				[
					'url' => 'zabbix.php?action=geomaps.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#geographical-maps'
				]
			],
			// #173 Administration -> General -> Modules list view.
			[
				[
					'url' => 'zabbix.php?action=module.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#modules'
				]
			],
			// #174 Administration -> General -> Module edit view.
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
					'doc_link' => '/en/manual/extensions/frontendmodules#manifest-preparation'
				]
			],
			// #175 Administration -> General -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #176 Administration -> General -> Api tokens -> Create Api token popup.
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
			// #177 Administration -> General -> Api tokens -> Edit Api token popup.
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
			// #178 Administration -> General -> Other view.
			[
				[
					'url' => 'zabbix.php?action=miscconfig.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#other-parameters'
				]
			],
			// #179 Administration -> Proxy list view.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/proxies'
				]
			],
			// #180 Administration -> Create proxy view.
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
			// #181 Administration -> Proxies -> Edit proxy view.
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
			// #182 Administration -> Authentication view.
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/authentication'
				]
			],
			// #183 Administration -> User groups list view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_groups'
				]
			],
			// #184 Administration -> User groups -> Create user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #185 Administration -> User groups -> Edit user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit&usrgrpid=7',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #186 Administration -> User roles list view.
			[
				[
					'url' => 'zabbix.php?action=userrole.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles'
				]
			],
			// #187 Administration -> User roles -> Create form view.
			[
				[
					'url' => '/zabbix.php?action=userrole.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #188 Administration -> User roles -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=userrole.edit&roleid=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #189 Administration -> Users list view.
			[
				[
					'url' => 'zabbix.php?action=user.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_list'
				]
			],
			// #190 Administration -> Users -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #191 Administration -> Users -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit&userid=1',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #192 Administration -> Media type list view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/mediatypes'
				]
			],
			// #193 Alerts -> Media type -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create media type'
						]
					],
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #194 Alerts -> Media type -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Email'
						]
					],
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #195 Alerts -> Media type -> Import view.
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
			// #196 Alerts -> Scripts list view.
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts'
				]
			],
			// #197 Alerts -> Scripts -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create script'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #198 Alerts -> Scripts -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Detect operating system'
						]
					],
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #199 Administration -> Queue overview view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-item-type'
				]
			],
			// #200 Administration -> Queue overview by proxy view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview.proxy',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-proxy'
				]
			],
			// #201 Administration -> Queue details view.
			[
				[
					'url' => 'zabbix.php?action=queue.details',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#list-of-waiting-items'
				]
			],
			// #202 User profile view.
			[
				[
					'url' => 'zabbix.php?action=userprofile.edit',
					'doc_link' => '/en/manual/web_interface/user_profile#user-profile'
				]
			],
			// #203 User settings -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'doc_link' => '/en/manual/web_interface/user_profile#api-tokens'
				]
			],
			// #204 User settings -> Api tokens -> Create Api token popup.
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
			// #205 User settings -> Api tokens -> Edit Api token popup.
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
			// #206 Template groups list view.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templategroups'
				]
			],
			// #207 Create template group popup.
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
					'doc_link' => '/en/manual/config/templates/template#creating-a-template-group'
				]
			],
			// #208 Edit template group popup.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'xpath://a[text()="Templates/Applications"]'
						]
					],
					'doc_link' => '/en/manual/config/templates/template#creating-a-template-group'
				]
			],
			// #209 Start creating Discovery status widget.
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
					'widget_type' => 'Discovery status',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/discovery_status'
				]
			],
			// #210 Start creating Favorite Graphs widget.
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
					'widget_type' => 'Favorite graphs',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/favorite_graphs'
				]
			],
			// #211 Start creating Favorite maps widget.
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
					'widget_type' => 'Favorite maps',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/favorite_maps'
				]
			],
			// #212 Start creating Geomap widget.
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
					'widget_type' => 'Geomap',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/geomap'
				]
			],
			// #213 Start creating Graph widget.
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
					'widget_type' => 'Graph',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/graph'
				]
			],
			// #214 Start creating Graph (Classic) widget.
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
					'widget_type' => 'Graph (classic)',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/graph_classic'
				]
			],
			// #215 Start creating Graph prototype widget.
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
					'widget_type' => 'Graph prototype',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/graph_prototype'
				]
			],
			// #216 Start creating Host availability widget.
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
					'widget_type' => 'Host availability',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/host_availability'
				]
			],
			// #217 Start creating Item value widget.
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
					'widget_type' => 'Item value',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/item_value'
				]
			],
			// #218 Start creating Map widget.
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
					'widget_type' => 'Map',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/map'
				]
			],
			// #219 Start creating Map tree widget.
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
					'widget_type' => 'Map navigation tree',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/map_tree'
				]
			],
			// #220 Start creating Plain text widget.
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
					'widget_type' => 'Item history',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/item_history'
				]
			],
			// #221 Start creating Problem hosts widget.
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
					'widget_type' => 'Problem hosts',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/problem_hosts'
				]
			],
			// #222 Start creating Problems widget.
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
					'widget_type' => 'Problems',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/problems'
				]
			],
			// #223 Start creating Problems severity widget.
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
					'widget_type' => 'Problems by severity',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/problems_severity'
				]
			],
			// #224 Start creating SLA report widget.
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
					'widget_type' => 'SLA report',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/sla_report'
				]
			],
			// #225 Start creating System widget.
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
					'widget_type' => 'System information',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/system'
				]
			],
			// #226 Start creating Top hosts widget.
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
					'widget_type' => 'Top hosts',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/top_hosts'
				]
			],
			// #227 Start creating Top triggers widget.
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
					'widget_type' => 'Top triggers',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/top_triggers'
				]
			],
			// #228 Start creating Trigger overview widget.
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
					'widget_type' => 'Trigger overview',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/trigger_overview'
				]
			],
			// #229 Start creating URL widget.
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
					'widget_type' => 'URL',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/url'
				]
			],
			// #230 Start creating Web monitoring widget.
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
					'widget_type' => 'Web monitoring',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/web_monitoring'
				]
			],
			// #231 Start creating Data overview widget.
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
					'widget_type' => 'Top items',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/top_items'
				]
			],
			// #232 Start creating Gauge widget.
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
					'widget_type' => 'Gauge',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/gauge'
				]
			],
			// #233 Connectors list view.
			[
				[
					'url' => 'zabbix.php?action=connector.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#connectors'
				]
			],
			// #234 Create connectors popup.
			[
				[
					'url' => 'zabbix.php?action=connector.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'button:Create connector'
						]
					],
					'doc_link' => '/en/manual/config/export/streaming#configuration'
				]
			],
			// #235 Edit connectors popup.
			[
				[
					'url' => 'zabbix.php?action=connector.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Default connector'
						]
					],
					'doc_link' => '/en/manual/config/export/streaming#configuration'
				]
			],
			// #236 Administration -> General -> Timeouts.
			[
				[
					'url' => 'zabbix.php?action=timeouts.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#timeouts'
				]
			],
			// #237 Create Pie chart.
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
					'widget_type' => 'Pie chart',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/pie_chart'
				]
			],
			// #238 Start creating Host navigator widget.
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
					'widget_type' => 'Host navigator',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/host_navigator'
				]
			],
			// #239 Start creating Item navigator widget.
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
					'widget_type' => 'Item navigator',
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/item_navigator'
				]
			]
		];
	}

	/**
	 * @dataProvider getGeneralDocumentationLinkData
	 */
	public function testDocumentationLinks_checkGeneralLinks($data) {
		if ($data['url'] === 'host_prototype') {
			$data['url'] = 'host_prototypes.php?form=update&parent_discoveryid='.self::$lldid.
					'&hostid='.self::$host_prototypeid.'&context=host';
		}

		$this->page->login()->open($data['url'])->waitUntilReady();

		// Execute the corresponding callback function to open the form with doc link.
		if (array_key_exists('actions', $data)) {
			foreach ($data['actions'] as $action) {
				call_user_func_array([$this, $action['callback']], [CTestArrayHelper::get($action, 'element', null)]);
			}
		}

		$dialog = COverlayDialogElement::find()->one(false);
		$location = ($dialog->isValid()) ? COverlayDialogElement::find()->all()->last()->waitUntilReady() : $this;

		// Check all widget documentation links.
		if (array_key_exists('widget_type', $data)) {
			$form = $dialog->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($data['widget_type'])]);
		}

		// Get the documentation link and compare it with expected result.
		$link = $location->query('class', ['btn-icon zi-help', 'btn-icon zi-help-small'])->one();
		$this->assertEquals(self::$path_start.self::$version.$data['doc_link'], $link->getAttribute('href'));

		// If the link was located in a popup - close this popup.
		if ($dialog->isValid()) {
			$dialogs = COverlayDialogElement::find()->all();

			for ($i = $dialogs->count() - 1; $i >= 0; $i--) {
				$dialogs->get($i)->close(true);
			}
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
					'element' => 'xpath://div[contains(@class, "sysmap_iconid_7")]',
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-elements'
				]
			],
			// #1 Edit shape form.
			[
				[
					'element' => 'xpath://div[contains(@style, "top: 82px")]',
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-shapes'
				]
			],
			// #2 Edit element selection.
			[
				[
					'element' => [
						'xpath://div[contains(@class, "sysmap_iconid_19")]',
						'xpath://div[contains(@class, "sysmap_iconid_7")]'
					],
					'doc_link' => '/en/manual/config/visualization/maps/map#selecting-elements'
				]
			],
			// #3 Edit shape selection.
			[
				[
					'element' => [
						'xpath://div[contains(@style, "top: 257px")]',
						'xpath://div[contains(@style, "top: 82px")]'
					],
					'doc_link' => '/en/manual/config/visualization/maps/map#adding-shapes'
				]
			]
		];
	}

	/**
	 * @dataProvider getMapDocumentationLinkData
	 */
	public function testDocumentationLinks_checkMapElementLinks($data) {
		$this->page->login()->open('sysmap.php?sysmapid='.CDataHelper::get('Maps.links_mapid'))->waitUntilReady();

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
		$link = $dialog->query('class:zi-help-small')->all()->filter(new CElementFilter(CElementFilter::VISIBLE))->first();

		$this->assertEquals(self::$path_start.self::$version.$data['doc_link'], $link->getAttribute('href'));
	}
}
