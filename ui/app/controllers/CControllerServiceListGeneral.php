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


abstract class CControllerServiceListGeneral extends CController {

	private const WITHOUT_PARENTS_SERVICEID = 0;

	private const FILTER_DEFAULT_NAME = '';
	private const FILTER_DEFAULT_STATUS = SERVICE_STATUS_ANY;
	private const FILTER_DEFAULT_EVALTYPE = TAG_EVAL_TYPE_AND_OR;

	protected $is_filter_empty = true;

	protected $service;

	protected function doAction(): void {
		if ($this->hasInput('serviceid')) {
			$db_service = API::Service()->get([
				'output' => ['serviceid', 'name', 'status', 'goodsla'],
				'serviceids' => $this->getInput('serviceid'),
				'selectParents' => ['serviceid'],
				'selectTags' => ['tag', 'value']
			]);

			if (!$db_service) {
				$this->setResponse(new CControllerResponseData([
					'error' => _('No permissions to referred object or it does not exist!')
				]));

				return;

			}

			$this->service = reset($db_service);
			$this->service['tags'] = makeTags([$this->service], true, 'serviceid', ZBX_TAG_COUNT_DEFAULT);
			$this->service['parents'] = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => array_column($this->service['parents'], 'serviceid'),
				'selectChildren' => API_OUTPUT_COUNT
			]);
		}
	}

	protected function updateFilter(): void {
		$serviceid = $this->getInput('serviceid', self::WITHOUT_PARENTS_SERVICEID);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.service.filter_name', $this->getInput('filter_name', self::FILTER_DEFAULT_NAME),
				PROFILE_TYPE_STR
			);

			CProfile::update('web.service.filter_status', $this->getInput('filter_status', self::FILTER_DEFAULT_STATUS),
				PROFILE_TYPE_INT
			);

			CProfile::update('web.service.filter.evaltype',
				$this->getInput('filter_evaltype', self::FILTER_DEFAULT_EVALTYPE), PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}
				$filter_tags['tags'][] = $tag['tag'];
				$filter_tags['values'][] = $tag['value'];
				$filter_tags['operators'][] = $tag['operator'];
			}
			CProfile::updateArray('web.service.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.service.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.service.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst') || (CProfile::get('web.service.serviceid') != $serviceid)) {
			CProfile::update('web.service.serviceid', $serviceid, PROFILE_TYPE_ID);
			CProfile::delete('web.service.filter_name');
			CProfile::delete('web.service.filter_status');
			CProfile::deleteIdx('web.service.filter.evaltype');
			CProfile::deleteIdx('web.service.filter.tags.tag');
			CProfile::deleteIdx('web.service.filter.tags.value');
			CProfile::deleteIdx('web.service.filter.tags.operator');
		}
	}

	protected function getFilter(): array {
		$filter = [
			'serviceid' => CProfile::get('web.service.serviceid', self::WITHOUT_PARENTS_SERVICEID),
			'name' => CProfile::get('web.service.filter_name', self::FILTER_DEFAULT_NAME),
			'status' => CProfile::get('web.service.filter_status', self::FILTER_DEFAULT_STATUS),
			'evaltype' => CProfile::get('web.service.filter.evaltype', self::FILTER_DEFAULT_EVALTYPE),
			'tags' => []
		];

		foreach (CProfile::getArray('web.service.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.service.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.service.filter.tags.operator', null, $i)
			];
		}

		if ($filter['name'] != self::FILTER_DEFAULT_NAME
				|| $filter['status'] != self::FILTER_DEFAULT_STATUS
				|| $filter['evaltype'] != self::FILTER_DEFAULT_EVALTYPE
				|| $filter['tags']) {
			$this->is_filter_empty = false;
		}

		return $filter;
	}

	protected function getPath(): array {
		if ($this->service === null) {
			return [];
		}

		$path = [];
		$db_service = $this->service;

		while (true) {
			if ($this->hasInput('path')) {
				$path_serviceids = $this->getInput('path', []);

				$db_services = API::Service()->get([
					'output' => [],
					'serviceids' => $path_serviceids,
					'preservekeys' => true
				]);

				foreach (array_reverse($path_serviceids) as $serviceid) {
					if (array_key_exists($serviceid, $db_services)) {
						$path[] = $serviceid;
					}
				}

				break;
			}

			if (!$db_service['parents']) {
				break;
			}

			$db_service = API::Service()->get([
				'output' => ['serviceid'],
				'serviceids' => $db_service['parents'][0]['serviceid'],
				'selectParents' => ['serviceid']
			]);

			if (!$db_service) {
				break;
			}

			$db_service = reset($db_service);

			$path[] = $db_service['serviceid'];
		}

		return array_reverse($path);
	}

	protected function getBreadcrumbs($path): array {
		$breadcrumbs = [[
			'name' => _('All services'),
			'curl' => (new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		]];

		$db_services = API::Service()->get([
			'output' => ['name'],
			'serviceids' => $path,
			'preservekeys' => true
		]);

		$parent_serviceids = [];

		foreach ($path as $serviceid) {
			$breadcrumbs[] = [
				'name' => $db_services[$serviceid]['name'],
				'curl' => (new CUrl('zabbix.php'))
					->setArgument('action', $this->getAction())
					->setArgument('path', $parent_serviceids)
					->setArgument('serviceid', $serviceid)
			];

			$parent_serviceids[] = $serviceid;
		}

		if ($this->service !== null) {
			$breadcrumbs[] = [
				'name' => $this->service['name'],
				'curl' => (new CUrl('zabbix.php'))
					->setArgument('action', $this->getAction())
					->setArgument('path', $parent_serviceids)
					->setArgument('serviceid', $this->service['serviceid'])
			];
		}

		if ($this->hasInput('filter_set')) {
			$breadcrumbs[] = [
				'name' => _('Filter results')
			];
		}

		return $breadcrumbs;
	}

	protected function prepareData(array $filter): array {
		if ($filter['status'] == SERVICE_STATUS_OK) {
			$filter_status = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}
		elseif ($filter['status'] == SERVICE_STATUS_PROBLEM) {
			$filter_status = array_column(getSeverities(TRIGGER_SEVERITY_INFORMATION), 'value');
		}
		else {
			$filter_status = null;
		}

		$db_services = API::Service()->get([
			'output' => [],
			'selectParents' => ($filter['serviceid'] == self::WITHOUT_PARENTS_SERVICEID && $this->is_filter_empty)
				? null
				: ['serviceid'],
			'parentids' => $this->is_filter_empty ? $filter['serviceid'] : null,
			'search' => ($filter['name'] === '')
				? null
				: ['name' => $filter['name']],
			'filter' => [
				'status' => $filter_status
			],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'preservekeys' => true
		]);

		if (!$db_services || $this->is_filter_empty || $filter['serviceid'] == self::WITHOUT_PARENTS_SERVICEID) {
			return array_keys($db_services);
		}

		$filtered_serviceids = [];

		do {
			$parentids = [];

			foreach ($db_services as $db_serviceid => $db_service) {
				$service_parentids = array_column($db_service['parents'], 'serviceid', 'serviceid');

				if (array_key_exists($filter['serviceid'], $service_parentids)) {
					$filtered_serviceids[$db_serviceid] = true;
					unset($db_services[$db_serviceid]);
				}
				else {
					$parentids += $service_parentids;
				}
			}

			$x_services = API::Service()->get([
				'output' => [],
				'selectParents' => ['serviceid'],
				'serviceids' => $parentids,
				'preservekeys' => true
			]);

			if (!$x_services) {
				break;
			}

			foreach ($db_services as &$db_service) {
				$service_parentids = array_column($db_service['parents'], 'serviceid', 'serviceid');

				$new_parentids = [];
				foreach ($service_parentids as $service_parentid) {
					$new_parentids += array_column($x_services[$service_parentid]['parents'], null, 'serviceid');
				}

				$db_service['parents'] = $new_parentids;
			}
			unset($db_service);
		} while (true);

		return array_keys($filtered_serviceids);
	}
}
