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


class CControllerPopupLdapCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userdirectoryid' =>		'db userdirectory.userdirectoryid',
			'name' =>					'required|db userdirectory.name|not_empty',
			'host' =>					'required|db userdirectory_ldap.host|not_empty',
			'port' =>					'required|db userdirectory_ldap.port|ge '.ZBX_MIN_PORT_NUMBER.'|le '.ZBX_MAX_PORT_NUMBER,
			'base_dn' =>				'required|db userdirectory_ldap.base_dn|not_empty',
			'bind_dn' =>				'db userdirectory_ldap.bind_dn',
			'bind_password' =>			'db userdirectory_ldap.bind_password',
			'search_attribute' =>		'required|db userdirectory_ldap.search_attribute|not_empty',
			'start_tls' =>				'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'search_filter' =>			'db userdirectory_ldap.search_filter',
			'description' =>			'db userdirectory.description',
			'group_basedn' =>			'db userdirectory_ldap.group_basedn',
			'group_name' =>				'db userdirectory_ldap.group_name',
			'group_member' =>			'db userdirectory_ldap.group_member',
			'user_ref_attr' =>			'db userdirectory_ldap.user_ref_attr',
			'group_filter' =>			'db userdirectory_ldap.group_filter',
			'group_membership' =>		'db userdirectory_ldap.group_membership',
			'user_username' =>			'db userdirectory_ldap.user_username',
			'user_lastname' =>			'db userdirectory_ldap.user_lastname',
			'add_ldap_server' =>		'in 0,1',
			'group_configuration' =>	'in '.CControllerPopupLdapEdit::LDAP_MEMBER_OF.','.CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES,
			'provision_status' =>		'in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'provision_groups' =>		'array',
			'provision_media' =>		'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('provision_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
			if ($ret && !$this->validateProvisionGroups()) {
				error(_('Invalid user group mapping configuration.'));
				$ret = false;
			}

			if ($ret && !$this->validateProvisionMedia()) {
				error(_('Invalid media type mapping configuration.'));
				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid LDAP configuration'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'body' => [
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
		$fields = array_flip(array_keys($data['body']));

		if ($this->getInput('group_configuration') == CControllerPopupLdapEdit::LDAP_MEMBER_OF) {
			unset($fields['group_basedn'], $fields['group_member'], $fields['user_ref_attr'], $fields['group_filter']);
		}
		else {
			unset($fields['group_membership']);
		}

		$this->getInputs($data['body'], array_keys($fields));

		if ($this->hasInput('userdirectoryid')) {
			$data['body']['userdirectoryid'] = $this->getInput('userdirectoryid');
		}

		if ($this->hasInput('bind_password')) {
			$data['body']['bind_password'] = $this->getInput('bind_password');
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	private function validateProvisionGroups(): bool {
		$groups = $this->getInput('provision_groups', []);

		foreach ($groups as $group) {
			if (!is_array($group)) {
				return false;
			}

			if (!array_key_exists('user_groups', $group) || !is_array($group['user_groups'])
					|| !array_key_exists('roleid', $group) || !ctype_digit($group['roleid'])) {
				return false;
			}
		}

		return (bool) $groups;
	}

	private function validateProvisionMedia(): bool {
		$validation_rules = [
			'mediatypeid' =>	'db media_type.mediatypeid',
			'name' =>			'required|string|not_empty',
			'attribute' =>		'required|string|not_empty',
			'period' =>			'time_periods',
			'severity' =>		'int32|ge 0|le '.(pow(2, TRIGGER_SEVERITY_COUNT) - 1),
			'active' =>			'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])
		];

		foreach ($this->getInput('provision_media', []) as $provision_media) {
			$validator = new CNewValidator($provision_media, $validation_rules);

			if ($validator->isError()) {
				return false;
			}
		}

		return true;
	}
}
