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


class CControllerPopupLdapCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getFieldsValidationRules(array $existing_names = []): array {
		$name_extra_rules = ['string'];

		if (count($existing_names) > 0) {
			$name_extra_rules += ['not_in' => $existing_names,
				'messages' => ['not_in' => _('Name already exists.')]
			];
		}

		return [
			'userdirectoryid' => ['db userdirectory.userdirectoryid'],
			'name' => [
				['db userdirectory.name', 'required', 'not_empty'],
				$name_extra_rules
			],
			'host' => ['db userdirectory_ldap.host', 'required', 'not_empty'],
			'port' => ['db userdirectory_ldap.port', 'required', 'min' => ZBX_MIN_PORT_NUMBER,
				'max' => ZBX_MAX_PORT_NUMBER
			],
			'base_dn' => ['db userdirectory_ldap.base_dn', 'required', 'not_empty'],
			'search_attribute' => ['db userdirectory_ldap.search_attribute', 'required', 'not_empty'],
			'bind_dn' => ['db userdirectory_ldap.bind_dn'],
			'bind_password' => ['db userdirectory_ldap.bind_password'],
			'description' => ['db userdirectory.description'],
			'provision_status' => ['db userdirectory.provision_status',
				'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]
			],
			'group_configuration' => ['integer',
				'in' => [CControllerPopupLdapEdit::LDAP_MEMBER_OF, CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES],
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'group_basedn' => ['db userdirectory_ldap.group_basedn',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
					['group_configuration', 'in' => [CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES]]
				]
			],
			'group_name' => ['db userdirectory_ldap.group_name',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'group_membership' => ['db userdirectory_ldap.group_membership',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
					['group_configuration', 'in' => [CControllerPopupLdapEdit::LDAP_MEMBER_OF]]
				]
			],
			'group_member' => ['db userdirectory_ldap.group_member',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
					['group_configuration', 'in' => [CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES]]
				]
			],
			'user_ref_attr' => ['db userdirectory_ldap.user_ref_attr',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
					['group_configuration', 'in' => [CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES]]
				]
			],
			'group_filter' => ['db userdirectory_ldap.group_filter',
				'when' => [
					['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
					['group_configuration', 'in' => [CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES]]
				]
			],
			'user_username' => ['db userdirectory_ldap.user_username',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'user_lastname' => ['db userdirectory_ldap.user_lastname',
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
			],
			'provision_groups' => ['objects', 'not_empty', 'uniq' => ['name'],
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
				'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
				'messages' => ['uniq' => _('Media type and attribute is not unique.')]
			],
			'start_tls' => ['db userdirectory_ldap.start_tls', 'in' => [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON]],
			'search_filter' => ['db userdirectory_ldap.search_filter']
		];
	}

	public static function getValidationRules(array $existing_names = []): array {
		return ['object', 'fields' => self::getFieldsValidationRules($existing_names)];
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
		$data = [
			'body' => $this->getInputAll() + [
				'name' => '',
				'host' => '',
				'port' => '',
				'base_dn' => '',
				'search_attribute' => '',
				'start_tls' => ZBX_AUTH_START_TLS_OFF,
				'bind_dn' => '',
				'description' => '',
				'search_filter' => '',
				'group_basedn' => '',
				'group_name' => '',
				'group_member' => '',
				'user_ref_attr' => '',
				'group_filter' => '',
				'group_membership' => '',
				'user_username' => '',
				'user_lastname' => '',
				'provision_status' => JIT_PROVISIONING_DISABLED,
				'add_ldap_server' => 1,
				'provision_groups' => [],
				'provision_media' => []
			]
		];

		if ($this->hasInput('provision_groups')) {
			foreach ($data['body']['provision_groups'] as &$group) {
				$group['user_groups'] = zbx_toObject($group['user_groups'], 'usrgrpid');
			}
			unset($group);
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
