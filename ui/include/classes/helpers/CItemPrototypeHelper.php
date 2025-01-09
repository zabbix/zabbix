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


class CItemPrototypeHelper extends CItemGeneralHelper {

	/**
	 * Get item prototype fields default values.
	 */
	public static function getDefaults(): array {
		$general_fields = parent::getDefaults();

		return [
			'discover'	=> ZBX_PROTOTYPE_DISCOVER,
			'flags'		=> ZBX_FLAG_DISCOVERY_PROTOTYPE
		] + $general_fields;
	}

	/**
	 * Convert API data to be ready to use for edit or create form.
	 *
	 * @param array $item  Array of API fields data.
	 */
	public static function convertApiInputForForm(array $item): array {
		$item = parent::convertApiInputForForm($item);
		$item['parent_items'] = makeItemTemplatesHtml(
			$item['itemid'],
			getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_PROTOTYPE),
			ZBX_FLAG_DISCOVERY_PROTOTYPE,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);
		$item['parent_discoveryid'] = $item['discoveryRule']['itemid'];
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => true
		]);

		if ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item = static::addDelayWithFlexibleIntervals($update_interval_parser, $item);
		}
		else {
			$item['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			$item['delay_flex'] = [];
		}

		if ($item['master_itemid']) {
			$master_item = API::ItemPrototype()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $item['master_itemid']
			]);

			if (!$master_item) {
				$master_item = API::Item()->get([
					'output' => ['itemid', 'name'],
					'itemids' => $item['master_itemid'],
					'webitems' => true
				]);
			}

			$item['master_item'] = $master_item ? reset($master_item) : [];
		}

		return $item;
	}

	/**
	 * @param array  $dst_ruleids
	 * @param string $dst_ruleids[<src_ruleid>][<dst_hostid]  ID of target LLD rule.
	 * @param array  $dst_hosts
	 *
	 * @return bool
	 */
	public static function copy(array $dst_ruleids, array $dst_hosts): bool {
		$src_items = CItemPrototypeHelper::getSourceItemPrototypes(['discoveryids' => array_keys($dst_ruleids)]);

		if (!$src_items) {
			return true;
		}

		$dst_valuemapids = self::getDestinationValueMaps($src_items, $dst_hosts);

		try {
			$dst_interfaceids = self::getDestinationHostInterfaces($src_items, $dst_hosts);
		}
		catch (Exception $e) {
			return false;
		}

		$src_itemids = array_fill_keys(array_keys($src_items), true);
		$src_dep_items = [];

		foreach ($src_items as $src_item) {
			if (array_key_exists($src_item['master_itemid'], $src_itemids)) {
				$src_dep_items[$src_item['master_itemid']][] = $src_item;

				unset($src_items[$src_item['itemid']]);
			}
		}

		$dst_master_itemids = self::getDestinationMasterItems($src_items, $dst_hosts);

		do {
			$dst_items = [];

			foreach ($dst_hosts as $dst_hostid => $dst_host) {
				foreach ($src_items as $src_item) {
					$dst_item = array_diff_key($src_item, array_flip(['itemid', 'hosts', 'discoveryRule']));

					if (array_key_exists($src_item['itemid'], $dst_valuemapids)) {
						$dst_item['valuemapid'] = $dst_valuemapids[$src_item['itemid']][$dst_hostid];
					}

					if (array_key_exists($src_item['itemid'], $dst_interfaceids)) {
						$dst_item['interfaceid'] = $dst_interfaceids[$src_item['itemid']][$dst_hostid];
					}

					if (array_key_exists($src_item['itemid'], $dst_master_itemids)) {
						$dst_item['master_itemid'] = $dst_master_itemids[$src_item['itemid']][$dst_hostid];
					}

					$dst_items[] = [
						'hostid' => $dst_hostid,
						'ruleid' => $dst_ruleids[$src_item['discoveryRule']['itemid']][$dst_hostid]
					] + getSanitizedItemFields([
						'templateid' => 0,
						'flags' => ZBX_FLAG_DISCOVERY_PROTOTYPE,
						'hosts' => [$dst_host]
					] + $dst_item);
				}
			}

			$response = API::ItemPrototype()->create($dst_items);

			if ($response === false) {
				return false;
			}

			$_src_items = [];

			if ($src_dep_items) {
				foreach ($dst_hosts as $dst_hostid => $foo) {
					foreach ($src_items as $src_item) {
						$dst_itemid = array_shift($response['itemids']);

						if (array_key_exists($src_item['itemid'], $src_dep_items)) {
							foreach ($src_dep_items[$src_item['itemid']] as $src_dep_item) {
								$dst_master_itemids[$src_dep_item['itemid']][$dst_hostid] = $dst_itemid;
							}

							$_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
							unset($src_dep_items[$src_item['itemid']]);
						}
					}
				}
			}

			$src_items = $_src_items;
		} while ($src_items);

		return true;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceItemPrototypes(array $src_options): array {
		return API::ItemPrototype()->get([
			'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'logtimefmt', 'description', 'status', 'discover',

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
			'selectTags' => ['tag', 'value'],
			'selectHosts' => ['status'],
			'selectDiscoveryRule' => ['itemid'],
			'preservekeys' => true
		] + $src_options);
	}
}
