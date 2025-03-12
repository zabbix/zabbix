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
 * @backup profiles, connector
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
					'url' => 'zabbix.php?action=popup&popup=acknowledge.edit&eventids%5B0%5D=93',
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
					'url' => 'zabbix.php?action=popup&popup=service.edit',
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
					'url' => 'zabbix.php?action=popup&popup=action.edit&eventsource=4',
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
					'url' => 'zabbix.php?action=popup&popup=sla.edit',
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
					'url' => 'zabbix.php?action=popup&popup=hostgroup.edit',
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #47 Update host group via popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=hostgroup.edit&groupid=4', // Zabbix servers.
					'doc_link' => '/en/manual/config/hosts/host#creating-a-host-group'
				]
			],
			// #48 Template list view.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates'
				]
			],
			// #49 Create template view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=template.edit',
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #50 Update template view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=template.edit&templateid=10076', // AIX by Zabbix agent.
					'doc_link' => '/en/manual/config/templates/template#creating-a-template'
				]
			],
			// #51 Template import popup.
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
			// #52 Template mass update popup.
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
			// #53 Template items list view.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=15000&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/items'
				]
			],
			// #54 Template item create form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=item.edit&hostid=10076&context=template', // AIX by Zabbix agent.
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #55 Template item update form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=item.edit&context=template&itemid=22933', // Host name (AIX by Zabbix agent).
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #56 Template item test form.
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
			// #57 Template item Mass update popup.
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
			// #58 Template trigger list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/triggers'
				]
			],
			// #59 Template trigger create form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=trigger.edit&context=template',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #60 Template trigger update form.
			[
				[
					// Trigger => AIX: Disk I/O is overloaded.
					'url' => 'zabbix.php?action=popup&popup=trigger.edit&triggerid=13367&hostid=10076&context=template',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #61 Template trigger Mass update popup.
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
					'doc_link' => '/en/manual/web_interface/frontend_sections/dashboards/widgets/action_log'
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
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=15011&filter_set=1&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/item_prototypes'
				]
			],
			// #75 Template LLD item prototype create form.
			[
				[
					// Discovery rule => Mounted filesystem discovery.
					'url' => 'zabbix.php?action=popup&popup=item.prototype.edit&context=template&parent_discoveryid=22947',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #76 Template LLD item prototype edit form.
			[
				[
					// Item prototype => FS [{#FSNAME}]: Get data.
					'url' => 'zabbix.php?action=popup&popup=item.prototype.edit&itemid=44428&parent_discoveryid=22947&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #77 Template LLD item prototype test form.
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
			// #78 Template LLD item prototype mass update popup.
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
			// #79 Template LLD trigger prototype list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=15011&context=template',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templates/discovery/trigger_prototypes'
				]
			],
			// #80 Template LLD trigger prototype create form.
			[
				[
					// Discovery rule => Mounted filesystem discovery.
					'url' => 'zabbix.php?action=popup&popup=trigger.prototype.edit&parent_discoveryid=22947',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #81 Template LLD trigger prototype edit form.
			[
				[
					// Trigger => AIX: FS [{#FSNAME}]: Space is low.
					'url' => 'zabbix.php?action=popup&popup=trigger.prototype.edit&parent_discoveryid=22947&triggerid=17114',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #82 Template LLD trigger prototype mass update popup.
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
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #88 Template LLD host prototype edit form.
			[
				[
					'url' => 'host_prototypes.php?form=update&parent_discoveryid=15011&hostid=99000&context=template',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
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
					'url' => 'zabbix.php?action=popup&popup=host.edit',
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #95 Edit host popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=host.edit&hostid=10084', // ЗАББИКС Сервер.
					'doc_link' => '/en/manual/config/hosts/host#configuration'
				]
			],
			// #96 Host import popup.
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
			// #97 Host mass update popup.
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
			// #98 Host items list view.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/items'
				]
			],
			// #99 Host item create form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=item.edit&context=host&hostid=10084',
					'doc_link' => '/en/manual/config/items/item#configuration'
				]
			],
			// #100 Host item update form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=item.edit&context=host&itemid=42243', // Item => Available memory
					'doc_link' => '/en/manual/config/items/item#configuration'

				]
			],
			// #101 Host item test form.
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
			// #102 Host item Mass update popup.
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
			// #103 Host trigger list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/triggers'
				]
			],
			// #104 Host trigger create form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=trigger.edit&context=host',
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #105 Host trigger update form.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=trigger.edit&triggerid=99251', // Test trigger with tag.
					'doc_link' => '/en/manual/config/triggers/trigger#configuration'
				]
			],
			// #106 Host trigger Mass update popup.
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
			// #107 Host graph list view.
			[
				[
					'url' => 'graphs.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/graphs'
				]
			],
			// #108 Host graph create form.
			[
				[
					'url' => 'graphs.php?hostid=40001&form=create&context=host',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #109 Host graph update form.
			[
				[
					'url' => 'graphs.php?form=update&graphid=300000&context=host&filter_hostids%5B0%5D=40001',
					'doc_link' => '/en/manual/config/visualization/graphs/custom#configuring-custom-graphs'
				]
			],
			// #110 Host LLD rule list view.
			[
				[
					'url' => 'host_discovery.php?context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery'
				]
			],
			// #111 Host LLD rule configuration form.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=40001&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery#discovery-rule'
				]
			],
			// #112 Host LLD rule test form.
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
			// #113 Host LLD item prototype list view.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/item_prototypes'
				]
			],
			// #114 Host LLD item prototype create form.
			[
				[
					// Discovery rule => testFormDiscoveryRule.
					'url' => 'zabbix.php?action=popup&popup=item.prototype.edit&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #115 Host LLD item prototype edit form.
			[
				[
					// Item prototype: testFormItemPrototype1.
					'url' => 'zabbix.php?action=popup&popup=item.prototype.edit&itemid=23800&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/item_prototypes'
				]
			],
			// #116 Host LLD item prototype test form.
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
			// #117 Host LLD item prototype mass update popup.
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
			// #118 Host LLD trigger prototype list view.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/trigger_prototypes'
				]
			],
			// #119 Host LLD trigger prototype create form.
			[
				[
					// Discovery rule => testFormDiscoveryRule.
					'url' => 'zabbix.php?action=popup&popup=trigger.prototype.edit&parent_discoveryid=133800',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #120 Host LLD trigger prototype edit form.
			[
				[
					// Trigger name => testFormTriggerPrototype1.
					'url' => 'zabbix.php?action=popup&popup=trigger.prototype.edit&parent_discoveryid=133800&triggerid=99518',
					'doc_link' => '/en/manual/discovery/low_level_discovery/trigger_prototypes'
				]
			],
			// #121 Host LLD trigger prototype mass update popup.
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
			// #122 Host LLD graph prototype list view.
			[
				[
					'url' => 'graphs.php?parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/graph_prototypes'
				]
			],
			// #123 Host LLD graph prototype create form.
			[
				[
					'url' => 'graphs.php?form=create&parent_discoveryid=133800&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #124 Host LLD graph prototype edit form.
			[
				[
					'url' => 'graphs.php?form=update&parent_discoveryid=133800&graphid=600000&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/graph_prototypes'
				]
			],
			// #125 Host LLD host prototype list view.
			[
				[
					'url' => 'host_prototypes.php?parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/discovery/host_prototypes'
				]
			],
			// #126 Host LLD host prototype create form.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=90001&context=host',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #127 Host LLD host prototype edit form.
			[
				[
					'url' => 'host_prototype',
					'doc_link' => '/en/manual/discovery/low_level_discovery/host_prototypes'
				]
			],
			// #128 Host Web scenario list view.
			[
				[
					'url' => 'httpconf.php?filter_set=1&filter_hostids%5B0%5D=50001&context=host',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/hosts/web'
				]
			],
			// #129 Host Web scenario create form.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=50001&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #130 Host Web scenario edit form.
			[
				[
					'url' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host',
					'doc_link' => '/en/manual/web_monitoring#configuring-a-web-scenario'
				]
			],
			// #131 Host Web scenario step configuration form popup.
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
			// #132 Maintenance list view.
			[
				[
					'url' => 'zabbix.php?action=maintenance.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/maintenance'
				]
			],
			// #133 Create maintenance form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=maintenance.edit',
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #134 Edit maintenance form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=maintenance.edit&maintenanceid=4', // Maintenance for suppression test.
					'doc_link' => '/en/manual/maintenance#configuration'
				]
			],
			// #135 Trigger actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=0',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #136 Create trigger action form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=action.edit&eventsource=0',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #137 Edit trigger action form popup.
			[
				[
					// Trigger action => Report problems to Zabbix administrators.
					'url' => 'zabbix.php?action=popup&popup=action.edit&actionid=3&eventsource=0',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #138 Discovery actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #139 Create discovery action form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=action.edit&eventsource=1',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #140 Edit discovery action form popup.
			[
				[
					// 	Discovery action => Auto discovery. Linux servers.
					'url' => 'zabbix.php?action=popup&popup=action.edit&actionid=2&eventsource=1',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #141 Autoregistration actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #142 Create autoregistration action form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=action.edit&eventsource=2',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #143 Edit autoregistration action form popup.
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
			// #144 Internal actions list view.
			[
				[
					'url' => 'zabbix.php?action=action.list&eventsource=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/actions'
				]
			],
			// #145 Create internal action form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=action.edit&eventsource=3',
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #146 Edit internal action form popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=action.edit&actionid=4&eventsource=3', // Report not supported items.
					'doc_link' => '/en/manual/config/notifications/action#configuring-an-action'
				]
			],
			// #147 Event correlation list view.
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/correlation'
				]
			],
			// #148 Create event correlation form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=correlation.edit',
					'doc_link' => '/en/manual/config/event_correlation/global#configuration'
				]
			],
			// #149 Edit event correlation form view.
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
			// #150 Network discovery list view.
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/discovery'
				]
			],
			// #151 Create network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=discovery.edit',
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #152 Edit network discovery form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=discovery.edit&druleid=2', // Discovery rule => Local network.
					'doc_link' => '/en/manual/discovery/network_discovery/rule#rule-attributes'
				]
			],
			// #153 Administration -> General -> GUI view.
			[
				[
					'url' => 'zabbix.php?action=gui.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#gui'
				]
			],
			// #154 Administration -> General -> Autoregistration view.
			[
				[
					'url' => 'zabbix.php?action=autoreg.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#autoregistration'
				]
			],
			// #155 Administration -> General -> Housekeeping view.
			[
				[
					'url' => 'zabbix.php?action=housekeeping.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/housekeeping'
				]
			],
			// #156 Administration -> General -> Audit log view.
			[
				[
					'url' => 'zabbix.php?action=audit.settings.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/audit_log'
				]
			],
			// #157 Administration -> General -> Images -> Icon view.
			[
				[
					'url' => 'zabbix.php?action=image.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #158 Administration -> General -> Images -> Background view.
			[
				[
					'url' => 'zabbix.php?action=image.list&imagetype=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #159 Administration -> General -> Images -> Create image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imagetype=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #160 Administration -> General -> Images -> Edit image view.
			[
				[
					'url' => 'zabbix.php?action=image.edit&imageid=2',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#images'
				]
			],
			// #161 Administration -> General -> Images -> Create background view.
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
			// #162 Administration -> General -> Icon mapping list view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #163 Administration -> General -> Icon mapping -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #164 Administration -> General -> Icon mapping -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit&iconmapid=101',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#icon-mapping'
				]
			],
			// #165 Administration -> General -> Regular expressions list view.
			[
				[
					'url' => 'zabbix.php?action=regex.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#regular-expressions'
				]
			],
			// #166 Administration -> General -> Regular expressions -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #167 Administration -> General -> Regular expressions -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=regex.edit&regexid=3',
					'doc_link' => '/en/manual/regular_expressions#global-regular-expressions'
				]
			],
			// #168 Administration -> General -> Macros view.
			[
				[
					'url' => 'zabbix.php?action=macros.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/macros'
				]
			],
			// #169 Administration -> General -> Trigger displaying options view.
			[
				[
					'url' => 'zabbix.php?action=trigdisplay.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#trigger-displaying-options'
				]
			],
			// #170 Administration -> General -> Geographical maps view.
			[
				[
					'url' => 'zabbix.php?action=geomaps.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#geographical-maps'
				]
			],
			// #171 Administration -> General -> Modules list view.
			[
				[
					'url' => 'zabbix.php?action=module.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#modules'
				]
			],
			// #172 Administration -> General -> Module edit view.
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
			// #173 Users -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #174 Users -> Api tokens -> Create Api token popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=token.edit&admin_mode=1',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #175 Users -> Api tokens -> Edit Api token popup.
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
			// #176 Administration -> General -> Other view.
			[
				[
					'url' => 'zabbix.php?action=miscconfig.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#other-parameters'
				]
			],
			// #177 Administration -> Proxy list view.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/proxies'
				]
			],
			// #178 Administration -> Create proxy view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=proxy.edit',
					'doc_link' => '/en/manual/distributed_monitoring/proxies#configuration'
				]
			],
			// #179 Administration -> Proxies -> Edit proxy view.
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
			// #180 Administration -> Proxy groups list view.
			[
				[
					'url' => 'zabbix.php?action=proxygroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/proxy_groups'
				]
			],
			// #181 Administration -> Create proxy group view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=proxygroup.edit',
					'doc_link' => '/en/manual/distributed_monitoring/proxies/ha'
				]
			],
			// #182 Administration -> Proxy groups -> Edit proxy group view.
			[
				[
					'url' => 'zabbix.php?action=proxygroup.list',
					'actions' => [
						[
							'callback' => 'openFormWithLink',
							'element' => 'link:Group without proxies'
						]
					],
					'doc_link' => '/en/manual/distributed_monitoring/proxies/ha'
				]
			],
			// #183 Users -> Authentication view.
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/authentication'
				]
			],
			// #184 Users -> User groups list view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_groups'
				]
			],
			// #185 Users -> User groups -> Create user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #186 Users -> User groups -> Edit user group view.
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit&usrgrpid=7',
					'doc_link' => '/en/manual/config/users_and_usergroups/usergroup#configuration'
				]
			],
			// #187 Users -> User roles list view.
			[
				[
					'url' => 'zabbix.php?action=userrole.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles'
				]
			],
			// #188 Administration -> User roles -> Create form view.
			[
				[
					'url' => '/zabbix.php?action=userrole.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #189 Users -> User roles -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=userrole.edit&roleid=3',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_roles#default-user-roles'
				]
			],
			// #190 Administration -> Users list view.
			[
				[
					'url' => 'zabbix.php?action=user.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/user_list'
				]
			],
			// #191 Users -> Users -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #192 Users -> Users -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=user.edit&userid=1',
					'doc_link' => '/en/manual/config/users_and_usergroups/user'
				]
			],
			// #193 Alerts -> Media type list view.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/mediatypes'
				]
			],
			// #194 Alerts -> Media type -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=mediatype.edit',
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #195 Alerts -> Media type -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=mediatype.edit&mediatypeid=1', // Email.
					'doc_link' => '/en/manual/config/notifications/media#common-parameters'
				]
			],
			// #196 Alerts -> Media type -> Import view.
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
			// #197 Alerts -> Scripts list view.
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts'
				]
			],
			// #198 Alerts -> Scripts -> Create form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=script.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #199 Alerts -> Scripts -> Edit form view.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=script.edit&scriptid=1', // Ping.
					'doc_link' => '/en/manual/web_interface/frontend_sections/alerts/scripts#configuring-a-global-script'
				]
			],
			// #200 Administration -> Queue overview view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-item-type'
				]
			],
			// #201 Administration -> Queue overview by proxy view.
			[
				[
					'url' => 'zabbix.php?action=queue.overview.proxy',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#overview-by-proxy'
				]
			],
			// #202 Administration -> Queue details view.
			[
				[
					'url' => 'zabbix.php?action=queue.details',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/queue#list-of-waiting-items'
				]
			],
			// #203 User profile view.
			[
				[
					'url' => 'zabbix.php?action=userprofile.edit',
					'doc_link' => '/en/manual/web_interface/user_profile#user-profile'
				]
			],
			// #204 User settings -> Api tokens list view.
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'doc_link' => '/en/manual/web_interface/user_profile#api-tokens'
				]
			],
			// #205 User settings -> Api tokens -> Create Api token popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=token.edit&admin_mode=0',
					'doc_link' => '/en/manual/web_interface/frontend_sections/users/api_tokens'
				]
			],
			// #206 User settings -> Api tokens -> Edit Api token popup.
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
			// #207 Template groups list view.
			[
				[
					'url' => 'zabbix.php?action=templategroup.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/data_collection/templategroups'
				]
			],
			// #208 Create template group popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=templategroup.edit',
					'doc_link' => '/en/manual/config/templates/template#creating-a-template-group'
				]
			],
			// #209 Edit template group popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=templategroup.edit&groupid=12', // Templates/Applications.
					'doc_link' => '/en/manual/config/templates/template#creating-a-template-group'
				]
			],
			// #210 Start creating Discovery status widget.
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
			// #211 Start creating Favorite Graphs widget.
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
			// #212 Start creating Favorite maps widget.
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
			// #213 Start creating Geomap widget.
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
			// #214 Start creating Graph widget.
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
			// #215 Start creating Graph (Classic) widget.
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
			// #216 Start creating Graph prototype widget.
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
			// #217 Start creating Host availability widget.
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
			// #218 Start creating Item value widget.
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
			// #219 Start creating Map widget.
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
			// #220 Start creating Map tree widget.
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
			// #221 Start creating History widget.
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
			// #222 Start creating Problem hosts widget.
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
			// #223 Start creating Problems widget.
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
			// #224 Start creating Problems severity widget.
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
			// #225 Start creating SLA report widget.
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
			// #226 Start creating System widget.
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
			// #227 Start creating Top hosts widget.
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
			// #228 Start creating Top triggers widget.
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
			// #229 Start creating Trigger overview widget.
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
			// #230 Start creating URL widget.
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
			// #231 Start creating Web monitoring widget.
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
			// #232 Start creating Data overview widget.
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
			// #233 Start creating Gauge widget.
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
			// #234 Connectors list view.
			[
				[
					'url' => 'zabbix.php?action=connector.list',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#connectors'
				]
			],
			// #235 Create connectors popup.
			[
				[
					'url' => 'zabbix.php?action=popup&popup=connector.edit',
					'doc_link' => '/en/manual/config/export/streaming#configuration'
				]
			],
			// #236 Edit connectors popup.
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
			// #237 Administration -> General -> Timeouts.
			[
				[
					'url' => 'zabbix.php?action=timeouts.edit',
					'doc_link' => '/en/manual/web_interface/frontend_sections/administration/general#timeouts'
				]
			],
			// #238 Create Pie chart.
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
			// #239 Start creating Host navigator widget.
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
			// #240 Start creating Item navigator widget.
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
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
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
