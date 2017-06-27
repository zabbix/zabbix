<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CControllerWidgetHostsView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$config = select_config();

		$filter = [
			'groupids' => null,
			'maintenance' => null,
			'severity' => null,
			'trigger_name' => '',
			'extAck' => 0
		];

		if (CProfile::get('web.dashconf.filter.enable', 0) == 1) {
			// groups
			if (CProfile::get('web.dashconf.groups.grpswitch', 0) == 0) {
				// null mean all groups
				$filter['groupids'] = null;
			}
			else {
				$filter['groupids'] = zbx_objectValues(CFavorite::get('web.dashconf.groups.groupids'), 'value');
				$hide_groupids = zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value');

				if ($hide_groupids) {
					// get all groups if no selected groups defined
					if (!$filter['groupids']) {
						$dbHostGroups = API::HostGroup()->get([
							'output' => ['groupid']
						]);
						$filter['groupids'] = zbx_objectValues($dbHostGroups, 'groupid');
					}

					$filter['groupids'] = array_diff($filter['groupids'], $hide_groupids);

					// get available hosts
					$dbAvailableHosts = API::Host()->get([
						'output' => ['hostid'],
						'groupids' => $filter['groupids']
					]);
					$availableHostIds = zbx_objectValues($dbAvailableHosts, 'hostid');

					$dbDisabledHosts = API::Host()->get([
						'output' => ['hostid'],
						'groupids' => $hide_groupids
					]);
					$disabledHostIds = zbx_objectValues($dbDisabledHosts, 'hostid');

					$filter['hostids'] = array_diff($availableHostIds, $disabledHostIds);
				}
				elseif (!$filter['groupids']) {
					// null mean all groups
					$filter['groupids'] = null;
				}
			}

			// hosts
			$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
			$filter['maintenance'] = ($maintenance == 0) ? 0 : null;

			// triggers
			$severity = CProfile::get('web.dashconf.triggers.severity', null);
			$filter['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
			$filter['severity'] = zbx_toHash($filter['severity']);
			$filter['trigger_name'] = CProfile::get('web.dashconf.triggers.name', '');

			$filter['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
		}

		// get host groups
		$options = [
			'output' => ['groupid', 'name'],
			'groupids' => $filter['groupids'],
			'monitored_hosts' => true,
			'preservekeys' => true
		];

		if (array_key_exists('hostids', $filter)) {
			$options['hostids'] = $filter['hostids'];
		}

		$groups = API::HostGroup()->get($options);

		$filter_groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $filter['groupids']
		]);

		$filter_groups_names = [];
		foreach ($filter_groups as $group) {
			$filter_groups_names[] = $group['name'].'/';
		}

		if ($filter_groups_names) {
			$options = [
				'output' => ['groupid', 'name'],
				'search' => ['name' => $filter_groups_names],
				'monitored_hosts' => true,
				'searchByAny' => true,
				'startSearch' => true
			];

			if (array_key_exists('hostids', $filter)) {
				$options['hostids'] = $filter['hostids'];
			}

			$child_groups = API::HostGroup()->get($options);
			if ($child_groups) {
				foreach ($child_groups as $child_group) {
					$groups[$child_group['groupid']] = $child_group;
				}
			}
		}

		// get hosts
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectGroups' => ['groupid'],
			'groupids' => array_keys($groups),
			'hostids' => array_key_exists('hostids', $filter) ? $filter['hostids'] : null,
			'filter' => ['maintenance_status' => $filter['maintenance']],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		CArrayHelper::sort($hosts, ['name']);
		CArrayHelper::sort($groups, ['name']);

		// get triggers
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'selectHosts' => ['hostid'],
			'search' => ($filter['trigger_name'] !== '') ? ['description' => $filter['trigger_name']] : null,
			'filter' => [
				'priority' => $filter['severity'],
				'value' => TRIGGER_VALUE_TRUE
			],
			'maintenance' => $filter['maintenance'],
			'monitored' => true
		]);

		if ($filter['extAck']) {
			$hosts_with_unack_triggers = [];

			$triggers_unack = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectHosts' => ['hostid'],
				'search' => ($filter['trigger_name'] !== '')
					? ['description' => $filter['trigger_name']]
					: null,
				'filter' => [
					'priority' => $filter['severity'],
					'value' => TRIGGER_VALUE_TRUE
				],
				'withLastEventUnacknowledged' => true,
				'maintenance' => $filter['maintenance'],
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

				if ($filter['extAck'] && array_key_exists($host['hostid'], $hosts_with_unack_triggers)) {
					if (!array_key_exists($host['hostid'], $lastUnack_host_list)) {
						$lastUnack_host_list[$host['hostid']] = [];
						$lastUnack_host_list[$host['hostid']]['host'] = $host['name'];
						$lastUnack_host_list[$host['hostid']]['hostid'] = $host['hostid'];
						$lastUnack_host_list[$host['hostid']]['severities'] = [];
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
						$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
					}
					if (array_key_exists($trigger['triggerid'], $triggers_unack)) {
						$lastUnack_host_list[$host['hostid']]['severities'][$trigger['priority']]++;
					}

					foreach ($host['groups'] as $gnum => $group) {
						if (!array_key_exists($group['groupid'], $highest_severity2)) {
							$highest_severity2[$group['groupid']] = 0;
						}

						if ($trigger['priority'] > $highest_severity2[$group['groupid']]) {
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

					for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
						$problematic_host_list[$host['hostid']]['severities'][$severity] = 0;
					}

					krsort($problematic_host_list[$host['hostid']]['severities']);
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
						if ($filter['extAck'] == EXTACK_OPTION_UNACK
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
			'filter' => $filter,
			'config' => [
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'problematic_host_list' => $problematic_host_list,
			'lastUnack_host_list' => $lastUnack_host_list,
			'highest_severity2' => $highest_severity2,
			'highest_severity' => $highest_severity,
			'hosts_data' => $hosts_data,
			'groups' => $groups,
			'hosts' => $hosts
		]));
	}
}
