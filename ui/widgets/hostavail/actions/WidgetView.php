<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\HostAvail\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemGeneral;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$interface_types = CItemGeneral::INTERFACE_TYPES_BY_PRIORITY;

		// Sanitize non-existing interface types.
		$this->fields_values['interface_type'] = array_values(
			array_intersect($interface_types, $this->fields_values['interface_type'])
		);

		$groupids = $this->fields_values['groupids'] ? getSubGroups($this->fields_values['groupids']) : null;

		$hosts_types = $this->fields_values['interface_type'] ?: $interface_types;

		$hosts_total = array_fill_keys($interface_types, 0);
		$hosts_count = array_fill_keys($interface_types, [
			INTERFACE_AVAILABLE_UNKNOWN => 0,
			INTERFACE_AVAILABLE_TRUE => 0,
			INTERFACE_AVAILABLE_FALSE => 0
		]);

		$db_hosts = API::Host()->get([
			'output' => [],
			'selectInterfaces' => ['type', 'available'],
			'groupids' => $groupids,
			'filter' => $this->fields_values['maintenance'] == HOST_MAINTENANCE_STATUS_OFF
				? ['status' => HOST_STATUS_MONITORED, 'maintenance_status' => HOST_MAINTENANCE_STATUS_OFF]
				: ['status' => HOST_STATUS_MONITORED]
		]);

		$availability_priority = [INTERFACE_AVAILABLE_FALSE, INTERFACE_AVAILABLE_UNKNOWN, INTERFACE_AVAILABLE_TRUE];

		foreach ($db_hosts as $host) {
			$host_interfaces = array_fill_keys($interface_types, []);

			foreach ($host['interfaces'] as $interface) {
				$host_interfaces[$interface['type']][] = $interface['available'];
			}

			$host_interfaces = array_filter($host_interfaces);

			foreach ($host_interfaces as $type => $interfaces) {
				$interfaces_availability = array_intersect($availability_priority, $interfaces);
				$available = reset($interfaces_availability);
				$hosts_count[$type][$available]++;
				$hosts_total[$type]++;
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'layout' => $this->fields_values['layout'],
			'hosts_types' => $hosts_types,
			'hosts_count' => $hosts_count,
			'hosts_total' => $hosts_total,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
