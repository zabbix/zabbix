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


class CControllerServiceList extends CControllerServiceListGeneral {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'serviceid' => 'db services.serviceid',
			'path' => 'array',
			'filter_name' => 'string',
			'filter_status' => 'in '.SERVICE_STATUS_ANY.','.SERVICE_STATUS_OK.','.SERVICE_STATUS_PROBLEM,
			'filter_evaltype' => 'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' => 'array',
			'filter_set' => 'in 1',
			'filter_rst' => 'in 1',
			'page' => 'ge 1',
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES);
	}

	protected function doAction(): void {
		parent::doAction();

		$path = $this->getPath();

		$this->updateFilter();
		$filter = $this->getFilter();

		$view_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'service.list.edit')
			->setArgument('path', $path ?: null)
			->setArgument('serviceid', $this->service !== null ? $this->service['serviceid'] : null);
		if ($this->is_filtered) {
			$view_url
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags']);
		}

		$data = [
			'can_edit' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES),
			'path' => $path,
			'breadcrumbs' => $this->getBreadcrumbs($path),
			'filter' => $filter,
			'is_filtered' => $this->is_filtered,
			'active_tab' => CProfile::get('web.service.filter.active', 1),
			'view_curl' => $view_url,
			'refresh_url' => (new CUrl('zabbix.php'))
				->setArgument('action', 'service.list.refresh')
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags'])
				->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null)
				->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'service' => $this->service
		];

		$db_serviceids = $this->prepareData($filter);

		$page_num = $this->getInput('page', 1);
		$data['paging'] = CPagerHelper::paginate($page_num, $db_serviceids, ZBX_SORT_UP,
			$data['view_curl']
		);
		CPagerHelper::savePage('service.list', $page_num);
		$data['page'] =  $page_num > 1 ? $page_num : null;

		$data['services'] = API::Service()->get([
			'output' => ['serviceid', 'name', 'status', 'goodsla', 'showsla'],
			'selectParents' => $this->is_filtered ? ['serviceid', 'name'] : null,
			'selectChildren' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'serviceids' => $db_serviceids,
			'sortfield' => ['sortorder', 'name'],
			'sortorder' => ZBX_SORT_UP,
			'preservekeys' => true
		]);

		$data['tags'] = makeTags($data['services'], true, 'serviceid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Services'));
		$this->setResponse($response);
	}
}
