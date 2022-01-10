<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CHostImporter extends CImporter {

	/**
	 * @var array  A list of host IDs which were created or updated to create an interface cache for those hosts.
	 */
	protected $processedHostIds = [];

	/**
	 * Import hosts.
	 *
	 * @param array $hosts
	 *
	 * @throws Exception
	 */
	public function import(array $hosts): void {
		$hosts_to_create = [];
		$hosts_to_update = [];
		$valuemaps = [];
		$template_linkage = [];
		$templates_to_clear = [];

		foreach ($hosts as $host) {
			/*
			 * Save linked templates for 2 purposes:
			 *  - save linkages to add in case if 'create new' linkages is checked;
			 *  - calculate missing linkages in case if 'delete missing' is checked.
			 */
			if (array_key_exists('templates', $host)) {
				foreach ($host['templates'] as $template) {
					$templateid = $this->referencer->findTemplateidByHost($template['name']);

					if ($templateid === null) {
						throw new Exception(_s('Template "%1$s" for host "%2$s" does not exist.', $template['name'], $host['host']));
					}

					$template_linkage[$host['host']][] = ['templateid' => $templateid];
				}
			}

			unset($host['templates']);

			$host = $this->resolveHostReferences($host);

			if (array_key_exists('hostid', $host)
					&& ($this->options['hosts']['updateExisting'] || $this->options['process_hosts'])) {
				$hosts_to_update[] = $host;
			}
			elseif ($this->options['hosts']['createMissing']) {
				if (array_key_exists('hostid', $host)) {
					throw new Exception(_s('Host "%1$s" already exists.', $host['host']));
				}

				$hosts_to_create[] = $host;
			}

			if (array_key_exists('valuemaps', $host)) {
				$valuemaps[$host['host']] = $host['valuemaps'];
			}
		}

		if ($hosts_to_update) {
			// Get template linkages to unlink and clear.
			if ($this->options['templateLinkage']['deleteMissing']) {
				// Get already linked templates.
				$db_template_links = API::Host()->get([
					'output' => ['hostids'],
					'selectParentTemplates' => ['hostid'],
					'hostids' => array_column($hosts_to_update, 'hostid'),
					'preservekeys' => true
				]);

				foreach ($db_template_links as &$db_template_link) {
					$db_template_link = array_column($db_template_link['parentTemplates'], 'templateid');
				}
				unset($db_template_link);

				foreach ($hosts_to_update as $host) {
					if (array_key_exists($host['host'], $template_linkage)) {
						$templates_to_clear[$host['hostid']] = array_diff(
							$db_template_links[$host['hostid']],
							array_column($template_linkage[$host['host']], 'templateid')
						);
					}
					else {
						$templates_to_clear[$host['hostid']] = $db_template_links[$host['hostid']];
					}
				}
			}

			if ($this->options['hosts']['updateExisting']) {
				$hosts_to_update = $this->addInterfaceIds($hosts_to_update);

				API::Host()->update($hosts_to_update);
			}

			foreach ($hosts_to_update as $host) {
				$this->processedHostIds[$host['host']] = $host['hostid'];

				// Drop existing template linkages if 'delete missing' selected.
				if (array_key_exists($host['hostid'], $templates_to_clear) && $templates_to_clear[$host['hostid']]) {
					API::Host()->massRemove([
						'hostids' => [$host['hostid']],
						'templateids_clear' => $templates_to_clear[$host['hostid']]
					]);
				}

				// Make new template linkages.
				if ($this->options['templateLinkage']['createMissing']
						&& array_key_exists($host['host'], $template_linkage)) {
					API::Host()->massAdd([
						'hosts' => $host,
						'templates' => $template_linkage[$host['host']]
					]);
				}

				$db_valuemaps = API::ValueMap()->get([
					'output' => ['valuemapid', 'name'],
					'hostids' => [$host['hostid']]
				]);

				if ($this->options['valueMaps']['createMissing'] && array_key_exists($host['host'], $valuemaps)) {
					$valuemaps_to_create = [];
					$valuemap_names = array_column($db_valuemaps, 'name');

					foreach ($valuemaps[$host['host']] as $valuemap) {
						if (!in_array($valuemap['name'], $valuemap_names)) {
							$valuemap['hostid'] = $host['hostid'];
							$valuemaps_to_create[] = $valuemap;
						}
					}

					if ($valuemaps_to_create) {
						API::ValueMap()->create($valuemaps_to_create);
					}
				}

				if ($this->options['valueMaps']['updateExisting'] && array_key_exists($host['host'], $valuemaps)) {
					$valuemaps_to_update = [];

					foreach ($db_valuemaps as $db_valuemap) {
						foreach ($valuemaps[$host['host']] as $valuemap) {
							if ($db_valuemap['name'] === $valuemap['name']) {
								$valuemap['valuemapid'] = $db_valuemap['valuemapid'];
								$valuemaps_to_update[] = $valuemap;
							}
						}
					}

					if ($valuemaps_to_update) {
						API::ValueMap()->update($valuemaps_to_update);
					}
				}

				if ($this->options['valueMaps']['deleteMissing'] && $db_valuemaps) {
					$valuemapids_to_delete = [];

					if (array_key_exists($host['host'], $valuemaps)) {
						$valuemap_names = array_column($valuemaps[$host['host']], 'name');

						foreach ($db_valuemaps as $db_valuemap) {
							if (!in_array($db_valuemap['name'], $valuemap_names)) {
								$valuemapids_to_delete[] = $db_valuemap['valuemapid'];
							}
						}
					}
					else {
						$valuemapids_to_delete = array_column($db_valuemaps, 'valuemapid');
					}

					if ($valuemapids_to_delete) {
						API::ValueMap()->delete($valuemapids_to_delete);
					}
				}
			}
		}

		if ($this->options['hosts']['createMissing'] && $hosts_to_create) {
			$created_hosts = API::Host()->create($hosts_to_create);

			foreach ($hosts_to_create as $index => $host) {
				$hostid = $created_hosts['hostids'][$index];

				$this->referencer->setDbHost($hostid, $host);
				$this->processedHostIds[$host['host']] = $hostid;

				if ($this->options['templateLinkage']['createMissing']
						&& array_key_exists($host['host'], $template_linkage)) {
					API::Host()->massAdd([
						'hosts' => ['hostid' => $hostid],
						'templates' => $template_linkage[$host['host']]
					]);
				}

				if ($this->options['valueMaps']['createMissing'] && array_key_exists($host['host'], $valuemaps)) {
					$valuemaps_to_create = [];

					foreach ($valuemaps[$host['host']] as $valuemap) {
						$valuemap['hostid'] = $hostid;
						$valuemaps_to_create[] = $valuemap;
					}

					if ($valuemaps_to_create) {
						API::ValueMap()->create($valuemaps_to_create);
					}
				}
			}
		}

		// create interfaces cache interface_ref->interfaceid
		$db_interfaces = API::HostInterface()->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $this->processedHostIds
		]);

		foreach ($hosts as $host) {
			if (array_key_exists($host['host'], $this->processedHostIds)) {
				foreach ($host['interfaces'] as $interface) {
					$hostid = $this->processedHostIds[$host['host']];

					if (!array_key_exists($hostid, $this->referencer->interfaces_cache)) {
						$this->referencer->interfaces_cache[$hostid] = [];
					}

					foreach ($db_interfaces as $db_interface) {
						if ($db_interface['hostid'] == $hostid
								&& $db_interface['ip'] === $interface['ip']
								&& $db_interface['dns'] === $interface['dns']
								&& $db_interface['useip'] == $interface['useip']
								&& $db_interface['port'] == $interface['port']
								&& $db_interface['type'] == $interface['type']
								&& $db_interface['main'] == $interface['main']) {

							// Check SNMP additional fields.
							if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
								// Get fields that we can compare.
								$array_diff = array_intersect_key($db_interface['details'], $interface['details']);

								foreach (array_keys($array_diff) as $key) {
									// Check field equality.
									if ($db_interface['details'][$key] != $interface['details'][$key]) {
										continue 2;
									}
								}
							}

							$this->referencer->interfaces_cache[$hostid][$interface['interface_ref']]
								= $db_interface['interfaceid'];
						}
					}
				}
			}
		}
	}

	/**
	 * Get a list of created or updated host IDs.
	 *
	 * @return array
	 */
	public function getProcessedHostIds(): array {
		return $this->processedHostIds;
	}

	/**
	 * Change all references in host to database ids.
	 *
	 * @param array $host
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function resolveHostReferences(array $host): array {
		foreach ($host['groups'] as $index => $group) {
			$groupid = $this->referencer->findGroupidByName($group['name']);

			if ($groupid === null) {
				throw new Exception(_s('Group "%1$s" for host "%2$s" does not exist.', $group['name'], $host['host']));
			}

			$host['groups'][$index] = ['groupid' => $groupid];
		}

		if (array_key_exists('proxy', $host)) {
			if (!$host['proxy']) {
				$proxyid = 0;
			}
			else {
				$proxyid = $this->referencer->findProxyidByHost($host['proxy']['name']);

				if ($proxyid === null) {
					throw new Exception(_s('Proxy "%1$s" for host "%2$s" does not exist.', $host['proxy']['name'], $host['host']));
				}
			}

			$host['proxy_hostid'] = $proxyid;
		}

		$hostid = $this->referencer->findHostidByHost($host['host']);

		if ($hostid !== null) {
			$host['hostid'] = $hostid;

			if (array_key_exists('macros', $host)) {
				foreach ($host['macros'] as &$macro) {
					$hostmacroid = $this->referencer->findHostMacroid($hostid, $macro['macro']);

					if ($hostmacroid !== null) {
						$macro['hostmacroid'] = $hostmacroid;
					}
				}
				unset($macro);
			}
		}

		return $host;
	}

	/**
	 * For existing hosts we need to set an interfaceid for existing interfaces or they will be added.
	 *
	 * @param array $hosts  Hosts from XML for which interfaces will be added.
	 *
	 * @return array
	 */
	protected function addInterfaceIds(array $hosts): array {
		$db_interfaces = API::HostInterface()->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => array_column($hosts, 'hostid'),
			'preservekeys' => true
		]);

		// build lookup maps for:
		// - interfaces per host
		// - default (primary) interface ids per host per interface type
		$db_host_interfaces = [];
		$db_host_main_interfaceids = [];

		foreach ($db_interfaces as $db_interface) {
			$hostid = $db_interface['hostid'];

			$db_host_interfaces[$hostid][] = $db_interface;
			if ($db_interface['main'] == INTERFACE_PRIMARY) {
				$db_host_main_interfaceids[$hostid][$db_interface['type']] = $db_interface['interfaceid'];
			}
		}

		foreach ($hosts as &$host) {
			// If interfaces in XML are non-existent or empty, delete the interfaces on host.

			$hostid = $host['hostid'];

			$main_interfaceids = array_key_exists($hostid, $db_host_main_interfaceids)
				? $db_host_main_interfaceids[$hostid]
				: [];

			$reused_interfaceids = [];

			foreach ($host['interfaces'] as &$interface) {
				// check if an existing interfaceid from current host can be reused
				// in case there is default (primary) interface in current host with same type
				if ($interface['main'] == INTERFACE_PRIMARY
						&& array_key_exists($interface['type'], $main_interfaceids)) {
					$db_interfaceid = $main_interfaceids[$interface['type']];

					$interface['interfaceid'] = $db_interfaceid;
					$reused_interfaceids[$db_interfaceid] = true;
				}
			}
			unset($interface);

			// loop through all interfaces of current host and take interfaceids from ones that
			// match completely, ignoring hosts from XML with set interfaceids and ignoring hosts
			// from DB with reused interfaceids
			foreach ($host['interfaces'] as &$interface) {
				if (!array_key_exists($hostid, $db_host_interfaces)) {
					continue;
				}

				foreach ($db_host_interfaces[$hostid] as $db_host_interface) {
					$db_interfaceid = $db_host_interface['interfaceid'];

					if (!array_key_exists('interfaceid', $interface)
							&& !array_key_exists($db_interfaceid, $reused_interfaceids)
							&& $db_host_interface['ip'] == $interface['ip']
							&& $db_host_interface['dns'] == $interface['dns']
							&& $db_host_interface['useip'] == $interface['useip']
							&& $db_host_interface['port'] == $interface['port']
							&& $db_host_interface['type'] == $interface['type']) {
						$interface['interfaceid'] = $db_interfaceid;
						$reused_interfaceids[$db_interfaceid] = true;
						break;
					}
				}
			}
			unset($interface);
		}
		unset($host);

		return $hosts;
	}
}
