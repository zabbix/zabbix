<?php declare(strict_types=1);
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


class CControllerTokenList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort'                   => 'in name,user,expires_at,creator,lastaccess,status',
			'sortorder'              => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck'                => 'in 1',
			'filter_set'             => 'in 1',
			'filter_rst'             => 'in 1',
			'filter_name'            => 'string',
			'filter_userids'         => 'array_db users.userid',
			'filter_expires_state'   => 'in 1',
			'filter_expires_days'    => 'int32',
			'filter_creator_userids' => 'array_db users.userid',
			'filter_status'          => 'in -1,'.ZBX_AUTH_TOKEN_ENABLED.','.ZBX_AUTH_TOKEN_DISABLED,
			'page'                   => 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		return ($this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)
			&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
		);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.token.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.token.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.token.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.token.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.token.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::updateArray('web.token.filter_userids', $this->getInput('filter_userids', []), PROFILE_TYPE_ID);
			CProfile::update('web.token.filter_expires_state', $this->getInput('filter_expires_state', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.token.filter_expires_days', $this->getInput('filter_expires_days', 14),
				PROFILE_TYPE_INT
			);
			CProfile::updateArray('web.token.filter_creator_userids', $this->getInput('filter_creator_userids', []),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.token.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.token.filter_name');
			CProfile::deleteIdx('web.token.filter_userids');
			CProfile::delete('web.token.filter_expires_state');
			CProfile::delete('web.token.filter_expires_days');
			CProfile::deleteIdx('web.token.filter_creator_userids');
			CProfile::delete('web.token.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.token.filter_name', ''),
			'userids' => CProfile::getArray('web.token.filter_userids', []),
			'expires_state' => CProfile::get('web.token.filter_expires_state', 0),
			'expires_days' => CProfile::get('web.token.filter_expires_days', 14),
			'creator_userids' => CProfile::getArray('web.token.filter_creator_userids', []),
			'status' => CProfile::get('web.token.filter_status', -1)
		];

		$data = [
			'ms_users' => [],
			'ms_creators' => [],
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.token.filter',
			'active_tab' => CProfile::get('web.token.filter.active', 1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['tokens'] = API::Token()->get([
			'output' => ['tokenid', 'name', 'userid', 'expires_at', 'created_at', 'creator_userid', 'lastaccess',
				'status'
			],
			'userids' => $filter['userids'] ? $filter['userids'] : null,
			'valid_at' => $filter['expires_state'] ? time() : null,
			'expired_at' => $filter['expires_state']
				? bcadd((string) time(), bcmul($filter['expires_days'], (string) SEC_PER_DAY, 0), 0)
				: null,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'creator_userid' => $filter['creator_userids'] ? $filter['creator_userids'] : null,
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'limit' => $limit,
			'preservekeys' => true
		]);

		if ($data['tokens'] === false) {
			$data['tokens'] = [];
		}

		$userids = array_column($data['tokens'], 'userid', 'userid');
		$userids += array_column($data['tokens'], 'creator_userid', 'creator_userid');
		$userids += array_flip($filter['userids']);
		$userids += array_flip($filter['creator_userids']);

		$users = $userids
			? API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];

		$now = time();
		array_walk($data['tokens'], function (array &$token) use ($users, $now) {
			$token['user'] = getUserFullname($users[$token['userid']]);

			$token['creator'] = array_key_exists($token['creator_userid'], $users)
				? getUserFullname($users[$token['creator_userid']])
				: null;

			$token['is_expired'] = $token['expires_at']
				? $now > $token['expires_at']
				: false;
		});

		CArrayHelper::sort($data['tokens'], [['field' => $sort_field, 'order' => $sort_order]]);

		foreach ($filter['userids'] as $userid) {
			if (!array_key_exists($userid, $users)) {
				continue;
			}

			$data['ms_users'][] = ['id' => $userid, 'name' => getUserFullname($users[$userid])];
		}

		CArrayHelper::sort($data['ms_users'], ['name']);

		foreach ($filter['creator_userids'] as $userid) {
			if (!array_key_exists($userid, $users)) {
				continue;
			}

			$data['ms_creators'][] = ['id' => $userid, 'name' => getUserFullname($users[$userid])];
		}

		CArrayHelper::sort($data['ms_creators'], ['name']);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('token.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['tokens'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('API tokens'));
		$this->setResponse($response);
	}
}
