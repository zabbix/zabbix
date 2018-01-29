<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			'sort' =>		'in name',
			'sortorder' =>	'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>	'in 1',
			'fullscreen' =>	'in 0,1'
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

		$sortField = $this->getInput('sort', CProfile::get('web.dashbrd.list.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.dashbrd.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.dashbrd.list.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.dashbrd.list.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$config = select_config();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'fullscreen' => $this->getInput('fullscreen', '0'),
			'sort' => $sortField,
			'sortorder' => $sortOrder
		];

		// list of dashboards
		$data['dashboards'] = API::Dashboard()->get([
			'output' => ['dashboardid', 'name'],
			'limit' => $config['search_limit'] + 1,
			'preservekeys' => true
		]);

		// sorting & paging
		order_result($data['dashboards'], $sortField, $sortOrder);

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'dashboard.list');
		if ($data['fullscreen']) {
			$url->setArgument('fullscreen', '1');
		}

		$data['paging'] = getPagingLine($data['dashboards'], $sortOrder, $url);

		if ($data['dashboards']) {
			$this->prepareEditableFlag($data['dashboards']);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboards'));
		$this->setResponse($response);
	}
}
