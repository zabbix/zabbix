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


class CHostPrototypeHelper {

	/**
	 * @param array $src_options
	 * @param array $dst_options
	 * @param array $dst_ruleids
	 *
	 * @return bool
	 */
	public static function copy(array $src_options, array $dst_options, array $dst_ruleids): bool {
		$src_host_prototypes = self::getSourceHostPrototypes($src_options);

		if (!$src_host_prototypes) {
			return true;
		}

		$dst_hostids = reset($dst_options);
		$dst_host_prototypes = [];

		foreach ($dst_hostids as $dst_hostid) {
			foreach ($src_host_prototypes as $src_host_prototype) {
				$dst_host_prototype =
					['ruleid' => $dst_ruleids[$src_host_prototype['discoveryRule']['itemid']][$dst_hostid]]
					+ array_diff_key($src_host_prototype, array_flip(['hostid', 'discoveryRule']));

				foreach ($dst_host_prototype['macros'] as &$macro) {
					if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
						$macro = ['type' => ZBX_MACRO_TYPE_TEXT, 'value' => ''] + $macro;
					}
				}
				unset($macro);

				$dst_host_prototypes[] = $dst_host_prototype;
			}
		}

		$response = API::HostPrototype()->create($dst_host_prototypes);

		return $response !== false;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	private static function getSourceHostPrototypes(array $src_options): array {
		return API::HostPrototype()->get([
			'output' => ['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTemplates' => ['templateid'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'type', 'value', 'description'],
			'selectDiscoveryRule' => ['itemid'],
			'preservekeys' => true
		] + $src_options);
	}
}
