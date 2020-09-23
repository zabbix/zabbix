<?php declare(strict_types=1);
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


class CControllerTemplateDashboardList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'templateid' => 'required|db dashboard.templateid',
			'sort' => 'in name',
			'sortorder' => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' => 'in 1',
			'page' => 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.dashboards.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.dashboards.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.dashboards.php.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.dashboards.php.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'paging' => null,
			'dashboards' => [],
			'sort' => $sort_field,
			'templateid' => $this->getInput('templateid', null),
			'uncheck' => $this->hasInput('uncheck'),
			'sortorder' => $sort_order
		];

		$data['dashboards'] = $this->fetchDashboards($sort_field, $sort_order);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('template.dashboard.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['dashboards'], $sort_order,
			(new CUrl('zabbix.php'))
				->setArgument('action', $this->getAction())
				->setArgument('templateid', $this->getInput('templateid'))
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of dashboards'));
		$this->setResponse($response);
	}

	/**
	 * Get list of dashboards.
	 *
	 * @param string $sort_field
	 * @param string $sort_order
	 *
	 * @return array
	 */
	private function fetchDashboards(string $sort_field, string $sort_order): array {

		// Get applications.
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$dashboards = API::TemplateDashboard()->get([
			'output' => API_OUTPUT_EXTEND,
			'templateids' => [$this->getInput('templateid', null)],
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit
		]);

		CArrayHelper::sort($dashboards, [['field' => $sort_field, 'order' => $sort_order]]);

		return $dashboards;
	}
}
