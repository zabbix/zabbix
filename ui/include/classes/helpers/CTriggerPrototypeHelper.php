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


class CTriggerPrototypeHelper extends CTriggerGeneralHelper {

	/**
	 * @param array $src_options
	 * @param array $dst_options
	 *
	 * @return bool
	 */
	public static function copy(array $src_options, array $dst_options): bool {
		$src_triggers = self::getSourceTriggerPrototypes($src_options);

		if (!$src_triggers) {
			return true;
		}

		$src_triggerids = array_fill_keys(array_keys($src_triggers), true);
		$src_dep_triggers = [];
		$src_master_dep_triggers = [];
		$src_hosts = [];

		foreach ($src_triggers as $src_trigger) {
			foreach ($src_trigger['dependencies'] as $master_trigger) {
				if (array_key_exists($master_trigger['triggerid'], $src_triggerids)) {
					$src_dep_triggers[$src_trigger['triggerid']] = $src_trigger;
					$src_master_dep_triggers[$master_trigger['triggerid']][$src_trigger['triggerid']] = true;

					unset($src_triggers[$src_trigger['triggerid']]);
				}
				else {
					$src_host = $src_trigger['hosts'][$src_trigger['discoveryRule']['hostid']];

					$src_hosts[$master_trigger['triggerid']][$src_trigger['triggerid']] = $src_host;
				}
			}
		}

		$dst_hosts = array_key_exists('templateids', $dst_options)
			? API::Template()->get([
				'output' => ['host'],
				'preservekeys' => true
			] + $dst_options)
			: API::Host()->get([
				'output' => ['host', 'status'],
				'preservekeys' => true
			] + $dst_options);

		$dst_master_triggerids = self::getDestinationMasterTriggers($src_hosts, $dst_hosts);

		do {
			$dst_triggers = [];

			foreach ($dst_hosts as $dst_hostid => $dst_host) {
				foreach ($src_triggers as $src_trigger) {
					$dst_trigger = array_diff_key($src_trigger, array_flip(['triggerid', 'hosts', 'discoveryRule']));

					$src_host = $src_trigger['hosts'][$src_trigger['discoveryRule']['hostid']];

					$dst_trigger['expression'] = self::getExpressionWithReplacedHost(
						$src_trigger['expression'], $src_host['host'], $dst_host['host']
					);

					if ($src_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						$dst_trigger['recovery_expression'] = self::getExpressionWithReplacedHost(
							$src_trigger['recovery_expression'], $src_host['host'], $dst_host['host']
						);
					}

					foreach ($dst_trigger['dependencies'] as &$master_trigger) {
						if (array_key_exists($master_trigger['triggerid'], $dst_master_triggerids)) {
							$dst_triggerid = $dst_master_triggerids[$master_trigger['triggerid']][$dst_hostid];

							$master_trigger['triggerid'] = is_array($dst_triggerid)
								? $dst_triggerid[$src_host['hostid']]
								: $dst_triggerid;
						}
					}
					unset($master_trigger);

					$dst_triggers[] = $dst_trigger;
				}
			}

			$response = API::TriggerPrototype()->create($dst_triggers);

			if ($response === false) {
				return false;
			}

			$_src_triggers = [];

			if ($src_dep_triggers) {
				foreach ($dst_hosts as $dst_hostid => $foo) {
					foreach ($src_triggers as $src_trigger) {
						$dst_triggerid = array_shift($response['triggerids']);

						if (array_key_exists($src_trigger['triggerid'], $src_master_dep_triggers)) {
							$dst_master_triggerids[$src_trigger['triggerid']][$dst_hostid] = $dst_triggerid;

							foreach ($src_master_dep_triggers[$src_trigger['triggerid']] as $src_dep_triggerid => $f) {
								unset($src_master_dep_triggers[$src_trigger['triggerid']][$src_dep_triggerid]);

								if (!$src_master_dep_triggers[$src_trigger['triggerid']]) {
									unset($src_master_dep_triggers[$src_trigger['triggerid']]);
								}

								foreach ($src_dep_triggers[$src_dep_triggerid]['dependencies'] as $master_trigger) {
									if (bccomp($master_trigger['triggerid'], $src_trigger['triggerid']) == 0) {
										continue;
									}

									if (array_key_exists($master_trigger['triggerid'], $src_master_dep_triggers)
											&& array_key_exists($src_dep_triggerid, $src_master_dep_triggers[$master_trigger['triggerid']])) {
										continue 3;
									}
								}

								$_src_triggers[] = $src_dep_triggers[$src_dep_triggerid];
								unset($src_dep_triggers[$src_dep_triggerid]);
							}
						}
					}
				}
			}

			$src_triggers = $_src_triggers;
		} while ($src_triggers);

		return true;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceTriggerPrototypes(array $src_options): array {
		$src_triggers = API::TriggerPrototype()->get([
			'output' => ['triggerid', 'expression', 'description', 'url_name', 'url', 'status', 'priority', 'comments',
				'type', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close',
				'opdata', 'event_name', 'discover'
			],
			'selectDependencies' => ['triggerid'],
			'selectTags' => ['tag', 'value'],
			'selectHosts' => ['hostid', 'host'],
			'selectDiscoveryRule' => ['itemid', 'hostid'],
			'preservekeys' => true
		] + $src_options);

		if (!$src_triggers) {
			return [];
		}

		$src_triggers = CMacrosResolverHelper::resolveTriggerExpressions($src_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($src_triggers as &$src_trigger) {
			$src_trigger['hosts'] = array_column($src_trigger['hosts'], null, 'hostid');
		}
		unset($src_trigger);

		return $src_triggers;
	}
}
