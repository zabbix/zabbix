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


class CControllerConnectorList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>	'string',
			'filter_status' =>	'in '.implode(',', [-1, ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]),
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'sort' =>			'in '.implode(',', ['name', 'data_type']),
			'sortorder'	=>		'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.connector.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.connector.filter.status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.connector.filter.name');
			CProfile::delete('web.connector.filter.status');
		}

		$filter = [
			'name' => CProfile::get('web.connector.filter.name', ''),
			'status' => CProfile::get('web.connector.filter.status', -1)
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.connector.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.connector.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.connector.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.connector.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'filter' => $filter,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'profileIdx' => 'web.connector.filter',
			'active_tab' => CProfile::get('web.connector.filter.active', 1)
		];

		$options = [
			'output' => ['connectorid', 'name', 'data_type', 'status'],
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name']
			],
			'filter' => [
				'status' => $filter['status'] != -1 ? $filter['status'] : null
			],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			'preservekeys' => true
		];

		$data['connectors'] = API::Connector()->get($options);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('connector.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['connectors'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Connectors'));
		$this->setResponse($response);
	}
}
