<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerUsergroupUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'usrgrpid'     => 'required|db usrgrp.usrgrpid',
			'name'         => 'required|not_empty|string',
			'userids'      => 'array_db users.userid',
			'gui_access'   => 'required|db usrgrp.gui_access|in '.implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]),
			'users_status' => 'required|db usrgrp.users_status|in '.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
			'debug_mode'   => 'required|db usrgrp.debug_mode|in '.GROUP_DEBUG_MODE_ENABLED.','.GROUP_DEBUG_MODE_DISABLED,

			'group_rights' => 'array',
			'tag_filters'  => 'array',

			'new_group_right' => 'array',
			'new_tag_filter' => 'array',

			'form_refresh' => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('usrgrpid', $this->getInput('usrgrpid'))
						->setArgument('action', 'usergroup.edit')
						->getUrl()
					);
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update group'));
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
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$user_group = [
			'usrgrpid'     => $this->getInput('usrgrpid'),
			'name'         => $this->getInput('name'),
			'users_status' => $this->getInput('users_status'),
			'gui_access'   => $this->getInput('gui_access'),
			'debug_mode'   => $this->getInput('debug_mode'),
			'userids'      => $this->getInput('userids', []),
			'tag_filters'  => $this->getInput('tag_filters', []),
			'rights'       => []
		];

		sdfile($this->getInput( 'group_rights' ));

		$group_rights = applyHostGroupRights($this->getInput('group_rights', []));

		foreach ($group_rights as $groupid => $group_right) {
			if ($groupid != 0 && $group_right['permission'] != PERM_NONE) {
				$user_group['rights'][] = [
					'id' => (string) $groupid,
					'permission' => $group_right['permission']
				];
			}
		}

		$result = (bool) API::UserGroup()->update($user_group);

		$form_data = $this->getInputAll();

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.list')
				->getUrl()
			);
			$response->setMessageOk(_('Group updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('usrgrpid', $this->getInput('usrgrpid'))
				->setArgument('action', 'usergroup.edit')
				->getUrl()
			);
			$response->setMessageError(_('Cannot update group'));
			$response->setFormData($form_data);
		}

		$this->setResponse($response);
	}
}
