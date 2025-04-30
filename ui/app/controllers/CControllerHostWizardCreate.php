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


class CControllerHostWizardCreate extends CControllerHostUpdateGeneral {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'host' =>				'required|db hosts.host|not_empty',
			'groups' =>				'required|array',
			'templates' =>			'required|array_db hosts.hostid',
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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected static function getValidateMacroRules(): array {
		return ['type' => API_OBJECT, 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_WIZARD_FIELD_NOCONF, ZBX_WIZARD_FIELD_TEXT, ZBX_WIZARD_FIELD_LIST, ZBX_WIZARD_FIELD_CHECKBOX])],
				'label' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIZARD_FIELD_TEXT, ZBX_WIZARD_FIELD_LIST, ZBX_WIZARD_FIELD_CHECKBOX])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('hostmacro_config', 'label')],
					['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hostmacro_config', 'label')]
				]],
				'description' =>		['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIZARD_FIELD_TEXT, ZBX_WIZARD_FIELD_LIST, ZBX_WIZARD_FIELD_CHECKBOX])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro_config', 'description')],
					['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hostmacro_config', 'description')]
				]],
				'required' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIZARD_FIELD_TEXT, ZBX_WIZARD_FIELD_LIST])], 'type' => API_INT32, 'in' => implode(',', [ZBX_WIZARD_FIELD_NOT_REQUIRED, ZBX_WIZARD_FIELD_REQUIRED])],
					['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('hostmacro_config', 'required')]
				]],
				'regex' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => ZBX_WIZARD_FIELD_TEXT], 'type' => API_REGEX, 'length' => DB::getFieldLength('hostmacro_config', 'regex')],
					['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hostmacro_config', 'regex')]
				]],
				'options' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => ZBX_WIZARD_FIELD_LIST], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['value', 'text']], 'fields' => [
						'value' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'text' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
					]],
					['if' => ['field' => 'type', 'in' => ZBX_WIZARD_FIELD_CHECKBOX], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 1, 'fields' => [
						'checked' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'unchecked' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]],
					['else' => true, 'type' => API_OBJECTS, 'length' => 0]
				]]
			]
		];
	}

	protected static function validateMacros(array $macros): array {
		dump(compact('macros') + ['source' => __FUNCTION__]);
		$errors = [];

		foreach ($macros as $path => $macro) {
			if (!CApiInputValidator::validate(self::getValidateMacroRules(), $macro['config'], $path, $error)) {
				$errors[] = $error;
				continue;
			}

			if ($macro['config']['regex'] && !preg_match('/'.$macro['config']['regex'].'/', $macro['value'])) {
				$errors[] = _s('Host configuration macro "%1$s" has incorrect value.', $macro['macro']);
			}
		}

		return $errors ? [
			'title' => _('Cannot add host'),
			'messages' => $errors
		] : [];
	}

	protected static function addMacroConfig(array $macros): array {
		$configs = [];
		$user_macros = API::UserMacro()->get([
			'output' => 'extend',
			'filter' => ['macro' => array_column($macros, 'macro')]
		]);

		foreach ($user_macros as $user_macro) {
			if (!array_key_exists('config', $user_macro)) {
				continue;
			}

			$configs[$user_macro['macro']] = $user_macro['config'];
		}

		foreach ($macros as &$macro) {
			$macro['config'] = $configs[$macro['macro']];
		}
		unset($macro);

		return $macros;
	}

	protected function doAction(): void {
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

		try {
			DBstart();

			$host = [
				'host' => $this->getInput('host'),
				'groups' => $this->processHostGroups($this->getInput('groups', [])),
				'templates' => $this->processTemplates([$this->getInput('templates', [])]),
				'interfaces' => $this->processHostInterfaces($interfaces),
				'macros' => $this->processUserMacros($this->getInput('macros', [])),
			];

			if ($this->hasInput('tls_psk_identity') && $this->hasInput('tls_psk')) {
				$host += [
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => $this->getInput('tls_psk_identity', ''),
					'tls_psk' => $this->getInput('tls_psk', '')
				];
			}
			else {
				$host['tls_connect'] = HOST_ENCRYPTION_NONE;
			}

			if ($error = self::validateMacros(self::addMacroConfig($host['macros']))) {
				$this->setResponse(new CControllerResponseData(['main_block' => json_encode(['error' => $error])]));

				return;
			}

			$result = API::Host()->create($host);

			if ($result === false) {
				throw new Exception();
			}

			$result = DBend();
		}
		catch (Exception) {
			$result = false;

			DBend(false);
		}

		$output = [];

		if ($result) {
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

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
