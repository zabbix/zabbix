<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	public function import(array $hosts) {
		$hostsToCreate = array();
		$hostsToUpdate = array();
		foreach ($hosts as $host) {
			// preserve host related templates to massAdd them later
			if ($this->options['templateLinkage']['createMissing'] && !empty($host['templates'])) {
				foreach ($host['templates'] as $template) {
					$templateId = $this->referencer->resolveTemplate($template['name']);
					if (!$templateId) {
						throw new Exception(_s('Template "%1$s" for host "%2$s" does not exist.', $template['name'], $host['host']));
					}
					$templateLinkage[$host['host']][] = array('templateid' => $templateId);
				}
			}
			unset($host['templates']);


			$host = $this->resolveHostReferences($host);

			if (isset($host['hostid'])) {
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}

		$hostsToUpdate = $this->addInterfaceIds($hostsToUpdate);

		// a list of hostids which were created or updated to create an interface cache for those hosts
		$processedHostIds = array();
		// create/update hosts
		if ($this->options['hosts']['createMissing'] && $hostsToCreate) {
			$newHostIds = API::Host()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostid) {
				$hostHost = $hostsToCreate[$hnum]['host'];
				$processedHostIds[$hostHost] = $hostid;
				$this->referencer->addHostRef($hostHost, $hostid);
				$this->referencer->addProcessedHost($hostHost);

				if (!empty($templateLinkage[$hostHost])) {
					API::Template()->massAdd(array(
						'hosts' => array('hostid' => $hostid),
						'templates' => $templateLinkage[$hostHost]
					));
				}
			}
		}
		if ($this->options['hosts']['updateExisting'] && $hostsToUpdate) {
			API::Host()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->referencer->addProcessedHost($host['host']);
				$processedHostIds[$host['host']] = $host['hostid'];

				if (!empty($templateLinkage[$host['host']])) {
					API::Template()->massAdd(array(
						'hosts' => $host,
						'templates' => $templateLinkage[$host['host']]
					));
				}
			}
		}

		// create interfaces cache interface_ref->interfaceid
		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $processedHostIds,
			'output' => API_OUTPUT_EXTEND
		));
		foreach ($hosts as $host) {
			foreach ($host['interfaces'] as $interface) {
				if (isset($processedHostIds[$host['host']])) {
					$hostId = $processedHostIds[$host['host']];
					if (!isset($this->referencer->interfacesCache[$hostId])) {
						$this->referencer->interfacesCache[$hostId] = array();
					}

					foreach ($dbInterfaces as $dbInterface) {
						if ($hostId == $dbInterface['hostid']
								&& $dbInterface['ip'] == $interface['ip']
								&& $dbInterface['dns'] == $interface['dns']
								&& $dbInterface['useip'] == $interface['useip']
								&& $dbInterface['port'] == $interface['port']
								&& $dbInterface['type'] == $interface['type']
								&& $dbInterface['main'] == $interface['main']) {

							$refName = $interface['interface_ref'];
							$this->referencer->interfacesCache[$hostId][$refName] = $dbInterface['interfaceid'];
						}
					}
				}
			}
		}
	}

	/**
	 * Change all references in host to database ids.
	 *
	 * @throws Exception
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function resolveHostReferences(array $host) {
		foreach ($host['groups'] as $gnum => $group) {
			$groupId = $this->referencer->resolveGroup($group['name']);
			if (!$groupId) {
				throw new Exception(_s('Group "%1$s" for host "%2$s" does not exist.', $group['name'], $host['host']));
			}
			$host['groups'][$gnum] = array('groupid' => $groupId);
		}

		if (isset($host['proxy'])) {
			if (empty($host['proxy'])) {
				$proxyId = 0;
			}
			else {
				$proxyId = $this->referencer->resolveProxy($host['proxy']['name']);
				if (!$proxyId) {
					throw new Exception(_s('Proxy "%1$s" for host "%2$s" does not exist.', $host['proxy']['name'], $host['host']));
				}
			}

			$host['proxy_hostid'] = $proxyId;
		}

		if ($hostId = $this->referencer->resolveHost($host['host'])) {
			$host['hostid'] = $hostId;

			if (!empty($host['macros'])) {
				foreach ($host['macros'] as &$macro) {
					if ($hostMacroId = $this->referencer->resolveMacro($hostId, $macro['macro'])) {
						$macro['hostmacroid'] = $hostMacroId;
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
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function addInterfaceIds(array $hosts) {

		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($dbInterfaces as $dbInterface) {
			foreach ($hosts as $hnum => $host) {
				if (!empty($host['interfaces']) && idcmp($host['hostid'], $dbInterface['hostid'])) {
					foreach ($host['interfaces'] as $inum => $interface) {
						if ($dbInterface['ip'] == $interface['ip']
								&& $dbInterface['dns'] == $interface['dns']
								&& $dbInterface['useip'] == $interface['useip']
								&& $dbInterface['port'] == $interface['port']
								&& $dbInterface['type'] == $interface['type']
								&& $dbInterface['main'] == $interface['main']) {
							$hosts[$hnum]['interfaces'][$inum]['interfaceid'] = $dbInterface['interfaceid'];
							break;
						}
					}
				}
				if (empty($hosts[$hnum]['interfaces'])) {
					unset($hosts[$hnum]['interfaces']);
				}
			}
		}

		return $hosts;
	}

}
