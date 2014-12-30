<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerScriptList extends CController {

	protected function checkInput() {
		$fields = array(
			'sort' =>			'fatal|in_str:command,name',
			'sortorder' =>		'fatal|in_str:'.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'fatal|in_int:1'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=script.list');
					$response->setMessageError(_('Validation error'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $result;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			access_deny();
		}
	}

	protected function doAction() {
		$data['uncheck'] = $this->hasInput('uncheck');

		$sortField = $this->getInput('sort', CProfile::get('web.scripts.php.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.scripts.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.scripts.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.scripts.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$data['sort'] = $sortField;
		$data['sortorder'] = $sortOrder;

		// list of scripts
		$data['scripts'] = API::Script()->get(array(
			'output' => array('scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'type', 'execute_on'),
			'editable' => true,
			'selectGroups' => API_OUTPUT_EXTEND
		));

		// find script host group name and user group name. set to '' if all host/user groups used.
		foreach ($data['scripts'] as $key => $script) {
			if ($script['usrgrpid'] > 0) {
				$userGroup = API::UserGroup()->get(array('usrgrpids' => $script['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
				$userGroup = reset($userGroup);

				$data['scripts'][$key]['userGroupName'] = $userGroup['name'];
			}
			else {
				$data['scripts'][$key]['userGroupName'] = null; // all user groups
			}

			if ($script['groupid'] > 0) {
				$group = array_pop($script['groups']);

				$data['scripts'][$key]['hostGroupName'] = $group['name'];
			}
			else {
				$data['scripts'][$key]['hostGroupName'] = null; // all host groups
			}
		}

		// sorting & paging
		order_result($data['scripts'], $sortField, $sortOrder);
		$data['paging'] = getPagingLine($data['scripts']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
