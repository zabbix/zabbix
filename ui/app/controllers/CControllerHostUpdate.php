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
class CControllerHostUpdate extends CController {

	protected function checkInput(): bool {

		$fields = [
			'hostid'			=> 'required|db hosts.hostid',
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
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		$this->host = API::Host()->get([
			'output' => ['hostid', 'host', 'name', 'status', 'description', 'proxy_hostid', 'ipmi_authtype',
				'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_connect', 'tls_accept', 'tls_issuer',
				'tls_subject', 'flags', 'inventory_mode'
			],
			'hostids' => $this->getInput('hostid'),
			'editable' => true
		]);

		if (!$this->host) {
			return false;
		}

		$this->host = $this->host[0];

		return true;
	}

	protected function doAction(): void {
		$host = [
			'hostid' => $this->host['hostid'],
			'host' => $this->getInput('host', $this->host['host']),
			'name' => $this->getInput('visiblename', $this->host['name']),
			'status' => $this->getInput('status', $this->host['status']),
			'proxy_hostid' => $this->getInput('proxy_hostid', $this->host['proxy_hostid']),
			'groups' => $this->processHostGroups(),
			'interfaces' => $this->processHostInterfaces(),
			'tags' => $this->processTags(),
			'templates' => $this->processTemplates(),
			'clear_templates' => zbx_toObject($this->getInput('clear_templates', []), 'templateid'),
			'macros' => $this->processUserMacros(),
			'inventory' => ($this->getInput('inventory_mode', $this->host['inventory_mode']) != HOST_INVENTORY_DISABLED)
				? $this->getInput('host_inventory', [])
				: [],
			'tls_connect' => $this->getInput('tls_connect', $this->host['tls_connect']),
			'tls_accept' => $this->getInput('tls_accept', $this->host['tls_accept'])
		];

		$host_properties = [
			'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_subject',
			'tls_issuer', 'inventory_mode'
		];

		foreach ($host_properties as $prop) {
			if (!array_key_exists($prop, $this->host) || $this->getInput($prop, '') !== $this->host[$prop]) {
				$host[$prop] = $this->getInput($prop, '');
			}
		}

		if ($this->hasInput('tls_psk_identity')) {
			$host['tls_psk_identity'] = $this->getInput('tls_psk_identity');
		}

		if ($this->hasInput('tls_psk')) {
			$host['tls_psk'] = $this->getInput('tls_psk');
		}

		if ($host['tls_connect'] != HOST_ENCRYPTION_PSK && !($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
			unset($host['tls_psk'], $host['tls_psk_identity']);
		}

		if ($host['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
				&& !($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
			unset($host['tls_issuer'], $host['tls_subject']);
		}

		$output = [];
		if (($hostids = API::Host()->update($host)) !== false && $this->processValueMaps()) {
			$output += [
				'hostid' => $hostids['hostids'][0],
				'message' => _('Host updated')
			];
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		// Set response.
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Prepare host interfaces for host.update method.
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
	 * Prepare host level user macros for host.update method.
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
	 * Prepare host tags for host.update method.
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
	 * Prepare host groups for host.update method.
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

	/**
	 * Prepare templates for host.update method.
	 *
	 * @return array
	 */
	private function processTemplates(): array {
		return zbx_toObject(array_merge($this->getInput('add_templates', []), $this->getInput('templates', [])),
			'templateid'
		);
	}

	/**
	 * Save valuemaps.
	 *
	 * @return bool
	 */
	private function processValueMaps(): bool {
		$valuemaps = $this->getInput('valuemaps', []);
		$ins_valuemaps = [];
		$upd_valuemaps = [];

		$del_valuemapids = API::ValueMap()->get([
			'output' => [],
			'hostids' => $this->host['hostid'],
			'preservekeys' => true
		]);

		foreach ($valuemaps as $valuemap) {
			if (array_key_exists('valuemapid', $valuemap)) {
				$upd_valuemaps[] = $valuemap;
				unset($del_valuemapids[$valuemap['valuemapid']]);
			}
			else {
				$ins_valuemaps[] = $valuemap + ['hostid' => $this->host['hostid']];
			}
		}

		if ($upd_valuemaps && !API::ValueMap()->update($upd_valuemaps)) {
			return false;
		}

		if ($ins_valuemaps && !API::ValueMap()->create($ins_valuemaps)) {
			return false;
		}

		if ($del_valuemapids && !API::ValueMap()->delete(array_keys($del_valuemapids))) {
			return false;
		}

		return true;
	}
}
