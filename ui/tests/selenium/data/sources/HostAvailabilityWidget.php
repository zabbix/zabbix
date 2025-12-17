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


class HostAvailabilityWidget {
	public static function load() {
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Host availability widget'],
			['name' => 'Group in maintenance for Host availability widget']
		]);
		$groupids = CDataHelper::getIds('name');

		// Create maintenance for host group.
		$maintenanceid = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for Host availability widget',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'description' => 'Maintenance for checking Show hosts in maintenance option in Host availability widget',
				'active_since' => 1534971600,
				'active_till' => 2147378400,
				'groups' => [['groupid' => $groupids['Group in maintenance for Host availability widget']]],
				'timeperiods' => [[]]
			]
		])['maintenanceids'][0];

		// Create hosts with interfaces.
		CDataHelper::createHosts([
			[
				'host' => 'Not available host',
				'description' => 'Not available host for Host availability widget',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group for Host availability widget']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050',
						'details' => [
							'version' => SNMP_V2C,
							'bulk' => SNMP_BULK_ENABLED,
							'community' => '{$SNMP_COMMUNITY}'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					]
				]
			],
			[
				'host' => 'Not available host in maintenance',
				'description' => 'Not available host in maintenance for Host availability widget',
				'groups' => [
					'groupid' => $groupids['Group in maintenance for Host availability widget']
				],
				'status' => HOST_STATUS_MONITORED,
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050',
						'details' => [
							'version' => SNMP_V2C,
							'bulk' => SNMP_BULK_ENABLED,
							'community' => '{$SNMP_COMMUNITY}'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					]
				]
			],
			[
				'host' => 'Unknown host',
				'description' => 'Unknown host for Host availability widget',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group for Host availability widget']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					]
				]
			],
			[
				'host' => 'Unknown host in maintenance',
				'description' => 'Unknown host for Host availability widget in maintenance',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group in maintenance for Host availability widget']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					]
				]
			],
			[
				'host' => 'Available host',
				'description' => 'Available host for Host availability widget',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group for Host availability widget']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050',
						'details' => [
							'version' => SNMP_V2C,
							'bulk' => SNMP_BULK_ENABLED,
							'community' => '{$SNMP_COMMUNITY}'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'useip' => INTERFACE_USE_DNS,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => '10050'
					]
				]
			],
			[
				'host' => 'Available host in maintenance',
				'description' => 'Available host in maintenance for Host availability widget',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group in maintenance for Host availability widget']
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050',
						'details' => [
							'version' => SNMP_V2C,
							'bulk' => SNMP_BULK_ENABLED,
							'community' => '{$SNMP_COMMUNITY}'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'useip' => INTERFACE_USE_IP,
						'main' => INTERFACE_PRIMARY,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				]
			]
		]);
		$hostids = CDataHelper::getIds('host');
		$interfaces = CDataHelper::getInterfaces($hostids);

		$data = [
			$interfaces['default_interfaces']['Not available host'][1] => [
					[
						'available' => 2,
						'error' => 'ERROR Agent'
					]
			],
			$interfaces['default_interfaces']['Not available host'][2] => [
					[
						'available' => 2,
						'error' => 'ERROR SNMP'
					]
			],
			$interfaces['default_interfaces']['Not available host'][3] => [
				[
					'available' => 2,
					'error' => 'ERROR IPMI'
				]
			],
			$interfaces['default_interfaces']['Not available host'][4] => [
				[
					'available' => 2,
					'error' => 'ERROR JMX'
				]
			],
			$interfaces['default_interfaces']['Not available host in maintenance'][1] => [
				[
					'available' => 2
				]
			],
			$interfaces['default_interfaces']['Not available host in maintenance'][2] => [
				[
					'available' => 2
				]
			],
			$interfaces['default_interfaces']['Not available host in maintenance'][3] => [
				[
					'available' => 2
				]
			],
			$interfaces['default_interfaces']['Not available host in maintenance'][4] => [
				[
					'available' => 2
				]
			],
			$interfaces['default_interfaces']['Unknown host'][1] => [
				[
					'available' => 0
				]
			],
			$interfaces['default_interfaces']['Unknown host in maintenance'][1] => [
				[
					'available' => 0
				]
			],
			$interfaces['default_interfaces']['Available host'][1] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host'][2] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host'][3] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host'][4] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host in maintenance'][1] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host in maintenance'][2] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host in maintenance'][3] => [
				[
					'available' => 1
				]
			],
			$interfaces['default_interfaces']['Available host in maintenance'][4] => [
				[
					'available' => 1
				]
			]
		];
		foreach ($data as $interfaceid => $values) {
			foreach ($values as $value) {
				$error = (array_key_exists('error', $value)) ? $value['error'] : '';
				DBexecute('UPDATE interface SET available='.zbx_dbstr($value['available']).', error='.zbx_dbstr($error).
						' WHERE interfaceid='.zbx_dbstr($interfaceid)
				);
			}
		}

		// Add hosts to maintenance.
		$maintenace_hostids = [
			$hostids['Not available host in maintenance'],
			$hostids['Unknown host in maintenance'],
			$hostids['Available host in maintenance']
		];
		foreach ($maintenace_hostids as $hostid) {
			DBexecute('INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES ('.
					zbx_dbstr($hostid).', '.zbx_dbstr($maintenanceid).','.zbx_dbstr($hostid).')'
			);

			DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenanceid).
					', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.
					zbx_dbstr(1534971600).' WHERE hostid='.zbx_dbstr($hostid)
			);
		}

		$dashboardid = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Host availability widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostavail',
								'name' => 'Reference HA widget',
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5
							],
							[
								'type' => 'hostavail',
								'name' => 'Reference HA widget to delete',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids',
										'value' => 4 // Zabbix servers.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'layout',
										'value' => 1
									]
								]
							]
						]
					]
				]
			]
		])['dashboardids'][0];

		return [
			'dashboardid' => $dashboardid,
			'hostids' => $hostids
		];
	}
}
