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


class CControllerPopupLdapTestEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userdirectoryid' =>		'db userdirectory.userdirectoryid',
			'host' =>					'required|db userdirectory_ldap.host|not_empty',
			'port' =>					'required|db userdirectory_ldap.port|ge '.ZBX_MIN_PORT_NUMBER.'|le '.ZBX_MAX_PORT_NUMBER,
			'base_dn' =>				'required|db userdirectory_ldap.base_dn|not_empty',
			'bind_dn' =>				'db userdirectory_ldap.bind_dn',
			'bind_password' =>			'db userdirectory_ldap.bind_password',
			'search_attribute' =>		'required|db userdirectory_ldap.search_attribute|not_empty',
			'start_tls' =>				'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'search_filter' =>			'db userdirectory_ldap.search_filter',
			'provision_status' =>		'db userdirectory.provision_status|in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'group_basedn' =>			'db userdirectory_ldap.group_basedn',
			'group_name' =>				'db userdirectory_ldap.group_name',
			'group_member' =>			'db userdirectory_ldap.group_member',
			'user_ref_attr' =>			'db userdirectory_ldap.user_ref_attr',
			'group_filter' =>			'db userdirectory_ldap.group_filter',
			'group_membership' =>		'db userdirectory_ldap.group_membership',
			'provision_media' =>		'array',
			'provision_groups' =>		'array',
			'user_username' =>			'db userdirectory_ldap.user_username',
			'user_lastname' =>			'db userdirectory_ldap.user_lastname'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid LDAP configuration'),
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'ldap_config' => [
				'provision_status' => JIT_PROVISIONING_DISABLED
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'test_username' => CWebUser::$data['username']
		];

		$this->getInputs($data['ldap_config'], ['userdirectoryid', 'host', 'port', 'base_dn', 'bind_dn',
			'bind_password', 'search_attribute', 'start_tls', 'search_filter','test_username', 'test_password',
			'provision_status', 'group_basedn', 'group_name', 'group_member', 'user_ref_attr','group_filter',
			'group_membership', 'user_username', 'user_lastname', 'provision_media', 'provision_groups'
		]);

		$this->setResponse(new CControllerResponseData($data));
	}
}
