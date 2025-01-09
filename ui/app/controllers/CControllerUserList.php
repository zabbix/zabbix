<?php
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


class CControllerUserList extends CController {

	public const FILTERS_SOURCE_ALL = 0;
	public const FILTERS_SOURCE_INTERNAL = 1;
	public const FILTERS_SOURCE_LDAP= 2;
	public const FILTERS_SOURCE_SAML = 3;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>				'in username,name,surname,role_name,ts_provisioned',
			'sortorder' =>			'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>			'in 1',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'filter_username' =>	'string',
			'filter_name' =>		'string',
			'filter_surname' =>		'string',
			'filter_roles' =>		'array_id',
			'filter_usrgrpids'=>	'array_id',
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
	}

	protected function doAction() {
		$sortfield = $this->getInput('sort', CProfile::get('web.user.sort', 'username'));
		$sortorder = $this->getInput('sortorder', CProfile::get('web.user.sortorder', ZBX_SORT_UP));
		CProfile::update('web.user.sort', $sortfield, PROFILE_TYPE_STR);
		CProfile::update('web.user.sortorder', $sortorder, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.user.filter_username', $this->getInput('filter_username', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.filter_surname', $this->getInput('filter_surname', ''), PROFILE_TYPE_STR);
			CProfile::updateArray('web.user.filter_roles', $this->getInput('filter_roles', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.user.filter_usrgrpids', $this->getInput('filter_usrgrpids', []),
				PROFILE_TYPE_ID
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.user.filter_username');
			CProfile::delete('web.user.filter_name');
			CProfile::delete('web.user.filter_surname');
			CProfile::deleteIdx('web.user.filter_roles');
			CProfile::deleteIdx('web.user.filter_usrgrpids');
		}

		$filter = [
			'username' => CProfile::get('web.user.filter_username', ''),
			'name' => CProfile::get('web.user.filter_name', ''),
			'surname' => CProfile::get('web.user.filter_surname', ''),
			'roles' => CProfile::getArray('web.user.filter_roles', []),
			'usrgrpids' => CProfile::getArray('web.user.filter_usrgrpids', [])
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sortfield,
			'sortorder' => $sortorder,
			'filter' => $filter,
			'profileIdx' => 'web.user.filter',
			'active_tab' => CProfile::get('web.user.filter.active', 1),
			'sessions' => [],
			'allowed_ui_user_groups' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS)
		];

		$data['filter']['roles'] = $filter['roles']
			? CArrayHelper::renameObjectsKeys(API::Role()->get([
				'output' => ['roleid', 'name'],
				'roleids' => $filter['roles']
			]), ['roleid' => 'id'])
			: [];

		$data['filter']['usrgrpids'] = $filter['usrgrpids']
			? CArrayHelper::renameObjectsKeys(API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $filter['usrgrpids']
			]), ['usrgrpid' => 'id'])
			: [];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['users'] = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname', 'autologout', 'attempt_failed', 'roleid',
				'userdirectoryid', 'ts_provisioned'
			],
			'selectUsrgrps' => ['name', 'gui_access', 'users_status'],
			'selectRole' => ['name'],
			'search' => [
				'username' => ($filter['username'] === '') ? null : $filter['username'],
				'name' => ($filter['name'] === '') ? null : $filter['name'],
				'surname' => ($filter['surname'] === '') ? null : $filter['surname']
			],
			'filter' => ['roleid' => ($filter['roles'] == []) ? null : $filter['roles']],
			'usrgrpids' => ($filter['usrgrpids'] == []) ? null : $filter['usrgrpids'],
			'getAccess' => true,
			'limit' => $limit
		]);

		$userdirectoryids = array_column($data['users'], 'userdirectoryid', 'userdirectoryid');
		$data['idp_names'] = [];

		if ($userdirectoryids) {
			$data['idp_names'] = API::UserDirectory()->get([
				'output' => ['name', 'idp_type'],
				'userdirectoryids' => array_keys($userdirectoryids),
				'preservekeys' => true
			]);
		}

		foreach ($data['users'] as &$user) {
			$user['role_name'] = $user['role'] ? $user['role']['name'] : '';
		}
		unset($user);

		// data sort and pager
		CArrayHelper::sort($data['users'], [['field' => $sortfield, 'order' => $sortorder]]);

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('user.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['users'], $sortorder,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		// set default lastaccess time to 0
		foreach ($data['users'] as $user) {
			$data['sessions'][$user['userid']] = ['lastaccess' => 0];
		}

		$db_sessions = DBselect(
			'SELECT s.userid,MAX(s.lastaccess) AS lastaccess,s.status'.
			' FROM sessions s'.
			' WHERE '.dbConditionInt('s.userid', array_column($data['users'], 'userid')).
			' GROUP BY s.userid,s.status'
		);
		while ($db_session = DBfetch($db_sessions)) {
			if ($data['sessions'][$db_session['userid']]['lastaccess'] < $db_session['lastaccess']) {
				$data['sessions'][$db_session['userid']] = $db_session;
			}
		}

		$data['config'] = [
			'login_attempts' => CSettingsHelper::get(CSettingsHelper::LOGIN_ATTEMPTS),
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of users'));
		$this->setResponse($response);
	}
}
