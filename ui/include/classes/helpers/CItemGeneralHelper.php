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


class CItemGeneralHelper {

	/**
	 * @param array  $src_items
	 * @param array  $dst_items
	 *
	 * @return array
	 */
	protected static function getDestinationValueMaps(array $src_items, array $dst_hostids): array {
		$item_indexes = [];
		$dst_valuemapids = [];

		foreach ($src_items as $src_item) {
			if ($src_item['valuemapid'] != 0) {
				$item_indexes[$src_item['valuemapid']][] = $src_item['itemid'];

				foreach ($dst_hostids as $dst_hostid) {
					$dst_valuemapids[$src_item['itemid']][$dst_hostid] = 0;
				}
			}
		}

		if (!$item_indexes) {
			return [];
		}

		$src_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'valuemapids' => array_keys($item_indexes)
		]);

		$dst_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'hostid', 'name'],
			'hostids' => $dst_hostids,
			'filter' => ['name' => array_unique(array_column($src_valuemaps, 'name'))]
		]);

		$_dst_valuemapids = [];

		foreach ($dst_valuemaps as $dst_valuemap) {
			$_dst_valuemapids[$dst_valuemap['name']][$dst_valuemap['hostid']] = $dst_valuemap['valuemapid'];
		}

		foreach ($src_valuemaps as $src_valuemap) {
			if (array_key_exists($src_valuemap['name'], $_dst_valuemapids)) {
				foreach ($_dst_valuemapids[$src_valuemap['name']] as $dst_hostid => $dst_valuemapid) {
					foreach ($item_indexes[$src_valuemap['valuemapid']] as $src_itemid) {
						$dst_valuemapids[$src_itemid][$dst_hostid] = $dst_valuemapid;
					}
				}
			}
		}

		return $dst_valuemapids;
	}

	/**
	 * @param array  $src_items
	 * @param array  $dst_options
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationHostInterfaces(array $src_items, array $dst_options): array {
		$dst_hostids = reset($dst_options);

		if (!array_key_exists('hostids', $dst_options)) {
			$dst_interfaceids = [];

			if (in_array(reset($src_items)['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				foreach ($src_items as $src_item) {
					if ($src_item['interfaceid'] != 0) {
						foreach ($dst_hostids as $dst_hostid) {
							$dst_interfaceids[$src_item['itemid']][$dst_hostid] = 0;
						}
					}
				}
			}

			return $dst_interfaceids;
		}

		$item_indexes = [];
		$dst_interfaceids = [];

		foreach ($src_items as $src_item) {
			if (itemTypeInterface($src_item['type']) !== false) {
				foreach ($dst_hostids as $dst_hostid) {
					$dst_interfaceids[$src_item['itemid']][$dst_hostid] = 0;
				}
			}

			if ($src_item['interfaceid'] != 0) {
				$item_indexes[$src_item['interfaceid']][] = $src_item['itemid'];
			}
		}

		if (!$dst_interfaceids) {
			return [];
		}

		$src_interfaces = [];

		if ($item_indexes) {
			$src_interfaces = API::HostInterface()->get([
				'output' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
				'interfaceids' => array_keys($item_indexes),
				'preservekeys' => true
			]);

			foreach ($src_interfaces as &$src_interface) {
				unset($src_interface['interfaceid']);
			}
			unset($src_interface);
		}

		$dst_interfaces = API::HostInterface()->get([
			'output' => ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => $dst_hostids
		]);

		$main_interfaceids = [];

		foreach ($dst_interfaces as $dst_interface) {
			$dst_interfaceid = $dst_interface['interfaceid'];
			$dst_hostid = $dst_interface['hostid'];
			unset($dst_interface['interfaceid'], $dst_interface['hostid']);

			foreach ($src_interfaces as $src_interfaceid => $src_interface) {
				if ($src_interface == $dst_interface) {
					foreach ($item_indexes[$src_interfaceid] as $src_itemid) {
						$dst_interfaceids[$src_itemid][$dst_hostid] = $dst_interfaceid;
					}

					break;
				}
			}

			if ($dst_interface['main'] == INTERFACE_PRIMARY) {
				$main_interfaceids[$dst_hostid][$dst_interface['type']] = $dst_interfaceid;
			}
		}

		$interfaces_by_priority = array_flip(CItemGeneral::INTERFACE_TYPES_BY_PRIORITY);

		foreach ($dst_interfaceids as $src_itemid => &$dst_host_interfaceids) {
			foreach ($dst_host_interfaceids as $dst_hostid => &$dst_interfaceid) {
				if ($dst_interfaceid != 0) {
					continue;
				}

				$dst_interface_type = itemTypeInterface($src_items[$src_itemid]['type']);

				if ($dst_interface_type == INTERFACE_TYPE_OPT) {
					$src_item = $src_items[$src_itemid];

					if (in_array($src_item['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
							&& $src_item['interfaceid'] == 0) {
						continue;
					}

					$dst_interface_type = array_key_exists($dst_hostid, $main_interfaceids)
						? key(array_intersect_key($interfaces_by_priority, $main_interfaceids[$dst_hostid]))
						: null;

					if ($dst_interface_type !== null) {
						$dst_interfaceid = $main_interfaceids[$dst_hostid][$dst_interface_type];
					}
				}
				else {
					if (array_key_exists($dst_hostid, $main_interfaceids)
							&& array_key_exists($dst_interface_type, $main_interfaceids[$dst_hostid])) {
						$dst_interfaceid = $main_interfaceids[$dst_hostid][$dst_interface_type];
					}
					else {
						$hosts = API::Host()->get([
							'output' => ['host'],
							'hostids' => $dst_hostid
						]);

						error(_s('Cannot find host interface on "%1$s" for item with key "%2$s".',
							$hosts[0]['host'], $src_items[$src_itemid]['key_']
						));

						throw new Exception();
					}
				}
			}
			unset($dst_interfaceid);
		}
		unset($dst_host_interfaceids);

		return $dst_interfaceids;
	}

	/**
	 * @param array  $src_items
	 * @param array  $dst_options
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationMasterItems(array $src_items, array $dst_options): array {
		$dst_hostids = reset($dst_options);

		$item_indexes = [];
		$dst_master_itemids = [];

		foreach ($src_items as $src_item) {
			if ($src_item['master_itemid'] != 0) {
				$item_indexes[$src_item['master_itemid']][] = $src_item['itemid'];

				foreach ($dst_hostids as $dst_hostid) {
					$dst_master_itemids[$src_item['itemid']][$dst_hostid] = 0;
				}
			}
		}

		if (!$item_indexes) {
			return [];
		}

		$src_master_items = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'itemids' => array_keys($item_indexes),
			'webitems' => true,
			'preservekeys' => true
		]);

		$dst_master_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'filter' => ['key_' => array_unique(array_column($src_master_items, 'key_'))],
			'webitems' => true
		] + $dst_options);

		$_dst_master_itemids = [];

		foreach ($dst_master_items as $dst_master_item) {
			$_dst_master_itemids[$dst_master_item['key_']][$dst_master_item['hostid']] = $dst_master_item['itemid'];
		}

		foreach ($src_master_items as $src_master_item) {
			if (array_key_exists($src_master_item['key_'], $_dst_master_itemids)) {
				foreach ($_dst_master_itemids[$src_master_item['key_']] as $dst_hostid => $dst_master_itemid) {
					foreach ($item_indexes[$src_master_item['itemid']] as $src_itemid) {
						$dst_master_itemids[$src_itemid][$dst_hostid] = $dst_master_itemid;
					}
				}
			}
		}

		foreach ($dst_master_itemids as $src_itemid => $dst_host_master_itemids) {
			foreach ($dst_host_master_itemids as $dst_hostid => $dst_master_itemid) {
				if ($dst_master_itemid == 0) {
					error(_s('Cannot copy item with key "%1$s" without its master item with key "%2$s".',
						$src_items[$src_itemid]['key_'],
						$src_master_items[$src_items[$src_itemid]['master_itemid']]['key_']
					));

					throw new Exception();
				}
			}
		}

		return $dst_master_itemids;
	}
}
