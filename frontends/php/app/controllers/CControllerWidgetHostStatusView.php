<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';
require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetHostStatusView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_HOST_STATUS);
		$this->setValidationRules([
			'name' => 'string',
			'fullscreen' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$config = select_config();

		$filter_groupids = $fields['groupids'] ? getSubGroups($fields['groupids']) : null;
		$filter_hostids = $fields['hostids'] ? $fields['hostids'] : null;
		$filter_problem = ($fields['problem'] !== '') ? $fields['problem'] : null;
		$filter_severities = $fields['severities']
			? $fields['severities']
			: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
		$filter_maintenance = $fields['maintenance'];
		$filter_ext_ack = $fields['ext_ack'];

		if ($fields['exclude_groupids']) {
			$exclude_groupids = getSubGroups($fields['exclude_groupids']);

			if ($filter_hostids === null) {
				// get all groups if no selected groups defined
				if ($filter_groupids === null) {
					$filter_groupids = array_keys(API::HostGroup()->get([
						'output' => [],
						'preservekeys' => true
					]));
				}

				$filter_groupids = array_diff($filter_groupids, $exclude_groupids);

				// get available hosts
				$filter_hostids = array_keys(API::Host()->get([
					'output' => [],
					'groupids' => $filter_groupids,
					'preservekeys' => true
				]));
			}

			$exclude_hostids = array_keys(API::Host()->get([
				'output' => [],
				'groupids' => $exclude_groupids,
				'preservekeys' => true
			]));

			$filter_hostids = array_diff($filter_hostids, $exclude_hostids);
		}

		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		// get hosts
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectGroups' => ['groupid'],
			'groupids' => array_keys($groups),
			'hostids' => $filter_hostids,
			'filter' => ['maintenance_status' => ($filter_maintenance == 0) ? 0 : null],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		CArrayHelper::sort($hosts, ['name']);
		CArrayHelper::sort($groups, ['name']);

		// get triggers
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'selectHosts' => ['hostid'],
			'search' => ['description' => $filter_problem],
			'filter' => [
				'priority' => $filter_severities,
				'value' => TRIGGER_VALUE_TRUE
			],
			'maintenance' => ($filter_maintenance == 0) ? false : null,
			'monitored' => true
		]);

		if ($filter_ext_ack != EXTACK_OPTION_ALL) {
			$hosts_with_unack_triggers = [];

			$triggers_unack = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectHosts' => ['hostid'],
				'search' => ['description' => $filter_problem],
				'filter' => [
					'priority' => $filter_severities,
					'value' => TRIGGER_VALUE_TRUE
				],
				'withLastEventUnacknowledged' => true,
				'maintenance' => ($filter_maintenance == 0) ? false : null,
				'monitored' => true,
				'preservekeys' => true
			]);

			foreach ($triggers_unack as $tunack) {
				foreach ($tunack['hosts'] as $unack_host) {
					$hosts_with_unack_triggers[$unack_host['hostid']] = $unack_host['hostid'];
				}
			}
		}

		$hosts_data = [];
		$problematic_host_list = [];
		$lastUnack_host_list = [];
		$highest_severity = [];
		$highest_severity2 = [];

		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $trigger_host) {
				if (!array_key_exists($trigger_host['hostid'], $hosts)) {
					continue;
				}
				else {
					$host = $hosts[$trigger_host['hostid']];
				}

				if ($filter_ext_ack != EXTACK_OPTION_ALL
						&& array_key_exists($host['hostid'], $hosts_with_unack_triggers)) {
					if (!array_key_exists($host['hostid'], $lastUnack_host_list)) {
						$lastUnack_host_list[$host['hostid']] = [];
						$lastUnack_host_list[$host['hostid']]['host'] = $host['name'];
						$lastUnack_host_list[$host['hostid']]['hostid'] = $host['hostid'];

						$lastUnack_host_list[$host['hostid']]['severities'] = [];

						foreach ($filter_severities as $severity) {
							$lastUnack_host_list[$host['hostid']]['severities'][$severity] = 0;
						}
					}
					if (array_key_exists($trigger['triggerid'], $triggers_unack)) {
						$lastUnack_host_list[$host['hostid']]['severities'][$trigger['priority']]++;
					}

					foreach ($host['groups'] as $gnum => $group) {
						if (!array_key_exists($group['groupid'], $highest_severity2)) {
							$highest_severity2[$group['groupid']] = 0;
						}

						if ($filter_ext_ack == EXTACK_OPTION_UNACK) {
							if ($trigger['priority'] > $highest_severity2[$group['groupid']]
									&& array_key_exists($trigger['triggerid'], $triggers_unack)) {
								$highest_severity2[$group['groupid']] = $trigger['priority'];
							}
						}
						elseif ($trigger['priority'] > $highest_severity2[$group['groupid']]) {
							$highest_severity2[$group['groupid']] = $trigger['priority'];
						}

						if (!array_key_exists($group['groupid'], $hosts_data)) {
							$hosts_data[$group['groupid']] = [
								'problematic' => 0,
								'ok' => 0,
								'lastUnack' => 0,
								'hostids_all' => [],
								'hostids_unack' => []
							];
						}

						if (!array_key_exists($host['hostid'], $hosts_data[$group['groupid']]['hostids_unack'])) {
							$hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']] = $host['hostid'];
							$hosts_data[$group['groupid']]['lastUnack']++;
						}
					}
				}

				if (!array_key_exists($host['hostid'], $problematic_host_list)) {
					$problematic_host_list[$host['hostid']] = [];
					$problematic_host_list[$host['hostid']]['host'] = $host['name'];
					$problematic_host_list[$host['hostid']]['hostid'] = $host['hostid'];

					$problematic_host_list[$host['hostid']]['severities'] = [];

					foreach ($filter_severities as $severity) {
						$problematic_host_list[$host['hostid']]['severities'][$severity] = 0;
					}
				}
				$problematic_host_list[$host['hostid']]['severities'][$trigger['priority']]++;

				foreach ($host['groups'] as $gnum => $group) {
					if (!array_key_exists($group['groupid'], $highest_severity)) {
						$highest_severity[$group['groupid']] = 0;
					}

					if ($trigger['priority'] > $highest_severity[$group['groupid']]) {
						$highest_severity[$group['groupid']] = $trigger['priority'];
					}

					if (!array_key_exists($group['groupid'], $hosts_data)) {
						$hosts_data[$group['groupid']] = [
							'problematic' => 0,
							'ok' => 0,
							'lastUnack' => 0,
							'hostids_all' => [],
							'hostids_unack' => []
						];
					}

					if (!array_key_exists($host['hostid'], $hosts_data[$group['groupid']]['hostids_all'])) {
						$hosts_data[$group['groupid']]['hostids_all'][$host['hostid']] = $host['hostid'];

						/*
						 * Display acknowledged problem triggers in "Without problems" column when filter dashboard is
						 * enabled and is set to display "Unacknowledged only". Host and trigger must not be in
						 * unacknowledged lists. Count as problematic host otherwise.
						 */
						if ($filter_ext_ack == EXTACK_OPTION_UNACK
								&& !array_key_exists($host['hostid'], $hosts_with_unack_triggers)
								&& !array_key_exists($trigger['triggerid'], $triggers_unack)) {
							$hosts_data[$group['groupid']]['ok']++;
						}
						else {
							$hosts_data[$group['groupid']]['problematic']++;
						}
					}
				}
			}
		}

		foreach ($hosts as $host) {
			foreach ($host['groups'] as $group) {
				if (!array_key_exists($group['groupid'], $groups)) {
					continue;
				}

				if (!array_key_exists('hosts', $groups[$group['groupid']])) {
					$groups[$group['groupid']]['hosts'] = [];
				}
				$groups[$group['groupid']]['hosts'][$host['hostid']] = ['hostid' => $host['hostid']];

				if (!array_key_exists($group['groupid'], $highest_severity)) {
					$highest_severity[$group['groupid']] = 0;
				}

				if (!array_key_exists($group['groupid'], $hosts_data)) {
					$hosts_data[$group['groupid']] = ['problematic' => 0, 'ok' => 0, 'lastUnack' => 0];
				}

				if (!array_key_exists($host['hostid'], $problematic_host_list)) {
					$hosts_data[$group['groupid']]['ok']++;
				}
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'filter' => [
				'hostids' => $fields['hostids'],
				'problem' => $fields['problem'],
				'severities' => $filter_severities,
				'maintenance' => $fields['maintenance'],
				'hide_empty_groups' => $fields['hide_empty_groups'],
				'ext_ack' => $fields['ext_ack']
			],
			'config' => [
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'problematic_host_list' => $problematic_host_list,
			'lastUnack_host_list' => $lastUnack_host_list,
			'highest_severity2' => $highest_severity2,
			'highest_severity' => $highest_severity,
			'hosts_data' => $hosts_data,
			'groups' => $groups,
			'hosts' => $hosts,
			'fullscreen' => $this->getInput('fullscreen', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
