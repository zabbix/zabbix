<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


namespace Services\DataProviders;

use API;
use CArrayHelper;
use CPagerHelper;

class HostDataProvider extends AbstractDataProvider {

	/**
	 * Type of data provider, should be unique across all source code including modules.
	 */
	const PROVIDER_TYPE = 'monitoring.hosts';

	/**
	 * Data provider filter template name. Filter template files can be found inside partials folder.
	 *
	 * @var string $template_file
	 */
	public $template_file = 'monitoring.host.filter';

	/**
	 * @var CDiv $paging
	 */
	protected $paging;

	/**
	 * Get data for monitoring hosts page.
	 */
	public function getData(): array {
		$groupids = null;

		if ($this->fields['groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $this->fields['groupids'],
				'preservekeys' => true
			]);
			$groupids = $groups ? $this->getSubgroups($groups) : null;
		}

		$hosts = $this->getHosts([
			'output' => ['hostid', $this->fields['sort']],
			'groupids' => $groupids,
			'sortfield' => $this->fields['sort'],
			'sortorder' => $this->fields['sortorder'],
			'preservekeys' => true
		]);

		// Split result array and create paging.
		$this->paging = CPagerHelper::paginate($this->fields['page'], $hosts, $this->fields['sortorder'],
			$this->fields['view_curl']
		);

		// Get additonal data to limited host amount.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenanceid', 'maintenance_type',
				'available', 'snmp_available', 'jmx_available', 'ipmi_available', 'error', 'ipmi_error', 'snmp_error',
				'jmx_error'
			],
			'selectInterfaces' => ['ip', 'dns', 'port', 'main', 'type', 'useip'],
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'sortfield' => $this->fields['sort'],
			'sortorder' => $this->fields['sortorder'],
			'preservekeys' => true
		]);
		// Re-sort the results again.
		CArrayHelper::sort($hosts, [['field' => $this->fields['sort'], 'order' => $this->fields['sortorder']]]);

		$maintenanceids = [];

		// Select triggers and problems to calculate number of problems for each host.
		$triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'hostids' => array_keys($hosts),
			'skipDependent' => true,
			'monitored' => true,
			'preservekeys' => true
		]);

		$problems = API::Problem()->get([
			'output' => ['eventid', 'objectid', 'severity'],
			'objectids' => array_keys($triggers),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => ($this->fields['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false
		]);

		// Group all problems per host per severity.
		$host_problems = [];
		foreach ($problems as $problem) {
			foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
				$host_problems[$trigger_host['hostid']][$problem['severity']][$problem['eventid']] = true;
			}
		}

		foreach ($hosts as &$host) {
			CArrayHelper::sort($host['interfaces'], [['field' => 'main', 'order' => ZBX_SORT_DOWN]]);

			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}

			// Fill empty arrays for hosts without problems.
			if (!array_key_exists($host['hostid'], $host_problems)) {
				$host_problems[$host['hostid']] = [];
			}

			// Count the number of problems (as value) per severity (as key).
			for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
				$host['problem_count'][$severity] = array_key_exists($severity, $host_problems[$host['hostid']])
					? count($host_problems[$host['hostid']][$severity])
					: 0;
			}

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

			$host['tags'] = $tags;
		}
		unset($host);

		if ($maintenanceids) {
			$maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);

			foreach ($hosts as &$host) {
				if (array_key_exists('maintenanceid', $host)
						&& array_key_exists($host['maintenanceid'], $maintenances)) {
					$host['maintenance'] = $maintenances[$host['maintenanceid']];
				}
			}
			unset($host);
		}

		return $hosts;
	}

	/**
	 * Get monitoring host items count according data provider filter settings.
	 */
	public function getCount(): int {
		$count = (int) $this->getHosts([
			'countOutput' => true
		]);

		return $count;
	}

	/**
	 * Get filter fields default value.
	 */
	public function getFieldsDefaults(): array {
		return [
			'name'					=> '',
			'groupids'				=> [],
			'ip'					=> '',
			'dns'					=> '',
			'port'					=> '',
			'status'				=> '-1',
			'evaltype'				=> (string) TAG_EVAL_TYPE_AND_OR,
			'tags'					=> [],
			'severities'			=> [],
			'show_suppressed'		=> (string) ZBX_PROBLEM_SUPPRESSED_FALSE,
			'maintenance_status'	=> (string) HOST_MAINTENANCE_STATUS_OFF,
		];
	}

	/**
	 * Set data provider input, passed input will be merged with exisitng fields values.
	 *
	 * @param array $input    Array of filter fields for data provider input.
	 */
	public function updateFields(array $input) {
		if (array_key_exists('tags', $input)) {
			$input['tags'] = array_filter($input['tags'], function ($tag) {
				return ($tag['tag'] !== '' || $tag['value'] !== '');
			});
		}

		if (array_key_exists('severities', $input)) {
			$input['severities'] = array_map('intval', array_values($input['severities']));
		}

		return parent::updateFields($input);
	}

	/**
	 * Get array of hosts according defined filter.
	 *
	 * @param array $options    Array of additional options passed to API.
	 */
	protected function getHosts(array $options = []) {
		$search = array_intersect_key($this->fields, ['name' => '', 'ip' => '', 'dns' => '']);
		$search = array_filter($search, 'strlen');

		return API::Host()->get($options + [
			'output' => ['hostid'],
			'evaltype' => $this->fields['evaltype'],
			'tags' => $this->fields['tags'] ? $this->fields['tags'] : null,
			'inheritedTags' => true,
			'groupids' => $this->fields['groupids'] ? $this->fields['groupids'] : null,
			'severities' => $this->fields['severities'] ? $this->fields['severities'] : null,
			'withProblemsSuppressed' => $this->fields['severities']
				? (($this->fields['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false)
				: null,
			'search' => $search ? $search : null,
			'filter' => [
				'status' => ($this->fields['status'] == -1) ? null : $this->fields['status'],
				'port' => ($this->fields['port'] === '') ? null : $this->fields['port'],
				'maintenance_status' => ($this->fields['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
					? null
					: HOST_MAINTENANCE_STATUS_OFF,
			],
			'limit' => $this->fields['limit']
		]);
	}

	/**
	 * Get array of ids of groups and their sub groups.
	 *
	 * @param array  $groups              Array of host group 'name' with 'groupid' as associative array index.
	 * @param string $groups[]['name']    Group name.
	 */
	protected function getSubgroups(array $groups): array {
		$child_groups = array_column($groups, 'name');

		foreach ($child_groups as &$child_group) {
			$child_group .= '/';
		}
		unset($child_group);

		$groups += API::HostGroup()->get([
			'output' => ['groupid'],
			'search' => ['name' => $child_groups],
			'startSearch' => true,
			'searchByAny' => true,
			'preservekeys' => true
		]);

		return array_keys($groups);
	}
}
