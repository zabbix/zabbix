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


class CControllerUsergroupUpdate extends CControllerUsergroupUpdateGeneral {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['usergroup.get', ['name' => '{name}'], 'usrgrpid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'usrgrpid' => ['db usrgrp.usrgrpid', 'required'],
			'name' => ['db usrgrp.name', 'required', 'not_empty'],
			'gui_access' => ['integer',
				'in' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP,
					GROUP_GUI_ACCESS_DISABLED
				]
			],
			'userdirectoryid' => ['db userdirectory.userdirectoryid',
				'when' => ['gui_access', 'in' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_LDAP]]
			],
			'mfa_status' => ['integer', 'in' => [GROUP_MFA_DISABLED, GROUP_MFA_ENABLED]],
			'mfaid' => ['db mfa.mfaid', 'when' => ['mfa_status', 'in' => [GROUP_MFA_ENABLED]]],
			'users_status' => ['db usrgrp.users_status', 'in' => [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]],
			'userids' => [
				['array', 'field' => ['db users_groups.userid']],
				['array',
					'field' => [
						'string', 'not_in' => [CWebUser::$data['userid']],
						'messages' => ['not_in' =>
							_('User cannot add oneself to a disabled group or a group with disabled GUI access.')
						]
					],
					'when' => ['gui_access', 'in' => [GROUP_GUI_ACCESS_DISABLED]]
				],
				['array',
					'field' => [
						'string', 'not_in' => [CWebUser::$data['userid']],
						'messages' => ['not_in' =>
							_('User cannot add oneself to a disabled group or a group with disabled GUI access.')
						]
					],
					'when' => ['users_status', 'in' => [GROUP_STATUS_DISABLED]]
				]
			],
			'debug_mode' => ['db usrgrp.debug_mode', 'in' => [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED]],
			'templategroup_rights' => ['objects', 'fields' => [
				'groupids' => ['array', 'required', 'not_empty', 'field' => ['db rights.groupid']],
				'permission' => ['integer', 'required', 'in' => [PERM_DENY, PERM_READ, PERM_READ_WRITE]]
			]],
			'hostgroup_rights' => ['objects', 'fields' => [
				'groupids' => ['array', 'required', 'not_empty', 'field' => ['db rights.groupid']],
				'permission' => ['integer', 'required', 'in' => [PERM_DENY, PERM_READ, PERM_READ_WRITE]]
			]],
			'tag_filters' => ['objects',
				'fields' => [
					'groupid' => ['db tag_filter.groupid', 'required'],
					'tags' => ['objects', 'fields' => [
						'value' => ['db tag_filter.value'],
						'tag' => [
							['db tag_filter.tag'],
							['db tag_filter.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
						]
					]]
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update user group'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function getUserGroupInputData(): array {
		$user_group = parent::getUserGroupInputData();
		$this->getInputs($user_group, ['usrgrpid']);

		return $user_group;
	}

	protected function doAction(): void {
		$user_group = self::processUserGroupInputData($this->getUserGroupInputData());

		$result = (bool) API::UserGroup()->update($user_group);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('User group updated');
			$output['success']['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.list')
				->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
				->getUrl();

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update user group'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
