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


class CControllerServiceListRefresh extends CControllerServiceListGeneral {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>			'db services.serviceid',
			'path' =>				'array',
			'filter_name' =>		'required|string',
			'filter_status' =>		'required|in '.SERVICE_STATUS_ANY.','.SERVICE_STATUS_OK.','.SERVICE_STATUS_PROBLEM,
			'filter_evaltype' =>	'required|in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>		'array',
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES);
	}

	protected function doAction(): void {
		parent::doAction();

		$path = $this->getPath();

		$filter = [
			'serviceid' => $this->service !== null ? $this->service['serviceid'] : self::WITHOUT_PARENTS_SERVICEID,
			'name' => $this->getInput('filter_name'),
			'status' => $this->getInput('filter_status'),
			'without_children' => self::FILTER_DEFAULT_WITHOUT_CHILDREN,
			'without_problem_tags' => self::FILTER_DEFAULT_WITHOUT_PROBLEM_TAGS,
			'tag_source' => self::FILTER_DEFAULT_TAG_SOURCE,
			'evaltype' => $this->getInput('filter_evaltype'),
			'tags' => $this->getInput('filter_tags', [])
		];

		$is_filtered = !$this->isDefaultFilter($filter);

		$view_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'service.list')
			->setArgument('path', $path ?: null)
			->setArgument('serviceid', $this->service !== null ? $this->service['serviceid'] : null)
			->setArgument('filter_name', $filter['name'])
			->setArgument('filter_status', $filter['status'])
			->setArgument('filter_evaltype', $filter['evaltype'])
			->setArgument('filter_tags', $filter['tags']);

		$data = [
			'path' => $path,
			'is_filtered' => $is_filtered,
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'service' => $this->service
		];

		$db_serviceids = $this->prepareData($filter);

		$page_num = $this->getInput('page', 1);
		$data['paging'] = CPagerHelper::paginate($page_num, $db_serviceids, ZBX_SORT_UP, $view_curl);

		$data['services'] = API::Service()->get([
			'output' => ['serviceid', 'name', 'status', 'goodsla', 'showsla'],
			'selectParents' => $is_filtered ? ['serviceid', 'name'] : null,
			'selectChildren' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'serviceids' => $db_serviceids,
			'sortfield' => ['sortorder', 'name'],
			'sortorder' => ZBX_SORT_UP,
			'preservekeys' => true
		]);

		$data['tags'] = makeTags($data['services'], true, 'serviceid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		$data['user']['debug_mode'] = $this->getDebugMode();

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Services'));
		$this->setResponse($response);
	}
}
