<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\HostNavigator\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

	private const SHOW_IN_MAINTENANCE_ON = 1;
	private const HOST_STATUS_ANY = 0;
	private const SHOW_PROBLEMS_UNSUPPRESSED = 1;
	private const SHOW_PROBLEMS_OFF = 2;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'with_config' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'vars' => $this->getHosts()
		];

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getHosts(): array {
		$no_data = [
			'hosts' => [],
			'is_limit_exceeded' => false,
			'maintenances' => []
		];

		$override_hostid = $this->fields_values['override_hostid'] ? $this->fields_values['override_hostid'][0] : '';

		if ($this->isTemplateDashboard() && $override_hostid === '') {
			return $no_data;
		}

		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		$output = $this->fields_values['maintenance'] == self::SHOW_IN_MAINTENANCE_ON
			? ['hostid', 'name', 'maintenanceid', 'maintenance_status']
			: ['hostid', 'name'];

		if ($override_hostid === '' && !$this->isTemplateDashboard()) {
			// Get hosts based on filter configurations
			$hosts = API::Host()->get([
				'output' => $output,
				'groupids' => $groupids,
				'evaltype' => !$this->isTemplateDashboard() ? $this->fields_values['host_tags_evaltype'] : null,
				'tags' => !$this->isTemplateDashboard() && $this->fields_values['host_tags']
					? $this->fields_values['host_tags']
					: null,
				'search' => [
					'name' => in_array('*', $this->fields_values['hosts'], true) ? null : $this->fields_values['hosts']
				],
				'searchWildcardsEnabled' => true,
				'searchByAny' => true,
				'severities' => $this->fields_values['severities'] ?: null,
				'filter' => [
					'status' => $this->fields_values['status'] == self::HOST_STATUS_ANY
						? null
						: $this->fields_values['status'],
					'maintenance_status' => $this->fields_values['maintenance'] != self::SHOW_IN_MAINTENANCE_ON
						? HOST_MAINTENANCE_STATUS_OFF
						: null
				],
				'selectHostGroups' => ['groupid', 'name'],
				'selectTags' => ['tag', 'value'],
				'sortfield' => 'name',
				// Request more than the set limit to distinguish if there are even more hosts available
				'limit' => $this->fields_values['limit'] + 1
			]);
		}
		else {
			$hostid = $override_hostid !== '' ? $override_hostid : $this->getInput('templateid', '');

			$hosts = API::Host()->get([
				'output' => $output,
				'hostids' => [$hostid],
				'severities' => $this->fields_values['severities'] ?: null,
				'filter' => [
					'status' => $this->fields_values['status'] == self::HOST_STATUS_ANY
						? null
						: $this->fields_values['status'],
					'maintenance_status' => $this->fields_values['maintenance'] != self::SHOW_IN_MAINTENANCE_ON
						? HOST_MAINTENANCE_STATUS_OFF
						: null
				],
				'selectHostGroups' => ['groupid', 'name'],
				'selectTags' => ['tag', 'value']
			]);
		}

		if (!$hosts) {
			return $no_data;
		}

		$is_limit_exceeded = false;

		if (count($hosts) > $this->fields_values['limit']) {
			$is_limit_exceeded = true;
			array_pop($hosts);
		}

		if ($this->fields_values['problems'] != self::SHOW_PROBLEMS_OFF) {
			$hostids = [];

			foreach ($hosts as $host) {
				$hostids[] = $host['hostid'];
			}

			// Select triggers and problems to calculate number of problems for each host.
			$triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid'],
				'hostids' => $hostids,
				'skipDependent' => true,
				'monitored' => true,
				'preservekeys' => true
			]);

			$problems = API::Problem()->get([
				'output' => ['eventid', 'objectid', 'severity'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectids' => array_keys($triggers),
				'suppressed' => ($this->fields_values['problems'] == self::SHOW_PROBLEMS_UNSUPPRESSED) ? false : null,
				'severities' => $this->fields_values['severities'] ?: null,
				'symptom' => false
			]);

			// Group all problems per host per severity.
			$host_problems = [];

			foreach ($problems as $problem) {
				foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
					$host_problems[$trigger_host['hostid']][$problem['severity']][$problem['eventid']] = true;
				}
			}

			foreach ($hosts as &$host) {
				// Fill empty arrays for hosts without problems.
				if (!array_key_exists($host['hostid'], $host_problems)) {
					$host_problems[$host['hostid']] = [];
				}

				// Count the number of problems (as value) per severity (as key).
				for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity <= TRIGGER_SEVERITY_DISASTER; $severity++) {
					$host['problem_count'][$severity] = array_key_exists($severity, $host_problems[$host['hostid']])
						? count($host_problems[$host['hostid']][$severity])
						: 0;
				}
			}
			unset($host);
		}

		$maintenances = [];

		if ($this->fields_values['maintenance'] == self::SHOW_IN_MAINTENANCE_ON) {
			$maintenanceids = [];

			foreach ($hosts as &$host) {
				if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$maintenanceids[$host['maintenanceid']] = true;
				}
				else {
					unset($host['maintenanceid']);
				}
				unset($host['maintenance_status']);
			}
			unset($host);

			if ($maintenanceids) {
				$maintenances = API::Maintenance()->get([
					'output' => ['name', 'maintenance_type', 'description'],
					'maintenanceids' => array_keys($maintenanceids),
					'preservekeys' => true
				]);

				foreach ($maintenances as &$maintenance) {
					unset($maintenance['maintenanceid']);
				}
				unset($maintenance);
			}
		}

		return [
			'hosts' => $hosts,
			'is_limit_exceeded' => $is_limit_exceeded,
			'maintenances' => $maintenances
		];
	}

	private function getConfig(): array {
		$config = [
			'severities' => [
				TRIGGER_SEVERITY_NOT_CLASSIFIED => ZBX_STYLE_NA_BG,
				TRIGGER_SEVERITY_INFORMATION => ZBX_STYLE_INFO_BG,
				TRIGGER_SEVERITY_WARNING => ZBX_STYLE_WARNING_BG,
				TRIGGER_SEVERITY_AVERAGE => ZBX_STYLE_AVERAGE_BG,
				TRIGGER_SEVERITY_HIGH => ZBX_STYLE_HIGH_BG,
				TRIGGER_SEVERITY_DISASTER => ZBX_STYLE_DISASTER_BG
			],
			'show_problems' => $this->fields_values['problems'] != self::SHOW_PROBLEMS_OFF,
			'group_by' => []
		];

		return $config;
	}
}
