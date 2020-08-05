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


/**
 * controller dashboard list
 *
 */
class CControllerDashboardList extends CControllerDashboardAbstract {

	protected function init() {
		$this->disableSIDValidation();
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
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		CProfile::delete('web.dashbrd.dashboardid');
		CProfile::update('web.dashbrd.list_was_opened', 1, PROFILE_TYPE_INT);

		$sort_field = $this->getInput('sort', CProfile::get('web.dashbrd.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.dashbrd.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.dashbrd.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.dashbrd.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.dashbrd.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.dashbrd.filter_show', $this->getInput('filter_show', DASHBOARD_FILTER_SHOW_ALL),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.dashbrd.filter_name');
			CProfile::delete('web.dashbrd.filter_show');
		}

		$filter = [
			'name' => CProfile::get('web.dashbrd.filter_name', ''),
			'show' => CProfile::get('web.dashbrd.filter_show', DASHBOARD_FILTER_SHOW_ALL)
		];

		$config = select_config();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.dashbrd.filter',
			'active_tab' => CProfile::get('web.dashbrd.filter.active', 1)
		];

		// list of dashboards
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
			'limit' => $config['search_limit'] + 1,
			'preservekeys' => true
		]);
		order_result($data['dashboards'], $sort_field, $sort_order);

		// pager
		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('dashboard.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['dashboards'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		if ($data['dashboards']) {
			foreach ($data['dashboards'] as &$dashboard) {
				$tags = [];

				if ($dashboard['userid'] == CWebUser::$data['userid']) {
					$tags[] = ['tag' => _('My'), 'value' => '', 'class' => ZBX_STYLE_GREEN_BG];
				}

				if ($dashboard['private'] == PUBLIC_SHARING || count($dashboard['users']) > 0
						|| count($dashboard['userGroups']) > 0) {
					$tags[] = ['tag' => _('Shared'), 'value' => '', 'class' => ZBX_STYLE_YELLOW_BG];
				}

				$dashboard['tags'] = $tags;
			}
			unset($dashboard);

			$this->prepareEditableFlag($data['dashboards']);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboards'));
		$this->setResponse($response);
	}
}
