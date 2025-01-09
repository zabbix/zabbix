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


class CLldRuleHelper extends CItemGeneralHelper {

	/**
	 * @param string $src_templateid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	public static function cloneTemplateItems(string $src_templateid, array $dst_host): bool {
		$src_items = self::getSourceLldRules([
			'templateids' => $src_templateid,
			'inherited' => false
		]);

		$dst_hosts = [$dst_host['templateid'] => $dst_host + ['status' => HOST_STATUS_TEMPLATE]];

		return !$src_items || self::copy($src_items, $dst_hosts);
	}

	/**
	 * @param string $src_hostid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	public static function cloneHostItems(string $src_hostid, array $dst_host): bool {
		$src_items = self::getSourceLldRules([
			'hostids' => $src_hostid,
			'inherited' => false
		]);

		$dst_hosts = [$dst_host['hostid'] => $dst_host];

		return !$src_items || self::copy($src_items, $dst_hosts);
	}

	/**
	 * @param array $src_items
	 * @param array $dst_hosts
	 *
	 * @return bool
	 */
	private static function copy(array $src_items, array $dst_hosts): bool {
		try {
			$dst_interfaceids = self::getDestinationHostInterfaces($src_items, $dst_hosts);
			$dst_master_itemids = self::getDestinationMasterItems($src_items, $dst_hosts);
		}
		catch (Exception $e) {
			return false;
		}

		$dst_items = [];

		foreach ($dst_hosts as $dst_hostid => $dst_host) {
			foreach ($src_items as $src_item) {
				$dst_item = array_diff_key($src_item, array_flip(['itemid', 'hosts']));

				if (array_key_exists($src_item['itemid'], $dst_interfaceids)) {
					$dst_item['interfaceid'] = $dst_interfaceids[$src_item['itemid']][$dst_hostid];
				}

				if (array_key_exists($src_item['itemid'], $dst_master_itemids)) {
					$dst_item['master_itemid'] = $dst_master_itemids[$src_item['itemid']][$dst_hostid];
				}

				$dst_items[] = ['hostid' => $dst_hostid] + getSanitizedItemFields([
					'templateid' => 0,
					'flags' => ZBX_FLAG_DISCOVERY_RULE,
					'hosts' => [$dst_host]
				] + $dst_item);
			}
		}

		$response = API::DiscoveryRule()->create($dst_items);

		if ($response === false) {
			return false;
		}

		$dst_itemids = [];

		foreach ($dst_hosts as $dst_hostid => $foo) {
			foreach ($src_items as $src_item) {
				$dst_itemids[$src_item['itemid']][$dst_hostid] = array_shift($response['itemids']);
			}
		}

		$src_options = ['discoveryids' => array_keys($src_items)];
		$dst_options = reset($dst_hosts)['status'] == HOST_STATUS_TEMPLATE
			? ['templateids' => array_keys($dst_hosts)]
			: ['hostids' => array_keys($dst_hosts)];

		return CItemPrototypeHelper::copy($dst_itemids, $dst_hosts)
			&& CTriggerPrototypeHelper::copy($src_options, $dst_options)
			&& CGraphPrototypeHelper::copy($src_options, $dst_options)
			&& CHostPrototypeHelper::copy($src_options, $dst_options, $dst_itemids);
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceLldRules(array $src_options): array {
		$src_items = API::DiscoveryRule()->get([
			'output' => ['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
				'enabled_lifetime', 'description', 'status',

				// Type fields.
				// The fields used for multiple item types.
				'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

				// Dependent item type specific fields.
				'master_itemid',

				// HTTP Agent item type specific fields.
				'url', 'query_fields', 'request_method', 'post_type', 'posts',
				'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
				'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

				// IPMI item type specific fields.
				'ipmi_sensor',

				// JMX item type specific fields.
				'jmx_endpoint',

				// Script item type specific fields.
				'parameters',

				// SNMP item type specific fields.
				'snmp_oid',

				// SSH item type specific fields.
				'publickey', 'privatekey'
			],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'selectHosts' => ['status'],
			'preservekeys' => true
		] + $src_options);

		foreach ($src_items as &$src_item) {
			foreach ($src_item['filter']['conditions'] as &$condition) {
				if ($src_item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					unset($condition['formulaid']);
				}
			}
			unset($condition);

			foreach ($src_item['overrides'] as &$override) {
				unset($override['filter']['eval_formula']);

				foreach ($override['filter']['conditions'] as &$condition) {
					if ($override['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
						unset($condition['formulaid']);
					}
				}
				unset($condition);
			}
			unset($override);
		}
		unset($src_item);

		return $src_items;
	}
}
