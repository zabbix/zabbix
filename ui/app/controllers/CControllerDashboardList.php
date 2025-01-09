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


/**
 * controller dashboard list
 *
 */
class CControllerDashboardList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'in 1',
			'page' =>			'ge 1',
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_show' =>	'in '.DASHBOARD_FILTER_SHOW_ALL.','.DASHBOARD_FILTER_SHOW_MY
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD);
	}

	protected function doAction() {
		CProfile::delete('web.dashboard.dashboardid');
		CProfile::update('web.dashboard.list_was_opened', 1, PROFILE_TYPE_INT);

		$sort_field = $this->getInput('sort', CProfile::get('web.dashboard.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.dashboard.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.dashboard.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.dashboard.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.dashboard.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.dashboard.filter_show', $this->getInput('filter_show', DASHBOARD_FILTER_SHOW_ALL),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.dashboard.filter_name');
			CProfile::delete('web.dashboard.filter_show');
		}

		$filter = [
			'name' => CProfile::get('web.dashboard.filter_name', ''),
			'show' => CProfile::get('web.dashboard.filter_show', DASHBOARD_FILTER_SHOW_ALL)
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.dashboard.filter',
			'active_tab' => CProfile::get('web.dashboard.filter.active', 1),
			'allowed_edit' => CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS)
		];

		// list of dashboards
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['dashboards'] = API::Dashboard()->get([
			'output' => ['dashboardid', 'name', 'userid', 'private'],
			'selectUsers' => ['userid'],
			'selectUserGroups' => ['usrgrpid'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'userid' => ($filter['show'] == DASHBOARD_FILTER_SHOW_ALL) ? null : CWebUser::$data['userid']
			],
			'limit' => $limit,
			'preservekeys' => true
		]);
		order_result($data['dashboards'], $sort_field, $sort_order);

		// pager
		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('dashboard.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['dashboards'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		CDashboardHelper::updateEditableFlag($data['dashboards']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboards'));
		$this->setResponse($response);
	}
}
