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


class CLldRulePrototypeHelper extends CItemGeneralHelper {

	/**
	 * @param array $src_options
	 * @param array $dst_hosts
	 * @param array $dst_itemids
	 *
	 * @return bool
	 */
	public static function copy(array $src_options, array $dst_hosts, array $dst_itemids): bool {
		$src_items = CLldRuleHelper::getSourceLldRules(true, $src_options);

		if (!$src_items) {
			return true;
		}

		try {
			$dst_interfaceids = self::getDestinationHostInterfaces($src_items, $dst_hosts);
			$dst_master_itemids = self::getDestinationMasterItems($src_items, $dst_hosts,
				ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE
			);
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

				$parent_lld = $src_item['discoveryRule'] ?: $src_item['discoveryRulePrototype'];

				$dst_items[] = [
					'hostid' => $dst_hostid,
					'ruleid' => $dst_itemids[$parent_lld['itemid']][$dst_hostid]
				] + getSanitizedItemFields([
					'templateid' => 0,
					'flags' => ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE,
					'hosts' => [$dst_host]
				] + $dst_item);
			}
		}

		$response = API::DiscoveryRulePrototype()->create($dst_items);

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
			&& CHostPrototypeHelper::copy($src_options, $dst_options, $dst_itemids)
			&& self::copy($src_options, $dst_hosts, $dst_itemids);
	}
}
