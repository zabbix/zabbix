<?php
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
			'filter_name' =>	'string',
			'filter_scope' =>	'in '.implode(',', [-1, ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])
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
			CProfile::update('web.scripts.filter_scope', $this->getInput('filter_scope', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.scripts.filter_name');
			CProfile::delete('web.scripts.filter_scope');
		}

		$filter = [
			'name' => CProfile::get('web.scripts.filter_name', ''),
			'scope' => CProfile::get('web.scripts.filter_scope', -1)
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
			'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'type', 'execute_on',
				'scope', 'menu_path'
			],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'scope' => ($filter['scope'] == -1) ? null : $filter['scope']
			],
			'editable' => true,
			'limit' => $limit,
			'preservekeys' => true
		]);

		// Data sort and pager.
		$name_path = [];

		foreach ($data['scripts'] as $script) {
			$trim_menu_path = trim($script['menu_path'], "/");
			$trim_name = trim($script['name'], "/");

			if ($script['menu_path'] != null) {
				$script['name_path'] = ('/'.$trim_menu_path.'/'.$trim_name);
			}
			else {
				$script['name_path'] = ($trim_name);
			}
			$name_path[] = $script;
		}
		unset($script);

		$data['scripts'] = $name_path;

		if ($sortField != 'name') {
			order_result($data['scripts'], $sortField, $sortOrder);
		}
		else {
			order_result($data['scripts'], 'name_path', $sortOrder);
		}

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('script.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['scripts'], $sortOrder,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);



		/*
		 * Find script host group name and user group name. Set to NULL if all host/user groups used. Find associated
		 * actions in any of operations. Collect scriptids for action scope scripts.
		 */
		$action_scriptids = [];
		$usrgrpids = [];
		$groupids = [];

		foreach ($data['scripts'] as &$script) {
			$script['actions'] = [];

			if ($script['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
				$script['command'] = '';
			}

			$script['userGroupName'] = null;
			$script['hostGroupName'] = null;

			if ($script['usrgrpid'] != 0) {
				$usrgrpids[] = $script['usrgrpid'];
			}

			if ($script['groupid'] != 0) {
				$groupids[] = $script['groupid'];
			}

			if ($script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
				$action_scriptids[$script['scriptid']] = true;
			}
		}
		unset($script);

		if ($action_scriptids) {
			$script_actions = API::Script()->get([
				'output' => [],
				'scriptids' => array_keys($action_scriptids),
				'selectActions' => ['actionid', 'name', 'eventsource'],
				'preservekeys' => true
			]);

			foreach ($data['scripts'] as $scriptid => &$script) {
				if (array_key_exists($scriptid, $script_actions)) {
					$script['actions'] = $script_actions[$scriptid]['actions'];
					CArrayHelper::sort($script['actions'], ['name']);
				}
			}
			unset($script);
		}

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

		$data['config'] = [
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
