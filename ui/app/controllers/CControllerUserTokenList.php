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


class CControllerUserTokenList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort'                   => 'in name,expires_at,lastaccess,status',
			'sortorder'              => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck'                => 'in 1',
			'filter_set'             => 'in 1',
			'filter_rst'             => 'in 1',
			'filter_name'            => 'string',
			'filter_expires_state'   => 'in 1',
			'filter_expires_days'    => 'int32',
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

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.user.token.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.user.token.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.user.token.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.user.token.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.user.token.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.token.filter_expires_state', $this->getInput('filter_expires_state', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.user.token.filter_expires_days', $this->getInput('filter_expires_days', 14),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.user.token.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.user.token.filter_name');
			CProfile::delete('web.user.token.filter_expires_state');
			CProfile::delete('web.user.token.filter_expires_days');
			CProfile::delete('web.user.token.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.user.token.filter_name', ''),
			'expires_state' => CProfile::get('web.user.token.filter_expires_state', 0),
			'expires_days' => CProfile::get('web.user.token.filter_expires_days', 14),
			'status' => CProfile::get('web.user.token.filter_status', -1)
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.user.token.filter',
			'active_tab' => CProfile::get('web.user.token.filter.active', 1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['tokens'] = API::Token()->get([
			'output' => ['tokenid', 'name', 'expires_at', 'created_at', 'lastaccess', 'status'],
			'userids' => CWebUser::$data['userid'],
			'valid_at' => $filter['expires_state'] ? time() : null,
			'expired_at' => $filter['expires_state']
				? bcadd((string) time(), bcmul($filter['expires_days'], (string) SEC_PER_DAY, 0), 0)
				: null,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'limit' => $limit,
			'preservekeys' => true
		]);

		if ($data['tokens'] === false) {
			$data['tokens'] = [];
		}

		$now = time();
		array_walk($data['tokens'], function (array &$token) use ($now) {
			$token['is_expired'] = $token['expires_at']
				? $now > $token['expires_at']
				: false;
		});

		CArrayHelper::sort($data['tokens'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('user.token.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['tokens'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('API tokens'));
		$this->setResponse($response);
	}
}
