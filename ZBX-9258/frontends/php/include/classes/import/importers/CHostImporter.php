<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * @var array		a list of host IDs which were created or updated to create an interface cache for those hosts
	 */
	protected $processedHostIds = array();

	/**
	 * Import hosts.
	 *
	 * @param array $hosts
	 *
	 * @throws Exception
	 */
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

		// create/update hosts
		if ($this->options['hosts']['createMissing'] && $hostsToCreate) {
			$newHostIds = API::Host()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostId) {
				$hostHost = $hostsToCreate[$hnum]['host'];
				$this->processedHostIds[$hostHost] = $hostId;

				$this->referencer->addHostRef($hostHost, $hostId);

				if (!empty($templateLinkage[$hostHost])) {
					API::Template()->massAdd(array(
						'hosts' => array('hostid' => $hostId),
						'templates' => $templateLinkage[$hostHost]
					));
				}
			}
		}

		if ($this->options['hosts']['updateExisting'] && $hostsToUpdate) {
			API::Host()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->processedHostIds[$host['host']] = $host['hostid'];

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
			'hostids' => $this->processedHostIds,
			'output' => API_OUTPUT_EXTEND
		));

		foreach ($hosts as $host) {
			if (isset($this->processedHostIds[$host['host']])) {
				foreach ($host['interfaces'] as $interface) {
					$hostId = $this->processedHostIds[$host['host']];

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
								&& $dbInterface['main'] == $interface['main']
								&& $dbInterface['bulk'] == $interface['bulk']) {
							$refName = $interface['interface_ref'];
							$this->referencer->interfacesCache[$hostId][$refName] = $dbInterface['interfaceid'];
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
	public function getProcessedHostIds() {
		return $this->processedHostIds;
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
	 * @param array $xmlHosts    hosts from XML for which interfaces will be added
	 *
	 * @return array
	 */
	protected function addInterfaceIds(array $xmlHosts) {
		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => zbx_objectValues($xmlHosts, 'hostid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		// build lookup maps for:
		// - interfaces per host
		// - default (primary) interface ids per host per interface type
		$dbHostInterfaces = array();
		$dbHostMainInterfaceIds = array();

		foreach ($dbInterfaces as $dbInterface) {
			$dbHostId = $dbInterface['hostid'];

			$dbHostInterfaces[$dbHostId][] = $dbInterface;
			if ($dbInterface['main'] == INTERFACE_PRIMARY) {
				$dbHostMainInterfaceIds[$dbHostId][$dbInterface['type']] = $dbInterface['interfaceid'];
			}
		}

		foreach ($xmlHosts as &$xmlHost) {
			// if interfaces in XML are empty then do not touch existing interfaces
			if (!$xmlHost['interfaces']) {
				unset($xmlHost['interfaces']);
				continue;
			}

			$xmlHostId = $xmlHost['hostid'];

			$currentDbHostMainInterfaceIds = isset($dbHostMainInterfaceIds[$xmlHostId])
				? $dbHostMainInterfaceIds[$xmlHostId]
				: array();

			$reusedInterfaceIds = array();

			foreach ($xmlHost['interfaces'] as &$xmlHostInterface) {
				$xmlHostInterfaceType = $xmlHostInterface['type'];

				// check if an existing interfaceid from current host can be reused
				// in case there is default (primary) interface in current host with same type
				if ($xmlHostInterface['main'] == INTERFACE_PRIMARY
						&& isset($currentDbHostMainInterfaceIds[$xmlHostInterfaceType])) {
					$dbHostInterfaceId = $currentDbHostMainInterfaceIds[$xmlHostInterfaceType];

					$xmlHostInterface['interfaceid'] = $dbHostInterfaceId;
					$reusedInterfaceIds[$dbHostInterfaceId] = true;
				}
			}
			unset($xmlHostInterface);

			// loop through all interfaces of current host and take interfaceids from ones that
			// match completely, ignoring hosts from XML with set interfaceids and ignoring hosts
			// from DB with reused interfaceids
			foreach ($xmlHost['interfaces'] as &$xmlHostInterface) {
				foreach ($dbHostInterfaces[$xmlHostId] as $dbHostInterface) {
					$dbHostInterfaceId = $dbHostInterface['interfaceid'];

					if (!isset($xmlHostInterface['interfaceid']) && !isset($reusedInterfaceIds[$dbHostInterfaceId])
							&& $dbHostInterface['ip'] == $xmlHostInterface['ip']
							&& $dbHostInterface['dns'] == $xmlHostInterface['dns']
							&& $dbHostInterface['useip'] == $xmlHostInterface['useip']
							&& $dbHostInterface['port'] == $xmlHostInterface['port']
							&& $dbHostInterface['type'] == $xmlHostInterface['type']
							&& $dbHostInterface['bulk'] == $xmlHostInterface['bulk']) {
						$xmlHostInterface['interfaceid'] = $dbHostInterfaceId;
						$reusedInterfaceIds[$dbHostInterfaceId] = true;
						break;
					}
				}
			}
			unset($xmlHostInterface);
		}
		unset($xmlHost);

		return $xmlHosts;
	}
}
