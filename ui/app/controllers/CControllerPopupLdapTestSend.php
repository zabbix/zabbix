<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerPopupLdapTestSend extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'userdirectoryid' => ['db userdirectory.userdirectoryid'],
			'host' => ['db userdirectory_ldap.host', 'required', 'not_empty'],
			'port' => ['db userdirectory_ldap.port', 'required', 'min' => ZBX_MIN_PORT_NUMBER,
				'max' => ZBX_MAX_PORT_NUMBER
			],
			'base_dn' => ['db userdirectory_ldap.base_dn', 'required', 'not_empty'],
			'search_attribute' => ['db userdirectory_ldap.search_attribute', 'required', 'not_empty'],
			'bind_dn' => ['db userdirectory_ldap.bind_dn'],
			'bind_password' => ['db userdirectory_ldap.bind_password'],
			'provision_status' => ['db userdirectory.provision_status',
				'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]
			],
			'group_basedn' => ['db userdirectory_ldap.group_basedn',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'group_name' => ['db userdirectory_ldap.group_name',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'group_membership' => ['db userdirectory_ldap.group_membership',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'group_member' => ['db userdirectory_ldap.group_member',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'user_ref_attr' => ['db userdirectory_ldap.user_ref_attr',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'group_filter' => ['db userdirectory_ldap.group_filter',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'user_username' => ['db userdirectory_ldap.user_username',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'user_lastname' => ['db userdirectory_ldap.user_lastname',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'provision_groups' => ['objects', 'required', 'not_empty', 'uniq' => ['name'],
				'fields' => CControllerPopupUserGroupMappingCheck::getFieldsValidationRules(),
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'provision_media' => ['objects', 'uniq' => ['attribute', 'mediatypeid'],
				'fields' => [
					'userdirectory_mediaid' => ['db userdirectory_media.userdirectory_mediaid'],
					'mediatypeid' => ['db media_type.mediatypeid', 'required'],
					'name' => ['db userdirectory_media.name', 'required', 'not_empty'],
					'attribute' => ['db userdirectory_media.attribute', 'required', 'not_empty'],
					'period' => ['db userdirectory_media.period', 'required', 'not_empty',
						'use' => [CTimePeriodParser::class, ['usermacros' => true]]
					],
					'severity' => ['db userdirectory_media.severity', 'min' => 0,
						'max' => (pow(2, TRIGGER_SEVERITY_COUNT) - 1)
					],
					'active' => ['db userdirectory_media.active', 'required',
						'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]
					]
				],
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'start_tls' => ['db userdirectory_ldap.start_tls', 'in' => [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON]],
			'search_filter' => ['db userdirectory_ldap.search_filter'],
			'test_username' => ['string', 'required', 'not_empty'],
			'test_password' => ['string', 'required', 'not_empty']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Invalid LDAP configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$ldap_test_object = [
			'provision_groups'	=> [],
			'provision_media'	=> [],
			'provision_status'	=> JIT_PROVISIONING_DISABLED
		];
		$this->getInputs($ldap_test_object, ['userdirectoryid', 'host', 'port', 'base_dn', 'bind_dn', 'bind_password',
			'search_attribute', 'start_tls', 'search_filter','test_username', 'test_password', 'provision_status',
			'group_basedn', 'group_name', 'group_member', 'group_filter', 'user_ref_attr','group_membership',
			'user_username', 'user_lastname'
		]);

		foreach ($this->getInput('provision_groups', []) as $provision_group) {
			if (!array_key_exists('roleid', $provision_group) || !array_key_exists('user_groups', $provision_group)) {
				continue;
			}

			$ldap_test_object['provision_groups'][] = [
				'name'			=> $provision_group['name'],
				'roleid'		=> $provision_group['roleid'],
				'user_groups'	=> zbx_toObject($provision_group['user_groups'], 'usrgrpid')
			];
		}

		foreach ($this->getInput('provision_media', []) as $provision_media) {
			$ldap_test_object['provision_media'][] = [
				'attribute'		=> $provision_media['attribute'],
				'mediatypeid'	=> $provision_media['mediatypeid'],
				'name'			=> $provision_media['name']
			];
		}

		$user = API::UserDirectory()->test($ldap_test_object);

		$output = [];
		$provisioning = [
			'role' => [],
			'groups' => [],
			'medias' => []
		];

		if ($user) {
			$success = ['title' => _('Login successful')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;

			if ($ldap_test_object['provision_status'] == JIT_PROVISIONING_ENABLED) {
				if (array_key_exists('roleid', $user)) {
					$provisioning['role'] = array_column(API::Role()->get([
						'output' => ['name'],
						'roleids' => [$user['roleid']]
					]), 'name');
				}

				$user_groupsids = array_key_exists('usrgrps', $user) ? array_column($user['usrgrps'], 'usrgrpid') : [];

				if ($user_groupsids) {
					$provisioning['groups'] = array_column(API::UserGroup()->get([
						'output' => ['name'],
						'usrgrpids' => $user_groupsids
					]), 'name');
				}

				if (array_key_exists('medias', $user)) {
					$provisioning['medias'] = array_column($user['medias'], 'name');
				}
			}
		}
		else {
			$output['error'] = [
				'title' => _('Login failed'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		if ($ldap_test_object['provision_status'] == JIT_PROVISIONING_ENABLED) {
			$output['provisioning'] = $provisioning;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
