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
			'status'			=> 'required|db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]),
			'proxy_hostid'		=> 'db hosts.proxy_hostid',
			'interfaces'		=> 'array',
			'mainInterfaces'	=> 'array',
			'groups'			=> 'required|array',
			'tags'				=> 'array',
			'templates'			=> 'array_db hosts.hostid',
			'add_templates'		=> 'array_db hosts.hostid',
			'rem_templates'		=> 'array_db hosts.hostid',
			'clear_templates'	=> 'array_db hosts.hostid',
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
			'valuemaps'			=> 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {

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

		$output = [];
		if (($hostids = API::Host()->create($host)) !== false) {
			$output += [
				'hostid' => $hostids['hostids'][0],
				'message' => _('Host added')
			];
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		// Set response.
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Prepare host interfaces for host.create method.
	 *
	 * @return array
	 */
	private function processHostInterfaces(): array {
		// Process host interfaces.
		$interfaces = $this->getInput('interfaces', []);

		foreach ($interfaces as $key => $interface) {
			// Process SNMP interface fields.
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
		// Add new group.
		$groups = $this->getInput('groups', []);
		$new_groups = [];

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

}
