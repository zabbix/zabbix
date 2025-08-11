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


class CControllerHostWizardCreate extends CControllerHostWizardUpdateGeneral {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'host' =>				'required|db hosts.host|not_empty',
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
						'title' => _('Cannot add host'),
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

		return parent::checkPermissions();
	}

	protected function doAction(): void {
		try {
			DBstart();

			if (!$this->validateMacrosByConfig()) {
				throw new Exception();
			}

			// Validate and update interfaces.
			$address_parser = new CAddressParser(['usermacros' => true, 'lldmacros' => true, 'macros' => true]);
			$interfaces = $this->getInput('interfaces', []);

			foreach ($interfaces as &$interface) {
				$address_parser->parse($interface['address']);

				$interface += [
					'useip' => $address_parser->getAddressType(),
					'isNew' => true,
					'main' => INTERFACE_PRIMARY
				];

				if ($interface['useip'] == INTERFACE_USE_DNS) {
					$interface['dns'] = $interface['address'];
					$interface['ip'] = '';
				}
				elseif ($interface['useip'] == INTERFACE_USE_IP) {
					$interface['ip'] = $interface['address'];
					$interface['dns'] = '';
				}

				unset($interface['address']);
			}
			unset($interface);

			$groups = $this->getInput('groups', []);
			$new_groups = $this->getInput('groups_new', []);

			$host = [
				'host' => $this->getInput('host'),
				'groups' => $this->processHostGroups($groups, $new_groups),
				'templates' => $this->processTemplates([[$this->getInput('templateid')]]),
				'interfaces' => $this->processHostInterfaces($interfaces),
				'macros' => $this->processUserMacros($this->getInput('macros', []))
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

			$result = API::Host()->create($host);
			$hostid = $result ? $result['hostids'][0] : null;

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
			$output['hostid'] = $hostid;

			$success = ['title' => _('Host added successfully')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add host'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
