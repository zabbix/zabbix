<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			'serviceid' =>			'db services.serviceid',
			'path' =>				'array',
			'filter_name' =>		'string',
			'filter_status' =>		'in '.implode(',', [SERVICE_STATUS_ANY, SERVICE_STATUS_OK, SERVICE_STATUS_PROBLEM]),
			'filter_evaltype' =>	'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>		'array',
			'filter_set' =>			'in 1',
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES)) {
			return false;
		}

		return parent::checkPermissions();
	}

	protected function doAction(): void {
		parent::doAction();

		$path = $this->getPath();

		$filter = [
			'serviceid' => $this->service !== null ? $this->service['serviceid'] : self::WITHOUT_PARENTS_SERVICEID,
			'name' => $this->getInput('filter_name', self::FILTER_DEFAULT_NAME),
			'status' => $this->getInput('filter_status', self::FILTER_DEFAULT_STATUS),
			'without_children' => self::FILTER_DEFAULT_WITHOUT_CHILDREN,
			'without_problem_tags' => self::FILTER_DEFAULT_WITHOUT_PROBLEM_TAGS,
			'tag_source' => self::FILTER_DEFAULT_TAG_SOURCE,
			'evaltype' => $this->getInput('filter_evaltype', self::FILTER_DEFAULT_EVALTYPE),
			'tags' => [],
			'filter_set' => $this->hasInput('filter_set')
		];

		foreach ($this->getInput('filter_tags', []) as $tag) {
			if (!array_key_exists('tag', $tag) || !array_key_exists('value', $tag)
					|| ($tag['tag'] === '' && $tag['value'] === '')) {
				continue;
			}
			$filter['tags'][] = $tag;
		}

		$reset_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'service.list')
			->setArgument('path', $path ?: null)
			->setArgument('serviceid', $this->service !== null ? $this->service['serviceid'] : null);

		$paging_curl = clone $reset_curl;

		if ($filter['filter_set']) {
			$paging_curl
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags'])
				->setArgument('filter_set', 1);
		}

		$edit_mode_curl = (clone $paging_curl)
			->setArgument('action', 'service.list.edit')
			->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null);

		$refresh_curl = (clone $paging_curl)
			->setArgument('action', 'service.list.refresh')
			->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null);

		$data = [
			'can_edit' => $this->canEdit(),
			'can_monitor_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'path' => $path,
			'breadcrumbs' => $this->getBreadcrumbs($path, $filter['filter_set']),
			'filter' => $filter,
			'is_filtered' => $filter['filter_set'],
			'active_tab' => CProfile::get('web.service.filter.active', 1),
			'reset_curl' => $reset_curl,
			'edit_mode_url' => $edit_mode_curl->getUrl(),
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'service' => $this->service
		];

		if ($this->service !== null && !$filter['filter_set']) {
			$data += $this->getSlas();
		}

		$db_serviceids = self::getServiceIds($filter, $filter['filter_set']);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('service.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $db_serviceids, ZBX_SORT_UP, $paging_curl);

		$data['services'] = API::Service()->get([
			'output' => ['serviceid', 'name', 'status', 'created_at', 'readonly'],
			'selectParents' => $filter['filter_set'] ? ['serviceid', 'name'] : null,
			'selectChildren' => API_OUTPUT_COUNT,
			'selectProblemEvents' => ['eventid', 'severity', 'name'],
			'selectTags' => ['tag', 'value'],
			'serviceids' => $db_serviceids,
			'sortfield' => ['sortorder', 'name'],
			'sortorder' => ZBX_SORT_UP,
			'preservekeys' => true
		]);

		self::extendProblemEvents($data['services']);

		$data['tags'] = makeTags($data['services'], true, 'serviceid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Services'));
		$this->setResponse($response);
	}
}
