<?php
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


class MonitoringOverview {
	public static function load() {
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group to check Overview'],
			['name' => 'Another group to check Overview']
		]);
		$groupids = CDataHelper::getIds('name');

		// Create hosts with interfaces.
		$hosts = CDataHelper::createHosts([
			[
				'host' => '1_Host_to_check_Monitoring_Overview',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group to check Overview']
				],
				'items' => [
					[
						'name' => '1_item',
						'key_' => 'trap[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'history' => '90d',
						'status' => ITEM_STATUS_ACTIVE,
						'tags' => [
							[
								'tag' => 'DataBase',
								'value' => 'mysql'
							]
						]
					],
					[
						'name' => '2_item',
						'key_' => 'trap[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'history' => '90d',
						'status' => ITEM_STATUS_ACTIVE,
						'tags' => [
							[
								'tag' => 'DataBase',
								'value' => 'PostgreSQL'
							]
						]
					]
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				]
			],
			[
				'host' => '3_Host_to_check_Monitoring_Overview',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group to check Overview']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				],
				'items' => [
					[
						'name' => '3_item',
						'key_' => 'trap[3]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'history' => '90d',
						'status' => ITEM_STATUS_ACTIVE,
						'tags' => [
							[
								'tag' => 'DataBase',
								'value' => 'Oracle'
							]
						]
					]
				],
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'type' => 'Type',
					'type_full' => 'Type (Full details)',
					'name' => 'Name',
					'alias' => 'Alias',
					'os' => 'OS',
					'os_full' => 'OS (Full details)',
					'os_short' => 'OS (Short)',
					'serialno_a' => 'Serial number A',
					'serialno_b' => 'Serial number B',
					'tag' => 'Tag',
					'asset_tag' => 'Asset tag',
					'macaddress_a' => 'MAC address A',
					'macaddress_b' => 'MAC address B',
					'hardware' => 'Hardware',
					'hardware_full' => 'Hardware (Full details)',
					'software' => 'Software',
					'software_full' => 'Software (Full details)',
					'software_app_a' => 'Software application A',
					'software_app_b' => 'Software application B',
					'software_app_c' => 'Software application C',
					'software_app_d' => 'Software application D',
					'software_app_e' => 'Software application E',
					'contact' => 'Contact',
					'location' => 'Location',
					'location_lat' => 'Location latitud',
					'location_lon' => 'Location longitu',
					'notes' => 'Notes',
					'chassis' => 'Chassis',
					'model' => 'Model',
					'hw_arch' => 'HW architecture',
					'vendor' => 'Vendor',
					'contract_number' => 'Contract number',
					'installer_name' => 'Installer name',
					'deployment_status' => 'Deployment status',
					'url_a' => 'URL A',
					'url_b' => 'URL B',
					'url_c' => 'URL C',
					'host_networks' => 'Host networks',
					'host_netmask' => 'Host subnet mask',
					'host_router' => 'Host router',
					'oob_ip' => 'OOB IP address',
					'oob_netmask' => 'OOB subnet mask',
					'oob_router' => 'OOB router',
					'date_hw_purchase' => 'Date HW purchased',
					'date_hw_install' => 'Date HW installed',
					'date_hw_expiry' => 'Date HW maintenance expires',
					'date_hw_decomm' => 'Date hw decommissioned',
					'site_address_a' => 'Site address A',
					'site_address_b' => 'Site address B',
					'site_address_c' => 'Site address C',
					'site_city' => 'Site city',
					'site_state' => 'Site state / province',
					'site_country' => 'Site country',
					'site_zip' => 'Site ZIP / postal',
					'site_rack' => 'Site rack location',
					'site_notes' => 'Site notes',
					'poc_1_name' => 'Primary POC name',
					'poc_1_email' => 'Primary POC email',
					'poc_1_phone_a' => 'Primary POC phone A',
					'poc_1_phone_b' => 'Primary POC phone B',
					'poc_1_cell' => 'Primary POC cell',
					'poc_1_screen' => 'Primary POC screen name',
					'poc_1_notes' => 'Primary POC notes',
					'poc_2_name' => 'Secondary POC name',
					'poc_2_email' => 'Secondary POC email',
					'poc_2_phone_a' => 'Secondary POC phone A',
					'poc_2_phone_b' => 'Secondary POC phone B',
					'poc_2_cell' => 'Secondary POC cell',
					'poc_2_screen' => 'Secondary POC screen name',
					'poc_2_notes' => 'Secondary POC notes'
				]
			],
			[
				'host' => '4_Host_to_check_Monitoring_Overview',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Another group to check Overview']
				],
				'items' => [
					[
						'name' => '4_item',
						'key_' => 'trap[4]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'history' => '90d',
						'status' => ITEM_STATUS_ACTIVE,
						'units' => 'UNIT',
						'tags' => [
							[
								'tag' => 'DataBase',
								'value' => 'Oracle DB'
							]
						]
					]
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				]
			]
		]);
		$itemids = CDataHelper::getIds('name');

		$i = 1;
		foreach (array_values($itemids) as $itemid) {
			CDataHelper::addItemData($itemid, [$i], 1533555726);
			$i++;
		}

		// Create triggers based on items.
		CDataHelper::call('trigger.create', [
			[
				'description' => '1_trigger_Not_classified',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1],#1)>0',
				'comments' => 'Macro should be resolved, host IP should be visible here: {HOST.CONN}',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'url' => 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}'
			],
			[
				'description' => '1_trigger_Warning',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1],#1)>0',
				'comments' => 'The following url should be clickable: https://zabbix.com',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => '1_trigger_Average',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1],#1)>0',
				'comments' => 'https://zabbix.com',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'url' => 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}'
			],
			[
				'description' => '1_trigger_High',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1],#1)>0',
				'comments' => 'Non-clickable description',
				'priority' => TRIGGER_SEVERITY_HIGH,
				'url' => 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}',
				'tags' => [
					[
						'tag' => 'webhook',
						'value' => '1'
					]
				]
			],
			[
				'description' => '1_trigger_Disaster',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1],#1)>0',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => '2_trigger_Information',
				'expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[2],#1)>0',
				'comments' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => '3_trigger_Average',
				'expression' => 'last(/3_Host_to_check_Monitoring_Overview/trap[3],#1)>0',
				'comments' => 'Macro - resolved, URL - clickable: {HOST.NAME}, https://zabbix.com',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => '3_trigger_Disaster',
				'expression' => 'last(/3_Host_to_check_Monitoring_Overview/trap[3],#1)>0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'url' => 'triggers.php?form=update&triggerid={TRIGGER.ID}&context=host'
			],
			[
				'description' => '4_trigger_Average',
				'expression' => 'last(/4_Host_to_check_Monitoring_Overview/trap[4],#1)>0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		]);
		$triggerids = CDataHelper::getIds('description');

		// Create events and problems.
		$trigger_names = [
			'1_trigger_Not_classified',
			'1_trigger_Warning',
			'1_trigger_Average',
			'1_trigger_High',
			'1_trigger_Disaster',
			'2_trigger_Information',
			'3_trigger_Average',
			'4_trigger_Average'
		];
		CDBHelper::setTriggerProblem($trigger_names, TRIGGER_VALUE_TRUE, ['clock' => 1533555726, 'ns' => 726692808]);

		foreach ($trigger_names as $description) {
			DBexecute('UPDATE triggers SET value=1 WHERE description='.zbx_dbstr($description));
		}

		// Get event ids.
		$eventids = [];
		foreach ($trigger_names as $event_name) {
			$eventids[$event_name] = CDBHelper::getValue('SELECT eventid FROM events WHERE name='.zbx_dbstr($event_name));
		}

		$acknowledge = [
			$eventids['2_trigger_Information'],
			$eventids['3_trigger_Average'],
			$eventids['4_trigger_Average']
		];

		CDataHelper::call('event.acknowledge', [
			'eventids' => $acknowledge,
			'message' => '1 acknowledged',
			'action' => 2
		]);

		foreach ($acknowledge as $id) {
			DBexecute('UPDATE acknowledges SET clock=1533629135 WHERE eventid='.zbx_dbstr($id));

			// Imitate event acknowledge by other user (guest).
			if ($id === $eventids['4_trigger_Average']) {
				DBexecute('UPDATE acknowledges SET userid = 2 WHERE eventid='.zbx_dbstr($id));
			}

			$id = CDBHelper::getValue('SELECT acknowledgeid FROM acknowledges WHERE eventid='.zbx_dbstr($id));
			DBexecute('UPDATE task SET clock=1533631968 WHERE taskid='.zbx_dbstr($id));
		}

		CDataHelper::call('mediatype.create', [
			[
				'type' => MEDIA_TYPE_WEBHOOK,
				'name' => 'URL test webhook',
				'status' => MEDIA_STATUS_ACTIVE,
				'script' => 'return 0;',
				'show_event_menu' => ZBX_EVENT_MENU_SHOW,
				'event_menu_name' => 'Webhook url for all',
				'event_menu_url' => 'zabbix.php?action=mediatype.edit&mediatypeid=101',
				'description' => 'Webhook media type for URL test'
			]
		]);

		return [
			'itemids' => $itemids,
			'eventids' => $eventids,
			'triggerids' => $triggerids,
			'groupids' => $groupids,
			'hostids' => $hosts['hostids']
		];
	}
}
