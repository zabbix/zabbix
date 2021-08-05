<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


/**
 * Controller for host creation.
 */
class CControllerHostCreate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'host'				=> 'required|db hosts.host|not_empty',
			'visiblename'		=> 'db hosts.name',
			'description'		=> 'db hosts.description',
			'status'			=> 'required|db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED,
										HOST_STATUS_NOT_MONITORED
									]),
			'proxy_hostid'		=> 'db hosts.proxy_hostid',
			'interfaces'		=> 'array',
			'mainInterfaces'	=> 'array',
			'groups'			=> 'required|array',
			'tags'				=> 'array',
			'add_templates'		=> 'array_db hosts.hostid',
			'ipmi_authtype'		=> 'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2,
										IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM,
										IPMI_AUTHTYPE_RMCP_PLUS
									]),
			'ipmi_privilege'	=> 'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER,
										IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
									]),
			'ipmi_username'		=> 'db hosts.ipmi_username',
			'ipmi_password'		=> 'db hosts.ipmi_password',
			'tls_connect'		=> 'db hosts.tls_connect|in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK,
										HOST_ENCRYPTION_CERTIFICATE
									]),
			'tls_accept'		=> 'db hosts.tls_accept|ge 0|le '.
										(0 | HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_subject'		=> 'db hosts.tls_subject',
			'tls_issuer'		=> 'db hosts.tls_issuer',
			'tls_psk_identity'	=> 'db hosts.tls_psk_identity',
			'tls_psk'			=> 'db hosts.tls_psk',
			'inventory_mode'	=> 'db host_inventory.inventory_mode|in '.implode(',', [HOST_INVENTORY_DISABLED,
										HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
									]),
			'host_inventory'	=> 'array',
			'macros'			=> 'array',
			'valuemaps'			=> 'array',
			'full_clone'		=> 'in 1',
			'clone_hostid'		=> 'db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))
				->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$ret = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);

		if ($ret && $this->hasInput('clone_hostid')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('clone_hostid')
			]);

			if (!$hosts) {
				$ret = false;
			}
		}

		return $ret;
	}

	protected function doAction(): void {
		$hostids = false;

		try {
			$host = array_filter([
				'status' => $this->getInput('status', HOST_STATUS_NOT_MONITORED),
				'proxy_hostid' => $this->getInput('proxy_hostid', 0),
				'groups' => $this->processHostGroups(),
				'interfaces' => $this->processHostInterfaces(),
				'tags' => $this->processTags(),
				'templates' => zbx_toObject($this->getInput('add_templates', []), 'templateid'),
				'macros' => $this->processUserMacros(),
				'inventory' => ($this->getInput('inventory_mode', HOST_INVENTORY_DISABLED) != HOST_INVENTORY_DISABLED)
					? $this->getInput('host_inventory', [])
					: [],
				'tls_connect' => $this->getInput('tls_connect', HOST_ENCRYPTION_NONE),
				'tls_accept' => $this->getInput('tls_accept', HOST_ENCRYPTION_NONE)
			]);

			$this->getInputs($host, [
				'host', 'visiblename', 'description', 'status', 'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege',
				'ipmi_username', 'ipmi_password', 'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk',
				'inventory_mode'
			]);

			if ($host['tls_connect'] != HOST_ENCRYPTION_PSK && !($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				unset($host['tls_psk'], $host['tls_psk_identity']);
			}

			if ($host['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
					&& !($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				unset($host['tls_issuer'], $host['tls_subject']);
			}

			$host = CArrayHelper::renameKeys($host, [
				'visiblename' => 'name'
			]);

			$full_clone = $this->hasInput('full_clone');
			$src_hostid = $this->getInput('clone_hostid', false);

			if ($src_hostid) {
				$host = $this->extendHostClone($host, (int) $src_hostid);
			}

			$output = [];
			$hostids = API::Host()->create($host);
		} catch (Exception $exception) {
			// Code is not missing here.
		}
		if ($hostids !== false && $this->createValueMaps((int) $hostids['hostids'][0])
				&& (!$full_clone || $this->copyFromCloneSourceHost((int) $src_hostid, (int) $hostids['hostids'][0]))) {
			$output += [
				'hostid' => $hostids['hostids'][0],
				'message' => _('Host added')
			];
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		$response = (new CControllerResponseData(['main_block' => json_encode($output)]))
			->disableView();
		$this->setResponse($response);
	}

	/**
	 * Copy write-only fields from source host to the new host. Used to clone host.
	 *
	 * @param array $host
	 * @param int   $src_hostid
	 *
	 * @return array
	 */
	private function extendHostClone(array $host, int $src_hostid): array {
		if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || ($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
			// Add values to PSK fields from cloned host.
			$clone_hosts = API::Host()->get([
				'output' => ['tls_psk_identity', 'tls_psk'],
				'hostids' => $src_hostid,
				'editable' => true
			]);

			if ($clone_hosts !== false) {
				$host['tls_psk_identity'] = $this->getInput('tls_psk_identity', $clone_hosts[0]['tls_psk_identity']);
				$host['tls_psk'] = $this->getInput('tls_psk', $clone_hosts[0]['tls_psk']);
			}
		}

		return $host;
	}

	/**
	 * Prepare host interfaces for host.create method.
	 *
	 * @return array
	 */
	private function processHostInterfaces(): array {
		$interfaces = $this->getInput('interfaces', []);

		foreach ($interfaces as $key => $interface) {
			if ($interface['type'] == INTERFACE_TYPE_SNMP) {
				if (!array_key_exists('details', $interface)) {
					$interface['details'] = [];
				}

				$interfaces[$key]['details']['bulk'] = array_key_exists('bulk', $interface['details'])
					? SNMP_BULK_ENABLED
					: SNMP_BULK_DISABLED;
			}

			if ($interface['isNew']) {
				unset($interfaces[$key]['interfaceid']);
			}

			unset($interfaces[$key]['isNew']);
			$interfaces[$key]['main'] = 0;
		}

		$main_interfaces = $this->getInput('mainInterfaces', []);
		foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $type) {
			if (array_key_exists($type, $main_interfaces) && array_key_exists($main_interfaces[$type], $interfaces)) {
				$interfaces[$main_interfaces[$type]]['main'] = INTERFACE_PRIMARY;
			}
		}

		return $interfaces;
	}

	/**
	 * Prepare host level user macros for host.create method.
	 *
	 * @return array
	 */
	private function processUserMacros(): array {
		return array_filter(cleanInheritedMacros($this->getInput('macros', [])),
			function (array $macro): bool {
				return (bool) array_filter(
					array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
				);
			}
		);
	}

	/**
	 * Prepare host tags for host.create method.
	 *
	 * @return array
	 */
	private function processTags(): array {
		return array_filter($this->getInput('tags', []),
			function (array $tag): bool {
				return ($tag['tag'] !== '' || $tag['value'] !== '');
			}
		);
	}

	/**
	 * Prepare host groups for host.create method.
	 *
	 * @return array
	 */
	private function processHostGroups(): array {
		$new_groups = [];
		$groups = $this->getInput('groups', []);

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::HostGroup()->create($new_groups);

			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		return zbx_toObject($groups, 'groupid');
	}

	/**
	 * Create valuemaps.
	 *
	 * @param int $hostid
	 *
	 * @return bool
	 */
	private function createValueMaps(int $hostid): bool {
		$valuemaps = array_map(function ($valuemap) use ($hostid) {
			return $valuemap + ['hostid' => $hostid];
		}, $this->getInput('valuemaps', []));

		if ($valuemaps && !API::ValueMap()->create($valuemaps)) {
			return false;
		}

		return true;
	}

	/**
	 * Copy http tests, items, triggers, discovery rules and graphs from source host to target host.
	 *
	 * @param int $src_hostid  Source hostid.
	 * @param int $hostid      Target hostid.
	 *
	 * @return bool
	 */
	private function copyFromCloneSourceHost(int $src_hostid, int $hostid): bool {
		// First copy web scenarios with web items, so that later regular items can use web item as their master item.
		if (!copyHttpTests($src_hostid, $hostid)) {
			return false;
		}

		if (!copyItems($src_hostid, $hostid)) {
			return false;
		}

		// Copy triggers.
		$db_triggers = API::Trigger()->get([
			'output' => ['triggerid'],
			'hostids' => $src_hostid,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		if ($db_triggers && !copyTriggersToHosts(array_column($db_triggers, 'triggerid'), $hostid, $src_hostid)) {
			return false;
		}

		// Copy discovery rules.
		$db_discovery_rules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $src_hostid,
			'inherited' => false
		]);

		if ($db_discovery_rules) {
			$copy_discovery_rules = API::DiscoveryRule()->copy([
				'discoveryids' => array_column($db_discovery_rules, 'itemid'),
				'hostids' => [$hostid]
			]);

			if (!$copy_discovery_rules) {
				return false;
			}
		}

		// Copy graphs.
		$db_graphs = API::Graph()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid'],
			'selectItems' => ['type'],
			'hostids' => $src_hostid,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'inherited' => false
		]);

		foreach ($db_graphs as $db_graph) {
			if (count($db_graph['hosts']) > 1) {
				continue;
			}

			if (httpItemExists($db_graph['items'])) {
				continue;
			}

			if (!copyGraphToHost($db_graph['graphid'], $hostid)) {
				return false;
			}
		}

		return true;
	}
}
