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


class CTriggerGeneralHelper {

	/**
	 * @param array  $src_hosts
	 * @param string $src_hosts[<src_master_triggerid>][<src_triggerid>]  Source host.
	 * @param array  $dst_hosts
	 * @param array  $dst_hosts[<dst_hostid>]                             Destination host.
	 *
	 * @return array [<src_master_triggerid>][<dst_hostid>] = <dst_master_triggerid>
	 *
	 * @throws Exception
	 */
	protected static function getDestinationMasterTriggers(array $src_hosts, array $dst_hosts): array {
		if (!$src_hosts) {
			return [];
		}

		$dst_hostids = array_keys($dst_hosts);

		$src_master_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_expression'],
			'selectHosts' => ['hostid', 'host'],
			'triggerids' => array_keys($src_hosts),
			'preservekeys' => true
		]);

		$src_master_triggers = CMacrosResolverHelper::resolveTriggerExpressions($src_master_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$src_descriptions = [];
		$dst_master_triggerids = [];

		foreach ($src_master_triggers as $src_master_trigger) {
			$src_master_trigger_hostids = array_column($src_master_trigger['hosts'], 'hostid');
			$src_hostids = [];

			foreach ($src_hosts[$src_master_trigger['triggerid']] as $src_host) {
				if (in_array($src_host['hostid'], $src_master_trigger_hostids)) {
					$src_descriptions[$src_master_trigger['description']] = true;

					$src_hostids[$src_host['hostid']] = true;
				}
			}

			if (count($src_hostids) == 1) {
				foreach ($dst_hostids as $dst_hostid) {
					$dst_master_triggerids[$src_master_trigger['triggerid']][$dst_hostid] = 0;
				}
			}
			else {
				foreach ($src_hostids as $src_hostid => $foo) {
					foreach ($dst_hostids as $dst_hostid) {
						$dst_master_triggerids[$src_master_trigger['triggerid']][$dst_hostid][$src_hostid] = 0;
					}
				}
			}
		}

		if (!$src_descriptions) {
			return [];
		}

		$dst_master_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_expression'],
			'selectHosts' => ['hostid', 'host'],
			'hostids' => $dst_hostids,
			'filter' => ['description' => array_keys($src_descriptions)],
			'preservekeys' => true
		]);

		$dst_master_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dst_master_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$_dst_master_triggerids = [];

		foreach ($dst_master_triggers as &$dst_master_trigger) {
			$dst_master_trigger['hosts'] = array_column($dst_master_trigger['hosts'], null, 'hostid');

			$_dst_hostids = array_intersect(array_keys($dst_master_trigger['hosts']), $dst_hostids);

			foreach ($_dst_hostids as $_dst_hostid) {
				$_dst_master_triggerids[$dst_master_trigger['description']][$_dst_hostid][] =
					$dst_master_trigger['triggerid'];
			}
		}
		unset($dst_master_trigger);

		foreach ($dst_master_triggerids as $src_master_triggerid => &$dst_host_master_triggers) {
			$src_master_trigger = $src_master_triggers[$src_master_triggerid];

			$description = $src_master_trigger['description'];

			if (!array_key_exists($description, $_dst_master_triggerids)) {
				self::throwTriggerCopyException(
					key($src_hosts[$src_master_triggerid]), $description, reset($dst_hosts)
				);
			}

			foreach ($dst_host_master_triggers as $dst_hostid => &$dst_triggerid) {
				if (!array_key_exists($dst_hostid, $_dst_master_triggerids[$description])) {
					self::throwTriggerCopyException(
						key($src_hosts[$src_master_triggerid]), $description, $dst_hosts[$dst_hostid]
					);
				}

				foreach ($_dst_master_triggerids[$description][$dst_hostid] as $_dst_triggerid) {
					$dst_host = $dst_master_triggers[$_dst_triggerid]['hosts'][$dst_hostid];

					foreach ($src_hosts[$src_master_triggerid] as $src_host) {
						$expression = self::getExpressionWithReplacedHost(
							$dst_master_triggers[$_dst_triggerid]['expression'], $dst_host['host'], $src_host['host']
						);

						if ($expression !== $src_master_trigger['expression']) {
							continue;
						}

						$recovery_expression = self::getExpressionWithReplacedHost(
							$dst_master_triggers[$_dst_triggerid]['recovery_expression'], $dst_host['host'],
							$src_host['host']
						);

						if ($recovery_expression !== $src_master_trigger['recovery_expression']) {
							continue;
						}

						if (is_array($dst_triggerid)) {
							$dst_triggerid[$src_host['hostid']] = $_dst_triggerid;
						}
						else {
							$dst_triggerid = $_dst_triggerid;
						}
					}

					if ((is_array($dst_triggerid) && !in_array(0, $dst_triggerid))
							|| (!is_array($dst_triggerid) && $dst_triggerid != 0)) {
						break;
					}
				}

				$dst_triggerids = is_array($dst_triggerid) ? $dst_triggerid : [$dst_triggerid];

				foreach ($dst_triggerids as $_dst_triggerid) {
					if ($_dst_triggerid == 0) {
						self::throwTriggerCopyException(
							key($src_hosts[$src_master_triggerid]), $description, $dst_hosts[$dst_hostid]
						);
					}
				}
			}
			unset($dst_triggerid);
		}
		unset($dst_host_master_triggers);

		return $dst_master_triggerids;
	}

	/**
	 * @param string $src_triggerid
	 * @param string $src_master_description
	 * @param array  $dst_host
	 *
	 * @throws Exception
	 */
	private static function throwTriggerCopyException(string $src_triggerid, string $src_master_description,
			array $dst_host): void {
		$src_triggers = API::Trigger()->get([
			'output' => ['description'],
			'triggerids' => $src_triggerid
		]);

		$error = array_key_exists('status', $dst_host)
			? _('Cannot copy trigger "%1$s" without the trigger "%2$s", on which it depends, to the host "%3$s".')
			: _('Cannot copy trigger "%1$s" without the trigger "%2$s", on which it depends, to the template "%3$s".');

		error(sprintf($error, $src_triggers[0]['description'], $src_master_description, $dst_host['host']));

		throw new Exception();
	}

	/**
	 * Replaces a host in the trigger expression with the one provided.
	 * nodata(/localhost/agent.ping, 5m)  =>  nodata(/localhost6/agent.ping, 5m)
	 *
	 * @param string $expression  Full expression with host names and item keys.
	 * @param string $src_host
	 * @param string $dst_host
	 *
	 * @return string
	 */
	public static function getExpressionWithReplacedHost(string $expression, string $src_host,
			string $dst_host): string {
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
				[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
			);
			$hist_function = end($hist_functions);

			do {
				$query_parameter = $hist_function['data']['parameters'][0];

				if ($query_parameter['data']['host'] === $src_host) {
					$expression = substr_replace($expression, '/'.$dst_host.'/'.$query_parameter['data']['item'],
						$query_parameter['pos'], $query_parameter['length']
					);
				}
			}
			while ($hist_function = prev($hist_functions));
		}

		return $expression;
	}
}
