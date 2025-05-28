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


class CControllerHostWizardUpdate extends CControllerHostWizardUpdateGeneral {

	private array $db_host;

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid' =>				'required|db hosts.hostid',
			'groups' =>				'array',
			'groups_new' =>			'array',
			'templateid' =>			'required|db hosts.hostid',
			'tls_psk_identity' =>	'db hosts.tls_psk_identity|not_empty',
			'tls_psk' =>			'db hosts.tls_psk|not_empty',
			'interfaces' =>			'array|not_empty',
			'ipmi_authtype' =>		'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2,
				IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM, IPMI_AUTHTYPE_RMCP_PLUS
			]),
			'ipmi_privilege' =>		'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER,
				IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
			]),
			'ipmi_username' =>		'db hosts.ipmi_username',
			'ipmi_password' =>		'db hosts.ipmi_password',
			'macros' =>				'array|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update host'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		$hosts = API::Host()->get([
			'output' => ['ipmi_authtype','ipmi_privilege', 'ipmi_username', 'ipmi_password'],
			'selectHostGroups' => ['groupid'],
			'selectInterfaces' => ['interfaceid', 'type', 'main', 'ip', 'dns', 'port', 'useip', 'details'],
			'selectMacros' => ['hostmacroid', 'macro'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => $this->getInput('hostid'),
			'editable' => true
		]);

		if (!$hosts) {
			return false;
		}

		$this->db_host = $hosts[0];

		return parent::checkPermissions();
	}

	protected function doAction(): void {
		try {
			DBstart();

			if (!$this->validateMacrosByConfig()) {
				throw new Exception();
			}

			$host = [
				'hostid' => $this->getInput('hostid'),
				'templates' => $this->processTemplates([array_keys(
					array_column($this->db_host['parentTemplates'], 'templateid', 'templateid')
					+ [$this->getInput('templateid') => true]
				)]),
				'macros' => $this->prepareMacros($this->getInput('macros', []), $this->db_host['macros'])
			];

			$this->getInputs($host, ['ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password']);

			if ($this->hasInput('groups') || $this->hasInput('groups_new')) {
				$host['groups'] = $this->processHostGroups(
					array_unique(array_merge(
						$this->getInput('groups', []),
						array_column($this->db_host['hostgroups'], 'groupid')
					)),
					$this->getInput('groups_new', [])
				);
			}

			if ($this->hasInput('interfaces')) {
				$interfaces = $this->prepareInterfaces($this->getInput('interfaces'), $this->db_host['interfaces']);

				$host['interfaces'] = array_merge(
					$this->db_host['interfaces'], $this->processHostInterfaces($interfaces)
				);
			}

			if ($this->hasInput('tls_psk_identity') && $this->hasInput('tls_psk')) {
				$host += [
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => $this->getInput('tls_psk_identity'),
					'tls_psk' => $this->getInput('tls_psk')
				];
			}

			$result = API::Host()->update($host);

			if ($result === false) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			$result = false;
			DBend(false);
		}

		$output = [];

		if ($result) {
			$success = ['title' => _('Host updated successfully')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update host'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}

	private function prepareInterfaces(array $interfaces, array $db_interfaces): array {
		$address_parser = new CAddressParser(['usermacros' => true, 'lldmacros' => true, 'macros' => true]);
		$db_interface_types = array_flip(array_column($db_interfaces, 'type'));

		foreach ($interfaces as $key => &$interface) {
			$address_parser->parse($interface['address']);
			$interface['useip'] = $address_parser->getAddressType();

			if ($interface['useip'] == INTERFACE_USE_DNS) {
				$interface['dns'] = $interface['address'];
				$interface['ip'] = '';
			}
			elseif ($interface['useip'] == INTERFACE_USE_IP) {
				$interface['ip'] = $interface['address'];
				$interface['dns'] = '';
			}

			unset($interface['address']);

			foreach ($db_interfaces as $db_interface) {
				if ($interface['type'] !== $db_interface['type']) {
					continue;
				}

				$compare_keys = ['port', 'useip'];
				$compare_keys[] = $interface['useip'] == INTERFACE_USE_IP ? 'ip' : 'dns';

				$interface_subset = array_intersect_key($interface, array_flip($compare_keys));
				$db_interface_subset = array_intersect_key($db_interface, array_flip($compare_keys));

				$db_interface_subset['useip'] = (int) $db_interface_subset['useip'];

				// $interface is not same as $db_interface
				if (array_diff_assoc($interface_subset, $db_interface_subset)) {
					continue;
				}

				if ($interface['type'] == INTERFACE_TYPE_SNMP) {
					$db_details_subset = array_intersect_key($db_interface['details'], $interface['details']);

					// $db_interface details are same as $interface details
					if (!array_diff_assoc($interface['details'], $db_details_subset)) {
						unset($interfaces[$key]);
						break;
					}
				}
				else {
					unset($interfaces[$key]);
					break;
				}
			}

			$interface += [
				'isNew' => true,
				'main' => array_key_exists($interface['type'], $db_interface_types)
					? INTERFACE_SECONDARY
					: INTERFACE_PRIMARY
			];
		}
		unset($interface);

		return $interfaces;
	}

	private function prepareMacros(array $macros, array $host_macros): array {
		$result = [];

		foreach ($host_macros as $host_macro) {
			$result[$host_macro['macro']] = ['hostmacroid' => $host_macro['hostmacroid']];
		}

		foreach ($macros as $macro) {
			$host_macro = array_key_exists($macro['macro'], $result) ? $result[$macro['macro']] : null;

			$result[$macro['macro']] = $host_macro != null
				? $macro + [
					'hostmacroid' => $host_macro['hostmacroid'],
					'automatic' => ZBX_USERMACRO_MANUAL
				]
				: $macro;
		}

		return array_values($result);
	}
}
