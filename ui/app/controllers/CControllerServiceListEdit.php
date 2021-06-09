<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerServiceListEdit extends CControllerServiceListGeneral {

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICES)
			&& $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES);
	}

	protected function doAction(): void {
		$serviceid = $this->hasInput('serviceid') ? $this->getInput('serviceid') : null;

		$this->updateFilter();
		$filter = $this->getFilter();

		$data = [
			'filter' => $filter,
			'active_tab' => CProfile::get('web.service.filter.active', 1),
			'view_curl' => (new CUrl('zabbix.php'))->setArgument('action', 'service.list.edit'),
			'refresh_url' => (new CUrl('zabbix.php'))
				->setArgument('action', 'service.list.edit.refresh')
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags'])
				->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null)
				->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'serviceid' => $serviceid,
			'tags' => []
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$db_servicesids = API::Service()->get([
			'output' => [],
			'sortfield' => ['sortorder', 'name'],
			'sortorder' => ZBX_SORT_UP,
			'preservekeys' => true,
			'limit' => $limit
		]);

		$data['paging'] = CPagerHelper::paginate($this->getInput('page', 1), $db_servicesids, ZBX_SORT_UP,
			$data['view_curl']
		);

		$data['services'] = API::Service()->get([
			'output' => ['name', 'status', 'goodsla', 'sortorder'],
			'serviceids' => array_keys($db_servicesids),
			'selectParent' => ['serviceid', 'name', 'sortorder'],
			'selectDependencies' => [],
			'selectTrigger' => ['description'],
			'preservekeys' => true
		]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Services'));
		$this->setResponse($response);
	}
}
