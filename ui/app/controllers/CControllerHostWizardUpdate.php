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
			'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description', 'automatic'],
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
		if (!$this->validateMacrosByConfig()) {
			$result = false;
		}
		else {
			// Validate and update interfaces.
			$interfaces = $this->prepareInterfaces($this->getInput('interfaces', []), $this->db_host);

			$host = [
				'hostid' => $this->getInput('hostid'),
				'groups' => $this->processHostGroups(array_unique(
					array_merge($this->getInput('groups', []), array_column($this->db_host['hostgroups'], 'groupid'))
				)),
				'templates' => $this->processTemplates([array_keys(
					array_column($this->db_host['parentTemplates'], 'templateid', 'templateid')
					+ [$this->getInput('templateid') => true]
				)]),
				'interfaces' => array_merge($this->db_host['interfaces'], $this->processHostInterfaces($interfaces)),
				'macros' => $this->prepareMacros($this->getInput('macros', []), $this->db_host['macros'])
			];

			$this->getInputs($host, ['ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password']);

			if ($this->hasInput('tls_psk_identity') && $this->hasInput('tls_psk')) {
				$host += [
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => $this->getInput('tls_psk_identity'),
					'tls_psk' => $this->getInput('tls_psk')
				];
			}

			$result = API::Host()->update($host);
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

	private function prepareInterfaces(array $interfaces, array $host): array {
		$address_parser = new CAddressParser(['usermacros' => true, 'lldmacros' => true, 'macros' => true]);

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

			foreach ($host['interfaces'] as $host_interface) {
				if ($interface['type'] === $host_interface['type']) {
					$same = $interface['port'] === $host_interface['port']
						&& $interface['useip'] == $host_interface['useip']
						&& (($interface['useip'] == INTERFACE_USE_IP && $interface['ip'] === $host_interface['ip'])
							|| ($interface['useip'] == INTERFACE_USE_DNS && $interface['dns'] === $host_interface['dns']));

					if ($same) {
						if (in_array($interface['type'], [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_JMX,
								INTERFACE_TYPE_IPMI])) {
							unset($interfaces[$key]);

							break;
						}

						if ($interface['type'] == INTERFACE_TYPE_SNMP) {
							$identical = 0;

							foreach ($interface['details'] as $detail_key => $value) {
								if ($value == $host_interface['details'][$detail_key]) {
									$identical++;
								}
							}

							if ($identical === count($interface['details'])) {
								unset($interfaces[$key]);

								break;
							}
						}
					}
				}
			}

			$interface += [
				'isNew' => true,
				'main' => in_array($interface['type'], array_column($host['interfaces'], 'type'))
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
			$result[$host_macro['macro']] = $host_macro;
		}

		foreach ($macros as $macro) {
			$result[$macro['macro']] = array_key_exists($macro['macro'], $result)
				? $macro + ['hostmacroid' => $result[$macro['macro']]['hostmacroid']]
				: $macro;
		}

		return array_values($result);
	}
}
