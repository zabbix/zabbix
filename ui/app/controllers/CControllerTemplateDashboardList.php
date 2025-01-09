<?php declare(strict_types = 0);
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


class CControllerTemplateDashboardList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
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

		return isWritableHostTemplates((array) $this->getInput('templateid'));
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.templates.dashboard.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.templates.dashboard.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.templates.dashboard.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.templates.dashboard.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$dashboards = API::TemplateDashboard()->get([
			'output' => ['name', 'templateid'],
			'templateids' => [$this->getInput('templateid')],
			'sortfield' => $sort_field,
			'limit' => $limit,
			'editable' => true,
			'preservekeys' => true
		]);

		CArrayHelper::sort($dashboards, [['field' => $sort_field, 'order' => $sort_order]]);

		// pager
		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('template.dashboard.list', $page_num);
		$paging = CPagerHelper::paginate($page_num, $dashboards, $sort_order,
			(new CUrl('zabbix.php'))
				->setArgument('action', $this->getAction())
				->setArgument('templateid', $this->getInput('templateid'))
		);

		$data = [
			'templateid' => $this->getInput('templateid'),
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'dashboards' => $dashboards,
			'paging' => $paging
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of dashboards'));
		$this->setResponse($response);
	}
}
