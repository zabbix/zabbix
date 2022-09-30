<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupLdapCheck extends CController {

	protected function init(): void {
		$this->disableSIDValidation();

		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'userdirectoryid' =>	'db userdirectory.userdirectoryid',
			'name' =>				'required|db userdirectory.name|not_empty',
			'host' =>				'required|db userdirectory_ldap.host|not_empty',
			'port' =>				'required|db userdirectory_ldap.port|ge '.ZBX_MIN_PORT_NUMBER.'|le '.ZBX_MAX_PORT_NUMBER,
			'base_dn' =>			'required|db userdirectory_ldap.base_dn|not_empty',
			'bind_dn' =>			'db userdirectory_ldap.bind_dn',
			'bind_password' =>		'db userdirectory_ldap.bind_password',
			'search_attribute' =>	'required|db userdirectory_ldap.search_attribute|not_empty',
			'start_tls' =>			'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'search_filter' =>		'db userdirectory_ldap.search_filter',
			'description' =>		'db userdirectory.description',
			'group_basedn' =>		'db userdirectory_ldap.group_basedn',
			'group_name' =>			'db userdirectory_ldap.group_name',
			'group_member' =>		'db userdirectory_ldap.group_member',
			'group_filter' =>		'db userdirectory_ldap.group_filter',
			'group_membership' =>	'db userdirectory_ldap.group_membership',
			'user_username' =>		'db userdirectory_ldap.user_username',
			'user_lastname' =>		'db userdirectory_ldap.user_lastname',
			'add_ldap_server' =>	'in 0,1',
			'provision_status' =>	'in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'provision_groups' =>	'array',
			'provision_media' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('provision_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
			foreach (['group_basedn', 'group_member', 'group_filter', 'group_membership'] as $field) {
				if (!$this->hasInput($field)) {
					error(_s('Field "%1$s" is mandatory.', $field));
					$ret = false;
					break;
				}
				elseif ($this->getInput($field) === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty')));
					$ret = false;
					break;
				}
			}

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
				'name' => $this->getInput('name'),
				'host' => $this->getInput('host'),
				'port' => $this->getInput('port'),
				'base_dn' => $this->getInput('base_dn'),
				'search_attribute' => $this->getInput('search_attribute'),
				'start_tls' => $this->getInput('start_tls', ZBX_AUTH_START_TLS_OFF),
				'bind_dn' => $this->getInput('bind_dn', ''),
				'description' => $this->getInput('description', ''),
				'search_filter' => $this->getInput('search_filter', ''),
				'group_basedn' => $this->getInput('group_basedn', ''),
				'group_name' => $this->getInput('group_name', ''),
				'group_member' => $this->getInput('group_member', ''),
				'group_filter' => $this->getInput('group_filter', ''),
				'group_membership' => $this->getInput('group_membership', ''),
				'user_username' => $this->getInput('user_username', ''),
				'user_lastname' => $this->getInput('user_lastname', ''),
				'provision_status' => $this->getInput('provision_status', JIT_PROVISIONING_DISABLED),
				'add_ldap_server' => $this->getInput('add_ldap_server', 1),
				'provision_groups' => $this->getInput('provision_groups', []),
				'provision_media' => $this->getInput('provision_media', [])
			]
		];

		foreach ($data['body']['provision_groups'] as $index => $group) {
			if (array_key_exists('enabled', $group) && $group['enabled'] == 0) {
				unset($data['body']['provision_groups'][$index]);
				continue;
			}

			$group_props = ['name', 'sortorder', 'user_groups', 'roleid'];
			$data['body']['provision_groups'][$index] = array_intersect_key($group, array_flip($group_props));
		}

		CArrayHelper::sort($data['body']['provision_groups'], ['sortorder']);
		$data['body']['provision_groups'] = array_values($data['body']['provision_groups']);

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

		foreach ($groups as $index => $group) {
			if (!is_array($group)) {
				return false;
			}

			if (array_key_exists('enabled', $group) && $group['enabled'] == 0) {
				unset($groups[$index]);
				continue;
			}

			if (!array_key_exists('user_groups', $group) || !is_array($group['user_groups'])
					|| !array_key_exists('roleid', $group) || !ctype_digit($group['roleid'])) {
				return false;
			}

			if (!array_key_exists('enabled', $group)
					&& (!array_key_exists('name', $group) || $group['name'] === ''
							|| $group['name'] === USERDIRECTORY_FALLBACK_GROUP_NAME)) {
				return false;
			}
		}

		return (bool) $groups;
	}

	private function validateProvisionMedia(): bool {
		if (!$this->hasInput('provision_media')) {
			return true;
		}

		foreach ($this->getInput('provision_media') as $media) {
			if (!is_array($media)
				|| !array_key_exists('name', $media) || !is_string($media['name']) || $media['name'] === ''
				|| !array_key_exists('attribute', $media) || !is_string($media['attribute'])
				|| $media['attribute'] === ''
				|| !array_key_exists('mediatypeid', $media) || !ctype_digit($media['mediatypeid'])) {
				return false;
			}
		}

		return true;
	}
}
