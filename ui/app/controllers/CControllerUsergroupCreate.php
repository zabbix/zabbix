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


class CControllerUsergroupCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name' =>					'required|not_empty|db usrgrp.name',
			'userids' =>				'array_db users.userid',
			'gui_access' =>				'db usrgrp.gui_access|in '.implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]),
			'users_status' =>			'db usrgrp.users_status|in '.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
			'debug_mode' =>				'db usrgrp.debug_mode|in '.GROUP_DEBUG_MODE_ENABLED.','.GROUP_DEBUG_MODE_DISABLED,
			'userdirectoryid' =>		'db usrgrp.userdirectoryid',
			'mfaid' =>					'int32',
			'ms_hostgroup_right' =>		'array',
			'hostgroup_right' =>		'array',
			'ms_templategroup_right' =>	'array',
			'templategroup_right' =>	'array',
			'tag_filters' =>			'array',
			'form_refresh' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.edit')
					);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot add user group'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction() {
		$user_group = [
			'users' => zbx_toObject($this->getInput('userids', []), 'userid'),
			'hostgroup_rights' => [],
			'templategroup_rights' => [],
			'tag_filters' => []
		];

		$this->getInputs($user_group, ['users_status', 'gui_access', 'debug_mode', 'userdirectoryid', 'mfaid']);
		$user_group['name'] = trim($this->getInput('name'));

		$db_hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);
		$db_templategroups = API::TemplateGroup()->get([
			'output' => ['groupid', 'name']
		]);

		$hostgroup_rights = [];
		$templategroup_rigts = [];

		$this->getInputs($hostgroup_rights, ['ms_hostgroup_right', 'hostgroup_right']);
		$this->getInputs($templategroup_rigts, ['ms_templategroup_right', 'templategroup_right']);

		if (!checkGroupsExist($hostgroup_rights, $db_hostgroups, 'ms_hostgroup_right')
				|| !checkGroupsExist($templategroup_rigts, $db_templategroups, 'ms_templategroup_right')) {
			$this->getErrorResponse(true);

			return;
		}

		$user_group['hostgroup_rights'] = processRights($hostgroup_rights, 'ms_hostgroup_right', 'hostgroup_right');
		$user_group['templategroup_rights'] = processRights($templategroup_rigts,'ms_templategroup_right',
			'templategroup_right'
		);

		$tag_filters = $this->getInput('tag_filters', []);

		foreach ($tag_filters as $hostgroup) {
			if (in_array($hostgroup['groupid'], array_column($db_hostgroups, 'groupid'))) {
				foreach ($hostgroup['tags'] as $tag_filter) {
					if ($hostgroup['groupid'] != 0) {
						$user_group['tag_filters'][] = [
							'groupid' => $hostgroup['groupid'],
							'tag' => $tag_filter['tag'],
							'value' => $tag_filter['value']
						];
					}
				}
			}
			else {
				$this->getErrorResponse(true);

				return;
			}
		}

		if (array_key_exists('mfaid', $user_group)) {
			if ($user_group['mfaid'] == -1) {
				$user_group['mfa_status'] = GROUP_MFA_DISABLED;
				unset($user_group['mfaid']);
			}
			else {
				$user_group['mfa_status'] = GROUP_MFA_ENABLED;
			}
		}

		$result = (bool) API::UserGroup()->create($user_group);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'usergroup.list')
					->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('User group added'));

			$this->setResponse($response);
		}
		else {
			$this->getErrorResponse();
		}
	}

	/**
	 * Creates and sets an error response when creating a user group fails.
	 * Redirects to the 'usergroup.edit' action and optionally adds a specific error message.
	 *
	 * @param bool $add_message  Optional flag for adding a specific error message.
	 */
	private function getErrorResponse(bool $add_message = false): void {
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.edit')
		);
		CMessageHelper::setErrorTitle(_('Cannot add user group'));

		if ($add_message) {
			CMessageHelper::addError(_('No permissions to referred object or it does not exist!'));
		}

		$response->setFormData($this->getInputAll());

		$this->setResponse($response);
	}
}
