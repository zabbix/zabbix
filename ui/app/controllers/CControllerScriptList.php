<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>			'in name,command',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'in 1',
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.scripts.php.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.scripts.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.scripts.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.scripts.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.scripts.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.scripts.filter_name');
		}

		$filter = [
			'name' => CProfile::get('web.scripts.filter_name', '')
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'filter' => $filter,
			'profileIdx' => 'web.scripts.filter',
			'active_tab' => CProfile::get('web.scripts.filter.active', 1)
		];

		// list of scripts
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['scripts'] = API::Script()->get([
			'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'type', 'execute_on'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'editable' => true,
			'limit' => $limit
		]);

		// data sort and pager
		order_result($data['scripts'], $sortField, $sortOrder);

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('script.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['scripts'], $sortOrder,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		// find script host group name and user group name. set to '' if all host/user groups used.

		$usrgrpids = [];
		$groupids = [];

		foreach ($data['scripts'] as &$script) {
			$script['userGroupName'] = null; // all user groups
			$script['hostGroupName'] = null; // all host groups

			if ($script['usrgrpid'] != 0) {
				$usrgrpids[] = $script['usrgrpid'];
			}

			if ($script['groupid'] != 0) {
				$groupids[] = $script['groupid'];
			}
		}
		unset($script);

		if ($usrgrpids) {
			$userGroups = API::UserGroup()->get([
				'output' => ['name'],
				'usrgrpids' => $usrgrpids,
				'preservekeys' => true
			]);

			foreach ($data['scripts'] as &$script) {
				if ($script['usrgrpid'] != 0 && array_key_exists($script['usrgrpid'], $userGroups)) {
					$script['userGroupName'] = $userGroups[$script['usrgrpid']]['name'];
				}
				unset($script['usrgrpid']);
			}
			unset($script);
		}

		if ($groupids) {
			$hostGroups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);

			foreach ($data['scripts'] as &$script) {
				if ($script['groupid'] != 0 && array_key_exists($script['groupid'], $hostGroups)) {
					$script['hostGroupName'] = $hostGroups[$script['groupid']]['name'];
				}
				unset($script['groupid']);
			}
			unset($script);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
