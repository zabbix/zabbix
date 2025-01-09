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


namespace Widgets\HostAvail\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemGeneral;

class WidgetView extends CControllerDashboardWidgetView {

	private const INTERFACE_STATUSES = [
		INTERFACE_AVAILABLE_UNKNOWN,
		INTERFACE_AVAILABLE_TRUE,
		INTERFACE_AVAILABLE_FALSE,
		INTERFACE_AVAILABLE_MIXED
	];

	protected function doAction(): void {
		$interface_types = array_merge([INTERFACE_TYPE_AGENT_ACTIVE], CItemGeneral::INTERFACE_TYPES_BY_PRIORITY);

		// Sanitize non-existing interface types.
		$this->fields_values['interface_type'] = array_values(
			array_intersect($interface_types, $this->fields_values['interface_type'])
		);

		$interface_types = $this->fields_values['interface_type'] ?: $interface_types;

		$interface_totals = array_fill_keys($interface_types, 0);
		$interface_type_count = array_fill_keys($interface_types, array_fill_keys(self::INTERFACE_STATUSES, 0));
		$total_hosts = array_fill_keys(self::INTERFACE_STATUSES, 0);

		if (!$this->isTemplateDashboard() || ($this->isTemplateDashboard() && $this->fields_values['override_hostid'])) {
			$options = [
				'output' => in_array(INTERFACE_TYPE_AGENT_ACTIVE, $interface_types) ? ['active_available'] : [],
				'selectInterfaces' => ['type', 'available'],
				'monitored_hosts' => true,
				'preservekeys' => true
			];

			if (!$this->isTemplateDashboard() && $this->fields_values['groupids']) {
				$options['groupids'] = getSubGroups($this->fields_values['groupids']);
			}

			if ($this->isTemplateDashboard()) {
				$options['hostids'] = $this->fields_values['override_hostid'];
			}

			if ($this->fields_values['maintenance'] == HOST_MAINTENANCE_STATUS_OFF) {
				$options['filter'] = ['maintenance_status' => HOST_MAINTENANCE_STATUS_OFF];
			}

			$db_hosts = API::Host()->get($options);

			$db_items_active_count = in_array(INTERFACE_TYPE_AGENT_ACTIVE, $interface_types)
				? API::Item()->get([
					'groupCount' => true,
					'countOutput' => true,
					'filter' => ['type' => ITEM_TYPE_ZABBIX_ACTIVE],
					'hostids' => array_keys($db_hosts)
				])
				: [];

			$db_items_active_count = array_filter(array_column($db_items_active_count, 'rowscount', 'hostid'));

			foreach ($db_hosts as $hostid => $host) {
				$host_interfaces = array_fill_keys($interface_types, []);

				foreach ($host['interfaces'] as $interface) {
					if (in_array($interface['type'], $interface_types)) {
						$host_interfaces[$interface['type']][] = $interface;
					}
				}

				if (array_key_exists('active_available', $host)) {
					$host_interfaces[INTERFACE_TYPE_AGENT_ACTIVE][] = $host['active_available'];
				}

				$host_interfaces = array_filter($host_interfaces);
				$host_interfaces_status = array_fill_keys(array_keys($host_interfaces), []);

				foreach ($host_interfaces as $type => $interfaces) {
					if ($type == INTERFACE_TYPE_AGENT_ACTIVE) {
						if (array_key_exists($hostid, $db_items_active_count)) {
							$status = $interfaces[0];
						}
						else {
							continue;
						}
					} else {
						$status = getInterfaceAvailabilityStatus($interfaces);
					}

					$interface_type_count[$type][$status]++;
					$interface_totals[$type]++;
					$host_interfaces_status[$type] = ['available' => $status];
				}

				$host_interfaces_status = array_filter($host_interfaces_status);

				if ($host_interfaces_status) {
					$total_hosts[getInterfaceAvailabilityStatus($host_interfaces_status)]++;
				}
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'layout' => $this->fields_values['layout'],
			'only_totals' => $this->fields_values['only_totals'],
			'interface_types' => $interface_types,
			'interface_type_count' => $interface_type_count,
			'interface_totals' => $interface_totals,
			'total_hosts' => $total_hosts,
			'total_hosts_sum' => array_sum($total_hosts),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
