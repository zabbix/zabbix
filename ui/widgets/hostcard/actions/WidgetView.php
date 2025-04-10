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


namespace Widgets\HostCard\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData;

use Widgets\HostCard\Includes\CWidgetFieldHostSections;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'error' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$data['host'] = [];
		}
		else {
			$host = $this->getHost();

			if ($host !== null) {
				$data['host'] = $host;
				$data['sections'] = $this->fields_values['sections'];
				$data['inventory'] = $this->fields_values['inventory'];
			}
			else {
				$data['error'] = _('No permissions to referred object or it does not exist!');
			}
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getHost(): ?array {
		if ($this->isTemplateDashboard()) {
			$hostids = $this->fields_values['override_hostid'];
		}
		else {
			$hostids = $this->fields_values['hostid'] ?: null;
		}

		if ($hostids === null) {
			return null;
		}

		$options = [
			'output' => ['hostid', 'name', 'status', 'maintenanceid', 'maintenance_status', 'maintenance_type',
				'description', 'active_available', 'monitored_by', 'proxyid', 'proxy_groupid'
			],
			'hostids' => $hostids
		];

		if (in_array(CWidgetFieldHostSections::SECTION_HOST_GROUPS, $this->fields_values['sections'])) {
			$options['selectHostGroups'] = ['name'];
		}

		if (in_array(CWidgetFieldHostSections::SECTION_MONITORING, $this->fields_values['sections'])) {
			$options['selectGraphs'] = API_OUTPUT_COUNT;
			$options['selectHttpTests'] = API_OUTPUT_COUNT;
		}

		if (in_array(CWidgetFieldHostSections::SECTION_AVAILABILITY, $this->fields_values['sections'])) {
			$options['selectInterfaces'] = ['interfaceid', 'ip', 'dns', 'port', 'main', 'type', 'useip', 'available',
				'error', 'details'
			];
		}

		if (in_array(CWidgetFieldHostSections::SECTION_TEMPLATES, $this->fields_values['sections'])) {
			$options['selectParentTemplates'] = ['templateid'];
		}

		if (in_array(CWidgetFieldHostSections::SECTION_INVENTORY, $this->fields_values['sections'])) {
			$output = [];
			$inventory_fields = getHostInventories();

			foreach ($this->fields_values['inventory'] as $nr) {
				$output[] = $inventory_fields[$nr]['db_field'];
			}

			$options['selectInventory'] = $output ?: array_column($inventory_fields, 'db_field');
		}

		if (in_array(CWidgetFieldHostSections::SECTION_TAGS, $this->fields_values['sections'])) {
			$options['selectTags'] = ['tag', 'value'];
			$options['selectInheritedTags'] = ['tag', 'value'];
		}

		$db_hosts = API::Host()->get($options);

		if (!$db_hosts) {
			return null;
		}

		$host = $db_hosts[0];

		if ($host['status'] == HOST_STATUS_MONITORED) {
			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$db_maintenances = API::Maintenance()->get([
					'output' => ['name', 'description'],
					'maintenanceids' => [$host['maintenanceid']]
				]);

				$host['maintenance'] = $db_maintenances
					? $db_maintenances[0]
					: [
						'name' => _('Inaccessible maintenance'),
						'description' => ''
					];
			}

			$db_triggers = API::Trigger()->get([
				'output' => [],
				'hostids' => [$host['hostid']],
				'skipDependent' => true,
				'monitored' => true,
				'preservekeys' => true
			]);

			$db_problems = API::Problem()->get([
				'output' => ['eventid', 'severity'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectids' => array_keys($db_triggers),
				'suppressed' => $this->fields_values['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE ? null : false,
				'symptom' => false
			]);

			$host_problems = [];

			foreach ($db_problems as $problem) {
				$host_problems[$problem['severity']][$problem['eventid']] = true;
			}

			for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
				$host['problem_count'][$severity] = array_key_exists($severity, $host_problems)
					? count($host_problems[$severity])
					: 0;
			}
		}

		if (in_array(CWidgetFieldHostSections::SECTION_HOST_GROUPS, $this->fields_values['sections'])) {
			CArrayHelper::sort($host['hostgroups'], ['name']);
		}

		if (in_array(CWidgetFieldHostSections::SECTION_MONITORING, $this->fields_values['sections'])) {
			$db_items_count = API::Item()->get([
				'countOutput' => true,
				'hostids' => [$host['hostid']],
				'webitems' => true,
				'monitored' => true
			]);

			$host['dashboard_count'] = count(getHostDashboards($host['hostid']));
			$host['item_count'] = $db_items_count;
			$host['graph_count'] = $host['graphs'];
			$host['web_scenario_count'] = $host['httpTests'];

			unset($host['graphs'], $host['httpTests']);
		}

		if (in_array(CWidgetFieldHostSections::SECTION_AVAILABILITY, $this->fields_values['sections'])) {
			$interface_enabled_items_count = getEnabledItemsCountByInterfaceIds(
				array_column($host['interfaces'], 'interfaceid')
			);

			foreach ($host['interfaces'] as &$interface) {
				$interfaceid = $interface['interfaceid'];
				$interface['has_enabled_items'] = array_key_exists($interfaceid, $interface_enabled_items_count)
					&& $interface_enabled_items_count[$interfaceid] > 0;
			}
			unset($interface);

			$enabled_active_items_count = getEnabledItemTypeCountByHostId(ITEM_TYPE_ZABBIX_ACTIVE, [$host['hostid']]);

			if ($enabled_active_items_count) {
				$host['interfaces'][] = [
					'type' => INTERFACE_TYPE_AGENT_ACTIVE,
					'available' => $host['active_available'],
					'has_enabled_items' => true,
					'error' => ''
				];
			}

			unset($host['active_available']);
		}

		if (in_array(CWidgetFieldHostSections::SECTION_MONITORED_BY, $this->fields_values['sections'])) {
			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$db_proxies = API::Proxy()->get([
					'output' => ['name'],
					'proxyids' => [$host['proxyid']]
				]);
				$host['proxy'] = $db_proxies[0];
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$db_proxy_groups = API::ProxyGroup()->get([
					'output' => ['name'],
					'proxy_groupids' => [$host['proxy_groupid']]
				]);
				$host['proxy_group'] = $db_proxy_groups[0];
			}
		}

		if (in_array(CWidgetFieldHostSections::SECTION_TEMPLATES, $this->fields_values['sections'])) {
			if ($host['parentTemplates']) {
				$db_templates = API::Template()->get([
					'output' => ['templateid', 'name'],
					'selectParentTemplates' => ['templateid', 'name'],
					'templateids' => array_column($host['parentTemplates'], 'templateid'),
					'preservekeys' => true
				]);

				CArrayHelper::sort($db_templates, ['name']);

				foreach ($db_templates as &$template) {
					CArrayHelper::sort($template['parentTemplates'], ['name']);
				}
				unset($template);

				$host['templates'] = $db_templates;
			}
			else {
				$host['templates'] = [];
			}

			unset($host['parentTemplates']);
		}

		if (in_array(CWidgetFieldHostSections::SECTION_TAGS, $this->fields_values['sections'])) {
			// Merge host tags with template tags, and skip duplicate tags and values.
			if (!$host['inheritedTags']) {
				$tags = $host['tags'];
			}
			elseif (!$host['tags']) {
				$tags = $host['inheritedTags'];
			}
			else {
				$tags = $host['tags'];

				foreach ($host['inheritedTags'] as $template_tag) {
					foreach ($tags as $host_tag) {
						// Skip tags with same name and value.
						if ($host_tag['tag'] === $template_tag['tag']
								&& $host_tag['value'] === $template_tag['value']) {
							continue 2;
						}
					}

					$tags[] = $template_tag;
				}
			}

			CArrayHelper::sort($tags, ['tag', 'value']);

			$host['tags'] = $tags;
		}

		return $host;
	}
}
