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


class CControllerCorrelationList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'sort' =>			'in name,status',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in -1,'.ZBX_CORRELATION_ENABLED.','.ZBX_CORRELATION_DISABLED,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.correlation.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.correlation.php.sortorder', ZBX_SORT_UP));
		CProfile::update('web.correlation.php.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.correlation.php.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.correlation.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.correlation.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.correlation.filter_name');
			CProfile::delete('web.correlation.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.correlation.filter_name', ''),
			'status' => CProfile::get('web.correlation.filter_status', -1)
		];

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.correlation.filter',
			'active_tab' => CProfile::get('web.correlation.filter.active', 1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['correlations'] = API::Correlation()->get([
			'output' => ['correlationid', 'name', 'description', 'status'],
			'selectFilter' => ['conditions'],
			'selectOperations' => ['type'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'editable' => true,
			'limit' => $limit
		]);

		CArrayHelper::sort($data['correlations'], [['field' => $sort_field, 'order' => $sort_order]]);

		// pager
		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('correlation.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['correlations'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$groupids = [];

		foreach ($data['correlations'] as &$correlation) {
			$groupids += array_column($correlation['filter']['conditions'], 'groupid', 'groupid');
		}
		unset($correlation);

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			$data['group_names'] = array_column($groups, 'name', 'groupid');
		}
		else {
			$data['group_names'] = [];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event correlation rules'));
		$this->setResponse($response);
	}
}
