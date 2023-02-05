<?php
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


class CHostImporter extends CImporter {

	/**
	 * Import hosts.
	 *
	 * @param array $hosts
	 *
	 * @throws Exception
	 */
	public function import(array $hosts): array {
		$hostids = [];
		$upd_hosts = [];
		$ins_hosts = [];

		$is_template_linkage = $this->options['templateLinkage']['createMissing']
			|| $this->options['templateLinkage']['deleteMissing'];

		foreach ($hosts as $host) {
			$host = $this->resolveHostReferences($host);

			if (array_key_exists('hostid', $host)) {
				if ($this->options['hosts']['updateExisting'] || $this->options['process_hosts']) {
					$hostids[$host['host']] = $host['hostid'];
					$this->referencer->setDbHost($host['hostid'], $host);

					if ($this->options['hosts']['updateExisting']) {
						if (!$is_template_linkage) {
							unset($host['templates']);
						}

						$upd_hosts[] = $host;
					}
					elseif ($is_template_linkage) {
						$upd_hosts[] = ['hostid' => $host['hostid'], 'templates' => $host['templates']];
					}
				}
			}
			else {
				if ($this->options['hosts']['createMissing']) {
					if (!$this->options['templateLinkage']['createMissing']) {
						unset($host['templates']);
					}

					$ins_hosts[] = $host;
				}
			}
		}

		if ($upd_hosts) {
			$this->addInterfaceIds($upd_hosts);

			if ($is_template_linkage) {
				$this->addExistingTemplates($upd_hosts);
			}

			API::Host()->update($upd_hosts);
		}

		if ($ins_hosts) {
			$ins_hostids = API::Host()->create($ins_hosts)['hostids'];

			foreach ($ins_hosts as $host) {
				$hostid = array_shift($ins_hostids);

				$hostids[$host['host']] = $hostid;
				$this->referencer->setDbHost($hostid, $host);
			}
		}

		// create interfaces cache interface_ref->interfaceid
		$db_interfaces = API::HostInterface()->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => array_values($hostids)
		]);

		foreach ($hosts as $host) {
			if (array_key_exists($host['host'], $hostids)) {
				foreach ($host['interfaces'] as $interface) {
					$hostid = $hostids[$host['host']];

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

		return array_values($hostids);
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
			$groupid = $this->referencer->findHostGroupidByName($group['name']);

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

		if ($this->options['templateLinkage']['createMissing'] || $this->options['templateLinkage']['deleteMissing']) {
			foreach ($host['templates'] as &$template) {
				$templateid = $this->referencer->findTemplateidByHost($template['name']);

				if ($templateid === null) {
					throw new Exception(
						_s('Template "%1$s" for host "%2$s" does not exist.', $template['name'], $host['host'])
					);
				}

				$template['templateid'] = $templateid;
			}
			unset($template);
		}

		return $host;
	}

	/**
	 * For existing hosts we need to set an interfaceid for existing interfaces or they will be added.
	 *
	 * @param array $hosts  Hosts from XML for which interfaces will be added.
	 */
	private function addInterfaceIds(array &$hosts): void {
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
	}

	/**
	 * Add the existing templates to given hosts.
	 *
	 * @param array $hosts
	 */
	private function addExistingTemplates(array &$hosts): void {
		if ($this->options['templateLinkage']['createMissing'] && $this->options['templateLinkage']['deleteMissing']) {
			return;
		}

		$db_hosts = API::Host()->get([
			'output' => [],
			'selectParentTemplates' => ['templateid'],
			'hostids' => array_column($hosts, 'hostid'),
			'preservekeys' => true
		]);

		foreach ($hosts as &$host) {
			if ($this->options['templateLinkage']['createMissing']) {
				$templateids = array_column($host['templates'], 'templateid');

				foreach ($db_hosts[$host['hostid']]['parentTemplates'] as $db_template) {
					if (!in_array($db_template['templateid'], $templateids)) {
						$host['templates'][] = $db_template;
					}
				}
			}

			if ($this->options['templateLinkage']['deleteMissing']) {
				$db_templateids = array_column($db_hosts[$host['hostid']]['parentTemplates'], 'templateid');

				foreach ($host['templates'] as $i => $template) {
					if (!in_array($template['templateid'], $db_templateids)) {
						unset($host['templates'][$i]);
					}
				}
			}
		}
		unset($host);
	}
}
